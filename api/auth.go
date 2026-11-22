package main

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/mail"
	"slices"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
	"golang.org/x/crypto/bcrypt"
)

const sessionCookie = "gz_admin"

type Admin struct {
	ID          int64      `db:"id" json:"id"`
	Name        string     `db:"name" json:"name"`
	Email       string     `db:"email" json:"email"`
	Role        string     `db:"role" json:"role"`
	Active      bool       `db:"active" json:"active"`
	LastLoginAt *time.Time `db:"last_login_at" json:"last_login_at"`
	CreatedAt   time.Time  `db:"created_at" json:"created_at"`
}

var roleLabels = map[string]string{"admin": "Quản trị viên", "editor": "Biên tập nội dung", "sales": "Tư vấn / chăm sóc khách"}

// Quyền theo vai trò
var perms = map[string][]string{
	"admin":  {"leads.view", "leads.edit", "leads.export", "leads.delete", "offers", "landings", "settings", "admins"},
	"editor": {"leads.view", "offers", "landings"},
	"sales":  {"leads.view", "leads.edit", "leads.export"},
}

func can(role, perm string) bool { return perm == "" || slices.Contains(perms[role], perm) }

type ctxKey struct{}

func currentAdmin(r *http.Request) *Admin { a, _ := r.Context().Value(ctxKey{}).(*Admin); return a }

func hashToken(t string) string {
	h := sha256.Sum256([]byte(t))
	return hex.EncodeToString(h[:])
}

// Bọc handler: bắt buộc đăng nhập + đúng quyền.
// Yêu cầu thay đổi dữ liệu phải có header X-Requested-With (chặn CSRF từ form trang khác).
func (a *App) auth(perm string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet && r.Header.Get("X-Requested-With") != "XMLHttpRequest" {
			fail(w, 403, "Yêu cầu không hợp lệ.")
			return
		}
		c, err := r.Cookie(sessionCookie)
		if err != nil || c.Value == "" {
			fail(w, 401, "Phiên đăng nhập đã hết. Hãy đăng nhập lại.")
			return
		}
		rows, err := a.db.Query(r.Context(), `SELECT a.id, a.name, a.email, a.role, a.active, a.last_login_at, a.created_at
			FROM sessions s JOIN admins a ON a.id = s.admin_id WHERE s.token_hash = $1 AND s.expires_at > now() AND a.active`, hashToken(c.Value))
		if err != nil {
			serverError(w, err)
			return
		}
		ad, err := pgx.CollectOneRow(rows, pgx.RowToStructByName[Admin])
		if err != nil {
			fail(w, 401, "Phiên đăng nhập đã hết. Hãy đăng nhập lại.")
			return
		}
		if !can(ad.Role, perm) {
			fail(w, 403, "Tài khoản của bạn không có quyền làm việc này.")
			return
		}
		next(w, r.WithContext(context.WithValue(r.Context(), ctxKey{}, &ad)))
	}
}

func (a *App) startSession(w http.ResponseWriter, r *http.Request, adminID int64) error {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return err
	}
	token := hex.EncodeToString(b)
	exp := time.Now().Add(14 * 24 * time.Hour)
	if _, err := a.db.Exec(r.Context(), `INSERT INTO sessions (token_hash, admin_id, expires_at) VALUES ($1, $2, $3)`, hashToken(token), adminID, exp); err != nil {
		return err
	}
	_, _ = a.db.Exec(r.Context(), `DELETE FROM sessions WHERE expires_at < now()`)
	http.SetCookie(w, &http.Cookie{Name: sessionCookie, Value: token, Path: "/", Expires: exp, HttpOnly: true, Secure: a.cfg.CookieSecure, SameSite: http.SameSiteLaxMode})
	return nil
}

func (a *App) mePayload(ad *Admin) map[string]any {
	return map[string]any{"admin": ad, "role_label": roleLabels[ad.Role], "perms": perms[ad.Role]}
}

func (a *App) adminSetupStatus(w http.ResponseWriter, r *http.Request) {
	var n int
	if err := a.db.QueryRow(r.Context(), `SELECT COUNT(*) FROM admins`).Scan(&n); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"needs_setup": n == 0})
}

type adminInput struct {
	Name            string `json:"name"`
	Email           string `json:"email"`
	Password        string `json:"password"`
	CurrentPassword string `json:"current_password"`
	Role            string `json:"role"`
	Active          *bool  `json:"active"`
}

func (a *App) validateAdmin(ctx context.Context, in *adminInput, id int64, needPassword bool) map[string]string {
	errs := map[string]string{}
	in.Name = clip(in.Name, 80)
	in.Email = strings.ToLower(clip(in.Email, 120))
	if len([]rune(in.Name)) < 2 {
		errs["name"] = "Nhập họ tên."
	}
	if _, err := mail.ParseAddress(in.Email); err != nil {
		errs["email"] = "Email chưa đúng định dạng."
	} else {
		var taken bool
		_ = a.db.QueryRow(ctx, `SELECT EXISTS (SELECT 1 FROM admins WHERE email = $1 AND id <> $2)`, in.Email, id).Scan(&taken)
		if taken {
			errs["email"] = "Email này đã có tài khoản."
		}
	}
	if (needPassword || in.Password != "") && len(in.Password) < 8 {
		errs["password"] = "Mật khẩu cần ít nhất 8 ký tự."
	}
	return errs
}

// Tạo tài khoản đầu tiên (chỉ khi chưa có tài khoản nào)
func (a *App) adminSetup(w http.ResponseWriter, r *http.Request) {
	if r.Header.Get("X-Requested-With") != "XMLHttpRequest" {
		fail(w, 403, "Yêu cầu không hợp lệ.")
		return
	}
	ctx := r.Context()
	var in adminInput
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if errs := a.validateAdmin(ctx, &in, 0, true); len(errs) > 0 {
		failFields(w, errs)
		return
	}
	hash, _ := bcrypt.GenerateFromPassword([]byte(in.Password), bcrypt.DefaultCost)
	var id int64
	// Chỉ chèn khi bảng còn trống, tránh hai người cùng tạo
	err := a.db.QueryRow(ctx, `INSERT INTO admins (name, email, password_hash, role) SELECT $1, $2, $3, 'admin'
		WHERE NOT EXISTS (SELECT 1 FROM admins) RETURNING id`, in.Name, in.Email, string(hash)).Scan(&id)
	if err == pgx.ErrNoRows {
		fail(w, 409, "Website đã có tài khoản quản trị. Hãy đăng nhập.")
		return
	} else if err != nil {
		serverError(w, err)
		return
	}
	if err := a.startSession(w, r, id); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

func (a *App) adminLogin(w http.ResponseWriter, r *http.Request) {
	if r.Header.Get("X-Requested-With") != "XMLHttpRequest" {
		fail(w, 403, "Yêu cầu không hợp lệ.")
		return
	}
	ctx := r.Context()
	ip := a.clientIP(r)
	var fails int
	_ = a.db.QueryRow(ctx, `SELECT COUNT(*) FROM login_attempts WHERE ip = $1 AND created_at > now() - interval '15 minutes'`, ip).Scan(&fails)
	if fails >= 8 {
		fail(w, 429, "Đăng nhập sai quá nhiều lần. Hãy thử lại sau 15 phút.")
		return
	}
	var in adminInput
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	var id int64
	var hash string
	err := a.db.QueryRow(ctx, `SELECT id, password_hash FROM admins WHERE email = $1 AND active`, strings.ToLower(strings.TrimSpace(in.Email))).Scan(&id, &hash)
	if err != nil || bcrypt.CompareHashAndPassword([]byte(hash), []byte(in.Password)) != nil {
		_, _ = a.db.Exec(ctx, `INSERT INTO login_attempts (ip) VALUES ($1)`, ip)
		fail(w, 401, "Email hoặc mật khẩu không đúng.")
		return
	}
	_, _ = a.db.Exec(ctx, `UPDATE admins SET last_login_at = now() WHERE id = $1`, id)
	_, _ = a.db.Exec(ctx, `DELETE FROM login_attempts WHERE created_at < now() - interval '1 day'`)
	if err := a.startSession(w, r, id); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

func (a *App) adminLogout(w http.ResponseWriter, r *http.Request) {
	if c, err := r.Cookie(sessionCookie); err == nil {
		_, _ = a.db.Exec(r.Context(), `DELETE FROM sessions WHERE token_hash = $1`, hashToken(c.Value))
	}
	http.SetCookie(w, &http.Cookie{Name: sessionCookie, Value: "", Path: "/", MaxAge: -1, HttpOnly: true, Secure: a.cfg.CookieSecure, SameSite: http.SameSiteLaxMode})
	writeJSON(w, 200, map[string]bool{"ok": true})
}

func (a *App) adminMe(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, 200, a.mePayload(currentAdmin(r)))
}

func (a *App) adminUpdateMe(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	me := currentAdmin(r)
	var in adminInput
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	in.Email = me.Email
	errs := a.validateAdmin(ctx, &in, me.ID, false)
	var hash string
	_ = a.db.QueryRow(ctx, `SELECT password_hash FROM admins WHERE id = $1`, me.ID).Scan(&hash)
	if bcrypt.CompareHashAndPassword([]byte(hash), []byte(in.CurrentPassword)) != nil {
		errs["current_password"] = "Mật khẩu hiện tại không đúng."
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}
	if in.Password != "" {
		h, _ := bcrypt.GenerateFromPassword([]byte(in.Password), bcrypt.DefaultCost)
		_, _ = a.db.Exec(ctx, `UPDATE admins SET password_hash = $2 WHERE id = $1`, me.ID, string(h))
	}
	if _, err := a.db.Exec(ctx, `UPDATE admins SET name = $2 WHERE id = $1`, me.ID, in.Name); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

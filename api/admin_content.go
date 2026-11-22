package main

import (
	"crypto/rand"
	"encoding/hex"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
	"golang.org/x/crypto/bcrypt"
)

/* ---------- Khách hàng (gộp theo SĐT) ---------- */

func (a *App) adminCustomers(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	q := strings.TrimSpace(r.URL.Query().Get("q"))
	page := max(1, int(qInt(r, "page")))
	const per = 30
	where, args := "TRUE", []any{}
	if q != "" {
		where, args = "(c.full_name ILIKE $1 OR c.phone LIKE $1 OR c.email ILIKE $1)", []any{"%" + q + "%"}
	}
	var total int
	if err := a.db.QueryRow(ctx, `SELECT COUNT(*) FROM customers c WHERE `+where, args...).Scan(&total); err != nil {
		serverError(w, err)
		return
	}
	rows, err := a.db.Query(ctx, `SELECT c.id, c.full_name, c.phone, c.province,
		COUNT(r.id) FILTER (WHERE r.offer_type = 'loan'), COUNT(r.id) FILTER (WHERE r.offer_type = 'card'), MAX(r.created_at)
		FROM customers c LEFT JOIN registrations r ON r.customer_id = c.id WHERE `+where+`
		GROUP BY c.id ORDER BY MAX(r.created_at) DESC NULLS LAST, c.id DESC LIMIT `+itoa(per)+` OFFSET `+itoa(int64((page-1)*per)), args...)
	if err != nil {
		serverError(w, err)
		return
	}
	type item struct {
		ID       int64      `json:"id"`
		FullName string     `json:"full_name"`
		Phone    string     `json:"phone"`
		Province string     `json:"province"`
		Loans    int        `json:"loans"`
		Cards    int        `json:"cards"`
		LastReg  *time.Time `json:"last_reg"`
	}
	items := []item{}
	for rows.Next() {
		var it item
		if err := rows.Scan(&it.ID, &it.FullName, &it.Phone, &it.Province, &it.Loans, &it.Cards, &it.LastReg); err != nil {
			serverError(w, err)
			return
		}
		items = append(items, it)
	}
	writeJSON(w, 200, map[string]any{"items": items, "total": total, "page": page, "per": per})
}

type Customer struct {
	ID         int64     `db:"id" json:"id"`
	FullName   string    `db:"full_name" json:"full_name"`
	Phone      string    `db:"phone" json:"phone"`
	Email      string    `db:"email" json:"email"`
	BirthYear  *int      `db:"birth_year" json:"birth_year"`
	Province   string    `db:"province" json:"province"`
	Occupation string    `db:"occupation" json:"occupation"`
	Income     string    `db:"income" json:"income"`
	Note       string    `db:"note" json:"note"`
	CreatedAt  time.Time `db:"created_at" json:"created_at"`
	UpdatedAt  time.Time `db:"updated_at" json:"updated_at"`
}

func (a *App) adminCustomer(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	rows, _ := a.db.Query(ctx, `SELECT id, full_name, phone, email, birth_year, province, occupation, income, note, created_at, updated_at FROM customers WHERE id = $1`, pathID(r))
	c, err := pgx.CollectOneRow(rows, pgx.RowToStructByName[Customer])
	if err == pgx.ErrNoRows {
		fail(w, 404, "Không tìm thấy khách hàng.")
		return
	} else if err != nil {
		serverError(w, err)
		return
	}
	lrows, err := a.db.Query(ctx, leadSelect+` WHERE r.customer_id = $1 ORDER BY r.id DESC`, c.ID)
	if err != nil {
		serverError(w, err)
		return
	}
	leads, err := pgx.CollectRows(lrows, pgx.RowToStructByName[Lead])
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"customer": c, "leads": leads})
}

func (a *App) adminCustomerUpdate(w http.ResponseWriter, r *http.Request) {
	var in Customer
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	errs := map[string]string{}
	phone, ok := normalizePhone(in.Phone)
	if !ok {
		errs["phone"] = "Số điện thoại không hợp lệ."
	}
	if len([]rune(strings.TrimSpace(in.FullName))) < 2 {
		errs["full_name"] = "Nhập họ tên."
	}
	ctx := r.Context()
	if ok {
		var dup int64
		_ = a.db.QueryRow(ctx, `SELECT id FROM customers WHERE phone = $1 AND id <> $2`, phone, pathID(r)).Scan(&dup)
		if dup > 0 {
			errs["phone"] = "Số này đã thuộc về khách hàng #" + itoa(dup) + "."
		}
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}
	_, err := a.db.Exec(ctx, `UPDATE customers SET full_name=$2, phone=$3, email=$4, birth_year=$5, province=$6, occupation=$7, income=$8, note=$9, updated_at=now() WHERE id=$1`,
		pathID(r), clip(in.FullName, 80), phone, clip(in.Email, 120), in.BirthYear, clip(in.Province, 60), clip(in.Occupation, 30), clip(in.Income, 30), clip(in.Note, 2000))
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

func (a *App) adminCustomerDelete(w http.ResponseWriter, r *http.Request) {
	if _, err := a.db.Exec(r.Context(), `DELETE FROM customers WHERE id = $1`, pathID(r)); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

/* ---------- Gói vay / gói thẻ ---------- */

func (a *App) adminOffers(w http.ResponseWriter, r *http.Request) {
	typ := r.URL.Query().Get("type")
	if !validType(typ) {
		fail(w, 400, "Thiếu loại module (loan hoặc card).")
		return
	}
	ctx := r.Context()
	list, err := a.offersWhere(ctx, `type = $1 ORDER BY sort_order, id`, typ)
	if err != nil {
		serverError(w, err)
		return
	}
	type item struct {
		Offer
		Regs   int `json:"regs"`
		Regs30 int `json:"regs30"`
	}
	items := make([]item, 0, len(list))
	for _, o := range list {
		it := item{Offer: o}
		_ = a.db.QueryRow(ctx, `SELECT COUNT(*), COUNT(*) FILTER (WHERE created_at > now() - interval '30 days') FROM registrations WHERE offer_id = $1`, o.ID).Scan(&it.Regs, &it.Regs30)
		items = append(items, it)
	}
	writeJSON(w, 200, map[string]any{"items": items})
}

func (a *App) adminOffer(w http.ResponseWriter, r *http.Request) {
	list, err := a.offersWhere(r.Context(), `id = $1`, pathID(r))
	if err != nil {
		serverError(w, err)
		return
	}
	if len(list) == 0 {
		fail(w, 404, "Không tìm thấy gói này.")
		return
	}
	writeJSON(w, 200, list[0])
}

func (a *App) adminOfferSave(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	id := pathID(r)
	var in Offer
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if id > 0 { // loại gói không đổi được sau khi tạo: gói vay luôn ở module vay, gói thẻ luôn ở module thẻ
		if err := a.db.QueryRow(ctx, `SELECT type FROM offers WHERE id = $1`, id).Scan(&in.Type); err != nil {
			fail(w, 404, "Không tìm thấy gói này.")
			return
		}
	}
	errs := map[string]string{}
	if !validType(in.Type) {
		errs["type"] = "Chọn module cho gói."
	}
	in.Name = clip(in.Name, 120)
	if in.Name == "" {
		errs["name"] = "Nhập tên gói."
	}
	in.Slug = slugify(firstNonEmpty(in.Slug, in.Name))
	var taken bool
	_ = a.db.QueryRow(ctx, `SELECT EXISTS (SELECT 1 FROM offers WHERE slug = $1 AND id <> $2)`, in.Slug, id).Scan(&taken)
	if taken {
		errs["slug"] = "Đường dẫn này đã dùng cho gói khác."
	}
	in.AffiliateURL = strings.TrimSpace(in.AffiliateURL)
	if in.AffiliateURL != "" {
		if u, err := url.Parse(in.AffiliateURL); err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
			errs["affiliate_url"] = "Link phải bắt đầu bằng http:// hoặc https://"
		}
	}
	if in.AmountMax > 0 && in.AmountMin > in.AmountMax {
		errs["amount_max"] = "Hạn mức tối đa phải lớn hơn tối thiểu."
	}
	if in.Logo != "" && !strings.HasPrefix(in.Logo, "/uploads/") {
		errs["logo"] = "Logo phải là ảnh tải lên từ trang quản trị."
	}
	if in.Type == "loan" {
		if in.TermMax > 0 && in.TermMin > in.TermMax {
			errs["term_max"] = "Kỳ hạn dài nhất phải lớn hơn ngắn nhất."
		}
		in.AnnualFee = ""
	} else {
		in.RateMin, in.TermMin, in.TermMax = 0, 0, 0
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}
	args := []any{in.Type, in.Slug, in.Name, clip(in.Partner, 120), in.Logo, clip(in.Badge, 30), in.AmountMin, in.AmountMax, clip(in.RateText, 60), in.RateMin,
		in.TermMin, in.TermMax, clip(in.AgeText, 60), clip(in.ApprovalText, 60), clip(in.AnnualFee, 60), clip(in.Highlights, 3000), clip(in.Requirements, 3000),
		clip(in.Description, 3000), in.AffiliateURL, in.SortOrder, in.Active}
	var err error
	if id > 0 {
		_, err = a.db.Exec(ctx, `UPDATE offers SET type=$1, slug=$2, name=$3, partner=$4, logo=$5, badge=$6, amount_min=$7, amount_max=$8, rate_text=$9, rate_min=$10,
			term_min=$11, term_max=$12, age_text=$13, approval_text=$14, annual_fee=$15, highlights=$16, requirements=$17, description=$18, affiliate_url=$19,
			sort_order=$20, active=$21, updated_at=now() WHERE id=$22`, append(args, id)...)
	} else {
		err = a.db.QueryRow(ctx, `INSERT INTO offers (type, slug, name, partner, logo, badge, amount_min, amount_max, rate_text, rate_min, term_min, term_max,
			age_text, approval_text, annual_fee, highlights, requirements, description, affiliate_url, sort_order, active)
			VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21) RETURNING id`, args...).Scan(&id)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"ok": true, "id": id, "type": in.Type})
}

func firstNonEmpty(v ...string) string {
	for _, s := range v {
		if strings.TrimSpace(s) != "" {
			return s
		}
	}
	return ""
}

func (a *App) adminOfferDelete(w http.ResponseWriter, r *http.Request) {
	if _, err := a.db.Exec(r.Context(), `DELETE FROM offers WHERE id = $1`, pathID(r)); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

// Tải logo: chỉ nhận PNG/JPG/WEBP/GIF, tối đa 1MB, tên file ngẫu nhiên
func (a *App) adminUpload(w http.ResponseWriter, r *http.Request) {
	r.Body = http.MaxBytesReader(w, r.Body, 1<<20+4096)
	f, _, err := r.FormFile("file")
	if err != nil {
		fail(w, 422, "Ảnh vượt quá 1MB hoặc chưa chọn file.")
		return
	}
	defer f.Close()
	head := make([]byte, 512)
	n, _ := io.ReadFull(f, head)
	ext := map[string]string{"image/png": ".png", "image/jpeg": ".jpg", "image/webp": ".webp", "image/gif": ".gif"}[http.DetectContentType(head[:n])]
	if ext == "" {
		fail(w, 422, "Chỉ nhận ảnh PNG, JPG, WEBP hoặc GIF.")
		return
	}
	b := make([]byte, 8)
	_, _ = rand.Read(b)
	name := time.Now().Format("200601") + "-" + hex.EncodeToString(b) + ext
	out, err := os.Create(filepath.Join(a.cfg.UploadDir, name))
	if err != nil {
		serverError(w, err)
		return
	}
	defer out.Close()
	if _, err := out.Write(head[:n]); err == nil {
		_, err = io.Copy(out, f)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]string{"url": "/uploads/" + name})
}

/* ---------- Landing page ---------- */

func (a *App) adminLandings(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	rows, err := a.db.Query(ctx, `SELECT `+landingCols+` FROM landings ORDER BY is_default DESC, id`)
	if err != nil {
		serverError(w, err)
		return
	}
	list, err := pgx.CollectRows(rows, pgx.RowToStructByName[Landing])
	if err != nil {
		serverError(w, err)
		return
	}
	type item struct {
		Landing
		Views30 int `json:"views30"`
		Loans30 int `json:"loans30"`
		Cards30 int `json:"cards30"`
		Total   int `json:"total"`
	}
	items := []item{}
	for _, l := range list {
		it := item{Landing: l}
		_ = a.db.QueryRow(ctx, `SELECT COALESCE(SUM(views), 0) FROM landing_views WHERE landing_id = $1 AND day > current_date - 30`, l.ID).Scan(&it.Views30)
		_ = a.db.QueryRow(ctx, `SELECT COUNT(*) FILTER (WHERE offer_type = 'loan' AND created_at > now() - interval '30 days'),
			COUNT(*) FILTER (WHERE offer_type = 'card' AND created_at > now() - interval '30 days'), COUNT(*) FROM registrations WHERE landing_id = $1`, l.ID).Scan(&it.Loans30, &it.Cards30, &it.Total)
		items = append(items, it)
	}
	writeJSON(w, 200, map[string]any{"items": items})
}

func (a *App) adminLanding(w http.ResponseWriter, r *http.Request) {
	l, err := a.landingWhere(r.Context(), `id = $1`, pathID(r))
	if err != nil {
		serverError(w, err)
		return
	}
	if l == nil {
		fail(w, 404, "Không tìm thấy landing này.")
		return
	}
	writeJSON(w, 200, l)
}

func (a *App) adminLandingSave(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	id := pathID(r)
	var in Landing
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	errs := map[string]string{}
	in.Name, in.Headline = clip(in.Name, 120), clip(in.Headline, 160)
	if in.Name == "" {
		errs["name"] = "Nhập tên landing để phân biệt trong báo cáo."
	}
	if in.Headline == "" {
		errs["headline"] = "Nhập tiêu đề chính."
	}
	if in.OfferType != "loan" && in.OfferType != "card" {
		in.OfferType = "all"
	}
	in.Slug = slugify(firstNonEmpty(in.Slug, in.Name))
	var taken bool
	_ = a.db.QueryRow(ctx, `SELECT EXISTS (SELECT 1 FROM landings WHERE slug = $1 AND id <> $2)`, in.Slug, id).Scan(&taken)
	if taken {
		errs["slug"] = "Đường dẫn này đã được dùng."
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}
	if in.OfferIDs == nil {
		in.OfferIDs = []int64{}
	}
	tx, err := a.db.Begin(ctx)
	if err != nil {
		serverError(w, err)
		return
	}
	defer tx.Rollback(ctx)
	if in.IsDefault {
		_, _ = tx.Exec(ctx, `UPDATE landings SET is_default = FALSE`)
	}
	args := []any{in.Slug, in.Name, in.Headline, clip(in.Subheadline, 400), in.OfferType, in.OfferIDs, in.ShowCalculator, clip(in.SeoTitle, 80),
		clip(in.SeoDescription, 200), in.IsDefault, in.Active}
	if id > 0 {
		_, err = tx.Exec(ctx, `UPDATE landings SET slug=$1, name=$2, headline=$3, subheadline=$4, offer_type=$5, offer_ids=$6, show_calculator=$7, seo_title=$8,
			seo_description=$9, is_default=$10, active=$11, updated_at=now() WHERE id=$12`, append(args, id)...)
	} else {
		err = tx.QueryRow(ctx, `INSERT INTO landings (slug, name, headline, subheadline, offer_type, offer_ids, show_calculator, seo_title, seo_description, is_default, active)
			VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11) RETURNING id`, args...).Scan(&id)
	}
	if err == nil {
		err = tx.Commit(ctx)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"ok": true, "id": id})
}

func (a *App) adminLandingDelete(w http.ResponseWriter, r *http.Request) {
	var isDefault bool
	_ = a.db.QueryRow(r.Context(), `SELECT is_default FROM landings WHERE id = $1`, pathID(r)).Scan(&isDefault)
	if isDefault {
		fail(w, 422, "Không xoá được landing đang làm trang chủ. Hãy đặt landing khác làm trang chủ trước.")
		return
	}
	if _, err := a.db.Exec(r.Context(), `DELETE FROM landings WHERE id = $1`, pathID(r)); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

/* ---------- Tài khoản quản trị ---------- */

func (a *App) adminUsers(w http.ResponseWriter, r *http.Request) {
	rows, err := a.db.Query(r.Context(), `SELECT id, name, email, role, active, last_login_at, created_at FROM admins ORDER BY id`)
	if err != nil {
		serverError(w, err)
		return
	}
	list, err := pgx.CollectRows(rows, pgx.RowToStructByName[Admin])
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"items": list, "roles": roleLabels})
}

func (a *App) adminUserSave(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	id := pathID(r)
	me := currentAdmin(r)
	var in adminInput
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	errs := a.validateAdmin(ctx, &in, id, id == 0)
	if _, ok := roleLabels[in.Role]; !ok {
		errs["role"] = "Chọn vai trò."
	}
	active := in.Active == nil || *in.Active
	if id == me.ID && (in.Role != "admin" || !active) {
		errs["role"] = "Bạn không thể tự hạ quyền hoặc khoá tài khoản của chính mình."
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}
	var err error
	if id > 0 {
		_, err = a.db.Exec(ctx, `UPDATE admins SET name=$2, email=$3, role=$4, active=$5 WHERE id=$1`, id, in.Name, in.Email, in.Role, active)
		if err == nil && in.Password != "" {
			h, _ := bcrypt.GenerateFromPassword([]byte(in.Password), bcrypt.DefaultCost)
			_, err = a.db.Exec(ctx, `UPDATE admins SET password_hash=$2 WHERE id=$1`, id, string(h))
		}
		if err == nil && !active {
			_, err = a.db.Exec(ctx, `DELETE FROM sessions WHERE admin_id = $1`, id)
		}
	} else {
		h, _ := bcrypt.GenerateFromPassword([]byte(in.Password), bcrypt.DefaultCost)
		err = a.db.QueryRow(ctx, `INSERT INTO admins (name, email, password_hash, role, active) VALUES ($1,$2,$3,$4,$5) RETURNING id`,
			in.Name, in.Email, string(h), in.Role, active).Scan(&id)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"ok": true, "id": id})
}

func (a *App) adminUserDelete(w http.ResponseWriter, r *http.Request) {
	if pathID(r) == currentAdmin(r).ID {
		fail(w, 422, "Bạn không thể xoá tài khoản đang đăng nhập.")
		return
	}
	if _, err := a.db.Exec(r.Context(), `DELETE FROM admins WHERE id = $1`, pathID(r)); err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

/* ---------- Cài đặt ---------- */

var settingKeys = []string{"site_name", "site_tagline", "hotline", "zalo", "contact_email", "company_info", "disclaimer", "faq", "head_scripts", "body_scripts"}

func (a *App) adminSettings(w http.ResponseWriter, r *http.Request) {
	s, err := a.settings(r.Context())
	if err != nil {
		serverError(w, err)
		return
	}
	out := map[string]string{}
	for _, k := range settingKeys {
		out[k] = s[k]
	}
	writeJSON(w, 200, out)
}

func (a *App) adminSettingsSave(w http.ResponseWriter, r *http.Request) {
	var in map[string]string
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if strings.TrimSpace(in["site_name"]) == "" {
		failFields(w, map[string]string{"site_name": "Nhập tên website."})
		return
	}
	for _, k := range settingKeys {
		if v, ok := in[k]; ok {
			if _, err := a.db.Exec(r.Context(), `INSERT INTO settings (key, value) VALUES ($1, $2) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value`, k, clip(v, 20000)); err != nil {
				serverError(w, err)
				return
			}
		}
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

package main

import (
	"context"
	"embed"
	"net/http"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
)

//go:embed legal/*.md
var legalFS embed.FS

// Ba trang bắt buộc. Khách đồng ý với cả ba khi gửi đăng ký.
var legalPages = []struct{ slug, title string }{
	{"dieu-khoan-su-dung", "Điều khoản sử dụng"},
	{"chinh-sach-bao-mat", "Chính sách bảo mật"},
	{"bao-ve-du-lieu-ca-nhan", "Thông báo xử lý dữ liệu cá nhân"},
}

type Page struct {
	Slug      string    `db:"slug" json:"slug"`
	Title     string    `db:"title" json:"title"`
	Content   string    `db:"content" json:"content"`
	Version   int       `db:"version" json:"version"`
	SortOrder int       `db:"sort_order" json:"sort_order"`
	UpdatedAt time.Time `db:"updated_at" json:"updated_at"`
}

func (a *App) seedLegal(ctx context.Context) error {
	for k, v := range map[string]string{"company_name": "", "tax_code": "", "company_address": "", "privacy_email": ""} {
		if _, err := a.db.Exec(ctx, `INSERT INTO settings (key, value) VALUES ($1, $2) ON CONFLICT (key) DO NOTHING`, k, v); err != nil {
			return err
		}
	}
	for i, p := range legalPages {
		body, err := legalFS.ReadFile("legal/" + p.slug + ".md")
		if err != nil {
			return err
		}
		if _, err := a.db.Exec(ctx, `INSERT INTO pages (slug, title, content, sort_order) VALUES ($1, $2, $3, $4) ON CONFLICT (slug) DO NOTHING`,
			p.slug, p.title, string(body), (i+1)*10); err != nil {
			return err
		}
	}
	return nil
}

// Thay {{biến}} bằng thông tin trong Cài đặt; thiếu thì hiện rõ để người vận hành bổ sung
func fillPlaceholders(content string, s map[string]string, updated time.Time) string {
	privacy := firstNonEmpty(s["privacy_email"], s["contact_email"])
	vals := map[string]string{
		"site_name": s["site_name"], "company_name": s["company_name"], "tax_code": s["tax_code"], "company_address": s["company_address"],
		"contact_email": s["contact_email"], "privacy_email": privacy, "hotline": s["hotline"],
		"updated_at": updated.In(vnTZ).Format("02/01/2006"),
	}
	for k, v := range vals {
		if strings.TrimSpace(v) == "" {
			v = "[chưa cập nhật]"
		}
		content = strings.ReplaceAll(content, "{{"+k+"}}", v)
	}
	return content
}

func (a *App) pageList(ctx context.Context) ([]Page, error) {
	rows, err := a.db.Query(ctx, `SELECT slug, title, content, version, sort_order, updated_at FROM pages ORDER BY sort_order, slug`)
	if err != nil {
		return nil, err
	}
	return pgx.CollectRows(rows, pgx.RowToStructByName[Page])
}

// Phiên bản các chính sách tại thời điểm khách đồng ý, ví dụ "dieu-khoan-su-dung@v2,chinh-sach-bao-mat@v1"
func (a *App) consentVersions(ctx context.Context) string {
	list, err := a.pageList(ctx)
	if err != nil {
		return ""
	}
	parts := []string{}
	for _, p := range list {
		parts = append(parts, p.Slug+"@v"+itoa(int64(p.Version)))
	}
	return strings.Join(parts, ",")
}

func (a *App) publicPage(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	rows, _ := a.db.Query(ctx, `SELECT slug, title, content, version, sort_order, updated_at FROM pages WHERE slug = $1`, r.PathValue("slug"))
	p, err := pgx.CollectOneRow(rows, pgx.RowToStructByName[Page])
	if err == pgx.ErrNoRows {
		fail(w, 404, "Không tìm thấy trang này.")
		return
	} else if err != nil {
		serverError(w, err)
		return
	}
	s, err := a.settings(ctx)
	if err != nil {
		serverError(w, err)
		return
	}
	p.Content = fillPlaceholders(p.Content, s, p.UpdatedAt)
	w.Header().Set("Cache-Control", "public, max-age=60")
	writeJSON(w, 200, p)
}

/* ---------- Yêu cầu của chủ thể dữ liệu ---------- */

var dataRequestTypes = []opt{
	{"access", "Xem / nhận bản sao dữ liệu của tôi"},
	{"correct", "Sửa thông tin chưa đúng"},
	{"delete", "Xoá dữ liệu của tôi"},
	{"withdraw", "Rút lại sự đồng ý, ngừng liên hệ"},
	{"restrict", "Hạn chế / phản đối việc xử lý"},
	{"other", "Yêu cầu khác"},
}
var dataRequestStatus = []opt{{"new", "Mới"}, {"processing", "Đang xử lý"}, {"done", "Đã xong"}, {"rejected", "Từ chối"}}

func (a *App) publicDataRequest(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	var in struct {
		Type     string `json:"type"`
		FullName string `json:"full_name"`
		Phone    string `json:"phone"`
		Email    string `json:"email"`
		Content  string `json:"content"`
		Website  string `json:"website"`
	}
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if in.Website != "" {
		writeJSON(w, 200, map[string]bool{"ok": true})
		return
	}
	errs := map[string]string{}
	if !hasOpt(dataRequestTypes, in.Type) {
		errs["type"] = "Chọn loại yêu cầu."
	}
	if len([]rune(strings.TrimSpace(in.FullName))) < 2 {
		errs["full_name"] = "Nhập họ tên đã dùng khi đăng ký."
	}
	phone, ok := normalizePhone(in.Phone)
	if !ok {
		errs["phone"] = "Nhập số điện thoại đã dùng khi đăng ký (10 số)."
	}
	if (in.Type == "correct" || in.Type == "other") && len([]rune(strings.TrimSpace(in.Content))) < 5 {
		errs["content"] = "Mô tả nội dung bạn cần, ví dụ thông tin nào cần sửa."
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}
	ip := a.clientIP(r)
	var recent int
	_ = a.db.QueryRow(ctx, `SELECT COUNT(*) FROM data_requests WHERE ip = $1 AND created_at > now() - interval '1 hour'`, ip).Scan(&recent)
	if recent >= 5 {
		fail(w, 429, "Bạn đã gửi nhiều yêu cầu. Hãy thử lại sau 1 giờ hoặc gửi email cho chúng tôi.")
		return
	}
	var cid *int64
	var id int64
	_ = a.db.QueryRow(ctx, `SELECT id FROM customers WHERE phone = $1`, phone).Scan(&id)
	if id > 0 {
		cid = &id
	}
	var reqID int64
	err := a.db.QueryRow(ctx, `INSERT INTO data_requests (type, full_name, phone, email, content, customer_id, ip) VALUES ($1,$2,$3,$4,$5,$6,$7) RETURNING id`,
		in.Type, clip(in.FullName, 80), phone, clip(in.Email, 120), clip(in.Content, 2000), cid, ip).Scan(&reqID)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"ok": true, "code": "YC" + itoa(reqID)})
}

type DataRequest struct {
	ID         int64     `db:"id" json:"id"`
	Type       string    `db:"type" json:"type"`
	FullName   string    `db:"full_name" json:"full_name"`
	Phone      string    `db:"phone" json:"phone"`
	Email      string    `db:"email" json:"email"`
	Content    string    `db:"content" json:"content"`
	CustomerID *int64    `db:"customer_id" json:"customer_id"`
	Status     string    `db:"status" json:"status"`
	Note       string    `db:"note" json:"note"`
	HandledBy  *string   `db:"handled_by_name" json:"handled_by_name"`
	CreatedAt  time.Time `db:"created_at" json:"created_at"`
	UpdatedAt  time.Time `db:"updated_at" json:"updated_at"`
}

func (a *App) adminDataRequests(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	st := r.URL.Query().Get("status")
	where, args := "TRUE", []any{}
	if hasOpt(dataRequestStatus, st) {
		where, args = "d.status = $1", []any{st}
	}
	rows, err := a.db.Query(ctx, `SELECT d.id, d.type, d.full_name, d.phone, d.email, d.content, d.customer_id, d.status, d.note,
		a.name AS handled_by_name, d.created_at, d.updated_at
		FROM data_requests d LEFT JOIN admins a ON a.id = d.handled_by WHERE `+where+` ORDER BY d.id DESC LIMIT 500`, args...)
	if err != nil {
		serverError(w, err)
		return
	}
	items, err := pgx.CollectRows(rows, pgx.RowToStructByName[DataRequest])
	if err != nil {
		serverError(w, err)
		return
	}
	counts := map[string]int{}
	crow, _ := a.db.Query(ctx, `SELECT status, COUNT(*) FROM data_requests GROUP BY status`)
	for crow.Next() {
		var s string
		var c int
		_ = crow.Scan(&s, &c)
		counts[s] = c
	}
	crow.Close()
	writeJSON(w, 200, map[string]any{"items": items, "counts": counts, "types": dataRequestTypes, "statuses": dataRequestStatus})
}

func (a *App) adminDataRequestUpdate(w http.ResponseWriter, r *http.Request) {
	var in struct {
		Status string `json:"status"`
		Note   string `json:"note"`
	}
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if !hasOpt(dataRequestStatus, in.Status) {
		fail(w, 422, "Trạng thái không hợp lệ.")
		return
	}
	tag, err := a.db.Exec(r.Context(), `UPDATE data_requests SET status=$2, note=$3, handled_by=$4, updated_at=now() WHERE id=$1`,
		pathID(r), in.Status, clip(in.Note, 2000), currentAdmin(r).ID)
	if err != nil {
		serverError(w, err)
		return
	}
	if tag.RowsAffected() == 0 {
		fail(w, 404, "Không tìm thấy yêu cầu này.")
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

// Ẩn danh hoá: xoá thông tin nhận diện của khách và mọi đăng ký của khách, giữ số liệu thống kê
func (a *App) adminCustomerAnonymize(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	id := pathID(r)
	tx, err := a.db.Begin(ctx)
	if err != nil {
		serverError(w, err)
		return
	}
	defer tx.Rollback(ctx)
	tag, err := tx.Exec(ctx, `UPDATE customers SET full_name='Đã ẩn danh', phone='an-'||id, email='', birth_year=NULL, province='', occupation='', income='',
		note='', anonymized_at=now(), updated_at=now() WHERE id=$1 AND anonymized_at IS NULL`, id)
	if err == nil && tag.RowsAffected() == 0 {
		fail(w, 404, "Không tìm thấy khách hàng, hoặc khách đã được ẩn danh.")
		return
	}
	if err == nil {
		_, err = tx.Exec(ctx, `UPDATE registrations SET full_name='Đã ẩn danh', phone='', email='', birth_year=0, note='', ip='', user_agent='', referrer='',
			anonymized_at=now(), updated_at=now() WHERE customer_id=$1`, id)
	}
	if err == nil {
		_, err = tx.Exec(ctx, `UPDATE data_requests SET full_name='Đã ẩn danh', phone='', email='', content='' WHERE customer_id=$1`, id)
	}
	if err == nil {
		err = tx.Commit(ctx)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

/* ---------- Quản lý trang pháp lý ---------- */

func (a *App) adminPages(w http.ResponseWriter, r *http.Request) {
	list, err := a.pageList(r.Context())
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"items": list})
}

func (a *App) adminPageSave(w http.ResponseWriter, r *http.Request) {
	var in Page
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	in.Title = clip(in.Title, 120)
	if in.Title == "" {
		failFields(w, map[string]string{"title": "Nhập tiêu đề trang."})
		return
	}
	// Nội dung đổi thì tăng phiên bản: các đăng ký sau đó ghi nhận khách đồng ý với phiên bản mới
	tag, err := a.db.Exec(r.Context(), `UPDATE pages SET title=$2, content=$3,
		version = version + CASE WHEN content <> $3 THEN 1 ELSE 0 END,
		updated_at = CASE WHEN content <> $3 THEN now() ELSE updated_at END WHERE slug=$1`,
		r.PathValue("slug"), in.Title, clip(in.Content, 100000))
	if err != nil {
		serverError(w, err)
		return
	}
	if tag.RowsAffected() == 0 {
		fail(w, 404, "Không tìm thấy trang này.")
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

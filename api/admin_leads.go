package main

import (
	"encoding/csv"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
)

type Lead struct {
	ID           int64      `db:"id" json:"id"`
	CustomerID   int64      `db:"customer_id" json:"customer_id"`
	OfferID      *int64     `db:"offer_id" json:"offer_id"`
	OfferName    string     `db:"offer_name" json:"offer_name"`
	OfferType    string     `db:"offer_type" json:"offer_type"`
	LandingID    *int64     `db:"landing_id" json:"landing_id"`
	LandingName  *string    `db:"landing_name" json:"landing_name"`
	FullName     string     `db:"full_name" json:"full_name"`
	Phone        string     `db:"phone" json:"phone"`
	Email        string     `db:"email" json:"email"`
	BirthYear    int        `db:"birth_year" json:"birth_year"`
	Province     string     `db:"province" json:"province"`
	Occupation   string     `db:"occupation" json:"occupation"`
	Income       string     `db:"income" json:"income"`
	Amount       *int64     `db:"amount" json:"amount"`
	Term         *int       `db:"term" json:"term"`
	Purpose      string     `db:"purpose" json:"purpose"`
	SalaryMethod string     `db:"salary_method" json:"salary_method"`
	HasCard      string     `db:"has_card" json:"has_card"`
	CardNeed     string     `db:"card_need" json:"card_need"`
	Status       string     `db:"status" json:"status"`
	Note         string     `db:"note" json:"note"`
	UtmSource    string     `db:"utm_source" json:"utm_source"`
	UtmMedium    string     `db:"utm_medium" json:"utm_medium"`
	UtmCampaign  string     `db:"utm_campaign" json:"utm_campaign"`
	UtmContent   string     `db:"utm_content" json:"utm_content"`
	Referrer     string     `db:"referrer" json:"referrer"`
	IP           string     `db:"ip" json:"ip"`
	UserAgent    string     `db:"user_agent" json:"user_agent"`
	ClickedOutAt *time.Time `db:"clicked_out_at" json:"clicked_out_at"`
	ConsentAt    *time.Time `db:"consent_at" json:"consent_at"`
	ConsentVer   string     `db:"consent_versions" json:"consent_versions"`
	AnonymizedAt *time.Time `db:"anonymized_at" json:"anonymized_at"`
	CreatedAt    time.Time  `db:"created_at" json:"created_at"`
}

const leadSelect = `SELECT r.id, r.customer_id, r.offer_id, COALESCE(o.name, r.offer_name) AS offer_name, r.offer_type, r.landing_id, l.name AS landing_name,
	r.full_name, r.phone, r.email, r.birth_year, r.province, r.occupation, r.income, r.amount, r.term, r.purpose, r.salary_method, r.has_card, r.card_need,
	r.status, r.note, r.utm_source, r.utm_medium, r.utm_campaign, r.utm_content, r.referrer, r.ip, r.user_agent, r.clicked_out_at,
	r.consent_at, r.consent_versions, r.anonymized_at, r.created_at
	FROM registrations r LEFT JOIN offers o ON o.id = r.offer_id LEFT JOIN landings l ON l.id = r.landing_id`

func validType(t string) bool { return t == "loan" || t == "card" }

// Bộ lọc danh sách đăng ký, luôn gắn với một module (vay hoặc thẻ)
func leadFilter(r *http.Request) (string, []any, string, bool) {
	q := r.URL.Query()
	typ := q.Get("type")
	if !validType(typ) {
		return "", nil, "", false
	}
	args := []any{typ}
	where := []string{"r.offer_type = $1"}
	add := func(cond string, v any) {
		args = append(args, v)
		where = append(where, strings.ReplaceAll(cond, "?", "$"+strconv.Itoa(len(args))))
	}
	if s := strings.TrimSpace(q.Get("q")); s != "" {
		add("(r.full_name ILIKE ? OR r.phone LIKE ? OR r.email ILIKE ?)", "%"+s+"%")
	}
	if s := q.Get("status"); hasOpt(statusOpts, s) {
		add("r.status = ?", s)
	}
	if v := qInt(r, "offer"); v > 0 {
		add("r.offer_id = ?", v)
	}
	if v := qInt(r, "landing"); v > 0 {
		add("r.landing_id = ?", v)
	}
	if s := q.Get("source"); s != "" {
		if s == "(trực tiếp)" {
			s = ""
		}
		add("r.utm_source = ?", s)
	}
	if d, err := time.ParseInLocation("2006-01-02", q.Get("from"), vnTZ); err == nil {
		add("r.created_at >= ?", d)
	}
	if d, err := time.ParseInLocation("2006-01-02", q.Get("to"), vnTZ); err == nil {
		add("r.created_at < ?", d.AddDate(0, 0, 1))
	}
	return strings.Join(where, " AND "), args, typ, true
}

var vnTZ, _ = time.LoadLocation("Asia/Ho_Chi_Minh")

func (a *App) adminLeads(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	where, args, typ, ok := leadFilter(r)
	if !ok {
		fail(w, 400, "Thiếu loại module (loan hoặc card).")
		return
	}
	page := max(1, int(qInt(r, "page")))
	const per = 30
	var total int
	if err := a.db.QueryRow(ctx, `SELECT COUNT(*) FROM registrations r WHERE `+where, args...).Scan(&total); err != nil {
		serverError(w, err)
		return
	}
	rows, err := a.db.Query(ctx, leadSelect+` WHERE `+where+fmt.Sprintf(` ORDER BY r.id DESC LIMIT %d OFFSET %d`, per, (page-1)*per), args...)
	if err != nil {
		serverError(w, err)
		return
	}
	leads, err := pgx.CollectRows(rows, pgx.RowToStructByName[Lead])
	if err != nil {
		serverError(w, err)
		return
	}
	counts := map[string]int{}
	crow, _ := a.db.Query(ctx, `SELECT status, COUNT(*) FROM registrations WHERE offer_type = $1 GROUP BY status`, typ)
	for crow.Next() {
		var s string
		var c int
		_ = crow.Scan(&s, &c)
		counts[s] = c
	}
	crow.Close()
	type idName struct {
		ID   int64  `json:"id"`
		Name string `json:"name"`
	}
	offers, landings := []idName{}, []idName{}
	orow, _ := a.db.Query(ctx, `SELECT id, name FROM offers WHERE type = $1 ORDER BY sort_order`, typ)
	for orow.Next() {
		var x idName
		_ = orow.Scan(&x.ID, &x.Name)
		offers = append(offers, x)
	}
	orow.Close()
	lrow, _ := a.db.Query(ctx, `SELECT id, name FROM landings ORDER BY id`)
	for lrow.Next() {
		var x idName
		_ = lrow.Scan(&x.ID, &x.Name)
		landings = append(landings, x)
	}
	lrow.Close()
	sources := []string{}
	srow, _ := a.db.Query(ctx, `SELECT DISTINCT utm_source FROM registrations WHERE offer_type = $1 AND utm_source <> '' ORDER BY 1`, typ)
	for srow.Next() {
		var s string
		_ = srow.Scan(&s)
		sources = append(sources, s)
	}
	srow.Close()
	writeJSON(w, 200, map[string]any{"items": leads, "total": total, "page": page, "per": per, "counts": counts,
		"offers": offers, "landings": landings, "sources": sources})
}

func (a *App) adminLeadsExport(w http.ResponseWriter, r *http.Request) {
	where, args, typ, ok := leadFilter(r)
	if !ok {
		fail(w, 400, "Thiếu loại module (loan hoặc card).")
		return
	}
	rows, err := a.db.Query(r.Context(), leadSelect+` WHERE `+where+` ORDER BY r.id DESC`, args...)
	if err != nil {
		serverError(w, err)
		return
	}
	leads, err := pgx.CollectRows(rows, pgx.RowToStructByName[Lead])
	if err != nil {
		serverError(w, err)
		return
	}
	name := "dang-ky-vay-"
	if typ == "card" {
		name = "dang-ky-mo-the-"
	}
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", `attachment; filename="`+name+time.Now().In(vnTZ).Format("20060102-1504")+`.csv"`)
	_, _ = w.Write([]byte("\xEF\xBB\xBF")) // BOM để Excel đọc đúng tiếng Việt
	cw := csv.NewWriter(w)
	head := []string{"Mã", "Thời gian", "Họ tên", "SĐT", "Năm sinh", "Email", "Tỉnh/TP", "Công việc", "Thu nhập"}
	if typ == "loan" {
		head = append(head, "Số tiền vay", "Kỳ hạn (tháng)", "Mục đích vay", "Nhận lương qua")
	} else {
		head = append(head, "Đã có thẻ tín dụng", "Nhu cầu dùng thẻ")
	}
	head = append(head, "Gói đăng ký", "Landing", "Trạng thái", "Đã sang trang đối tác", "utm_source", "utm_medium", "utm_campaign", "utm_content", "Ghi chú")
	_ = cw.Write(head)
	for _, l := range leads {
		row := []string{"R" + itoa(l.ID), l.CreatedAt.In(vnTZ).Format("02/01/2006 15:04"), l.FullName, "'" + l.Phone, itoa(int64(l.BirthYear)), l.Email,
			l.Province, optLabel(occupationOpts, l.Occupation), optLabel(incomeOpts, l.Income)}
		if typ == "loan" {
			amt, term := "", ""
			if l.Amount != nil {
				amt = itoa(*l.Amount)
			}
			if l.Term != nil {
				term = itoa(int64(*l.Term))
			}
			row = append(row, amt, term, optLabel(purposeOpts, l.Purpose), optLabel(salaryOpts, l.SalaryMethod))
		} else {
			row = append(row, optLabel(hasCardOpts, l.HasCard), optLabel(cardNeedOpts, l.CardNeed))
		}
		landing, clicked := "", "Chưa"
		if l.LandingName != nil {
			landing = *l.LandingName
		}
		if l.ClickedOutAt != nil {
			clicked = "Có"
		}
		row = append(row, l.OfferName, landing, optLabel(statusOpts, l.Status), clicked, l.UtmSource, l.UtmMedium, l.UtmCampaign, l.UtmContent, l.Note)
		_ = cw.Write(row)
	}
	cw.Flush()
}

func (a *App) adminLead(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	rows, err := a.db.Query(ctx, leadSelect+` WHERE r.id = $1`, pathID(r))
	if err != nil {
		serverError(w, err)
		return
	}
	l, err := pgx.CollectOneRow(rows, pgx.RowToStructByName[Lead])
	if err == pgx.ErrNoRows {
		fail(w, 404, "Không tìm thấy đăng ký này.")
		return
	} else if err != nil {
		serverError(w, err)
		return
	}
	orows, err := a.db.Query(ctx, leadSelect+` WHERE r.customer_id = $1 AND r.id <> $2 ORDER BY r.id DESC`, l.CustomerID, l.ID)
	if err != nil {
		serverError(w, err)
		return
	}
	others, _ := pgx.CollectRows(orows, pgx.RowToStructByName[Lead])
	writeJSON(w, 200, map[string]any{"lead": l, "others": others})
}

func (a *App) adminLeadUpdate(w http.ResponseWriter, r *http.Request) {
	var in struct {
		Status *string `json:"status"`
		Note   *string `json:"note"`
	}
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if in.Status != nil && !hasOpt(statusOpts, *in.Status) {
		fail(w, 422, "Trạng thái không hợp lệ.")
		return
	}
	var note *string
	if in.Note != nil {
		n := clip(*in.Note, 1000)
		note = &n
	}
	tag, err := a.db.Exec(r.Context(), `UPDATE registrations SET status = COALESCE($2, status), note = COALESCE($3, note), updated_at = now() WHERE id = $1`,
		pathID(r), in.Status, note)
	if err != nil {
		serverError(w, err)
		return
	}
	if tag.RowsAffected() == 0 {
		fail(w, 404, "Không tìm thấy đăng ký này.")
		return
	}
	writeJSON(w, 200, map[string]bool{"ok": true})
}

func (a *App) adminLeadsBulk(w http.ResponseWriter, r *http.Request) {
	var in struct {
		IDs    []int64 `json:"ids"`
		Action string  `json:"action"`
	}
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if len(in.IDs) == 0 {
		fail(w, 422, "Chọn ít nhất một đăng ký.")
		return
	}
	ctx := r.Context()
	switch {
	case in.Action == "delete":
		if !can(currentAdmin(r).Role, "leads.delete") {
			fail(w, 403, "Tài khoản của bạn không có quyền xoá đăng ký.")
			return
		}
		tag, err := a.db.Exec(ctx, `DELETE FROM registrations WHERE id = ANY($1)`, in.IDs)
		if err != nil {
			serverError(w, err)
			return
		}
		writeJSON(w, 200, map[string]any{"ok": true, "affected": tag.RowsAffected()})
	case hasOpt(statusOpts, in.Action):
		tag, err := a.db.Exec(ctx, `UPDATE registrations SET status = $2, updated_at = now() WHERE id = ANY($1)`, in.IDs, in.Action)
		if err != nil {
			serverError(w, err)
			return
		}
		writeJSON(w, 200, map[string]any{"ok": true, "affected": tag.RowsAffected()})
	default:
		fail(w, 422, "Thao tác không hợp lệ.")
	}
}

/* ---------- Tổng quan ---------- */

func (a *App) adminDashboard(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	rng := int(qInt(r, "range"))
	if rng != 7 && rng != 90 {
		rng = 30
	}
	now := time.Now().In(vnTZ)
	today := time.Date(now.Year(), now.Month(), now.Day(), 0, 0, 0, 0, vnTZ)
	from := today.AddDate(0, 0, -(rng - 1))

	type offerStat struct {
		ID       int64  `json:"id"`
		Name     string `json:"name"`
		Regs     int    `json:"regs"`
		Clicked  int    `json:"clicked"`
		Approved int    `json:"approved"`
	}
	mods := map[string]any{}
	for _, typ := range []string{"loan", "card"} {
		var todayN, regs, newN, clicked, approved int
		err := a.db.QueryRow(ctx, `SELECT
			COUNT(*) FILTER (WHERE created_at >= $2),
			COUNT(*) FILTER (WHERE created_at >= $3),
			COUNT(*) FILTER (WHERE status = 'new'),
			COUNT(*) FILTER (WHERE created_at >= $3 AND clicked_out_at IS NOT NULL),
			COUNT(*) FILTER (WHERE created_at >= $3 AND status = 'approved')
			FROM registrations WHERE offer_type = $1`, typ, today, from).Scan(&todayN, &regs, &newN, &clicked, &approved)
		if err != nil {
			serverError(w, err)
			return
		}
		rows, err := a.db.Query(ctx, `SELECT o.id, o.name, COUNT(r.id),
			COUNT(r.id) FILTER (WHERE r.clicked_out_at IS NOT NULL), COUNT(r.id) FILTER (WHERE r.status = 'approved')
			FROM offers o LEFT JOIN registrations r ON r.offer_id = o.id AND r.created_at >= $2
			WHERE o.type = $1 GROUP BY o.id ORDER BY 3 DESC, o.sort_order`, typ, from)
		if err != nil {
			serverError(w, err)
			return
		}
		stats := []offerStat{}
		for rows.Next() {
			var s offerStat
			_ = rows.Scan(&s.ID, &s.Name, &s.Regs, &s.Clicked, &s.Approved)
			stats = append(stats, s)
		}
		rows.Close()
		mods[typ] = map[string]any{"today": todayN, "regs": regs, "new": newN, "clicked": clicked, "approved": approved, "offers": stats}
	}

	type day struct {
		Day  string `json:"day"`
		Loan int    `json:"loan"`
		Card int    `json:"card"`
	}
	days := []day{}
	idx := map[string]int{}
	for d := from; !d.After(today); d = d.AddDate(0, 0, 1) {
		idx[d.Format("2006-01-02")] = len(days)
		days = append(days, day{Day: d.Format("2006-01-02")})
	}
	drows, err := a.db.Query(ctx, `SELECT to_char(created_at AT TIME ZONE 'Asia/Ho_Chi_Minh', 'YYYY-MM-DD'), offer_type, COUNT(*)
		FROM registrations WHERE created_at >= $1 GROUP BY 1, 2`, from)
	if err != nil {
		serverError(w, err)
		return
	}
	for drows.Next() {
		var d, t string
		var c int
		_ = drows.Scan(&d, &t, &c)
		if i, ok := idx[d]; ok {
			if t == "loan" {
				days[i].Loan = c
			} else {
				days[i].Card = c
			}
		}
	}
	drows.Close()

	var views int
	_ = a.db.QueryRow(ctx, `SELECT COALESCE(SUM(views), 0) FROM landing_views WHERE day >= $1::date`, from).Scan(&views)

	type landingStat struct {
		ID    int64  `json:"id"`
		Name  string `json:"name"`
		Views int    `json:"views"`
		Loan  int    `json:"loan"`
		Card  int    `json:"card"`
	}
	landings := []landingStat{}
	lrows, err := a.db.Query(ctx, `SELECT l.id, l.name,
		(SELECT COALESCE(SUM(views), 0) FROM landing_views v WHERE v.landing_id = l.id AND v.day >= $1::date),
		(SELECT COUNT(*) FROM registrations r WHERE r.landing_id = l.id AND r.created_at >= $1 AND r.offer_type = 'loan'),
		(SELECT COUNT(*) FROM registrations r WHERE r.landing_id = l.id AND r.created_at >= $1 AND r.offer_type = 'card')
		FROM landings l ORDER BY l.is_default DESC, l.id`, from)
	if err != nil {
		serverError(w, err)
		return
	}
	for lrows.Next() {
		var s landingStat
		_ = lrows.Scan(&s.ID, &s.Name, &s.Views, &s.Loan, &s.Card)
		landings = append(landings, s)
	}
	lrows.Close()

	type src struct {
		Source string `json:"source"`
		Count  int    `json:"count"`
	}
	sources := []src{}
	srows, err := a.db.Query(ctx, `SELECT COALESCE(NULLIF(utm_source, ''), '(trực tiếp)'), COUNT(*) FROM registrations WHERE created_at >= $1 GROUP BY 1 ORDER BY 2 DESC LIMIT 8`, from)
	if err != nil {
		serverError(w, err)
		return
	}
	for srows.Next() {
		var s src
		_ = srows.Scan(&s.Source, &s.Count)
		sources = append(sources, s)
	}
	srows.Close()

	writeJSON(w, 200, map[string]any{"range": rng, "modules": mods, "days": days, "views": views, "landings": landings, "sources": sources})
}

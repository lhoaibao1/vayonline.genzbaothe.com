package main

import (
	"context"
	"fmt"
	"net/http"
	"net/mail"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
)

func publicOffers(list []Offer) []Offer {
	for i := range list {
		list[i].AffiliateURL = "" // không lộ link đối tác ra trình duyệt; chuyển qua /go/ của tracker
	}
	return list
}

func (a *App) publicSite(w http.ResponseWriter, r *http.Request) {
	s, err := a.settings(r.Context())
	if err != nil {
		serverError(w, err)
		return
	}
	type faq struct {
		Q string `json:"q"`
		A string `json:"a"`
	}
	faqs := []faq{}
	for _, line := range strings.Split(s["faq"], "\n") {
		q, ans, ok := strings.Cut(strings.TrimSpace(line), "|")
		if ok && q != "" {
			faqs = append(faqs, faq{q, ans})
		}
	}
	type pageLink struct {
		Slug  string `json:"slug"`
		Title string `json:"title"`
	}
	pages := []pageLink{}
	if list, err := a.pageList(r.Context()); err == nil {
		for _, p := range list {
			pages = append(pages, pageLink{p.Slug, p.Title})
		}
	}
	w.Header().Set("Cache-Control", "public, max-age=60")
	writeJSON(w, 200, map[string]any{
		"pages": pages, "company_name": s["company_name"], "tax_code": s["tax_code"], "company_address": s["company_address"],
		"site_name": s["site_name"], "site_tagline": s["site_tagline"], "hotline": s["hotline"], "zalo": s["zalo"],
		"contact_email": s["contact_email"], "company_info": s["company_info"], "disclaimer": s["disclaimer"],
		"faq": faqs, "head_scripts": s["head_scripts"], "body_scripts": s["body_scripts"],
	})
}

// Gói hiển thị trên một landing, đã tách sẵn theo module
func (a *App) landingPayload(ctx context.Context, l *Landing) (map[string]any, error) {
	where := `active`
	args := []any{}
	if l.OfferType != "all" {
		args = append(args, l.OfferType)
		where += ` AND type = $1`
	}
	if len(l.OfferIDs) > 0 {
		args = append(args, l.OfferIDs)
		where += fmt.Sprintf(` AND id = ANY($%d)`, len(args))
	}
	offers, err := a.offersWhere(ctx, where+` ORDER BY sort_order, id`, args...)
	if err != nil {
		return nil, err
	}
	loans, cards := []Offer{}, []Offer{}
	for _, o := range publicOffers(offers) {
		if o.Type == "loan" {
			loans = append(loans, o)
		} else {
			cards = append(cards, o)
		}
	}
	return map[string]any{"landing": l, "loans": loans, "cards": cards}, nil
}

func (a *App) publicLanding(w http.ResponseWriter, r *http.Request) {
	slug := r.URL.Query().Get("slug")
	var l *Landing
	var err error
	if slug == "" {
		l, err = a.landingWhere(r.Context(), `active ORDER BY is_default DESC, id`)
	} else {
		l, err = a.landingWhere(r.Context(), `active AND slug = $1`, slug)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if l == nil {
		fail(w, 404, "Không tìm thấy trang này.")
		return
	}
	out, err := a.landingPayload(r.Context(), l)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, out)
}

// Trang riêng của từng module: /khoan-vay, /the-tin-dung
func (a *App) publicModule(w http.ResponseWriter, r *http.Request) {
	typ := r.PathValue("type")
	if typ != "loan" && typ != "card" {
		fail(w, 404, "Không tìm thấy trang này.")
		return
	}
	ctx := r.Context()
	l, err := a.landingWhere(ctx, `active AND offer_type = $1 AND cardinality(offer_ids) = 0 ORDER BY is_default DESC, id`, typ)
	if err == nil && l == nil {
		l, err = a.landingWhere(ctx, `active ORDER BY is_default DESC, id`)
		if l != nil {
			l.OfferType, l.OfferIDs = typ, nil
			if typ == "loan" {
				l.Headline, l.Subheadline, l.ShowCalculator = "Vay tiêu dùng online, *duyệt nhanh trong ngày*", "Chọn số tiền và kỳ hạn, xem ngay mỗi tháng trả bao nhiêu.", true
				l.SeoTitle = "Vay tiêu dùng online – GenZbaothe.com"
			} else {
				l.Headline, l.Subheadline, l.ShowCalculator = "Mở thẻ tín dụng online, *miễn lãi đến 55 ngày*", "So sánh ưu đãi hoàn tiền, phí thường niên và mở thẻ ngay trên điện thoại.", false
				l.SeoTitle = "Mở thẻ tín dụng online – GenZbaothe.com"
			}
		}
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if l == nil {
		fail(w, 404, "Không tìm thấy trang này.")
		return
	}
	out, err := a.landingPayload(ctx, l)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, out)
}

func (a *App) publicOffer(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	list, err := a.offersWhere(ctx, `active AND slug = $1`, r.PathValue("slug"))
	if err != nil {
		serverError(w, err)
		return
	}
	if len(list) == 0 {
		fail(w, 404, "Gói này không còn nhận đăng ký.")
		return
	}
	o := list[0]
	related, err := a.offersWhere(ctx, `active AND type = $1 AND id <> $2 ORDER BY sort_order LIMIT 3`, o.Type, o.ID)
	if err != nil {
		serverError(w, err)
		return
	}
	writeJSON(w, 200, map[string]any{"offer": publicOffers([]Offer{o})[0], "related": publicOffers(related)})
}

type registerInput struct {
	OfferID      int64  `json:"offer_id"`
	LandingID    int64  `json:"landing_id"`
	FullName     string `json:"full_name"`
	Phone        string `json:"phone"`
	Email        string `json:"email"`
	BirthYear    int    `json:"birth_year"`
	Province     string `json:"province"`
	Occupation   string `json:"occupation"`
	Income       string `json:"income"`
	Amount       int64  `json:"amount"`
	Term         int    `json:"term"`
	Purpose      string `json:"purpose"`
	SalaryMethod string `json:"salary_method"`
	HasCard      string `json:"has_card"`
	CardNeed     string `json:"card_need"`
	Consent      bool   `json:"consent"`
	Website      string `json:"website"` // bẫy bot, phải để trống
	UtmSource    string `json:"utm_source"`
	UtmMedium    string `json:"utm_medium"`
	UtmCampaign  string `json:"utm_campaign"`
	UtmContent   string `json:"utm_content"`
	Referrer     string `json:"referrer"`
}

// 30000000 -> "30.000.000đ"
func vnd(n int64) string {
	str := itoa(n)
	out := []byte{}
	for i := range str {
		if i > 0 && (len(str)-i)%3 == 0 {
			out = append(out, '.')
		}
		out = append(out, str[i])
	}
	return string(out) + "đ"
}

func itoa(n int64) string {
	if n == 0 {
		return "0"
	}
	neg := n < 0
	if neg {
		n = -n
	}
	b := []byte{}
	for n > 0 {
		b = append([]byte{byte('0' + n%10)}, b...)
		n /= 10
	}
	if neg {
		return "-" + string(b)
	}
	return string(b)
}

func (a *App) publicRegister(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	var in registerInput
	if err := readJSON(r, &in); err != nil {
		fail(w, 400, err.Error())
		return
	}
	if in.Website != "" {
		writeJSON(w, 200, map[string]any{"ok": true})
		return
	}

	list, err := a.offersWhere(ctx, `active AND id = $1`, in.OfferID)
	if err != nil {
		serverError(w, err)
		return
	}
	errs := map[string]string{}
	if len(list) == 0 {
		fail(w, 422, "Gói này không còn nhận đăng ký. Hãy chọn gói khác.")
		return
	}
	offer := list[0]
	isLoan := offer.Type == "loan"

	in.FullName = clip(in.FullName, 80)
	if len([]rune(in.FullName)) < 2 {
		errs["full_name"] = "Nhập họ tên như trên CCCD."
	}
	phone, ok := normalizePhone(in.Phone)
	if !ok {
		errs["phone"] = "Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09."
	}
	in.Email = clip(in.Email, 120)
	if in.Email != "" {
		if _, err := mail.ParseAddress(in.Email); err != nil || !strings.Contains(in.Email, ".") {
			errs["email"] = "Email chưa đúng định dạng, ví dụ: ten@gmail.com."
		}
	}
	age := time.Now().Year() - in.BirthYear
	if in.BirthYear == 0 {
		errs["birth_year"] = "Chọn năm sinh."
	} else if age < 18 || age > 70 {
		errs["birth_year"] = "Đối tác chỉ nhận hồ sơ từ 18 đến 70 tuổi."
	}
	validProvince := false
	for _, p := range provinces {
		if p == in.Province {
			validProvince = true
		}
	}
	if !validProvince {
		errs["province"] = "Chọn tỉnh/thành bạn đang sống."
	}
	if !hasOpt(occupationOpts, in.Occupation) {
		errs["occupation"] = "Chọn công việc hiện tại."
	}
	if !hasOpt(incomeOpts, in.Income) {
		errs["income"] = "Chọn mức thu nhập hằng tháng."
	}
	if isLoan {
		if in.Amount < 1_000_000 {
			errs["amount"] = "Nhập số tiền muốn vay, từ 1.000.000đ."
		} else if offer.AmountMax > 0 && (in.Amount < offer.AmountMin || in.Amount > offer.AmountMax) {
			errs["amount"] = "Gói này cho vay từ " + vnd(offer.AmountMin) + " đến " + vnd(offer.AmountMax) + "."
		}
		okTerm := false
		for _, t := range termOpts {
			if t == in.Term {
				okTerm = true
			}
		}
		if !okTerm {
			errs["term"] = "Chọn thời gian trả."
		}
		if !hasOpt(purposeOpts, in.Purpose) {
			errs["purpose"] = "Chọn mục đích vay."
		}
		if !hasOpt(salaryOpts, in.SalaryMethod) {
			errs["salary_method"] = "Chọn hình thức nhận lương."
		}
		in.HasCard, in.CardNeed = "", ""
	} else {
		if !hasOpt(hasCardOpts, in.HasCard) {
			errs["has_card"] = "Cho biết bạn đã có thẻ tín dụng chưa."
		}
		if !hasOpt(cardNeedOpts, in.CardNeed) {
			errs["card_need"] = "Chọn nhu cầu dùng thẻ."
		}
		in.Amount, in.Term, in.Purpose, in.SalaryMethod = 0, 0, "", ""
	}
	if !in.Consent {
		errs["consent"] = "Bạn cần đồng ý với điều khoản và chính sách dữ liệu cá nhân để gửi đăng ký."
	}
	if len(errs) > 0 {
		failFields(w, errs)
		return
	}

	ip := a.clientIP(r)
	var recent int
	if err := a.db.QueryRow(ctx, `SELECT COUNT(*) FROM registrations WHERE ip = $1 AND created_at > now() - interval '1 hour'`, ip).Scan(&recent); err != nil {
		serverError(w, err)
		return
	}
	if recent >= 15 {
		fail(w, 429, "Bạn đã gửi quá nhiều đăng ký. Hãy thử lại sau 1 giờ.")
		return
	}

	var landingID *int64
	if in.LandingID > 0 {
		var exists bool
		_ = a.db.QueryRow(ctx, `SELECT EXISTS (SELECT 1 FROM landings WHERE id = $1)`, in.LandingID).Scan(&exists)
		if exists {
			landingID = &in.LandingID
		}
	}
	var amount, term any
	if isLoan {
		amount, term = in.Amount, in.Term
	}

	consent := a.consentVersions(ctx)
	tx, err := a.db.Begin(ctx)
	if err != nil {
		serverError(w, err)
		return
	}
	defer tx.Rollback(ctx)

	var customerID int64
	err = tx.QueryRow(ctx, `INSERT INTO customers (full_name, phone, email, birth_year, province, occupation, income)
		VALUES ($1,$2,$3,$4,$5,$6,$7)
		ON CONFLICT (phone) DO UPDATE SET full_name = EXCLUDED.full_name,
			email = COALESCE(NULLIF(EXCLUDED.email, ''), customers.email), birth_year = EXCLUDED.birth_year,
			province = EXCLUDED.province, occupation = EXCLUDED.occupation, income = EXCLUDED.income, updated_at = now()
		RETURNING id`, in.FullName, phone, in.Email, in.BirthYear, in.Province, in.Occupation, in.Income).Scan(&customerID)
	if err != nil {
		serverError(w, err)
		return
	}

	// Cùng SĐT đăng ký lại cùng gói trong 24h: cập nhật bản ghi cũ thay vì tạo trùng
	var regID int64
	err = tx.QueryRow(ctx, `SELECT id FROM registrations WHERE customer_id = $1 AND offer_id = $2 AND created_at > now() - interval '24 hours' ORDER BY id DESC LIMIT 1`,
		customerID, offer.ID).Scan(&regID)
	if err == pgx.ErrNoRows {
		err = tx.QueryRow(ctx, `INSERT INTO registrations (customer_id, offer_id, offer_name, offer_type, landing_id, full_name, phone, email, birth_year,
			province, occupation, income, amount, term, purpose, salary_method, has_card, card_need, utm_source, utm_medium, utm_campaign, utm_content,
			referrer, ip, user_agent, consent_at, consent_versions)
			VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21,$22,$23,$24,$25, now(), $26) RETURNING id`,
			customerID, offer.ID, offer.Name, offer.Type, landingID, in.FullName, phone, in.Email, in.BirthYear, in.Province, in.Occupation, in.Income,
			amount, term, in.Purpose, in.SalaryMethod, in.HasCard, in.CardNeed, clip(in.UtmSource, 120), clip(in.UtmMedium, 120), clip(in.UtmCampaign, 120),
			clip(in.UtmContent, 120), clip(in.Referrer, 255), ip, clip(r.UserAgent(), 255), consent).Scan(&regID)
	} else if err == nil {
		_, err = tx.Exec(ctx, `UPDATE registrations SET full_name=$2, email=$3, birth_year=$4, province=$5, occupation=$6, income=$7, amount=$8, term=$9,
			purpose=$10, salary_method=$11, has_card=$12, card_need=$13, consent_at=now(), consent_versions=$14, updated_at=now() WHERE id=$1`,
			regID, in.FullName, in.Email, in.BirthYear, in.Province, in.Occupation, in.Income, amount, term, in.Purpose, in.SalaryMethod, in.HasCard, in.CardNeed, consent)
	}
	if err != nil {
		serverError(w, err)
		return
	}
	if err := tx.Commit(ctx); err != nil {
		serverError(w, err)
		return
	}

	redirect := ""
	if strings.TrimSpace(offer.AffiliateURL) != "" {
		redirect = "/go/" + itoa(regID) + "-" + a.sign(regID)
	}
	writeJSON(w, 200, map[string]any{"ok": true, "id": regID, "redirect": redirect, "offer": offer.Name, "type": offer.Type})
}

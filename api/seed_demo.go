package main

import (
	"context"
	"fmt"
	"math/rand/v2"
	"time"

	"golang.org/x/crypto/bcrypt"
)

// Dữ liệu minh hoạ cho bản demo (SEED_DEMO=1). Không bật trên website thật.
func (a *App) seedDemo(ctx context.Context) error {
	rnd := rand.New(rand.NewPCG(7, 11))
	var fbID int64
	err := a.db.QueryRow(ctx, `INSERT INTO landings (slug, name, headline, subheadline, offer_type, show_calculator, seo_title)
		VALUES ('fb-vay-nhanh-t11', 'FB – Vay nhanh tháng 11', 'Vay nhanh trong ngày, *chỉ cần CCCD*', 'Chọn số tiền, xem ngay khoản trả hằng tháng và đăng ký trong 2 phút.', 'loan', TRUE, 'Vay nhanh trong ngày')
		RETURNING id`).Scan(&fbID)
	if err != nil {
		return err
	}
	demoHash, _ := bcrypt.GenerateFromPassword([]byte("demo12345"), bcrypt.DefaultCost)
	if _, err := a.db.Exec(ctx, `INSERT INTO admins (name, email, password_hash, role) VALUES ('Quản trị demo', 'demo@genzbaothe.com', $1, 'admin')
		ON CONFLICT DO NOTHING`, string(demoHash)); err != nil {
		return err
	}
	offers, err := a.offersWhere(ctx, `TRUE ORDER BY id`)
	if err != nil {
		return err
	}
	pick := func(list []opt) string { return list[rnd.IntN(len(list))].Value }
	first := []string{"Nguyễn", "Trần", "Lê", "Phạm", "Hoàng", "Vũ", "Đặng", "Bùi", "Đỗ", "Ngô"}
	mid := []string{"Văn", "Thị", "Minh", "Quốc", "Thu", "Ngọc", "Đức", "Hải"}
	last := []string{"An", "Bình", "Châu", "Dũng", "Giang", "Hà", "Khánh", "Linh", "Nam", "Phúc", "Quân", "Trang", "Vy", "Yến"}
	srcs := [][3]string{{"facebook", "cpc", "vay-nhanh-t11"}, {"facebook", "cpc", "vay-nhanh-t11"}, {"tiktok", "video", "the-t11"}, {"google", "organic", ""}, {"", "", ""}}
	statuses := []string{"new", "new", "contacted", "contacted", "submitted", "approved", "approved", "rejected", "spam"}
	var phones []string
	for i := 0; i < 70; i++ {
		days := int(float64(29) * rnd.Float64() * rnd.Float64())
		at := time.Now().Add(-time.Duration(days)*24*time.Hour - time.Duration(rnd.IntN(36000))*time.Second)
		phone := fmt.Sprintf("09%08d", rnd.IntN(1e8))
		if i%7 == 3 && len(phones) > 2 {
			phone = phones[len(phones)-2] // một số khách đăng ký nhiều gói
		}
		phones = append(phones, phone)
		name := first[rnd.IntN(len(first))] + " " + mid[rnd.IntN(len(mid))] + " " + last[rnd.IntN(len(last))]
		prov := provinces[rnd.IntN(8)]
		occ, inc, by := pick(occupationOpts[:6]), pick(incomeOpts), time.Now().Year()-20-rnd.IntN(32)
		o := offers[rnd.IntN(len(offers))]
		s := srcs[rnd.IntN(len(srcs))]
		var landing int64 = 1
		if s[0] == "tiktok" {
			landing = 2
		} else if s[0] == "facebook" && o.Type == "loan" {
			landing = fbID
		}
		status := statuses[rnd.IntN(len(statuses))]
		if days == 0 {
			status = "new"
		}
		note := ""
		if status == "rejected" {
			note = "Đối tác báo chưa đủ lịch sử tín dụng"
		} else if status == "contacted" {
			note = "Hẹn gọi lại buổi chiều"
		}
		var cid int64
		if err := a.db.QueryRow(ctx, `INSERT INTO customers (full_name, phone, birth_year, province, occupation, income, created_at) VALUES ($1,$2,$3,$4,$5,$6,$7)
			ON CONFLICT (phone) DO UPDATE SET updated_at = now() RETURNING id`, name, phone, by, prov, occ, inc, at).Scan(&cid); err != nil {
			return err
		}
		var amount, term any
		purpose, salary, hasCard, need, email := "", "", "", "", ""
		if o.Type == "loan" {
			lo, hi := max(1, o.AmountMin/1e6), min(200, o.AmountMax/1e6)
			amount = (lo + rnd.Int64N(hi-lo+1)) * 1e6
			term = []int{6, 12, 24, 36}[rnd.IntN(4)]
			purpose, salary = pick(purposeOpts), salaryOpts[min(rnd.IntN(10)/7, 1)].Value
		} else {
			hasCard, need, email = pick(hasCardOpts), pick(cardNeedOpts), fmt.Sprintf("khach%d@gmail.com", i+1)
		}
		var clicked any
		if rnd.IntN(100) < 72 {
			clicked = at.Add(20 * time.Second)
		}
		_, err := a.db.Exec(ctx, `INSERT INTO registrations (customer_id, offer_id, offer_name, offer_type, landing_id, full_name, phone, email, birth_year, province,
			occupation, income, amount, term, purpose, salary_method, has_card, card_need, status, note, utm_source, utm_medium, utm_campaign, ip, user_agent,
			clicked_out_at, created_at, updated_at)
			VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21,$22,$23,$24,$25,$26,$27,$27)`,
			cid, o.ID, o.Name, o.Type, landing, name, phone, email, by, prov, occ, inc, amount, term, purpose, salary, hasCard, need, status, note,
			s[0], s[1], s[2], fmt.Sprintf("113.161.%d.%d", 1+rnd.IntN(254), 1+rnd.IntN(254)),
			"Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) Safari/604.1", clicked, at)
		if err != nil {
			return err
		}
	}
	for d := 0; d < 30; d++ {
		for lid, base := range map[int64]int{1: 70, 2: 25, fbID: 55} {
			if _, err := a.db.Exec(ctx, `INSERT INTO landing_views (landing_id, day, views) VALUES ($1, current_date - $2::int, $3)`, lid, d, base+rnd.IntN(40)); err != nil {
				return err
			}
		}
	}
	return nil
}

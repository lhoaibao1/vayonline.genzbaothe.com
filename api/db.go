package main

import (
	"context"
	"embed"
	"fmt"
	"sort"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
)

//go:embed migrations/*.sql
var migrationFS embed.FS

type Offer struct {
	ID           int64     `db:"id" json:"id"`
	Type         string    `db:"type" json:"type"`
	Slug         string    `db:"slug" json:"slug"`
	Name         string    `db:"name" json:"name"`
	Partner      string    `db:"partner" json:"partner"`
	Logo         string    `db:"logo" json:"logo"`
	Badge        string    `db:"badge" json:"badge"`
	AmountMin    int64     `db:"amount_min" json:"amount_min"`
	AmountMax    int64     `db:"amount_max" json:"amount_max"`
	RateText     string    `db:"rate_text" json:"rate_text"`
	RateMin      float64   `db:"rate_min" json:"rate_min"`
	TermMin      int       `db:"term_min" json:"term_min"`
	TermMax      int       `db:"term_max" json:"term_max"`
	AgeText      string    `db:"age_text" json:"age_text"`
	ApprovalText string    `db:"approval_text" json:"approval_text"`
	AnnualFee    string    `db:"annual_fee" json:"annual_fee"`
	Highlights   string    `db:"highlights" json:"highlights"`
	Requirements string    `db:"requirements" json:"requirements"`
	Description  string    `db:"description" json:"description"`
	AffiliateURL string    `db:"affiliate_url" json:"affiliate_url,omitempty"`
	SortOrder    int       `db:"sort_order" json:"sort_order"`
	Active       bool      `db:"active" json:"active"`
	CreatedAt    time.Time `db:"created_at" json:"created_at"`
	UpdatedAt    time.Time `db:"updated_at" json:"updated_at"`
}

const offerCols = `id, type, slug, name, partner, logo, badge, amount_min, amount_max, rate_text, rate_min::float8 AS rate_min, term_min, term_max,
	age_text, approval_text, annual_fee, highlights, requirements, description, affiliate_url, sort_order, active, created_at, updated_at`

type Landing struct {
	ID             int64     `db:"id" json:"id"`
	Slug           string    `db:"slug" json:"slug"`
	Name           string    `db:"name" json:"name"`
	Headline       string    `db:"headline" json:"headline"`
	Subheadline    string    `db:"subheadline" json:"subheadline"`
	OfferType      string    `db:"offer_type" json:"offer_type"`
	OfferIDs       []int64   `db:"offer_ids" json:"offer_ids"`
	ShowCalculator bool      `db:"show_calculator" json:"show_calculator"`
	SeoTitle       string    `db:"seo_title" json:"seo_title"`
	SeoDescription string    `db:"seo_description" json:"seo_description"`
	IsDefault      bool      `db:"is_default" json:"is_default"`
	Active         bool      `db:"active" json:"active"`
	CreatedAt      time.Time `db:"created_at" json:"created_at"`
	UpdatedAt      time.Time `db:"updated_at" json:"updated_at"`
}

const landingCols = `id, slug, name, headline, subheadline, offer_type, offer_ids, show_calculator, seo_title, seo_description, is_default, active, created_at, updated_at`

func (a *App) migrate(ctx context.Context) error {
	if _, err := a.db.Exec(ctx, `CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT now())`); err != nil {
		return err
	}
	entries, err := migrationFS.ReadDir("migrations")
	if err != nil {
		return err
	}
	names := make([]string, 0, len(entries))
	for _, e := range entries {
		names = append(names, e.Name())
	}
	sort.Strings(names)
	for _, name := range names {
		var done bool
		if err := a.db.QueryRow(ctx, `SELECT EXISTS (SELECT 1 FROM schema_migrations WHERE name = $1)`, name).Scan(&done); err != nil {
			return err
		}
		if done {
			continue
		}
		sql, _ := migrationFS.ReadFile("migrations/" + name)
		tx, err := a.db.Begin(ctx)
		if err != nil {
			return err
		}
		if _, err := tx.Exec(ctx, string(sql)); err != nil {
			_ = tx.Rollback(ctx)
			return fmt.Errorf("%s: %w", name, err)
		}
		if _, err := tx.Exec(ctx, `INSERT INTO schema_migrations (name) VALUES ($1)`, name); err != nil {
			_ = tx.Rollback(ctx)
			return err
		}
		if err := tx.Commit(ctx); err != nil {
			return err
		}
	}
	return nil
}

func (a *App) settings(ctx context.Context) (map[string]string, error) {
	rows, err := a.db.Query(ctx, `SELECT key, value FROM settings`)
	if err != nil {
		return nil, err
	}
	out := map[string]string{}
	for rows.Next() {
		var k, v string
		if err := rows.Scan(&k, &v); err != nil {
			return nil, err
		}
		out[k] = v
	}
	return out, rows.Err()
}

func (a *App) offersWhere(ctx context.Context, where string, args ...any) ([]Offer, error) {
	rows, err := a.db.Query(ctx, `SELECT `+offerCols+` FROM offers WHERE `+where, args...)
	if err != nil {
		return nil, err
	}
	return pgx.CollectRows(rows, pgx.RowToStructByName[Offer])
}

func (a *App) landingWhere(ctx context.Context, where string, args ...any) (*Landing, error) {
	rows, err := a.db.Query(ctx, `SELECT `+landingCols+` FROM landings WHERE `+where+` LIMIT 1`, args...)
	if err != nil {
		return nil, err
	}
	l, err := pgx.CollectOneRow(rows, pgx.RowToStructByName[Landing])
	if err == pgx.ErrNoRows {
		return nil, nil
	}
	return &l, err
}

// Dữ liệu khởi tạo lần đầu: cài đặt, gói mẫu, landing mẫu
func (a *App) seed(ctx context.Context) error {
	defaults := map[string]string{
		"site_name":     "GenZbaothe.com",
		"site_tagline":  "Vay tiêu dùng và mở thẻ tín dụng online, đăng ký trong 2 phút",
		"hotline":       "",
		"zalo":          "",
		"contact_email": "",
		"company_info":  "Website giới thiệu sản phẩm tài chính từ các tổ chức tín dụng được cấp phép. Chúng tôi không trực tiếp cho vay và không thu bất kỳ khoản phí nào từ khách hàng.",
		"disclaimer":    "Lãi suất, hạn mức và thời gian duyệt chỉ mang tính tham khảo, có thể thay đổi theo chính sách của từng đối tác và hồ sơ của bạn. Quyết định phê duyệt thuộc về tổ chức tín dụng. Hãy đọc kỹ hợp đồng trước khi ký.",
		"faq": strings.Join([]string{
			"Tôi có mất phí khi đăng ký không?|Không. Việc đăng ký qua website là miễn phí. Bạn chỉ trả lãi và phí theo hợp đồng ký trực tiếp với đối tác.",
			"Cần giấy tờ gì để vay?|Thường chỉ cần CCCD gắn chip còn hiệu lực. Khoản vay hạn mức cao cần thêm sao kê lương hoặc hợp đồng lao động.",
			"Bao lâu thì có kết quả?|Khoản vay online thường có kết quả trong 15 phút đến 24 giờ, thẻ tín dụng từ 1 đến 7 ngày làm việc.",
			"Nợ xấu có vay được không?|Các khoản vay đều thẩm định lịch sử tín dụng. Nếu đang có nợ nhóm 3 trở lên, khả năng được duyệt rất thấp.",
			"Thông tin của tôi có được bảo mật không?|Thông tin chỉ dùng để chuyển đến đối tác bạn đã chọn và để tư vấn viên liên hệ. Chúng tôi không bán dữ liệu cho bên thứ ba.",
		}, "\n"),
		"head_scripts": "",
		"body_scripts": "",
	}
	for k, v := range defaults {
		if _, err := a.db.Exec(ctx, `INSERT INTO settings (key, value) VALUES ($1, $2) ON CONFLICT (key) DO NOTHING`, k, v); err != nil {
			return err
		}
	}

	var n int
	if err := a.db.QueryRow(ctx, `SELECT COUNT(*) FROM offers`).Scan(&n); err != nil || n > 0 {
		return err
	}
	type seedOffer struct {
		typ, slug, name, partner, badge string
		min, max                        int64
		rateText                        string
		rate                            float64
		tmin, tmax                      int
		age, approval, fee, hl, req     string
	}
	loanReq := "Công dân Việt Nam từ 20 tuổi\nCCCD gắn chip còn hiệu lực\nCó thu nhập ổn định"
	cardReq := "Công dân Việt Nam từ 18 tuổi\nThu nhập từ 5 triệu/tháng\nKhông có nợ xấu"
	seeds := []seedOffer{
		{"loan", "vay-tieu-dung-cccd", "Vay tiêu dùng chỉ cần CCCD", "Đối tác mẫu A", "Duyệt nhanh", 5e6, 70e6, "Từ 1,6%/tháng", 19.2, 6, 36, "20 – 60 tuổi", "Trong 24 giờ", "",
			"Chỉ cần CCCD gắn chip\nGiải ngân qua tài khoản ngân hàng\nTrả góp cố định hằng tháng", loanReq},
		{"loan", "vay-online-24h", "Vay online giải ngân trong ngày", "Đối tác mẫu B", "Khoản nhỏ", 1e6, 20e6, "Từ 2,2%/tháng", 26.4, 3, 12, "18 – 60 tuổi", "15 – 30 phút", "",
			"Hồ sơ online 100%\nKhoản vay đầu tiên có thể miễn lãi 7 ngày\nTất toán sớm linh hoạt", loanReq},
		{"loan", "vay-tin-chap-theo-luong", "Vay tín chấp theo lương", "Ngân hàng mẫu C", "Hạn mức cao", 30e6, 500e6, "Từ 1,1%/tháng", 13.2, 12, 60, "22 – 58 tuổi", "2 – 3 ngày", "",
			"Hạn mức đến 15 lần lương\nKhông cần tài sản đảm bảo\nLãi tính trên dư nợ giảm dần", "Nhận lương qua chuyển khoản từ 7 triệu/tháng\nHợp đồng lao động từ 1 năm"},
		{"card", "the-hoan-tien", "Thẻ tín dụng hoàn tiền mua sắm", "Ngân hàng mẫu D", "Hoàn tiền 10%", 10e6, 100e6, "Miễn lãi đến 55 ngày", 0, 0, 0, "18 – 65 tuổi", "3 – 5 ngày", "Miễn phí năm đầu",
			"Hoàn tiền 10% khi mua sắm online\nTrả góp 0% tại đối tác liên kết\nMở thẻ online, có thẻ phi vật lý ngay", cardReq},
		{"card", "the-du-lich", "Thẻ tín dụng du lịch", "Ngân hàng mẫu E", "Tích dặm", 20e6, 300e6, "Miễn lãi đến 45 ngày", 0, 0, 0, "21 – 65 tuổi", "5 – 7 ngày", "899.000đ/năm",
			"Tích dặm bay cho mọi chi tiêu\nPhòng chờ sân bay miễn phí\nBảo hiểm du lịch toàn cầu", cardReq},
	}
	for i, s := range seeds {
		_, err := a.db.Exec(ctx, `INSERT INTO offers (type, slug, name, partner, badge, amount_min, amount_max, rate_text, rate_min, term_min, term_max,
			age_text, approval_text, annual_fee, highlights, requirements, description, affiliate_url, sort_order)
			VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19)`,
			s.typ, s.slug, s.name, s.partner, s.badge, s.min, s.max, s.rateText, s.rate, s.tmin, s.tmax, s.age, s.approval, s.fee, s.hl, s.req,
			"Đây là gói mẫu. Hãy sửa nội dung, link đối tác và logo trong trang quản trị.",
			"https://example.com/?sub_id={sub_id}&utm_source={utm_source}", (i+1)*10)
		if err != nil {
			return err
		}
	}
	_, err := a.db.Exec(ctx, `INSERT INTO landings (slug, name, headline, subheadline, offer_type, show_calculator, seo_title, seo_description, is_default) VALUES
		('trang-chu', 'Trang chủ', 'Cần tiền gấp? *Chọn gói, duyệt liền.*', 'Kéo thanh chọn số tiền, xem ngay mỗi tháng trả bao nhiêu. Đăng ký 2 phút, đối tác gọi lại trong ngày.', 'all', TRUE,
		 'GenZbaothe.com – Vay tiêu dùng & thẻ tín dụng online', 'Vay tiêu dùng online và mở thẻ tín dụng: so sánh lãi suất, hạn mức, đăng ký trong 2 phút.', TRUE),
		('mo-the-tin-dung', 'Chiến dịch thẻ tín dụng', 'Mở thẻ online, *miễn lãi 55 ngày*', 'So sánh ưu đãi hoàn tiền, phí thường niên và chọn thẻ hợp thói quen chi tiêu của bạn.', 'card', FALSE,
		 'Mở thẻ tín dụng online – GenZbaothe.com', 'So sánh thẻ tín dụng hoàn tiền, du lịch. Mở thẻ online.', FALSE)`)
	if err != nil {
		return err
	}
	if a.cfg.SeedDemo {
		return a.seedDemo(ctx)
	}
	return nil
}

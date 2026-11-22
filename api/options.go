package main

import "net/http"

// Danh sách lựa chọn dùng chung cho form đăng ký (Nuxt) và CMS (Vue)
type opt struct {
	Value string `json:"value"`
	Label string `json:"label"`
}

var (
	provinces = []string{"Hà Nội", "TP. Hồ Chí Minh", "Hải Phòng", "Đà Nẵng", "Cần Thơ", "An Giang", "Bắc Ninh", "Cà Mau", "Cao Bằng", "Đắk Lắk",
		"Điện Biên", "Đồng Nai", "Đồng Tháp", "Gia Lai", "Hà Tĩnh", "Hưng Yên", "Huế", "Khánh Hòa", "Lai Châu", "Lâm Đồng", "Lạng Sơn", "Lào Cai",
		"Nghệ An", "Ninh Bình", "Phú Thọ", "Quảng Ngãi", "Quảng Ninh", "Quảng Trị", "Sơn La", "Tây Ninh", "Thái Nguyên", "Thanh Hóa", "Tuyên Quang", "Vĩnh Long"}
	incomeOpts     = []opt{{"duoi-5", "Dưới 5 triệu"}, {"5-10", "5 – 10 triệu"}, {"10-20", "10 – 20 triệu"}, {"20-50", "20 – 50 triệu"}, {"tren-50", "Trên 50 triệu"}}
	occupationOpts = []opt{{"van-phong", "Nhân viên văn phòng"}, {"cong-nhan", "Công nhân"}, {"cong-chuc", "Giáo viên / công chức"}, {"kinh-doanh", "Kinh doanh tự do"},
		{"tai-xe", "Tài xế công nghệ / giao hàng"}, {"sinh-vien", "Sinh viên"}, {"khac", "Khác"}}
	purposeOpts = []opt{{"tieu-dung", "Tiêu dùng cá nhân"}, {"mua-xe", "Mua xe"}, {"sua-nha", "Sửa / mua đồ nhà"}, {"hoc-phi", "Học phí"},
		{"kinh-doanh", "Kinh doanh nhỏ"}, {"y-te", "Khám chữa bệnh"}, {"khac", "Khác"}}
	salaryOpts   = []opt{{"chuyen-khoan", "Chuyển khoản qua ngân hàng"}, {"tien-mat", "Tiền mặt"}}
	hasCardOpts  = []opt{{"chua", "Chưa có thẻ tín dụng"}, {"co-1", "Đang có 1 thẻ"}, {"co-2", "Đang có từ 2 thẻ"}}
	cardNeedOpts = []opt{{"mua-sam", "Mua sắm online"}, {"tra-gop", "Trả góp 0%"}, {"du-lich", "Du lịch / đặt vé"}, {"hang-ngay", "Chi tiêu hằng ngày"}, {"hoan-tien", "Hoàn tiền / tích điểm"}}
	termOpts     = []int{3, 6, 12, 18, 24, 36, 48, 60}
	statusOpts   = []opt{{"new", "Mới"}, {"contacted", "Đã liên hệ"}, {"submitted", "Đã nộp hồ sơ"}, {"approved", "Được duyệt"}, {"rejected", "Từ chối"}, {"spam", "Rác / trùng"}}
)

func hasOpt(list []opt, v string) bool {
	for _, o := range list {
		if o.Value == v {
			return true
		}
	}
	return false
}

func optLabel(list []opt, v string) string {
	for _, o := range list {
		if o.Value == v {
			return o.Label
		}
	}
	return v
}

func (a *App) publicOptions(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "public, max-age=300")
	writeJSON(w, 200, map[string]any{
		"provinces": provinces, "income": incomeOpts, "occupation": occupationOpts, "purpose": purposeOpts,
		"salary_method": salaryOpts, "has_card": hasCardOpts, "card_need": cardNeedOpts, "terms": termOpts, "status": statusOpts,
	})
}

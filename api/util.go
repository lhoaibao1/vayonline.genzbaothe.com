package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"io"
	"log"
	"net"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"unicode"

	"golang.org/x/text/unicode/norm"
)

// Lỗi trả về cho giao diện: message chung + lỗi theo từng trường
type apiError struct {
	Message string            `json:"message,omitempty"`
	Errors  map[string]string `json:"errors,omitempty"`
}

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(v)
}

func fail(w http.ResponseWriter, code int, msg string) {
	writeJSON(w, code, apiError{Message: msg})
}

func failFields(w http.ResponseWriter, errs map[string]string) {
	writeJSON(w, 422, apiError{Message: "Kiểm tra lại các ô được đánh dấu.", Errors: errs})
}

func serverError(w http.ResponseWriter, err error) {
	log.Printf("lỗi: %v", err)
	fail(w, 500, "Hệ thống đang gặp sự cố. Thử lại sau ít phút.")
}

func readJSON(r *http.Request, v any) error {
	body := io.LimitReader(r.Body, 1<<20)
	dec := json.NewDecoder(body)
	if err := dec.Decode(v); err != nil {
		return errors.New("Dữ liệu gửi lên không đúng định dạng.")
	}
	return nil
}

func pathID(r *http.Request) int64 {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	return id
}

func qInt(r *http.Request, k string) int64 {
	v, _ := strconv.ParseInt(r.URL.Query().Get(k), 10, 64)
	return v
}

func (a *App) clientIP(r *http.Request) string {
	if a.cfg.TrustProxy {
		// Caddy gửi IP đã xác thực (kể cả khi qua Cloudflare) trong X-Real-IP
		if ip := strings.TrimSpace(r.Header.Get("X-Real-IP")); ip != "" {
			return ip
		}
		if xf := r.Header.Get("X-Forwarded-For"); xf != "" {
			return strings.TrimSpace(strings.Split(xf, ",")[0])
		}
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return host
}

var phoneDigits = regexp.MustCompile(`\D+`)
var phoneVN = regexp.MustCompile(`^0[35789]\d{8}$`)

// Chuẩn hoá SĐT di động Việt Nam về dạng 0xxxxxxxxx
func normalizePhone(p string) (string, bool) {
	p = phoneDigits.ReplaceAllString(p, "")
	if strings.HasPrefix(p, "84") {
		p = "0" + p[2:]
	}
	return p, phoneVN.MatchString(p)
}

var nonSlug = regexp.MustCompile(`[^a-z0-9]+`)

func slugify(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	s = strings.ReplaceAll(s, "đ", "d")
	var b strings.Builder
	for _, r := range norm.NFD.String(s) {
		if unicode.Is(unicode.Mn, r) {
			continue
		}
		b.WriteRune(r)
	}
	out := strings.Trim(nonSlug.ReplaceAllString(b.String(), "-"), "-")
	if out == "" {
		return "item"
	}
	return out
}

// Ký link chuyển hướng /go/{id}-{sig}; Rust tracker kiểm tra lại bằng cùng APP_KEY
func (a *App) sign(id int64) string {
	m := hmac.New(sha256.New, []byte(a.cfg.AppKey))
	m.Write([]byte("go" + strconv.FormatInt(id, 10)))
	return hex.EncodeToString(m.Sum(nil))[:16]
}

func clip(s string, n int) string {
	s = strings.TrimSpace(s)
	r := []rune(s)
	if len(r) > n {
		return string(r[:n])
	}
	return s
}

func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h := w.Header()
		h.Set("X-Content-Type-Options", "nosniff")
		h.Set("Referrer-Policy", "strict-origin-when-cross-origin")
		if strings.HasPrefix(r.URL.Path, "/api/admin") {
			h.Set("Cache-Control", "no-store")
		}
		next.ServeHTTP(w, r)
	})
}

func recoverer(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if v := recover(); v != nil {
				log.Printf("panic %s %s: %v", r.Method, r.URL.Path, v)
				fail(w, 500, "Hệ thống đang gặp sự cố. Thử lại sau ít phút.")
			}
		}()
		next.ServeHTTP(w, r)
	})
}

func noDirList(h http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "" || strings.HasSuffix(r.URL.Path, "/") {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Cache-Control", "public, max-age=2592000")
		h.ServeHTTP(w, r)
	})
}

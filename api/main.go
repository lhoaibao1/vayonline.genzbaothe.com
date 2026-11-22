// GenZbaothe.com API: landing (công khai) và CMS (quản trị).
package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
)

type Config struct {
	Addr         string
	DatabaseURL  string
	AppKey       string // dùng chung với Rust tracker để ký link /go/
	UploadDir    string
	CookieSecure bool
	TrustProxy   bool
	SeedDemo     bool
}

type App struct {
	cfg Config
	db  *pgxpool.Pool
}

func env(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}

func main() {
	cfg := Config{
		Addr:         env("API_ADDR", ":8080"),
		DatabaseURL:  env("DATABASE_URL", "postgres://genz:genz@localhost:5432/genz?sslmode=disable"),
		AppKey:       os.Getenv("APP_KEY"),
		UploadDir:    env("UPLOAD_DIR", "./uploads"),
		CookieSecure: env("COOKIE_SECURE", "1") == "1",
		TrustProxy:   env("TRUST_PROXY", "1") == "1",
		SeedDemo:     env("SEED_DEMO", "0") == "1",
	}
	if len(cfg.AppKey) < 32 {
		log.Fatal("APP_KEY phải dài ít nhất 32 ký tự (dùng chung với tracker)")
	}
	if err := os.MkdirAll(cfg.UploadDir, 0o755); err != nil {
		log.Fatal(err)
	}

	ctx := context.Background()
	pool, err := pgxpool.New(ctx, cfg.DatabaseURL)
	if err != nil {
		log.Fatal(err)
	}
	defer pool.Close()
	for i := 0; i < 30; i++ { // chờ Postgres khởi động
		if err = pool.Ping(ctx); err == nil {
			break
		}
		time.Sleep(time.Second)
	}
	if err != nil {
		log.Fatal("không kết nối được database: ", err)
	}

	app := &App{cfg: cfg, db: pool}
	if err := app.migrate(ctx); err != nil {
		log.Fatal("migrate: ", err)
	}
	if err := app.seed(ctx); err != nil {
		log.Fatal("seed: ", err)
	}

	srv := &http.Server{
		Addr:              cfg.Addr,
		Handler:           app.routes(),
		ReadHeaderTimeout: 10 * time.Second,
		ReadTimeout:       30 * time.Second,
		WriteTimeout:      60 * time.Second,
	}
	go func() {
		log.Printf("API chạy tại %s", cfg.Addr)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatal(err)
		}
	}()
	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)
	<-stop
	shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(shutdownCtx)
}

func (a *App) routes() http.Handler {
	mux := http.NewServeMux()

	// Công khai (landing Nuxt gọi)
	mux.HandleFunc("GET /api/healthz", func(w http.ResponseWriter, r *http.Request) { writeJSON(w, 200, map[string]bool{"ok": true}) })
	mux.HandleFunc("GET /api/public/site", a.publicSite)
	mux.HandleFunc("GET /api/public/options", a.publicOptions)
	mux.HandleFunc("GET /api/public/landing", a.publicLanding)      // ?slug= (trống = trang chủ)
	mux.HandleFunc("GET /api/public/module/{type}", a.publicModule) // loan | card
	mux.HandleFunc("GET /api/public/offers/{slug}", a.publicOffer)
	mux.HandleFunc("POST /api/public/register", a.publicRegister)
	mux.HandleFunc("GET /api/public/pages/{slug}", a.publicPage)
	mux.HandleFunc("POST /api/public/data-requests", a.publicDataRequest)

	// Ảnh logo đã tải lên
	mux.Handle("GET /uploads/", http.StripPrefix("/uploads/", noDirList(http.FileServer(http.Dir(a.cfg.UploadDir)))))

	// Quản trị
	mux.HandleFunc("GET /api/admin/setup", a.adminSetupStatus)
	mux.HandleFunc("POST /api/admin/setup", a.adminSetup)
	mux.HandleFunc("POST /api/admin/login", a.adminLogin)
	mux.HandleFunc("POST /api/admin/logout", a.adminLogout)
	mux.HandleFunc("GET /api/admin/me", a.auth("", a.adminMe))
	mux.HandleFunc("PUT /api/admin/me", a.auth("", a.adminUpdateMe))
	mux.HandleFunc("GET /api/admin/options", a.auth("", a.publicOptions))

	mux.HandleFunc("GET /api/admin/dashboard", a.auth("leads.view", a.adminDashboard))

	mux.HandleFunc("GET /api/admin/leads", a.auth("leads.view", a.adminLeads)) // ?type=loan|card
	mux.HandleFunc("GET /api/admin/leads/export", a.auth("leads.export", a.adminLeadsExport))
	mux.HandleFunc("GET /api/admin/leads/{id}", a.auth("leads.view", a.adminLead))
	mux.HandleFunc("PATCH /api/admin/leads/{id}", a.auth("leads.edit", a.adminLeadUpdate))
	mux.HandleFunc("POST /api/admin/leads/bulk", a.auth("leads.edit", a.adminLeadsBulk))

	mux.HandleFunc("GET /api/admin/customers", a.auth("leads.view", a.adminCustomers))
	mux.HandleFunc("GET /api/admin/customers/{id}", a.auth("leads.view", a.adminCustomer))
	mux.HandleFunc("PUT /api/admin/customers/{id}", a.auth("leads.edit", a.adminCustomerUpdate))
	mux.HandleFunc("DELETE /api/admin/customers/{id}", a.auth("leads.delete", a.adminCustomerDelete))
	mux.HandleFunc("POST /api/admin/customers/{id}/anonymize", a.auth("leads.delete", a.adminCustomerAnonymize))

	mux.HandleFunc("GET /api/admin/data-requests", a.auth("leads.view", a.adminDataRequests))
	mux.HandleFunc("PATCH /api/admin/data-requests/{id}", a.auth("leads.edit", a.adminDataRequestUpdate))
	mux.HandleFunc("GET /api/admin/pages", a.auth("settings", a.adminPages))
	mux.HandleFunc("PUT /api/admin/pages/{slug}", a.auth("settings", a.adminPageSave))

	mux.HandleFunc("GET /api/admin/offers", a.auth("offers", a.adminOffers)) // ?type=loan|card
	mux.HandleFunc("GET /api/admin/offers/{id}", a.auth("offers", a.adminOffer))
	mux.HandleFunc("POST /api/admin/offers", a.auth("offers", a.adminOfferSave))
	mux.HandleFunc("PUT /api/admin/offers/{id}", a.auth("offers", a.adminOfferSave))
	mux.HandleFunc("DELETE /api/admin/offers/{id}", a.auth("offers", a.adminOfferDelete))
	mux.HandleFunc("POST /api/admin/uploads", a.auth("offers", a.adminUpload))

	mux.HandleFunc("GET /api/admin/landings", a.auth("landings", a.adminLandings))
	mux.HandleFunc("GET /api/admin/landings/{id}", a.auth("landings", a.adminLanding))
	mux.HandleFunc("POST /api/admin/landings", a.auth("landings", a.adminLandingSave))
	mux.HandleFunc("PUT /api/admin/landings/{id}", a.auth("landings", a.adminLandingSave))
	mux.HandleFunc("DELETE /api/admin/landings/{id}", a.auth("landings", a.adminLandingDelete))

	mux.HandleFunc("GET /api/admin/users", a.auth("admins", a.adminUsers))
	mux.HandleFunc("POST /api/admin/users", a.auth("admins", a.adminUserSave))
	mux.HandleFunc("PUT /api/admin/users/{id}", a.auth("admins", a.adminUserSave))
	mux.HandleFunc("DELETE /api/admin/users/{id}", a.auth("admins", a.adminUserDelete))

	mux.HandleFunc("GET /api/admin/settings", a.auth("settings", a.adminSettings))
	mux.HandleFunc("PUT /api/admin/settings", a.auth("settings", a.adminSettingsSave))

	return recoverer(securityHeaders(mux))
}

//! GenZbaothe.com tracker
//!
//! - `POST /t/view/{landing_id}`: cộng một lượt xem cho landing trong ngày (giờ Việt Nam).
//! - `GET  /go/{id}-{sig}`: kiểm tra chữ ký do API Go tạo, ghi thời điểm khách bấm sang đối tác,
//!   thay biến trong link affiliate rồi chuyển hướng (302).
//! - `GET  /t/healthz`

use std::{env, net::SocketAddr, time::Duration};

use axum::{
    extract::{Path, State},
    http::{header, HeaderValue, StatusCode},
    response::{IntoResponse, Redirect, Response},
    routing::{get, post},
    Router,
};
use hmac::{Hmac, Mac};
use sha2::Sha256;
use sqlx::{postgres::PgPoolOptions, PgPool};

#[derive(Clone)]
struct AppState {
    db: PgPool,
    app_key: String,
    home_url: String,
}

#[tokio::main]
async fn main() {
    tracing_subscriber::fmt()
        .with_env_filter(env::var("RUST_LOG").unwrap_or_else(|_| "info".into()))
        .init();

    let database_url = env::var("DATABASE_URL").expect("thiếu DATABASE_URL");
    let app_key = env::var("APP_KEY").expect("thiếu APP_KEY (dùng chung với API Go)");
    assert!(app_key.len() >= 32, "APP_KEY phải dài ít nhất 32 ký tự");
    let addr: SocketAddr = env::var("TRACKER_ADDR").unwrap_or_else(|_| "0.0.0.0:8081".into()).parse().expect("TRACKER_ADDR không hợp lệ");

    let db = PgPoolOptions::new()
        .max_connections(10)
        .acquire_timeout(Duration::from_secs(5))
        .connect(&database_url)
        .await
        .expect("không kết nối được database");

    let state = AppState { db, app_key, home_url: env::var("HOME_URL").unwrap_or_else(|_| "/".into()) };
    let app = Router::new()
        .route("/t/healthz", get(|| async { "ok" }))
        .route("/t/view/:landing_id", post(track_view))
        .route("/go/:token", get(go))
        .with_state(state);

    tracing::info!("tracker chạy tại {addr}");
    let listener = tokio::net::TcpListener::bind(addr).await.expect("không mở được cổng");
    axum::serve(listener, app)
        .with_graceful_shutdown(async { let _ = tokio::signal::ctrl_c().await; })
        .await
        .expect("lỗi máy chủ");
}

/// Cộng lượt xem. Trình duyệt gọi bằng navigator.sendBeacon, mỗi phiên một lần cho mỗi landing.
async fn track_view(State(st): State<AppState>, Path(landing_id): Path<i64>) -> StatusCode {
    let res = sqlx::query(
        "INSERT INTO landing_views (landing_id, day, views)
         SELECT id, (now() AT TIME ZONE 'Asia/Ho_Chi_Minh')::date, 1 FROM landings WHERE id = $1 AND active
         ON CONFLICT (landing_id, day) DO UPDATE SET views = landing_views.views + 1",
    )
    .bind(landing_id)
    .execute(&st.db)
    .await;
    match res {
        Ok(_) => StatusCode::NO_CONTENT,
        Err(e) => {
            tracing::error!("ghi lượt xem: {e}");
            StatusCode::INTERNAL_SERVER_ERROR
        }
    }
}

/// Chữ ký giống hệt hàm sign() bên Go: HMAC-SHA256(APP_KEY, "go{id}"), lấy 16 ký tự hex đầu
fn sign(key: &str, id: i64) -> String {
    let mut mac = Hmac::<Sha256>::new_from_slice(key.as_bytes()).expect("khoá HMAC");
    mac.update(format!("go{id}").as_bytes());
    hex::encode(mac.finalize().into_bytes())[..16].to_string()
}

/// So sánh không lộ thời gian
fn same(a: &str, b: &str) -> bool {
    a.len() == b.len() && a.bytes().zip(b.bytes()).fold(0u8, |acc, (x, y)| acc | (x ^ y)) == 0
}

#[derive(sqlx::FromRow)]
struct Target {
    affiliate_url: String,
    utm_source: String,
    utm_campaign: String,
    landing_slug: Option<String>,
}

async fn go(State(st): State<AppState>, Path(token): Path<String>) -> Response {
    let home = || Redirect::to(&st.home_url).into_response();
    let Some((id_str, sig)) = token.split_once('-') else { return home() };
    let Ok(id) = id_str.parse::<i64>() else { return home() };
    if !same(&sign(&st.app_key, id), sig) {
        return (StatusCode::NOT_FOUND, "Link không hợp lệ.").into_response();
    }

    let target = sqlx::query_as::<_, Target>(
        "UPDATE registrations r SET clicked_out_at = COALESCE(r.clicked_out_at, now())
         FROM offers o
         WHERE r.id = $1 AND o.id = r.offer_id
         RETURNING o.affiliate_url, r.utm_source, r.utm_campaign,
                   (SELECT slug FROM landings l WHERE l.id = r.landing_id) AS landing_slug",
    )
    .bind(id)
    .fetch_optional(&st.db)
    .await;

    let t = match target {
        Ok(Some(t)) if !t.affiliate_url.trim().is_empty() => t,
        Ok(_) => return home(),
        Err(e) => {
            tracing::error!("ghi click {id}: {e}");
            return home();
        }
    };

    let enc = |s: &str| url::form_urlencoded::byte_serialize(s.as_bytes()).collect::<String>();
    let dest = t
        .affiliate_url
        .replace("{sub_id}", &format!("R{id}"))
        .replace("{reg_id}", &id.to_string())
        .replace("{utm_source}", &enc(&t.utm_source))
        .replace("{utm_campaign}", &enc(&t.utm_campaign))
        .replace("{landing}", &enc(t.landing_slug.as_deref().unwrap_or("")));

    // Chỉ chuyển sang http/https
    match url::Url::parse(&dest) {
        Ok(u) if u.scheme() == "http" || u.scheme() == "https" => {
            let mut res = Redirect::to(u.as_str()).into_response();
            res.headers_mut().insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
            res.headers_mut().insert(header::REFERRER_POLICY, HeaderValue::from_static("no-referrer"));
            res
        }
        _ => home(),
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn chu_ky_khop_voi_go() {
        // Giá trị lấy từ API Go với cùng APP_KEY
        assert_eq!(sign("0123456789abcdef0123456789abcdef0123456789", 71), "efab1fc03582244a");
        assert!(same("abc", "abc"));
        assert!(!same("abc", "abd"));
    }
}

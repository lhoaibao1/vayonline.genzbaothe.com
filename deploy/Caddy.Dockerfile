# Build CMS Vue rồi đóng gói cùng Caddy
FROM node:22-alpine AS admin
WORKDIR /src
COPY admin/package.json admin/package-lock.json ./
RUN npm ci
COPY admin/ .
RUN npm run build

# Caddy kèm plugin Cloudflare để xin chứng chỉ HTTPS qua DNS (chạy được cả khi bật proxy Cloudflare)
FROM caddy:2.9.1-builder AS caddy
RUN xcaddy build v2.9.1 --with github.com/caddy-dns/cloudflare@v0.2.1

FROM caddy:2.9.1-alpine
COPY --from=caddy /usr/bin/caddy /usr/bin/caddy
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=admin /src/dist /srv/admin

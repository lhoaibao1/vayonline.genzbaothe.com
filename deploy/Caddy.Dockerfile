# Build CMS Vue rồi đóng gói cùng Caddy
FROM node:22-alpine AS admin
WORKDIR /src
COPY admin/package.json admin/package-lock.json ./
RUN npm ci
COPY admin/ .
RUN npm run build

FROM caddy:2.9-alpine
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=admin /src/dist /srv/admin

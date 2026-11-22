# API Go
FROM golang:1.24-alpine AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -ldflags="-s -w" -o /out/api .

FROM alpine:3.21
RUN apk add --no-cache ca-certificates tzdata && adduser -D -u 10001 app && mkdir -p /data/uploads && chown app /data/uploads
USER app
COPY --from=build /out/api /usr/local/bin/api
ENV API_ADDR=:8080 UPLOAD_DIR=/data/uploads TZ=Asia/Ho_Chi_Minh
EXPOSE 8080
CMD ["api"]

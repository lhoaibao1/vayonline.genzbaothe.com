# Tracker Rust
FROM rust:1-slim-bookworm AS build
WORKDIR /src
COPY Cargo.toml Cargo.lock ./
COPY src ./src
RUN cargo build --release

FROM debian:bookworm-slim
RUN useradd -u 10001 app
USER app
COPY --from=build /src/target/release/genz-tracker /usr/local/bin/tracker
ENV TRACKER_ADDR=0.0.0.0:8081
EXPOSE 8081
CMD ["tracker"]

FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod ./
COPY . .
RUN go build -trimpath -ldflags="-s -w" -o /out/kgm-mail-service ./cmd/server

FROM alpine:3.20
RUN adduser -D -H -u 10001 appuser
WORKDIR /app
COPY --from=build /out/kgm-mail-service /app/kgm-mail-service
COPY web /app/web
RUN mkdir -p /app/data/messages /app/data/templates && chown -R appuser:appuser /app
USER appuser
EXPOSE 8088
CMD ["/app/kgm-mail-service"]

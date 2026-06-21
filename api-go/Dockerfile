# syntax=docker/dockerfile:1.7
FROM golang:1.23-alpine AS build
WORKDIR /src
RUN apk add --no-cache ca-certificates tzdata
COPY go.mod go.sum* ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/kgm-api ./cmd/api     && CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/kgm-worker ./cmd/worker

FROM alpine:3.20
RUN apk add --no-cache ca-certificates tzdata && addgroup -S kgm && adduser -S -G kgm kgm
WORKDIR /app
COPY --from=build /out/kgm-api /app/kgm-api
COPY --from=build /out/kgm-worker /app/kgm-worker
USER kgm
EXPOSE 8080
CMD ["/app/kgm-api"]

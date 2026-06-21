APP=kgm-api
WORKER=kgm-worker
GOFLAGS=-trimpath
LDFLAGS=-s -w

.PHONY: build build-api build-worker run run-worker test fmt vet quality clean tree

build: build-api build-worker

build-api:
	go build $(GOFLAGS) -ldflags="$(LDFLAGS)" -o bin/$(APP) ./cmd/api

build-worker:
	go build $(GOFLAGS) -ldflags="$(LDFLAGS)" -o bin/$(WORKER) ./cmd/worker

run:
	go run ./cmd/api

run-worker:
	go run ./cmd/worker

test:
	go test ./...

fmt:
	gofmt -w cmd internal

vet:
	go vet ./...

quality: fmt vet test

clean:
	rm -rf bin

tree:
	find cmd internal -maxdepth 3 -type f | sort

## Structure

target: prerequisites
	recipe (TAB, not spaces)

# All-in-one example
build: deps
	go build -o bin/app ./cmd/app

.PHONY: build test clean lint     — declare non-file targets

## Variables

NAME    = value       — recursively expanded (lazy; evaluated on use)
NAME   := value       — simply expanded (eager; evaluated immediately)
NAME   ?= value       — assign only if not already set (env override friendly)
NAME   += more        — append to existing value

override NAME = val   — override -D flag from CLI
unexport NAME         — prevent exporting to child processes

# CLI override
make NAME=prod build  — override any variable

## Automatic variables (recipe only)

$@    — target name
$<    — first prerequisite
$^    — all prerequisites (no duplicates)
$+    — all prerequisites (with duplicates, in order)
$*    — stem matched by pattern (% in pattern rules)
$(@D) — directory part of $@
$(@F) — file part of $@
$(<D) — dir of $<
$(<F) — file of $<

## Pattern rules

%.o: %.c
	$(CC) -c -o $@ $<

lib/%.so: src/%.c
	$(CC) -shared -fPIC -o $@ $<

# Static pattern rule (apply only to subset)
$(OBJS): %.o: %.c
	$(CC) -c -o $@ $<

## Built-in variables

CC      = cc
CXX     = g++
CFLAGS  = (C compiler flags)
CXXFLAGS
LDFLAGS
LDLIBS
AR      = ar
MAKE    = make (recursive make)
MAKECMDGOALS — goals from command line
MAKEFILE_LIST — list of parsed makefiles

## Phony targets

.PHONY: all build test lint clean fmt help

all: build test lint   — default (first target)

build:
	go build ./...

test:
	go test -race -cover ./...

lint:
	golangci-lint run

fmt:
	gofmt -s -w .
	goimports -w .

clean:
	rm -rf bin/ dist/ *.out

## Functions ($(func args))

$(subst from,to,text)              — string replace
$(patsubst pattern,replacement,text) — pattern replace (% wildcard)
$(strip text)                      — remove extra whitespace
$(filter pattern...,text)          — keep matching words
$(filter-out pattern...,text)      — remove matching words
$(sort list)                       — sort + deduplicate
$(word n,text)                     — nth word (1-indexed)
$(words text)                      — word count
$(firstword text) / $(lastword text)
$(dir names)                       — directory part
$(notdir names)                    — filename part
$(suffix names)                    — extension (.c, .go)
$(basename names)                  — strip extension
$(addsuffix suffix,names)
$(addprefix prefix,names)
$(join list1,list2)                — pair-wise join
$(wildcard pattern)                — glob (shell-safe)
$(realpath name)                   — resolve symlinks
$(abspath name)                    — absolute path, no resolve

$(foreach var,list,text)           — iteration
FILES := $(foreach dir,src lib,$(wildcard $(dir)/*.go))

$(call var,arg1,arg2)              — call user-defined function
UPPER = $(shell echo '$(1)' | tr a-z A-Z)
result := $(call UPPER,hello)

$(if condition,then[,else])        — conditional expansion
$(or text1,text2,...)              — first non-empty
$(and text1,text2,...)             — last if all non-empty, else empty

$(shell command)                   — capture stdout (newlines → spaces)
GIT_SHA := $(shell git rev-parse --short HEAD)

$(origin var)                      — where variable came from (default/environment/file/command line)
$(flavor var)                      — recursive or simple
$(error text)                      — abort with message
$(warning text)                    — print warning, continue
$(info text)                       — print info, continue

$(eval text)                       — evaluate text as make syntax
$(value var)                       — unexpanded value of var

## Conditionals

ifeq ($(ENV),prod)
  FLAGS = -O2
else
  FLAGS = -g
endif

ifneq ($(origin CI),undefined)
  TEST_FLAGS += -v
endif

ifdef DEBUG
  $(info Debug mode on)
endif

ifndef GOPATH
  $(error GOPATH is not set)
endif

## Multi-line variables (define)

define HELP_MSG
Usage:
  make build   — compile
  make test    — run tests
endef

help:
	@echo "$(HELP_MSG)"

# Export multi-line to shell
export SCRIPT
define SCRIPT
#!/bin/bash
set -euo pipefail
echo "hello"
endef

## Include / dependencies

include config.mk
-include optional.mk          — ignore if missing (- prefix)
include $(wildcard *.mk)      — include all .mk files

## Order-only prerequisites (pipe)

bin/app: $(SRCS) | bin          — build bin/ first, but don't rebuild app just because bin/ changes
bin:
	mkdir -p $@

## Double-colon rules (multiple independent rule blocks)

clean::
	rm -rf *.o
clean::
	rm -rf *.test

## Common idioms

# Silent command (suppress echo)
	@echo "Building..."
	@$(MAKE) sub-target

# Silence whole recipe
.SILENT: help

# Fail fast in shell recipes
SHELL = /bin/bash
.SHELLFLAGS = -euo pipefail -c

# Suppress output + check exit code
	@go test ./... 2>&1 | tee test.log; exit $${PIPESTATUS[0]}

# Parallel targets
make -j$(nproc) build test

# Recursive make
subsystem:
	$(MAKE) -C subsystem/

# Target-specific variables
release: FLAGS = -ldflags="-s -w"
release: build

# Private target (convention: _ prefix)
_check-env:
	@command -v docker >/dev/null 2>&1 || (echo "docker required" && exit 1)

## Environment / CI patterns

# Default values with env override
VERSION   ?= $(shell git describe --tags --always --dirty)
REGISTRY  ?= ghcr.io/myorg
IMAGE      = $(REGISTRY)/app:$(VERSION)

build-image:
	docker build --build-arg VERSION=$(VERSION) -t $(IMAGE) .

push:
	docker push $(IMAGE)

# Deploy (depends on build + push)
deploy: build-image push
	helm upgrade --install app chart/ --set image.tag=$(VERSION)

## Help target (self-documenting)

.PHONY: help
help:           ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build:          ## Build the binary
	go build -o bin/app ./cmd/app

test:           ## Run tests with race detector
	go test -race -cover ./...

## Full project Makefile template

BINARY    := app
CMD_DIR   := ./cmd/$(BINARY)
BIN_DIR   := bin
VERSION   ?= $(shell git describe --tags --always --dirty 2>/dev/null || echo "dev")
LDFLAGS   := -ldflags="-s -w -X main.version=$(VERSION)"
GOFLAGS   ?=

.PHONY: all build test lint fmt clean help

all: lint test build   ## lint + test + build (default)

build:                 ## Compile binary
	@mkdir -p $(BIN_DIR)
	go build $(GOFLAGS) $(LDFLAGS) -o $(BIN_DIR)/$(BINARY) $(CMD_DIR)

test:                  ## Run tests (race + cover)
	go test -race -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out -o coverage.html

lint:                  ## Run linter
	golangci-lint run ./...

fmt:                   ## Format code
	gofmt -s -w .
	goimports -w .

clean:                 ## Remove build artifacts
	rm -rf $(BIN_DIR) coverage.out coverage.html

help:                  ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

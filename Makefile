# Makefile for epc_qrcode_generator
APP_NAME = epc_qrcode_generator

.PHONY: dev-setup build-js watch-js build clean test lint

dev-setup:
	npm ci
	composer install

build-js:
	npm run build

watch-js:
	npm run watch

build: build-js

clean:
	rm -rf node_modules
	rm -rf js/*
	rm -rf vendor

test:
	$(MAKE) lint

lint:
	npm run lint
	composer run lint

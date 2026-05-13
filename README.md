# EPC QR Code Generator

Generate EPC QR codes for SEPA transfers directly in Nextcloud.

## Features

- **SEPA Transfer Form** — Enter beneficiary, IBAN, amount, and reference
- **Real-time IBAN Validation** — Validates 50+ countries (modulo 97 check)
- **QR Code Generation** — Uses `qr-code-styling` for high-quality QR codes
- **Full Customization** — Logo upload, pixel shapes, finder patterns, colors, resolution
- **Style Presets** — Save, load, and manage QR code style presets
- **Export Options** — Download PNG, copy to clipboard, save to Nextcloud Files
- **History** — Automatic history of generated QR codes with search and re-generate
- **Multi-language** — English, French, German, Spanish, Italian, Dutch

## Requirements

- Nextcloud 28+
- PHP 8.1+
- Node.js 20+ (for development)

## Installation

1. Clone this repository into your Nextcloud `apps/` directory:
   ```bash
   git clone https://github.com/qtl/epc-qrcode-generator.git
   ```

2. Install PHP dependencies:
   ```bash
   composer install --no-dev
   ```

3. Install JS dependencies and build:
   ```bash
   npm ci
   npm run build
   ```

4. Enable the app in Nextcloud:
   ```bash
   occ app:enable epc_qrcode_generator
   ```

## Development Setup

```bash
# Install dependencies
make dev-setup

# Build JS in watch mode
make watch-js

# Production build
make build-js
```

## License

AGPL-3.0-or-later

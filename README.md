# Tourivo – Travel, Tour & Hotel Booking Engine for WordPress

[![Version](https://img.shields.io/badge/version-1.3.0-blue.svg)](https://tourivo.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-21759B.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Tourivo** is a next-generation, high-performance travel management and booking engine for WordPress. Built with a dual-engine architecture, Tourivo seamlessly powers tour operators, travel agencies, boutique hotels, resorts, and vacation rentals within a unified platform.

Unlike traditional monolithic booking plugins, Tourivo is designed from the ground up with a **Zero-jQuery lightweight reactive frontend**, **modern PSR-4 modular architecture**, and **dedicated relational database tables** featuring MySQL row-level transaction locks (`SELECT ... FOR UPDATE`) to prevent double-booking.

---

## 🌟 Key Features

- **Dual-Engine Architecture**: Manage Tour Packages (Single-day, Multi-day, Hourly, Fixed departure) and Accommodations (Hotels & Room Hierarchy) in one plugin.
- **Concurrency-Safe Inventory Engine**: Custom relational tables (`wp_tourivo_inventories`) with row-level locks and transaction rollbacks to prevent race conditions and overbooking.
- **Reactive Frontend Booking Panel**: Instant price calculation, date range picker, and guest breakdown (+ / - counters) built with pure vanilla JavaScript (Zero jQuery).
- **Day-by-Day Itinerary Builder**: Dynamic repeater for structuring multi-day trip itineraries with meal plans and activity highlights.
- **Inclusions & Exclusions Checklist**: Visual feature checklist for tour packages.
- **Hotel & Room Hierarchy**: Link room types to parent hotels with individual pricing, bed configurations, and capacity limits.
- **Anti-Spoofing Client IP Resolution**: Secure Cloudflare Edge verification and trusted reverse proxy CIDR/IP chain evaluation.
- **Standalone Offline Checkout**: Support for Cash on Delivery, Bank Transfer, and offline payment instructions without requiring third-party dependencies.
- **Automated HTML Email Confirmations**: Clean, responsive booking confirmation emails sent to customers and administrators.
- **Gutenberg Blocks & Elementor Widgets**: Core server-rendered Gutenberg blocks and native Elementor widgets for tour grids, hotel listings, search bars, and booking panels.
- **Developer-Friendly Template Overrides**: Standard template hierarchy overrideable via `your-theme/tourivo/`.

---

## 📂 Architecture Overview

Tourivo follows clean modern PHP best practices:

```
tourivo/
├── app/
│   ├── Admin/              # Admin menus, meta boxes, settings page, and booking tables
│   ├── Cli/                # WP-CLI commands (status, stats, cleanup, demo management)
│   ├── Common/             # Abstract base classes, contracts, traits, and exceptions
│   ├── Config/             # Configuration management & environment settings
│   ├── Controllers/        # Request controllers
│   │   └── Api/            # REST API controllers & routes (Availability, Checkout, Search)
│   ├── Database/           # Schema migration runner, tables, and demo seeders
│   ├── Integrations/       # Elementor and Gutenberg blocks integrations
│   ├── Models/             # Domain models (Tour, Hotel, Room, Booking, Inventory, Inquiry)
│   ├── Providers/          # Service providers (Admin, API, Database, Routing, Shortcodes)
│   ├── Repositories/       # Database query repositories with atomic locking
│   ├── Services/           # Business logic (Booking, Availability, Inventory, Email, SEO, Webhook)
│   ├── Shortcodes/         # Frontend shortcode handlers & lookup widgets
│   └── Support/            # Utilities (ClientIp anti-spoofing, Money format, Date helpers)
├── assets/                 # Minified frontend & admin CSS / JS
├── bin/                    # Release packaging and version bump scripts
├── languages/              # Translation files (.pot, .po, .mo)
├── templates/              # Public frontend theme templates
├── views/                  # Admin dashboard & settings views
├── tests/                  # Automated unit and integration test suites
│   ├── Unit/               # Isolated unit tests (Money, ClientIp, DateValidation)
│   └── Integration/        # Integration tests (BookingEngine, Transitions, Security, Webhooks)
├── tourivo.php             # Main plugin bootstrap file
├── readme.txt              # Official WordPress.org plugin directory readme
└── uninstall.php           # Cleanup routine on deletion
```

---

## 🚀 Installation

### Minimum Requirements
- **PHP**: 8.0 or higher (PHP 8.1, 8.2, 8.3+ compatible)
- **WordPress**: 6.0 or higher
- **MySQL**: 5.7+ / MariaDB 10.3+

### Manual Installation
1. Clone the repository into your WordPress plugins directory:
   ```bash
   git clone https://github.com/itsmanzur/tourivo.git wp-content/plugins/tourivo
   ```
2. Activate the plugin via **WordPress Admin -> Plugins**.
3. Navigate to **Tourivo -> Dashboard** to import sample data or start adding your tours and hotels.

---

## ⚡ Shortcodes

Embed Tourivo components anywhere on your website:

| Shortcode | Description | Example |
|---|---|---|
| `[tourivo_tours]` | Responsive grid of tour packages | `[tourivo_tours columns="3" count="6" destination="bali"]` |
| `[tourivo_hotels]` | Responsive grid of hotels & resorts | `[tourivo_hotels columns="3" count="6" destination="maldives"]` |
| `[tourivo_search_bar]` | Destination, date, and traveler search bar | `[tourivo_search_bar]` |
| `[tourivo_booking_panel]` | Interactive booking sidebar for an item | `[tourivo_booking_panel item_id="123" item_type="tour"]` |
| `[tourivo_booking_lookup]` | Guest booking lookup & voucher generator | `[tourivo_booking_lookup]` |

---

## 🧪 Automated Testing & Quality Infrastructure

Tourivo maintains a comprehensive testing and static analysis pipeline:

### Running Tests Locally
```bash
# Run the complete test suite (Unit & Integration)
npm test
# or directly via PHP runner:
php tests/run_all_tests.php
```

### Static Analysis (PHPStan)
Tourivo enforces PHPStan Level 5 static analysis:
```bash
composer analyse
# or
vendor/bin/phpstan analyse
```
*Roadmap*: Moving toward PHPStan Level 6-8 in upcoming releases with generic template annotations on all repositories.

### Code Style & Standards (PHPCS)
```bash
# Check code against WordPress & PHPCompatibility standards
composer lint

# Automatically fix formatting issues
composer lint:fix
```

### Dockerized WordPress Environment (`@wordpress/env`)
```bash
# Start local WordPress test environment
npm run env:start

# Run PHPUnit tests inside the docker container
npm run test:php

# Stop local environment
npm run env:stop
```

---

## 📄 License

Tourivo is open-source software licensed under the [GPLv2 or later](LICENSE).

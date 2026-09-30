# Tourivo – Travel, Tour & Hotel Booking Engine for WordPress

[![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://tourivo.com)
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
│   ├── Api/                # REST API controllers & routes (Availability, Checkout, Search)
│   ├── Common/             # Abstract base classes, contracts, traits, and exceptions
│   ├── Config/             # Configuration management
│   ├── Database/           # Schema migration runner and table migrations
│   ├── Integrations/       # Elementor and Gutenberg blocks integrations
│   ├── Models/             # Domain models (Tour, Hotel, Room, Booking, Inventory, Inquiry)
│   ├── Providers/          # Service providers (Admin, API, Database, Routing, Shortcodes)
│   ├── Services/           # Business logic (Booking, Availability, Inventory, Email, Seeder)
│   └── Support/            # Utilities (ClientIp anti-spoofing, Date helpers, Formatting)
├── assets/                 # Minified frontend & admin CSS / JS
├── languages/              # Translation files & POT template
├── templates/              # Public frontend theme templates
├── views/                  # Admin dashboard & settings views
├── tests/                  # Automated unit test suite
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

---

## 🧪 Testing

Run the automated test suite locally:

```bash
php tests/test_client_ip.php
```

---

## 📄 License

Tourivo is open-source software licensed under the [GPLv2 or later](LICENSE).

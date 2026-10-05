=== Tourivo ===
Contributors: tourivo
Tags: travel, tour booking, hotel booking, booking engine, accommodation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Next-Gen Travel, Tour & Accommodation Booking Engine for WordPress. Dual-engine architecture for tours and hotel stays.

== Description ==

**Tourivo** is an all-in-one, modern travel management and booking plugin designed for tour operators, travel agencies, boutique hotels, resorts, and vacation rentals.

Unlike traditional heavy booking plugins, Tourivo is built with a **Zero-jQuery lightweight reactive frontend**, **modern PSR-4 architecture**, and **relational database schema** designed to handle high-traffic bookings without slowing down your website.

### 🌟 Key Features

* **Dual Engine in One Plugin:** Seamlessly manage Tour Packages (Single-day, Multi-day, Hourly, Fixed departure) and Hotels/Accommodations units in the same system.
* **Smart Inventory & Anti-Double-Booking:** Built-in concurrency-safe capacity engine with database transactions and row-level locking.
* **Interactive Frontend Booking Panel:** Real-time price breakdown, date range selector, and guest (+ / -) counters with instant calculations.
* **Day-by-Day Itinerary Builder:** Dynamic repeater for structuring multi-day trip itineraries with included meals and activity highlights.
* **Inclusions & Exclusions Manager:** Visual checklist of included and excluded items for each tour.
* **Hotel & Room Hierarchy:** Link multiple room units to parent hotels with individual pricing, bed configurations, and capacity limits.
* **Direct Offline Checkout:** Allow travelers to book instantly with cash on delivery, bank transfer, or custom payment instructions.
* **Automated Email Notifications:** Professional responsive HTML booking confirmations sent to both customers and administrators.
* **Gutenberg & Elementor Integration:** Server-rendered Gutenberg Core Blocks and dedicated Elementor Widgets for Tour Grids, Hotel Grids, Search Bars, and Booking Panels.
* **Theme Developer Friendly:** Override any single tour, single hotel, or booking panel template in your theme's `/tourivo/` directory.

---

### 🚀 Tourivo Pro (Available Extension)
Upgrade to **Tourivo Pro** for advanced enterprise features:
* **Tour + Hotel Combo Bundles:** Upsell hotel rooms directly inside tour checkouts with bundle discounts.
* **Full WooCommerce Bridge:** Connect 100+ WooCommerce payment gateways, deposits, and partial payments.
* **2-Way iCal Calendar Sync:** Sync bookings automatically with Airbnb, Booking.com, VRBO, and Google Calendar.
* **Dynamic Pricing Engine:** Set seasonal rates, weekend hiking, early bird and last-minute discount rules.
* **QR-Code Tickets & PDF Invoices:** Auto-generate downloadable PDF vouchers and mobile-scannable QR tickets.
* **WhatsApp & SMS Notifications:** Instant booking updates via WhatsApp Cloud API and Twilio.

---

== Shortcodes ==

Tourivo comes with built-in shortcodes to embed anywhere in your posts or pages:

* `[tourivo_tours columns="3" count="6" destination="bali"]` – Display tour packages in a responsive grid.
* `[tourivo_hotels columns="3" count="6" destination="maldives"]` – Display hotels & resorts in a grid.
* `[tourivo_search_bar]` – Embed the destination, date, and traveler filter search bar.
* `[tourivo_booking_panel item_id="123" item_type="tour"]` – Embed the interactive booking panel for a specific item.

---

== Installation ==

1. Upload the `tourivo` folder to the `/wp-content/plugins/` directory, or install directly via the WordPress Plugins menu.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Tourivo -> Dashboard** to import sample data or start adding your Tours and Hotels.
4. Configure your preferred currency and settings in **Tourivo -> Settings**.

---

== Frequently Asked Questions ==

= Does Tourivo require WooCommerce to work? =
No! The free version of Tourivo includes its own standalone direct booking and checkout engine (supporting offline payments, bank transfers, and cash on delivery). Tourivo Pro provides an optional WooCommerce bridge for users who want to use WooCommerce gateways.

= Can I customize the single tour and single hotel layouts? =
Yes. Tourivo uses a clean template hierarchy. Simply copy `templates/single-tour.php` or `templates/single-hotel.php` to `your-theme/tourivo/` to customize the layout.

= What is the minimum PHP version required? =
Tourivo requires PHP 8.0 or higher (fully compatible with PHP 8.1, 8.2, and 8.3+).

= How does the anti-double-booking system work? =
Tourivo uses custom relational database tables (`wp_tourivo_inventories`) with MySQL row-level transaction locks (`SELECT ... FOR UPDATE`), ensuring that concurrent users cannot book the same seat or room simultaneously.

---

== External Services ==

Tourivo contains an optional Outbound Webhook Integration feature that connects to user-configured external endpoints (e.g. Zapier, Make.com, n8n, custom CRMs, Slack).

* **What data is sent:** Traveler full name, email address, phone number, booked tour or room item details (title, dates, guest counts), booking reference code, payment status, financial totals, and inquiry message content.
* **Where data is sent:** Exclusively to the HTTPS endpoint URL configured by the site administrator in Tourivo Settings.
* **When data is sent:** Automatically when a new booking is created (`booking.created`), when a booking status changes (`booking.status_changed`), or when a traveler inquiry is submitted (`inquiry.created`), as well as manually when clicking "Send Test Ping Payload" in settings.
* **Privacy & Control:** This service is completely **optional and disabled by default**. No data is transmitted to external endpoints unless a webhook URL is explicitly configured and enabled in plugin settings.

---

== Screenshots ==

1. Tourivo Admin Dashboard with Live Booking KPIs and Quick Actions.
2. Tabbed Tour Settings Editor with Day-by-Day Itinerary Builder.
3. Single Tour Page with Sticky Interactive Booking Sidebar.
4. Hotel & Room Listings with Individual Nightly Rates.
5. Search Bar and Responsive Tour Grid.

---

== Changelog ==

= 1.2.0 =
* Hardened ClientIp anti-spoofing logic for Cloudflare edge verification and reverse proxies.
* Added custom trusted proxy CIDR/IP settings and `tourivo/cloudflare_ranges` filter.
* Enhanced booking status state transitions, inventory rollback, and manual booking handling.
* Automatic database migration hooks on plugin upgrades (`upgrader_process_complete`).
* Improved responsive admin dashboard UI, copy buttons, and frontend accessibility.

= 1.0.0 =
* Initial public release.
* Core architecture with PSR-4 autoloading and PSR-11 Dependency Injection container.
* Custom database migration engine (`bookings`, `booking_items`, `inventories`, `logs`).
* Custom Post Types: `tourivo_tour`, `tourivo_hotel`, `tourivo_room`.
* Taxonomies: `tourivo_destination`, `tourivo_activity_type`, `tourivo_amenity`, `tourivo_tour_feature`.
* Concurrency-safe Inventory & Availability Engine with row-locking.
* REST API endpoints for real-time checks and direct checkout.
* Zero-jQuery Reactive Booking Panel.
* Gutenberg Core Blocks and Elementor Widgets integration.
* 1-Click Sample Travel & Accommodation Data Seeder.

== Upgrade Notice ==

= 1.2.0 =
Security hardening for client IP resolution and booking inventory state management.

= 1.0.0 =
Initial release of Tourivo.

== Credits ==

* **Hind Siliguri Font** by Indian Type Foundry (https://github.com/itfoundry/hind-siliguri), licensed under SIL Open Font License 1.1 (assets/fonts/OFL.txt).


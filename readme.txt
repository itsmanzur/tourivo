=== Tourivo ===
Contributors: itsmanzur, readscope
Tags: travel, tour booking, hotel booking, booking engine, accommodation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
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

### 🏠 Optional Travel Homepage

Tourivo never changes your homepage, footer or menus on its own. If you want a ready-made travel storefront, turn on **Travel Homepage Layout** in the Setup Wizard or under **Tourivo -> Settings**: it adds the search bar, destinations, open tours and hotels to the homepage and fills empty theme placeholder menus with booking links.

### 🚀 Tourivo Pro (Separate Paid Add-on)
Everything above is free and fully functional. **Tourivo Pro** is a separate plugin that adds:
* **WooCommerce Payment Bridge:** Take payment through any WooCommerce payment gateway; the booking is confirmed when the order is paid.
* **Paid Extras & Add-ons:** Sell extras such as airport transfers or extra beds, priced per booking, per night, per person or per person per night.
* **Child & Infant Rates, Group and Seasonal Pricing:** Separate traveler rates, group discounts and seasonal or weekend price rules.
* **Promo Codes & Custom Checkout Fields:** Coupons with usage limits and expiry, plus extra fields on the booking form.
* **PDF Invoices, Tickets & QR Check-in:** Downloadable PDF documents with a QR code staff can scan to check guests in.
* **iCal Room Sync:** Import and export room calendars with Airbnb, Booking.com, VRBO and Google Calendar.
* **Customer Dashboard:** A traveler portal for trips, tickets and cancellation requests.

= Shortcodes =

Tourivo comes with built-in shortcodes to embed anywhere in your posts or pages:

* `[tourivo_tours columns="3" count="6" destination="bali"]` – Display tour packages in a responsive grid.
* `[tourivo_hotels columns="3" count="6" destination="maldives"]` – Display hotels & resorts in a grid.
* `[tourivo_search_bar]` – Embed the destination, date, and traveler filter search bar.
* `[tourivo_filter_search type="tour"]` – Search page with live destination, price and rating filters.
* `[tourivo_booking_panel item_id="123" item_type="tour"]` – Embed the interactive booking panel for a specific item.
* `[tourivo_booking_lookup]` – Let travelers look up a booking by code and email and print the voucher.
* `[tourivo_thank_you]` – Booking confirmation page.
* `[tourivo_my_bookings per_page="10" show_stats="yes"]` – Customer account portal for travelers to manage bookings, vouchers, and cancellations.
* `[tourivo_wishlist]` – Traveler wishlist of saved tours and hotels.
* `[tourivo_currency_switcher]` – Let visitors view prices in another currency.

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

= Will Tourivo change my homepage or footer? =
No. Tourivo only adds its own pages, blocks and shortcodes. The optional **Travel Homepage Layout** setting (off by default) is the only thing that changes the homepage, footer tagline, theme credit and empty placeholder menus, and it can be switched off again at any time.

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
* **Local & Intranet Webhook Endpoints:** Tourivo uses `wp_safe_remote_post()` which protects against SSRF attacks by blocking local IP ranges. If you are developing locally or testing with a local n8n/webhook server (e.g., `http://127.0.0.1:5678`), allow local requests via WordPress's native filter:
  `add_filter('http_request_host_is_external', function($is_external, $host) { return $host === 'localhost' || $host === '127.0.0.1' ? true : $is_external; }, 10, 2);`

---

== Privacy & GDPR Compliance ==

Tourivo is engineered from the ground up for strict privacy regulations (GDPR, CCPA, and statutory accounting rules):

* **User Consent (Opt-in):** Optional explicit consent checkboxes on frontend booking panels and inquiry forms with automated versioning (SHA-256 hash) and timestamp audit tracking (`consent_at`, `consent_version`).
* **WordPress Privacy Tools Integration:**
  * **Personal Data Exporter:** Integrated with WordPress Core Export Personal Data tool with 50-item batched pagination for customer bookings and inquiries.
  * **Personal Data Eraser:** Integrated with WordPress Core Erase Personal Data tool. Anonymizes customer identifiers (`customer_name`, `customer_email`, `customer_phone`, `billing_address`, `customer_notes`, `ip_address`) while legally retaining financial transaction lines for statutory tax accounting obligations. Deletes inquiries and traveler wishlist metadata.
* **Data Minimization & IP Collection Control:** Configurable `store_ip` setting allowing operators to disable IP address storage entirely. Endpoint rate limits continue to operate using temporary, hashed transients without persisting raw IP addresses.
* **Scheduled Data Retention:** A daily job (`tourivo_daily_privacy_retention`, off until you set a period) anonymizes bookings a set number of days after the trip has ended, and deletes inquiries older than a set number of months. You choose which booking statuses are anonymized (completed and cancelled by default; pending, on hold and confirmed can be added) under **Tourivo -> Settings**. Each run handles at most 200 bookings and 200 inquiries.

---

== Screenshots ==

1. Tourivo Admin Dashboard with Live Booking KPIs and Quick Actions.
2. Tabbed Tour Settings Editor with Day-by-Day Itinerary Builder.
3. Single Tour Page with Sticky Interactive Booking Sidebar.
4. Hotel & Room Listings with Individual Nightly Rates.
5. Search Bar and Responsive Tour Grid.

---

== Changelog ==

= 1.0.0 =
* Initial public release.
* Tours and hotels in one plugin: custom post types, destinations, activities, amenities, day-by-day itineraries, room types with their own prices and capacity.
* Concurrency-safe availability engine with row locking, so two travelers cannot book the last seat or room.
* Booking panel without jQuery: live price breakdown, date range and traveler counters, open-date search across all listings.
* Offline checkout (bank transfer, cash on arrival, custom instructions), customer and admin emails, booking lookup with printable voucher, My Bookings portal with self-cancellation.
* Admin: bookings list with filters and CSV export, inquiries, reports, setup wizard, sample data importer.
* Gutenberg blocks, Elementor widgets and shortcodes for tours, hotels, search, booking panel, wishlist and currency switcher.
* Privacy: consent checkbox with audit trail, WordPress personal data export and erase, optional IP storage, scheduled anonymization and inquiry purge.
* Optional outbound webhooks, optional travel homepage layout (off by default).
* Developer hooks under `tourivo/*` (for example `tourivo/booking/created`, `tourivo/booking/status_changed`, `tourivo/inquiry/created`).
* Bundled Anek Bangla font for Bengali and Latin text.

== Upgrade Notice ==

= 1.0.0 =
Initial release of Tourivo.

== Credits ==

Tourivo is developed and maintained by itsmanzur (The Readscope).

* Source code: https://github.com/itsmanzur/tourivo
* Website: https://tourivo.thereadscope.com/

Third-party resources bundled with the plugin:

* **Anek Bangla Font** by The Anek Project Authors / Ek Type (https://github.com/EkType/Anek), licensed under SIL Open Font License 1.1 (assets/fonts/OFL.txt). Served from your own site; nothing is loaded from Google Fonts or any other CDN.

No other third-party libraries are bundled: the booking panel, calendar and photo lightbox are written for Tourivo, and admin icons come from the Dashicons set that ships with WordPress.

=== Tourivo ===
Contributors: itsmanzur
Tags: travel, tour booking, hotel booking, booking engine, accommodation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.5.0
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
* **Scheduled Data Retention:** Built-in automated retention crons (`tourivo_daily_privacy_retention`) to automatically anonymize completed bookings after *N* months and purge old inquiries after *N* months.

---

== Screenshots ==

1. Tourivo Admin Dashboard with Live Booking KPIs and Quick Actions.
2. Tabbed Tour Settings Editor with Day-by-Day Itinerary Builder.
3. Single Tour Page with Sticky Interactive Booking Sidebar.
4. Hotel & Room Listings with Individual Nightly Rates.
5. Search Bar and Responsive Tour Grid.

---

== Changelog ==

= 1.5.0 =
* **Open Dates & Availability Search:** Travelers can pick any open date; a month calendar shows how many tours and hotels are open each day, and totals are recalculated on the server.
* **Search Across Listings:** The destination, date and traveler search now carries through tour and hotel archives, destination pages and single listings, with related tours and hotels in the same destination.
* **Optional Travel Homepage Layout:** New setting (off by default, also offered in the Setup Wizard) that turns the homepage and footer into a travel storefront. Without it Tourivo leaves the homepage, footer and menus untouched.
* **Itemised Price Breakdown:** Price changes made by add-ons (seasonal rates, group discounts, paid extras) now appear as their own named lines in the booking panel, and the lines always add up to the subtotal.
* **Developer:** New `tourivo/public_payment_methods` filter lets add-ons that collect payment themselves (such as a WooCommerce checkout) register their own method for public bookings, and `tourivo/travel_homepage` filter overrides the homepage layout setting. `tourivo/booking_price` filters can return an `adjustments` list (title and amount, negative for discounts) to itemise their changes.
* **Translations:** Built-in email subjects and headings are now in the translation template; translator comments added for every placeholder string.
* **Fix:** Report date ranges and CSV export file names use explicit UTC dates.
* **Typography:** Tourivo now uses your theme's font by default, so booking panels and listings look like part of your site, and shows Bengali text in Anek Bangla on every site (the font downloads only on pages with Bengali). New Font setting under Appearance: theme font, Anek Bangla everywhere, or the device font.
* **Typography:** One type scale (7 sizes instead of 20, nothing below 12px, sizes follow the visitor's browser text size), calmer weights, more line height for Bengali, aligned numbers in prices and calendars, and 16px form fields on phones so they no longer zoom the page.
* **Typography:** Hind Siliguri replaced by Anek Bangla (one variable file for all weights); Tourivo no longer overrides the WordPress admin font with `!important`.

= 1.4.3 =
* **Offline Payment Method Expansion (Bank Transfer & Cash on Arrival):** Added dedicated offline payment method configuration allowing tour operators and hoteliers to accept direct bank deposits and cash on arrival.
* **Structured Bank Transfer Details:** Configurable bank fields including Bank Name, Account Name, Account Number, IBAN, SWIFT/Routing Code, Branch Name, and custom payment instructions rendered automatically on the booking confirmation (Thank You) page and customer emails.
* **Customer Transaction Reference Submission:** Interactive AJAX-powered reference submission form on the Thank You page and reservation lookup, allowing travelers to submit wire/deposit transaction IDs and verification notes.
* **Admin Payment Recording & Ledger Suite:** Added interactive payment recording modal in the Bookings Table allowing administrators to log received amounts, transaction IDs, payment dates, update payment status (`paid`, `partially_paid`, `refunded`, `pending`), track paid vs outstanding due balances, auto-confirm reservations, and append audit logs.
* **Enhanced Frontend Booking Flow:** Added payment method radio selectors directly into the interactive booking panel with instant offline checkout support.

= 1.4.2 =
* **Admin Analytics, Sales & Occupancy Reports Hub:** Added dedicated Reports & Analytics dashboard (`tourivo-reports`) providing comprehensive business monitoring for site administrators and tour operators.
* **Dynamic Date-Range Filtering:** Real-time metrics filtering with quick presets (Today, Last 7 Days, This Month, Last Month, This Year) and custom calendar date picker.
* **KPI Financial Summary Cards:** Instant visibility into Total Bookings, Gross Revenue, Net Revenue, Tax Collected, Discounts, Outstanding Due, Total Travelers, and Average Order Value (AOV).
* **Occupancy Rate Engine:** Integrated occupancy rate calculation from scheduled tour dates and hotel room inventory capacities with visual progress meters.
* **Visual Performance Timeline & Rankings:** Lightweight responsive CSS/SVG sales and booking timeline charts, along with ranked leaderboards for top-performing tour packages and hotel stays.
* **1-Click Accounting & Tax Filing CSV Export:** Instant download of clean accounting spreadsheets formatted for statutory bookkeeping and tax filing with strict CSV formula injection mitigation and UTF-8 BOM encoding for Excel.

= 1.4.1 =
* **Advanced Booking Export & Reporting Suite:** Added an interactive, modal-driven export system supporting CSV / Excel downloads directly from the Admin Bookings Table and Dashboard.
* **Flexible Filtering:** Filter exports by Booking Status (including "Cancel Requested"), Payment Status, Date Criteria (Booking Creation Date vs. Travel Check-in Date), Specific Item ID, Item Type (Tour vs. Hotel Room), or Customer Search keywords.
* **Date Presets & Live Statistics Preview:** Quick date presets (Today, Last 7 Days, This Month, Last Month, All Time) with dynamic debounced AJAX preview calculation displaying matched bookings, projected revenue, and traveler counts prior to downloading.
* **Multiple Export Formats:** Standard Bookings List, Itemized Line-Item Manifest (breakdown per guest/room), and Aggregated Financial Summary Report.
* **Security & International Spreadsheet Compatibility:** Comprehensive protection against CSV Formula Injection (escaping `=`, `+`, `-`, `@`, `\t`, `\r` prefixes), full UTF-8 BOM encoding for seamless Bengali & Unicode character display in Microsoft Excel.

= 1.4.0 =
* **Customer Account & "My Bookings" Self-Service Portal:** Added `[tourivo_my_bookings]` shortcode and Gutenberg block enabling travelers to view all past and upcoming trips, filter by booking status, inspect itemized pricing breakdowns, download HMAC-secured vouchers, add trips to calendar (.ics), and cancel bookings within permitted cancellation windows.
* **Traveler Data Isolation & Security:** Strict customer ID and email ownership checks preventing cross-traveler data access, with seamless guest login/register prompt and direct track-booking fallback.
* **Booking Setup Wizard Integration:** Added optional one-click creation of the "My Bookings" page during initial plugin setup.
* **Admin Settings & Documentation:** Added dedicated page selector for My Bookings in Admin Settings and comprehensive shortcode documentation.

= 1.3.0 =
* **Pricing Engine & Date Overrides:** Centralized pricing calculations with date-specific overrides, child rates, infant options, and flexible tax configurations.
* **Complete Email Notification Suite:** Multi-template responsive HTML emails with background queues, client deduplication, plain-text fallback, and admin test controls.
* **Post-Booking Experience & Thank-You Page:** Dedicated `[tourivo_thank_you]` page, HMAC token security without exposing PII, and downloadable ICS calendar invites.
* **Privacy & GDPR Compliance:** Explicit user consent checkboxes with version hash tracking, WordPress Core Personal Data Exporter & Eraser integration, and automated retention cleanup.
* **Inventory Hold & Concurrency Hardening:** Real-MySQL row-level locking, automated temporary hold expiration, and rate-limiting anti-abuse protections.
* **Asynchronous Webhook Delivery:** Webhooks queue asynchronously via Action Scheduler or WP-Cron with exponential backoff retries and SHA256 HMAC signature verification.
* **Atomic Booking Status Transitions:** Unified `BookingService::changeStatus()` with atomic conditional SQL updates and concurrency-safe inventory rollback across Admin AJAX and WP-CLI.
* **Dynamic Schema & SEO Availability:** Real-time 90-day inventory calculation for Schema.org (`InStock` / `SoldOut`) with transient caching and Yoast/Rank Math compatibility.
* **Lookup & Voucher URL Fixes:** Clean raw URL parsing and safe client-side textContent rendering for booking lookup and vouchers.
* **Seeder Robustness & Rollback:** Demo seeder supports clean rollbacks on insertion failure and smart metadata backfilling.
* **Bengali Localization Support:** Comprehensive Bengali translation pack with self-hosted Hind Siliguri typography and WP.org translation precedence.

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

= 1.5.0 =
Open-date availability search across all listings. The homepage, footer and menu changes are now an opt-in setting: if you relied on them, turn on Travel Homepage Layout under Tourivo -> Settings after updating.

= 1.4.3 =
Introduces the Offline Payment Expansion suite (Structured Bank Transfer & Cash on Arrival), customer payment reference submission, and Admin payment recording tool.

= 1.4.2 =
Introduces the Admin Analytics, Sales & Occupancy Reports Hub (`tourivo-reports`), timeline charts, and 1-click Accounting & Tax Filing CSV export.

= 1.4.1 =
Introduces the Advanced Booking Export & Reporting suite with multi-format CSV/Excel reports, formula injection security, and live preview statistics.

= 1.4.0 =
Introduces the Customer Account self-service portal (`[tourivo_my_bookings]`), live trip stats, line item details, and self-cancellation management.

= 1.3.0 =
Important update with async webhook queueing, atomic booking transitions, and enhanced internationalization.

= 1.2.0 =
Security hardening for client IP resolution and booking inventory state management.

= 1.0.0 =
Initial release of Tourivo.

== Credits ==

* **Anek Bangla Font** by The Anek Project Authors / Ek Type (https://github.com/EkType/Anek), licensed under SIL Open Font License 1.1 (assets/fonts/OFL.txt).


# Tourivo & Tourivo Pro – Complete Product, Technical & Architectural Master Roadmap (v2.0)

## ১. এক্সিকিউটিভ সামারি ও প্রোডাক্ট ভিশন

* **প্রোডাক্টের নাম:** **Tourivo** (কোর ফ্রি প্লাগিন - WordPress.org) এবং **Tourivo Pro** (অ্যাডভান্সড পেইড এক্সটেনশন)
* **স্লোগান:** The Next-Gen Travel, Tour & Accommodation Booking Engine for WordPress.
* **মূল উদ্দেশ্য:** ওয়ার্ডপ্রেস ইকোসিস্টেমে Tourfic, WP Travel Engine, MotoPress বা YITH Booking-এর সীমাবদ্ধতা অতিক্রম করে একটি আধুনিক, দ্রুতগতির (Lightweight & Reactive), মডুলার ট্রাভেল ও হোটেল বুকিং ইকোসিস্টেম তৈরি করা।
* **কোর ডিফারেনশিয়েটর (Unique Selling Points):**
  1. **Dual Engine in One Core:** ট্যুর প্যাকেজ (Single/Multi-day/Time-slot) এবং হোটেল/রিসোর্ট রুম বুকিং উভয়ই একই প্লাগিনে সম্বলিত।
  2. **Smart Combo Bundling:** ট্যুর বুকিংয়ের সময় হোটেল রুম বা অ্যাকোমোডেশন বান্ডেল অ্যাড-অন করার সুযোগ (Travel + Stay)।
  3. **Modern Headless/Reactive Ready:** সম্পূর্ণ REST API ফার্স্ট আর্কিটেকচার, নো-রিফ্রেশ রিঅ্যাক্টিভ ফ্রন্টএন্ড ক্যালেন্ডার ও বুকিং উইজেট (Vanilla JS/Preact, Zero-jQuery dependency on Frontend)।
  4. **Strict WordPress.org Compliance:** কোর প্লাগিনে কোনো লুকানো প্রো কোড বা ডেড কোড থাকবে না; হুক ও ফিল্টার আর্কিটেকচারের মাধ্যমে প্রো প্লাগিন ডায়নামিকভাবে কার্যকারিতা বৃদ্ধি করবে।
  5. **Concurrency & Race-Condition Safe:** ডেটাবেজ লেভেলে ট্রানজ্যাকশন এবং পেসিমিস্টিক/অপটিমিস্টিক লকিং সিস্টেম, যা একই সিট/রুম ডাবল বুকিং হওয়া সম্পূর্ণভাবে রোধ করে।

---

## ২. সিস্টেম আর্কিটেকচার ও টেকনিক্যাল স্পেসিফিকেশন

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                Tourivo Ecosystem                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│  [ Core: tourivo (Free) ]                           [ Add-on: tourivo-pro ]     │
│  ├─ PSR-4 Autoloading (Tourivo\)                   ├─ Namespace: Tourivo\Pro\   │
│  ├─ Service Container & Provider Pattern           ├─ Hook Decorators & Bridges │
│  ├─ Custom DB Tables & Migrations Engine           ├─ WooCommerce Hybrid Bridge │
│  ├─ Custom Post Types (Tour, Hotel, Room)          ├─ 2-Way iCal Sync Engine    │
│  ├─ REST API Endpoints (/tourivo/v1/)              ├─ Dynamic Pricing Engine    │
│  ├─ Gutenberg Core Blocks & Shortcodes             ├─ QR Ticket & PDF Generator │
│  └─ Native Offline/Direct Booking Engine           └─ Elementor Pro Extensions  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### ২.১ কোডিং স্ট্যান্ডার্ড ও স্ট্যাক
* **পিএইচপি রিকোয়ারমেন্ট:** PHP 8.0+ (PHP 8.1/8.2/8.3 Fully Compatible with Strict Types).
* **স্ট্যান্ডার্ডস:** PSR-4 (Autoloading), PSR-11 (Container), PSR-12 (Coding Style), WordPress Coding Standards (WPCS).
* **সিকিউরিটি পলিসি:** Nonce ভ্যালিডেশন, `current_user_can()` ক্যাপাবিলিটি চেকিং, ডিক্লেয়ার্ড Prepared SQL Queries (`$wpdb->prepare()`), ইনপুট Sanitization ও আউটপুট Escaping।
* **ফ্রন্টএন্ড স্ট্যাক:**
  * ফ্রন্টএন্ড ক্যালেন্ডার ও বুকিং বার: Vanilla JavaScript (ES6 Modules) অথবা Lightweight Preact (কমপ্যাক্ট <30kb বান্ডল)।
  * নো jQuery ডিপেন্ডেন্সি ফ্রন্টএন্ড বুকিং উইজেটে (আল্ট্রা ফাস্ট পেজ লোড ও কোর ওয়েব ভাইটাল ফ্রেন্ডলি)।
  * ডেটপিকার: Flatpickr / Dayjs (লোকালাইজড ও লাইটওয়েট)।
* **অ্যাডমিন প্যানেল স্ট্যাক:** WordPress Native UI + React-based Settings & Live Gantt Calendar View (অ্যাভেইল্যাবিলিটি ও বুকিং ম্যানেজমেন্টের জন্য)।

### ২.২ নেইমিং কনভেনশন
| কম্পোনেন্ট | Core (Free) | Pro Extension |
| :--- | :--- | :--- |
| **Plugin Slug** | `tourivo` | `tourivo-pro` |
| **Main File** | `tourivo.php` | `tourivo-pro.php` |
| **Root Namespace** | `Tourivo\` | `Tourivo\Pro\` |
| **Constants Prefix** | `TOURIVO_` | `TOURIVO_PRO_` |
| **DB Table Prefix** | `{$wpdb->prefix}tourivo_` | `{$wpdb->prefix}tourivo_` |
| **Hook Prefixes** | `tourivo/action/*`, `tourivo/filter/*` | `tourivo_pro/action/*` |
| **REST Namespace** | `tourivo/v1` | `tourivo-pro/v1` |

---

## ৩. ডেটাবেজ আর্কিটেকচার (High Performance & Relational Schema)

ওয়ার্ডপ্রেসের ডিফল্ট `wp_posts` ও `wp_postmeta` টেবিল হাই-ভলিউম সার্চ ও ইনভেন্টরি কুয়েরির জন্য স্লো। তাই Tourivo রিলেশনাল কাস্টম টেবিল স্ট্রাকচার ব্যবহার করবে:

```
┌─────────────────────────┐          ┌──────────────────────────┐
│   wp_tourivo_bookings   │───1:N───►│ wp_tourivo_booking_items │
└─────────────────────────┘          └──────────────────────────┘
             │ 1:N                                │
             ▼                                    ▼
┌─────────────────────────┐          ┌──────────────────────────┐
│   wp_tourivo_payments   │          │ wp_tourivo_inventories   │
└─────────────────────────┘          └──────────────────────────┘
             │                                    ▲
             ▼                                    │
┌─────────────────────────┐                       │
│     wp_tourivo_logs     │                       │
└─────────────────────────┘                       │
                                                  │
┌─────────────────────────┐                       │
│ wp_tourivo_pricing_rules│───────────────────────┘
└─────────────────────────┘
┌─────────────────────────┐
│  wp_tourivo_ical_sync   │
└─────────────────────────┘
```

### ৩.১ টেবিল ডেফিনিশন ও ফিল্ডসমূহ

#### ১. `wp_tourivo_bookings` (সেন্ট্রাল বুকিং রেকর্ড)
* `id` (BIGINT UNSIGNED, Primary Key, Auto Increment)
* `booking_code` (VARCHAR(32), Unique - e.g., `TRV-2026-8941`)
* `customer_id` (BIGINT UNSIGNED, 0 for Guest)
* `customer_name` (VARCHAR(191))
* `customer_email` (VARCHAR(191), Index)
* `customer_phone` (VARCHAR(64))
* `billing_address` (TEXT)
* `total_amount` (DECIMAL(12,2))
* `tax_amount` (DECIMAL(12,2))
* `discount_amount` (DECIMAL(12,2))
* `paid_amount` (DECIMAL(12,2))
* `due_amount` (DECIMAL(12,2))
* `currency` (VARCHAR(10))
* `payment_method` (VARCHAR(50) - e.g., `offline`, `stripe`, `woocommerce`)
* `payment_status` (ENUM: `pending`, `partial`, `paid`, `refunded`, `failed`)
* `booking_status` (ENUM: `pending`, `confirmed`, `completed`, `cancelled`, `on_hold`)
* `customer_notes` (TEXT)
* `admin_notes` (TEXT)
* `ip_address` (VARCHAR(45))
* `created_at`, `updated_at` (DATETIME)

#### ২. `wp_tourivo_booking_items` (বুকিং লাইট আইটেমস - ট্যুর, রুম, অতিরিক্ত সার্ভিস)
* `id` (BIGINT UNSIGNED, Primary Key, Auto Increment)
* `booking_id` (BIGINT UNSIGNED, Index, Foreign Key to bookings)
* `item_id` (BIGINT UNSIGNED - Tour ID or Room ID)
* `item_type` (ENUM: `tour`, `hotel_room`, `extra_service`, `combo`)
* `parent_item_id` (BIGINT UNSIGNED, Nullable - used for extra services attached to a tour/room)
* `item_title` (VARCHAR(255))
* `check_in` (DATETIME)
* `check_out` (DATETIME, Nullable for single-day tours)
* `time_slot` (VARCHAR(50), Nullable - e.g., `09:00-12:00`)
* `adults_count` (INT)
* `children_count` (INT)
* `infants_count` (INT)
* `quantity` (INT, Default 1 - e.g. Number of rooms)
* `unit_price` (DECIMAL(12,2))
* `total_price` (DECIMAL(12,2))
* `pricing_breakdown` (LONGTEXT - JSON storing passenger wise rates, taxes, and applied rules)

#### ৩. `wp_tourivo_inventories` (ক্যাপাসিটি ও অ্যাভেইলেবিলিটি ইঞ্জিন)
* `id` (BIGINT UNSIGNED, Primary Key, Auto Increment)
* `item_id` (BIGINT UNSIGNED, Index)
* `item_type` (ENUM: `tour`, `hotel_room`)
* `event_date` (DATE, Index)
* `time_slot` (VARCHAR(50), Index, Default `all_day`)
* `total_capacity` (INT - total seats or rooms)
* `booked_count` (INT - currently booked count)
* `reserved_count` (INT - temporary hold during ongoing checkout, with TTL expiry)
* `price_override` (DECIMAL(12,2), Nullable)
* `status` (ENUM: `available`, `sold_out`, `blocked`, `closed`)
* *Unique Key:* `(item_id, item_type, event_date, time_slot)`

#### ৪. `wp_tourivo_pricing_rules` (Pro - ডায়নামিক ও সিজনাল প্রাইসিং রুলস)
* `id` (BIGINT UNSIGNED, Primary Key, Auto Increment)
* `item_id` (BIGINT UNSIGNED, Index, 0 for Global Rule)
* `item_type` (VARCHAR(50) - `tour`, `hotel`, `room`, `global`)
* `rule_name` (VARCHAR(191))
* `rule_type` (ENUM: `seasonal`, `weekend`, `early_bird`, `last_minute`, `group_size`, `custom_date_range`)
* `start_date` (DATE, Nullable)
* `end_date` (DATE, Nullable)
* `days_of_week` (VARCHAR(50), Nullable - e.g., `["fri","sat"]`)
* `min_pax` (INT, Nullable)
* `max_pax` (INT, Nullable)
* `adjustment_type` (ENUM: `percentage_increase`, `percentage_discount`, `fixed_increase`, `fixed_discount`, `fixed_price`)
* `adjustment_value` (DECIMAL(12,2))
* `priority` (INT, Default 10)
* `status` (TINYINT(1), Default 1)

#### ৫. `wp_tourivo_ical_sync` (Pro - 2-Way Calendar Synchronization)
* `id` (BIGINT UNSIGNED, Primary Key, Auto Increment)
* `room_id` (BIGINT UNSIGNED, Index)
* `feed_name` (VARCHAR(100) - e.g., `Airbnb Master Suite`, `Booking.com`)
* `feed_type` (ENUM: `import`, `export`)
* `feed_url` (TEXT)
* `sync_frequency` (INT - in seconds, e.g. 1800 for 30m)
* `last_sync_at` (DATETIME, Nullable)
* `last_sync_status` (ENUM: `success`, `failed`, `pending`)
* `last_error_message` (TEXT, Nullable)
* `sync_token` (VARCHAR(64), Unique)

#### ৬. `wp_tourivo_logs` (অডিট ও অ্যাক্টিভিটি ট্রেল)
* `id` (BIGINT UNSIGNED, Primary Key, Auto Increment)
* `booking_id` (BIGINT UNSIGNED, Index)
* `action` (VARCHAR(100) - e.g., `status_changed`, `payment_received`, `ical_synced`, `email_sent`)
* `user_id` (BIGINT UNSIGNED, Default 0)
* `details` (LONGTEXT - JSON)
* `created_at` (DATETIME)

---

## ৪. কাস্টম পোস্ট টাইপ ও ট্যাক্সোনমি আর্কিটেকচার

### ৪.১ পোস্ট টাইপস
1. **`tourivo_tour`** (ট্যুর প্যাকেজ ও অ্যাক্টিভিটি)
   - *সাপোর্টস:* Title, Editor, Thumbnail, Excerpt, Comments (Reviews), Custom Fields.
   - *মেটা ডেটা:*
     - Tour Type: Single Day, Multi-Day, Fixed Departure, Hourly/Time Slot.
     - Duration (e.g., "3 Days 2 Nights" or "4 Hours").
     - Minimum & Maximum Group Size.
     - Pickup / Drop-off Locations & GPS Coordinates.
     - Itinerary (Structured JSON: Day/Time, Title, Description, Meals included, Activity icon).
     - Inclusions & Exclusions list.
     - Highlights & FAQ list.
     - Badge (Bestseller, Featured, 20% Off).
     - PDF Brochure Attachment.
2. **`tourivo_hotel`** (হোটেল / রিসোর্ট / ভিলা লিস্টিং)
   - *সাপোর্টস:* Title, Editor, Thumbnail, Excerpt, Comments.
   - *মেটা ডেটা:*
     - Star Rating (1 to 5 Stars).
     - Full Address, City, Zip, Map Coordinates (Lat/Long).
     - Check-in Time & Check-out Time rules.
     - Contact details (Phone, Email, Website).
     - Hotel Policy, Cancellation Terms, House Rules.
3. **`tourivo_room`** (হোটেল রুম / অ্যাকোমোডেশন ইউনিট)
   - *মেটা ডেটা:*
     - Linked Hotel ID (`parent_hotel_id`).
     - Room Capacity (Max Adults, Max Children, Max Infants, Total Max Guests).
     - Bed Types (e.g., 1 King Bed, 2 Single Beds).
     - Room Size (sqm / sqft).
     - Room Quantity (Total available units of this type).
     - Base Nightly Rate.
     - Extra Bed Price / Extra Child Price.

### ৪.২ ট্যাক্সোনমি
* `tourivo_destination` (Hierarchical: Country > State > City > Area).
* `tourivo_activity_type` (Trekking, Sightseeing, Scuba Diving, Safari, Culinary, etc.).
* `tourivo_amenity` (WiFi, Swimming Pool, Air Conditioning, Free Breakfast, Parking, Spa, Pet Friendly).
* `tourivo_tour_feature` (Instant Confirmation, Free Cancellation, English Guide, Audio Guide).

---

## ৫. ফ্রি বনাম প্রো কম্প্রিহেনসিভ ফিচার ম্যাট্রিক্স

| ফিচার ডোমেইন | Tourivo (ফ্রি ভার্সন - WP.org) | Tourivo Pro (পেইড অ্যাড-অন) |
| :--- | :--- | :--- |
| **১. ট্যুর টাইপ** | সিঙ্গেল-ডে ট্যুর, সাধারণ ডেট পিকার | মাল্টি-ডে, ফিক্সড ডিপার্চার ডেটস, টাইম-স্লট ও রেগুলার রিকারিং ট্যুর |
| **২. প্রাইসিং মডেল** | ফ্ল্যাট বেস প্রাইস (প্রতি ব্যক্তি বা প্রতি গ্রুপ) | অ্যাডাল্ট/চাইল্ড/ইনফ্যান্ট টায়ারড প্রাইসিং, গ্রুপ ডিসকাউন্ট, পার্সন-টাইপ ভ্যারিয়েশন |
| **৩. ডায়নামিক প্রাইসিং** | অনুপলব্ধ | উইকেন্ড রেটস, সিজনাল হাই/লো রেঞ্জ, আর্লি বার্ড ও লাস্ট মিনিট ডিসকাউন্ট রুলস |
| **৪. হোটেল ও রুম** | বেসিক রুম লিস্টিং, ফ্ল্যাট নাইট রেট, ফিক্সড ক্যাপাসিটি | মাল্টি-রুম একক কার্ট বুকিং, এক্সট্রা গেস্ট/বেড ফি, কাস্টম সিজনাল নাইট রেটস |
| **৫. কম্বো বান্ডেল** | অনুপলব্ধ | **Tour + Hotel Bundle Engine** (ট্যুর চেকআউটে সরাসরি রুম অ্যাড-অন ও বান্ডেল ডিসকাউন্ট) |
| **৬. এক্সট্রা সার্ভিস / অ্যাড-অন** | বেসিক স্ট্যাটিক টেক্সট | কাস্টম পেইড অ্যাড-অন (এয়ারপোর্ট পিকআপ, লাঞ্চ প্যাকেজ, ইনস্যুরেন্স, গিয়ার রেন্টাল) |
| **৭. পেজ বিল্ডার ও থিমিং** | ৪টি ডেডিকেটেড গুটেনবার্গ ব্লক, ৩টি এলিমেন্টর বেসিক উইজেট, শর্টকোডস | ফুল এলিমেন্টর প্রো থিম বিল্ডার ট্যাগস, ২০+ অ্যাডভান্সড উইজেটস, কাস্টমাইজযোগ্য স্কিনস |
| **৮. ক্যালেন্ডার ও বুকিং UX** | স্ট্যান্ডার্ড ক্যালেন্ডার ডেট সিলেকশন | লাইভ অ্যাভেইল্যাবিলিটি বার, মান্থলি ভিউ উইথ লো-প্রাইস ইন্ডিকেটর, স্টিকি বুকিং বার |
| **৯. iCal সিঙ্ক্রোনাইজেশন** | অনুপলব্ধ | **2-Way Automatic iCal Sync** (Airbnb, Booking.com, VRBO, Google Calendar) |
| **১০. পেমেন্ট গেটওয়ে** | অফলাইন ক্যাশ, ব্যাংক ট্রান্সফার, কাস্টম পেমেন্ট ইন্সট্রাকশন | **Full WooCommerce Checkout Bridge** (100+ gateways), Native Direct Stripe/PayPal |
| **১১. ডিপোজিট ও পারশিয়াল** | ফুল পেমেন্ট অনলি | ডিপোজিট পেমেন্ট (যেমন ২০% অগ্রিম), সিকিউরিটি ড্যামেজ ডিপোজিট, ব্যালেন্স রিমাইন্ডার |
| **১২. টিকেট ও ইনভয়েস** | বেসিক এইচটিএমএল ইমেইল নোটিফিকেশন | **ডায়নামিক PDF ইনভয়েস**, ইউনিক **QR Code ই-টিকেট** (মোবাইল চেক-ইন রেডি) |
| **১৩. কমিউনিকেশন হাব** | ওয়ার্ডপ্রেস ডিফল্ট ইমেইল | এসএমএস (Twilio/SMS Alert) ও **WhatsApp নোটিফিকেশন** ইন্টিগ্রেশন |
| **১৪. ইউজার ও ভেন্ডর পোর্টাল** | স্ট্যান্ডার্ড WP প্রোফাইল | ডেডিকেটেড কাস্টমার ড্যাশবোর্ড (বুকিং হিস্ট্রি, টিকেট ডাউনলোড, ক্যান্সেলেশন রিকোয়েস্ট) |
| **১৫. অ্যাডমিন টুলস** | বেসিক বুকিং লিস্ট টেবিল | **Gantt & Timeline Availability Calendar**, কুইক বুকিং ক্রিয়েটর (অফলাইন/ফোন বুকিং) |
| **১৬. অ্যানালিটিক্স ও রিপোর্ট** | টোটাল বুকিং কাউন্ট | সেলস রেভিনিউ চার্ট, অকুপেন্সি রেট, মোস্ট পপুলার ডেস্টিনেশন, এক্সপোর্ট CSV/Excel |

---

## ৬. মাস্টার ডেভেলপমেন্ট ফেজ ও মাইলস্টোন প্ল্যান

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        Master Implementation Roadmap (20 Weeks)                        │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ Weeks 01-03: Phase 1 ──► Core Framework, PSR-4 Architecture & Database Engine          │
│ Weeks 04-06: Phase 2 ──► Custom Post Types, Meta Framework & Admin Settings            │
│ Weeks 07-09: Phase 3 ──► Availability Engine, Time Slots & Inventory Logic             │
│ Weeks 10-12: Phase 4 ──► Gutenberg Blocks, Elementor Widgets & Frontend AJAX Engine    │
│ Weeks 13-14: Phase 5 ──► Free Core Polish, Security Audit & WordPress.org Submission   │
│ Weeks 15-17: Phase 6 ──► Pro Add-on: Dynamic Pricing, WooCommerce Bridge & Bundle     │
│ Weeks 18-19: Phase 7 ──► Pro Add-on: iCal 2-Way Sync, QR Tickets & Customer Dashboard  │
│ Week  20   : Phase 8 ──► QA, Compatibility Matrix, Seed Demos & Commercial Launch     │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### ফেজ ১: ফাউন্ডেশন, PSR-4 আর্কিটেকচার ও ডেটাবেজ ইঞ্জিন (সপ্তাহ ১ – ৩)
* **মডিউল স্ট্রাকচার:**
  ```
  tourivo/
  ├── app/
  │   ├── Common/             # Abstract classes, Interfaces, Enums, Traits
  │   ├── Config/             # Constants and Global Configs
  │   ├── Controllers/        # Admin, Frontend and API Controllers
  │   ├── Database/           # Migrations, Schema and Seeder
  │   ├── Models/             # Tour, Hotel, Room, Booking, Inventory models
  │   ├── Repositories/       # Data Access Layer (Clean DB queries)
  │   ├── Services/           # Business Logic (BookingService, InventoryService, PriceService)
  │   └── Providers/          # Service Providers (RouteServiceProvider, BlockServiceProvider)
  ├── assets/                 # Compiled CSS/JS, Images, SVGs
  ├── build/                  # Production Build Files
  ├── languages/              # .pot and Translation Files
  ├── templates/              # Overridable Frontend Templates (Single Tour, Hotel, Room, Card)
  ├── tourivo.php             # Plugin Entry Point
  └── composer.json           # PSR-4 Autoloading configuration
  ```
* **কাজের বিবরণ:**
  1. Composer-ভিত্তিক PSR-4 অটোলোডার এবং লাইটওয়েট ডিপেন্ডেন্সি ইনজেকশন কন্টেইনার স্থাপন।
  2. `dbDelta()` ব্যবহার করে কাস্টম টেবিল তৈরি (`wp_tourivo_bookings`, `wp_tourivo_booking_items`, `wp_tourivo_inventories`, `wp_tourivo_logs`)।
  3. প্লাগিন অ্যাক্টিভেশন, ডিঅ্যাক্টিভেশন এবং আনইন্সটল ডেটা ক্লিনআপ মেকানিজম।
  4. ফিল্টার ও অ্যাকশন হুকের ডকব্লক ও এক্সটেনশন ফ্রেমওয়ার্ক রেডি করা।

### ফেজ ২: কাস্টম পোস্ট টাইপ, মেটাবক্স ও অ্যাডমিন প্যানেল (সপ্তাহ ৪ – ৬)
* **কাজের বিবরণ:**
  1. `tourivo_tour`, `tourivo_hotel`, `tourivo_room` রেজিস্টার করা এবং ট্যাক্সোনমি অ্যাসাইন করা।
  2. মডার্ন অ্যাডমিন মেটাবক্স বিল্ডার (আইটিনেরারি বিল্ডার, ইনক্লুশন/এক্সক্লুশন, হোটেল অ্যামেনিটিস, রুম ক্যাপাসিটি)।
  3. রিলেশনশিপ হ্যান্ডলার: প্রতিটি `tourivo_room` পোস্টকে তার প্যারেন্ট `tourivo_hotel`-এর সাথে ডায়নামিকালি লিঙ্ক করা।
  4. গ্লোবাল সেটিংস প্যানেল (কারেন্সি, ট্যাক্স রেট, ইমেইল নোটিফিকেশন টেমপ্লেট, পারমালিংক স্ট্রাকচার)।

### ফেজ ৩: ইনভেন্টরি, ক্যাপাসিটি ও অ্যাভেইলেবিলিটি ইঞ্জিন (সপ্তাহ ৭ – ৯)
* **কাজের বিবরণ:**
  1. ডেট-ওয়াইজ এবং স্লট-ওয়াইজ ক্যাপাসিটি হ্যান্ডলিং ইঞ্জিন (`InventoryService`)।
  2. রিয়েল-টাইম অ্যাভেইলেবিলিটি চেক REST API এন্ডপয়েন্ট (`/wp-json/tourivo/v1/availability`)।
  3. কনকারেন্সি প্রটেকশন: একই তারিখে একই সিট একাধিক ইউজার বুক করতে গেলে ডেটাবেজ ট্রানজ্যাকশন লক (`SELECT ... FOR UPDATE`) কার্যকর করা।
  4. ফ্রন্টএন্ড ডেটপিকারের জন্য ডিসেবলড ডেটস ও সোল্ড-আউট ডেটস ক্যালকুলেশন লজিক।

### ফেজ ৪: ফ্রন্টএন্ড ইঞ্জিনিয়ারিং (Gutenberg + Elementor + Templates) (সপ্তাহ ১০ – ১২)
* **কাজের বিবরণ:**
  1. **টেমপ্লেট ওভাররাইড ফ্রেমওয়ার্ক:** থিম ডেভেলপারদের জন্য WooCommerce-এর মতো চাইল্ড থিমে `your-theme/tourivo/` ফোল্ডারে টেমপ্লেট ওভাররাইড সাপোর্ট।
  2. **Gutenberg Core Blocks (React/@wordpress/scripts):**
     - `Tourivo Search Bar` (গন্তব্য, তারিখ, গেস্ট কাউন্টার ফিল্টার)।
     - `Tourivo Tour Grid / Carousel`।
     - `Tourivo Hotel & Room Showcase`।
     - `Tourivo Booking Panel` (ইন্টারেক্টিভ বুকিং সাইডবার উইজেট)।
  3. **Elementor Integration:**
     - ৩টি ডেডিকেটেড এলিমেন্টর ফ্রি উইজেট রেজিস্টার করা।
  4. **Lightweight Frontend Booking App:**
     - নো-রিফ্রেশ AJAX বুকিং সামারি ক্যালকুলেটর (লাইভ গেস্ট সংখ্যা বৃদ্ধিতে রিয়েল-টাইম প্রাইজ চেঞ্জ)।
     - ডিরেক্ট / অফলাইন বুকিং সাবমিশন এবং গ্রাহকের কনফার্মেশন পেজ রেন্ডার।

### ফেজ ৫: ফ্রি ভার্সন অডিট ও WordPress.org সাবমিশন (সপ্তাহ ১৩ – ১৪)
* **কাজের বিবরণ:**
  1. WordPress.org Plugin Guidelines ১০০% অডিট (কোনো অবফুসকেটেড কোড বা সরাসরি প্রো ব্যানার স্প্যামিং ছাড়া)।
  2. WPCS (WordPress Coding Standards) ও PHPStan Level 8 স্ট্যাটিক অ্যানালাইসিস পাস করা।
  3. অফিশিয়াল `readme.txt`, ব্যানার (1544x500), আইকন (256x256) এবং স্ক্রিনশট তৈরি।
  4. WordPress.org প্লাগিন রিভিউ টিমের কাছে সাবমিশন এবং অনুমোদন গ্রহণ।

### ফেজ ৬: Tourivo Pro – প্রাইসিং, WooCommerce ও কম্বো বান্ডেল (সপ্তাহ ১৫ – ১৭)
* **কাজের বিবরণ:**
  1. **অ্যাডভান্সড প্রাইসিং ইঞ্জিন:**
     - অ্যাডাল্ট / চাইল্ড / ইনফ্যান্ট টায়ারড প্রাইসিং।
     - সিজনাল রেঞ্জ এবং উইকেন্ড হাইকিং রুলস টেবিল ক্যালকুলেটর।
     - এক্সট্রা সার্ভিসেস / অ্যাড-অন প্রাইসিং ম্যানেজার।
  2. **Tour + Hotel Combo Bundler:**
     - ট্যুর ডিটেইলস পেজে রিলেটেড হোটেল রুম যুক্ত করার ডায়নামিক বান্ডেল উইজেট।
     - কম্বো প্যাকেজে অটোমেটিক ডিসকাউন্ট (যেমন ৫% বা ফিক্সড $50 ডিসকাউন্ট) অ্যাপ্লাই করা।
  3. **WooCommerce Bridge:**
     - Tourivo বুকিং অবজেক্টকে ভার্চুয়াল WooCommerce কার্ট আইটেমে রূপান্তর।
     - WooCommerce-এর সমস্ত গেটওয়ে (Stripe, PayPal, bKash, SSLCommerz, Klarna, Authorize.net) সাপোর্ট।
     - ডিপোজিট ও পারশিয়াল পেমেন্ট কনফিগারেশন।

### ফেজ ৭: Tourivo Pro – iCal Sync, QR Tickets ও ড্যাশবোর্ড (সপ্তাহ ১৮ – ১৯)
* **কাজের বিবরণ:**
  1. **2-Way iCal Engine:**
     - Airbnb, Booking.com, VRBO থেকে `.ics` ক্যালেন্ডার ইনপুট ও এক্সপোর্ট।
     - WP-Cron ব্যাকগ্রাউন্ড শিডিউলার (প্রতি ৩০ মিনিটে অটো সিঙ্ক ও কনফ্লিক্ট লগিং)।
  2. **PDF ইনভয়েস ও QR কোড টিকেট:**
     - ডায়নামিক PDF ভাউচার ও ইনভয়েস জেনারেটর (Dompdf/TCPDF ইন্টিগ্রেশন)।
     - ট্যুর অপারেটরের জন্য মোবাইল স্ক্যানযোগ্য সিকিউর ভ্যালিডেশন QR কোড।
  3. **কাস্টমার পোর্টাল ও নোটিফিকেশন:**
     - ফ্রন্টএন্ড একাউন্ট পেজ (বুকিং হিস্ট্রি, টিকেট রি-ডাউনলোড, বাতিল রিকোয়েস্ট)।
     - WhatsApp (WhatsApp Cloud API / UltraMsg) এবং Twilio SMS অটোমেশন।

### ফেজ ৮: কোয়ালিটি অ্যাসুরেন্স, ডেমো ইমপোর্টার ও লঞ্চ (সপ্তাহ ২০)
* **কাজের বিবরণ:**
  1. **কম্প্যাটিবিলিটি ও পারফরম্যান্স টেস্ট:**
     - Astra, Hello Elementor, GeneratePress, Divi, Kadence থিমের সাথে টেস্ট।
     - ক্যাশিং প্লাগিন (WP Rocket, LiteSpeed Cache) কমপ্যাটিবিলিটি।
  2. **১-ক্লিক ডেমো ইমপোর্টার:**
     - Travel Agency Demo & Boutique Hotel Demo ডেমো ডেটা প্যাক।
  3. **লাইসেন্সিং ও অটো-আপডেট সিস্টেম:**
     - সিকিউর লাইসেন্স ভ্যালিডেশন ও ওয়ান-ক্লিক অটো আপডেট।
  4. **অফিশিয়াল ডকুমেন্টেশন ও ল্যান্ডিং পেজ পাবলিশ।**

---

## ৭. সিকিউরিটি, পারফরম্যান্স ও কনকারেন্সি স্ট্র্যাটেজি

1. **Anti-Double-Booking Protection:**
   - ডেটাবেজ লেভেলে `wp_tourivo_inventories` টেবিলে ইউনিক কনস্ট্রেইন্ট।
   - বুকিং সাবমিশনের সময় `START TRANSACTION` এবং `SELECT ... FOR UPDATE` কুয়েরি এক্সিকিউশন।
2. **Rate Limiting & Anti-Spam:**
   - বুকিং রিকোয়েস্টে Cloudflare Turnstile / Google reCAPTCHA v3 ইন্টিগ্রেশন সাপোর্ট।
   - REST API রিকোয়েস্টে IP-বেজড রেট লিমিটিং।
3. **Database Caching:**
   - পুনরাবৃত্তিমূলক ট্যাক্সোনমি ও ডেস্টিনেশন কুয়েরির জন্য WP Transients API ব্যবহার।
   - মেমোরি ও কুয়েরি অপটিমাইজেশন (প্রতিটি টেবিল কলামে প্রপার ইনডেক্সিং)।
4. **Clean Code & Extensibility:**
   - ডেভেলপারদের জন্য ৫০+ অ্যাকশন ও ফিল্টার হুক।
   - পূর্ণাঙ্গ REST API ডকুমেন্টেশন।

---

## ৮. রিকমেন্ডেড পরবর্তী অ্যাকশন প্ল্যান

1. **Core Skeleton Initialization:** PSR-4 কমপোসার স্ট্রাকচার, ডিরেক্টরি ট্রি এবং প্লাগিন মেইন ফাইল সেটআপ করা।
2. **Database Migration Runner:** কাস্টম ডেটাবেজ টেবিল ক্রিয়েট ও ভার্সন কন্ট্রোল ক্লাস তৈরি।
3. **Post Type & Taxonomy Registration:** ট্যুর, হোটেল ও রুম পোস্ট টাইপ রেজিস্টার করা।
4. **মেটাবক্স ও অ্যাডমিন লেয়ার ডেভেলপমেন্ট শুরু করা।**
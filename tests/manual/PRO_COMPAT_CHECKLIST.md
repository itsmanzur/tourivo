# Tourivo ↔ Tourivo Pro compatibility checklist

Run this before releasing either plugin, and after renaming, adding or removing any `tourivo/...` hook.
Both checkouts are expected side by side (`plugins/tourivo` and `plugins/tourivo-pro`).

## 1. Hook names (automatic)

```
php scripts/check-hooks.php            # add --verbose to see every match
php scripts/check-hooks.php --pro=/path/to/tourivo-pro
```

- [ ] Exit code 0: every `tourivo/...` hook Pro listens to (`add_action`, `add_filter`, `has_*`, `remove_*`)
      is fired by core or by Pro (`do_action*`, `apply_filters*`).
- [ ] No "Deprecated names" section. A pre-1.5.0 name still works through `Support\LegacyHooks`, but Pro
      must use the new `tourivo/booking/*` / `tourivo/inquiry/created` names.
- [ ] Every "runtime name" listener was checked by hand.
- [ ] A hook that core stops firing is either still fired under its old name or added to
      `LegacyHooks::ACTIONS` / `LegacyHooks::FILTERS`, so third-party code keeps working.

## 2. Version gate

- [ ] `tourivo-pro.php` requires the oldest core that fires every hook Pro listens to
      (currently `version_compare(TOURIVO_VERSION, '1.0.0', '<')` and the matching admin notice).
- [ ] `Requires Plugins: tourivo` is still in the Pro header.

## 3. Test suites

- [ ] Core: `php tests/run_all_tests.php` passes.
- [ ] Pro: `php tests/run_all_tests.php` (in tourivo-pro) passes.
- [ ] Core, real database (MySQL 8.0 and MariaDB), when inventory code changed:
      `php tests/RealDb/run_inventory_concurrency.php` and `php tests/RealDb/run_inventory_upsert_guard.php`
      (CI runs both on `mysql:8.0` and `mariadb:10.11`).

## 4. Manual smoke test (both plugins active, `WP_DEBUG` on)

- [ ] New booking: Pro coupon is applied and committed; Pro message/notification goes out
      (`tourivo/booking/created`, `tourivo/booking/coupon_commit`).
- [ ] Status change to confirmed and to cancelled: Pro messages, WooCommerce order sync
      (`tourivo/booking/status_changed`); a cancelled booking rolls its coupon back
      (`tourivo/booking/coupon_rollback`).
- [ ] Package booking: package price lines and the package title appear in the panel, emails and
      My Bookings (`tourivo/booking/adjustments`, `tourivo/booking/title`, `tourivo/accept_extra_items`).
- [ ] Pro pricing rules (seasonal / group) change the booking panel total (`tourivo/booking/price`).
- [ ] Pro payment methods show in the booking and package panels
      (`tourivo/booking/panel_payment_options`, `tourivo/public_payment_methods`).
- [ ] Direct / WooCommerce payment marks the booking verified (`tourivo/booking/email_verified` fired by Pro).
- [ ] Thank-you page and My Bookings show Pro buttons (documents, balance payment, tickets).
- [ ] `debug.log` has no "is deprecated since version 1.5.0" notice coming from Pro.

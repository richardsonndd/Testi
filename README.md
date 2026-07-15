# PS Gaming Center — Install Guide

A simple, single-folder PHP + MySQLi app (same style as Verona Menu) — plain pages,
no JavaScript framework, no build step. No Angular, no JWT, no API layer to debug.

## Install (2 steps)

1. **Upload everything in this zip** to your web root, e.g. `public_html/` (or a
   subfolder like `public_html/psgc/` — both work with no extra configuration, since
   every link in this app is a plain relative link).
2. **Visit `install.php`** in your browser (e.g. `https://yourdomain.com/psgc/install.php`).
   - Create a MySQL database + user first (control panel → "MySQL Databases").
   - Fill in the DB credentials and an admin account, submit.
   - Tables are created and your admin login works immediately.
3. Log in and start adding stations (Stations page) and snacks (Snacks page).

Then **delete or rename `install.php`** — it refuses to run a second time once
installed, but it's best removed entirely.

## Requirements

- PHP 7.4+ with the `mysqli` extension (standard on virtually every host)
- MySQL/MariaDB
- Apache with `mod_rewrite` is NOT required — this app uses plain `.php` links only

## What's included

- **Login / logout** — session-based, `password_hash`/`password_verify`, no JWT
- **Floor Plan** (`index.php`) — visual station layout per zone:
  - Click a station to open a panel: start session, add a snack, end session
  - Admins can drag a station to reposition it (auto-saves)
  - Live running timer while a session is active
- **Stations** (`stations.php`, admin only) — add/edit/delete stations and zones
- **Snacks** (`snacks.php`, admin only) — manage snack inventory and prices
- **History** (`sessions.php`) — every closed session with duration, price, and
  snack totals, plus a running revenue total
- **Staff** (`users.php`, admin only) — create/delete accounts, reset passwords,
  assign ADMIN or STAFF role

## How it works (so it's easy to maintain yourself)

- Every page is a normal PHP file: reads `$_POST`/`$_GET`, runs a `mysqli_prepare`/
  `mysqli_stmt_bind_param` query, and either renders HTML or redirects back.
- No routing layer, no API contract, no JSON — just forms that POST to small
  action scripts (`station_action.php`, `order_action.php`) which redirect back
  to the page you came from.
- The only JavaScript (`assets/js/floorplan.js`) is optional/progressive: it makes
  the running timer tick live and lets admins drag stations. If JS is disabled,
  everything still works except those two things — sessions still start/end fine
  via the button forms.
- All database access uses parameterized queries (`mysqli_prepare` +
  `mysqli_stmt_bind_param`) — no string-concatenated SQL anywhere.

## Notes on roles

- **ADMIN**: everything, plus managing stations, snacks, zones, and staff accounts.
- **STAFF**: floor plan (start/end sessions, add snacks to a running session) and
  session history — no access to Stations/Snacks/Staff management pages.
- The very first account is whatever you create during install — always ADMIN.
  Create more accounts from the Staff page afterward.

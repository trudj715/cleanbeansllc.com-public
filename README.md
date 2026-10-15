# cleanbeansllc.com (public source)

Website for **Clean Beans LLC**, a cleaning company in Hudson, Wisconsin: residential and deep cleaning,
move-in/move-out, rental flips and listing prep, commercial and office, post-construction, and hoarding,
biohazard and complex cleans.

- Live: https://cleanbeansllc.com (under-construction page until launch; the site is staged at `/w/`)
- Facebook: https://www.facebook.com/cleanbeansllc

This is the public copy of the site code. Deployment configuration and mail-forwarding scripts live in
the private repository.

## What's here

| Path | Purpose |
|---|---|
| `index.html`, `.htaccess` | Web root: redirects the home page to `/uc/` until launch |
| `uc/` | Under-construction page |
| `w/` | The website: PHP 8 + MySQL, with an owner admin (pages, photos, quote-request inbox, users, two-step sign-in) |
| `w/install.php`, `w/sql/schema.sql` | One-time installer into an empty MySQL database |
| `CHANGELOG.md` | Release notes |

Credentials (`w/app/config.php`), uploads and logs are created on the server and are never committed.

## Running it locally

The SQL is portable, so the site runs on SQLite for testing: create `w/app/config.php` defining `DB_DSN`
(for example `sqlite:/tmp/cb.db`), `DB_NAME`, `BASE_URL` and `BASE_PATH` (`/w`), load the schema, and serve
the folder with `php -S`.

Built by St Croix Tech Services / TRUDJ.net. © Clean Beans LLC. All rights reserved: the code is shown for
reference, and the Clean Beans name, logo and content may not be reused.

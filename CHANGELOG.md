# Clean Beans LLC website — Changelog

## 1.0.1 — 2026-10-14 — Clean Beans logo

- New logo redrawn from Becky's embroidered patch and flyers: the sunglasses bean with the gritted-teeth grimace, raised
  glove hand, broom and bucket; arched CLEAN BEANS with Est. 2014 / LLC. / Insured, bonded (no phone number).
  Hand-drawn outlines and a satin-stitch texture to keep the patch's feel. Lettering outlined from Patrick Hand (OFL).
- Files: `assets/img/logo.svg` (mascot: header, footer, admin, installer), `logo-full.svg` (light lettering, for dark
  backgrounds: home hero, under-construction page), `logo-badge.svg` / `logo-badge.png` (navy badge; social/search logo),
  favicon.svg / favicon.ico / apple-touch-icon.png (bean face).
- Under-construction page leads with the full logo; Facebook and TikTok (@clean.beans.llc) buttons unchanged.
- Removed the placeholder hero-sparkles.svg.

## 1.0.0 — 2026-10-14 — First build (staged)

Built from the kmtow.com 3.0.0 platform (custom PHP/MySQL site + owner admin).

### Site
- Under-construction page at `/uc/` (logo, call/text button, Facebook and TikTok links); the domain root redirects there until launch.
- Website staged at `/w/`, search engines blocked (robots.txt + noindex) until launch.
- Pages: Home, House Cleaning (residential & deep), Move-In/Move-Out, Rental Flips & Listing Prep, Commercial & Office,
  Post-Construction, Hoarding/Biohazard/Complex Cleans, Service Area, About. Copy from the Clean Beans Facebook page.
- Design: green and black palette from the brand, bean-and-sparkle logo mark, Barlow typefaces, big tap-to-call number,
  mobile call bar, credentials row in the home hero.
- Service tiles show a line icon until Becky's job photos are uploaded and set as page banners.
- Service Area map centered on Hudson, WI with a 40-mile circle and a St. Croix Valley town list (to confirm with Becky).
- "Request a free quote" form on every page, with cleaning service types; requests go to the admin Inbox and an email alert.
- Footer: Facebook and TikTok links, St Croix Tech / TRUDJ.net credits.

### Removed from the kmtow platform
- Parts & equipment catalog: listings, categories, vehicle fitments, listing photos, parts inquiries, their admin screens,
  database tables, settings and styles.


### Verified (local, SQLite)
- All 9 pages 200, unknown paths and old /parts URLs 404, sitemap 9 URLs, robots blocks while staging.
- Quote form: saves to Inbox, shows confirmation, field errors on bad input. Admin sign-in and every admin screen 200.
- No horizontal overflow at 390px, 1366px or 1600px.

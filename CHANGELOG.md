# Clean Beans LLC website — Changelog

## 1.3.0 — 2026-10-15 — Conversations, email + text alerts, two-way texting

- Inquiries are now conversations (new `messages` table): form message, emails and texts both ways, internal notes,
  status changes and alerts, each with who sent it. Inbox list shows last activity, "Waiting for reply" and who answered.
- Alerts by email, text or both: primary recipient in Settings → Notifications, plus per-user *Email me* / *Text me*,
  phone and text style (full details + link, or link only) in Admin → Users.
- Replies from admin by email or text. Staff can reply straight from their mailbox (Reply-To `reply-<token>@`) or phone
  (business-number relay; `?` lists threads, `#12 …` picks one). Customer email replies and texts land in the conversation.
- Provider-neutral texting (`app/lib/sms.php`): Twilio or Telnyx, chosen in Settings → Text messaging (hostmaster);
  signed inbound webhook `sms/inbound/<key>`; STOP/START/HELP; `log` provider for testing. **Off until A2P 10DLC registration and keys.**
- Quote form: optional "OK to text me" consent box. New footer pages Privacy Policy and Text Message Terms (content rev 4).
- Fix: settings saved during a request are visible to the rest of that request.

## 1.2.0 — 2026-10-15 — Featured photos lead the site

- Imported the Facebook **Cover photos** (13) and **Profile pictures** (9) albums: full clean-room shots
  (whole-house collage, remodeled bathroom, living room), the embroidered logo patches, a hand-drawn logo
  and the business card. Library ids 431-451.
- Content rev 3 (`migration_3_featured_photos`, plan `app/content/photos-2026-10b.php`), applied on first load:
  - page banners: House Cleaning = living room, Rentals = whole-house collage, Construction = remodeled bathroom,
    Our Work = living room (others unchanged); only replaces banners rev 2 set;
  - home "Recent work" leads with the three full-room shots;
  - Our Work (393 photos): full clean rooms first, then adverts/logos, then job photos; album duplicates left out;
  - the 13 screenshots of customer text messages / Messenger chats are deleted from the photo library;
  - temporary import script removed again.
- Tested: hostmaster@cleanbeansllc.com forwarding end-to-end from Proton (delivered to inbox, From email@cleanbeansllc.com).

## 1.1.1 — 2026-10-15 — Domain email forwarding in Settings

- Admin > Settings > **Domain email**: "Forward all other @cleanbeansllc.com mail to" (owner and hostmaster;
  one or more addresses, comma-separated; blank = send to the hostmaster destination). Hostmaster-only:
  "Forward hostmaster@cleanbeansllc.com to". @cleanbeansllc.com destinations are refused (they would loop).
- The mail forwarder (`mail/`) reads these settings from the website database on every message, falling back
  to the owner's address / hostmaster@trudj.net if the database can't be reached. No cPanel change needed to re-route.
- cPanel (done 2026-10-15): Email Routing = Local; forwarder hostmaster@ -> `|/home/trudj/cleanbeans-mail/to-hostmaster.php`;
  Default Address -> `|/home/trudj/cleanbeans-mail/to-becky.php`. DNS: DKIM `default._domainkey` added at Name.com (valid).

## 1.1.0 — 2026-10-15 — Job photos from Facebook, Our Work gallery

- 430 photos from the Clean Beans Facebook page imported into the photo library (category "facebook"),
  resized to 1800px with 640px thumbnails and EXIF/GPS stripped by the normal upload code.
- Applied automatically on the first page load after deploy (`app/lib/migrations.php`, content rev 2; plan in
  `app/content/photos-2026-10.php`):
  - service page banners from the best finished-job shots;
  - home page "Recent work" gallery (9 photos) linking to the new page;
  - "From recent jobs" before-and-after strip on five service pages (not on Hoarding/Biohazard);
  - About page photo; written descriptions for the featured photos;
  - new **Our Work** page (menu, between Specialty Cleans and Service Area) with 384 photos. Flyers, customer
    text-message screenshots, photos of people, a car, a deer, near-blank and clog photos are left out (still in the library).
  - deletes the temporary import script `fbimport.php` from the server.
- Photo viewer: previous/next buttons, arrow keys, swipe on phones, "n / total" counter. Denser square grid on Our Work.
- Under-construction page: Facebook, TikTok and footer credit links open in a new tab.

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

### Email
- Mail forwarder (`mail/`) rebranded: hostmaster@ → hostmaster@trudj.net; catch-all → the owner,
  re-sent from "Clean Beans Domain Email Message" <email@cleanbeansllc.com>.

### Verified (local, SQLite)
- All 9 pages 200, unknown paths and old /parts URLs 404, sitemap 9 URLs, robots blocks while staging.
- Quote form: saves to Inbox, shows confirmation, field errors on bad input. Admin sign-in and every admin screen 200.
- No horizontal overflow at 390px, 1366px or 1600px.

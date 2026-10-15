<?php
/**
 * One-time content updates for a live site, applied on the first request after a deploy.
 * Each step runs once (tracked in settings.content_rev) and only touches content it owns,
 * so edits Becky makes in the admin afterwards are left alone.
 */
declare(strict_types=1);

const CONTENT_REV = 4;

function run_content_migrations(): void
{
    if (!IS_INSTALLED) return;
    try {
        $rev = (int)setting('content_rev', '1');
    } catch (Throwable $e) {
        return;
    }
    if ($rev >= CONTENT_REV) return;

    // Only one request applies the update; others carry on normally.
    $lock = @fopen(APP_DIR . '/content-migrate.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
    try {
        if ((int)setting('content_rev', '1') < 2) {
            migration_2_facebook_photos();
            setting_save('content_rev', '2');
        }
        if ((int)setting('content_rev', '1') < 3) {
            migration_3_featured_photos();
            setting_save('content_rev', '3');
        }
        if ((int)setting('content_rev', '1') < 4) {
            migration_4_conversations();
            setting_save('content_rev', '4');
        }
    } catch (Throwable $e) {
        error_log('Content migration failed: ' . $e->getMessage());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink(APP_DIR . '/content-migrate.lock');
    }
}

/** The media id if it is one of the photos imported from Facebook (original_name fb-<fbid>.jpg), else null. */
function media_id_for_fbid(int|string $mid): ?int
{
    $id = db_val("SELECT id FROM media WHERE id=? AND original_name LIKE 'fb-%'", [(int)$mid]);
    return $id ? (int)$id : null;
}

/**
 * Rev 2 (v1.1.0): job photos from the Clean Beans Facebook page.
 * - service page banners and the home page "Recent work" gallery use the best photos
 * - new "Our Work" page with a large gallery of most of the imported photos
 * - removes the temporary import script used to bring the photos in
 */
function migration_2_facebook_photos(): void
{
    @unlink(APP_ROOT . '/fbimport.php');

    $plan = require APP_DIR . '/content/photos-2026-10.php';
    $ids = fn(array $fbids) => array_values(array_filter(array_map('media_id_for_fbid', $fbids)));
    if (!$ids($plan['gallery'])) {
        return;   // fresh install without the imported photos: nothing to place
    }
    $now = now();
    $pageId = fn(string $slug) => db_val('SELECT id FROM pages WHERE slug=?', [$slug]);

    db()->beginTransaction();
    try {
        // Photo descriptions written for the site (better than Facebook's automatic ones)
        db_exec("UPDATE media SET alt_text='Clean Beans job photo' WHERE category='facebook' AND (alt_text LIKE 'No photo description%' OR alt_text='')");
        foreach ($plan['alt'] ?? [] as $fbid => $alt) {
            $mid = media_id_for_fbid($fbid);
            if ($mid) db_exec('UPDATE media SET alt_text=? WHERE id=?', [$alt, $mid]);
        }

        // Page banners (only where the page has none yet)
        foreach ($plan['heroes'] as $slug => $fbid) {
            $mid = media_id_for_fbid($fbid);
            $pid = $pageId($slug);
            if ($mid && $pid) {
                db_exec('UPDATE pages SET hero_media_id=?, updated_at=? WHERE id=? AND hero_media_id IS NULL', [$mid, $now, $pid]);
            }
        }

        // Home: "Recent work" gallery after the intro, replacing the text-only Stillwater note
        $home = $pageId('home');
        if ($home) {
            $intro = db_val("SELECT sort_order FROM sections WHERE page_id=? AND heading='A local cleaning company'", [$home]);
            db_exec("UPDATE sections SET is_enabled=0, updated_at=? WHERE page_id=? AND heading LIKE 'Recent work:%'", [$now, $home]);
            db_insert('sections', ['page_id' => $home, 'type' => 'gallery', 'heading' => 'Recent work',
                'body_html' => '<p>A few jobs from around the valley. <a href="our-work">See more of our work</a> or follow along on '
                    . '<a href="https://www.facebook.com/cleanbeansllc" target="_blank" rel="noopener">Facebook</a> and '
                    . '<a href="https://www.tiktok.com/@clean.beans.llc" target="_blank" rel="noopener">TikTok</a>.</p>',
                'media_id' => null, 'data' => json_encode(['media_ids' => $ids($plan['home'])]),
                'sort_order' => ($intro ? (int)$intro : 20) + 5, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Service pages: a photo strip of that kind of work, just before the call-to-action
        foreach ($plan['service_galleries'] ?? [] as $slug => $fbids) {
            $pid = $pageId($slug);
            $mids = $ids($fbids);
            if (!$pid || !$mids) continue;
            $cta = db_val("SELECT MIN(sort_order) FROM sections WHERE page_id=? AND type='cta'", [$pid]);
            db_insert('sections', ['page_id' => $pid, 'type' => 'gallery', 'heading' => 'From recent jobs', 'body_html' => '',
                'media_id' => null, 'data' => json_encode(['media_ids' => $mids]),
                'sort_order' => ($cta ? (int)$cta : 100) - 5, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // About: photo beside the story
        if (!empty($plan['about_photo'])) {
            $about = $pageId('about');
            $mid = media_id_for_fbid($plan['about_photo']);
            if ($about && $mid) {
                $first = db_one("SELECT id FROM sections WHERE page_id=? AND type='text' ORDER BY sort_order LIMIT 1", [$about]);
                if ($first) db_exec("UPDATE sections SET type='image_text', media_id=?, updated_at=? WHERE id=?", [$mid, $now, $first['id']]);
            }
        }

        // New page: Our Work (big gallery)
        if (!$pageId('our-work')) {
            $p = db_insert('pages', ['slug' => 'our-work', 'title' => 'Our Work', 'nav_label' => 'Our Work', 'show_in_nav' => 1, 'nav_order' => 75,
                'is_published' => 1, 'hero_media_id' => media_id_for_fbid($plan['heroes']['our-work'] ?? 0),
                'hero_heading' => 'Our work',
                'hero_text' => 'Photos from Clean Beans jobs around Hudson and the St. Croix Valley: move-outs, deep cleans, rental turnovers, appliances, bathrooms and more.',
                'meta_title' => 'Our Work: Cleaning Photos | Clean Beans LLC, Hudson WI',
                'meta_description' => 'Before-and-after and finished-job photos from Clean Beans LLC: move-out cleans, deep cleans, rental flips and appliance cleaning around Hudson, WI.',
                'created_at' => $now, 'updated_at' => $now]);
            $o = 0;
            $add = function (string $type, string $heading, string $body, array $data = []) use ($p, $now, &$o) {
                $o += 10;
                db_insert('sections', ['page_id' => $p, 'type' => $type, 'heading' => $heading, 'body_html' => $body, 'media_id' => null,
                    'data' => json_encode($data), 'sort_order' => $o, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now]);
            };
            $add('gallery', '', '<p>Tap any photo to see it larger. New jobs go up on '
                . '<a href="https://www.facebook.com/cleanbeansllc" target="_blank" rel="noopener">Facebook</a> first.</p>',
                ['media_ids' => $ids($plan['gallery']), 'big' => 1]);
            $add('cta', 'Want your place looking like this?', '<p>Tell Becky about the space and what you need. You\'ll get a straight answer on what it takes and what it costs.</p>');
            $add('request_form', 'Request a free quote', '', ['band' => 1]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/**
 * Rev 3 (v1.2.0): featured photos from the Facebook Cover photos and Profile pictures albums.
 * - full clean-room shots become page banners and lead the home "Recent work" gallery
 * - Our Work: full clean rooms first, then adverts, then job photos (duplicates left out)
 * - text-message / Messenger screenshots are removed from the photo library
 * Banners and galleries are only changed where they still hold what rev 2 put there.
 */
function migration_3_featured_photos(): void
{
    @unlink(APP_ROOT . '/fbimport.php');
    $plan = require APP_DIR . '/content/photos-2026-10b.php';
    $rev2 = require APP_DIR . '/content/photos-2026-10.php';
    $ids = fn(array $list) => array_values(array_unique(array_filter(array_map('media_id_for_fbid', $list))));
    if (!$ids($plan['featured'])) return;   // featured photos not imported on this install
    $now = now();
    $pageId = fn(string $slug) => db_val('SELECT id FROM pages WHERE slug=?', [$slug]);

    db()->beginTransaction();
    try {
        foreach ($plan['alt'] as $mid => $alt) {
            if (media_id_for_fbid($mid)) db_exec('UPDATE media SET alt_text=? WHERE id=?', [$alt, (int)$mid]);
        }
        // Banners: replace rev 2's choice (or an empty banner), leave owner changes alone
        foreach ($plan['heroes'] as $slug => $mid) {
            $pid = $pageId($slug);
            if (!$pid || !media_id_for_fbid($mid)) continue;
            $old = $rev2['heroes'][$slug] ?? null;
            db_exec('UPDATE pages SET hero_media_id=?, updated_at=? WHERE id=? AND (hero_media_id IS NULL OR hero_media_id=?)',
                [(int)$mid, $now, $pid, $old ?? 0]);
        }
        // Galleries: home "Recent work" and the Our Work page, if still as rev 2 left them
        $setGallery = function (?int $pid, string $where, array $oldIds, array $newIds) use ($now) {
            if (!$pid || !$newIds) return;
            foreach (db_all("SELECT id, data FROM sections WHERE page_id=? AND type='gallery' AND $where", [$pid]) as $sec) {
                $d = json_decode((string)$sec['data'], true) ?: [];
                if (array_map('intval', $d['media_ids'] ?? []) !== array_map('intval', $oldIds)) continue;
                $d['media_ids'] = $newIds;
                db_exec('UPDATE sections SET data=?, updated_at=? WHERE id=?', [json_encode($d), $now, $sec['id']]);
            }
        };
        $setGallery($pageId('home'), "heading='Recent work'", $ids($rev2['home']), $ids($plan['home']));
        $setGallery($pageId('our-work'), "heading=''", $ids($rev2['gallery']), $ids($plan['gallery']));

        // Remove screenshots of text messages / Messenger chats from the library
        foreach ($plan['delete'] as $mid) {
            if (media_id_for_fbid($mid)) {
                // drop from any gallery first so the delete is allowed
                foreach (db_all("SELECT id, data FROM sections WHERE type='gallery'") as $sec) {
                    $d = json_decode((string)$sec['data'], true) ?: [];
                    $list = array_map('intval', $d['media_ids'] ?? []);
                    if (in_array((int)$mid, $list, true)) {
                        $d['media_ids'] = array_values(array_diff($list, [(int)$mid]));
                        db_exec('UPDATE sections SET data=? WHERE id=?', [json_encode($d), $sec['id']]);
                    }
                }
                media_delete((int)$mid);
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/** True if $table has $column (works on MySQL and SQLite). */
function db_has_column(string $table, string $column): bool
{
    try {
        db()->query("SELECT $column FROM $table LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Rev 4 (v1.3.0): conversations, per-user alerts, texts.
 * - table messages (the thread for each inquiry)
 * - users: phone, notify_email, notify_sms, sms_style, sms_relay
 * - inquiries: reply_token, sms_opt_in, sms_opted_out, last_activity_at
 * - existing inquiries get their original message as the first line of the thread
 * - Privacy Policy and Text Message Terms pages (needed for carrier SMS registration)
 */
function migration_4_conversations(): void
{
    $mysql = !defined('DB_DSN');
    $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    if (!db_has_column('messages', 'id')) {
        db()->exec("CREATE TABLE messages (
            id $pk,
            inquiry_id INT NOT NULL,
            direction VARCHAR(10) NOT NULL,
            channel VARCHAR(10) NOT NULL,
            user_id INT NULL,
            author VARCHAR(120) NOT NULL DEFAULT '',
            body TEXT NULL,
            addr VARCHAR(190) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT '',
            provider_id VARCHAR(80) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        )$tail");
        db()->exec('CREATE INDEX ix_messages_inquiry ON messages (inquiry_id, id)');
    }
    $cols = [
        ['users', 'phone', "VARCHAR(40) NOT NULL DEFAULT ''"],
        ['users', 'notify_email', 'TINYINT NOT NULL DEFAULT 0'],
        ['users', 'notify_sms', 'TINYINT NOT NULL DEFAULT 0'],
        ['users', 'sms_style', "VARCHAR(10) NOT NULL DEFAULT 'details'"],
        ['users', 'sms_relay', 'TINYINT NOT NULL DEFAULT 0'],
        ['inquiries', 'reply_token', "VARCHAR(16) NOT NULL DEFAULT ''"],
        ['inquiries', 'sms_opt_in', 'TINYINT NOT NULL DEFAULT 0'],
        ['inquiries', 'sms_opted_out', 'TINYINT NOT NULL DEFAULT 0'],
        ['inquiries', 'last_activity_at', 'DATETIME NULL'],
    ];
    foreach ($cols as [$t, $c, $def]) {
        if (!db_has_column($t, $c)) db()->exec("ALTER TABLE $t ADD COLUMN $c $def");
    }

    db()->beginTransaction();
    try {
        // Seed each existing inquiry's thread with its original message
        foreach (db_all('SELECT i.* FROM inquiries i WHERE NOT EXISTS (SELECT 1 FROM messages m WHERE m.inquiry_id=i.id)') as $q) {
            db_insert('messages', ['inquiry_id' => $q['id'], 'direction' => 'in', 'channel' => 'web', 'user_id' => null, 'author' => $q['name'],
                'body' => (string)$q['message'], 'addr' => $q['email'] ?: $q['phone'], 'status' => 'received', 'provider_id' => '', 'created_at' => $q['created_at']]);
            db_exec('UPDATE inquiries SET last_activity_at=? WHERE id=?', [$q['updated_at'] ?: $q['created_at'], $q['id']]);
        }
        // Legal pages (not in the menu; linked from the footer and the quote form)
        $now = now();
        $legal = require APP_DIR . '/content/legal-pages.php';
        foreach ($legal as $slug => $pg) {
            if (db_val('SELECT id FROM pages WHERE slug=?', [$slug])) continue;
            $pid = db_insert('pages', ['slug' => $slug, 'title' => $pg['title'], 'nav_label' => $pg['title'], 'show_in_nav' => 0, 'nav_order' => 200,
                'is_published' => 1, 'hero_media_id' => null, 'hero_heading' => $pg['title'], 'hero_text' => $pg['intro'],
                'meta_title' => $pg['title'] . ' | ' . setting('company_name', 'Clean Beans LLC'), 'meta_description' => $pg['intro'],
                'created_at' => $now, 'updated_at' => $now]);
            db_insert('sections', ['page_id' => $pid, 'type' => 'text', 'heading' => '', 'body_html' => $pg['body'], 'media_id' => null,
                'data' => '[]', 'sort_order' => 10, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        // Defaults: alerts reply through the site; text alerts to the primary phone relay customer texts
        if (setting('alert_reply_mode') === '') setting_save('alert_reply_mode', 'thread');
        if (setting('notify_sms_style') === '') setting_save('notify_sms_style', 'details');
        if (setting('notify_sms_relay') === '') setting_save('notify_sms_relay', '1');
        if (setting('sms_provider') === '') setting_save('sms_provider', 'log');
        if (setting('sms_enabled') === '') setting_save('sms_enabled', '0');
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

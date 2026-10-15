<?php
/**
 * One-time content updates for a live site, applied on the first request after a deploy.
 * Each step runs once (tracked in settings.content_rev) and only touches content it owns,
 * so edits Becky makes in the admin afterwards are left alone.
 */
declare(strict_types=1);

const CONTENT_REV = 2;

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

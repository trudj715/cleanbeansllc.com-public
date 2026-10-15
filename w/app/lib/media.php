<?php
/**
 * Photo library: uploads, resizing, thumbnails, URLs.
 *
 * Uploaded files are re-encoded with GD, which strips EXIF/GPS data and any
 * non-image payload. Files get random names; the uploads folder cannot run scripts.
 */
declare(strict_types=1);

const MEDIA_MAX_BYTES = 15 * 1024 * 1024;   // 15 MB per photo (phones)
const MEDIA_MAX_EDGE  = 1800;               // stored size (longest edge, px)
const MEDIA_SM_EDGE   = 640;                // thumbnail size

function media_url(?array $m, bool $small = false): string
{
    if (!$m) return '';
    $f = $small && !empty($m['file_sm']) ? $m['file_sm'] : $m['file'];
    return url('uploads/' . $f);
}

function media_get(?int $id): ?array
{
    return $id ? db_one('SELECT * FROM media WHERE id=?', [$id]) : null;
}

/** Many media rows by id, keyed by id. */
function media_many(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $rows = db_all('SELECT * FROM media WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
    return array_column($rows, null, 'id');
}

/** <img> tag with srcset for a media row. */
function media_img(?array $m, string $class = '', string $sizes = '100vw', bool $lazy = true, ?string $alt = null): string
{
    if (!$m) return '';
    $src = media_url($m);
    $set = !empty($m['file_sm']) ? ' srcset="' . e(media_url($m, true)) . ' ' . MEDIA_SM_EDGE . 'w, ' . e($src) . ' ' . (int)max($m['width'], $m['height']) . 'w" sizes="' . e($sizes) . '"' : '';
    return '<img src="' . e($src) . '"' . $set
        . ' alt="' . e($alt ?? $m['alt_text']) . '"'
        . ($m['width'] ? ' width="' . (int)$m['width'] . '" height="' . (int)$m['height'] . '"' : '')
        . ($class ? ' class="' . e($class) . '"' : '')
        . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"') . '>';
}

/**
 * Save one uploaded file (an entry from $_FILES). Returns the new media id.
 * Throws InvalidArgumentException with a user-readable message on bad input.
 */
function media_store_upload(array $f, string $alt = '', string $category = 'general', ?int $userId = null): int
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match ($f['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photo is larger than the server allows.',
            UPLOAD_ERR_NO_FILE => 'No file was chosen.',
            default => 'The upload did not finish. Please try again.',
        };
        throw new InvalidArgumentException($msg);
    }
    if ($f['size'] > MEDIA_MAX_BYTES) {
        throw new InvalidArgumentException('That photo is over 15 MB. Please use a smaller photo.');
    }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        throw new InvalidArgumentException('Only JPG, PNG, WebP or GIF photos can be uploaded.');
    }
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('The server is missing the GD image extension.');
    }
    $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$img) {
        throw new InvalidArgumentException('That file could not be read as a photo.');
    }
    if ($info[2] === IMAGETYPE_JPEG) {
        $img = media_apply_exif_rotation($img, $f['tmp_name']);
    }
    $keepAlpha = in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true);
    $ext = $keepAlpha ? 'png' : 'jpg';

    $dir = date('Y/m');
    @mkdir(APP_ROOT . '/uploads/' . $dir . '/sm', 0755, true);
    $name = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
    $nameSm = $dir . '/sm/' . basename($name);

    [$w, $h] = media_save_resized($img, APP_ROOT . '/uploads/' . $name, MEDIA_MAX_EDGE, $ext);
    media_save_resized($img, APP_ROOT . '/uploads/' . $nameSm, MEDIA_SM_EDGE, $ext);
    imagedestroy($img);

    $orig = preg_replace('/[^\w.\- ]+/', '', basename((string)$f['name'])) ?: 'photo';
    return db_insert('media', [
        'file' => $name, 'file_sm' => $nameSm, 'original_name' => substr($orig, 0, 190),
        'alt_text' => substr($alt !== '' ? $alt : pathinfo($orig, PATHINFO_FILENAME), 0, 255),
        'caption' => '', 'category' => $category, 'width' => $w, 'height' => $h,
        'uploaded_by' => $userId, 'created_at' => now(),
    ]);
}

function media_apply_exif_rotation(GdImage $img, string $path): GdImage
{
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($path);
    $o = (int)($exif['Orientation'] ?? 1);
    $r = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    return $r ?: $img;
}

/** Resize (never enlarge) and save. Returns [width, height] written. */
function media_save_resized(GdImage $src, string $dest, int $maxEdge, string $ext): array
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $maxEdge / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = $ext === 'png' ? imagepng($dst, $dest, 7) : imagejpeg($dst, $dest, 82);
    imagedestroy($dst);
    if (!$ok) {
        throw new RuntimeException('Could not save the photo (check that the uploads folder is writable).');
    }
    return [$nw, $nh];
}

/** Delete a photo's files and row (only when it is not used anywhere). */
function media_delete(int $id): bool
{
    if (media_usage_count($id) > 0) return false;
    $m = media_get($id);
    if (!$m) return false;
    foreach ([$m['file'], $m['file_sm']] as $f) {
        $p = realpath(APP_ROOT . '/uploads/' . $f);
        if ($p && str_starts_with($p, realpath(APP_ROOT . '/uploads') . DIRECTORY_SEPARATOR) && is_file($p)) {
            @unlink($p);
        }
    }
    db_exec('DELETE FROM media WHERE id=?', [$id]);
    return true;
}

function media_usage_count(int $id): int
{
    $n = (int)db_val('SELECT COUNT(*) FROM pages WHERE hero_media_id=?', [$id])
       + (int)db_val('SELECT COUNT(*) FROM sections WHERE media_id=?', [$id]);
    // Gallery sections store ids in JSON; settings may point at a photo.
    foreach (db_all("SELECT data FROM sections WHERE type='gallery'") as $s) {
        $d = json_decode((string)$s['data'], true);
        if (in_array($id, array_map('intval', $d['media_ids'] ?? []), true)) $n++;
    }
    return $n;
}

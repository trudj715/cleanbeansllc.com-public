<?php
/**
 * Inbound email for conversations.
 *
 * Alert emails and staff replies use a per-inquiry reply address reply-<token>@cleanbeansllc.com.
 * The domain catch-all (mail/to-becky.php) hands any message for such an address to mailin_handle():
 *   - from a staff address (an admin user, or the primary alert address): it is a staff reply.
 *     It is sent on to the customer from Clean Beans and saved in the conversation, credited to that person.
 *   - from anyone else (normally the customer): saved in the conversation and staff are alerted.
 * Quoted earlier messages ("On ... wrote:", "> ...") are trimmed so the thread stays readable.
 */
declare(strict_types=1);

/** Token from any reply-<token>@ address in the raw message's recipient headers, or ''. */
function mailin_token(string $raw): string
{
    $head = substr($raw, 0, (int)(strpos($raw, "\n\n") ?: strlen($raw)));
    $dom = preg_quote(mail_domain(), '/');
    if (preg_match('/reply-([a-f0-9]{12})@' . $dom . '/i', $head, $m)) return strtolower($m[1]);
    return '';
}

/** Split a raw message into [headers(array lowercase => string), body]. Folded headers are unfolded. */
function mailin_split(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    $pos = strpos($raw, "\n\n");
    $head = $pos === false ? $raw : substr($raw, 0, $pos);
    $body = $pos === false ? '' : substr($raw, $pos + 2);
    $headers = [];
    foreach (preg_split('/\n(?![ \t])/', $head) as $line) {
        if (!str_contains($line, ':')) continue;
        [$k, $v] = explode(':', $line, 2);
        $k = strtolower(trim($k));
        $v = trim(preg_replace('/\n[ \t]+/', ' ', $v));
        $headers[$k] = isset($headers[$k]) ? $headers[$k] . ', ' . $v : $v;
    }
    return [$headers, $body];
}

/** Best plain-text body of a MIME message (prefers text/plain, falls back to stripped text/html). */
function mailin_text(array $headers, string $body, int $depth = 0): string
{
    $ctype = strtolower($headers['content-type'] ?? 'text/plain');
    $cte = strtolower(trim($headers['content-transfer-encoding'] ?? ''));
    if ($depth < 5 && str_starts_with($ctype, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'] ?? '', $m)) {
        $parts = explode('--' . $m[1], $body);
        $html = '';
        foreach ($parts as $p) {
            $p = ltrim($p, "\n");
            if ($p === '' || str_starts_with($p, '--')) continue;
            [$ph, $pb] = mailin_split($p);
            $pt = strtolower($ph['content-type'] ?? 'text/plain');
            if (str_contains(strtolower($ph['content-disposition'] ?? ''), 'attachment')) continue;
            if (str_starts_with($pt, 'multipart/')) {
                $t = mailin_text($ph, $pb, $depth + 1);
                if ($t !== '') return $t;
            } elseif (str_starts_with($pt, 'text/plain')) {
                return mailin_text($ph, $pb, $depth + 1);
            } elseif (str_starts_with($pt, 'text/html') && $html === '') {
                $html = mailin_text($ph, $pb, $depth + 1);
            }
        }
        return $html;
    }
    if ($cte === 'base64') $body = (string)base64_decode(preg_replace('/\s+/', '', $body));
    elseif ($cte === 'quoted-printable') $body = quoted_printable_decode($body);
    if (preg_match('/charset="?([\w-]+)"?/i', $ctype, $cm) && strtolower($cm[1]) !== 'utf-8') {
        $conv = @mb_convert_encoding($body, 'UTF-8', $cm[1]);
        if (is_string($conv)) $body = $conv;
    }
    if (str_starts_with($ctype, 'text/html')) {
        $body = preg_replace('#<(br|/p|/div|/li)[^>]*>#i', "\n", $body);
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return trim(str_replace("\r", '', $body));
}

/** Drop quoted history and signatures that mail apps add below a reply. */
function mailin_strip_quotes(string $text): string
{
    $out = [];
    foreach (explode("\n", $text) as $line) {
        $t = trim($line);
        if (preg_match('/^On .{6,200}wrote:?$/i', $t) || preg_match('/^-{2,}\s*Original Message\s*-{2,}/i', $t)
            || preg_match('/^From: .+/i', $t) && count($out) > 0 || preg_match('/^Sent from my (iPhone|iPad|Android|phone)/i', $t)
            || $t === '-- ' || preg_match('/^_{5,}$/', $t)) {
            break;
        }
        if (str_starts_with($t, '>')) continue;
        $out[] = rtrim($line);
    }
    return trim(implode("\n", $out));
}

/** Email address inside "Name <addr>" (lowercase), or ''. */
function mailin_addr(string $h): string
{
    if (preg_match('/<([^>]+)>/', $h, $m)) $h = $m[1];
    $h = strtolower(trim($h, " \t\"'"));
    return filter_var($h, FILTER_VALIDATE_EMAIL) ? $h : '';
}

/** The staff member an email address belongs to: ['user_id' => ?int, 'name' => string] or null. */
function staff_for_email(string $addr): ?array
{
    if ($addr === '') return null;
    $u = db_one('SELECT * FROM users WHERE is_enabled=1 AND LOWER(email)=?', [$addr]);
    if ($u) return ['user_id' => (int)$u['id'], 'name' => $u['name'] ?: $u['email']];
    foreach (notify_recipients() as $r) {
        if ($r['email'] === $addr) return ['user_id' => $r['user_id'], 'name' => $r['name']];
    }
    return null;
}

/**
 * Handle one inbound message for a reply-<token>@ address. Returns true when it was taken care of
 * (saved/relayed or deliberately dropped), false if the token is unknown (caller forwards it normally).
 */
function mailin_handle(string $raw, string $token): bool
{
    $q = db_one('SELECT * FROM inquiries WHERE reply_token=?', [$token]);
    if (!$q) return false;
    [$headers, $body] = mailin_split($raw);
    if (!empty($headers['x-cb-site']) || preg_match('/^(yes)\b/i', $headers['x-spam-flag'] ?? '')) return true;   // our own mail / spam
    if (preg_match('/auto-(replied|generated)/i', $headers['auto-submitted'] ?? '') && !preg_match('/^no$/i', $headers['auto-submitted'] ?? '')) {
        thread_add((int)$q['id'], 'system', 'email', 'Automatic reply received (not shown): ' . mb_strimwidth($headers['subject'] ?? '', 0, 120, '…'));
        return true;
    }
    $from = mailin_addr($headers['from'] ?? '');
    $text = mailin_strip_quotes(mailin_text($headers, $body));
    if ($text === '') $text = '(empty message)';

    $staff = staff_for_email($from);
    if ($staff && strtolower((string)$q['email']) !== $from) {
        [$ok, $err] = inquiry_reply($q, 'email', $text, $staff);
        if (!$ok) {
            site_mail($from, 'Not sent: your reply to ' . $q['name'], "Your reply to " . inquiry_label($q) . " was not sent: $err\n\n" . abs_url('admin/inbox/' . $q['id']) . "\n",
                setting('brand_name', 'Clean Beans') . ' Website', setting('notify_from', 'hostmaster@cleanbeansllc.com'));
        }
        return true;
    }
    thread_add((int)$q['id'], 'in', 'email', $text, null, ['author' => $q['name'], 'addr' => $from, 'status' => 'received']);
    if ($q['status'] === 'closed') db_exec("UPDATE inquiries SET status='new' WHERE id=?", [$q['id']]);
    inquiry_alert((int)$q['id'], 'reply', $text, 'email');
    return true;
}

<?php
/**
 * Conversations about a website inquiry: the thread, staff notifications, replies.
 *
 * Every inquiry has a thread (table `messages`): the original request, customer replies by email or text,
 * staff replies from the admin, by email or from their phone, internal notes, and a line for each alert sent.
 *
 * Who gets alerted about new inquiries and replies:
 *   - the primary recipient in Settings > Notifications (email and/or text phone)
 *   - every enabled admin user with Inbox access who turned alerts on in Users
 * Each recipient chooses email, text or both, and for texts: full details + link, or just the link.
 * Recipients marked "relay" also get customer texts on their phone and can answer them by replying.
 */
declare(strict_types=1);

const MSG_DIRECTIONS = ['in', 'out', 'note', 'system'];

/** Add one line to an inquiry's thread. Returns the message id. */
function thread_add(int $inquiryId, string $direction, string $channel, string $body, ?int $userId = null, array $extra = []): int
{
    $now = now();
    $id = db_insert('messages', [
        'inquiry_id' => $inquiryId, 'direction' => in_array($direction, MSG_DIRECTIONS, true) ? $direction : 'system',
        'channel' => $channel, 'user_id' => $userId, 'author' => mb_substr((string)($extra['author'] ?? ''), 0, 120),
        'body' => mb_substr($body, 0, 20000), 'addr' => mb_substr((string)($extra['addr'] ?? ''), 0, 190),
        'status' => mb_substr((string)($extra['status'] ?? ''), 0, 20), 'provider_id' => mb_substr((string)($extra['provider_id'] ?? ''), 0, 80),
        'created_at' => $now,
    ]);
    db_exec('UPDATE inquiries SET last_activity_at=?, updated_at=? WHERE id=?', [$now, $now, $inquiryId]);
    return $id;
}

function thread_messages(int $inquiryId): array
{
    return db_all('SELECT m.*, u.name AS user_name, u.email AS user_email FROM messages m LEFT JOIN users u ON u.id=m.user_id
                   WHERE m.inquiry_id=? ORDER BY m.id', [$inquiryId]);
}

/** Per-inquiry reply address, e.g. reply-3f9a0c1b2d4e@cleanbeansllc.com (caught by the domain catch-all). */
function inquiry_reply_token(array $q): string
{
    if (preg_match('/^[a-f0-9]{12}$/', (string)($q['reply_token'] ?? ''))) return $q['reply_token'];
    $t = bin2hex(random_bytes(6));
    db_exec('UPDATE inquiries SET reply_token=? WHERE id=?', [$t, $q['id']]);
    return $t;
}

function mail_domain(): string
{
    $from = setting('notify_from', 'hostmaster@cleanbeansllc.com');
    return strtolower(substr(strrchr($from, '@') ?: '@cleanbeansllc.com', 1));
}

function inquiry_reply_address(array $q): string
{
    return 'reply-' . inquiry_reply_token($q) . '@' . mail_domain();
}

/** Send a plain-text email from the website. Envelope sender = alert sender so SPF/DKIM align. */
function site_mail(string $to, string $subject, string $body, string $fromName, string $fromAddr, string $replyTo = '', array $extraHeaders = []): bool
{
    $one = fn(string $s): string => trim(preg_replace('/[\r\n\t]+/', ' ', $s));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) return false;
    $envelope = setting('notify_from', 'hostmaster@cleanbeansllc.com');
    if (!filter_var($envelope, FILTER_VALIDATE_EMAIL)) $envelope = $fromAddr;
    $h = 'From: "' . str_replace('"', '', $one($fromName)) . '" <' . $fromAddr . ">\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $h .= 'Reply-To: ' . $replyTo . "\r\n";
    $h .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nX-CB-Site: 1\r\n";
    foreach ($extraHeaders as $k => $v) $h .= $k . ': ' . $one((string)$v) . "\r\n";
    $ok = mail($to, mb_encode_mimeheader($one($subject), 'UTF-8', 'Q'), $body, $h, '-f' . $envelope);
    if (!$ok) err_log('mail', "Email to $to ($subject) was not accepted by the server's mail program.\n");
    return $ok;
}

/* ======================= Who to alert ======================= */

/**
 * Alert recipients: [['key','name','user_id','email','phone','sms_style','relay']].
 * The primary recipient (Settings) comes first; then users who opted in. Same address/phone only once.
 */
function notify_recipients(): array
{
    $out = [];
    $seenE = $seenP = [];
    $add = function (array $r) use (&$out, &$seenE, &$seenP) {
        $e = strtolower(trim((string)$r['email']));
        $p = sms_e164((string)$r['phone']);
        if ($e !== '' && (isset($seenE[$e]) || !filter_var($e, FILTER_VALIDATE_EMAIL))) $e = '';
        if ($p !== '' && isset($seenP[$p])) $p = '';
        if ($e === '' && $p === '') return;
        if ($e !== '') $seenE[$e] = 1;
        if ($p !== '') $seenP[$p] = 1;
        $out[] = ['key' => $r['key'], 'name' => $r['name'], 'user_id' => $r['user_id'], 'email' => $e, 'phone' => $p,
                  'sms_style' => $r['sms_style'] === 'link' ? 'link' : 'details', 'relay' => !empty($r['relay']) && $p !== ''];
    };
    $add(['key' => 'primary', 'name' => setting('notify_name', 'Primary recipient'), 'user_id' => null,
          'email' => setting('notify_email'), 'phone' => setting('notify_sms_phone'),
          'sms_style' => setting('notify_sms_style', 'details'), 'relay' => setting('notify_sms_relay', '1') === '1']);
    foreach (db_all('SELECT * FROM users WHERE is_enabled=1 ORDER BY id') as $u) {
        if (!(((int)$u['permissions'] & PERM_INBOX) || (int)$u['is_protected'] === 1)) continue;
        $add(['key' => 'user' . $u['id'], 'name' => $u['name'] ?: $u['email'], 'user_id' => (int)$u['id'],
              'email' => (int)$u['notify_email'] === 1 ? $u['email'] : '',
              'phone' => (int)$u['notify_sms'] === 1 ? (string)$u['phone'] : '',
              'sms_style' => (string)$u['sms_style'], 'relay' => (int)$u['sms_relay'] === 1]);
    }
    return $out;
}

/** The staff member (or primary recipient) a phone number belongs to, if it is set up to relay. */
function staff_for_phone(string $e164): ?array
{
    foreach (notify_recipients() as $r) {
        if ($r['phone'] !== '' && $r['phone'] === $e164) return $r;
    }
    // A staff phone saved on a user who has text alerts off still counts as staff (so they can answer)
    foreach (db_all("SELECT * FROM users WHERE is_enabled=1 AND phone<>''") as $u) {
        if (sms_e164((string)$u['phone']) === $e164) {
            return ['key' => 'user' . $u['id'], 'name' => $u['name'] ?: $u['email'], 'user_id' => (int)$u['id'], 'email' => '',
                    'phone' => $e164, 'sms_style' => 'details', 'relay' => true];
        }
    }
    return null;
}

/** Which conversation a staff phone is "in" (the last one relayed to it or answered from it). */
function staff_current_inquiry(array $staff): ?int
{
    $v = (int)setting('sms_relay_ptr_' . $staff['key'], '0');
    return $v > 0 ? $v : null;
}

function staff_set_current_inquiry(array $staff, int $inquiryId): void
{
    setting_save('sms_relay_ptr_' . $staff['key'], (string)$inquiryId);
}

/* ======================= Alerts ======================= */

/** Text line for an inquiry: "#12 Jane Doe (Move-in / move-out)". */
function inquiry_label(array $q): string
{
    return '#' . $q['id'] . ' ' . $q['name'] . ($q['service_type'] !== '' ? ' (' . $q['service_type'] . ')' : '');
}

/**
 * Alert everyone who should know about a new inquiry ($event = 'new') or a customer reply ($event = 'reply').
 * $text = the customer's words for replies. Each alert sent is noted in the thread.
 */
function inquiry_alert(int $inquiryId, string $event = 'new', string $text = '', string $channel = 'web'): void
{
    $q = db_one('SELECT * FROM inquiries WHERE id=?', [$inquiryId]);
    if (!$q) return;
    $link = abs_url('admin/inbox/' . $q['id']);
    $company = setting('brand_name', 'Clean Beans');
    $one = fn(string $s): string => trim(preg_replace('/\s+/', ' ', $s));
    $fromAddr = setting('notify_from', 'hostmaster@cleanbeansllc.com');
    if (!filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) $fromAddr = 'hostmaster@cleanbeansllc.com';
    $replyThroughSite = setting('alert_reply_mode', 'thread') === 'thread';
    $smsOn = sms_enabled();
    $sentTo = [];

    foreach (notify_recipients() as $r) {
        // ---- email
        if ($r['email'] !== '') {
            if ($event === 'new') {
                $subject = 'New website quote request: ' . $one($q['name']) . ($q['service_type'] !== '' ? ' (' . $q['service_type'] . ')' : '');
                $lines = ['A new ' . ($channel === 'sms' ? 'text message' : 'quote request') . ' came in for ' . $company . '.', '',
                          'Name:     ' . $one($q['name'])];
                if ($q['phone'] !== '') $lines[] = 'Phone:    ' . $one($q['phone']);
                if ($q['email'] !== '') $lines[] = 'Email:    ' . $one($q['email']);
                if ($q['service_type'] !== '') $lines[] = 'Needs:    ' . $q['service_type'];
                if ($q['location'] !== '') $lines[] = 'Location: ' . $one($q['location']);
                $lines[] = '';
                $lines[] = (string)($q['message'] !== '' ? $q['message'] : '(no message)');
            } else {
                $subject = 'Reply from ' . $one($q['name']) . ' (' . inquiry_label($q) . ')';
                $lines = [$one($q['name']) . ' replied by ' . ($channel === 'sms' ? 'text' : 'email') . ':', '', $text];
            }
            $lines[] = '';
            $lines[] = 'Open the conversation: ' . $link;
            $lines[] = '';
            if ($replyThroughSite && $q['email'] !== '') {
                $lines[] = '-- Reply to this email to answer ' . $one($q['name']) . '. Your reply goes to them from ' . $company
                         . ' and is saved in the conversation.';
            } elseif ($q['email'] !== '') {
                $lines[] = '-- Reply to this email to answer ' . $one($q['name']) . ' directly.';
            } else {
                $lines[] = '-- ' . $one($q['name']) . ' did not leave an email address' . ($q['phone'] !== '' ? '; call or text them.' : '.');
            }
            $replyTo = $replyThroughSite && $q['email'] !== '' ? inquiry_reply_address($q) : ($q['email'] ?: '');
            $ok = site_mail($r['email'], $subject, implode("\n", $lines) . "\n", $company . ' Website', $fromAddr, $replyTo,
                ['X-CB-Inquiry' => (string)$q['id']]);
            $sentTo[] = ($ok ? '' : 'FAILED ') . 'email ' . $r['email'];
        }
        // ---- text
        if ($r['phone'] !== '' && $smsOn) {
            $canRelay = $r['relay'] && $q['phone'] !== '' && empty($q['sms_opted_out']);
            if ($r['sms_style'] === 'link') {
                $body = $company . ': ' . ($event === 'new' ? 'new inquiry ' : 'new reply on ') . '#' . $q['id'] . '. ' . $link;
            } elseif ($event === 'new') {
                $body = $company . ' ' . inquiry_label($q) . "\n"
                      . ($q['phone'] !== '' ? $q['phone'] . "\n" : '') . ($q['location'] !== '' ? $q['location'] . "\n" : '')
                      . mb_strimwidth($one((string)$q['message']), 0, 600, '…') . "\n" . $link;
            } else {
                $body = $company . ' #' . $q['id'] . ' ' . $q['name'] . ': ' . mb_strimwidth($one($text), 0, 900, '…') . "\n" . $link;
            }
            if ($canRelay && (!empty($q['sms_opt_in']) || $channel === 'sms')) {
                $first = str_starts_with($q['name'], 'Text from') ? 'them' : strtok($one($q['name']), ' ');
                $body .= "\nReply to text " . $first . ' back.';
            }
            $res = sms_send($r['phone'], $body);
            if ($res['ok'] && $canRelay) staff_set_current_inquiry($r, (int)$q['id']);
            $sentTo[] = ($res['ok'] ? '' : 'FAILED ') . 'text ' . sms_pretty($r['phone']);
        }
    }
    if ($sentTo) {
        thread_add((int)$q['id'], 'system', 'alert', 'Alerted: ' . implode(', ', $sentTo));
    }
}

/* ======================= Replies to the customer ======================= */

/** Whether the customer can be texted (they ticked the box or texted us, and have not opted out). */
function inquiry_can_text(array $q): bool
{
    return sms_enabled() && sms_e164((string)$q['phone']) !== '' && empty($q['sms_opted_out'])
        && (!empty($q['sms_opt_in']) || (int)db_val("SELECT COUNT(*) FROM messages WHERE inquiry_id=? AND direction='in' AND channel='sms'", [$q['id']]) > 0);
}

/**
 * Send a staff reply to the customer by 'email' or 'sms' and record it.
 * $by = ['user_id' => ?int, 'name' => string] (who answered). Returns [ok, error].
 */
function inquiry_reply(array $q, string $channel, string $text, array $by): array
{
    $text = trim($text);
    if ($text === '') return [false, 'Write a message first.'];
    $company = setting('company_name', 'Clean Beans LLC');
    if ($channel === 'email') {
        if (!filter_var((string)$q['email'], FILTER_VALIDATE_EMAIL)) return [false, 'This customer has no email address.'];
        $subject = 'Re: your ' . setting('brand_name', 'Clean Beans') . ' quote request';
        $sig = "\n\n" . ($by['name'] !== '' ? $by['name'] . "\n" : '') . $company . "\n" . setting('phone', '') . "\n" . abs_url();
        $ok = site_mail($q['email'], $subject, $text . $sig . "\n", ($by['name'] !== '' ? $by['name'] . ' at ' : '') . $company,
            inquiry_reply_address($q), inquiry_reply_address($q), ['X-CB-Inquiry' => (string)$q['id']]);
        thread_add((int)$q['id'], 'out', 'email', $text, $by['user_id'], ['author' => $by['name'], 'addr' => $q['email'], 'status' => $ok ? 'sent' : 'failed']);
        if ($ok && $q['status'] === 'new') db_exec("UPDATE inquiries SET status='contacted' WHERE id=?", [$q['id']]);
        return [$ok, $ok ? '' : 'The email was not accepted by the server. Check the mail log.'];
    }
    if ($channel === 'sms') {
        if (!inquiry_can_text($q)) return [false, 'This customer cannot be texted (no consent, opted out, or texts are off).'];
        $res = sms_send((string)$q['phone'], $text);
        thread_add((int)$q['id'], 'out', 'sms', $text, $by['user_id'], ['author' => $by['name'], 'addr' => sms_e164((string)$q['phone']),
            'status' => $res['ok'] ? 'sent' : 'failed', 'provider_id' => $res['id']]);
        if ($res['ok'] && $q['status'] === 'new') db_exec("UPDATE inquiries SET status='contacted' WHERE id=?", [$q['id']]);
        return [$res['ok'], $res['ok'] ? '' : 'The text was not sent: ' . $res['error']];
    }
    return [false, 'Unknown channel.'];
}

/* ======================= Inbound texts ======================= */

/**
 * Handle one inbound text to the business number.
 * From a staff phone: it is their reply to the customer ("#12 text" picks the conversation; otherwise
 * the last one relayed to them). "?" lists open conversations. From anyone else: customer message.
 */
function sms_inbound(array $in): void
{
    $from = $in['from'];
    $text = trim($in['text']);
    if ($from === '' || $text === '') return;

    $staff = staff_for_phone($from);
    if ($staff) {
        if ($text === '?' || strtoupper($text) === 'LIST') {
            $rows = db_all("SELECT id, name FROM inquiries WHERE status<>'closed' AND phone<>'' ORDER BY COALESCE(last_activity_at, created_at) DESC LIMIT 6");
            $cur = staff_current_inquiry($staff);
            $lines = array_map(fn($r) => '#' . $r['id'] . ' ' . $r['name'] . ((int)$r['id'] === $cur ? ' (current)' : ''), $rows);
            sms_send($from, "Open conversations:\n" . ($lines ? implode("\n", $lines) : '(none)') . "\nStart a reply with #number to pick one.");
            return;
        }
        $target = null;
        if (preg_match('/^#(\d{1,9})\s*(.*)$/s', $text, $m)) {
            $target = (int)$m[1];
            $text = trim($m[2]);
        } else {
            $target = staff_current_inquiry($staff);
        }
        $q = $target ? db_one('SELECT * FROM inquiries WHERE id=?', [$target]) : null;
        if (!$q) {
            sms_send($from, 'Which conversation? Start your text with #number (text ? for a list).');
            return;
        }
        staff_set_current_inquiry($staff, (int)$q['id']);
        if ($text === '') {
            sms_send($from, 'Now replying to ' . inquiry_label($q) . '. Your next text goes to them.');
            return;
        }
        [$ok, $err] = inquiry_reply($q, 'sms', $text, ['user_id' => $staff['user_id'], 'name' => $staff['name']]);
        if (!$ok) sms_send($from, 'Not sent to #' . $q['id'] . ': ' . $err);
        return;
    }

    // Customer
    $digits = substr(preg_replace('/\D/', '', $from), -10);
    $q = null;
    foreach (db_all("SELECT * FROM inquiries WHERE phone<>'' ORDER BY COALESCE(last_activity_at, created_at) DESC LIMIT 500") as $row) {
        if (substr(phone_digits((string)$row['phone']), -10) === $digits) { $q = $row; break; }
    }
    $kw = sms_keyword($text);
    if ($kw === 'stop' || $kw === 'start') {
        if ($q) {
            db_exec('UPDATE inquiries SET sms_opted_out=? WHERE id=?', [$kw === 'stop' ? 1 : 0, $q['id']]);
            thread_add((int)$q['id'], 'system', 'sms', $kw === 'stop' ? 'Customer texted STOP: no more texts will be sent.' : 'Customer texted START: texts allowed again.');
        }
        return;   // the provider sends the required confirmation itself
    }
    if ($kw === 'help') {
        sms_send($from, setting('company_name', 'Clean Beans LLC') . ': call or text ' . setting('phone', '') . ' or visit ' . abs_url() . '. Msg&data rates may apply. Reply STOP to opt out.');
        if (!$q) return;
    }
    $isNew = false;
    if (!$q || ($q['status'] === 'closed' && strtotime((string)($q['last_activity_at'] ?: $q['created_at'])) < time() - 30 * 86400)) {
        $now = now();
        $newId = db_insert('inquiries', ['kind' => 'sms', 'name' => 'Text from ' . sms_pretty($from), 'phone' => sms_pretty($from), 'email' => '',
            'location' => '', 'service_type' => '', 'message' => $text, 'status' => 'new', 'admin_notes' => '', 'ip' => '', 'user_agent' => 'sms',
            'sms_opt_in' => 1, 'sms_opted_out' => 0, 'created_at' => $now, 'updated_at' => $now, 'last_activity_at' => $now]);
        $q = db_one('SELECT * FROM inquiries WHERE id=?', [$newId]);
        $isNew = true;
    } elseif ($q['status'] === 'closed') {
        db_exec("UPDATE inquiries SET status='new' WHERE id=?", [$q['id']]);
    }
    thread_add((int)$q['id'], 'in', 'sms', $text, null, ['author' => $q['name'], 'addr' => $from, 'status' => 'received', 'provider_id' => $in['id']]);
    inquiry_alert((int)$q['id'], $isNew ? 'new' : 'reply', $text, 'sms');
}

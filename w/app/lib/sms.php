<?php
/**
 * Text messages (SMS), provider-neutral.
 *
 * Providers: Twilio, Telnyx, or "log" (test mode: nothing is sent, each message is written to err/sms.log).
 * Settings (admin > Settings > Text messages, hostmaster only):
 *   sms_enabled      1 = on
 *   sms_provider     twilio | telnyx | log
 *   sms_from         the business number, e.g. +17155550123 (E.164)
 *   sms_twilio_sid, sms_twilio_token                 (Twilio)
 *   sms_telnyx_key, sms_telnyx_public_key, sms_telnyx_profile   (Telnyx; profile id optional)
 *   sms_webhook_key  random secret in the inbound webhook URL (set automatically)
 *
 * Inbound texts arrive at  <site>/sms/inbound/<sms_webhook_key>  and are checked with the
 * provider's signature (Twilio X-Twilio-Signature, Telnyx ed25519) before anything is stored.
 */
declare(strict_types=1);

const SMS_PROVIDERS = ['twilio' => 'Twilio', 'telnyx' => 'Telnyx', 'log' => 'Test mode (log only, nothing is sent)'];
const SMS_MAX_LEN = 1500;   // ~10 segments; longer bodies are trimmed

function sms_provider(): string
{
    $p = setting('sms_provider', 'log');
    return isset(SMS_PROVIDERS[$p]) ? $p : 'log';
}

/** True when texts can be sent (switched on and the chosen provider has what it needs). */
function sms_enabled(): bool
{
    if (setting('sms_enabled', '0') !== '1' || sms_e164(setting('sms_from')) === '') return false;
    return match (sms_provider()) {
        'twilio' => setting('sms_twilio_sid') !== '' && setting('sms_twilio_token') !== '',
        'telnyx' => setting('sms_telnyx_key') !== '',
        default  => true,
    };
}

/** US/Canada numbers to E.164 (+1XXXXXXXXXX). Numbers already starting with + are kept. '' if not usable. */
function sms_e164(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') return '';
    if (str_starts_with($phone, '+')) {
        $d = preg_replace('/\D/', '', $phone);
        return strlen($d) >= 8 && strlen($d) <= 15 ? '+' . $d : '';
    }
    $d = preg_replace('/\D/', '', $phone);
    if (strlen($d) === 10) return '+1' . $d;
    if (strlen($d) === 11 && $d[0] === '1') return '+' . $d;
    return '';
}

/** (715) 222-2444 style display for +1 numbers. */
function sms_pretty(string $e164): string
{
    if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $m)) return "($m[1]) $m[2]-$m[3]";
    return $e164;
}

/**
 * Send one text. Returns ['ok' => bool, 'id' => provider message id, 'error' => string].
 * Never throws; failures are logged to err/sms.log.
 */
function sms_send(string $to, string $body): array
{
    $to = sms_e164($to);
    $from = sms_e164(setting('sms_from'));
    $body = mb_substr(trim($body), 0, SMS_MAX_LEN);
    if ($to === '' || $body === '') return ['ok' => false, 'id' => '', 'error' => 'missing number or text'];
    if (!sms_enabled()) return ['ok' => false, 'id' => '', 'error' => 'text messages are turned off'];

    $res = match (sms_provider()) {
        'twilio' => sms_http('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(setting('sms_twilio_sid')) . '/Messages.json',
            http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]),
            ['Content-Type: application/x-www-form-urlencoded',
             'Authorization: Basic ' . base64_encode(setting('sms_twilio_sid') . ':' . setting('sms_twilio_token'))]),
        'telnyx' => sms_http('https://api.telnyx.com/v2/messages',
            (string)json_encode(array_filter(['from' => $from, 'to' => $to, 'text' => $body,
                'messaging_profile_id' => setting('sms_telnyx_profile') ?: null])),
            ['Content-Type: application/json', 'Authorization: Bearer ' . setting('sms_telnyx_key')]),
        default => ['code' => 200, 'body' => json_encode(['sid' => 'log-' . bin2hex(random_bytes(6))])],
    };
    $j = json_decode((string)$res['body'], true) ?: [];
    $ok = $res['code'] >= 200 && $res['code'] < 300;
    $id = (string)($j['sid'] ?? ($j['data']['id'] ?? ''));
    $error = $ok ? '' : (string)($j['message'] ?? ($j['errors'][0]['detail'] ?? ('HTTP ' . $res['code'])));
    sms_log(($ok ? 'sent' : 'FAILED') . ' via ' . sms_provider() . " to $to" . ($error ? " ($error)" : '') . ': ' . str_replace("\n", ' / ', mb_strimwidth($body, 0, 160, '…')));
    return ['ok' => $ok, 'id' => $id, 'error' => $error];
}

function sms_http(string $url, string $payload, array $headers): array
{
    if (!function_exists('curl_init')) return ['code' => 0, 'body' => '{"message":"curl is not available on the server"}'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false) $body = json_encode(['message' => 'network error: ' . curl_error($ch)]);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$body];
}

function sms_log(string $line): void
{
    @file_put_contents(APP_ROOT . '/err/sms.log', date('c') . ' ' . $line . "\n", FILE_APPEND);
}

/** The secret part of the inbound webhook URL; created on first use. */
function sms_webhook_key(): string
{
    $k = setting('sms_webhook_key');
    if (!preg_match('/^[a-f0-9]{32}$/', $k)) {
        $k = bin2hex(random_bytes(16));
        setting_save('sms_webhook_key', $k);
    }
    return $k;
}

function sms_webhook_url(): string
{
    return abs_url('sms/inbound/' . sms_webhook_key());
}

/**
 * Check that an inbound webhook really came from the provider.
 * $raw = raw request body. Test mode accepts anything that knows the URL secret.
 */
function sms_webhook_verified(string $raw): bool
{
    switch (sms_provider()) {
        case 'twilio':
            $sig = (string)($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
            if ($sig === '' || setting('sms_twilio_token') === '') return false;
            $params = $_POST;
            ksort($params, SORT_STRING);
            $data = sms_webhook_url();
            foreach ($params as $k => $v) $data .= $k . (is_array($v) ? implode('', $v) : $v);
            $calc = base64_encode(hash_hmac('sha1', $data, setting('sms_twilio_token'), true));
            return hash_equals($calc, $sig);
        case 'telnyx':
            $sig = base64_decode((string)($_SERVER['HTTP_TELNYX_SIGNATURE_ED25519'] ?? ''), true);
            $ts = (string)($_SERVER['HTTP_TELNYX_TIMESTAMP'] ?? '');
            $pub = base64_decode(setting('sms_telnyx_public_key'), true);
            if (!$sig || $ts === '' || !$pub || !function_exists('sodium_crypto_sign_verify_detached')) return false;
            if (abs(time() - (int)$ts) > 300) return false;
            try {
                return sodium_crypto_sign_verify_detached($sig, $ts . '|' . $raw, $pub);
            } catch (Throwable $e) {
                return false;
            }
        default:
            return true;
    }
}

/** Inbound text as ['from' => E.164, 'to' => E.164, 'text' => string, 'id' => string], or null if not a received message. */
function sms_parse_inbound(string $raw): ?array
{
    if (sms_provider() === 'telnyx') {
        $j = json_decode($raw, true);
        $p = $j['data']['payload'] ?? null;
        if (($j['data']['event_type'] ?? '') !== 'message.received' || !$p) return null;
        return ['from' => sms_e164((string)($p['from']['phone_number'] ?? '')), 'to' => sms_e164((string)($p['to'][0]['phone_number'] ?? '')),
                'text' => (string)($p['text'] ?? ''), 'id' => (string)($p['id'] ?? '')];
    }
    // Twilio and test mode post form fields
    if (!isset($_POST['From'])) return null;
    return ['from' => sms_e164((string)$_POST['From']), 'to' => sms_e164((string)($_POST['To'] ?? '')),
            'text' => (string)($_POST['Body'] ?? ''), 'id' => (string)($_POST['MessageSid'] ?? '')];
}

/** What the provider expects back from the webhook (no auto-reply; replies are sent through the API). */
function sms_webhook_respond(): void
{
    if (sms_provider() === 'twilio') {
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    } else {
        header('Content-Type: application/json');
        echo '{"ok":true}';
    }
}

/** Opt-out / opt-in keywords carriers require (the provider also enforces STOP itself). */
function sms_keyword(string $text): string
{
    $w = strtoupper(trim(preg_replace('/[^a-z]/i', '', $text)));
    if (in_array($w, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'REVOKE'], true)) return 'stop';
    if (in_array($w, ['START', 'UNSTOP', 'OPTIN'], true)) return 'start';   // not "YES": customers answer questions with it
    if (in_array($w, ['HELP', 'INFO'], true)) return 'help';
    return '';
}

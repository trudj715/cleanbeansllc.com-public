<?php
/**
 * TOTP (RFC 6238) - works with Google Authenticator, Microsoft Authenticator, Authy, 1Password, etc.
 */
declare(strict_types=1);

function totp_new_secret(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = '';
    foreach (str_split(random_bytes(20)) as $b) {
        $s .= $alphabet[ord($b) & 31];
    }
    return $s; // 32 base32 chars = 160 bits
}

function base32_decode_rfc(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $out .= chr(bindec($byte));
    }
    return $out;
}

function totp_code(string $secret, int $step): string
{
    $key = base32_decode_rfc($secret);
    $h = hash_hmac('sha1', pack('J', $step), $key, true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Returns the matching time step (>0) or 0. Allows +/-1 step (30s) of clock drift,
 * and rejects any step at or before $lastStep so a code cannot be replayed.
 */
function totp_verify(string $secret, string $code, int $lastStep = 0): int
{
    if ($secret === '' || !preg_match('/^\d{6}$/', $code)) return 0;
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) {
        $step = $now + $d;
        if ($step > $lastStep && hash_equals(totp_code($secret, $step), $code)) {
            return $step;
        }
    }
    return 0;
}

function totp_uri(string $secret, string $email): string
{
    $issuer = 'Clean Beans Website';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

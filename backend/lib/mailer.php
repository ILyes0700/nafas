<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth_config.php';
/**
 * Mailer with native SMTP (no external library needed).
 * MAIL_MODE:
 *   'smtp' : real email via SMTP (Gmail supported: smtp.gmail.com:587 TLS + App Password)
 *   'log'  : writes a .eml file under backend/logs/mail/ (offline dev)
 *   'php'  : PHP mail()
 * Returns true on success.
 */
function mailer_send(string $to, string $subject, string $htmlBody, string $textBody = '', array $attachments = []): bool
{
    if ($textBody === '') {
        $textBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));
    }
    switch (MAIL_MODE) {
        case 'smtp':
            $ok = mailer_send_smtp($to, $subject, $htmlBody, $textBody, $attachments);
            if (!$ok) { mailer_send_log($to, $subject, $htmlBody, $textBody); } // keep a copy on failure
            return $ok;
        case 'php':
            return mailer_send_php($to, $subject, $htmlBody);
        case 'log':
        default:
            return mailer_send_log($to, $subject, $htmlBody, $textBody);
    }
}

function mailer_send_log(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    $dir = __DIR__ . '/../logs/mail';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]+/i', '_', $to) . '.eml';
    $content = "To: $to\r\n"
        . 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n"
        . "Subject: $subject\r\n"
        . "Content-Type: text/html; charset=utf-8\r\n\r\n"
        . $htmlBody . "\r\n\r\n---- TEXT ----\r\n" . $textBody . "\r\n";
    return @file_put_contents($file, $content) !== false;
}

function mailer_send_php(string $to, string $subject, string $htmlBody): bool
{
    $headers = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-type: text/html; charset=utf-8' . "\r\n"
        . 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
    return @mail($to, $subject, $htmlBody, $headers);
}

/** Read one SMTP reply (handles multi-line replies). Returns the numeric code. */
function _smtp_read($fp, ?string &$full = null): int
{
    $full = '';
    while (($line = fgets($fp, 515)) !== false) {
        $full .= $line;
        // Lines like "250-..." continue; "250 ..." (space) ends the reply.
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return (int)substr(ltrim($full), 0, 3);
}

/** Send a command and check the expected reply code. */
function _smtp_cmd($fp, string $cmd, int $expect, ?string &$err): bool
{
    fwrite($fp, $cmd . "\r\n");
    $full = '';
    $code = _smtp_read($fp, $full);
    if ($code !== $expect) {
        $err = 'SMTP: expected ' . $expect . ', got ' . trim($full) . ' (after ' . substr($cmd, 0, 12) . ')';
        return false;
    }
    return true;
}

/**
 * Native SMTP sender. Supports STARTTLS (587) and implicit SSL (465).
 * Uses AUTH LOGIN with SMTP_USER / SMTP_PASS (Gmail App Password).
 */
function mailer_send_smtp(string $to, string $subject, string $htmlBody, string $textBody, array $attachments = []): bool
{
    $err = null;
    $host   = SMTP_HOST;
    $port   = (int)SMTP_PORT;
    $secure = strtolower((string)SMTP_SECURE); // 'tls' or 'ssl'

    $transport = ($secure === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
    ]);

    $fp = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        error_log('[mailer] SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }
    stream_set_timeout($fp, 20);

    try {
        if (_smtp_read($fp) !== 220) { throw new RuntimeException('No 220 greeting'); }

        $ehloHost = 'localhost';
        if (!_smtp_cmd($fp, 'EHLO ' . $ehloHost, 250, $err)) throw new RuntimeException($err);

        if ($secure === 'tls') {
            if (!_smtp_cmd($fp, 'STARTTLS', 220, $err)) throw new RuntimeException($err);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new RuntimeException('STARTTLS handshake failed');
            }
            if (!_smtp_cmd($fp, 'EHLO ' . $ehloHost, 250, $err)) throw new RuntimeException($err);
        }

        // AUTH LOGIN
        if (!_smtp_cmd($fp, 'AUTH LOGIN', 334, $err)) throw new RuntimeException($err);
        if (!_smtp_cmd($fp, base64_encode(SMTP_USER), 334, $err)) throw new RuntimeException($err);
        if (!_smtp_cmd($fp, base64_encode(SMTP_PASS), 235, $err)) throw new RuntimeException('Auth rejected (check Gmail App Password): ' . $err);

        $from = MAIL_FROM ?: SMTP_USER;
        if (!_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', 250, $err)) throw new RuntimeException($err);
        if (!_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', 250, $err)) {
            // Some servers reply 251 for forwarded recipients.
            throw new RuntimeException($err);
        }
        if (!_smtp_cmd($fp, 'DATA', 354, $err)) throw new RuntimeException($err);

        // Build the alternative (plain + html) part.
        $altB = 'alt_' . bin2hex(random_bytes(8));
        $alt  = '--' . $altB . "\r\n";
        $alt .= "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $alt .= chunk_split(base64_encode($textBody)) . "\r\n";
        $alt .= '--' . $altB . "\r\n";
        $alt .= "Content-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $alt .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $alt .= '--' . $altB . "--\r\n";

        // Keep only readable attachments under a sane size.
        $valid = [];
        foreach ($attachments as $att) {
            $path = (string)($att['path'] ?? '');
            if ($path === '' || !is_file($path) || !is_readable($path)) continue;
            if ((int)@filesize($path) > 15 * 1024 * 1024) continue; // 15 MB cap per file
            $valid[] = $att;
        }

        $headers  = 'From: ' . _smtp_encode_name(MAIL_FROM_NAME) . ' <' . $from . ">\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
        $headers .= 'Subject: ' . _smtp_encode_header($subject) . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Date: ' . date('r') . "\r\n";

        if (empty($valid)) {
            $headers .= 'Content-Type: multipart/alternative; boundary="' . $altB . '"' . "\r\n";
            $body = $alt;
        } else {
            $mixB = 'mix_' . bin2hex(random_bytes(8));
            $headers .= 'Content-Type: multipart/mixed; boundary="' . $mixB . '"' . "\r\n";
            $body  = '--' . $mixB . "\r\n";
            $body .= 'Content-Type: multipart/alternative; boundary="' . $altB . '"' . "\r\n\r\n";
            $body .= $alt . "\r\n";
            foreach ($valid as $att) {
                $path = (string)$att['path'];
                $name = _smtp_encode_header((string)($att['name'] ?? basename($path)));
                $mime = (string)($att['mime'] ?? 'application/octet-stream');
                $cid  = (string)($att['cid'] ?? '');
                $disp = ($cid !== '') ? 'inline' : 'attachment';
                $body .= '--' . $mixB . "\r\n";
                $body .= 'Content-Type: ' . $mime . '; name="' . $name . '"' . "\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= 'Content-Disposition: ' . $disp . '; filename="' . $name . '"' . "\r\n";
                if ($cid !== '') $body .= 'Content-ID: <' . $cid . ">\r\n";
                $body .= "\r\n";
                $body .= chunk_split(base64_encode((string)@file_get_contents($path))) . "\r\n";
            }
            $body .= '--' . $mixB . "--\r\n";
        }

        // Dot-stuffing: lines starting with '.' must be escaped.
        $data = $headers . "\r\n" . $body;
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($fp, $data . "\r\n.\r\n");
        $full = '';
        if (_smtp_read($fp, $full) !== 250) { throw new RuntimeException('Message not accepted: ' . trim($full)); }

        _smtp_cmd($fp, 'QUIT', 221, $err); // best-effort
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('[mailer] SMTP failed: ' . $e->getMessage());
        if (is_resource($fp)) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); }
        return false;
    }
}

function _smtp_encode_header(string $s): string
{
    return preg_match('/[\x80-\xFF]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}
function _smtp_encode_name(string $s): string
{
    return _smtp_encode_header($s);
}

/** Build [subject, htmlBody] for an OTP email (professional branded template). */
function mailer_otp_email(string $code, string $purposeLabel): array
{
    $app     = htmlspecialchars(APP_NAME);
    $subject = APP_NAME . ' — رمز التحقق: ' . $code;
    $ttlMin  = (int)round(AUTH_OTP_TTL_SECONDS / 60);
    $codeEsc = htmlspecialchars($code);
    $purpose = htmlspecialchars($purposeLabel);
    $year    = date('Y');
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef3fa;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3fa;padding:28px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6e3da;font-family:Segoe UI,Tahoma,Arial,sans-serif;">
        <tr><td style="background:#0d3b66;background:linear-gradient(135deg,#0d3b66,#1d4e89);padding:26px 28px;" align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
            <td style="width:46px;height:46px;background:#ffffff;border-radius:50%;text-align:center;vertical-align:middle;font-size:24px;line-height:46px;">&#127811;</td>
            <td style="padding-right:12px;padding-left:12px;color:#ffffff;font-size:22px;font-weight:800;">$app</td>
          </tr></table>
          <div style="color:#cfe0f5;font-size:12px;margin-top:10px;">نظام مراقبة جودة الهواء والصحة — قابس</div>
        </td></tr>
        <tr><td style="padding:30px 28px 8px;">
          <h1 style="margin:0 0 6px;font-size:19px;color:#0d3b66;">مرحباً &#128075;</h1>
          <p style="margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.8;">استخدم الرمز التالي لإتمام <b>$purpose</b>. هذا الرمز صالح لمرة واحدة فقط.</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
            <td align="center" style="background:#f8f7f2;border:1px dashed #1d4e89;border-radius:12px;padding:20px;">
              <div style="font-size:34px;font-weight:800;letter-spacing:10px;color:#0d3b66;">$codeEsc</div>
            </td>
          </tr></table>
          <p style="margin:18px 0 0;font-size:12.5px;color:#6b7280;line-height:1.8;">&#9201;&#65039; ينتهي هذا الرمز خلال $ttlMin دقيقة. إن لم تطلبه، تجاهل هذه الرسالة بأمان.</p>
        </td></tr>
        <tr><td style="padding:22px 28px 0;"><div style="border-top:1px solid #eef0f2;"></div></td></tr>
        <tr><td style="padding:16px 28px 26px;" align="center">
          <div style="font-size:12px;color:#9aa3af;line-height:1.7;">&#169; $year $app — جميع الحقوق محفوظة<br>هذه رسالة آلية، الرجاء عدم الرد عليها.</div>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    return [_mail_u($subject), _mail_u($html)];
}

/* =====================================================================
 * Feature emails: admin welcome (temp credentials) + event notifications.
 * ===================================================================== */

/** Decode literal backslash-u escapes (PHP heredoc does not process them). */
function _mail_u(string $s): string
{
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static function (array $m): string {
        return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE');
    }, $s);
}

/** Build [subject, html] welcome email with temporary credentials for a new Admin. */
function mailer_admin_welcome_email(string $email, string $tempPassword, string $fullName): array
{
    $app   = htmlspecialchars(APP_NAME);
    $name  = htmlspecialchars($fullName !== '' ? $fullName : 'Admin');
    $mail  = htmlspecialchars($email);
    $pwd   = htmlspecialchars($tempPassword);
    $login = htmlspecialchars(rtrim(APP_BASE_URL, '/') . '/login.php');
    $year  = date('Y');
    $subject = APP_NAME . ' \u2014 \u062a\u0645 \u062a\u0641\u0639\u064a\u0644 \u062d\u0633\u0627\u0628\u0643 \u0627\u0644\u0625\u062f\u0627\u0631\u064a';
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef3fa;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3fa;padding:28px 12px;"><tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e6e3da;font-family:Segoe UI,Tahoma,Arial,sans-serif;">
      <tr><td style="background:#0d3b66;background:linear-gradient(135deg,#0d3b66,#1d4e89);padding:26px 28px;" align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
          <td style="width:46px;height:46px;background:#fff;border-radius:50%;text-align:center;vertical-align:middle;font-size:24px;line-height:46px;">&#127811;</td>
          <td style="padding-right:12px;padding-left:12px;color:#fff;font-size:22px;font-weight:800;">$app</td>
        </tr></table>
      </td></tr>
      <tr><td style="padding:30px 28px 8px;">
        <h1 style="margin:0 0 6px;font-size:19px;color:#0d3b66;">\u0645\u0631\u062d\u0628\u0627\u064b $name &#128075;</h1>
        <p style="margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.8;">\u062a\u0645 \u062a\u0641\u0639\u064a\u0644 \u062d\u0633\u0627\u0628 <b>\u0645\u0633\u0624\u0648\u0644 (Admin)</b> \u062e\u0627\u0635 \u0628\u0643 \u0641\u064a \u0645\u0646\u0635\u0629 $app. \u0625\u0644\u064a\u0643 \u0628\u064a\u0627\u0646\u0627\u062a \u0627\u0644\u062f\u062e\u0648\u0644:</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7f2;border:1px solid #e6e3da;border-radius:12px;">
          <tr><td style="padding:14px 16px;border-bottom:1px solid #eef0f2;font-size:14px;color:#6b7280;">\u0627\u0644\u0628\u0631\u064a\u062f</td><td style="padding:14px 16px;border-bottom:1px solid #eef0f2;font-size:14px;color:#111827;font-weight:700;" dir="ltr">$mail</td></tr>
          <tr><td style="padding:14px 16px;font-size:14px;color:#6b7280;">\u0643\u0644\u0645\u0629 \u0627\u0644\u0645\u0631\u0648\u0631 \u0627\u0644\u0645\u0624\u0642\u062a\u0629</td><td style="padding:14px 16px;font-size:18px;color:#0d3b66;font-weight:800;letter-spacing:2px;" dir="ltr">$pwd</td></tr>
        </table>
        <p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.8;">&#128273; \u0644\u0623\u0645\u0627\u0646\u0643\u060c \u0633\u064a\u064f\u0637\u0644\u0628 \u0645\u0646\u0643 <b>\u062a\u063a\u064a\u064a\u0631 \u0643\u0644\u0645\u0629 \u0627\u0644\u0645\u0631\u0648\u0631 \u0641\u0648\u0631 \u0623\u0648\u0644 \u062a\u0633\u062c\u064a\u0644 \u062f\u062e\u0648\u0644</b>.</p>
      </td></tr>
      <tr><td style="padding:20px 28px 28px;" align="center">
        <a href="$login" style="display:inline-block;background:#0d3b66;color:#fff;text-decoration:none;padding:12px 28px;border-radius:10px;font-weight:700;">\u062a\u0633\u062c\u064a\u0644 \u0627\u0644\u062f\u062e\u0648\u0644 &#8592;</a>
      </td></tr>
      <tr><td style="padding:0 28px 26px;" align="center"><div style="font-size:12px;color:#9aa3af;">&#169; $year $app</div></td></tr>
    </table>
  </td></tr></table>
</body></html>
HTML;
    return [_mail_u($subject), _mail_u($html)];
}

/** Build [subject, html] internal notification email about an account event. */
function mailer_event_notify_email(string $title, array $rows): array
{
    $app  = htmlspecialchars(APP_NAME);
    $ttl  = htmlspecialchars($title);
    $year = date('Y');
    $when = htmlspecialchars(date('Y-m-d H:i'));
    $tr = '';
    foreach ($rows as $k => $v) {
        $tr .= '<tr><td style="padding:9px 14px;border-bottom:1px solid #eef0f2;color:#6b7280;font-size:13px;white-space:nowrap;">' . htmlspecialchars((string)$k) . '</td><td style="padding:9px 14px;border-bottom:1px solid #eef0f2;color:#111827;font-size:13px;font-weight:600;">' . htmlspecialchars((string)$v) . '</td></tr>';
    }
    $subject = APP_NAME . ' \u2014 ' . $title;
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#eef3fa;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3fa;padding:28px 12px;"><tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e6e3da;font-family:Segoe UI,Tahoma,Arial,sans-serif;">
      <tr><td style="background:#0d3b66;background:linear-gradient(135deg,#0d3b66,#1d4e89);padding:20px 26px;color:#fff;">
        <div style="font-size:16px;font-weight:800;">&#128276; $ttl</div>
        <div style="font-size:12px;color:#cfe0f5;margin-top:3px;">$app \u2014 \u0625\u0634\u0639\u0627\u0631 \u0625\u062f\u0627\u0631\u064a &#8226; $when</div>
      </td></tr>
      <tr><td style="padding:16px 26px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6e3da;border-radius:12px;overflow:hidden;">$tr</table>
      </td></tr>
      <tr><td style="padding:14px 26px 24px;" align="center"><div style="font-size:12px;color:#9aa3af;">&#169; $year $app</div></td></tr>
    </table>
  </td></tr></table>
</body></html>
HTML;
    return [_mail_u($subject), _mail_u($html)];
}

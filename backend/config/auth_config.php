<?php
declare(strict_types=1);
/**
 * Gabes Tatenafas - Authentication configuration.
 * Central place for all auth tunables. Do not hard-code secrets elsewhere.
 */

/* --- OTP / verification codes --- */
const AUTH_OTP_LENGTH           = 6;      // number of digits
const AUTH_OTP_TTL_SECONDS      = 600;    // code lifetime (10 min) - configurable
const AUTH_OTP_MAX_ATTEMPTS     = 5;      // wrong tries before a code is burned
const AUTH_OTP_RESEND_COOLDOWN  = 60;     // seconds between resend requests

/* --- Rate limiting (max occurrences per window in seconds) --- */
const AUTH_RL_REGISTER_MAX      = 20;
const AUTH_RL_REGISTER_WINDOW   = 3600;
const AUTH_RL_LOGIN_MAX         = 10;
const AUTH_RL_LOGIN_WINDOW      = 900;
const AUTH_RL_OTP_SEND_MAX      = 5;
const AUTH_RL_OTP_SEND_WINDOW   = 3600;
const AUTH_RL_OTP_VERIFY_MAX    = 12;
const AUTH_RL_OTP_VERIFY_WINDOW = 900;
const AUTH_RL_RESET_MAX         = 5;
const AUTH_RL_RESET_WINDOW      = 3600;

/* --- Account lockout (brute-force protection) --- */
const AUTH_MAX_FAILED_LOGINS    = 8;
const AUTH_LOCKOUT_SECONDS      = 900;    // 15 min

/* --- Password policy --- */
const AUTH_PWD_MIN_LEN          = 8;

/* --- Application --- */
const APP_NAME                  = 'Nafass - Gabes';
// Base URL of the frontend folder (used in emails). Adjust to your install path.
const APP_BASE_URL              = 'http://localhost/gabes-tatenafas-v2/frontend';

/* --- Internal notification inbox ---
 * All account events (create / role change / delete / new registration)
 * are also emailed to this address. */
const APP_NOTIFY_EMAIL          = 'nafass.gabes@gmail.com';

/* --- Mail transport ---
 * 'log'  = write the email to backend/logs/mail/*.eml (no mail server needed; ideal for WAMP/XAMPP dev)
 * 'php'  = use PHP mail() (needs a configured MTA)
 * 'smtp' = use SMTP via PHPMailer (place PHPMailer under backend/lib/vendor/)
 */
const MAIL_MODE      = 'smtp';

/* --- LOCAL DEV: show the OTP code on screen (no email server needed) ---
 * Keep TRUE for local XAMPP/Electron use so registration + reset work without email.
 * Set to FALSE in real production (with MAIL_MODE='smtp'). */
const AUTH_DEV_SHOW_OTP = false;
const MAIL_FROM      = ''; // leave empty -> uses your Gmail (SMTP_USER) as sender
const MAIL_FROM_NAME = 'Nafass Gabes';
/* SMTP settings (only used when MAIL_MODE = 'smtp') */
// >>> GMAIL: put your Gmail address in SMTP_USER and a 16-char App Password in SMTP_PASS <<<
const SMTP_HOST   = 'smtp.gmail.com';
const SMTP_PORT   = 587;
const SMTP_USER   = 'nafass.gabes@gmail.com';
const SMTP_PASS   = 'feiksemexyjdmmpy';
const SMTP_SECURE = 'tls'; // 'tls' or 'ssl'

/* --- Super Admin seed (used by backend/scripts/seed_super_admin.php) --- */
const SUPER_ADMIN_EMAIL    = 'naily@gmail.com';
const SUPER_ADMIN_PASSWORD = 'naily2026'; // CHANGE after first login
const SUPER_ADMIN_NAME     = 'Super Administrator';
const SUPER_ADMIN_PHONE    = '+21600000000';

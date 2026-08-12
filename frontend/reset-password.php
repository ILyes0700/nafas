<?php
require_once __DIR__ . '/../backend/lib/auth.php';
auth_start();

$error = ''; $notice = '';
$email = strtolower((string)($_SESSION['reset_email'] ?? ''));
$stage = !empty($_SESSION['pwd_reset_grant']) ? 'set' : 'code';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } elseif (($_POST['stage'] ?? '') === 'code') {
        $res = auth_verify_reset($email, (string)($_POST['code'] ?? ''));
        if ($res['ok']) { $stage = 'set'; $notice = 'Code verified. Choose a new password.'; }
        else { $error = $res['error']; $stage = 'code'; }
    } elseif (($_POST['stage'] ?? '') === 'set') {
        $res = auth_reset_password((string)($_POST['password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
        if ($res['ok']) {
            unset($_SESSION['reset_email']);
            $_SESSION['flash_login'] = 'Password updated. You can now log in.';
            header('Location: login.php');
            exit;
        }
        $error = $res['error'];
        $stage = !empty($_SESSION['pwd_reset_grant']) ? 'set' : 'code';
    }
}

if ($email === '') { header('Location: forgot-password.php'); exit; }
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password - Nafass . Gabes</title>
  <link rel="stylesheet" href="styles/auth.css">
  <link rel="shortcut icon" href="assets/favicon.ico">
  <meta name="theme-color" content="#0d3b66">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="brand">
      <div class="logo">N</div>
      <div>
        <h1>Reset Password</h1>
        <div class="subtitle"><?= htmlspecialchars($email) ?></div>
      </div>
    </div>

    <?php if ($error): ?><div class="auth-error">&#9888; <?= htmlspecialchars($error) ?></div><?php endif ?>
    <?php if ($notice): ?><div class="auth-notice">&#10003; <?= htmlspecialchars($notice) ?></div><?php endif ?>

    <?php if (!empty($_SESSION['dev_last_otp']) && defined('AUTH_DEV_SHOW_OTP') && AUTH_DEV_SHOW_OTP): ?>
      <div class="auth-notice" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa">
        🔑 <b>DEV mode</b> — your reset code is:
        <span style="font-size:20px;font-weight:800;letter-spacing:4px"><?= htmlspecialchars($_SESSION['dev_last_otp']) ?></span>
      </div>
    <?php endif ?>

    <?php if ($stage === 'code'): ?>
    <form method="post" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="stage" value="code">
      <div class="field">
        <label>Reset Code</label>
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus class="otp-input" placeholder="000000">
      </div>
      <button type="submit" class="auth-btn">Verify Code</button>
    </form>
    <?php else: ?>
    <form method="post" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="stage" value="set">
      <div class="field">
        <label>New Password</label>
        <input type="password" name="password" required minlength="8" autofocus placeholder="Min 8 chars">
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required minlength="8" placeholder="Repeat password">
      </div>
      <div class="auth-hint">Password must be 8+ characters with an uppercase letter, a lowercase letter, and a digit.</div>
      <button type="submit" class="auth-btn">Update Password</button>
    </form>
    <?php endif ?>

    <div class="auth-footer"><a href="login.php">Back to login</a></div>
  </div>
</body>
</html>

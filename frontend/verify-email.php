<?php
require_once __DIR__ . '/../backend/lib/auth.php';
auth_start();

$uid = (int)($_GET['uid'] ?? ($_SESSION['pending_verify_uid'] ?? 0));
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } elseif (($_POST['do'] ?? '') === 'resend') {
        $res = auth_resend_email_otp($uid);
        if ($res['ok']) { $notice = 'A new code has been sent to your email.'; }
        else { $error = $res['error']; }
    } else {
        $res = auth_verify_email($uid, (string)($_POST['code'] ?? ''));
        if ($res['ok']) {
            unset($_SESSION['pending_verify_uid']);
            header('Location: index.php'); // auto-logged-in -> role-based dashboard
            exit;
        }
        $error = $res['error'];
    }
}

if ($uid <= 0) { header('Location: register.php'); exit; }
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify Email - Nafass . Gabes</title>
  <link rel="stylesheet" href="styles/auth.css">
  <link rel="shortcut icon" href="assets/favicon.ico">
  <meta name="theme-color" content="#0d3b66">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="brand">
      <div class="logo">N</div>
      <div>
        <h1>Verify Your Email</h1>
        <div class="subtitle">Enter the code we sent you</div>
      </div>
    </div>

    <?php if ($error): ?><div class="auth-error">&#9888; <?= htmlspecialchars($error) ?></div><?php endif ?>
    <?php if ($notice): ?><div class="auth-notice">&#10003; <?= htmlspecialchars($notice) ?></div><?php endif ?>

    <?php if (!empty($_SESSION['dev_last_otp']) && defined('AUTH_DEV_SHOW_OTP') && AUTH_DEV_SHOW_OTP): ?>
      <div class="auth-notice" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa">
        🔑 <b>DEV mode</b> — your verification code is:
        <span style="font-size:20px;font-weight:800;letter-spacing:4px"><?= htmlspecialchars($_SESSION['dev_last_otp']) ?></span>
      </div>
    <?php endif ?>

    <form method="post" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field">
        <label>Verification Code</label>
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus class="otp-input" placeholder="000000">
      </div>
      <button type="submit" class="auth-btn">Verify &amp; Continue</button>
    </form>

    <form method="post" class="auth-form" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="do" value="resend">
      <button type="submit" class="auth-btn auth-btn-ghost">Resend Code</button>
    </form>

    <div class="auth-footer"><a href="login.php">Back to login</a></div>
  </div>
</body>
</html>

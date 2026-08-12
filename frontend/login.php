<?php
require_once __DIR__ . '/../backend/lib/auth.php';
auth_start();

$error = '';
$needVerifyUid = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $res = auth_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if (is_array($res) && isset($res['id'])) {
        header('Location: index.php');
        exit;
    }
    if (is_array($res) && ($res['error'] ?? '') === 'unverified') {
        header('Location: verify-email.php?uid=' . (int)($res['user_id'] ?? 0));
        exit;
    }
    if (is_array($res) && ($res['error'] ?? '') === 'inactive') {
        $error = 'Your account is not active. Please contact an administrator.';
    } else {
        $error = 'Invalid email or password.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Session expired. Please try again.';
}

if (auth_user()) { header('Location: index.php'); exit; }
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Nafass . Gabes</title>
  <link rel="stylesheet" href="styles/auth.css">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/logo-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/logo-16.png">
  <link rel="shortcut icon" href="assets/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0d3b66">
  <meta name="application-name" content="Nafass">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="brand">
      <div class="logo">N</div>
      <div>
        <h1>Login</h1>
        <div class="subtitle">Nafass . Gabes</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="auth-error">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <form method="post" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required autofocus placeholder="you@example.tn">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
      </div>
      <div class="auth-row-right">
        <a href="forgot-password.php">Forgot password?</a>
      </div>
      <button type="submit" class="auth-btn">Login</button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="register.php">Create an account</a>
    </div>
  </div>
</body>
</html>

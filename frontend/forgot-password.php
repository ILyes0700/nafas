<?php
require_once __DIR__ . '/../backend/lib/auth.php';
auth_start();

$error = ''; $notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        auth_request_password_reset($email);
        $_SESSION['reset_email'] = strtolower($email);
        header('Location: reset-password.php');
        exit;
    }
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password - Nafass . Gabes</title>
  <link rel="stylesheet" href="styles/auth.css">
  <link rel="shortcut icon" href="assets/favicon.ico">
  <meta name="theme-color" content="#0d3b66">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="brand">
      <div class="logo">N</div>
      <div>
        <h1>Forgot Password</h1>
        <div class="subtitle">We'll email you a reset code</div>
      </div>
    </div>

    <?php if ($error): ?><div class="auth-error">&#9888; <?= htmlspecialchars($error) ?></div><?php endif ?>

    <form method="post" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required autofocus placeholder="you@example.tn">
      </div>
      <button type="submit" class="auth-btn">Send Reset Code</button>
    </form>

    <div class="auth-footer"><a href="login.php">Back to login</a></div>
  </div>
</body>
</html>

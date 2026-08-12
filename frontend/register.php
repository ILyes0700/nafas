<?php
require_once __DIR__ . '/../backend/lib/auth.php';
auth_start();

$error = '';
$old = ['first_name'=>'','last_name'=>'','phone'=>'','age'=>'','email'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($old) as $k) { $old[$k] = trim((string)($_POST[$k] ?? '')); }
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $res = auth_register([
            'first_name'       => $_POST['first_name'] ?? '',
            'last_name'        => $_POST['last_name'] ?? '',
            'phone'            => $_POST['phone'] ?? '',
            'age'              => $_POST['age'] ?? '',
            'email'            => $_POST['email'] ?? '',
            'password'         => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
        ]);
        if ($res['ok']) {
            $_SESSION['pending_verify_uid'] = (int)$res['user_id'];
            header('Location: verify-email.php?uid=' . (int)$res['user_id']);
            exit;
        }
        $error = $res['error'];
    }
}

if (auth_user()) { header('Location: index.php'); exit; }
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account - Nafass . Gabes</title>
  <link rel="stylesheet" href="styles/auth.css">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/logo-32.png">
  <link rel="shortcut icon" href="assets/favicon.ico">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0d3b66">
</head>
<body class="auth-body">
  <div class="auth-card auth-card-wide">
    <div class="brand">
      <div class="logo">N</div>
      <div>
        <h1>Create Account</h1>
        <div class="subtitle">Nafass . Gabes</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="auth-error">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <form method="post" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="grid-2">
        <div class="field">
          <label>First Name</label>
          <input type="text" name="first_name" required value="<?= htmlspecialchars($old['first_name']) ?>" placeholder="Salma">
        </div>
        <div class="field">
          <label>Last Name</label>
          <input type="text" name="last_name" required value="<?= htmlspecialchars($old['last_name']) ?>" placeholder="Ben Salah">
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label>Phone Number</label>
          <input type="tel" name="phone" required value="<?= htmlspecialchars($old['phone']) ?>" placeholder="+216 20 123 456">
        </div>
        <div class="field">
          <label>Age</label>
          <input type="number" name="age" required min="1" max="120" value="<?= htmlspecialchars($old['age']) ?>" placeholder="30">
        </div>
      </div>
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($old['email']) ?>" placeholder="you@example.tn">
      </div>
      <div class="grid-2">
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required minlength="8" placeholder="Min 8 chars">
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required minlength="8" placeholder="Repeat password">
        </div>
      </div>
      <div class="auth-hint">Password must be 8+ characters with an uppercase letter, a lowercase letter, and a digit.</div>
      <button type="submit" class="auth-btn">Create Account</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="login.php">Login</a>
    </div>
  </div>
</body>
</html>

<?php
require_once __DIR__ . '/../backend/lib/auth.php';
auth_logout();
header('Location: login.php');
exit;

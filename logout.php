<?php

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// خروج از سیستم
Auth::logout();

// استفاده از جاوااسکریپت برای redirect
echo "<script>window.location.href = '" . BASE_URL . "/login.php';</script>";
exit;
?>
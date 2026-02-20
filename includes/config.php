<?php
// تنظیمات سشن (باید قبل از session_start باشد)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    // SameSite برای کاهش CSRF
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Lax');
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// هدرهای امنیتی - غیرفعال شده برای رفع مشکل CSP
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
}

// تنظیمات پایه
define('BASE_PATH', dirname(__DIR__));

// تنظیم دستی BASE_URL با توجه به آدرس شما
define('BASE_URL', 'http://localhost:8080/chek');

define('SITE_NAME', 'سیستم فروش اقساطی قبور');

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'chek');

// تنظیم منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// تنظیمات زبان
mb_internal_encoding("UTF-8");

// گزارش خطاها (توسعه/تولید)
$appEnv = getenv('APP_ENV') ?: 'development';
if ($appEnv === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
?>
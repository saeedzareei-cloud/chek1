<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

// فقط کاربران مجاز (مدیر سیستم، جانشین مدیر، یا کسانی که به گزارش‌ها دسترسی دارند)
if (!Auth::hasPermission('reports') && $_SESSION['access_level'] !== 'مدیر سیستم' && $_SESSION['access_level'] !== 'جانشین مدیر') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    redirect('/index.php');
}

$db = Database::getInstance()->getConnection();

$id = (int)($_GET['id'] ?? 0);
$to = (int)($_GET['to'] ?? 0);
$to = ($to === 1) ? 1 : 0;

if ($id <= 0) {
    setMessage('شناسه چک نامعتبر است.', 'danger');
    redirect('/modules/reports/index.php?type=checks');
}

// تاریخ وصول: اگر وصول شده باشد امروز ثبت می‌شود، در غیر این صورت NULL
$received_date = null;
if ($to === 1) {
    $received_date = date('Y-m-d');
}

$stmt = $db->prepare("UPDATE checks SET is_received = ?, received_date = ? WHERE id = ?");
$stmt->bind_param('isi', $to, $received_date, $id);
if ($stmt->execute()) {
    setMessage('وضعیت چک بروزرسانی شد.', 'success');
} else {
    setMessage('خطا در بروزرسانی وضعیت چک.', 'danger');
}

redirect('/modules/reports/index.php?type=checks');

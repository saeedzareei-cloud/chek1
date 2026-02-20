<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if (!Auth::hasPermission('reports') && $_SESSION['access_level'] !== 'مدیر سیستم' && $_SESSION['access_level'] !== 'جانشین مدیر') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    redirect('/index.php');
}

$db = Database::getInstance()->getConnection();
$check_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($check_id <= 0) {
    setMessage('شناسه چک نامعتبر است.', 'danger');
    redirect('/modules/reports/index.php?type=checks');
}

$stmt = $db->prepare("DELETE FROM checks WHERE id = ?");
$stmt->bind_param('i', $check_id);
if ($stmt->execute()) {
    setMessage('چک با موفقیت حذف شد.', 'success');
} else {
    setMessage('خطا در حذف چک.', 'danger');
}

redirect('/modules/reports/index.php?type=checks');

<?php
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || $_SESSION['access_level'] !== 'مدیر سیستم') {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

$db = Database::getInstance()->getConnection();
$date = $_POST['date'] ?? '';
$description = $_POST['description'] ?? '';

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'تاریخ وارد نشده']);
    exit;
}

$date_parts = explode('/', $date);
if (count($date_parts) == 3) {
    if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        echo json_encode(['success' => false, 'message' => 'تاریخ وارد شده نامعتبر است']);
        exit;
    }
    
    if (function_exists('jalali_to_gregorian')) {
        $g_date = jalali_to_gregorian($date_parts[0], $date_parts[1], $date_parts[2]);
        $g_date_str = $g_date[0] . '-' . str_pad($g_date[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g_date[2], 2, '0', STR_PAD_LEFT);
    } else {
        $g_date_str = date('Y-m-d');
    }
    
    $check = $db->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
    $check->bind_param('s', $g_date_str);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'این تاریخ قبلاً ثبت شده است']);
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
    $stmt->bind_param('ss', $g_date_str, $description);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'روز تعطیل با موفقیت ثبت شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در ثبت روز تعطیل: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'فرمت تاریخ نامعتبر است. فرمت صحیح: 1403/12/29']);
}
?>
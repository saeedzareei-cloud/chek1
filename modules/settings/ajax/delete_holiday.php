<?php
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn() || $_SESSION['access_level'] !== 'مدیر سیستم') {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

$db = Database::getInstance()->getConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $check = $db->prepare("SELECT id FROM holidays WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'روز تعطیل یافت نشد']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'روز تعطیل با موفقیت حذف شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در حذف روز تعطیل: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'شناسه نامعتبر']);
}
?>
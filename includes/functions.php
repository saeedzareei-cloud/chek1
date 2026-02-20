<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jdf.php';

// توابع تاریخ
function jdateNow() {
    return jdate('Y/m/d');
}

function jtimeNow() {
    return jdate('H:i:s');
}

function jdatetimeNow() {
    return jdate('Y/m/d H:i:s');
}


// ===============================
// مهاجرت سبک دیتابیس (ستون‌های جدید)
// ===============================
function ensureChecksStatusColumns() {
    $db = Database::getInstance()->getConnection();
    // بررسی وجود ستون is_received
    $col = $db->query("SHOW COLUMNS FROM checks LIKE 'is_received'");
    if ($col && $col->num_rows == 0) {
        $db->query("ALTER TABLE checks ADD COLUMN is_received TINYINT(1) NOT NULL DEFAULT 0 AFTER description");
    }
    // بررسی وجود ستون received_date
    $col2 = $db->query("SHOW COLUMNS FROM checks LIKE 'received_date'");
    if ($col2 && $col2->num_rows == 0) {
        $db->query("ALTER TABLE checks ADD COLUMN received_date DATE NULL AFTER is_received");
    }
}

// ===============================
// تبدیل تاریخ میلادی به شمسی برای نمایش
// ===============================
function toJalaliDate($dateStr, $format='Y/m/d') {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return $dateStr;
    return jdate($format, $ts);
}

// اجرای مهاجرت‌های سبک (در هر درخواست بسیار سبک است)
ensureChecksStatusColumns();


function generateRequestNumber() {
    return jdate('Ymd') . date('His');
}

function isHoliday($date) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM holidays WHERE holiday_date = ?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

function getNextWorkingDay($date) {
    $max_iterations = 30;
    $counter = 0;
    $current_date = $date;
    
    while (isHoliday($current_date) && $counter < $max_iterations) {
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        $counter++;
    }
    
    return $current_date;
}

function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0 ریال';
    }
    return number_format((float)$amount) . ' ریال';
}

function redirect($url) {
    $url = '/' . ltrim($url, '/');
    header('Location: ' . rtrim(BASE_URL, '/') . $url);
    exit;
}

function displayMessage() {
    if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='بستن'></button>
                </div>";
    }
    return '';
}

function setMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function validateNationalCode($code) {
    $code = trim($code);
    
    if (!preg_match('/^[0-9]{10}$/', $code)) {
        return false;
    }
    
    for ($i = 0; $i < 10; $i++) {
        if (str_repeat($i, 10) == $code) {
            return false;
        }
    }
    
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int)$code[$i] * (10 - $i);
    }
    
    $remainder = $sum % 11;
    $control = (int)$code[9];
    
    return ($remainder < 2 && $control == $remainder) || 
           ($remainder >= 2 && $control == 11 - $remainder);
}

function validateMobile($mobile) {
    $mobile = trim($mobile);
    return preg_match('/^09[0-9]{9}$/', $mobile);
}
/**
 * نمایش تاریخ به صورت شمسی
 * @param string $date تاریخ میلادی
 * @return string تاریخ شمسی یا خط تیره
 */
function showDate($date) {
    // اگر تاریخ خالی یا نامعتبر بود
    if (empty($date) || $date == '0000-00-00' || $date == '0000/00/00' || $date == '1970-01-01') {
        return '-';
    }
    
    // حذف فاصله‌های اضافی
    $date = trim($date);
    
    // تبدیل به تایم‌استمپ
    $timestamp = strtotime($date);
    
    // اگر تبدیل ناموفق بود
    if ($timestamp === false || $timestamp <= 0) {
        // تلاش برای تبدیل تاریخ با فرمت دیگر
        $date_parts = explode('-', $date);
        if (count($date_parts) == 3) {
            $timestamp = mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]);
        } else {
            return '-';
        }
    }
    
    // اگر تابع jdate وجود دارد
    if (function_exists('jdate')) {
        return jdate('Y/m/d', $timestamp);
    }
    
    // در غیر این صورت تاریخ میلادی را برگردان
    return date('Y/m/d', $timestamp);
}

// خروجی امن برای HTML
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
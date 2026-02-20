<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

$db = Database::getInstance()->getConnection();

// لیست بانک‌ها برای انتخاب در ثبت چک
$banks_list = [];
$bank_q = $db->query("CREATE TABLE IF NOT EXISTS banks (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL UNIQUE, is_active BOOLEAN DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
$bank_q = $db->query("SELECT name FROM banks WHERE is_active = 1 ORDER BY name");
if ($bank_q) {
    while ($b = $bank_q->fetch_assoc()) {
        $banks_list[] = $b['name'];
    }
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 1;

// تنظیم متغیرهای هدر
$page_title = 'ثبت درخواست جدید';
$header_icon = 'plus-circle';

// دریافت تنظیمات هزینه‌ها و تعطیلات
$settings = [];
$holidays = [];
$result = $db->query("SELECT * FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// دریافت لیست تعطیلات از پایگاه داده
$holidays_result = $db->query("SELECT holiday_date FROM holidays ORDER BY holiday_date");
while ($row = $holidays_result->fetch_assoc()) {
    $holidays[] = $row['holiday_date'];
}

// ============================================
// پردازش درخواست‌های AJAX
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // پاک کردن هر گونه خروجی قبلی
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'add_grave') {
        $request_id = (int)$_POST['request_id'];
        $section_id = (int)$_POST['section_id'];
        $row_number = $_POST['row_number'] ?? '';
        $grave_number = $_POST['grave_number'] ?? '';
        $floor_count = (int)$_POST['floor_count'];
        
        if (!$request_id || !$section_id || !$grave_number || !$floor_count) {
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
            exit;
        }
        
        // بررسی تکراری نبودن قبر
        $check_stmt = $db->prepare("SELECT id FROM request_items WHERE request_id = ? AND section_id = ? AND grave_number = ?");
        $check_stmt->bind_param('iis', $request_id, $section_id, $grave_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'این قبر قبلاً به درخواست اضافه شده است']);
            exit;
        }
        
        // دریافت اطلاعات کامل قطعه
        $stmt = $db->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->bind_param('i', $section_id);
        $stmt->execute();
        $section = $stmt->get_result()->fetch_assoc();
        
        if (!$section) {
            echo json_encode(['success' => false, 'message' => 'قطعه یافت نشد']);
            exit;
        }
        
        // بررسی حداکثر طبقات
        if ($floor_count > $section['floor_count']) {
            echo json_encode(['success' => false, 'message' => "تعداد طبقات نمی‌تواند بیشتر از {$section['floor_count']} باشد"]);
            exit;
        }
        
        // دریافت قیمت بر اساس طبقه
        $prices = json_decode($section['prices_json'] ?? '{}', true);
        
        if (isset($prices[$floor_count])) {
            $price = $prices[$floor_count];
        } elseif ($floor_count == 1) {
            // اگر طبقه اول قیمت نداشت، از base_price استفاده کن
            $price = $section['base_price'];
        } else {
            echo json_encode(['success' => false, 'message' => "قیمت برای طبقه {$floor_count} در این قطعه تعریف نشده است"]);
            exit;
        }
        
        // محاسبه هزینه‌های اضافی بر اساس تعداد طبقات
        $file_creation_fee = ($floor_count > 1) ? ($settings['file_creation_fee'] ?? 500000) : 0;
        $stone_reservation_fee = ($floor_count > 1) ? ($settings['stone_reservation_fee'] ?? 300000) : 0;
        
        $stmt = $db->prepare("INSERT INTO request_items (request_id, item_type, section_id, row_number, grave_number, floor_count, price, file_creation_fee, stone_reservation_fee) VALUES (?, 'قبر', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissiiii', $request_id, $section_id, $row_number, $grave_number, $floor_count, $price, $file_creation_fee, $stone_reservation_fee);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در ثبت: ' . $db->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'add_tomb') {
        $request_id = (int)$_POST['request_id'];
        $tomb_id = (int)$_POST['tomb_id'];
        
        if (!$request_id || !$tomb_id) {
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
            exit;
        }
        
        // بررسی تکراری نبودن آرامگاه
        $check_stmt = $db->prepare("SELECT id FROM request_items WHERE request_id = ? AND tomb_id = ?");
        $check_stmt->bind_param('ii', $request_id, $tomb_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'این آرامگاه قبلاً به درخواست اضافه شده است']);
            exit;
        }
        
        // دریافت اطلاعات آرامگاه
        $stmt = $db->prepare("SELECT * FROM tombs WHERE id = ?");
        $stmt->bind_param('i', $tomb_id);
        $stmt->execute();
        $tomb = $stmt->get_result()->fetch_assoc();
        
        if (!$tomb) {
            echo json_encode(['success' => false, 'message' => 'آرامگاه یافت نشد']);
            exit;
        }
        
        // بروزرسانی وضعیت آرامگاه
        $stmt = $db->prepare("UPDATE tombs SET is_available = 0 WHERE id = ?");
        $stmt->bind_param('i', $tomb_id);
        $stmt->execute();
        
        $stmt = $db->prepare("INSERT INTO request_items (request_id, item_type, tomb_id, price) VALUES (?, 'آرامگاه', ?, ?)");
        $stmt->bind_param('iii', $request_id, $tomb_id, $tomb['price']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در ثبت: ' . $db->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_item') {
        $item_id = (int)$_POST['item_id'];
        
        if (!$item_id) {
            echo json_encode(['success' => false, 'message' => 'شناسه آیتم نامعتبر است']);
            exit;
        }
        
        // اگر آرامگاه است، وضعیت آن را برگردان
        $stmt = $db->prepare("SELECT item_type, tomb_id FROM request_items WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        
        if ($item && $item['item_type'] == 'آرامگاه' && $item['tomb_id']) {
            $stmt = $db->prepare("UPDATE tombs SET is_available = 1 WHERE id = ?");
            $stmt->bind_param('i', $item['tomb_id']);
            $stmt->execute();
        }
        
        $stmt = $db->prepare("DELETE FROM request_items WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در حذف: ' . $db->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'update_fee') {
        $item_id = (int)$_POST['item_id'];
        $fee_type = $_POST['fee_type'] ?? '';
        $value = (int)$_POST['value'];
        
        if (!$item_id || !$fee_type) {
            echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
            exit;
        }
        
        if ($fee_type == 'file') {
            $stmt = $db->prepare("UPDATE request_items SET file_creation_fee = ? WHERE id = ?");
        } elseif ($fee_type == 'stone') {
            $stmt = $db->prepare("UPDATE request_items SET stone_reservation_fee = ? WHERE id = ?");
        } else {
            echo json_encode(['success' => false, 'message' => 'نوع هزینه نامعتبر است']);
            exit;
        }
        
        $stmt->bind_param('ii', $value, $item_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در بروزرسانی: ' . $db->error]);
        }
        exit;
    }
    
    // اضافه کردن اکشن جدید برای بررسی تعطیل بودن تاریخ
    if ($_POST['action'] == 'check_holiday') {
        $date = $_POST['date'] ?? '';
        
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'تاریخ وارد نشده است']);
            exit;
        }
        
        // بررسی جمعه بودن
        $timestamp = strtotime(str_replace('/', '-', $date));
        $dayOfWeek = date('w', $timestamp);
        $isFriday = ($dayOfWeek == 5); // 5 در PHP برابر جمعه است (0=شنبه تا 6=جمعه)
        
        // بررسی تعطیلات رسمی
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM holidays WHERE holiday_date = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $isHoliday = ($result['count'] > 0);
        
        // پیدا کردن اولین روز غیرتعطیل بعدی
        $nextWorkingDay = findNextWorkingDay($db, $date);
        
        echo json_encode([
            'success' => true,
            'is_friday' => $isFriday,
            'is_holiday' => $isHoliday,
            'is_off' => ($isFriday || $isHoliday),
            'next_working_day' => $nextWorkingDay
        ]);
        exit;
    }
}

// تابع پیدا کردن اولین روز غیرتعطیل
function findNextWorkingDay($db, $date) {
    $currentDate = $date;
    $maxAttempts = 30; // جلوگیری از حلقه بی‌نهایت
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        // بررسی جمعه بودن
        $timestamp = strtotime(str_replace('/', '-', $currentDate));
        $dayOfWeek = date('w', $timestamp);
        $isFriday = ($dayOfWeek == 5);
        
        // بررسی تعطیلات رسمی
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM holidays WHERE holiday_date = ?");
        $stmt->bind_param('s', $currentDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $isHoliday = ($result['count'] > 0);
        
        if (!$isFriday && !$isHoliday) {
            return $currentDate;
        }
        
        // رفتن به روز بعد
        $timestamp = strtotime('+1 day', strtotime(str_replace('/', '-', $currentDate)));
        $currentDate = date('Y/m/d', $timestamp);
    }
    
    return $currentDate;
}

// ============================================
// مرحله 1: ثبت اطلاعات مشتری
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step1'])) {
    $national_code = $_POST['national_code'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $father_name = $_POST['father_name'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // اعتبارسنجی
    if (empty($national_code) || empty($full_name) || empty($mobile)) {
        $error = 'لطفاً فیلدهای ضروری را پر کنید.';
    } elseif (!validateNationalCode($national_code)) {
        $error = 'کد ملی نامعتبر است.';
    } elseif (!validateMobile($mobile)) {
        $error = 'شماره همراه نامعتبر است.';
    } else {
        // بررسی وجود مشتری
        $stmt = $db->prepare("SELECT id FROM customers WHERE national_code = ?");
        $stmt->bind_param('s', $national_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $customer = $result->fetch_assoc();
            $customer_id = $customer['id'];
            
            // به‌روزرسانی اطلاعات مشتری
            $stmt = $db->prepare("UPDATE customers SET full_name = ?, father_name = ?, mobile = ?, address = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $full_name, $father_name, $mobile, $address, $customer_id);
            $stmt->execute();
        } else {
            // ثبت مشتری جدید
            $stmt = $db->prepare("INSERT INTO customers (national_code, full_name, father_name, mobile, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $national_code, $full_name, $father_name, $mobile, $address);
            $stmt->execute();
            $customer_id = $db->insert_id;
        }
        
        // ایجاد درخواست جدید
        $request_number = generateRequestNumber();
        $request_date = date('Y-m-d');
        $user_id = $_SESSION['user_id'];
        
        $stmt = $db->prepare("INSERT INTO requests (request_number, request_date, customer_id, registrar_user_id, status) VALUES (?, ?, ?, ?, 'در حال تکمیل')");
        $stmt->bind_param('ssii', $request_number, $request_date, $customer_id, $user_id);
        
        if ($stmt->execute()) {
            $request_id = $db->insert_id;
            
            echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/new.php?step=2&id=" . $request_id . "';</script>";
            exit;
        } else {
            $error = 'خطا در ایجاد درخواست: ' . $db->error;
        }
    }
}

// ============================================
// مرحله 2: انتخاب قبور/آرامگاه
// ============================================
$request = null;
$sections = null;
$tombs = null;
$items = null;
$total_with_fees = 0;
$request_id = 0;

if (isset($_GET['step']) && $_GET['step'] == 2 && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    if ($request_id <= 0) {
        setMessage('شناسه درخواست نامعتبر است.', 'danger');
        echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
        exit;
    }
    
    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("SELECT r.*, c.* FROM requests r JOIN customers c ON r.customer_id = c.id WHERE r.id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    
    if (!$request) {
        setMessage('درخواست یافت نشد.', 'danger');
        echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
        exit;
    }
    
    // دریافت لیست قطعات و آرامگاه‌ها
    $sections = $db->query("SELECT * FROM sections ORDER BY name");
    $tombs = $db->query("SELECT * FROM tombs WHERE is_available = 1 ORDER BY tomb_number");
    
    // دریافت اقلام خریداری شده
    $items_query = "SELECT ri.*, s.name as section_name, t.tomb_number 
                    FROM request_items ri 
                    LEFT JOIN sections s ON ri.section_id = s.id 
                    LEFT JOIN tombs t ON ri.tomb_id = t.id 
                    WHERE ri.request_id = $request_id";
    $items = $db->query($items_query);
    
    if (!$items) {
        $error = 'خطا در دریافت اقلام: ' . $db->error;
    } else {
        $total_with_fees = 0;
        if ($items->num_rows > 0) {
            while ($item = $items->fetch_assoc()) {
                $total_with_fees += $item['price'] + $item['file_creation_fee'] + $item['stone_reservation_fee'];
            }
            $items->data_seek(0);
        }
    }
}

// ============================================
// مرحله 3: ثبت چک‌ها (نسخه اصلاح شده)
// ============================================
$total_base_price = 0;
$total_file_fees = 0;
$total_stone_fees = 0;
$total_with_fees_3 = 0;

if (isset($_GET['step']) && $_GET['step'] == 3 && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    if ($request_id <= 0) {
        setMessage('شناسه درخواست نامعتبر است.', 'danger');
        echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
        exit;
    }
    
    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("SELECT r.*, c.* FROM requests r JOIN customers c ON r.customer_id = c.id WHERE r.id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    
    if (!$request) {
        setMessage('درخواست یافت نشد.', 'danger');
        echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
        exit;
    }
    
    // دریافت اقلام خریداری شده
    $items = $db->query("SELECT ri.*, s.name as section_name, t.tomb_number FROM request_items ri 
                         LEFT JOIN sections s ON ri.section_id = s.id 
                         LEFT JOIN tombs t ON ri.tomb_id = t.id 
                         WHERE ri.request_id = $request_id");
    
    $total_base_price = 0;
    $total_file_fees = 0;
    $total_stone_fees = 0;
    $total_with_fees_3 = 0;
    
    if ($items && $items->num_rows > 0) {
        while ($item = $items->fetch_assoc()) {
            $total_base_price += $item['price'];
            $total_file_fees += $item['file_creation_fee'];
            $total_stone_fees += $item['stone_reservation_fee'];
            $total_with_fees_3 += $item['price'] + $item['file_creation_fee'] + $item['stone_reservation_fee'];
        }
        $items->data_seek(0);
    }
    
    // پردازش فرم ثبت چک‌ها
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_checks'])) {
        $cash_percent = (float)($_POST['cash_percent'] ?? 0);
        $check_count = (int)($_POST['check_count'] ?? 0);
        
        // محاسبه مبالغ
        $cash_amount = ($total_base_price * $cash_percent / 100) + $total_file_fees + $total_stone_fees;
        $check_amount = $total_base_price - ($total_base_price * $cash_percent / 100);
        
        $total_checks = 0;
        $checks_data = [];
        $date_error = false;
        $error_messages = [];
        
        // بررسی وجود حداقل یک چک معتبر
        $has_valid_check = false;
        
        for ($i = 1; $i <= $check_count; $i++) {
            $check_number = $_POST['check_number_' . $i] ?? '';
            $amount_str = $_POST['amount_' . $i] ?? '';
            
            // اگر شماره چک یا مبلغ خالی باشد، این چک را نادیده بگیر
            if (empty($check_number) || empty($amount_str)) {
                continue;
            }
            
            $has_valid_check = true;
            $due_date = $_POST['due_date_' . $i] ?? '';
            
            // تبدیل اعداد فارسی به انگلیسی در مبلغ
            $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            
            // تبدیل اعداد فارسی/عربی به انگلیسی
            $amount_str = str_replace($persian, $english, $amount_str);
            $amount_str = str_replace($arabic, $english, $amount_str);

            // حذف جداکننده‌های هزارگان فارسی/عربی/انگلیسی و فاصله‌ها
            // (در برخی مرورگرها جداکننده «٬» یا «،» ارسال می‌شود و در تبدیل به عدد، مقدار را می‌بُرد)
            $amount_str = str_replace([',', '٬', '،', ' '], '', $amount_str);

            // فقط رقم/نقطه نگه دار (ایمن‌تر)
            $amount_str = preg_replace('/[^\d.]/u', '', $amount_str);

            $check_amount_i = (float)$amount_str;
            
            if ($check_amount_i <= 0) {
                $error_messages[] = "مبلغ چک شماره $i باید بیشتر از صفر باشد.";
                $date_error = true;
                continue;
            }
            
            // بررسی تعطیل بودن تاریخ (اختیاری - در صورت نیاز فعال کنید)
            /*
            if (!empty($due_date)) {
                $timestamp = strtotime(str_replace('/', '-', $due_date));
                if ($timestamp) {
                    $dayOfWeek = date('w', $timestamp);
                    $isFriday = ($dayOfWeek == 5);
                    
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM holidays WHERE holiday_date = ?");
                    $stmt->bind_param('s', $due_date);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $isHoliday = ($result['count'] > 0);
                    
                    if ($isFriday || $isHoliday) {
                        $error_messages[] = "تاریخ چک شماره $i ($due_date) تعطیل یا جمعه است.";
                        $date_error = true;
                        continue;
                    }
                }
            }
            */
            
            $total_checks += $check_amount_i;
            
            $checks_data[] = [
                'number' => $check_number,
                'bank' => $_POST['bank_name_' . $i] ?? '',
                'due_date' => $due_date,
                'amount' => $check_amount_i,
                'drawer_national_code' => $_POST['drawer_national_code_' . $i] ?? '',
                'drawer_full_name' => $_POST['drawer_full_name_' . $i] ?? '',
                'drawer_father_name' => $_POST['drawer_father_name_' . $i] ?? '',
                'drawer_mobile' => $_POST['drawer_mobile_' . $i] ?? ''
            ];
        }
        
        // اگر چکی وارد نشده باشد و check_count > 0 باشد، یعنی همه چک‌ها خالی هستند
        // این حالت مجاز است (پرداخت نقدی کامل)
        
        if ($date_error) {
            $error = implode('<br>', $error_messages);
        } elseif ($cash_percent < 0 || $cash_percent > 100) {
            $error = 'درصد پرداخت نقدی باید بین ۰ تا ۱۰۰ باشد.';
        } elseif ($has_valid_check && $check_amount < 0) {
            $error = 'مبلغ چک نمی‌تواند منفی باشد.';
        } elseif ($has_valid_check && $check_amount > 0 && abs($total_checks - $check_amount) > 1000) {
            // فقط زمانی که مبلغ چک محاسبه شده بیشتر از صفر باشد و مجموع چک‌ها با آن مطابقت نداشته باشد
            $error = 'مجموع مبالغ چک‌ها (' . number_format($total_checks) . ' ریال) با مبلغ چک محاسبه شده (' . number_format($check_amount) . ' ریال) مطابقت ندارد.';
        } else {
            // شروع تراکنش برای اطمینان از یکپارچگی داده‌ها
            $db->begin_transaction();
            
            try {
                // به‌روزرسانی اطلاعات درخواست
                $stmt = $db->prepare("UPDATE requests SET cash_percent = ?, cash_amount = ?, check_amount = ?, total_amount = ? WHERE id = ?");
                $stmt->bind_param('diiii', $cash_percent, $cash_amount, $check_amount, $total_with_fees_3, $request_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در به‌روزرسانی درخواست: " . $stmt->error);
                }
                
                // حذف چک‌های قبلی
                $stmt = $db->prepare("DELETE FROM checks WHERE request_id = ?");
                $stmt->bind_param('i', $request_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در حذف چک‌های قبلی: " . $stmt->error);
                }
                
                // ثبت چک‌های جدید
                if (!empty($checks_data)) {
                    $stmt = $db->prepare("INSERT INTO checks (request_id, check_number, bank_name, due_date, amount, drawer_national_code, drawer_full_name, drawer_father_name, drawer_mobile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    foreach ($checks_data as $check) {
                        $stmt->bind_param('isssissss', 
                            $request_id, 
                            $check['number'], 
                            $check['bank'], 
                            $check['due_date'], 
                            $check['amount'], 
                            $check['drawer_national_code'], 
                            $check['drawer_full_name'], 
                            $check['drawer_father_name'], 
                            $check['drawer_mobile']
                        );
                        
                        if (!$stmt->execute()) {
                            throw new Exception("خطا در ثبت چک: " . $stmt->error);
                        }
                    }
                }
                
                // تغییر وضعیت درخواست به "ارجاع برای امضا" (برای نمایش در تایید درخواست‌ها)
                $status = 'ارجاع برای امضا';
                $stmt = $db->prepare("UPDATE requests SET status = ? WHERE id = ?");
                $stmt->bind_param('si', $status, $request_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در به‌روزرسانی وضعیت: " . $stmt->error);
                }
                
                // تایید تراکنش
                $db->commit();
                
                setMessage('درخواست با موفقیت ثبت و برای امضا ارسال شد.', 'success');
                echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
                exit;
                
            } catch (Exception $e) {
                // برگرداندن تراکنش در صورت خطا
                $db->rollback();
                $error = 'خطا در ثبت اطلاعات: ' . $e->getMessage();
                
                // لاگ خطا
                error_log("Error in request submission: " . $e->getMessage());
            }
        }
    }
}

include '../../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- مراحل -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">
                <span class="badge <?php echo $step >= 1 ? 'bg-primary' : 'bg-secondary'; ?>">1</span>
                اطلاعات مشتری
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
                <span class="badge <?php echo $step >= 2 ? 'bg-primary' : 'bg-secondary'; ?>">2</span>
                انتخاب قبور/آرامگاه
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                <span class="badge <?php echo $step >= 3 ? 'bg-primary' : 'bg-secondary'; ?>">3</span>
                ثبت چک‌ها
            </div>
        </div>
    </div>
</div>

<?php if ($step == 1): ?>
    <!-- مرحله 1: اطلاعات مشتری -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-person"></i> اطلاعات درخواست کننده
        </div>
        <div class="card-body">
            <form method="POST" action="" id="customerForm">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="national_code" class="form-label">کد ملی <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="national_code" name="national_code" maxlength="10" required>
                        <div class="invalid-feedback" id="nationalCodeError"></div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="full_name" class="form-label">نام و نام خانوادگی <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="father_name" class="form-label">نام پدر</label>
                        <input type="text" class="form-control" id="father_name" name="father_name">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="mobile" class="form-label">شماره همراه <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mobile" name="mobile" maxlength="11" required>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label for="address" class="form-label">آدرس</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="step1" class="btn btn-primary" id="submitStep1">
                            <i class="bi bi-arrow-left"></i> مرحله بعد
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($step == 2 && isset($request) && $request_id > 0): ?>
    <!-- مرحله 2: انتخاب قبور/آرامگاه -->
    
    <!-- ردیف اول: اطلاعات درخواست -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-info-circle"></i> اطلاعات درخواست
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <strong>شماره درخواست:</strong>
                            <p class="text-primary mb-0"><?php echo htmlspecialchars($request['request_number']); ?></p>
                        </div>
                        <div class="col-md-3 mb-2">
                            <strong>تاریخ:</strong>
                            <p class="mb-0"><?php echo function_exists('jdate') ? jdate('Y/m/d', strtotime($request['request_date'])) : $request['request_date']; ?></p>
                        </div>
                        <div class="col-md-3 mb-2">
                            <strong>درخواست کننده:</strong>
                            <p class="mb-0"><?php echo htmlspecialchars($request['full_name']); ?></p>
                        </div>
                        <div class="col-md-3 mb-2">
                            <strong>کد ملی:</strong>
                            <p class="mb-0"><?php echo htmlspecialchars($request['national_code']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ردیف دوم: فرم‌های افزودن قبر و آرامگاه (کنار هم) -->
    <div class="row mb-4">
        <!-- فرم افزودن قبر -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-plus-circle"></i> افزودن قبر
                </div>
                <div class="card-body">
                    <form id="addGraveForm" onsubmit="return false;">
                        <input type="hidden" id="request_id" value="<?php echo $request_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">قطعه <span class="text-danger">*</span></label>
                            <select class="form-control" id="section_id" required onchange="updateFloorInfo()">
                                <option value="">انتخاب کنید</option>
                                <?php 
                                if ($sections && $sections->num_rows > 0) {
                                    $sections->data_seek(0);
                                    while($section = $sections->fetch_assoc()): 
                                        $prices = json_decode($section['prices_json'] ?? '{}', true);
                                ?>
                                    <option value="<?php echo $section['id']; ?>" 
                                            data-floor-count="<?php echo $section['floor_count']; ?>"
                                            data-prices='<?php echo json_encode($prices); ?>'>
                                        <?php echo htmlspecialchars($section['name']); ?> 
                                        (حداکثر <?php echo $section['floor_count']; ?> طبقه)
                                    </option>
                                <?php 
                                    endwhile;
                                } 
                                ?>
                            </select>
                            <small class="form-text text-muted" id="max_floors_display">حداکثر طبقات: 0</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ردیف</label>
                                <input type="text" class="form-control" id="row_number" value="1">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره قبر <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="grave_number" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">تعداد طبقات <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="floor_count" min="1" max="10" value="1" required onchange="updatePriceDisplay()">
                            <small class="text-danger d-none" id="floor_error"></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">قیمت محاسبه شده</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="price_display" readonly value="۰ ریال">
                                <span class="input-group-text bg-info text-white">ریال</span>
                            </div>
                            <small class="form-text text-muted" id="price_info"></small>
                        </div>
                        
                        <button type="button" class="btn btn-success w-100" onclick="addGrave()">
                            <i class="bi bi-plus"></i> افزودن قبر
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- فرم افزودن آرامگاه -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-plus-circle"></i> افزودن آرامگاه
                </div>
                <div class="card-body">
                    <form id="addTombForm" onsubmit="return false;">
                        <div class="mb-3">
                            <label class="form-label">آرامگاه <span class="text-danger">*</span></label>
                            <select class="form-control" id="tomb_id" required>
                                <option value="">انتخاب کنید</option>
                                <?php 
                                if ($tombs && $tombs->num_rows > 0) {
                                    $tombs->data_seek(0);
                                    while($tomb = $tombs->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $tomb['id']; ?>" data-price="<?php echo $tomb['price']; ?>">
                                        <?php echo htmlspecialchars($tomb['tomb_number']); ?> - 
                                        <?php echo number_format($tomb['price']); ?> ریال
                                    </option>
                                <?php 
                                    endwhile;
                                } 
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <p class="text-muted small">آرامگاه‌های موجود با وضعیت قابل فروش نمایش داده می‌شوند.</p>
                        </div>
                        
                        <button type="button" class="btn btn-success w-100" onclick="addTomb()">
                            <i class="bi bi-plus"></i> افزودن آرامگاه
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ردیف سوم: جدول اقلام انتخاب شده -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-list"></i> اقلام انتخاب شده
                    </span>
                    <span class="badge bg-light text-dark"><?php echo ($items && $items->num_rows) ? $items->num_rows : 0; ?> قلم</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="itemsTable">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>نوع</th>
                                    <th>شرح</th>
                                    <th>قیمت پایه</th>
                                    <th>هزینه تشکیل پرونده</th>
                                    <th>هزینه سنگ رزرو</th>
                                    <th>جمع کل</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($items && $items->num_rows > 0):
                                    $items->data_seek(0);
                                    while($item = $items->fetch_assoc()):
                                        $item_price = isset($item['price']) ? (int)$item['price'] : 0;
                                        $item_file_fee = isset($item['file_creation_fee']) ? (int)$item['file_creation_fee'] : 0;
                                        $item_stone_fee = isset($item['stone_reservation_fee']) ? (int)$item['stone_reservation_fee'] : 0;
                                        $item_total = $item_price + $item_file_fee + $item_stone_fee;
                                        
                                        if ($item['item_type'] == 'قبر') {
                                            $description = "قطعه: " . ($item['section_name'] ?? '-') . 
                                                          " - ردیف: " . ($item['row_number'] ?? '-') . 
                                                          " - شماره: " . ($item['grave_number'] ?? '-');
                                            if ($item['floor_count'] > 1) {
                                                $description .= " (" . $item['floor_count'] . " طبقه)";
                                            }
                                        } else {
                                            $description = "شماره آرامگاه: " . ($item['tomb_number'] ?? '-');
                                        }
                                ?>
                                <tr id="item_<?php echo $item['id']; ?>">
                                    <td class="align-middle">
                                        <?php if ($item['item_type'] == 'قبر'): ?>
                                            <span class="badge bg-info">قبر</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">آرامگاه</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle"><?php echo htmlspecialchars($description); ?></td>
                                    <td class="text-start align-middle"><?php echo number_format($item_price); ?> ریال</td>
                                    <td class="text-start align-middle">
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="checkbox" class="form-check-input fee-checkbox" 
                                                   id="file_<?php echo $item['id']; ?>"
                                                   <?php echo ($item['file_creation_fee'] > 0) ? 'checked' : ''; ?>
                                                   onchange="toggleFee(<?php echo $item['id']; ?>, 'file', this.checked, <?php echo $settings['file_creation_fee'] ?? 500000; ?>)">
                                            <label for="file_<?php echo $item['id']; ?>" class="form-check-label">
                                                <?php echo number_format($item_file_fee); ?> ریال
                                            </label>
                                        </div>
                                    </td>
                                    <td class="text-start align-middle">
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="checkbox" class="form-check-input fee-checkbox" 
                                                   id="stone_<?php echo $item['id']; ?>"
                                                   <?php echo ($item['stone_reservation_fee'] > 0) ? 'checked' : ''; ?>
                                                   onchange="toggleFee(<?php echo $item['id']; ?>, 'stone', this.checked, <?php echo $settings['stone_reservation_fee'] ?? 300000; ?>)">
                                            <label for="stone_<?php echo $item['id']; ?>" class="form-check-label">
                                                <?php echo number_format($item_stone_fee); ?> ریال
                                            </label>
                                        </div>
                                    </td>
                                    <td class="text-start align-middle"><strong><?php echo number_format($item_total); ?> ریال</strong></td>
                                    <td class="align-middle">
                                        <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                        <h5>هیچ قبر یا آرامگاهی انتخاب نشده است</h5>
                                        <p class="mb-0">از فرم‌های بالا برای افزودن قبر یا آرامگاه استفاده کنید</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="bg-light fw-bold">
                                <tr>
                                    <th colspan="5" class="text-start">جمع کل:</th>
                                    <th class="text-start text-success fs-5">
                                        <?php echo number_format($total_with_fees); ?> ریال
                                    </th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- دکمه‌های ناوبری -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=1" class="btn btn-secondary">
                            <i class="bi bi-arrow-right"></i> بازگشت به مرحله قبل
                        </a>
                        
                        <?php if ($items && $items->num_rows > 0): ?>
                            <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=3&id=<?php echo $request_id; ?>" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> مرحله بعد (ثبت چک‌ها)
                            </a>
                        <?php else: ?>
                            <button class="btn btn-primary" disabled title="ابتدا حداقل یک قلم انتخاب کنید">
                                <i class="bi bi-arrow-left"></i> مرحله بعد (ثبت چک‌ها)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($step == 3 && isset($request) && $request_id > 0): ?>
    <!-- مرحله 3: ثبت چک‌ها -->
    <div class="card mb-3">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-info-circle"></i> اطلاعات درخواست
            </span>
            <div>
                <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=2&id=<?php echo $request_id; ?>" class="btn btn-light btn-sm me-2">
                    <i class="bi bi-arrow-right"></i> بازگشت به مرحله قبل
                </a>
                <span class="badge bg-light text-dark">مرحله ۳ از ۳</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>شماره درخواست:</strong>
                    <p class="text-primary"><?php echo htmlspecialchars($request['request_number']); ?></p>
                </div>
                <div class="col-md-3">
                    <strong>تاریخ:</strong>
                    <p><?php echo function_exists('jdate') ? jdate('Y/m/d', strtotime($request['request_date'])) : $request['request_date']; ?></p>
                </div>
                <div class="col-md-3">
                    <strong>درخواست کننده:</strong>
                    <p><?php echo htmlspecialchars($request['full_name']); ?></p>
                </div>
                <div class="col-md-3">
                    <strong>کد ملی:</strong>
                    <p><?php echo htmlspecialchars($request['national_code']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول اقلام انتخاب شده -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">اقلام انتخاب شده</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th>شرح</th>
                            <th>قیمت پایه</th>
                            <th>هزینه تشکیل پرونده</th>
                            <th>هزینه سنگ رزرو</th>
                            <th>جمع کل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($items && $items->num_rows > 0):
                            $items->data_seek(0);
                            while($item = $items->fetch_assoc()):
                                $item_price = isset($item['price']) ? (int)$item['price'] : 0;
                                $item_file_fee = isset($item['file_creation_fee']) ? (int)$item['file_creation_fee'] : 0;
                                $item_stone_fee = isset($item['stone_reservation_fee']) ? (int)$item['stone_reservation_fee'] : 0;
                                $item_total = $item_price + $item_file_fee + $item_stone_fee;
                                
                                if ($item['item_type'] == 'قبر') {
                                    $description = "قبر - قطعه: " . ($item['section_name'] ?? '-') . 
                                                  "، ردیف: " . ($item['row_number'] ?? '-') . 
                                                  "، شماره: " . ($item['grave_number'] ?? '-');
                                    if ($item['floor_count'] > 1) {
                                        $description .= " (" . $item['floor_count'] . " طبقه)";
                                    }
                                } else {
                                    $description = "آرامگاه - شماره: " . ($item['tomb_number'] ?? '-');
                                }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($description); ?></td>
                            <td class="text-start"><?php echo number_format($item_price); ?> ریال</td>
                            <td class="text-start"><?php echo number_format($item_file_fee); ?> ریال</td>
                            <td class="text-start"><?php echo number_format($item_stone_fee); ?> ریال</td>
                            <td class="text-start"><strong><?php echo number_format($item_total); ?> ریال</strong></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="text-center text-danger">
                                هیچ قلمی انتخاب نشده است. لطفاً به مرحله قبل بازگردید.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <th class="text-start">جمع کل:</th>
                            <th class="text-start"><?php echo number_format($total_base_price); ?> ریال</th>
                            <th class="text-start"><?php echo number_format($total_file_fees); ?> ریال</th>
                            <th class="text-start"><?php echo number_format($total_stone_fees); ?> ریال</th>
                            <th class="text-start"><?php echo number_format($total_with_fees_3); ?> ریال</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php if ($items && $items->num_rows > 0): ?>
    <!-- فرم ثبت چک‌ها -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">ثبت چک‌ها</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="checksForm" onsubmit="return validateChecks()">
                <!-- خلاصه مبالغ -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="alert alert-info mb-0">
                            <strong>جمع کل:</strong><br>
                            <span class="fs-5"><?php echo number_format($total_with_fees_3); ?> ریال</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-secondary mb-0">
                            <strong>قیمت پایه کل:</strong><br>
                            <span><?php echo number_format($total_base_price); ?> ریال</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning mb-0">
                            <strong>مجموع هزینه‌ها:</strong><br>
                            <span><?php echo number_format($total_file_fees + $total_stone_fees); ?> ریال</span>
                        </div>
                    </div>
                </div>

                <!-- درصد پرداخت نقدی -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">درصد پرداخت نقدی (از قیمت پایه):</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="cash_percent" name="cash_percent" 
                                   min="0" max="100" value="0" onchange="calculateAmounts()">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">مبلغ نقدی قابل پرداخت:</label>
                        <div class="alert alert-success p-2 mb-0" id="cash_amount_display">
                            <?php echo number_format($total_file_fees + $total_stone_fees); ?> ریال
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">مبلغ باقیمانده (چک):</label>
                        <div class="alert alert-primary p-2 mb-0" id="check_amount_display">
                            <?php echo number_format($total_base_price); ?> ریال
                        </div>
                    </div>
                </div>

                <!-- تعداد چک‌ها -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">تعداد چک‌ها:</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="check_count" name="check_count" 
                                   min="0" max="12" value="1" onchange="generateCheckForms()">
                            <button type="button" class="btn btn-info" onclick="copyCustomerInfo()">
                                <i class="bi bi-files"></i> کپی اطلاعات مشتری
                            </button>
                        </div>
                        <small class="text-muted">حداکثر 12 چک (برای پرداخت نقدی کامل، تعداد را 0 قرار دهید)</small>
                    </div>
                </div>

                <!-- کانتینر چک‌ها -->
                <div id="checksContainer" class="mb-4"></div>

                <div class="mt-4 d-flex justify-content-between">
                    <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=2&id=<?php echo $request_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> بازگشت
                    </a>
                    <button type="submit" name="save_checks" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> ثبت نهایی درخواست
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
        هیچ قلمی برای ثبت چک وجود ندارد. لطفاً ابتدا در مرحله دوم قبور یا آرامگاه را انتخاب کنید.
        <div class="mt-3">
            <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=2&id=<?php echo $request_id; ?>" class="btn btn-primary">
                <i class="bi bi-arrow-right"></i> بازگشت به مرحله انتخاب
            </a>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> صفحه مورد نظر یافت نشد.
    </div>
<?php endif; ?>

<style>
.step {
    flex: 1;
    text-align: center;
    padding: 10px;
    color: #999;
}
.step.active {
    color: #0d6efd;
    font-weight: bold;
}
.step .badge {
    margin-left: 5px;
}
.fee-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
.fee-checkbox:checked {
    background-color: #28a745;
    border-color: #28a745;
}
.form-check-label {
    cursor: pointer;
    user-select: none;
}
.check-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background-color: #f8f9fa;
}
.h-100 {
    height: 100%;
}
</style>

<!-- لینک‌های مورد نیاز -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/persian-datepicker.min.css">
<script src="<?php echo BASE_URL; ?>/assets/js/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/persian-date.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/persian-datepicker.min.js"></script>

<script>
// اطلاعات مشتری برای کپی
const customerInfo = {
    national_code: '<?php echo isset($request) ? addslashes($request['national_code']) : ''; ?>',
    full_name: '<?php echo isset($request) ? addslashes($request['full_name']) : ''; ?>',
    father_name: '<?php echo isset($request) ? addslashes($request['father_name']) : ''; ?>',
    mobile: '<?php echo isset($request) ? addslashes($request['mobile']) : ''; ?>'
};

// ذخیره اطلاعات قطعات
let sectionsData = {};

// تابع بررسی تعطیل بودن تاریخ با AJAX
async function checkHoliday(date) {
    try {
        const response = await $.ajax({
            url: '<?php echo BASE_URL; ?>/modules/requests/new.php',
            type: 'POST',
            data: {
                action: 'check_holiday',
                date: date
            },
            dataType: 'json'
        });
        return response;
    } catch (error) {
        console.error('خطا در بررسی تعطیلات:', error);
        return { is_off: false };
    }
}

// تابع اضافه کردن روز به تاریخ شمسی
function addDaysToPersianDate(dateStr, days) {
    if (!dateStr) return '';
    try {
        const parts = dateStr.split('/');
        if (parts.length !== 3) return '';
        
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const day = parseInt(parts[2]);
        
        const date = new persianDate([year, month, day]);
        date.add('days', days);
        return date.format('YYYY/MM/DD');
    } catch (e) {
        console.error('خطا در تبدیل تاریخ:', e);
        return '';
    }
}

// لیست بانک‌ها از سمت سرور
const banksList = <?php echo json_encode($banks_list, JSON_UNESCAPED_UNICODE); ?>;
        holidaysList = <?php echo json_encode($holidays, JSON_UNESCAPED_UNICODE); ?>;

// تابع تولید فرم‌های چک
async function generateCheckForms() {
    const count = parseInt(document.getElementById('check_count').value) || 0;
    const container = document.getElementById('checksContainer');
    
    if (!container) return;
    
    container.innerHTML = '';
    
    if (count === 0) {
        container.innerHTML = '<div class="alert alert-info">تعداد چک صفر است. پرداخت به صورت نقدی کامل انجام می‌شود.</div>';
        return;
    }
    
    for (let i = 1; i <= count; i++) {
        const today = new persianDate().format('YYYY/MM/DD');
        const defaultDate = addDaysToPersianDate(today, 30 * i);
        
        const formHtml = `
            <div class="check-card" id="check_card_${i}">
                <h6 class="mb-3 bg-light p-2 rounded">چک شماره ${i}</h6>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">شماره چک <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="check_number_${i}" id="check_number_${i}">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">بانک</label>
                        <select class="form-select" name="bank_name_${i}" id="bank_name_${i}">
                            <option value="">-- انتخاب بانک --</option>
                            ${Array.isArray(banksList) ? banksList.map(b => `<option value="${b}">${b}</option>`).join('') : ''}
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">مدت سررسید (روز)</label>
                        <input type="text" inputmode="numeric" class="form-control" name="due_days_${i}" id="due_days_${i}" placeholder="مثلاً 45">
                        <small id="due_hint_${i}" class="text-muted"></small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">تاریخ سررسید <span class="text-danger">*</span></label>
                        <input type="text" class="form-control persian-date" name="due_date_${i}" 
                               id="due_date_${i}" value="${defaultDate}" autocomplete="off">
                        <small class="text-muted date-status" id="dateStatus_${i}"></small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">مبلغ (ریال) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control price-format money-input" name="amount_${i}" id="amount_${i}">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">کد ملی صادرکننده</label>
                        <input type="text" class="form-control" name="drawer_national_code_${i}" id="drawer_national_code_${i}">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">نام صادرکننده</label>
                        <input type="text" class="form-control" name="drawer_full_name_${i}" id="drawer_full_name_${i}">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">نام پدر صادرکننده</label>
                        <input type="text" class="form-control" name="drawer_father_name_${i}" id="drawer_father_name_${i}">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">شماره همراه صادرکننده</label>
                        <input type="text" class="form-control" name="drawer_mobile_${i}" id="drawer_mobile_${i}">
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += formHtml;
    }
    
    // فعال‌سازی تاریخ‌پیکر
    if (typeof $ !== 'undefined' && $.fn.persianDatepicker) {
        $('.persian-date').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            observer: true,
            initialValue: true
        });
    }
    
    
    // محاسبه خودکار تاریخ سررسید بر اساس «مدت سررسید (روز)» و تعطیلات سیستم
    const holidaysSet = new Set(Array.isArray(holidaysList) ? holidaysList : []);
    const pad2 = (n) => String(n).padStart(2, '0');
    const toISO = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;

    for (let i = 1; i <= count; i++) {
        const daysInput = document.getElementById(`due_days_${i}`);
        const dueInput = document.getElementById(`due_date_${i}`);
        const hintEl = document.getElementById(`due_hint_${i}`);

        if (!daysInput || !dueInput) continue;

        daysInput.addEventListener('input', function () {
            // فقط عدد
            let raw = (this.value || '').replace(/[^0-9۰-۹٠-٩]/g, '');
            if (raw === '') {
                if (hintEl) hintEl.textContent = '';
                return;
            }

            // تبدیل اعداد فارسی/عربی به انگلیسی
            const persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            const arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
            for (let k = 0; k < 10; k++) {
                raw = raw.replaceAll(persian[k], String(k)).replaceAll(arabic[k], String(k));
            }

            const days = parseInt(raw, 10);
            if (isNaN(days)) return;

            // امروز + N روز
            const today = new Date();
            today.setHours(0,0,0,0);
            const target = new Date(today);
            target.setDate(target.getDate() + days);

            // اگر تعطیل بود، به اولین روز غیرتعطیل بعدی منتقل شود
            let adjusted = new Date(target);
            let moved = false;
            while (holidaysSet.has(toISO(adjusted))) {
                adjusted.setDate(adjusted.getDate() + 1);
                moved = true;
            }

            // مقداردهی تاریخ سررسید (شمسی)
            if (typeof persianDate !== 'undefined') {
                dueInput.value = new persianDate(adjusted).format('YYYY/MM/DD');
            } else {
                // fallback (میلادی)
                dueInput.value = toISO(adjusted).replaceAll('-', '/');
            }

            if (hintEl) {
                hintEl.textContent = moved ? 'تاریخ محاسبه‌شده تعطیل بود؛ به روز کاری بعد منتقل شد.' : '';
            }
        });
    }

// فرمت کردن اعداد
    $('.price-format').on('input', function() {
        let value = this.value.replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            this.value = Number(value).toLocaleString();
        }
    });
    
    // محاسبه خودکار مبلغ چک‌ها
    calculateCheckAmounts();
}

// محاسبه خودکار مبلغ چک‌ها بر اساس مبلغ باقیمانده
function calculateCheckAmounts() {
    const count = parseInt(document.getElementById('check_count').value) || 0;
    const { checkAmount } = calculateAmounts();
    
    if (count > 0 && checkAmount > 0) {
        const perCheckAmount = Math.floor(checkAmount / count);
        const lastCheckAmount = checkAmount - (perCheckAmount * (count - 1));
        
        for (let i = 1; i <= count; i++) {
            const amountInput = document.getElementById(`amount_${i}`);
            if (amountInput) {
                if (i === count) {
                    amountInput.value = lastCheckAmount.toLocaleString();
                } else {
                    amountInput.value = perCheckAmount.toLocaleString();
                }
            }
        }
    }
}

// محاسبه مبالغ بر اساس درصد
function calculateAmounts() {
    const totalBasePrice = <?php echo $total_base_price ?? 0; ?>;
    const totalFileFees = <?php echo $total_file_fees ?? 0; ?>;
    const totalStoneFees = <?php echo $total_stone_fees ?? 0; ?>;
    const percent = parseFloat(document.getElementById('cash_percent').value) || 0;
    
    const cashAmount = (totalBasePrice * percent / 100) + totalFileFees + totalStoneFees;
    const checkAmount = totalBasePrice - (totalBasePrice * percent / 100);
    
    document.getElementById('cash_amount_display').innerHTML = cashAmount.toLocaleString() + ' ریال';
    document.getElementById('check_amount_display').innerHTML = checkAmount.toLocaleString() + ' ریال';
    
    // به‌روزرسانی مبلغ چک‌ها
    calculateCheckAmounts();
    
    return { cashAmount, checkAmount };
}

// کپی اطلاعات مشتری
function copyCustomerInfo() {
    const count = parseInt(document.getElementById('check_count').value) || 0;
    
    for (let i = 1; i <= count; i++) {
        const nationalCodeInput = document.getElementById(`drawer_national_code_${i}`);
        const fullNameInput = document.getElementById(`drawer_full_name_${i}`);
        const fatherNameInput = document.getElementById(`drawer_father_name_${i}`);
        const mobileInput = document.getElementById(`drawer_mobile_${i}`);
        
        if (nationalCodeInput) nationalCodeInput.value = customerInfo.national_code;
        if (fullNameInput) fullNameInput.value = customerInfo.full_name;
        if (fatherNameInput) fatherNameInput.value = customerInfo.father_name;
        if (mobileInput) mobileInput.value = customerInfo.mobile;
    }
}

// اعتبارسنجی چک‌ها قبل از ارسال
async function validateChecks() {
    const { checkAmount } = calculateAmounts();
    const count = parseInt(document.getElementById('check_count').value) || 0;
    let totalChecks = 0;
    let hasValidCheck = false;
    let errorMessages = [];
    
    // اگر تعداد چک صفر باشد، مستقیماً ارسال کن
    if (count === 0) {
        return true;
    }
    
    for (let i = 1; i <= count; i++) {
        const checkNumber = document.getElementById(`check_number_${i}`)?.value;
        const amountInput = document.getElementById(`amount_${i}`);
        const dateInput = document.getElementById(`due_date_${i}`);
        
        // اگر فیلدهای چک خالی هستند، از آن رد شو
        if (!checkNumber || !amountInput || !amountInput.value) {
            continue;
        }
        
        // حذف کاما و تبدیل اعداد فارسی به انگلیسی
        let amountStr = amountInput.value.replace(/,/g, '');
        
        // تبدیل اعداد فارسی به انگلیسی
        const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
        const arabicNumbers = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
        
        for (let j = 0; j < 10; j++) {
            amountStr = amountStr.replace(persianNumbers[j], j);
            amountStr = amountStr.replace(arabicNumbers[j], j);
        }
        
        const amount = parseFloat(amountStr) || 0;
        
        if (amount <= 0) {
            errorMessages.push(`مبلغ چک شماره ${i} باید بیشتر از صفر باشد.`);
            continue;
        }
        
        if (!dateInput || !dateInput.value) {
            errorMessages.push(`تاریخ چک شماره ${i} را وارد کنید.`);
            continue;
        }
        
        totalChecks += amount;
        hasValidCheck = true;
    }
    
    if (errorMessages.length > 0) {
        alert(errorMessages.join('\n'));
        return false;
    }
    
    // اگر چکی وارد نشده باشد، مجاز است
    if (!hasValidCheck) {
        return true;
    }
    
    if (Math.abs(totalChecks - checkAmount) > 1000 && checkAmount > 0) {
        alert(`خطا: مجموع مبالغ چک‌ها (${totalChecks.toLocaleString()} ریال) با مبلغ چک محاسبه شده (${checkAmount.toLocaleString()} ریال) مطابقت ندارد.`);
        return false;
    }
    
    return true;
}

// توابع AJAX برای افزودن قبر و آرامگاه
function addGrave() {
    if (!validateGraveForm()) {
        return;
    }
    
    const request_id = $('#request_id').val();
    const section_id = $('#section_id').val();
    const row_number = $('#row_number').val();
    const grave_number = $('#grave_number').val();
    const floor_count = $('#floor_count').val();
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>/modules/requests/new.php',
        type: 'POST',
        data: {
            action: 'add_grave',
            request_id: request_id,
            section_id: section_id,
            row_number: row_number,
            grave_number: grave_number,
            floor_count: floor_count
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('خطا: ' + (response.message || 'خطای ناشناخته'));
            }
        },
        error: function(xhr, status, error) {
            alert('خطا در ارتباط با سرور: ' + error);
            console.error(xhr.responseText);
        }
    });
}

function addTomb() {
    const request_id = $('#request_id').val();
    const tomb_id = $('#tomb_id').val();
    
    if (!tomb_id) {
        alert('لطفاً آرامگاه را انتخاب کنید.');
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>/modules/requests/new.php',
        type: 'POST',
        data: {
            action: 'add_tomb',
            request_id: request_id,
            tomb_id: tomb_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('خطا: ' + (response.message || 'خطای ناشناخته'));
            }
        },
        error: function(xhr, status, error) {
            alert('خطا در ارتباط با سرور: ' + error);
            console.error(xhr.responseText);
        }
    });
}

function deleteItem(item_id) {
    if (confirm('آیا از حذف این آیتم اطمینان دارید؟')) {
        $.ajax({
            url: '<?php echo BASE_URL; ?>/modules/requests/new.php',
            type: 'POST',
            data: {
                action: 'delete_item',
                item_id: item_id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا: ' + (response.message || 'خطای ناشناخته'));
                }
            },
            error: function(xhr, status, error) {
                alert('خطا در ارتباط با سرور: ' + error);
                console.error(xhr.responseText);
            }
        });
    }
}

function toggleFee(item_id, fee_type, checked, default_value) {
    let value = checked ? default_value : 0;
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>/modules/requests/new.php',
        type: 'POST',
        data: {
            action: 'update_fee',
            item_id: item_id,
            fee_type: fee_type,
            value: value
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('خطا: ' + (response.message || 'خطای ناشناخته'));
                location.reload();
            }
        },
        error: function(xhr, status, error) {
            alert('خطا در ارتباط با سرور: ' + error);
            location.reload();
        }
    });
}

// توابع مربوط به قبرها
function updateFloorInfo() {
    let select = document.getElementById('section_id');
    if (!select) return;
    
    let selectedOption = select.options[select.selectedIndex];
    let maxFloorsSpan = document.getElementById('max_floors_display');
    let floorInput = document.getElementById('floor_count');
    let floorError = document.getElementById('floor_error');
    
    if (!selectedOption || !selectedOption.value) {
        if (maxFloorsSpan) maxFloorsSpan.innerText = 'حداکثر طبقات: 0';
        if (floorInput) floorInput.max = 1;
        return;
    }
    
    let maxFloors = selectedOption.dataset.floorCount || 1;
    let prices = JSON.parse(selectedOption.dataset.prices || '{}');
    let sectionId = selectedOption.value;
    
    // ذخیره اطلاعات
    sectionsData[sectionId] = {
        maxFloors: maxFloors,
        prices: prices
    };
    
    if (maxFloorsSpan) maxFloorsSpan.innerText = 'حداکثر طبقات: ' + maxFloors;
    if (floorInput) {
        floorInput.max = maxFloors;
        
        // بررسی مقدار فعلی
        let currentFloor = parseInt(floorInput.value) || 1;
        if (currentFloor > maxFloors) {
            floorInput.value = maxFloors;
        }
    }
    
    updatePriceDisplay();
}

function updatePriceDisplay() {
    let select = document.getElementById('section_id');
    if (!select) return;
    
    let selectedOption = select.options[select.selectedIndex];
    let floorCount = parseInt(document.getElementById('floor_count')?.value) || 1;
    let priceDisplay = document.getElementById('price_display');
    let priceInfo = document.getElementById('price_info');
    let floorError = document.getElementById('floor_error');
    
    if (priceInfo) priceInfo.innerHTML = '';
    if (floorError) floorError.classList.add('d-none');
    
    if (!selectedOption || !selectedOption.value) {
        if (priceDisplay) priceDisplay.value = '۰ ریال';
        return;
    }
    
    let maxFloors = selectedOption.dataset.floorCount || 1;
    let prices = JSON.parse(selectedOption.dataset.prices || '{}');
    
    // بررسی حداکثر طبقات
    if (floorCount > maxFloors) {
        if (floorError) {
            floorError.classList.remove('d-none');
            floorError.innerText = `تعداد طبقات نمی‌تواند بیشتر از ${maxFloors} باشد`;
        }
        if (priceDisplay) priceDisplay.value = '۰ ریال';
        return;
    }
    
    // بررسی وجود قیمت
    if (prices[floorCount]) {
        let price = prices[floorCount];
        if (priceDisplay) priceDisplay.value = Number(price).toLocaleString() + ' ریال';
        if (priceInfo) priceInfo.innerHTML = '<span class="text-success">✓ قیمت موجود است</span>';
    } else {
        if (floorCount == 1) {
            if (priceDisplay) priceDisplay.value = '۰ ریال';
            if (priceInfo) priceInfo.innerHTML = '<span class="text-danger">⚠️ قیمت برای طبقه 1 تعریف نشده است</span>';
        } else {
            if (priceDisplay) priceDisplay.value = '۰ ریال';
            if (priceInfo) priceInfo.innerHTML = '<span class="text-warning">⚠️ قیمت برای این طبقه تعریف نشده است</span>';
        }
    }
}

function validateGraveForm() {
    let select = document.getElementById('section_id');
    if (!select) return false;
    
    let selectedOption = select.options[select.selectedIndex];
    let graveNumber = document.getElementById('grave_number')?.value;
    let floorCount = parseInt(document.getElementById('floor_count')?.value) || 1;
    
    if (!selectedOption || !selectedOption.value) {
        alert('لطفاً قطعه را انتخاب کنید');
        return false;
    }
    
    if (!graveNumber) {
        alert('لطفاً شماره قبر را وارد کنید');
        return false;
    }
    
    let maxFloors = selectedOption.dataset.floorCount || 1;
    if (floorCount > maxFloors) {
        alert(`تعداد طبقات نمی‌تواند بیشتر از ${maxFloors} باشد`);
        return false;
    }
    
    return true;
}

$(document).ready(function() {
    if (document.getElementById('check_count')) {
        generateCheckForms();
    }
    
    // جمع‌آوری اطلاعات قطعات
    $('#section_id option').each(function() {
        if (this.value) {
            let prices = $(this).data('prices') || {};
            sectionsData[this.value] = {
                maxFloors: $(this).data('floor-count') || 1,
                prices: prices
            };
        }
    });
    
    // رویدادهای فرم
    $('#section_id').on('change', updateFloorInfo);
    $('#floor_count').on('input', updatePriceDisplay);
    
    // مقداردهی اولیه
    if ($('#section_id').val()) {
        updateFloorInfo();
    }
    
    // اعتبارسنجی فرم مشتری
    $('#customerForm').on('submit', function(e) {
        const nationalCode = $('#national_code').val();
        if (nationalCode && nationalCode.length !== 10) {
            e.preventDefault();
            $('#nationalCodeError').text('کد ملی باید ۱۰ رقم باشد').show();
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
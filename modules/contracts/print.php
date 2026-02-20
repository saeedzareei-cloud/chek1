<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance()->getConnection();
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
    setMessage('شناسه درخواست نامعتبر است.', 'danger');
    redirect('/modules/requests/index.php');
}

// دریافت اطلاعات درخواست + مشتری + کاربران
$stmt = $db->prepare("
    SELECT r.*,
           c.full_name as customer_full_name,
           c.national_code as customer_national_code,
           c.father_name as customer_father_name,
           c.mobile as customer_mobile,
           c.address as customer_address,
           u1.full_name as registrar_name,
           u2.full_name as verifier_name,
           u1.signature_image as registrar_signature,
           u2.signature_image as verifier_signature
    FROM requests r
    JOIN customers c ON r.customer_id = c.id
    LEFT JOIN users u1 ON r.registrar_user_id = u1.id
    LEFT JOIN users u2 ON r.verifier_user_id = u2.id
    WHERE r.id = ? AND r.status = 'تایید شده'
");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    setMessage('درخواست یافت نشد یا تایید نشده است.', 'danger');
    redirect('/modules/requests/index.php');
}

// اقلام درخواست - اصلاح کوئری با بررسی وجود فیلدها
try {
    // ابتدا بررسی می‌کنیم که کدام فیلدها در جدول tombs وجود دارند
    $columns_query = $db->query("SHOW COLUMNS FROM tombs");
    $tomb_columns = [];
    if ($columns_query) {
        while ($col = $columns_query->fetch_assoc()) {
            $tomb_columns[] = $col['Field'];
        }
    }
    
    // ساخت کوئری پویا بر اساس فیلدهای موجود
    $tomb_fields = [];
    if (in_array('tomb_number', $tomb_columns)) $tomb_fields[] = 't.tomb_number';
    if (in_array('grave_count', $tomb_columns)) $tomb_fields[] = 't.grave_count';
    if (in_array('row_position', $tomb_columns)) $tomb_fields[] = 't.row_position as tomb_row_position';
    if (in_array('facade', $tomb_columns)) $tomb_fields[] = 't.facade';
    if (in_array('floor_count', $tomb_columns)) $tomb_fields[] = 't.floor_count';
    
    $tomb_fields_str = !empty($tomb_fields) ? ', ' . implode(', ', $tomb_fields) : '';
    
    // کوئری اصلی با فیلدهای موجود
    $query = "
        SELECT ri.*,
               s.name as section_name
               $tomb_fields_str
        FROM request_items ri
        LEFT JOIN sections s ON ri.section_id = s.id
        LEFT JOIN tombs t ON ri.tomb_id = t.id
        WHERE ri.request_id = ?
    ";
    
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        // اگر باز هم خطا داشت، کوئری ساده‌تر را امتحان کن
        error_log("Error in items query: " . $db->error);
        
        // کوئری ساده بدون فیلدهای tombs
        $stmt = $db->prepare("
            SELECT ri.*,
                   s.name as section_name
            FROM request_items ri
            LEFT JOIN sections s ON ri.section_id = s.id
            WHERE ri.request_id = ?
        ");
    }
    
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    // ذخیره اقلام در آرایه برای استفاده چندباره
    $items_array = [];
    while ($row = $items_result->fetch_assoc()) {
        $items_array[] = $row;
    }
    
} catch (Exception $e) {
    error_log("Exception in items query: " . $e->getMessage());
    setMessage('خطا در دریافت اقلام درخواست: ' . $e->getMessage(), 'danger');
    redirect('/modules/requests/view.php?id=' . $request_id);
}

// چک‌ها
$stmt = $db->prepare("SELECT * FROM checks WHERE request_id = ? ORDER BY due_date");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$checks_result = $stmt->get_result();

// ذخیره چک‌ها در آرایه برای استفاده چندباره
$checks_array = [];
while ($row = $checks_result->fetch_assoc()) {
    $checks_array[] = $row;
}

// تنظیمات (لوگو/مهر/متن قرارداد)
$settings = [];
$set_q = $db->query("SELECT setting_key, setting_value FROM settings");
if ($set_q) {
    while ($s = $set_q->fetch_assoc()) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
}

// بررسی دقیق مسیر لوگو
$logo_path = '';

// بررسی کلیدهای مختلف لوگو
if (!empty($settings['logo'])) {
    $logo_path = $settings['logo'];
} elseif (!empty($settings['logo_path'])) {
    $logo_path = $settings['logo_path'];
} elseif (!empty($settings['site_logo'])) {
    $logo_path = $settings['site_logo'];
} elseif (!empty($settings['logo_upload'])) {
    $logo_path = $settings['logo_upload'];
}

// اگر هیچکدام نبود، مسیر پیش‌فرض را امتحان کن
if (empty($logo_path)) {
    $default_logo_path = 'assets/uploads/logos/logo.png';
    if (file_exists(__DIR__ . '/../../' . $default_logo_path)) {
        $logo_path = $default_logo_path;
    }
}

// بررسی کلیدهای مختلف مهر
$stamp_path = '';
if (!empty($settings['stamp'])) {
    $stamp_path = $settings['stamp'];
} elseif (!empty($settings['stamp_path'])) {
    $stamp_path = $settings['stamp_path'];
} elseif (!empty($settings['stamp_upload'])) {
    $stamp_path = $settings['stamp_upload'];
}

$contract_text = $settings['contract_text'] ?? $settings['contract'] ?? '';

// امضاها
$registrar_signature = $request['registrar_signature'] ?? null;
$verifier_signature  = $request['verifier_signature'] ?? null;

// تاریخ‌ها
$printed_date = date('Y-m-d H:i:s');
$contract_date = jdate('Y/m/d', strtotime($request['created_at'] ?? $printed_date));

// محاسبه مجموع مبالغ
$total_base_price = 0;
$total_file_fee = 0;
$total_stone_fee = 0;
$total_sum = 0;

foreach ($items_array as $it) {
    $base_price = (float)$it['price'];
    $file_fee = (float)$it['file_creation_fee'];
    $stone_fee = (float)$it['stone_reservation_fee'];
    
    $total_base_price += $base_price;
    $total_file_fee += $file_fee;
    $total_stone_fee += $stone_fee;
    $total_sum += $base_price + $file_fee + $stone_fee;
}

// محاسبه مبالغ نقدی و چک
$cash_amount = $request['cash_amount'] ?? 0;
$check_amount = $request['check_amount'] ?? 0;
$cash_percent = $request['cash_percent'] ?? 0;

// نام سازمان از تنظیمات
$organization_name = $settings['organization_name'] ?? 'سازمان';

// تابع فرمت اعداد بدون ریال
function formatNumber($amount) {
    return number_format((float)$amount, 0, '.', ',');
}

// تابع جایگزینی متغیرها در متن قرارداد
function processContractText($text, $request, $total_sum) {
    if (empty($text)) return '';
    
    // فرمت مبلغ
    $total_amount_formatted = formatMoney($total_sum) . ' ریال';
    
    // آرایه جایگزینی
    $replacements = [
        '[خریدار]' => $request['customer_full_name'] ?? '',
        '[کد ملی]' => $request['customer_national_code'] ?? '',
        '[تاریخ]' => jdate('Y/m/d'),
        '[مبلغ]' => $total_amount_formatted
    ];
    
    // جایگزینی تمام متغیرها
    $processed_text = str_replace(array_keys($replacements), array_values($replacements), $text);
    
    return $processed_text;
}

// پردازش متن قرارداد
$processed_contract = processContractText($contract_text, $request, $total_sum);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپ قرارداد - <?php echo htmlspecialchars($request['request_number']); ?></title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap-icons.css">

    <style>
    @font-face {
        font-family: 'Vazir';
        src: url('<?php echo BASE_URL; ?>/assets/fonts/Vazir.woff2') format('woff2');
        font-weight: normal;
        font-style: normal;
    }
    
    /* استایل پایه */
    body { 
        font-family: 'Vazir', Tahoma, Arial, sans-serif; 
        background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        font-size: 14px;
        font-weight: 400;
        line-height: 1.6;
        color: #2c3e50;
        min-height: 100vh;
    }
    
    /* استایل صفحه */
    .page { 
        max-width: 950px; 
        margin: 30px auto; 
        padding: 30px 35px;
        position: relative;
        background: white;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08), 0 6px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    
    .page:hover {
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
    }
    
    .page-break { page-break-after: always; }
    @media print { 
        .page-break { page-break-after: always; }
        .page { 
            box-shadow: none; 
            margin: 0; 
            padding: 15px;
            border-radius: 0;
        }
    }
    
    /* هدر - لوگو سمت راست */
    .header { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        gap: 20px; 
        margin-bottom: 25px;
        position: relative;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
    }
    
    .logo-container { 
        width: 100px;
        text-align: right;
    }
    
    .header .logo { 
        width: 100px; 
        height: 100px; 
        object-fit: contain; 
        border-radius: 16px;
        background: #fff;
        padding: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: transform 0.3s ease;
    }
    
    .header .logo:hover {
        transform: scale(1.02);
    }
    
    .title { 
        flex: 1;
        text-align: center; 
    }
    
    .title h4 { 
        margin: 0 0 8px 0;
        font-size: 22px;
        font-weight: 700;
        color: #1e3c72;
        letter-spacing: -0.3px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .title h5 { 
        margin: 8px 0;
        font-size: 17px;
        font-weight: 500;
        color: #2c3e50;
        background: linear-gradient(135deg, #667eea10, #764ba210);
        display: inline-block;
        padding: 5px 20px;
        border-radius: 30px;
        letter-spacing: -0.2px;
    }
    
    .muted { 
        color: #64748b; 
        font-size: 13px; 
        font-weight: 400;
        background: #f8fafc;
        padding: 6px 12px;
        border-radius: 20px;
        display: inline-block;
        margin-top: 5px;
    }
    
    .spacer {
        width: 100px;
    }
    
    /* باکس‌ها */
    .box { 
        border: 1px solid #e9ecef;
        border-radius: 20px; 
        padding: 20px 22px; 
        margin-top: 20px; 
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        transition: all 0.3s ease;
    }
    
    .box:hover {
        border-color: #cbd5e1;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
    }
    
    .box h6 { 
        margin: 0 0 15px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1e3c72;
        border-bottom: 2px solid #f0f2f5;
        padding-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .box h6::before {
        content: '';
        width: 4px;
        height: 18px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 4px;
        display: inline-block;
    }
    
    /* اطلاعات خریدار */
    .row.g-2 div {
        font-size: 14px;
        font-weight: 400;
        line-height: 1.8;
        color: #475569;
        background: #f8fafc;
        padding: 8px 12px;
        border-radius: 12px;
        border: 1px solid #f0f2f5;
    }
    
    .row.g-2 strong {
        font-weight: 600;
        color: #1e3c72;
        margin-left: 5px;
    }
    
    /* استایل جدول اقلام */
    .items-table, .checks-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }
    
    .items-table th, .checks-table th {
        background: linear-gradient(135deg, #f8fafd, #f1f5f9) !important;
        color: #1e3c72 !important;
        text-align: center;
        white-space: nowrap;
        padding: 14px 10px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        border-bottom: 2px solid #d1d9e6;
    }
    
    .items-table td, .checks-table td {
        text-align: center;
        vertical-align: middle;
        padding: 12px 10px;
        font-size: 14px;
        font-weight: 400;
        color: #334155;
        border: 1px solid #ecf1f7;
        background: white;
    }
    
    .items-table td strong {
        font-weight: 600;
        color: #0d9488;
    }
    
    .description-cell {
        text-align: right;
        max-width: 320px;
        font-size: 13px;
        font-weight: 400;
        line-height: 1.7;
        color: #475569;
    }
    
    .type-badge {
        display: inline-block;
        padding: 5px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 500;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    
    .type-badge.grave {
        background: linear-gradient(135deg, #e0f2fe, #bae6fd);
        color: #0369a1;
    }
    
    .type-badge.tomb {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #166534;
    }
    
    .total-row {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }
    
    .total-row th {
        font-weight: 600;
        font-size: 14px;
        padding: 14px 10px;
        border: none;
        border-top: 2px solid #d1d9e6;
        color: #1e3c72;
    }
    
    .total-row th.fs-6 {
        font-size: 16px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        background: linear-gradient(135deg, #f1f5f9, #e9eef3);
    }
    
    /* استایل متن نقدی */
    .cash-text-box {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border: 1px solid #d9e2ef;
        border-radius: 18px;
        padding: 18px 22px;
        margin: 20px 0;
        text-align: right;
        font-size: 15px;
        line-height: 2;
        font-weight: 400;
        color: #334155;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .cash-amount {
        font-weight: 700;
        color: #0f172a;
        font-size: 18px;
        background: white;
        padding: 3px 12px;
        border-radius: 30px;
        border: 1px solid #cbd5e1;
        display: inline-block;
        margin: 0 5px;
    }
    
    .cash-text-box strong {
        font-weight: 600;
        color: #1e3c72;
    }
    
    /* استایل متن قرارداد */
    .contract-text {
        white-space: pre-wrap;
        line-height: 2;
        text-align: justify;
        padding: 20px;
        background: #f8fafc;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 400;
        color: #334155;
        border: 1px solid #e9eef3;
        box-shadow: inset 0 1px 4px rgba(0,0,0,0.02);
    }
    
    /* امضاها */
    .signature { 
        height: 70px; 
        object-fit: contain;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05));
        transition: all 0.3s ease;
    }
    
    .signature:hover {
        transform: scale(1.02);
    }
    
    .stamp { 
        width: 120px; 
        height: 120px; 
        object-fit: contain; 
        opacity: 0.9;
        filter: drop-shadow(0 8px 12px rgba(0,0,0,0.08));
        transition: all 0.3s ease;
    }
    
    .stamp:hover {
        opacity: 1;
        transform: rotate(2deg);
    }
    
    .col-md-4 div {
        font-size: 14px;
        font-weight: 400;
        margin: 8px 0;
        color: #475569;
    }
    
    .col-md-4 strong {
        font-weight: 600;
        font-size: 15px;
        color: #1e3c72;
        display: block;
        margin-bottom: 5px;
    }
    
    /* دکمه‌ها */
    .no-print { 
        margin: 20px auto; 
        text-align: center; 
        padding: 10px;
    }
    
    .btn {
        font-weight: 500;
        font-size: 14px;
        padding: 10px 24px;
        border-radius: 40px;
        transition: all 0.3s ease;
        margin: 0 5px;
        border: none;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }
    
    .btn-secondary {
        background: white;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    
    .btn-secondary:hover {
        background: #f8fafc;
        transform: translateY(-2px);
    }
    
    /* حذف کپی بج */
    .copy-badge {
        display: none !important;
    }
    
    /* ردیف‌های جدول با افکت هاور */
    .items-table tbody tr:hover td,
    .checks-table tbody tr:hover td {
        background: #f8fafc;
        transition: background 0.2s ease;
    }
    
    /* فوتر جدول */
    .table-light th {
        background: linear-gradient(135deg, #f1f5f9, #e9eef3) !important;
        color: #1e3c72 !important;
        font-weight: 600;
    }
    
    @media print {
        .no-print { display: none !important; }
        .page { 
            max-width: 100%; 
            margin: 0; 
            padding: 15px;
            box-shadow: none;
        }
        .box { 
            border-color: #ddd; 
            box-shadow: none;
            break-inside: avoid;
        }
        body { 
            font-size: 12px; 
            background: white;
        }
        .items-table td, .items-table th,
        .checks-table td, .checks-table th { 
            font-size: 12px; 
        }
        .type-badge {
            box-shadow: none;
        }
    }
</style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> چاپ
        </button>
        <a href="<?php echo BASE_URL; ?>/modules/requests/view.php?id=<?php echo (int)$request_id; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <?php
$copies = [
    'مالی',
    'درخواست کننده',
    'متوفیات'
];

// محاسبه جمع کل چک‌ها یکبار برای همه نسخه‌ها
$total_check_sum = 0;
foreach ($checks_array as $ch) {
    $total_check_sum += (float)$ch['amount'];
}

foreach($copies as $ci => $copy_title): 
?>
<div class="page <?php echo ($ci < count($copies)-1) ? 'page-break' : ''; ?>">
        <!-- هدر با لوگو سمت راست - عنوان جدید -->
        <div class="header">
            <div class="logo-container">
                <?php 
                // نمایش لوگو اگر وجود داشته باشد
                if (!empty($logo_path)): 
                    $full_logo_path = __DIR__ . '/../../' . $logo_path;
                    if (file_exists($full_logo_path)): 
                ?>
                        <img class="logo" src="<?php echo BASE_URL . '/' . $logo_path; ?>" alt="لوگو">
                    <?php else: ?>
                        <!-- اگر فایل لوگو وجود نداشت، آیکون پیش‌فرض نمایش بده -->
                        <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #f1f5f9, #e9eef3); border-radius: 16px; display: flex; align-items: center; justify-content: center; border: 1px dashed #94a3b8;">
                            <i class="bi bi-building" style="font-size: 45px; color: #64748b;"></i>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- اگر لوگو در تنظیمات ثبت نشده بود -->
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #f1f5f9, #e9eef3); border-radius: 16px; display: flex; align-items: center; justify-content: center; border: 1px dashed #94a3b8;">
                        <i class="bi bi-building" style="font-size: 45px; color: #64748b;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="title">
                <h4>سازمان مدیریت آرامستانهای شهرداری کرج</h4>
                <h5>رسید وجه نقد و اقساط</h5>
                <div class="muted">
                    <i class="bi bi-file-text"></i> شماره: <strong><?php echo htmlspecialchars($request['request_number']); ?></strong> 
                    <i class="bi bi-calendar3 ms-2"></i> تاریخ: <strong><?php echo jdate('Y/m/d H:i', strtotime($printed_date)); ?></strong>
                </div>
            </div>
            <div class="spacer"></div>
        </div>

        <div class="box">
            <h6><i class="bi bi-person-badge"></i> اطلاعات خریدار</h6>
            <div class="row g-2">
                <div class="col-md-6"><i class="bi bi-person"></i> <strong>نام و نام خانوادگی:</strong> <?php echo htmlspecialchars($request['customer_full_name']); ?></div>
                <div class="col-md-6"><i class="bi bi-card-text"></i> <strong>کد ملی:</strong> <?php echo htmlspecialchars($request['customer_national_code']); ?></div>
                <div class="col-md-6"><i class="bi bi-people"></i> <strong>نام پدر:</strong> <?php echo htmlspecialchars($request['customer_father_name']); ?></div>
                <div class="col-md-6"><i class="bi bi-phone"></i> <strong>موبایل:</strong> <?php echo htmlspecialchars($request['customer_mobile']); ?></div>
                <div class="col-12"><i class="bi bi-geo-alt"></i> <strong>آدرس:</strong> <?php echo htmlspecialchars($request['customer_address']); ?></div>
            </div>
        </div>

        <div class="box">
            <h6><i class="bi bi-cart-check"></i> اطلاعات خرید</h6>
            <table class="table table-bordered items-table">
                <thead>
                    <tr>
                        <th>نوع</th>
                        <th>شرح</th>
                        <th>قیمت پایه</th>
                        <th>تشکیل پرونده</th>
                        <th>سنگ رزرو</th>
                        <th>جمع کل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $i = 1;
                        
                        if (!empty($items_array)):
                            foreach($items_array as $it):
                                $base_price = (float)$it['price'];
                                $file_fee = (float)$it['file_creation_fee'];
                                $stone_fee = (float)$it['stone_reservation_fee'];
                                $row_total = $base_price + $file_fee + $stone_fee;
                                
                                // تعیین نوع
                                $type = ($it['item_type'] ?? '') ?: (isset($it['tomb_id']) && !empty($it['tomb_id']) ? 'آرامگاه' : 'قبر');
                                $typeClass = ($type == 'آرامگاه') ? 'tomb' : 'grave';
                                
                                // ساخت شرح مانند فرم ثبت درخواست
                                $description = '';
                                $floor_count = $it['floor_count'] ?? ($it['floor_count'] ?? 1);
                                
                                if ($type == 'آرامگاه') {
                                    $tomb_number = $it['tomb_number'] ?? ($it['tomb_number'] ?? '');
                                    $description = "آرامگاه: " . ($tomb_number ?: '-');
                                    if (!empty($it['section_name'])) {
                                        $description = "قطعه " . $it['section_name'] . " - " . $description;
                                    }
                                } else {
                                    $description = "قطعه: " . ($it['section_name'] ?? '-');
                                    
                                    if (!empty($it['row_number'])) {
                                        $description .= " - ردیف: " . $it['row_number'];
                                    }
                                    
                                    if (!empty($it['grave_number'])) {
                                        $description .= " - شماره: " . $it['grave_number'];
                                    }
                                }
                                
                                // اضافه کردن تعداد طبقات
                                if (!empty($it['floor_count_from_items'])) {
                                    $floor_count = $it['floor_count_from_items'];
                                } elseif (!empty($it['floor_count'])) {
                                    $floor_count = $it['floor_count'];
                                }
                                
                                if (!empty($floor_count) && $floor_count > 1) {
                                    $description .= " (" . $floor_count . " طبقه)";
                                } elseif (!empty($floor_count) && $floor_count == 1) {
                                    $description .= " (1 طبقه)";
                                }
                                
                                // اضافه کردن نما اگر وجود داشته باشد
                                if (!empty($it['facade'])) {
                                    $description .= " - نما: " . $it['facade'];
                                }
                    ?>
                    <tr>
                        <td>
                            <span class="type-badge <?php echo $typeClass; ?>">
                                <?php echo htmlspecialchars($type); ?>
                            </span>
                        </td>
                        <td class="description-cell text-right"><?php echo htmlspecialchars($description); ?></td>
                        <td class="text-center"><?php echo formatNumber($base_price); ?></td>
                        <td class="text-center"><?php echo formatNumber($file_fee); ?></td>
                        <td class="text-center"><?php echo formatNumber($stone_fee); ?></td>
                        <td class="text-center"><strong><?php echo formatNumber($row_total); ?></strong></td>
                    </tr>
                    <?php 
                            endforeach;
                        else: 
                    ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <h5>هیچ قبر یا آرامگاهی انتخاب نشده است</h5>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="5" style="text-align: left;">جمع کل:</th>
                        <th class="text-center fs-6"><?php echo formatMoney($total_sum); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- متن نقدی به فرمت جدید -->
        <div class="cash-text-box">
            <p class="mb-0">
                <i class="bi bi-cash-coin"></i> خریدار در تاریخ 
                <strong><?php echo $contract_date; ?></strong> 
                مبلغ 
                <span class="cash-amount"><?php echo formatNumber($cash_amount); ?></span> 
                ریال به حساب <?php echo htmlspecialchars($organization_name); ?> واریز کردند.
            </p>
        </div>

        <div class="box">
            <h6><i class="bi bi-receipt"></i> اسناد دریافتی</h6>
            <table class="table table-bordered checks-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>شماره چک</th>
                        <th>بانک</th>
                        <th>مالک چک</th>
                        <th>کد ملی</th>
                        <th>موبایل</th>
                        <th>تاریخ سررسید</th>
                        <th>مبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $j = 1;
                        if (!empty($checks_array)):
                            foreach($checks_array as $ch):
                                // تبدیل تاریخ میلادی به شمسی
                                $due_date_shamsi = '';
                                if (!empty($ch['due_date']) && $ch['due_date'] != '0000-00-00') {
                                    $due_date_shamsi = jdate('Y/m/d', strtotime($ch['due_date']));
                                }
                    ?>
                    <tr>
                        <td><?php echo $j++; ?></td>
                        <td><?php echo htmlspecialchars($ch['check_number']); ?></td>
                        <td><?php echo htmlspecialchars($ch['bank_name']); ?></td>
                        <td><?php echo htmlspecialchars($ch['drawer_full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($ch['drawer_national_code'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($ch['drawer_mobile'] ?? ''); ?></td>
                        <td><?php echo $due_date_shamsi; ?></td>
                        <td class="text-center"><?php echo formatNumber($ch['amount']); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-file-earmark-text fs-2 d-block mb-2"></i>
                                هیچ سند دریافتی ثبت نشده است (پرداخت نقدی کامل)
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($checks_array)): ?>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="7" class="text-end">جمع اسناد دریافتی</th>
                        <th class="text-center"><?php echo formatMoney($total_check_sum); ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <div class="box">
            <h6><i class="bi bi-file-text"></i> متن قرارداد و تعهدات</h6>
            <?php if (!empty($processed_contract)): ?>
                <div class="contract-text"><?php echo nl2br(htmlspecialchars($processed_contract)); ?></div>
            <?php else: ?>
                <div class="text-muted">متن قرارداد در تنظیمات → متن قرارداد قابل تعریف است.</div>
            <?php endif; ?>
        </div>

        <div class="box">
            <div class="row text-center g-3 align-items-end">
                <div class="col-md-4">
                    <div><strong>ثبت‌کننده</strong></div>
                    <div class="muted"><?php echo htmlspecialchars($request['registrar_name'] ?? ''); ?></div>
                    <div style="height: 80px; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($registrar_signature) && file_exists(__DIR__ . '/../../' . $registrar_signature)): ?>
                            <img class="signature" src="<?php echo BASE_URL . '/' . $registrar_signature; ?>" alt="امضا ثبت‌کننده">
                        <?php else: ?>
                            <div style="width: 100px; height: 60px; border-bottom: 2px solid #cbd5e1;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="muted">امضاء</div>
                </div>
                <div class="col-md-4">
                    <?php 
                    // نمایش مهر اگر وجود داشته باشد
                    if (!empty($stamp_path)): 
                        $full_stamp_path = __DIR__ . '/../../' . $stamp_path;
                        if (file_exists($full_stamp_path)): 
                    ?>
                        <img class="stamp" src="<?php echo BASE_URL . '/' . $stamp_path; ?>" alt="مهر">
                    <?php 
                        else: 
                    ?>
                        <div style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-stamp" style="font-size: 50px; color: #94a3b8;"></i>
                        </div>
                    <?php 
                        endif;
                    else: 
                    ?>
                        <div style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-stamp" style="font-size: 50px; color: #94a3b8;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div><strong>تاییدکننده</strong></div>
                    <div class="muted"><?php echo htmlspecialchars($request['verifier_name'] ?? ''); ?></div>
                    <div style="height: 80px; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($verifier_signature) && file_exists(__DIR__ . '/../../' . $verifier_signature)): ?>
                            <img class="signature" src="<?php echo BASE_URL . '/' . $verifier_signature; ?>" alt="امضا تاییدکننده">
                        <?php else: ?>
                            <div style="width: 100px; height: 60px; border-bottom: 2px solid #cbd5e1;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="muted">امضاء</div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

    <script src="<?php echo BASE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
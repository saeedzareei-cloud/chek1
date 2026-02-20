<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/index.php';</script>";
    exit;
}

$db = Database::getInstance()->getConnection();
$active_tab = $_GET['tab'] ?? 'general';
$error_message = '';

// اطمینان از وجود جدول بانک‌ها
$db->query("CREATE TABLE IF NOT EXISTS banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

// پردازش عملیات بانک‌ها
if ($active_tab === 'banks' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['bank_action'] ?? '';
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_id = (int)($_POST['bank_id'] ?? 0);

    if ($action === 'add') {
        if (empty($bank_name)) {
            $error_message = 'نام بانک را وارد کنید.';
        } else {
            $stmt = $db->prepare("INSERT INTO banks (name, is_active) VALUES (?, 1)");
            $stmt->bind_param('s', $bank_name);
            if ($stmt->execute()) {
                setMessage('بانک با موفقیت اضافه شد.', 'success');
                redirect('/modules/settings/index.php?tab=banks');
            } else {
                $error_message = 'خطا در افزودن بانک (ممکن است تکراری باشد).';
            }
        }
    } elseif ($action === 'update') {
        if ($bank_id <= 0 || empty($bank_name)) {
            $error_message = 'اطلاعات بانک برای ویرایش کامل نیست.';
        } else {
            $stmt = $db->prepare("UPDATE banks SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $bank_name, $bank_id);
            if ($stmt->execute()) {
                setMessage('بانک با موفقیت ویرایش شد.', 'success');
                redirect('/modules/settings/index.php?tab=banks');
            } else {
                $error_message = 'خطا در ویرایش بانک (ممکن است نام تکراری باشد).';
            }
        }
    } elseif ($action === 'delete') {
        if ($bank_id <= 0) {
            $error_message = 'شناسه بانک نامعتبر است.';
        } else {
            $stmt = $db->prepare("DELETE FROM banks WHERE id = ?");
            $stmt->bind_param('i', $bank_id);
            if ($stmt->execute()) {
                setMessage('بانک حذف شد.', 'success');
                redirect('/modules/settings/index.php?tab=banks');
            } else {
                $error_message = 'خطا در حذف بانک.';
            }
        }
    } elseif ($action === 'toggle') {
        if ($bank_id <= 0) {
            $error_message = 'شناسه بانک نامعتبر است.';
        } else {
            $stmt = $db->prepare("UPDATE banks SET is_active = IF(is_active=1,0,1) WHERE id = ?");
            $stmt->bind_param('i', $bank_id);
            if ($stmt->execute()) {
                setMessage('وضعیت بانک بروزرسانی شد.', 'success');
                redirect('/modules/settings/index.php?tab=banks');
            } else {
                $error_message = 'خطا در بروزرسانی وضعیت بانک.';
            }
        }
    }
}

// تابع تبدیل اعداد فارسی به انگلیسی
function convertPersianToEnglish($string) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    $string = str_replace($persian, $english, $string);
    $string = str_replace($arabic, $english, $string);
    
    return $string;
}

// تابع بررسی و ایجاد پوشه
function ensureDirectoryExists($path) {
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    return is_writable($path);
}

// تابع آپلود فایل
function uploadFile($file, $key) {
    $upload_dir = __DIR__ . '/../../assets/uploads/';
    
    // تعیین پوشه بر اساس نوع فایل
    if (strpos($key, 'logo') !== false) {
        $target_dir = $upload_dir . 'logos/';
        $db_path = 'assets/uploads/logos/';
    } elseif (strpos($key, 'stamp') !== false || strpos($key, 'مهر') !== false) {
        $target_dir = $upload_dir . 'stamps/';
        $db_path = 'assets/uploads/stamps/';
    } elseif (strpos($key, 'signature') !== false || strpos($key, 'امضا') !== false) {
        $target_dir = $upload_dir . 'signatures/';
        $db_path = 'assets/uploads/signatures/';
    } else {
        $target_dir = $upload_dir . 'others/';
        $db_path = 'assets/uploads/others/';
    }
    
    // ایجاد پوشه اگر وجود ندارد
    if (!ensureDirectoryExists($target_dir)) {
        return ['success' => false, 'message' => 'خطا در ایجاد پوشه مورد نظر'];
    }
    
    // اعتبارسنجی نوع فایل
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_type = $file['type'];
    
    // بررسی نوع فایل
    $type_valid = false;
    foreach ($allowed_types as $allowed_type) {
        if (strpos($file_type, $allowed_type) !== false) {
            $type_valid = true;
            break;
        }
    }
    
    if (!in_array($file_extension, $allowed_extensions) || !$type_valid) {
        return ['success' => false, 'message' => 'فرمت فایل مجاز نیست. فقط jpg، png و gif مجاز هستند.'];
    }
    
    // اعتبارسنجی حجم فایل (حداکثر 2 مگابایت)
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم فایل نباید بیشتر از 2 مگابایت باشد.'];
    }
    
    // ایجاد نام یکتا برای فایل
    $file_name = time() . '_' . uniqid() . '.' . $file_extension;
    $target_path = $target_dir . $file_name;
    
    // آپلود فایل
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // ذخیره مسیر نسبی در دیتابیس
        $relative_path = $db_path . $file_name;
        return ['success' => true, 'path' => $relative_path];
    } else {
        return ['success' => false, 'message' => 'خطا در آپلود فایل'];
    }
}

// پردازش درخواست‌های AJAX برای روزهای تعطیل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] == 'add_holiday') {
        $date = $_POST['date'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // تبدیل تاریخ از فارسی به انگلیسی
        $date = convertPersianToEnglish($date);
        
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'تاریخ وارد نشده است']);
            exit;
        }
        
        // بررسی فرمت تاریخ
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'فرمت تاریخ نامعتبر است']);
            exit;
        }
        
        // تبدیل تاریخ شمسی به میلادی
        [$jy, $jm, $jd] = array_map('intval', explode('/', $date));
        if (!function_exists('jalali_to_gregorian')) {
            require_once __DIR__ . '/../../includes/jdf.php';
        }
        $gregorian_date = jalali_to_gregorian($jy, $jm, $jd, '-');

        // بررسی تکراری نبودن
        $check = $db->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
        $check->bind_param('s', $gregorian_date);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'این تاریخ قبلاً ثبت شده است']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $gregorian_date, $description);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در ثبت: ' . $db->error]);
        }
        exit;
    }
    
    if ($_POST['ajax_action'] == 'delete_holiday') {
        $id = (int)$_POST['id'];
        
        $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در حذف']);
        }
        exit;
    }
}

// ذخیره تنظیمات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax_action']) && !isset($_POST['bank_action'])) {
    $has_file_upload = false;
    
    // ابتدا فایل‌ها را پردازش کنیم
    foreach ($_FILES as $key => $file) {
        if ($file['error'] == 0) {
            $has_file_upload = true;
            $upload_result = uploadFile($file, $key);
            
            if ($upload_result['success']) {
                // تعیین کلید مناسب برای ذخیره در دیتابیس
                if (strpos($key, 'logo') !== false) {
                    $db_key = 'logo';
                } elseif (strpos($key, 'stamp') !== false) {
                    $db_key = 'stamp';
                } elseif (strpos($key, 'signature') !== false) {
                    $db_key = 'signature';
                } else {
                    $db_key = $key;
                }
                
                // بروزرسانی یا درج تنظیم
                $check = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
                $check->bind_param('s', $db_key);
                $check->execute();
                $check->store_result();
                
                if ($check->num_rows > 0) {
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->bind_param('ss', $upload_result['path'], $db_key);
                } else {
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text')");
                    $stmt->bind_param('ss', $db_key, $upload_result['path']);
                }
                
                if (!$stmt->execute()) {
                    $error_message = 'خطا در ذخیره تنظیمات: ' . $db->error;
                }
            } else {
                $error_message = $upload_result['message'];
            }
        }
    }
    
    // سپس فیلدهای متنی را پردازش کنیم
    foreach ($_POST as $key => $value) {
        if ($key != 'tab' && !isset($_FILES[$key])) {
            // بروزرسانی یا درج تنظیم
            $check = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $check->bind_param('s', $key);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param('ss', $value, $key);
            } else {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text')");
                $stmt->bind_param('ss', $key, $value);
            }
            
            if (!$stmt->execute()) {
                $error_message = 'خطا در ذخیره تنظیمات: ' . $db->error;
            }
        }
    }
    
    if (empty($error_message)) {
        if ($has_file_upload) {
            setMessage('تصاویر با موفقیت آپلود و تنظیمات ذخیره شد.', 'success');
        } else {
            setMessage('تنظیمات با موفقیت ذخیره شد.', 'success');
        }
    } else {
        setMessage($error_message, 'danger');
    }
    
    echo "<script>window.location.href = '" . BASE_URL . "/modules/settings/index.php?tab=" . $active_tab . "';</script>";
    exit;
}

// دریافت تنظیمات
$settings = [];
$result = $db->query("SELECT * FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$page_title = 'تنظیمات سیستم';
$header_icon = 'gear';

include '../../includes/header.php';
?>

<!-- تب‌های تنظیمات -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-sliders2"></i>
        تنظیمات سیستم
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab == 'general' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=general">
                    <i class="bi bi-gear"></i> تنظیمات عمومی
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab == 'fees' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=fees">
                    <i class="bi bi-cash-stack"></i> هزینه‌ها
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab == 'images' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=images">
                    <i class="bi bi-image"></i> تصاویر
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab == 'banks' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=banks">
                    <i class="bi bi-bank"></i> بانک‌ها
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab == 'holidays' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=holidays">
                    <i class="bi bi-calendar-x"></i> روزهای تعطیل
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab == 'contract' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=contract">
                    <i class="bi bi-file-text"></i> متن قرارداد
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- نمایش پیام خطا -->
<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- نمایش پیام موفقیت -->
<?php echo displayMessage(); ?>

<!-- فرم تنظیمات -->
<div class="card">
    <div class="card-header bg-success text-white">
        <i class="bi bi-pencil-square"></i>
        <?php
        $tab_titles = [
            'general' => 'تنظیمات عمومی',
            'fees' => 'مدیریت هزینه‌ها',
            'images' => 'مدیریت تصاویر',
            'holidays' => 'مدیریت روزهای تعطیل',
            'banks' => 'مدیریت بانک‌ها',
            'contract' => 'مدیریت متن قرارداد'
        ];
        echo $tab_titles[$active_tab] ?? 'تنظیمات';
        ?>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
            
            <?php if ($active_tab == 'general'): ?>
                <!-- تنظیمات عمومی -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="organization_name" class="form-label">
                            <i class="bi bi-building text-primary"></i>
                            نام سازمان
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="organization_name" 
                               name="organization_name" 
                               value="<?php echo htmlspecialchars($settings['organization_name'] ?? 'سازمان مدیریت آرامستانهای شهرداری کرج'); ?>">
                        <small class="form-text text-muted">نامی که در سربرگ قراردادها نمایش داده می‌شود</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="site_title" class="form-label">
                            <i class="bi bi-tag text-primary"></i>
                            عنوان سایت
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="site_title" 
                               name="site_title" 
                               value="<?php echo htmlspecialchars($settings['site_title'] ?? 'سیستم فروش اقساطی قبور'); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="address" class="form-label">
                            <i class="bi bi-geo-alt text-primary"></i>
                            آدرس
                        </label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="2"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">
                            <i class="bi bi-telephone text-primary"></i>
                            تلفن تماس
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                    </div>
                </div>
                
            <?php elseif ($active_tab == 'fees'): ?>
                <!-- هزینه‌ها -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="file_creation_fee" class="form-label">
                            <i class="bi bi-file-text text-primary"></i>
                            هزینه تشکیل پرونده (ریال)
                        </label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control price-format" 
                                   id="file_creation_fee" 
                                   name="file_creation_fee" 
                                   value="<?php echo number_format($settings['file_creation_fee'] ?? 500000); ?>">
                            <span class="input-group-text bg-primary text-white">ریال</span>
                        </div>
                        <small class="form-text text-muted">هزینه تشکیل پرونده برای قبور با بیش از یک طبقه</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="stone_reservation_fee" class="form-label">
                            <i class="bi bi-gem text-primary"></i>
                            هزینه سنگ رزرو (ریال)
                        </label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control price-format" 
                                   id="stone_reservation_fee" 
                                   name="stone_reservation_fee" 
                                   value="<?php echo number_format($settings['stone_reservation_fee'] ?? 300000); ?>">
                            <span class="input-group-text bg-primary text-white">ریال</span>
                        </div>
                        <small class="form-text text-muted">هزینه رزرو سنگ برای قبور با بیش از یک طبقه</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="tax_percent" class="form-label">
                            <i class="bi bi-percent text-primary"></i>
                            درصد مالیات
                        </label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   id="tax_percent" 
                                   name="tax_percent" 
                                   value="<?php echo $settings['tax_percent'] ?? 0; ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.1">
                            <span class="input-group-text bg-primary text-white">%</span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($active_tab == 'images'): ?>
                <!-- بخش مدیریت تصاویر اصلاح شده -->
                <div class="row">
                    <!-- کارت لوگو -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-image"></i> لوگو
                            </div>
                            <div class="card-body text-center">
                                <?php 
                                $logo_value = $settings['logo'] ?? '';
                                
                                if (!empty($logo_value) && file_exists(__DIR__ . '/../../' . $logo_value)) {
                                ?>
                                    <div class="mb-3">
                                        <img src="<?php echo BASE_URL; ?>/<?php echo $logo_value; ?>" 
                                             class="img-fluid" 
                                             style="max-height: 100px; border: 1px solid #ddd; border-radius: 8px; padding: 5px;"
                                             alt="لوگو">
                                        <div class="mt-2">
                                            <small class="text-success">لوگوی فعلی</small>
                                        </div>
                                    </div>
                                <?php 
                                } else {
                                ?>
                                    <div class="mb-3 text-muted">
                                        <i class="bi bi-image" style="font-size: 4rem;"></i>
                                        <p>لوگویی آپلود نشده است</p>
                                    </div>
                                <?php } ?>
                                
                                <label for="logo_upload" class="btn btn-primary w-100">
                                    <i class="bi bi-upload"></i> انتخاب لوگوی جدید
                                </label>
                                <input type="file" 
                                       class="d-none" 
                                       id="logo_upload" 
                                       name="logo_upload" 
                                       accept=".jpg,.jpeg,.png,.gif" 
                                       onchange="previewImage(this, 'logo-preview')">
                                <small class="form-text text-muted d-block mt-2">فرمت‌های مجاز: jpg, png, gif</small>
                                <div id="logo-preview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- کارت مهر -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-stamp"></i> مهر
                            </div>
                            <div class="card-body text-center">
                                <?php 
                                $stamp_value = $settings['stamp'] ?? '';
                                
                                if (!empty($stamp_value) && file_exists(__DIR__ . '/../../' . $stamp_value)) {
                                ?>
                                    <div class="mb-3">
                                        <img src="<?php echo BASE_URL; ?>/<?php echo $stamp_value; ?>" 
                                             class="img-fluid" 
                                             style="max-height: 100px; border: 1px solid #ddd; border-radius: 8px; padding: 5px;"
                                             alt="مهر">
                                        <div class="mt-2">
                                            <small class="text-success">مهر فعلی</small>
                                        </div>
                                    </div>
                                <?php 
                                } else {
                                ?>
                                    <div class="mb-3 text-muted">
                                        <i class="bi bi-stamp" style="font-size: 4rem;"></i>
                                        <p>مهری آپلود نشده است</p>
                                    </div>
                                <?php } ?>
                                
                                <label for="stamp_upload" class="btn btn-primary w-100">
                                    <i class="bi bi-upload"></i> انتخاب مهر جدید
                                </label>
                                <input type="file" 
                                       class="d-none" 
                                       id="stamp_upload" 
                                       name="stamp_upload" 
                                       accept=".jpg,.jpeg,.png" 
                                       onchange="previewImage(this, 'stamp-preview')">
                                <small class="form-text text-muted d-block mt-2">فرمت‌های مجاز: jpg, png</small>
                                <div id="stamp-preview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- کارت امضا -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-pencil"></i> امضا
                            </div>
                            <div class="card-body text-center">
                                <?php 
                                $signature_value = $settings['signature'] ?? '';
                                
                                if (!empty($signature_value) && file_exists(__DIR__ . '/../../' . $signature_value)) {
                                ?>
                                    <div class="mb-3">
                                        <img src="<?php echo BASE_URL; ?>/<?php echo $signature_value; ?>" 
                                             class="img-fluid" 
                                             style="max-height: 100px; border: 1px solid #ddd; border-radius: 8px; padding: 5px;"
                                             alt="امضا">
                                        <div class="mt-2">
                                            <small class="text-success">امضای فعلی</small>
                                        </div>
                                    </div>
                                <?php 
                                } else {
                                ?>
                                    <div class="mb-3 text-muted">
                                        <i class="bi bi-pencil" style="font-size: 4rem;"></i>
                                        <p>امضایی آپلود نشده است</p>
                                    </div>
                                <?php } ?>
                                
                                <label for="signature_upload" class="btn btn-primary w-100">
                                    <i class="bi bi-upload"></i> انتخاب امضای جدید
                                </label>
                                <input type="file" 
                                       class="d-none" 
                                       id="signature_upload" 
                                       name="signature_upload" 
                                       accept=".jpg,.jpeg,.png" 
                                       onchange="previewImage(this, 'signature-preview')">
                                <small class="form-text text-muted d-block mt-2">فرمت‌های مجاز: jpg, png</small>
                                <div id="signature-preview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>نکته:</strong> پس از آپلود تصویر، صفحه رفرش می‌شود و تصویر جدید نمایش داده می‌شود.
                </div>
                
                <!-- گالری تصاویر آپلود شده -->
                <div class="col-12 mt-4">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <i class="bi bi-images"></i> گالری تصاویر آپلود شده
                        </div>
                        <div class="card-body">
                            <?php
                            $gallery_dirs = [
                                'لوگوها' => 'assets/uploads/logos',
                                'مهرها' => 'assets/uploads/stamps',
                                'امضاها' => 'assets/uploads/signatures',
                                'سایر' => 'assets/uploads/others'
                            ];

                            $allowed_ext = ['jpg','jpeg','png','gif'];

                            foreach ($gallery_dirs as $title => $rel_dir):
                                $abs_dir = __DIR__ . '/../../' . $rel_dir;
                                $files = [];
                                if (is_dir($abs_dir)) {
                                    $scan = scandir($abs_dir);
                                    foreach ($scan as $f) {
                                        if ($f === '.' || $f === '..') continue;
                                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                        if (in_array($ext, $allowed_ext, true)) {
                                            $files[] = $rel_dir . '/' . $f;
                                        }
                                    }
                                }
                                // مرتب‌سازی جدیدترها بالا
                                usort($files, function($a, $b) {
                                    $pa = __DIR__ . '/../../' . $a;
                                    $pb = __DIR__ . '/../../' . $b;
                                    return (@filemtime($pb) ?: 0) <=> (@filemtime($pa) ?: 0);
                                });
                            ?>
                                <div class="mb-4">
                                    <h6 class="mb-2"><?php echo htmlspecialchars($title); ?></h6>
                                    <?php if (!empty($files)): ?>
                                        <div class="row g-3">
                                            <?php foreach ($files as $path): ?>
                                                <div class="col-6 col-md-3 col-lg-2">
                                                    <div class="border rounded p-2 h-100 text-center">
                                                        <a href="<?php echo BASE_URL; ?>/<?php echo $path; ?>" target="_blank" style="text-decoration:none;">
                                                            <img src="<?php echo BASE_URL; ?>/<?php echo $path; ?>"
                                                                 class="img-fluid"
                                                                 style="max-height: 110px; object-fit: contain;"
                                                                 alt="image"
                                                                 onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>/assets/img/no-image.png';">
                                                            <div class="small text-muted mt-2" style="word-break: break-all;">
                                                                <?php echo htmlspecialchars(basename($path)); ?>
                                                            </div>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">تصویری در این بخش وجود ندارد.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i>
                                بعد از آپلود، تصاویر همین‌جا نمایش داده می‌شوند. اگر تصویر نمایش داده نشد، مسیر پوشه و سطح دسترسی فایل‌ها را بررسی کنید.
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_tab == 'banks'): ?>
                <!-- بخش بانک‌ها -->
                <?php
                    $banks_res = $db->query("SELECT id, name, is_active, created_at FROM banks ORDER BY name ASC");
                ?>
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <i class="bi bi-plus-circle"></i> افزودن بانک جدید
                            </div>
                            <div class="card-body">
                                <form method="post" action="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=banks">
                                    <input type="hidden" name="bank_action" value="add">
                                    <div class="mb-3">
                                        <label class="form-label">نام بانک</label>
                                        <input type="text" name="bank_name" class="form-control" required maxlength="150" placeholder="مثلاً: ملی">
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check2"></i> ثبت
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            بانک‌های «غیرفعال» در زمان ثبت چک نمایش داده نمی‌شوند.
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card border">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div><i class="bi bi-bank"></i> لیست بانک‌ها</div>
                                <div class="small text-muted">
                                    <?php echo $banks_res ? $banks_res->num_rows : 0; ?> مورد
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width:60px;">#</th>
                                                <th>نام بانک</th>
                                                <th style="width:120px;">وضعیت</th>
                                                <th style="width:220px;">عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($banks_res && $banks_res->num_rows > 0): ?>
                                                <?php $i=1; while($b = $banks_res->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo $i++; ?></td>
                                                        <td><?php echo htmlspecialchars($b['name']); ?></td>
                                                        <td>
                                                            <?php if ((int)$b['is_active'] === 1): ?>
                                                                <span class="badge bg-success">فعال</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">غیرفعال</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1 align-items-center">
                                                                <form method="post" action="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=banks" class="d-inline">
                                                                    <input type="hidden" name="bank_action" value="toggle">
                                                                    <input type="hidden" name="bank_id" value="<?php echo (int)$b['id']; ?>">
                                                                    <button type="submit" class="btn btn-outline-primary btn-sm" title="تغییر وضعیت">
                                                                        <i class="bi bi-arrow-repeat"></i>
                                                                    </button>
                                                                </form>

                                                                <button type="button" class="btn btn-outline-warning btn-sm" title="ویرایش" data-bs-toggle="modal" data-bs-target="#editBankModal<?php echo (int)$b['id']; ?>">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>

                                                                <form method="post" action="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=banks" class="d-inline" onsubmit="return confirm('آیا از حذف این بانک مطمئن هستید؟');">
                                                                    <input type="hidden" name="bank_action" value="delete">
                                                                    <input type="hidden" name="bank_id" value="<?php echo (int)$b['id']; ?>">
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="حذف">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>

                                                            <!-- Edit Modal -->
                                                            <div class="modal fade" id="editBankModal<?php echo (int)$b['id']; ?>" tabindex="-1" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <form method="post" action="<?php echo BASE_URL; ?>/modules/settings/index.php?tab=banks">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title"><i class="bi bi-pencil"></i> ویرایش بانک</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <input type="hidden" name="bank_action" value="update">
                                                                                <input type="hidden" name="bank_id" value="<?php echo (int)$b['id']; ?>">
                                                                                <div class="mb-3">
                                                                                    <label class="form-label">نام بانک</label>
                                                                                    <input type="text" name="bank_name" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($b['name']); ?>">
                                                                                </div>
                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                                                <button type="submit" class="btn btn-warning">
                                                                                    <i class="bi bi-check2"></i> ذخیره تغییرات
                                                                                </button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center p-4 text-muted">
                                                        <i class="bi bi-inbox"></i> بانکی ثبت نشده است.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_tab == 'holidays'): ?>
                <!-- روزهای تعطیل -->
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <i class="bi bi-plus-circle"></i> افزودن روز تعطیل
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">تاریخ تعطیل</label>
                                    <input type="text" class="form-control persian-date" id="holiday_date" placeholder="مثال: 1403/12/29">
                                    <small class="form-text text-muted">تاریخ را از تقویم انتخاب کنید</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">توضیحات</label>
                                    <input type="text" class="form-control" id="holiday_desc" placeholder="مثال: تعطیلات نوروز">
                                </div>
                                <button type="button" class="btn btn-success w-100" onclick="addHoliday()">
                                    <i class="bi bi-plus"></i> افزودن به تقویم
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-calendar"></i> لیست روزهای تعطیل
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered" id="holidays_table">
                                        <thead>
                                            <tr>
                                                <th>تاریخ</th>
                                                <th>توضیحات</th>
                                                <th>روز هفته</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $holidays = $db->query("SELECT * FROM holidays ORDER BY holiday_date");
                                            if ($holidays && $holidays->num_rows > 0):
                                                while ($holiday = $holidays->fetch_assoc()):
                                                    $timestamp = strtotime($holiday['holiday_date']);
                                                    $weekday = function_exists('jdate') ? jdate('l', $timestamp) : date('l', $timestamp);
                                            ?>
                                            <tr>
                                                <td><?php echo function_exists('jdate') ? jdate('Y/m/d', $timestamp) : $holiday['holiday_date']; ?></td>
                                                <td><?php echo htmlspecialchars($holiday['description']); ?></td>
                                                <td><?php echo $weekday; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteHoliday(<?php echo $holiday['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php 
                                                endwhile;
                                            else: 
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">
                                                    هیچ روز تعطیلی ثبت نشده است
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($active_tab == 'contract'): ?>
                <!-- متن قرارداد -->
                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="contract_text" class="form-label">
                            <i class="bi bi-file-text text-primary"></i>
                            متن قرارداد
                        </label>
                        <textarea class="form-control" 
                                  id="contract_text" 
                                  name="contract_text" 
                                  rows="20"><?php echo htmlspecialchars($settings['contract_text'] ?? $settings['contract'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">
                            می‌توانید از متغیرهای [خریدار]، [کد ملی]، [تاریخ] و [مبلغ] استفاده کنید
                        </small>
                    </div>
                    
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>متغیرهای قابل استفاده:</strong>
                            <ul class="mt-2 mb-0">
                                <li><code>[خریدار]</code> - نام خریدار</li>
                                <li><code>[کد ملی]</code> - کد ملی خریدار</li>
                                <li><code>[تاریخ]</code> - تاریخ امروز</li>
                                <li><code>[مبلغ]</code> - مبلغ کل قرارداد</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($active_tab != 'holidays' && $active_tab != 'banks'): ?>
                <hr>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> ذخیره تنظیمات
                    </button>
                    <button type="reset" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-repeat"></i> بازنشانی
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- استایل اختصاصی -->
<style>
.nav-tabs {
    border-bottom: 2px solid #dee2e6;
}
.nav-tabs .nav-link {
    border: none;
    color: #495057;
    font-weight: 500;
    padding: 10px 20px;
    margin-left: 5px;
    border-radius: 8px 8px 0 0;
    transition: all 0.3s ease;
}
.nav-tabs .nav-link i {
    margin-left: 5px;
}
.nav-tabs .nav-link:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
}
.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}
.form-control, .input-group-text {
    border-radius: 8px;
    border: 1px solid #e1e1e1;
    padding: 10px 15px;
    transition: all 0.3s ease;
}
.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
.input-group-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.card-header {
    border-radius: 12px 12px 0 0 !important;
    padding: 15px 20px;
    font-weight: 600;
}
.btn {
    border-radius: 8px;
    padding: 10px 25px;
    font-weight: 500;
    transition: all 0.3s ease;
}
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}
.btn-lg {
    padding: 12px 30px;
    font-size: 1rem;
}
.img-fluid {
    border-radius: 8px;
    max-width: 100%;
    height: auto;
}
.table {
    border-radius: 10px;
    overflow: hidden;
}
.table thead th {
    background: #f8f9fa;
    font-weight: 600;
}
@media (max-width: 768px) {
    .d-flex.gap-2 {
        flex-direction: column;
    }
    .btn {
        width: 100%;
    }
    .nav-tabs .nav-link {
        width: 100%;
        margin: 2px 0;
    }
}
</style>

<!-- اسکریپت‌ها -->
<script src="<?php echo BASE_URL; ?>/assets/js/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/persian-date.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/persian-datepicker.min.js"></script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/persian-datepicker.min.css">

<script>
// تابع تبدیل اعداد فارسی به انگلیسی
function convertPersianToEnglish(str) {
    const persianNumbers = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
        '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9'
    };
    const arabicNumbers = {
        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
        '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
    };
    
    let result = str;
    for (let persian in persianNumbers) {
        result = result.replace(new RegExp(persian, 'g'), persianNumbers[persian]);
    }
    for (let arabic in arabicNumbers) {
        result = result.replace(new RegExp(arabic, 'g'), arabicNumbers[arabic]);
    }
    return result;
}

// فعال‌سازی تاریخ‌picker
$(document).ready(function() {
    $('.persian-date').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        observer: true,
        initialValue: false,
        onSelect: function() {
            let input = this.$el;
            let val = input.val();
            if (val) {
                input.val(convertPersianToEnglish(val));
            }
        }
    });
    
    // فرمت قیمت (برای فیلدهای عددی)
    $('.price-format').each(function() {
        let value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value != '') {
            $(this).val(Number(value).toLocaleString());
        }
    });
    
    $('.price-format').on('input', function() {
        let value = this.value.replace(/,/g, '');
        if (!isNaN(value) && value != '') {
            this.value = Number(value).toLocaleString();
        }
    });
});

// پیش‌نمایش تصویر
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            $('#' + previewId).html('<img src="' + e.target.result + '" class="img-fluid mt-2" style="max-height: 100px; border: 2px solid #28a745; padding: 5px; border-radius: 8px;">');
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// افزودن روز تعطیل
function addHoliday() {
    let date = $('#holiday_date').val();
    let desc = $('#holiday_desc').val();
    
    if (!date) {
        alert('لطفاً تاریخ را وارد کنید');
        return;
    }
    
    date = convertPersianToEnglish(date);
    
    if (!/^\d{4}\/\d{2}\/\d{2}$/.test(date)) {
        alert('فرمت تاریخ نامعتبر است. لطفاً از تقویم استفاده کنید.');
        return;
    }
    
    $.ajax({
        url: '<?php echo BASE_URL; ?>/modules/settings/index.php',
        method: 'POST',
        data: {
            ajax_action: 'add_holiday',
            date: date,
            description: desc
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'خطا در ثبت روز تعطیل');
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('خطا در ارتباط با سرور: ' + error);
        }
    });
}

// حذف روز تعطیل
function deleteHoliday(id) {
    if (confirm('آیا از حذف این روز تعطیل اطمینان دارید؟')) {
        $.ajax({
            url: '<?php echo BASE_URL; ?>/modules/settings/index.php',
            method: 'POST',
            data: {
                ajax_action: 'delete_holiday',
                id: id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا در حذف روز تعطیل');
                }
            },
            error: function(xhr, status, error) {
                alert('خطا در ارتباط با سرور: ' + error);
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
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
$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    setMessage('شناسه آرامگاه نامعتبر است.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/modules/tombs/index.php';</script>";
    exit;
}

// دریافت اطلاعات آرامگاه
$stmt = $db->prepare("SELECT * FROM tombs WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$tomb = $stmt->get_result()->fetch_assoc();

if (!$tomb) {
    setMessage('آرامگاه یافت نشد.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/modules/tombs/index.php';</script>";
    exit;
}

// بررسی وجود فیلد facade در جدول
$check_column = $db->query("SHOW COLUMNS FROM tombs LIKE 'facade'");
$facade_exists = $check_column && $check_column->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tomb_number = $_POST['tomb_number'] ?? '';
    $grave_count = $_POST['grave_count'] ?? 1;
    $row_position = $_POST['row_position'] ?? 'جلو';
    $facade = $_POST['facade'] ?? 'ندارد';
    
    // دریافت قیمت و حذف کاماها
    $price_input = $_POST['price'] ?? '0';
    
    // تبدیل اعداد فارسی به انگلیسی
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    $price_input = str_replace($persian, $english, $price_input);
    $price_input = str_replace($arabic, $english, $price_input);
    
    // حذف کاما و کاراکترهای غیرعددی
    $price_input = preg_replace('/[^0-9]/', '', $price_input);
    $price = (int)$price_input;
    
    if (empty($tomb_number) || empty($grave_count) || $price <= 0) {
        $error = 'تمامی فیلدهای ضروری را با مقادیر معتبر پر کنید.';
    } else {
        // بررسی تکراری نبودن شماره آرامگاه (به جز خودش)
        $check = $db->prepare("SELECT id FROM tombs WHERE tomb_number = ? AND id != ?");
        $check->bind_param('si', $tomb_number, $id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = 'این شماره آرامگاه قبلاً ثبت شده است.';
        } else {
            // بررسی وجود فیلد facade
            $columns = $db->query("SHOW COLUMNS FROM tombs");
            $column_names = [];
            if ($columns) {
                while ($col = $columns->fetch_assoc()) {
                    $column_names[] = $col['Field'];
                }
            }
            
            if (in_array('facade', $column_names)) {
                // اگر فیلد facade وجود دارد
                $stmt = $db->prepare("UPDATE tombs SET tomb_number = ?, grave_count = ?, row_position = ?, facade = ?, price = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('sissii', $tomb_number, $grave_count, $row_position, $facade, $price, $id);
                } else {
                    $error = 'خطا در آماده‌سازی کوئری: ' . $db->error;
                }
            } else {
                // اگر فیلد facade وجود ندارد
                $stmt = $db->prepare("UPDATE tombs SET tomb_number = ?, grave_count = ?, row_position = ?, price = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('sissi', $tomb_number, $grave_count, $row_position, $price, $id);
                } else {
                    $error = 'خطا در آماده‌سازی کوئری: ' . $db->error;
                }
            }
            
            if ($stmt && $stmt->execute()) {
                setMessage('آرامگاه با موفقیت ویرایش شد.', 'success');
                echo "<script>window.location.href = '" . BASE_URL . "/modules/tombs/index.php';</script>";
                exit;
            } elseif ($stmt) {
                $error = 'خطا در ویرایش آرامگاه: ' . $db->error;
            }
        }
    }
}

$page_title = 'ویرایش آرامگاه';
$header_icon = 'pencil-square';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-pencil-square"></i>
            ویرایش آرامگاه: <?php echo htmlspecialchars($tomb['tomb_number']); ?>
        </span>
        <a href="<?php echo BASE_URL; ?>/modules/tombs/index.php" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-right"></i> بازگشت به لیست
        </a>
    </div>
    
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="row">
                <!-- شماره آرامگاه -->
                <div class="col-md-6 mb-3">
                    <label for="tomb_number" class="form-label">
                        <i class="bi bi-hash text-primary"></i>
                        شماره آرامگاه <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="tomb_number" 
                           name="tomb_number" 
                           value="<?php echo htmlspecialchars($tomb['tomb_number']); ?>" 
                           required>
                    <small class="form-text text-muted">
                        شماره آرامگاه باید یکتا باشد
                    </small>
                </div>
                
                <!-- تعداد قبر -->
                <div class="col-md-6 mb-3">
                    <label for="grave_count" class="form-label">
                        <i class="bi bi-grid-3x3 text-primary"></i>
                        تعداد قبر <span class="text-danger">*</span>
                    </label>
                    <input type="number" 
                           class="form-control" 
                           id="grave_count" 
                           name="grave_count" 
                           value="<?php echo $tomb['grave_count']; ?>" 
                           min="1" 
                           max="500" 
                           required>
                    <small class="form-text text-muted">
                        حداکثر ۵۰۰ قبر
                    </small>
                </div>
                
                <!-- موقعیت مکانی -->
                <div class="col-md-6 mb-3">
                    <label for="row_position" class="form-label">
                        <i class="bi bi-pin-map text-primary"></i>
                        موقعیت مکانی <span class="text-danger">*</span>
                    </label>
                    <select class="form-control" id="row_position" name="row_position" required>
                        <option value="جلو" <?php echo ($tomb['row_position'] == 'جلو') ? 'selected' : ''; ?>>ردیف جلو</option>
                        <option value="پشت" <?php echo ($tomb['row_position'] == 'پشت') ? 'selected' : ''; ?>>ردیف پشت</option>
                    </select>
                </div>
                
                <!-- نماسازی -->
                <div class="col-md-6 mb-3">
                    <label for="facade" class="form-label">
                        <i class="bi bi-building text-primary"></i>
                        نماسازی <span class="text-danger">*</span>
                    </label>
                    <select class="form-control" id="facade" name="facade" required>
                        <option value="دارد" <?php echo (isset($tomb['facade']) && $tomb['facade'] == 'دارد') ? 'selected' : ''; ?>>دارد</option>
                        <option value="ندارد" <?php echo (!isset($tomb['facade']) || $tomb['facade'] == 'ندارد') ? 'selected' : ''; ?>>ندارد</option>
                    </select>
                </div>
                
                <!-- قیمت -->
                <div class="col-md-6 mb-3">
                    <label for="price" class="form-label">
                        <i class="bi bi-currency-exchange text-primary"></i>
                        قیمت (ریال) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="price" 
                               name="price" 
                               value="<?php echo $tomb['price']; ?>" 
                               required>
                        <span class="input-group-text bg-primary text-white">ریال</span>
                    </div>
                    <small class="form-text text-muted">
                        فقط اعداد وارد کنید (بدون کاما)
                    </small>
                </div>
                
                <!-- وضعیت -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">
                        <i class="bi bi-info-circle text-primary"></i>
                        وضعیت
                    </label>
                    <div class="form-control bg-light">
                        <?php if ($tomb['is_available']): ?>
                            <span class="badge bg-success">موجود</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">فروخته شده</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- دکمه‌های اقدام -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i>
                    ذخیره تغییرات
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/tombs/index.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i>
                    انصراف
                </a>
            </div>
        </form>
    </div>
</div>

<!-- کارت اطلاعات -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i>
                اطلاعات آرامگاه
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>شناسه:</strong>
                        <p><?php echo $tomb['id']; ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>تاریخ ایجاد:</strong>
                        <p><?php echo jdate('Y/m/d', strtotime($tomb['created_at'] ?? 'now')); ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>وضعیت فعلی:</strong>
                        <p><?php echo $tomb['is_available'] ? 'موجود' : 'فروخته شده'; ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>قیمت فعلی:</strong>
                        <p class="text-primary fw-bold"><?php echo number_format($tomb['price']); ?> ریال</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// فقط اجازه ورود اعداد را می‌دهیم
document.getElementById('price')?.addEventListener('keypress', function(e) {
    var charCode = e.which ? e.which : e.keyCode;
    if (charCode < 48 || charCode > 57) {
        e.preventDefault();
    }
});

// اعتبارسنجی فرم قبل از ارسال
document.querySelector('form')?.addEventListener('submit', function(e) {
    let price = document.getElementById('price').value.trim();
    
    if (price === '') {
        e.preventDefault();
        alert('لطفاً قیمت را وارد کنید.');
        return false;
    }
    
    if (isNaN(price)) {
        e.preventDefault();
        alert('لطفاً فقط عدد وارد کنید.');
        return false;
    }
    
    let priceNum = parseInt(price);
    if (priceNum <= 0) {
        e.preventDefault();
        alert('قیمت باید بزرگتر از صفر باشد.');
        return false;
    }
});
</script>

<style>
.form-label {
    font-weight: 600;
    color: #495057;
}
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    animation: slideUp 0.5s ease-out;
}
.card-header {
    border-radius: 12px 12px 0 0 !important;
}
.btn {
    transition: all 0.3s ease;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
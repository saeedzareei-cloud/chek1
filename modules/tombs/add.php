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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tomb_number = $_POST['tomb_number'] ?? '';
    $grave_count = $_POST['grave_count'] ?? 1;
    $row_position = $_POST['row_position'] ?? 'جلو'; // موقعیت مکانی
    $facade = $_POST['facade'] ?? 'ندارد'; // نماسازی
    
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
        // بررسی تکراری نبودن شماره آرامگاه
        $check = $db->prepare("SELECT id FROM tombs WHERE tomb_number = ?");
        $check->bind_param('s', $tomb_number);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = 'این شماره آرامگاه قبلاً ثبت شده است.';
        } else {
            $stmt = $db->prepare("INSERT INTO tombs (tomb_number, grave_count, row_position, facade, price, is_available) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param('sissi', $tomb_number, $grave_count, $row_position, $facade, $price);
            
            if ($stmt->execute()) {
                setMessage('آرامگاه با موفقیت ایجاد شد.', 'success');
                echo "<script>window.location.href = '" . BASE_URL . "/modules/tombs/index.php';</script>";
                exit;
            } else {
                $error = 'خطا در ایجاد آرامگاه: ' . $db->error;
            }
        }
    }
}

$page_title = 'افزودن آرامگاه جدید';
$header_icon = 'building-add';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-plus-circle"></i>
            افزودن آرامگاه جدید
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
                           value="<?php echo htmlspecialchars($_POST['tomb_number'] ?? ''); ?>" 
                           placeholder="مثال: A-101"
                           required 
                           autofocus>
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
                           value="<?php echo htmlspecialchars($_POST['grave_count'] ?? 1); ?>" 
                           min="1" 
                           max="500" 
                           required>
                    <small class="form-text text-muted">
                        حداکثر ۵۰۰ قبر
                    </small>
                </div>
                
                <!-- موقعیت مکانی (ردیف جلو/پشت) -->
                <div class="col-md-6 mb-3">
                    <label for="row_position" class="form-label">
                        <i class="bi bi-pin-map text-primary"></i>
                        موقعیت مکانی <span class="text-danger">*</span>
                    </label>
                    <select class="form-control" id="row_position" name="row_position" required>
                        <option value="جلو" <?php echo (isset($_POST['row_position']) && $_POST['row_position'] == 'جلو') ? 'selected' : ''; ?>>ردیف جلو</option>
                        <option value="پشت" <?php echo (isset($_POST['row_position']) && $_POST['row_position'] == 'پشت') ? 'selected' : ''; ?>>ردیف پشت</option>
                    </select>
                    <small class="form-text text-muted">
                        موقعیت آرامگاه در ردیف جلو یا پشت
                    </small>
                </div>
                
                <!-- نماسازی -->
                <div class="col-md-6 mb-3">
                    <label for="facade" class="form-label">
                        <i class="bi bi-building text-primary"></i>
                        نماسازی <span class="text-danger">*</span>
                    </label>
                    <select class="form-control" id="facade" name="facade" required>
                        <option value="دارد" <?php echo (isset($_POST['facade']) && $_POST['facade'] == 'دارد') ? 'selected' : ''; ?>>دارد</option>
                        <option value="ندارد" <?php echo (!isset($_POST['facade']) || $_POST['facade'] == 'ندارد') ? 'selected' : ''; ?>>ندارد</option>
                    </select>
                    <small class="form-text text-muted">
                        آیا آرامگاه دارای نماسازی است؟
                    </small>
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
                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" 
                               placeholder="مثال: 500000000"
                               required>
                        <span class="input-group-text bg-primary text-white">ریال</span>
                    </div>
                    <small class="form-text text-muted">
                        فقط اعداد وارد کنید (بدون کاما)
                    </small>
                </div>
                
                <!-- وضعیت فعال -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">
                        <i class="bi bi-info-circle text-primary"></i>
                        وضعیت
                    </label>
                    <div class="form-control bg-light" style="border: none; padding: 10px 0;">
                        <span class="badge bg-success">موجود</span>
                        <small class="text-muted me-2">آرامگاه آماده فروش</small>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- دکمه‌های اقدام -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i>
                    ذخیره آرامگاه
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/tombs/index.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i>
                    انصراف
                </a>
            </div>
        </form>
    </div>
</div>

<!-- کارت راهنما و اطلاعات -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-lightbulb"></i>
                راهنمای ثبت آرامگاه
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <strong>شماره آرامگاه:</strong> یک شناسه منحصر به فرد انتخاب کنید
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <strong>تعداد قبر:</strong> تعداد قبرهای موجود در آرامگاه
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <strong>موقعیت مکانی:</strong>
                        <span class="badge bg-primary me-1">ردیف جلو</span>
                        <span class="badge bg-secondary me-1">ردیف پشت</span>
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <strong>نماسازی:</strong>
                        <span class="badge bg-success me-1">دارد</span>
                        <span class="badge bg-secondary me-1">ندارد</span>
                    </li>
                    <li class="mb-3">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <strong>قیمت:</strong> قیمت نهایی آرامگاه به ریال
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-info-circle"></i>
                نکات مهم
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>توجه:</strong> شماره آرامگاه باید یکتا باشد و نمی‌تواند تکراری ثبت شود.
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>موقعیت مکانی:</strong>
                    <ul class="mt-2">
                        <li><span class="badge bg-primary">ردیف جلو</span> - آرامگاه‌های جلویی با دسترسی آسان‌تر</li>
                        <li><span class="badge bg-secondary">ردیف پشت</span> - آرامگاه‌های پشتی</li>
                    </ul>
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
.badge {
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.85rem;
}
</style>

<?php include '../../includes/footer.php'; ?>
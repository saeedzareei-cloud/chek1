<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود و دسترسی
Auth::requireLogin();
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    redirect('/index.php');
}

$db = Database::getInstance()->getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $access_level = $_POST['access_level'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // اعتبارسنجی
    if (empty($username) || empty($password) || empty($full_name) || empty($access_level)) {
        $error = 'تمامی فیلدهای ضروری را پر کنید.';
    } else {
        // بررسی تکراری نبودن نام کاربری
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param('s', $username);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = 'این نام کاربری قبلاً ثبت شده است.';
        } else {
            // درج کاربر جدید
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, access_level, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssi', $username, $hashed_password, $full_name, $access_level, $is_active);
            
            if ($stmt->execute()) {
                setMessage('کاربر با موفقیت ایجاد شد.', 'success');
                // استفاده از redirect با آدرس صحیح
                echo "<script>window.location.href = 'http://localhost:8080/chek/modules/users/index.php';</script>";
                exit;
            } else {
                $error = 'خطا در ایجاد کاربر: ' . $db->error;
            }
        }
    }
}

$page_title = 'افزودن کاربر جدید';
$header_icon = 'person-plus';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-person-plus text-primary"></i>
            افزودن کاربر جدید
        </span>
        <a href="index.php" class="btn btn-sm btn-secondary">
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
                <!-- نام کاربری -->
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">
                        <i class="bi bi-person text-muted"></i>
                        نام کاربری <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?php echo isset($error) && empty($username) ? 'is-invalid' : ''; ?>" 
                           id="username" 
                           name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required 
                           autofocus>
                    <div class="invalid-feedback">
                        لطفاً نام کاربری را وارد کنید
                    </div>
                    <small class="form-text text-muted">
                        نام کاربری باید یکتا باشد
                    </small>
                </div>
                
                <!-- رمز عبور -->
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock text-muted"></i>
                        رمز عبور <span class="text-danger">*</span>
                    </label>
                    <input type="password" 
                           class="form-control <?php echo isset($error) && empty($password) ? 'is-invalid' : ''; ?>" 
                           id="password" 
                           name="password" 
                           required>
                    <div class="invalid-feedback">
                        لطفاً رمز عبور را وارد کنید
                    </div>
                    <small class="form-text text-muted">
                        حداقل ۶ کاراکتر
                    </small>
                </div>
                
                <!-- نام و نام خانوادگی -->
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">
                        <i class="bi bi-person-badge text-muted"></i>
                        نام و نام خانوادگی <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?php echo isset($error) && empty($full_name) ? 'is-invalid' : ''; ?>" 
                           id="full_name" 
                           name="full_name" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                           required>
                    <div class="invalid-feedback">
                        لطفاً نام و نام خانوادگی را وارد کنید
                    </div>
                </div>
                
                <!-- سطح دسترسی -->
                <div class="col-md-6 mb-3">
                    <label for="access_level" class="form-label">
                        <i class="bi bi-shield-lock text-muted"></i>
                        سطح دسترسی <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?php echo isset($error) && empty($access_level) ? 'is-invalid' : ''; ?>" 
                            id="access_level" 
                            name="access_level" 
                            required>
                        <option value="">انتخاب کنید</option>
                        <option value="مدیر سیستم" <?php echo (isset($_POST['access_level']) && $_POST['access_level'] == 'مدیر سیستم') ? 'selected' : ''; ?>>
                            مدیر سیستم
                        </option>
                        <option value="جانشین مدیر" <?php echo (isset($_POST['access_level']) && $_POST['access_level'] == 'جانشین مدیر') ? 'selected' : ''; ?>>
                            جانشین مدیر
                        </option>
                        <option value="کاربر" <?php echo (isset($_POST['access_level']) && $_POST['access_level'] == 'کاربر') ? 'selected' : ''; ?>>
                            کاربر
                        </option>
                        <option value="مباشر" <?php echo (isset($_POST['access_level']) && $_POST['access_level'] == 'مباشر') ? 'selected' : ''; ?>>
                            مباشر
                        </option>
                    </select>
                    <div class="invalid-feedback">
                        لطفاً سطح دسترسی را انتخاب کنید
                    </div>
                </div>
                
                <!-- وضعیت فعال بودن -->
                <div class="col-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               <?php echo (isset($_POST['is_active']) || !isset($_POST['is_active'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            <i class="bi bi-check-circle text-success"></i>
                            کاربر فعال باشد
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        اگر این گزینه فعال باشد، کاربر می‌تواند وارد سیستم شود
                    </small>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i>
                    ذخیره اطلاعات
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i>
                    انصراف
                </a>
            </div>
        </form>
    </div>
</div>

<!-- راهنمای سطوح دسترسی -->
<div class="card mt-3">
    <div class="card-header bg-info text-white">
        <i class="bi bi-question-circle"></i>
        راهنمای سطوح دسترسی
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="text-center p-3 border rounded bg-light">
                    <i class="bi bi-shield-lock text-danger" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">مدیر سیستم</h6>
                    <small class="text-muted">دسترسی کامل به تمام بخش‌ها</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center p-3 border rounded bg-light">
                    <i class="bi bi-shield text-warning" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">جانشین مدیر</h6>
                    <small class="text-muted">تایید درخواست‌ها، مشاهده گزارشات</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center p-3 border rounded bg-light">
                    <i class="bi bi-person text-info" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">کاربر</h6>
                    <small class="text-muted">ثبت درخواست، مدیریت مشتریان</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="text-center p-3 border rounded bg-light">
                    <i class="bi bi-person-badge text-secondary" style="font-size: 2rem;"></i>
                    <h6 class="mt-2">مباشر</h6>
                    <small class="text-muted">راهنمایی مشتریان، ثبت اولیه</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
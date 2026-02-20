<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود و دسترسی
Auth::requireLogin();
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/index.php';</script>";
    exit;
}

$db = Database::getInstance()->getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    setMessage('شناسه کاربر نامعتبر است.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/modules/users/index.php';</script>";
    exit;
}

// دریافت اطلاعات کاربر
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    setMessage('کاربر یافت نشد.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/modules/users/index.php';</script>";
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $access_level = $_POST['access_level'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    
    // اعتبارسنجی
    if (empty($username) || empty($full_name) || empty($access_level)) {
        $error = 'تمامی فیلدهای ضروری را پر کنید.';
    } else {
        // بررسی تکراری نبودن نام کاربری (غیر از خودش)
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param('si', $username, $user_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = 'این نام کاربری قبلاً ثبت شده است.';
        } else {
            // بروزرسانی اطلاعات
            if (!empty($new_password)) {
                // اگر رمز عبور جدید وارد شده
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, access_level = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param('ssssii', $username, $hashed_password, $full_name, $access_level, $is_active, $user_id);
            } else {
                // بدون تغییر رمز عبور
                $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, access_level = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param('sssii', $username, $full_name, $access_level, $is_active, $user_id);
            }
            
            if ($stmt->execute()) {
                setMessage('کاربر با موفقیت بروزرسانی شد.', 'success');
                echo "<script>window.location.href = '" . BASE_URL . "/modules/users/index.php';</script>";
                exit;
            } else {
                $error = 'خطا در بروزرسانی کاربر: ' . $db->error;
            }
        }
    }
}

$page_title = 'ویرایش کاربر';
$header_icon = 'pencil-square';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-pencil-square"></i>
            ویرایش کاربر: <?php echo htmlspecialchars($user['full_name']); ?>
        </span>
        <a href="<?php echo BASE_URL; ?>/modules/users/index.php" class="btn btn-light btn-sm">
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
                        <i class="bi bi-person text-primary"></i>
                        نام کاربری <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                           required>
                    <small class="form-text text-muted">
                        نام کاربری باید یکتا باشد
                    </small>
                </div>
                
                <!-- نام و نام خانوادگی -->
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">
                        <i class="bi bi-person-badge text-primary"></i>
                        نام و نام خانوادگی <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="full_name" 
                           name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                           required>
                </div>
                
                <!-- سطح دسترسی -->
                <div class="col-md-6 mb-3">
                    <label for="access_level" class="form-label">
                        <i class="bi bi-shield-lock text-primary"></i>
                        سطح دسترسی <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="access_level" name="access_level" required>
                        <option value="">انتخاب کنید</option>
                        <option value="مدیر سیستم" <?php echo $user['access_level'] == 'مدیر سیستم' ? 'selected' : ''; ?>>
                            مدیر سیستم
                        </option>
                        <option value="جانشین مدیر" <?php echo $user['access_level'] == 'جانشین مدیر' ? 'selected' : ''; ?>>
                            جانشین مدیر
                        </option>
                        <option value="کاربر" <?php echo $user['access_level'] == 'کاربر' ? 'selected' : ''; ?>>
                            کاربر
                        </option>
                        <option value="مباشر" <?php echo $user['access_level'] == 'مباشر' ? 'selected' : ''; ?>>
                            مباشر
                        </option>
                    </select>
                </div>
                
                <!-- رمز عبور جدید -->
                <div class="col-md-6 mb-3">
                    <label for="new_password" class="form-label">
                        <i class="bi bi-lock text-primary"></i>
                        رمز عبور جدید
                    </label>
                    <input type="password" class="form-control" id="new_password" name="new_password" 
                           placeholder="فقط در صورت تمایل به تغییر وارد کنید">
                    <small class="form-text text-muted">
                        اگر نمی‌خواهید رمز عبور تغییر کند، این فیلد را خالی بگذارید.
                    </small>
                </div>
                
                <!-- وضعیت فعال بودن -->
                <div class="col-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               <?php echo $user['is_active'] ? 'checked' : ''; ?>>
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
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i>
                    ذخیره تغییرات
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/users/index.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i>
                    انصراف
                </a>
            </div>
        </form>
    </div>
</div>

<!-- کارت اطلاعات کاربر -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i>
                اطلاعات کاربر
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>شناسه:</strong>
                        <p><?php echo $user['id']; ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>تاریخ ثبت:</strong>
                        <p><?php echo jdate('Y/m/d', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>وضعیت فعلی:</strong>
                        <p>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">فعال</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">غیرفعال</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// اعتبارسنجی سمت کلاینت
document.querySelector('form')?.addEventListener('submit', function(e) {
    let username = document.getElementById('username').value.trim();
    let full_name = document.getElementById('full_name').value.trim();
    let access_level = document.getElementById('access_level').value;
    
    if (username === '' || full_name === '' || access_level === '') {
        e.preventDefault();
        alert('لطفاً تمام فیلدهای ضروری را پر کنید.');
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
.card-header.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}
.card-header.bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%) !important;
}
.btn {
    transition: all 0.3s ease;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}
.btn-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    border: none;
    color: white;
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #5a6268 0%, #6c757d 100%);
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
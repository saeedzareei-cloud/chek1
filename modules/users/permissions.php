<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    redirect('/index.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_GET['id'] ?? 0;

// دریافت اطلاعات کاربر
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    setMessage('کاربر یافت نشد.', 'danger');
    redirect('index.php');
}

// لیست منوهای سیستم
$menus = [
    'dashboard' => 'داشبورد',
    'users' => 'مدیریت کاربران',
    'requests_new' => 'ثبت درخواست جدید',
    'requests_list' => 'لیست درخواست‌ها',
    'requests_verify' => 'تایید درخواست‌ها',
    'sections' => 'مدیریت قطعات',
    'tombs' => 'مدیریت آرامگاه‌ها',
    'customers' => 'مشتریان',
    'reports' => 'گزارش‌ها',
    'settings' => 'تنظیمات'
];

// ذخیره دسترسی‌ها
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // حذف دسترسی‌های قبلی
    $stmt = $db->prepare("DELETE FROM menu_permissions WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    
    // ثبت دسترسی‌های جدید
    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
        $stmt = $db->prepare("INSERT INTO menu_permissions (user_id, menu_name, can_access) VALUES (?, ?, 1)");
        
        foreach ($_POST['permissions'] as $menu) {
            $stmt->bind_param('is', $user_id, $menu);
            $stmt->execute();
        }
    }
    
    setMessage('دسترسی‌ها با موفقیت بروزرسانی شد.', 'success');
    redirect('index.php');
}

// دریافت دسترسی‌های فعلی
$current_perms = [];
$stmt = $db->prepare("SELECT menu_name FROM menu_permissions WHERE user_id = ? AND can_access = 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $current_perms[] = $row['menu_name'];
}

$page_title = 'مدیریت دسترسی‌ها';
$header_icon = 'shield-lock';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-shield-lock"></i>
            مدیریت دسترسی‌های کاربر: <?php echo htmlspecialchars($user['full_name']); ?>
        </span>
        <a href="index.php" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-right"></i> بازگشت به لیست
        </a>
    </div>
    
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>کاربر:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
            با سطح دسترسی فعلی: <span class="badge bg-<?php 
                echo $user['access_level'] == 'مدیر سیستم' ? 'danger' : 
                    ($user['access_level'] == 'جانشین مدیر' ? 'warning' : 
                    ($user['access_level'] == 'کاربر' ? 'info' : 'secondary')); 
            ?>"><?php echo $user['access_level']; ?></span>
        </div>
        
        <form method="POST" action="">
            <div class="row">
                <?php foreach ($menus as $key => $title): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="permissions[]" 
                                           value="<?php echo $key; ?>"
                                           id="perm_<?php echo $key; ?>"
                                           <?php echo in_array($key, $current_perms) ? 'checked' : ''; ?>
                                           style="cursor: pointer; width: 40px; height: 20px;">
                                    <label class="form-check-label fw-bold" for="perm_<?php echo $key; ?>" style="cursor: pointer; margin-right: 10px;">
                                        <?php echo $title; ?>
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <?php
                                    $descriptions = [
                                        'dashboard' => 'دسترسی به صفحه اصلی داشبورد',
                                        'users' => 'مدیریت کاربران سیستم',
                                        'requests_new' => 'ثبت درخواست جدید',
                                        'requests_list' => 'مشاهده لیست درخواست‌ها',
                                        'requests_verify' => 'تایید درخواست‌ها',
                                        'sections' => 'مدیریت قطعات',
                                        'tombs' => 'مدیریت آرامگاه‌ها',
                                        'customers' => 'مدیریت مشتریان',
                                        'reports' => 'مشاهده گزارش‌ها',
                                        'settings' => 'تنظیمات سیستم'
                                    ];
                                    echo $descriptions[$key] ?? '';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> ذخیره تغییرات
                </button>
                <a href="index.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i> انصراف
                </a>
            </div>
        </form>
    </div>
</div>

<!-- استایل اختصاصی -->
<style>
/* استایل کارت‌ها */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
    padding: 15px 20px;
}

.card-header.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

/* استایل فرم چک باکس */
.form-check-input {
    background-color: #e9ecef;
    border: none;
    transition: all 0.3s ease;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.form-check-input:focus {
    box-shadow: none;
    border-color: #28a745;
}

.form-check-label {
    font-size: 16px;
    color: #495057;
}

/* استایل دکمه‌ها */
.btn {
    border-radius: 8px;
    padding: 12px 25px;
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

.btn-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    border: none;
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #5a6268 0%, #6c757d 100%);
    transform: translateY(-2px);
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1rem;
}

/* استایل آلرت */
.alert-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border: none;
    border-radius: 10px;
    color: #0c5460;
    padding: 15px 20px;
}

.alert-info i {
    margin-left: 8px;
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

/* استایل برای موبایل */
@media (max-width: 768px) {
    .col-md-4 {
        margin-bottom: 15px;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}

/* انیمیشن */
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

.card {
    animation: slideUp 0.5s ease-out;
}

/* استایل hover برای کارت‌ها */
.card .form-check {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.card small {
    font-size: 12px;
    color: #6c757d;
    line-height: 1.5;
}

/* استایل برای چک باکس‌های انتخاب شده */
.form-check-input:checked + .form-check-label {
    color: #28a745;
    font-weight: 600;
}

/* استایل خط جداکننده */
hr {
    border: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
    margin: 30px 0;
}

/* استایل tooltip */
[title] {
    position: relative;
    cursor: help;
}

[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    right: 0;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}
</style>

<?php include '../../includes/footer.php'; ?>
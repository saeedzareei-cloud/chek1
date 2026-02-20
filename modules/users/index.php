<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

// فقط مدیر سیستم می‌تواند به این صفحه دسترسی داشته باشد
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    redirect('/index.php');
}

$db = Database::getInstance()->getConnection();

// حذف کاربر
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // بررسی نبودن کاربر تکراری
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND username != 'admin'");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        setMessage('کاربر با موفقیت حذف شد.', 'success');
    } else {
        setMessage('خطا در حذف کاربر.', 'danger');
    }
    
    redirect('/modules/users/index.php');
}

// دریافت لیست کاربران
$users = $db->query("SELECT * FROM users ORDER BY id DESC");

$page_title = 'مدیریت کاربران';
$header_icon = 'people';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-people-fill"></i>
            لیست کاربران
        </span>
        <div>
            <span class="badge bg-light text-dark me-2">
                <i class="bi bi-person"></i> تعداد: <?php echo $users->num_rows; ?>
            </span>
            <a href="add.php" class="btn btn-light btn-sm">
                <i class="bi bi-plus-circle"></i> کاربر جدید
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($users && $users->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام کاربری</th>
                            <th>نام و نام خانوادگی</th>
                            <th>سطح دسترسی</th>
                            <th>وضعیت</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; ?>
                        <?php while($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = '';
                                    $badge_text = '';
                                    if ($row['access_level'] == 'مدیر سیستم') {
                                        $badge_class = 'bg-danger';
                                        $badge_text = 'مدیر سیستم';
                                    } elseif ($row['access_level'] == 'جانشین مدیر') {
                                        $badge_class = 'bg-warning text-dark';
                                        $badge_text = 'جانشین مدیر';
                                    } elseif ($row['access_level'] == 'کاربر') {
                                        $badge_class = 'bg-info';
                                        $badge_text = 'کاربر';
                                    } elseif ($row['access_level'] == 'مباشر') {
                                        $badge_class = 'bg-secondary';
                                        $badge_text = 'مباشر';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>" style="padding: 6px 12px; border-radius: 20px;">
                                        <?php echo $badge_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success" style="padding: 6px 12px; border-radius: 20px;">
                                            <i class="bi bi-check-circle"></i> فعال
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" style="padding: 6px 12px; border-radius: 20px;">
                                            <i class="bi bi-x-circle"></i> غیرفعال
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 text-muted me-1"></i>
                                    <?php echo jdate('Y/m/d', strtotime($row['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <!-- دکمه ویرایش -->
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm" 
                                           style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease;"
                                           title="ویرایش کاربر"
                                           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(23,162,184,0.3)';"
                                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                                            <i class="bi bi-pencil" style="font-size: 18px;"></i>
                                        </a>
                                        
                                        <?php if ($row['username'] != 'admin'): ?>
                                            <!-- دکمه دسترسی‌ها -->
                                            <a href="permissions.php?id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm" 
                                               style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #333; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease;"
                                               title="مدیریت دسترسی‌ها"
                                               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(255,193,7,0.3)';"
                                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                                                <i class="bi bi-gear" style="font-size: 18px;"></i>
                                            </a>
                                            
                                            <!-- دکمه حذف -->
                                            <a href="?delete=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm" 
                                               style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease;"
                                               title="حذف کاربر"
                                               onclick="return confirm('آیا از حذف این کاربر اطمینان دارید؟')"
                                               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(220,53,69,0.3)';"
                                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.1)';">
                                                <i class="bi bi-trash" style="font-size: 18px;"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 5rem; opacity: 0.3;"></i>
                <h4 class="mt-3 text-muted">هیچ کاربری یافت نشد</h4>
                <p class="text-muted mb-4">برای شروع، اولین کاربر خود را ایجاد کنید.</p>
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> افزودن کاربر جدید
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($users && $users->num_rows > 0): ?>
    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            تعداد کل کاربران: <strong><?php echo $users->num_rows; ?></strong>
        </small>
        <small class="text-muted">
            <i class="bi bi-shield-lock me-1"></i>
            کاربر <strong>admin</strong> قابل حذف نیست
        </small>
    </div>
    <?php endif; ?>
</div>

<!-- استایل اختصاصی -->
<style>
/* استایل جدول */
.table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    border: none;
    padding: 15px;
    font-size: 0.9rem;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #eee;
}

/* استایل بج‌ها */
.badge {
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge i {
    font-size: 12px;
}

/* استایل دکمه‌های عملیات */
.d-flex.gap-1 {
    gap: 5px !important;
}

.btn-sm {
    transition: all 0.3s ease !important;
    border: none;
    outline: none;
    cursor: pointer;
}

.btn-sm:hover {
    transform: translateY(-2px);
}

.btn-sm:active {
    transform: translateY(0);
}

/* استایل کارت */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    animation: slideUp 0.5s ease-out;
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
    padding: 15px 20px;
}

.card-footer {
    border-radius: 0 0 15px 15px !important;
    padding: 12px 20px;
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

/* استایل برای حالت خالی */
.py-5 {
    padding: 3rem 0;
}

/* استایل برای hover بج‌ها */
.badge.bg-success:hover {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%) !important;
}

.badge.bg-warning:hover {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
}

.badge.bg-info:hover {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
}

.badge.bg-danger:hover {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
}

.badge.bg-secondary:hover {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
}

/* استایل برای موبایل */
@media (max-width: 768px) {
    .table td {
        white-space: nowrap;
    }
    
    .d-flex.gap-1 {
        flex-wrap: wrap;
    }
    
    .btn-sm {
        width: 32px;
        height: 32px;
    }
}

/* اطمینان از نمایش صحیح آیکون‌ها */
.bi {
    font-family: 'bootstrap-icons' !important;
    font-style: normal;
    font-weight: normal;
    font-variant: normal;
    text-transform: none;
    line-height: 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    display: inline-block;
}
</style>

<?php include '../../includes/footer.php'; ?>
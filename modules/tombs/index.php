<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

// فقط مدیر سیستم می‌تواند آرامگاه‌ها را مدیریت کند
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/index.php';</script>";
    exit;
}

$db = Database::getInstance()->getConnection();

// حذف آرامگاه
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // بررسی وجود درخواست برای این آرامگاه
    $check = $db->prepare("SELECT COUNT(*) as count FROM request_items WHERE tomb_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        setMessage('این آرامگاه دارای درخواست خرید است و قابل حذف نمی‌باشد.', 'danger');
    } else {
        $stmt = $db->prepare("DELETE FROM tombs WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            setMessage('آرامگاه با موفقیت حذف شد.', 'success');
        } else {
            setMessage('خطا در حذف آرامگاه.', 'danger');
        }
    }
    
    echo "<script>window.location.href = '" . BASE_URL . "/modules/tombs/index.php';</script>";
    exit;
}

// تغییر وضعیت موجود بودن
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = $_GET['toggle'];
    
    // بررسی وجود آرامگاه
    $check = $db->prepare("SELECT id FROM tombs WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $stmt = $db->prepare("UPDATE tombs SET is_available = NOT is_available WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
        setMessage('وضعیت آرامگاه تغییر کرد.', 'success');
    } else {
        setMessage('آرامگاه یافت نشد.', 'danger');
    }
    
    echo "<script>window.location.href = '" . BASE_URL . "/modules/tombs/index.php';</script>";
    exit;
}

// دریافت لیست آرامگاه‌ها
$tombs = $db->query("SELECT * FROM tombs ORDER BY id DESC");

// محاسبه آمار
$total_tombs = $tombs ? $tombs->num_rows : 0;
$available_tombs = 0;
$sold_tombs = 0;

if ($tombs && $total_tombs > 0) {
    $available_result = $db->query("SELECT COUNT(*) as count FROM tombs WHERE is_available = 1");
    $available_tombs = $available_result ? $available_result->fetch_assoc()['count'] : 0;
    
    $sold_result = $db->query("SELECT COUNT(*) as count FROM tombs WHERE is_available = 0");
    $sold_tombs = $sold_result ? $sold_result->fetch_assoc()['count'] : 0;
}

$page_title = 'مدیریت آرامگاه‌ها';
$header_icon = 'building';

include '../../includes/header.php';
?>

<!-- کارت آمار خلاصه (مشابه مدیریت قطعات) -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-building text-primary me-1"></i>
                        کل آرامگاه‌ها
                    </h6>
                    <h3><?php echo number_format($total_tombs); ?></h3>
                </div>
                <i class="bi bi-building-fill fs-1 text-primary"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-check-circle text-success me-1"></i>
                        آرامگاه‌های موجود
                    </h6>
                    <h3 class="text-success"><?php echo number_format($available_tombs); ?></h3>
                </div>
                <i class="bi bi-check-circle-fill fs-1 text-success"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-x-circle text-danger me-1"></i>
                        فروخته شده
                    </h6>
                    <h3 class="text-danger"><?php echo number_format($sold_tombs); ?></h3>
                </div>
                <i class="bi bi-x-circle-fill fs-1 text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- لیست آرامگاه‌ها -->
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list"></i>
            لیست آرامگاه‌ها
        </span>
        <div>
            <span class="badge bg-light text-dark me-2">
                <i class="bi bi-building"></i> تعداد: <?php echo $total_tombs; ?>
            </span>
            <a href="<?php echo BASE_URL; ?>/modules/tombs/add.php" class="btn btn-light btn-sm">
                <i class="bi bi-plus-circle"></i> آرامگاه جدید
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($tombs && $tombs->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr style="background-color: #4e73df; color: white;">
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">شماره آرامگاه</th>
                            <th style="padding: 12px;">تعداد قبر</th>
                            <th style="padding: 12px;">موقعیت</th>
                            <th style="padding: 12px;">نماسازی</th>
                            <th style="padding: 12px;">قیمت (ریال)</th>
                            <th style="padding: 12px;">وضعیت</th>
                            <th style="padding: 12px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; ?>
                        <?php while($row = $tombs->fetch_assoc()): ?>
                        <tr class="<?php echo !$row['is_available'] ? 'table-secondary' : ''; ?>">
                            <td style="padding: 12px;"><?php echo $i++; ?></td>
                            <td style="padding: 12px;">
                                <strong><?php echo htmlspecialchars($row['tomb_number']); ?></strong>
                            </td>
                            <td style="padding: 12px;" class="text-center">
                                <span class="badge bg-info"><?php echo $row['grave_count']; ?> قبر</span>
                            </td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($row['row_position'] ?? '-'); ?></td>
                            <td style="padding: 12px;">
                                <?php if (isset($row['facade']) && $row['facade'] == 'دارد'): ?>
                                    <span class="badge bg-success">دارد</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">ندارد</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;" class="text-start fw-bold text-primary">
                                <?php 
                                $price = (int)$row['price'];
                                echo number_format($price) . ' ریال'; 
                                ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php if ($row['is_available']): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> موجود
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-lock"></i> فروخته شده
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <div class="d-flex gap-1">
                                    <!-- دکمه ویرایش -->
                                    <a href="<?php echo BASE_URL; ?>/modules/tombs/edit.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm" 
                                       style="background: #17a2b8; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="ویرایش"
                                       onmouseover="this.style.background='#138496'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#17a2b8'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    
                                    <!-- دکمه تغییر وضعیت -->
                                    <a href="javascript:void(0);" 
                                       onclick="toggleStatus(<?php echo $row['id']; ?>)"
                                       class="btn btn-sm" 
                                       style="background: #ffc107; color: #333; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="تغییر وضعیت"
                                       onmouseover="this.style.background='#e0a800'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#ffc107'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </a>
                                    
                                    <?php if ($row['is_available']): ?>
                                    <!-- دکمه حذف -->
                                    <a href="javascript:void(0);" 
                                       onclick="deleteTomb(<?php echo $row['id']; ?>)"
                                       class="btn btn-sm" 
                                       style="background: #dc3545; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="حذف"
                                       onmouseover="this.style.background='#c82333'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#dc3545'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-trash"></i>
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
                <i class="bi bi-building text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">آرامگاهی تعریف نشده است</h5>
                <p class="text-muted">برای شروع، اولین آرامگاه خود را ایجاد کنید</p>
                <a href="<?php echo BASE_URL; ?>/modules/tombs/add.php" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> افزودن آرامگاه جدید
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($tombs && $tombs->num_rows > 0): ?>
    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <i class="bi bi-info-circle"></i>
            تعداد کل آرامگاه‌ها: <?php echo $total_tombs; ?>
        </small>
        <small class="text-muted">
            <i class="bi bi-check-circle text-success"></i>
            موجود: <?php echo $available_tombs; ?> | 
            <i class="bi bi-x-circle text-danger"></i>
            فروخته شده: <?php echo $sold_tombs; ?>
        </small>
    </div>
    <?php endif; ?>
</div>

<script>
function deleteTomb(id) {
    if (confirm('آیا از حذف این آرامگاه اطمینان دارید؟')) {
        window.location.href = '<?php echo BASE_URL; ?>/modules/tombs/index.php?delete=' + id;
    }
}

function toggleStatus(id) {
    if (confirm('آیا از تغییر وضعیت این آرامگاه اطمینان دارید؟')) {
        window.location.href = '<?php echo BASE_URL; ?>/modules/tombs/index.php?toggle=' + id;
    }
}
</script>

<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid #eee;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: #667eea;
}
.stat-card i {
    transition: transform 0.3s ease;
}
.stat-card:hover i {
    transform: scale(1.1);
}
.table thead tr {
    background-color: #4e73df !important;
}
.table thead th {
    color: white;
    font-weight: 500;
    border: none;
}
.table td {
    vertical-align: middle;
}
.btn-sm {
    transition: all 0.2s ease !important;
}
.btn-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
}
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
}
</style>

<?php include '../../includes/footer.php'; ?>
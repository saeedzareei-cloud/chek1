<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

$db = Database::getInstance()->getConnection();

// حذف مشتری
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // بررسی وجود درخواست برای این مشتری
    $check = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE customer_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        setMessage('این مشتری دارای درخواست خرید است و قابل حذف نمی‌باشد.', 'danger');
    } else {
        $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            setMessage('مشتری با موفقیت حذف شد.', 'success');
        } else {
            setMessage('خطا در حذف مشتری.', 'danger');
        }
    }
    
    redirect('/modules/customers/index.php');
}

// جستجو
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ساخت شرط جستجو
$where = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR national_code LIKE ? OR mobile LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// دریافت تعداد کل مشتریان
$count_query = "SELECT COUNT(*) as total FROM customers $where_clause";
$count_stmt = $db->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// دریافت لیست مشتریان
$query = "SELECT * FROM customers $where_clause ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result();

// دریافت آمار با Prepared Statement
$today = date('Y-m-d');
$stmt_today = $db->prepare("SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) = ?");
$stmt_today->bind_param('s', $today);
$stmt_today->execute();
$today_customers = $stmt_today->get_result()->fetch_assoc()['count'] ?? 0;

$has_requests = $db->query("SELECT COUNT(DISTINCT customer_id) as count FROM requests")->fetch_assoc()['count'] ?? 0;

$page_title = 'مدیریت مشتریان';
$header_icon = 'people';

include '../../includes/header.php';
?>

<!-- کارت آمار خلاصه -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-people-fill text-primary me-1"></i>
                        کل مشتریان
                    </h6>
                    <h3><?php echo number_format($total_records); ?></h3>
                </div>
                <i class="bi bi-people-fill fs-1 text-primary"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-calendar-check text-success me-1"></i>
                        مشتریان امروز
                    </h6>
                    <h3 class="text-success"><?php echo number_format($today_customers); ?></h3>
                </div>
                <i class="bi bi-calendar-check fs-1 text-success"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-file-text text-info me-1"></i>
                        دارای درخواست
                    </h6>
                    <h3 class="text-info"><?php echo number_format($has_requests); ?></h3>
                </div>
                <i class="bi bi-file-text fs-1 text-info"></i>
            </div>
        </div>
    </div>
</div>

<!-- جستجو و فیلتر -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-search"></i>
        جستجوی مشتریان
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" 
                       class="form-control" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="جستجو بر اساس نام، کد ملی یا شماره همراه...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> جستجو
                </button>
            </div>
        </form>
    </div>
</div>

<!-- لیست مشتریان -->
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list"></i>
            لیست مشتریان
        </span>
        <div>
            <span class="badge bg-light text-dark me-2">
                <i class="bi bi-people"></i> تعداد: <?php echo number_format($total_records); ?>
            </span>
            <a href="add.php" class="btn btn-light btn-sm">
                <i class="bi bi-plus-circle"></i> مشتری جدید
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($customers && $customers->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="customersTable">
                    <thead>
                        <tr style="background-color: #4e73df; color: white;">
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">کد ملی</th>
                            <th style="padding: 12px;">نام و نام خانوادگی</th>
                            <th style="padding: 12px;">نام پدر</th>
                            <th style="padding: 12px;">شماره همراه</th>
                            <th style="padding: 12px;">آدرس</th>
                            <th style="padding: 12px;">تاریخ ثبت</th>
                            <th style="padding: 12px;">تعداد درخواست</th>
                            <th style="padding: 12px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = $offset + 1;
                        while($row = $customers->fetch_assoc()): 
                            // تعداد درخواست‌های مشتری
                            $check = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE customer_id = ?");
                            $check->bind_param('i', $row['id']);
                            $check->execute();
                            $req_count = $check->get_result()->fetch_assoc()['count'];
                        ?>
                        <tr>
                            <td style="padding: 12px;"><?php echo $i++; ?></td>
                            <td style="padding: 12px;">
                                <strong class="text-primary"><?php echo htmlspecialchars($row['national_code']); ?></strong>
                            </td>
                            <td style="padding: 12px;">
                                <i class="bi bi-person-circle text-muted me-1"></i>
                                <?php echo htmlspecialchars($row['full_name']); ?>
                            </td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($row['father_name'] ?: '-'); ?></td>
                            <td style="padding: 12px;">
                                <i class="bi bi-phone text-muted me-1"></i>
                                <?php echo htmlspecialchars($row['mobile']); ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php 
                                $address = $row['address'] ?? '';
                                if (strlen($address) > 30) {
                                    $address = substr($address, 0, 30) . '...';
                                }
                                echo htmlspecialchars($address ?: '-');
                                ?>
                            </td>
                            <td style="padding: 12px;">
                                <i class="bi bi-calendar3 text-muted me-1"></i>
                                <?php echo jdate('Y/m/d', strtotime($row['created_at'])); ?>
                            </td>
                            <td style="padding: 12px;" class="text-center">
                                <?php if ($req_count > 0): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-file-text"></i> <?php echo $req_count; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <div class="d-flex gap-1">
                                    <!-- دکمه مشاهده جزئیات -->
                                    <a href="view.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm" 
                                       style="background: #17a2b8; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="مشاهده جزئیات"
                                       onmouseover="this.style.background='#138496'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#17a2b8'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <!-- دکمه ویرایش -->
                                    <a href="edit.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm" 
                                       style="background: #ffc107; color: #333; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="ویرایش"
                                       onmouseover="this.style.background='#e0a800'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#ffc107'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    
                                    <?php if ($req_count == 0): ?>
                                    <!-- دکمه حذف -->
                                    <a href="javascript:void(0);" 
                                       onclick="deleteCustomer(<?php echo $row['id']; ?>, '<?php echo urlencode($search); ?>')"
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
            
            <!-- صفحه‌بندی -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                            <i class="bi bi-chevron-right"></i> قبلی
                        </a>
                    </li>
                    
                    <?php 
                    // نمایش حداکثر ۵ صفحه
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($p = $start_page; $p <= $end_page; $p++): 
                    ?>
                        <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                            بعدی <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">مشتری‌ای یافت نشد</h5>
                <?php if (!empty($search)): ?>
                    <p class="text-muted">هیچ مشتری با عبارت "<?php echo htmlspecialchars($search); ?>" یافت نشد</p>
                    <a href="index.php" class="btn btn-primary mt-2">
                        <i class="bi bi-arrow-repeat"></i>
                        پاک کردن جستجو
                    </a>
                <?php else: ?>
                    <p class="text-muted">هنوز هیچ مشتری ثبت نشده است</p>
                    <a href="add.php" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> افزودن مشتری جدید
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($customers && $customers->num_rows > 0): ?>
    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <i class="bi bi-info-circle"></i>
            نمایش <?php echo $offset + 1; ?> تا <?php echo min($offset + $limit, $total_records); ?> از <?php echo number_format($total_records); ?> مشتری
        </small>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportToExcel()">
                <i class="bi bi-file-excel"></i>
                خروجی Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i>
                پرینت
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- استایل اختصاصی -->
<style>
/* استایل کارت‌های آمار */
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

.stat-card h6 {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.stat-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 0;
}

/* استایل جدول */
.table {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.table thead tr {
    background-color: #4e73df !important;
}

.table thead th {
    color: white;
    font-weight: 500;
    border: none;
    white-space: nowrap;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: #f5f5f5;
    transform: scale(1.01);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #eee;
}

/* استایل بج‌ها */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge i {
    font-size: 0.9rem;
}

/* استایل دکمه‌های عملیات */
.btn-sm {
    transition: all 0.2s ease !important;
    border: none;
    outline: none;
    cursor: pointer;
}

.btn-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
}

/* استایل صفحه‌بندی */
.pagination {
    gap: 5px;
}

.page-link {
    border-radius: 8px;
    border: none;
    padding: 8px 15px;
    color: #667eea;
    transition: all 0.3s ease;
}

.page-link:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: translateY(-2px);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.page-item.disabled .page-link {
    background: #f8f9fa;
    color: #6c757d;
    cursor: not-allowed;
}

/* استایل فوتر */
.card-footer {
    background: white;
    border-top: 1px solid #eee;
    padding: 12px 20px;
}

/* استایل برای حالت خالی */
.py-5 i {
    opacity: 0.5;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 0.5; }
    50% { opacity: 1; }
    100% { opacity: 0.5; }
}

/* استایل برای موبایل */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 15px;
    }
    
    .d-flex.gap-1 {
        flex-wrap: wrap;
    }
    
    .btn-sm {
        margin-bottom: 5px;
    }
    
    .table td {
        white-space: nowrap;
    }
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .card-footer .d-flex {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<!-- اسکریپت‌ها -->
<script src="<?php echo BASE_URL; ?>/assets/js/jquery.min.js"></script>
<script>
// تابع خروجی Excel
function exportToExcel() {
    var table = document.getElementById('customersTable');
    var html = table.outerHTML;
    var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'customers_list.xls';
    link.click();
    URL.revokeObjectURL(link.href);
}

// تابع حذف مشتری
function deleteCustomer(id, search) {
    if (confirm('آیا از حذف این مشتری اطمینان دارید؟')) {
        window.location.href = '<?php echo BASE_URL; ?>/modules/customers/index.php?delete=' + id + '&search=' + search;
    }
}

// جستجوی سریع (اختیاری)
$(document).ready(function() {
    // فعال‌سازی tooltip
    $('[title]').tooltip({ placement: 'top' });
});
</script>

<?php include '../../includes/footer.php'; ?>
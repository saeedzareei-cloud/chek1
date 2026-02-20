<?php

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// بررسی ورود
Auth::requireLogin();

// اگر کاربر دسترسی داشبورد ندارد، به اولین صفحه مجاز هدایت شود
if (!Auth::hasPermission('dashboard')) {
    header('Location: ' . Auth::getLandingUrl());
    exit;
}

$current_user = Auth::getCurrentUser();
$db = Database::getInstance()->getConnection();

// دریافت تاریخ شروع و پایان سال شمسی جاری
$current_year = jdate('Y');

if (function_exists('jalali_to_gregorian')) {
    $start_gregorian = jalali_to_gregorian($current_year, 1, 1);
    $end_gregorian = jalali_to_gregorian($current_year, 12, 30);
    $start_date = $start_gregorian[0] . '-' . $start_gregorian[1] . '-' . $start_gregorian[2];
    $end_date = $end_gregorian[0] . '-' . $end_gregorian[1] . '-' . $end_gregorian[2];
} else {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
}

try {
    // تعداد درخواست‌های امروز
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE request_date = ?");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $today_requests = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // تعداد درخواست‌های در انتظار تایید
    // (برای سازگاری با داده‌های قبلی، وضعیت «منتظر تایید» هم لحاظ می‌شود)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE status IN ('ارجاع برای امضا','منتظر تایید')");
    $stmt->execute();
    $pending_requests = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // تعداد کل درخواست‌ها در سال جاری
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE request_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $yearly_requests = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // تعداد قبور فروخته شده در سال جاری
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM request_items ri
        JOIN requests r ON ri.request_id = r.id
        WHERE ri.item_type = 'قبر' 
        AND r.request_date BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $graves_sold = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // آمار چک‌های سال جاری
    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM checks WHERE DATE(due_date) <= ? AND DATE(due_date) >= ?");
    $stmt->bind_param('ss', $today, $start_date);
    $stmt->execute();
    $cleared_checks = $stmt->get_result()->fetch_assoc();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM checks WHERE DATE(due_date) > ? AND DATE(due_date) <= ?");
    $stmt->bind_param('ss', $today, $end_date);
    $stmt->execute();
    $pending_checks = $stmt->get_result()->fetch_assoc();
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM checks WHERE DATE(due_date) BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $total_checks_year = $stmt->get_result()->fetch_assoc()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM checks WHERE DATE(due_date) = ?");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $due_today = $stmt->get_result()->fetch_assoc();
    
    // آخرین درخواست‌ها
    $recent_requests = $db->query("
        SELECT r.*, c.full_name as customer_name 
        FROM requests r 
        JOIN customers c ON r.customer_id = c.id 
        ORDER BY r.id DESC 
        LIMIT 10
    ");
    
    // آخرین چک‌های سررسید شده
    $recent_checks = $db->query("
        SELECT c.*, r.request_number, cu.full_name as customer_name 
        FROM checks c
        JOIN requests r ON c.request_id = r.id
        JOIN customers cu ON r.customer_id = cu.id
        WHERE DATE(c.due_date) <= CURDATE() AND (c.is_received = 0 OR c.is_received IS NULL)
        ORDER BY c.due_date DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$page_title = 'داشبورد';
$header_icon = 'speedometer2';

include 'includes/header.php';
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>خطا:</strong> <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- ردیف اول: کارت‌های اصلی -->
<div class="row">
    <div class="col-md-3">
        <a href="<?php echo BASE_URL; ?>/modules/requests/index.php?date=today" class="stat-link">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-calendar-day text-primary me-1"></i>
                            درخواست‌های امروز
                        </h6>
                        <h3><?php echo number_format($today_requests); ?></h3>
                    </div>
                    <i class="bi bi-calendar-check text-primary stat-icon"></i>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-3">
        <a href="<?php echo BASE_URL; ?>/modules/requests/index.php?status=ارجاع برای امضا" class="stat-link">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-hourglass-split text-warning me-1"></i>
                            در انتظار تایید
                        </h6>
                        <h3><?php echo number_format($pending_requests); ?></h3>
                    </div>
                    <i class="bi bi-clock-history text-warning stat-icon"></i>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-file-text text-info me-1"></i>
                        تعداد درخواست‌ها (سال <?php echo $current_year; ?>)
                    </h6>
                    <h3 class="text-primary"><?php echo number_format($yearly_requests); ?></h3>
                </div>
                <i class="bi bi-bar-chart text-info stat-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-box text-success me-1"></i>
                        قبور فروخته شده (سال <?php echo $current_year; ?>)
                    </h6>
                    <h3><?php echo number_format($graves_sold); ?></h3>
                </div>
                <i class="bi bi-box-seam text-success stat-icon"></i>
            </div>
        </div>
    </div>
</div>

<!-- ردیف دوم: آمار چک‌ها -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-right: 4px solid #28a745;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-muted mb-0">
                    <i class="bi bi-check-circle-fill text-success me-1"></i> 
                    چک‌های وصول شده (سال <?php echo $current_year; ?>)
                </h6>
                <span class="badge bg-success"><?php echo number_format($cleared_checks['count']); ?> فقره</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-success small">
                    <i class="bi bi-cash-stack me-1"></i>جمع وصولی:
                </span>
                <h6 class="text-success mb-0"><?php echo formatMoney($cleared_checks['total']); ?></h6>
            </div>
            <div class="progress mt-2" style="height: 5px;">
                <?php 
                $cleared_percent = $total_checks_year > 0 ? ($cleared_checks['total'] / $total_checks_year) * 100 : 0;
                ?>
                <div class="progress-bar bg-success" style="width: <?php echo $cleared_percent; ?>%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card" style="border-right: 4px solid #ffc107;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-muted mb-0">
                    <i class="bi bi-hourglass-split text-warning me-1"></i> 
                    چک‌های پاس نشده (سال <?php echo $current_year; ?>)
                </h6>
                <span class="badge bg-warning"><?php echo number_format($pending_checks['count']); ?> فقره</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-warning small">
                    <i class="bi bi-cash-stack me-1"></i>جمع مانده:
                </span>
                <h6 class="text-warning mb-0"><?php echo formatMoney($pending_checks['total']); ?></h6>
            </div>
            <div class="progress mt-2" style="height: 5px;">
                <?php 
                $pending_percent = $total_checks_year > 0 ? ($pending_checks['total'] / $total_checks_year) * 100 : 0;
                ?>
                <div class="progress-bar bg-warning" style="width: <?php echo $pending_percent; ?>%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card" style="border-right: 4px solid #dc3545;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-muted mb-0">
                    <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> 
                    چک‌های سررسید امروز
                </h6>
                <span class="badge bg-danger"><?php echo number_format($due_today['count']); ?> فقره</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-danger small">
                    <i class="bi bi-cash-stack me-1"></i>جمع:
                </span>
                <h6 class="text-danger mb-0"><?php echo formatMoney($due_today['total']); ?></h6>
            </div>
        </div>
    </div>
</div>

<!-- ردیف سوم: آخرین چک‌ها و درخواست‌ها -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning py-3">
                <i class="bi bi-clock-history me-1"></i> 
                آخرین چک‌های سررسید شده
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>شماره چک</th>
                                <th>مشتری</th>
                                <th>تاریخ سررسید</th>
                                <th>مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($recent_checks) && $recent_checks->num_rows > 0): ?>
                                <?php 
                                $recent_checks->data_seek(0);
                                while($check = $recent_checks->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($check['check_number']); ?></td>
                                    <td><?php echo htmlspecialchars($check['customer_name']); ?></td>
                                    <td>
                                        <?php 
                                        $due_date = $check['due_date'] ?? '';
                                        if (!empty($due_date) && $due_date != '0000-00-00') {
                                            $timestamp = strtotime($due_date);
                                            if ($timestamp !== false) {
                                                if (function_exists('jdate')) {
                                                    echo jdate('Y/m/d', $timestamp);
                                                } else {
                                                    echo jdate('Y/m/d', $timestamp);
                                                }
                                            } else {
                                                echo !empty($due_date) ? jdate('Y/m/d', strtotime($due_date)) : '';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="fw-bold text-primary"><?php echo formatMoney($check['amount']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        <span>چک سررسید شده‌ای یافت نشد</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-2">
                <a href="<?php echo BASE_URL; ?>/modules/reports/index.php?type=checks" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye me-1"></i> مشاهده همه چک‌ها
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white py-3">
                <i class="bi bi-clock-history me-1"></i>
                آخرین درخواست‌ها
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>شماره درخواست</th>
                                <th>مشتری</th>
                                <th>تاریخ</th>
                                <th>مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($recent_requests) && $recent_requests->num_rows > 0): ?>
                                <?php 
                                $recent_requests->data_seek(0);
                                while($row = $recent_requests->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['request_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td>
                                        <?php 
                                        $req_date = $row['request_date'] ?? '';
                                        if (!empty($req_date) && $req_date != '0000-00-00') {
                                            $timestamp = strtotime($req_date);
                                            if ($timestamp !== false) {
                                                if (function_exists('jdate')) {
                                                    echo jdate('Y/m/d', $timestamp);
                                                } else {
                                                    echo jdate('Y/m/d', $timestamp);
                                                }
                                            } else {
                                                echo htmlspecialchars($req_date);
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="fw-bold text-primary"><?php echo formatMoney($row['total_amount'] ?? 0); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        <span>درخواستی یافت نشد</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-2">
                <a href="<?php echo BASE_URL; ?>/modules/requests/index.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye me-1"></i> مشاهده همه درخواست‌ها
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* استایل کارت‌های آمار */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.1);
    border-color: #667eea;
}

.stat-card h6 {
    color: #6c757d;
    font-size: 0.8rem;
    margin-bottom: 4px;
    letter-spacing: 0.3px;
}

.stat-card h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: #2d3748;
}

.stat-icon {
    font-size: 1.8rem;
    opacity: 0.7;
}

.stat-card:hover .stat-icon {
    opacity: 1;
    transform: scale(1.05);
}

.stat-link {
    text-decoration: none;
    color: inherit;
    display: block;
    height: 100%;
}

/* استایل کارت‌ها */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.03);
    margin-bottom: 20px;
    overflow: hidden;
    border: 1px solid #f5f5f5;
}

.card-header {
    padding: 12px 18px;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: none;
}

.card-header.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white;
}

.card-header.bg-warning {
    background: #fff3cd !important;
    color: #856404;
    border-bottom: 1px solid #ffeeba;
}

.card-footer {
    background: white;
    border-top: 1px solid #f0f0f0;
    padding: 10px 18px;
}

/* استایل جدول */
.table {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.table thead th {
    background: #f8fafc;
    border-bottom: 1px solid #e9ecef;
    padding: 12px 15px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
    color: #4a5568;
}

.table tbody tr:hover {
    background-color: #f8fafc;
}

/* استایل پیشرفت */
.progress {
    height: 4px;
    border-radius: 2px;
    background-color: #e9ecef;
    margin-top: 8px;
}

.progress-bar {
    border-radius: 2px;
}

/* استایل بج‌ها */
.badge {
    padding: 4px 8px;
    border-radius: 16px;
    font-weight: 500;
    font-size: 0.7rem;
}

/* استایل برای موبایل */
@media (max-width: 768px) {
    .col-md-3, .col-md-4, .col-md-6 {
        margin-bottom: 12px;
    }
    
    .stat-card h3 {
        font-size: 1.3rem;
    }
    
    .stat-icon {
        font-size: 1.5rem;
    }
    
    .table {
        font-size: 0.8rem;
    }
    
    .table td, .table th {
        padding: 10px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
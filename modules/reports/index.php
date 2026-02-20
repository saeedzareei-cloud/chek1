<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

$db = Database::getInstance()->getConnection();

// دریافت پارامترهای گزارش
$report_type = $_GET['type'] ?? 'requests';
$date_from = $_GET['date_from'] ?? jdate('Y/m/01');
$date_to = $_GET['date_to'] ?? jdate('Y/m/d');
$status = $_GET['status'] ?? '';
$customer = $_GET['customer'] ?? '';
$section_id = $_GET['section_id'] ?? '';
$year = $_GET['year'] ?? jdate('Y');

// تبدیل تاریخ شمسی به میلادی
$g_date_from = '';
$g_date_to = '';

if (!empty($date_from)) {
    $date_parts = explode('/', $date_from);
    if (count($date_parts) == 3) {
        $g_date = jalali_to_gregorian($date_parts[0], $date_parts[1], $date_parts[2]);
        $g_date_from = $g_date[0] . '-' . str_pad($g_date[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g_date[2], 2, '0', STR_PAD_LEFT);
    }
}

if (!empty($date_to)) {
    $date_parts = explode('/', $date_to);
    if (count($date_parts) == 3) {
        $g_date = jalali_to_gregorian($date_parts[0], $date_parts[1], $date_parts[2]);
        $g_date_to = $g_date[0] . '-' . str_pad($g_date[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g_date[2], 2, '0', STR_PAD_LEFT);
    }
}

// دریافت لیست قطعات برای فیلتر
$sections = $db->query("SELECT * FROM sections ORDER BY name");

// دریافت سال‌های مختلف (شمسی) برای گزارش
$years_list = [];
$minmax = $db->query("SELECT MIN(request_date) as min_date, MAX(request_date) as max_date FROM requests");
if ($minmax) {
    $mm = $minmax->fetch_assoc();
    if (!empty($mm['min_date']) && !empty($mm['max_date'])) {
        $min_jy = (int)jdate('Y', strtotime($mm['min_date']));
        $max_jy = (int)jdate('Y', strtotime($mm['max_date']));
        if ($min_jy > 0 && $max_jy > 0) {
            for ($jy = $max_jy; $jy >= $min_jy; $jy--) {
                $years_list[] = $jy;
            }
        }
    }
}
$page_title = 'گزارش‌ها';
$header_icon = 'file-text';

include '../../includes/header.php';
?>

<!-- تب‌های گزارش -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-bar-chart"></i>
        انواع گزارش
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $report_type == 'requests' ? 'active' : ''; ?>" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>/modules/reports/index.php?type=requests'">
                    <i class="bi bi-file-text"></i> گزارش درخواست‌ها
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $report_type == 'financial' ? 'active' : ''; ?>" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>/modules/reports/index.php?type=financial'">
                    <i class="bi bi-cash-stack"></i> گزارش مالی
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $report_type == 'graves' ? 'active' : ''; ?>" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>/modules/reports/index.php?type=graves'">
                    <i class="bi bi-grid"></i> گزارش قبور فروخته شده
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $report_type == 'tombs' ? 'active' : ''; ?>" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>/modules/reports/index.php?type=tombs'">
                    <i class="bi bi-building"></i> گزارش آرامگاه‌ها
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $report_type == 'checks' ? 'active' : ''; ?>" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>/modules/reports/index.php?type=checks'">
                    <i class="bi bi-cash"></i> گزارش چک‌ها
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $report_type == 'customers' ? 'active' : ''; ?>" 
                        onclick="window.location.href='<?php echo BASE_URL; ?>/modules/reports/index.php?type=customers'">
                    <i class="bi bi-people"></i> گزارش مشتریان
                </button>
            </li>
        </ul>
    </div>
</div>

<!-- فیلترهای گزارش -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <i class="bi bi-funnel"></i>
        فیلترهای گزارش
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            
            <?php if ($report_type == 'requests' || $report_type == 'financial'): ?>
                <div class="col-md-3">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" name="status">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="در حال تکمیل" <?php echo $status == 'در حال تکمیل' ? 'selected' : ''; ?>>در حال تکمیل</option>
                        <option value="ارجاع برای امضا" <?php echo $status == 'ارجاع برای امضا' ? 'selected' : ''; ?>>ارجاع برای امضا</option>
                        <option value="تایید شده" <?php echo $status == 'تایید شده' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="رد شده" <?php echo $status == 'رد شده' ? 'selected' : ''; ?>>رد شده</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">مشتری</label>
                    <input type="text" class="form-control" name="customer" value="<?php echo htmlspecialchars($customer); ?>" placeholder="نام یا کد ملی">
                </div>
            <?php endif; ?>
            
            <?php if ($report_type == 'graves'): ?>
                <div class="col-md-3">
                    <label class="form-label">قطعه</label>
                    <select class="form-select" name="section_id">
                        <option value="">همه قطعات</option>
                        <?php 
                        if ($sections && $sections->num_rows > 0) {
                            $sections->data_seek(0);
                            while($section = $sections->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $section_id == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['name']); ?>
                            </option>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if ($report_type == 'checks'): ?>
                <div class="col-md-3">
                    <label class="form-label">وضعیت چک</label>
                    <select class="form-select" name="check_status">
                        <option value="">همه چک‌ها</option>
                        <option value="pending" <?php echo ($_GET['check_status'] ?? '') == 'pending' ? 'selected' : ''; ?>>وصول نشده</option>
                        <option value="cleared" <?php echo ($_GET['check_status'] ?? '') == 'cleared' ? 'selected' : ''; ?>>وصول شده</option>
                        <option value="overdue" <?php echo ($_GET['check_status'] ?? '') == 'overdue' ? 'selected' : ''; ?>>سررسید شده (وصول نشده)</option>
                        <option value="upcoming" <?php echo ($_GET['check_status'] ?? '') == 'upcoming' ? 'selected' : ''; ?>>آینده (وصول نشده)</option>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if ($report_type == 'tombs'): ?>
                <div class="col-md-3">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" name="tomb_status">
                        <option value="">همه</option>
                        <option value="available" <?php echo ($_GET['tomb_status'] ?? '') == 'available' ? 'selected' : ''; ?>>موجود</option>
                        <option value="sold" <?php echo ($_GET['tomb_status'] ?? '') == 'sold' ? 'selected' : ''; ?>>فروخته شده</option>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label class="form-label">از تاریخ</label>
                <input type="text" class="form-control persian-date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">تا تاریخ</label>
                <input type="text" class="form-control persian-date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">سال</label>
                <select class="form-select" name="year">
                    <option value="">همه سال‌ها</option>
                    <?php if (!empty($years_list)) { foreach ($years_list as $jy): ?>
                        <option value="<?php echo $jy; ?>" <?php echo (string)$year === (string)$jy ? 'selected' : ''; ?>>
                            <?php echo $jy; ?>
                        </option>
                    <?php endforeach; } ?>
                </select>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> نمایش گزارش
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/reports/index.php?type=<?php echo $report_type; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-repeat"></i> پاک کردن فیلترها
                </a>
                <button type="button" class="btn btn-success" onclick="exportToExcel()">
                    <i class="bi bi-file-excel"></i> خروجی Excel
                </button>
                <button type="button" class="btn btn-warning" onclick="window.print()">
                    <i class="bi bi-printer"></i> پرینت
                </button>
            </div>
        </form>
    </div>
</div>

<!-- نمایش گزارش -->
<div class="card">
    <div class="card-header bg-info text-white">
        <i class="bi bi-bar-chart"></i>
        نتایج گزارش
    </div>
    <div class="card-body">
        <?php
        // گزارش درخواست‌ها
        if ($report_type == 'requests'):
            $where = [];
            $params = [];
            $types = "";
            
            if (!empty($g_date_from)) {
                $where[] = "r.request_date >= ?";
                $params[] = $g_date_from;
                $types .= "s";
            }
            
            if (!empty($g_date_to)) {
                $where[] = "r.request_date <= ?";
                $params[] = $g_date_to;
                $types .= "s";
            }

            // فیلتر سال (شمسی) - تبدیل به بازه میلادی
            if (!empty($year)) {
                $jy = (int)$year;
                $g1 = jalali_to_gregorian($jy, 1, 1);
                $g2 = jalali_to_gregorian($jy + 1, 1, 1);
                $g_start = $g1[0] . '-' . str_pad($g1[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g1[2], 2, '0', STR_PAD_LEFT);
                $g_next  = $g2[0] . '-' . str_pad($g2[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g2[2], 2, '0', STR_PAD_LEFT);
                $where[] = "r.request_date >= ? AND r.request_date < ?";
                $params[] = $g_start;
                $params[] = $g_next;
                $types .= "ss";
            }

            
            if (!empty($status)) {
                $where[] = "r.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($customer)) {
                $where[] = "(c.full_name LIKE ? OR c.national_code LIKE ?)";
                $search = "%$customer%";
                $params[] = $search;
                $params[] = $search;
                $types .= "ss";
            }
            
            $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $query = "SELECT r.*, c.full_name as customer_name, c.national_code, c.mobile,
                      u.full_name as registrar_name
                      FROM requests r
                      JOIN customers c ON r.customer_id = c.id
                      LEFT JOIN users u ON r.registrar_user_id = u.id
                      $where_clause
                      ORDER BY r.id DESC";
            
            $stmt = $db->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $results = $stmt->get_result();
        ?>
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>شماره درخواست</th>
                        <th>تاریخ</th>
                        <th>مشتری</th>
                        <th>کد ملی</th>
                        <th>تلفن</th>
                        <th>مبلغ کل</th>
                        <th>وضعیت</th>
                        <th>ثبت کننده</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $total_sum = 0;
                    while($row = $results->fetch_assoc()): 
                        $total_sum += $row['total_amount'] ?? 0;
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['request_number']); ?></td>
                        <td><?php echo jdate('Y/m/d', strtotime($row['request_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td><?php echo $row['national_code']; ?></td>
                        <td><?php echo $row['mobile']; ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_amount'] ?? 0); ?></td>
                        <td>
                            <?php
                            $badge_class = [
                                'در حال تکمیل' => 'secondary',
                                'ارجاع برای امضا' => 'warning',
                                'تایید شده' => 'success',
                                'رد شده' => 'danger'
                            ][$row['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['registrar_name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="6" class="text-start">جمع کل:</th>
                        <th class="text-start"><?php echo formatMoney($total_sum); ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        
        <?php 
        // گزارش مالی
        elseif ($report_type == 'financial'):
            $where = [];
            $params = [];
            $types = "";
            
            if (!empty($g_date_from)) {
                $where[] = "r.request_date >= ?";
                $params[] = $g_date_from;
                $types .= "s";
            }
            
            if (!empty($g_date_to)) {
                $where[] = "r.request_date <= ?";
                $params[] = $g_date_to;
                $types .= "s";
            }
            
            if (!empty($status)) {
                $where[] = "r.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            $where_clause = !empty($where) ? "AND " . implode(" AND ", $where) : "";
            
            $query = "SELECT 
                        DATE_FORMAT(r.request_date, '%Y-%m') as month,
                        COUNT(*) as request_count,
                        SUM(r.total_amount) as total_amount,
                        SUM(r.cash_amount) as total_cash,
                        SUM(r.check_amount) as total_check,
                        AVG(r.total_amount) as avg_amount
                      FROM requests r
                      WHERE r.status = 'تایید شده' $where_clause
                      GROUP BY DATE_FORMAT(r.request_date, '%Y-%m')
                      ORDER BY month DESC";
            
            $stmt = $db->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $results = $stmt->get_result();
            
            // آمار کلی
            $total_stats = $db->query("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(total_amount) as total_amount,
                    SUM(cash_amount) as total_cash,
                    SUM(check_amount) as total_check
                FROM requests
                WHERE status = 'تایید شده'
            ")->fetch_assoc();
        ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">کل درخواست‌ها</h6>
                        <h3><?php echo number_format($total_stats['total_requests']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">جمع کل</h6>
                        <h3 class="text-primary"><?php echo formatMoney($total_stats['total_amount']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">جمع نقدی</h6>
                        <h3 class="text-success"><?php echo formatMoney($total_stats['total_cash']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">جمع چک</h6>
                        <h3 class="text-warning"><?php echo formatMoney($total_stats['total_check']); ?></h3>
                    </div>
                </div>
            </div>
            
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>ماه</th>
                        <th>تعداد درخواست</th>
                        <th>جمع کل</th>
                        <th>جمع نقدی</th>
                        <th>جمع چک</th>
                        <th>میانگین هر قرارداد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_requests = 0;
                    $total_amount = 0;
                    if ($results && $results->num_rows > 0):
                        while($row = $results->fetch_assoc()): 
                            $total_requests += $row['request_count'];
                            $total_amount += $row['total_amount'];
                            $month_name = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                            $month_num = (int)substr($row['month'], 5, 2);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $month_name[$month_num - 1]; ?></strong>
                            <small class="text-muted d-block"><?php echo substr($row['month'], 0, 4); ?></small>
                        </td>
                        <td class="text-center"><?php echo number_format($row['request_count']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_amount']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_cash']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_check']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['avg_amount']); ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th class="text-start">جمع کل:</th>
                        <th class="text-center"><?php echo number_format($total_requests); ?></th>
                        <th class="text-start"><?php echo formatMoney($total_amount); ?></th>
                        <th colspan="3"></th>
                    </tr>
                </tfoot>
            </table>
        
        <?php 
        // گزارش قبور فروخته شده
        elseif ($report_type == 'graves'):
            $where = [];
            $params = [];
            $types = "";
            
            if (!empty($g_date_from)) {
                $where[] = "r.request_date >= ?";
                $params[] = $g_date_from;
                $types .= "s";
            }
            
            if (!empty($g_date_to)) {
                $where[] = "r.request_date <= ?";
                $params[] = $g_date_to;
                $types .= "s";
            }
            
            if (!empty($section_id)) {
                $where[] = "ri.section_id = ?";
                $params[] = $section_id;
                $types .= "i";
            }
            
            $where_clause = !empty($where) ? "AND " . implode(" AND ", $where) : "";
            
            $query = "SELECT 
                        s.name as section_name,
                        COUNT(ri.id) as grave_count,
                        SUM(ri.price) as total_price,
                        SUM(ri.file_creation_fee) as total_fees,
                        SUM(ri.stone_reservation_fee) as total_stone
                      FROM request_items ri
                      JOIN requests r ON ri.request_id = r.id
                      LEFT JOIN sections s ON ri.section_id = s.id
                      WHERE r.status = 'تایید شده' AND ri.item_type = 'قبر' $where_clause
                      GROUP BY s.id
                      ORDER BY grave_count DESC";
            
            $stmt = $db->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $results = $stmt->get_result();
            
            // آمار کلی
            $total_stats = $db->query("
                SELECT 
                    COUNT(*) as total_graves,
                    SUM(price) as total_price,
                    SUM(file_creation_fee) as total_fees,
                    SUM(stone_reservation_fee) as total_stone
                FROM request_items ri
                JOIN requests r ON ri.request_id = r.id
                WHERE r.status = 'تایید شده' AND ri.item_type = 'قبر'
            ")->fetch_assoc();
        ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">کل قبور فروخته شده</h6>
                        <h3><?php echo number_format($total_stats['total_graves']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">جمع قیمت قبور</h6>
                        <h3 class="text-primary"><?php echo formatMoney($total_stats['total_price']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">جمع هزینه تشکیل پرونده</h6>
                        <h3 class="text-success"><?php echo formatMoney($total_stats['total_fees']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">جمع هزینه سنگ رزرو</h6>
                        <h3 class="text-info"><?php echo formatMoney($total_stats['total_stone']); ?></h3>
                    </div>
                </div>
            </div>
            
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>قطعه</th>
                        <th>تعداد قبر فروخته شده</th>
                        <th>جمع قیمت قبور</th>
                        <th>جمع هزینه تشکیل پرونده</th>
                        <th>جمع هزینه سنگ رزرو</th>
                        <th>جمع کل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_graves = 0;
                    $total_price = 0;
                    $total_fees = 0;
                    $total_stone = 0;
                    if ($results && $results->num_rows > 0):
                        while($row = $results->fetch_assoc()): 
                            $total_graves += $row['grave_count'];
                            $total_price += $row['total_price'];
                            $total_fees += $row['total_fees'];
                            $total_stone += $row['total_stone'];
                            $section_total = $row['total_price'] + $row['total_fees'] + $row['total_stone'];
                    ?>
                    <tr>
                        <td><strong><?php echo $row['section_name'] ?? 'تعریف نشده'; ?></strong></td>
                        <td class="text-center"><?php echo number_format($row['grave_count']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_price']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_fees']); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['total_stone']); ?></td>
                        <td class="text-start"><?php echo formatMoney($section_total); ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th class="text-start">جمع کل:</th>
                        <th class="text-center"><?php echo number_format($total_graves); ?></th>
                        <th class="text-start"><?php echo formatMoney($total_price); ?></th>
                        <th class="text-start"><?php echo formatMoney($total_fees); ?></th>
                        <th class="text-start"><?php echo formatMoney($total_stone); ?></th>
                        <th class="text-start"><?php echo formatMoney($total_price + $total_fees + $total_stone); ?></th>
                    </tr>
                </tfoot>
            </table>
        
        <?php 
        // گزارش آرامگاه‌ها
        elseif ($report_type == 'tombs'):
            $where = [];
            $tomb_status = $_GET['tomb_status'] ?? '';
            
            if ($tomb_status == 'available') {
                $where[] = "is_available = 1";
            } elseif ($tomb_status == 'sold') {
                $where[] = "is_available = 0";
            }
            
            $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $query = "SELECT * FROM tombs $where_clause ORDER BY id DESC";
            $results = $db->query($query);
            
            $available = $db->query("SELECT COUNT(*) as count FROM tombs WHERE is_available = 1")->fetch_assoc()['count'];
            $sold = $db->query("SELECT COUNT(*) as count FROM tombs WHERE is_available = 0")->fetch_assoc()['count'];
        ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6 class="text-muted">کل آرامگاه‌ها</h6>
                        <h3><?php echo number_format($results->num_rows); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6 class="text-muted">موجود</h6>
                        <h3 class="text-success"><?php echo number_format($available); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6 class="text-muted">فروخته شده</h6>
                        <h3 class="text-danger"><?php echo number_format($sold); ?></h3>
                    </div>
                </div>
            </div>
            
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>شماره آرامگاه</th>
                        <th>تعداد قبر</th>
                        <th>موقعیت</th>
                        <th>قیمت</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $total_price = 0;
                    if ($results && $results->num_rows > 0):
                        while($row = $results->fetch_assoc()): 
                            $total_price += $row['price'];
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['tomb_number']); ?></strong></td>
                        <td class="text-center"><?php echo $row['grave_count']; ?></td>
                        <td><?php echo htmlspecialchars($row['row_position'] ?? '-'); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['price']); ?></td>
                        <td>
                            <?php if ($row['is_available']): ?>
                                <span class="badge bg-success">موجود</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">فروخته شده</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="4" class="text-start">جمع کل:</th>
                        <th class="text-start"><?php echo formatMoney($total_price); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        
        <?php 
        // گزارش چک‌ها
        elseif ($report_type == 'checks'):
            $today = date('Y-m-d');
            $check_status = $_GET['check_status'] ?? '';
            
            $where = [];
            $params = [];
            $types = "";
            
            if (!empty($g_date_from)) {
                $where[] = "DATE(c.due_date) >= ?";
                $params[] = $g_date_from;
                $types .= "s";
            }
            
            if (!empty($g_date_to)) {
                $where[] = "DATE(c.due_date) <= ?";
                $params[] = $g_date_to;
                $types .= "s";
            }

            // فیلتر سال (شمسی) - تبدیل به بازه میلادی
            if (!empty($year)) {
                $jy = (int)$year;
                $g1 = jalali_to_gregorian($jy, 1, 1);
                $g2 = jalali_to_gregorian($jy + 1, 1, 1);
                $g_start = $g1[0] . '-' . str_pad($g1[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g1[2], 2, '0', STR_PAD_LEFT);
                $g_next  = $g2[0] . '-' . str_pad($g2[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g2[2], 2, '0', STR_PAD_LEFT);
                $where[] = "DATE(c.due_date) >= ? AND DATE(c.due_date) < ?";
                $params[] = $g_start;
                $params[] = $g_next;
                $types .= "ss";
            }

            
            if ($check_status == 'pending') {
                $where[] = "c.is_received = 0";
            } elseif ($check_status == 'cleared') {
                $where[] = "c.is_received = 1";
            } elseif ($check_status == 'overdue') {
                $where[] = "c.is_received = 0 AND DATE(c.due_date) <= ?";
                $params[] = $today;
                $types .= "s";
            } elseif ($check_status == 'upcoming') {
                $where[] = "c.is_received = 0 AND DATE(c.due_date) > ?";
                $params[] = $today;
                $types .= "s";
            }

            $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $query = "SELECT c.*, r.request_number, cu.full_name as customer_name, cu.national_code
                      FROM checks c
                      JOIN requests r ON c.request_id = r.id
                      JOIN customers cu ON r.customer_id = cu.id
                      $where_clause
                      ORDER BY c.due_date DESC";
            
            $stmt = $db->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $results = $stmt->get_result();
            
            // آمار چک‌ها
            $stmt = $db->prepare("SELECT SUM(amount) as total FROM checks WHERE DATE(due_date) > ?");
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $pending_total = $stmt->get_result()->fetch_assoc()['total'];
            $stmt = $db->prepare("SELECT SUM(amount) as total FROM checks WHERE DATE(due_date) <= ?");
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $cleared_total = $stmt->get_result()->fetch_assoc()['total'];
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM checks WHERE DATE(due_date) > ?");
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $pending_count = $stmt->get_result()->fetch_assoc()['count'];
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM checks WHERE DATE(due_date) <= ?");
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $cleared_count = $stmt->get_result()->fetch_assoc()['count'];
        ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">کل چک‌ها</h6>
                        <h3><?php echo number_format($results->num_rows); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">چک‌های وصول شده</h6>
                        <h3 class="text-success"><?php echo number_format($cleared_count); ?></h3>
                        <small><?php echo formatMoney($cleared_total); ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">چک‌های وصول نشده</h6>
                        <h3 class="text-warning"><?php echo number_format($pending_count); ?></h3>
                        <small><?php echo formatMoney($pending_total); ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6 class="text-muted">درصد وصول</h6>
                        <?php
                        $total_checks = $cleared_total + $pending_total;
                        $percent = $total_checks > 0 ? round(($cleared_total / $total_checks) * 100) : 0;
                        ?>
                        <h3 class="text-info"><?php echo $percent; ?>%</h3>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>شماره چک</th>
                        <th>شماره درخواست</th>
                        <th>مشتری</th>
                        <th>بانک</th>
                        <th>تاریخ سررسید</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $total_amount = 0;
                    if ($results && $results->num_rows > 0):
                        while($row = $results->fetch_assoc()): 
                            $total_amount += $row['amount'];
                            $is_received = (int)($row['is_received'] ?? 0) === 1;
                            $is_overdue = !$is_received && strtotime($row['due_date']) <= strtotime($today);
?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['check_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['request_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['bank_name']); ?></td>
                        <td><?php echo jdate('Y/m/d', strtotime($row['due_date'])); ?></td>
                        <td class="text-start"><?php echo formatMoney($row['amount']); ?></td>
                        <td>
                            <?php if ($is_cleared): ?>
                                <span class="badge bg-success">وصول شده</span>
                            <?php else: ?>
                                <span class="badge bg-warning">در انتظار</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a class="btn btn-sm btn-primary" href="<?php echo BASE_URL; ?>/modules/checks/edit.php?id=<?php echo (int)$row['id']; ?>">
                                <i class="bi bi-pencil-square"></i> ویرایش
                            </a>
                            <a class="btn btn-sm btn-danger" href="<?php echo BASE_URL; ?>/modules/checks/delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('حذف این چک؟');">
                                <i class="bi bi-trash"></i> حذف
                            </a>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="7" class="text-start">جمع کل:</th>
                        <th class="text-start"><?php echo formatMoney($total_amount); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        
        <?php 
        // گزارش مشتریان
        elseif ($report_type == 'customers'):
            $query = "SELECT c.*, 
                         COUNT(r.id) as request_count,
                         SUM(r.total_amount) as total_purchases
                      FROM customers c
                      LEFT JOIN requests r ON c.id = r.customer_id AND r.status = 'تایید شده'
                      GROUP BY c.id
                      ORDER BY request_count DESC";
            
            $results = $db->query($query);
            
            $total_customers = $results->num_rows;
            $customers_with_request = $db->query("SELECT COUNT(DISTINCT customer_id) as count FROM requests")->fetch_assoc()['count'];
        ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6 class="text-muted">کل مشتریان</h6>
                        <h3><?php echo number_format($total_customers); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6 class="text-muted">مشتریان دارای خرید</h6>
                        <h3 class="text-success"><?php echo number_format($customers_with_request); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6 class="text-muted">درصد خریداران</h6>
                        <?php
                        $percent = $total_customers > 0 ? round(($customers_with_request / $total_customers) * 100) : 0;
                        ?>
                        <h3 class="text-info"><?php echo $percent; ?>%</h3>
                    </div>
                </div>
            </div>
            
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>کد ملی</th>
                        <th>نام و نام خانوادگی</th>
                        <th>شماره همراه</th>
                        <th>تاریخ ثبت</th>
                        <th>تعداد خرید</th>
                        <th>جمع خرید</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    if ($results && $results->num_rows > 0):
                        while($row = $results->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['national_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo jdate('Y/m/d', strtotime($row['created_at'])); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $row['request_count'] > 0 ? 'success' : 'secondary'; ?>">
                                <?php echo $row['request_count']; ?>
                            </span>
                        </td>
                        <td class="text-start"><?php echo formatMoney($row['total_purchases'] ?? 0); ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (isset($results) && $results->num_rows == 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">اطلاعاتی یافت نشد</h5>
                <p class="text-muted">هیچ داده‌ای با فیلترهای انتخاب شده وجود ندارد</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- استایل اختصاصی -->
<style>
/* استایل تب‌ها */
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
    background: transparent;
    cursor: pointer;
}

.nav-tabs .nav-link:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
}

.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

/* استایل کارت‌های آمار */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid #eee;
    text-align: center;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card h6 {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.stat-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 0;
}

.stat-card small {
    display: block;
    margin-top: 5px;
    font-size: 0.85rem;
}

/* استایل جدول */
.table {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    border: none;
    padding: 12px;
    font-size: 0.9rem;
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

.table td, .table th {
    padding: 12px;
    vertical-align: middle;
}

/* استایل پیشرفت */
.progress {
    height: 8px;
    border-radius: 4px;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 4px;
}

/* استایل برای موبایل */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 15px;
    }
    
    .nav-tabs {
        flex-wrap: wrap;
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
// فعال‌سازی تاریخ‌picker
$(document).ready(function() {
    $('.persian-date').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        observer: true
    });
});

// تابع خروجی Excel
function exportToExcel() {
    var table = document.getElementById('reportTable');
    var html = table.outerHTML;
    var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'report.xls';
    link.click();
    URL.revokeObjectURL(link.href);
}

// چاپ
window.onbeforeprint = function() {
    // آماده‌سازی برای چاپ
};
</script>

<?php include '../../includes/footer.php'; ?>
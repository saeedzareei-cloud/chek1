<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance()->getConnection();

// حذف درخواست
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // بررسی سطح دسترسی برای حذف
    $is_admin = ($_SESSION['access_level'] == 'مدیر سیستم');
    
    if ($is_admin) {
        // مدیر سیستم می‌تواند هر درخواستی را حذف کند
        $check = $db->prepare("SELECT id FROM requests WHERE id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $check->store_result();
        $can_delete = ($check->num_rows > 0);
        $error_msg = '';
    } else {
        // کاربر عادی فقط می‌تواند درخواست‌های "در حال تکمیل" خودش را حذف کند
        $check = $db->prepare("SELECT id FROM requests WHERE id = ? AND status = 'در حال تکمیل' AND registrar_user_id = ?");
        $check->bind_param('ii', $id, $_SESSION['user_id']);
        $check->execute();
        $check->store_result();
        $can_delete = ($check->num_rows > 0);
        $error_msg = 'شما فقط می‌توانید درخواست‌های "در حال تکمیل" خود را حذف کنید.';
    }
    
    if ($can_delete) {
        // ابتدا آیتم‌های وابسته را حذف کنیم
        $stmt = $db->prepare("DELETE FROM request_items WHERE request_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt = $db->prepare("DELETE FROM checks WHERE request_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // سپس خود درخواست را حذف کنیم
        $stmt = $db->prepare("DELETE FROM requests WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            setMessage('درخواست با موفقیت حذف شد.', 'success');
        } else {
            setMessage('خطا در حذف درخواست: ' . $db->error, 'danger');
        }
    } else {
        setMessage($error_msg ?: 'شما مجاز به حذف این درخواست نیستید.', 'danger');
    }
    
    echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
    exit;
}

// فیلترها
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$customer = $_GET['customer'] ?? '';
$request_no = $_GET['request_no'] ?? '';

// تبدیل تاریخ شمسی به میلادی
$g_date_from = '';
$g_date_to = '';

if (!empty($date_from)) {
    $date_parts = explode('/', $date_from);
    if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        require_once('jdf.php');
        $g_date = jalali_to_gregorian($date_parts[0], $date_parts[1], $date_parts[2]);
        $g_date_from = $g_date[0] . '-' . str_pad($g_date[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g_date[2], 2, '0', STR_PAD_LEFT);
    }
}

if (!empty($date_to)) {
    $date_parts = explode('/', $date_to);
    if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        require_once('jdf.php');
        $g_date = jalali_to_gregorian($date_parts[0], $date_parts[1], $date_parts[2]);
        $g_date_to = $g_date[0] . '-' . str_pad($g_date[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g_date[2], 2, '0', STR_PAD_LEFT);
    }
}

// ساخت شرط WHERE
$where_conditions = [];
$params = [];
$types = "";

if (!empty($status)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($g_date_from)) {
    $where_conditions[] = "r.request_date >= ?";
    $params[] = $g_date_from;
    $types .= "s";
}

if (!empty($g_date_to)) {
    $where_conditions[] = "r.request_date <= ?";
    $params[] = $g_date_to;
    $types .= "s";
}

if (!empty($customer)) {
    $where_conditions[] = "(c.full_name LIKE ? OR c.national_code LIKE ? OR c.mobile LIKE ?)";
    $search_term = "%$customer%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($request_no)) {
    $where_conditions[] = "r.request_number LIKE ?";
    $params[] = "%$request_no%";
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// دریافت لیست درخواست‌ها
$query = "SELECT r.*, c.full_name as customer_name, c.national_code, c.mobile, 
          u.full_name as registrar_name,
          u2.full_name as verifier_name,
          u2.signature_image as verifier_signature
          FROM requests r 
          JOIN customers c ON r.customer_id = c.id 
          LEFT JOIN users u ON r.registrar_user_id = u.id 
          LEFT JOIN users u2 ON r.verifier_user_id = u2.id 
          $where_clause 
          ORDER BY r.id DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result();

$page_title = 'لیست درخواست‌ها';
$header_icon = 'list-check';

include '../../includes/header.php';
?>

<!-- نمایش پیام‌ها -->
<?php echo displayMessage(); ?>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-funnel"></i>
        فیلترهای جستجو
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="request_no" class="form-label">شماره درخواست</label>
                <input type="text" class="form-control" id="request_no" name="request_no" 
                       value="<?php echo htmlspecialchars($request_no); ?>" placeholder="مثال: 1404...">
            </div>
            
            <div class="col-md-3">
                <label for="customer" class="form-label">اطلاعات مشتری</label>
                <input type="text" class="form-control" id="customer" name="customer" 
                       value="<?php echo htmlspecialchars($customer); ?>" placeholder="نام / کد ملی / موبایل">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">وضعیت</label>
                <select class="form-select" id="status" name="status">
                    <option value="">همه</option>
                    <option value="در حال تکمیل" <?php echo $status == 'در حال تکمیل' ? 'selected' : ''; ?>>در حال تکمیل</option>
                    <option value="ارجاع برای امضا" <?php echo $status == 'ارجاع برای امضا' ? 'selected' : ''; ?>>ارجاع برای امضا</option>
                    <option value="منتظر تایید" <?php echo $status == 'منتظر تایید' ? 'selected' : ''; ?>>منتظر تایید</option>
                    <option value="تایید شده" <?php echo $status == 'تایید شده' ? 'selected' : ''; ?>>تایید شده</option>
                    <option value="رد شده" <?php echo $status == 'رد شده' ? 'selected' : ''; ?>>رد شده</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">از تاریخ</label>
                <input type="text" class="form-control persian-date" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>" placeholder="1403/01/01">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">تا تاریخ</label>
                <input type="text" class="form-control persian-date" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>" placeholder="1403/12/29">
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> جستجو
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/requests/index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-repeat"></i> پاک کردن فیلترها
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/requests/new.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست جدید
                </a>
            </div>
        </form>
    </div>
</div>

<!-- لیست درخواست‌ها -->
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-table"></i>
            لیست درخواست‌ها
        </span>
        <span class="badge bg-light text-dark">
            <i class="bi bi-list"></i> تعداد: <?php echo $requests ? $requests->num_rows : 0; ?>
        </span>
    </div>
    
    <div class="card-body">
        <?php if ($requests && $requests->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr style="background-color: #4e73df; color: white;">
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">شماره درخواست</th>
                            <th style="padding: 12px;">تاریخ</th>
                            <th style="padding: 12px;">مشتری</th>
                            <th style="padding: 12px;">مبلغ کل</th>
                            <th style="padding: 12px;">وضعیت</th>
                            <th style="padding: 12px;">ثبت کننده</th>
                            <th style="padding: 12px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        while($row = $requests->fetch_assoc()): 
                        ?>
                        <tr>
                            <td style="padding: 12px;"><?php echo $i++; ?></td>
                            <td style="padding: 12px;">
                                <strong><?php echo htmlspecialchars($row['request_number']); ?></strong>
                            </td>
                            <td style="padding: 12px;">
                                <i class="bi bi-calendar3 text-muted"></i>
                                <?php 
                                // نمایش تاریخ به شمسی
                                if (!empty($row['request_date'])) {
                                    $timestamp = strtotime($row['request_date']);
                                    if ($timestamp !== false) {
                                        echo jdate('Y/m/d', $timestamp);
                                    } else {
                                        echo htmlspecialchars($row['request_date']);
                                    }
                                } else {
                                    echo '---';
                                }
                                ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php echo htmlspecialchars($row['customer_name']); ?>
                            </td>
                            <td style="padding: 12px;" class="text-start fw-bold text-primary">
                                <?php echo number_format((float)($row['total_amount'] ?? 0)); ?> ریال
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                $badge_class = [
                                    'در حال تکمیل' => 'secondary',
                                    'ارجاع برای امضا' => 'warning',
                                    'منتظر تایید' => 'warning',
                                    'تایید شده' => 'success',
                                    'رد شده' => 'danger'
                                ][$row['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($row['registrar_name'] ?: '-'); ?></td>
                            <td style="padding: 12px;">
                                <div class="d-flex gap-1">
                                    <!-- دکمه مشاهده جزئیات -->
                                    <a href="<?php echo BASE_URL; ?>/modules/requests/view.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm" 
                                       style="background: #17a2b8; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="مشاهده جزئیات"
                                       onmouseover="this.style.background='#138496'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#17a2b8'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php
                                    // بررسی دسترسی برای ویرایش/ادامه ثبت
                                    $can_edit = ($row['status'] == 'در حال تکمیل' && $row['registrar_user_id'] == $_SESSION['user_id']);
                                    
                                    // بررسی دسترسی برای حذف
                                    $can_delete = false;
                                    if ($_SESSION['access_level'] == 'مدیر سیستم') {
                                        $can_delete = true;
                                    } else {
                                        $can_delete = ($row['status'] == 'در حال تکمیل' && $row['registrar_user_id'] == $_SESSION['user_id']);
                                    }
                                    
                                    // بررسی دسترسی برای تایید
                                    $can_verify = (in_array($row['status'], ['ارجاع برای امضا', 'منتظر تایید']) && in_array($_SESSION['access_level'], ['مدیر سیستم', 'جانشین مدیر']));
                                    
                                    // بررسی دسترسی برای چاپ
                                    $can_print = ($row['status'] == 'تایید شده' && !empty($row['verifier_signature']));
                                    ?>
                                    
                                    <?php if ($can_edit): ?>
                                        <!-- دکمه ویرایش (ادامه ثبت) -->
                                        <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=2&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm" 
                                           style="background: #ffc107; color: #333; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                           title="ادامه ثبت"
                                           onmouseover="this.style.background='#e0a800'; this.style.transform='translateY(-2px)';"
                                           onmouseout="this.style.background='#ffc107'; this.style.transform='translateY(0)';">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete): ?>
                                        <!-- دکمه حذف -->
                                        <a href="javascript:void(0);" 
                                           onclick="deleteRequest(<?php echo $row['id']; ?>, '<?php echo $_SESSION['access_level']; ?>')"
                                           class="btn btn-sm" 
                                           style="background: #dc3545; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                           title="<?php echo ($_SESSION['access_level'] == 'مدیر سیستم') ? 'حذف (مدیر سیستم)' : 'حذف'; ?>"
                                           onmouseover="this.style.background='#c82333'; this.style.transform='translateY(-2px)';"
                                           onmouseout="this.style.background='#dc3545'; this.style.transform='translateY(0)';">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_verify): ?>
                                        <!-- دکمه تایید -->
                                        <a href="<?php echo BASE_URL; ?>/modules/requests/verify.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm" 
                                           style="background: #28a745; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                           title="تایید درخواست"
                                           onmouseover="this.style.background='#218838'; this.style.transform='translateY(-2px)';"
                                           onmouseout="this.style.background='#28a745'; this.style.transform='translateY(0)';">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (($row['status'] == 'تایید شده') && empty($row['verifier_signature'])): ?>
                                        <!-- قرارداد تایید شده ولی امضا ثبت نشده -->
                                        <span class="btn btn-sm"
                                              style="background: #adb5bd; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none; cursor:not-allowed;"
                                              title="امضای مدیر ثبت نشده است">
                                            <i class="bi bi-printer"></i>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($can_print): ?>
                                        <!-- دکمه چاپ قرارداد -->
                                        <a href="<?php echo BASE_URL; ?>/modules/contracts/print.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm" 
                                           style="background: #6f42c1; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                           title="چاپ قرارداد (۳ نسخه)"
                                           target="_blank"
                                           onmouseover="this.style.background='#5a32a3'; this.style.transform='translateY(-2px)';"
                                           onmouseout="this.style.background='#6f42c1'; this.style.transform='translateY(0)';">
                                            <i class="bi bi-printer"></i>
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
                <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">درخواستی یافت نشد</h5>
                <p class="text-muted">هیچ درخواستی با فیلترهای انتخاب شده وجود ندارد</p>
                <a href="<?php echo BASE_URL; ?>/modules/requests/new.php" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> ثبت درخواست جدید
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($requests && $requests->num_rows > 0): ?>
    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <i class="bi bi-info-circle"></i>
            تعداد کل درخواست‌ها: <?php echo $requests->num_rows; ?>
        </small>
        <small class="text-muted">
            <i class="bi bi-filter"></i>
            فیلترهای فعال: 
            <?php 
            $filters_active = [];
            if ($status) $filters_active[] = 'وضعیت';
            if ($date_from) $filters_active[] = 'از تاریخ';
            if ($date_to) $filters_active[] = 'تا تاریخ';
            if ($customer) $filters_active[] = 'مشتری';
            if ($request_no) $filters_active[] = 'شماره درخواست';
            
            echo !empty($filters_active) ? implode('، ', $filters_active) : 'هیچکدام';
            ?>
        </small>
    </div>
    <?php endif; ?>
</div>

<script>
function deleteRequest(id, accessLevel) {
    let message = 'آیا از حذف این درخواست اطمینان دارید؟\nاین عمل غیرقابل بازگشت است.';
    
    if (accessLevel === 'مدیر سیستم') {
        message = '⚠️ توجه: شما به عنوان مدیر سیستم در حال حذف این درخواست هستید.\nآیا مطمئن هستید؟';
    }
    
    if (confirm(message)) {
        window.location.href = '<?php echo BASE_URL; ?>/modules/requests/index.php?delete=' + id;
    }
}
</script>

<!-- اضافه کردن استایل برای اطمینان از نمایش صحیح -->
<style>
.btn-sm {
    transition: all 0.2s ease !important;
}

.btn-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
}

.table td {
    vertical-align: middle;
}

.d-flex.gap-1 {
    gap: 5px !important;
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.table thead tr {
    background-color: #4e73df !important;
}

.table thead th {
    color: white;
    font-weight: 500;
    border: none;
}
</style>

<script src="<?php echo BASE_URL; ?>/assets/js/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/persian-date.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/persian-datepicker.min.js"></script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/persian-datepicker.min.css">

<script>
$(document).ready(function() {
    $('.persian-date').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        observer: true,
        initialValue: false
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
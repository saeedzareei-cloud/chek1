<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

$db = Database::getInstance()->getConnection();
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$request_id) {
    setMessage('شناسه درخواست نامعتبر است.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
    exit;
}

// دریافت اطلاعات درخواست
$stmt = $db->prepare("
    SELECT r.*, 
           c.full_name as customer_name, 
           c.national_code, 
           c.father_name, 
           c.mobile, 
           c.address,
           u1.full_name as registrar_name,
           u2.full_name as verifier_name
    FROM requests r
    JOIN customers c ON r.customer_id = c.id
    LEFT JOIN users u1 ON r.registrar_user_id = u1.id
    LEFT JOIN users u2 ON r.verifier_user_id = u2.id
    WHERE r.id = ?
");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    setMessage('درخواست یافت نشد.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/index.php';</script>";
    exit;
}

// دریافت اقلام خریداری شده
$items = $db->query("
    SELECT ri.*, 
           s.name as section_name, 
           t.tomb_number,
           t.row_position as tomb_position,
           t.facade
    FROM request_items ri
    LEFT JOIN sections s ON ri.section_id = s.id
    LEFT JOIN tombs t ON ri.tomb_id = t.id
    WHERE ri.request_id = $request_id
");

// دریافت چک‌ها با تمام فیلدها
$stmt = $db->prepare("SELECT * FROM checks WHERE request_id = ? ORDER BY due_date");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$checks = $stmt->get_result();

// نگه‌داری لیست چک‌ها برای نمایش
$checks_list = [];
$checks_total = 0;

if ($checks && $checks->num_rows > 0) {
    while ($ch = $checks->fetch_assoc()) {
        $checks_list[] = $ch;
        $checks_total += (float)($ch['amount'] ?? 0);
    }
}

// محاسبه مجموع مبالغ
$total_amount = 0;
$total_file_fees = 0;
$total_stone_fees = 0;

// نگه‌داری لیست اقلام برای نمایش
$items_list = [];

if ($items && $items->num_rows > 0) {
    while ($item = $items->fetch_assoc()) {
        $items_list[] = $item;
        
        // بررسی نام فیلدها بر اساس ساختار جدول request_items
        $price = (float)($item['price'] ?? $item['base_price'] ?? 0);
        $file_fee = (float)($item['file_creation_fee'] ?? $item['file_fee'] ?? 0);
        $stone_fee = (float)($item['stone_reservation_fee'] ?? $item['stone_fee'] ?? 0);
        
        $total_amount += $price;
        $total_file_fees += $file_fee;
        $total_stone_fees += $stone_fee;
    }
    $items->data_seek(0);
}

// محاسبه جمع کل
$grand_total = $total_amount + $total_file_fees + $total_stone_fees;

// بررسی دسترسی برای ادامه ثبت
$can_continue = false;
$continue_message = '';

if ($request['status'] == 'در حال تکمیل' && $request['registrar_user_id'] == $_SESSION['user_id']) {
    $can_continue = true;
} else {
    if ($request['status'] != 'در حال تکمیل') {
        $continue_message = 'وضعیت درخواست "' . $request['status'] . '" است و قابل ویرایش نیست.';
    } elseif ($request['registrar_user_id'] != $_SESSION['user_id']) {
        $continue_message = 'شما ثبت‌کننده این درخواست نیستید.';
    }
}

$page_title = 'مشاهده جزئیات درخواست';
$header_icon = 'eye';

include '../../includes/header.php';
?>

<!-- نمایش پیام خطا اگر وجود داشته باشد -->
<?php if (!empty($continue_message)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $continue_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
    </div>
<?php endif; ?>

<!-- وضعیت درخواست -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-info-circle"></i> 
                    اطلاعات کلی درخواست
                </span>
                <div>
                    <a href="<?php echo BASE_URL; ?>/modules/requests/index.php" class="btn btn-light btn-sm me-2">
                        <i class="bi bi-arrow-right"></i> بازگشت به لیست
                    </a>
                    
                    <!-- دکمه ادامه ثبت -->
                    <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=2&id=<?php echo $request_id; ?>" 
                       class="btn btn-warning btn-sm <?php echo !$can_continue ? 'disabled' : ''; ?>"
                       <?php if (!$can_continue): ?> 
                           onclick="return false;" 
                           style="opacity: 0.5; cursor: not-allowed;"
                           title="<?php echo $continue_message; ?>"
                       <?php endif; ?>>
                        <i class="bi bi-pencil"></i> ادامه ثبت
                    </a>
                    
                    <?php if ($request['status'] == 'تایید شده'): ?>
                        <a href="<?php echo BASE_URL; ?>/modules/contracts/print.php?id=<?php echo $request_id; ?>" class="btn btn-success btn-sm" target="_blank">
                            <i class="bi bi-printer"></i> چاپ قرارداد
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>شماره درخواست:</strong>
                        <p class="text-primary fs-5"><?php echo htmlspecialchars($request['request_number']); ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>تاریخ درخواست:</strong>
                        <p><?php echo jdate('Y/m/d', strtotime($request['request_date'])); ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>وضعیت:</strong>
                        <p>
                            <?php
                            $badge_class = [
                                'در حال تکمیل' => 'secondary',
                                'ارجاع برای امضا' => 'warning',
                                'منتظر تایید' => 'warning',
                                'تایید شده' => 'success',
                                'رد شده' => 'danger'
                            ][$request['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?> fs-6">
                                <?php echo $request['status']; ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <strong>تاریخ ثبت:</strong>
                        <p><?php echo jdate('Y/m/d H:i', strtotime($request['created_at'])); ?></p>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <strong>ثبت کننده:</strong>
                        <p><?php echo htmlspecialchars($request['registrar_name'] ?: '-'); ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>شناسه ثبت کننده:</strong>
                        <p><?php echo $request['registrar_user_id']; ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>شناسه کاربر فعلی:</strong>
                        <p><?php echo $_SESSION['user_id']; ?></p>
                    </div>
                </div>
                <?php if ($request['verified_at']): ?>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>تاریخ تایید/رد:</strong>
                        <p><?php echo jdate('Y/m/d H:i', strtotime($request['verified_at'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- اطلاعات مشتری -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-person-vcard"></i> اطلاعات مشتری
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>نام مشتری:</strong>
                        <p><?php echo htmlspecialchars($request['customer_name'] ?? ''); ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>کد ملی:</strong>
                        <p><?php echo htmlspecialchars($request['national_code'] ?? ''); ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>نام پدر:</strong>
                        <p><?php echo htmlspecialchars($request['father_name'] ?? ''); ?></p>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <strong>موبایل:</strong>
                        <p><?php echo htmlspecialchars($request['mobile'] ?? ''); ?></p>
                    </div>
                    <div class="col-md-8">
                        <strong>آدرس:</strong>
                        <p><?php echo nl2br(htmlspecialchars($request['address'] ?? '')); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- خلاصه مالی -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-cash-coin"></i> خلاصه مالی
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>جمع پایه:</strong>
                        <p><?php echo number_format($total_amount); ?> ریال</p>
                    </div>
                    <div class="col-md-3">
                        <strong>هزینه پرونده:</strong>
                        <p><?php echo number_format($total_file_fees); ?> ریال</p>
                    </div>
                    <div class="col-md-3">
                        <strong>هزینه سنگ:</strong>
                        <p><?php echo number_format($total_stone_fees); ?> ریال</p>
                    </div>
                    <div class="col-md-3">
                        <strong>جمع کل:</strong>
                        <p class="text-primary fw-bold"><?php echo number_format($grand_total); ?> ریال</p>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-3">
                        <strong>درصد نقدی:</strong>
                        <p><?php echo htmlspecialchars($request['cash_percent'] ?? $request['cash_percentage'] ?? '0'); ?>%</p>
                    </div>
                    <div class="col-md-3">
                        <strong>مبلغ نقدی:</strong>
                        <p><?php echo isset($request['cash_amount']) ? number_format((float)$request['cash_amount']) . ' ریال' : '0 ریال'; ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>مانده:</strong>
                        <p><?php echo isset($request['remaining_amount']) ? number_format((float)$request['remaining_amount']) . ' ریال' : '0 ریال'; ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong>جمع چک‌ها:</strong>
                        <p><?php echo number_format($checks_total); ?> ریال</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- اقلام خریداری شده -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-bag-check"></i> اقلام خریداری شده
            </div>
            <div class="card-body p-0">
                <?php if (!empty($items_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>قطعه</th>
                                    <th>شماره قبر</th>
                                    <th>ردیف/جایگاه</th>
                                    <th>نما</th>
                                    <th>قیمت پایه</th>
                                    <th>هزینه پرونده</th>
                                    <th>هزینه سنگ</th>
                                    <th>جمع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_list as $i => $it): 
                                    // بررسی نام‌های مختلف فیلدها
                                    $base = (float)($it['price'] ?? $it['base_price'] ?? 0);
                                    $ff = (float)($it['file_creation_fee'] ?? $it['file_fee'] ?? 0);
                                    $sf = (float)($it['stone_reservation_fee'] ?? $it['stone_fee'] ?? 0);
                                    $sum = $base + $ff + $sf;
                                ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($it['section_name'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($it['tomb_number'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($it['tomb_position'] ?? $it['row_position'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($it['facade'] ?? '---'); ?></td>
                                        <td><?php echo number_format($base); ?> ریال</td>
                                        <td><?php echo number_format($ff); ?> ریال</td>
                                        <td><?php echo number_format($sf); ?> ریال</td>
                                        <td class="fw-bold"><?php echo number_format($sum); ?> ریال</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="5" class="text-start">جمع کل:</th>
                                    <th><?php echo number_format($total_amount); ?> ریال</th>
                                    <th><?php echo number_format($total_file_fees); ?> ریال</th>
                                    <th><?php echo number_format($total_stone_fees); ?> ریال</th>
                                    <th><?php echo number_format($grand_total); ?> ریال</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-muted">هیچ قلمی برای این درخواست ثبت نشده است.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- چک‌ها -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning">
                <i class="bi bi-receipt"></i> چک‌ها
            </div>
            <div class="card-body p-0">
                <?php if (!empty($checks_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>شماره چک</th>
                                    <th>بانک</th>
                                    <th>مبلغ (ریال)</th>
                                    <th>تاریخ سررسید</th>
                                    <th>نام صادرکننده</th>
                                    <th>کد ملی</th>
                                    <th>موبایل</th>
                                    <th>نام پدر</th>
                                    <th>دریافت شده</th>
                                    <th>تاریخ دریافت</th>
                                    <th>توضیحات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks_list as $i => $ch): 
                                    // نمایش تاریخ سررسید (اولویت با شمسی)
                                    $due_date_display = '---';
                                    if (!empty($ch['due_date_shamsi'])) {
                                        $due_date_display = htmlspecialchars($ch['due_date_shamsi']);
                                    } elseif (!empty($ch['due_date'])) {
                                        $timestamp = strtotime($ch['due_date']);
                                        if ($timestamp !== false) {
                                            $due_date_display = jdate('Y/m/d', $timestamp);
                                        } else {
                                            $due_date_display = htmlspecialchars($ch['due_date']);
                                        }
                                    }
                                    
                                    // تاریخ دریافت
                                    $received_date_display = '---';
                                    if (!empty($ch['received_date'])) {
                                        $timestamp = strtotime($ch['received_date']);
                                        if ($timestamp !== false) {
                                            $received_date_display = jdate('Y/m/d', $timestamp);
                                        } else {
                                            $received_date_display = htmlspecialchars($ch['received_date']);
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($ch['check_number'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($ch['bank_name'] ?? '---'); ?></td>
                                        <td class="fw-bold"><?php echo number_format((float)($ch['amount'] ?? 0)); ?></td>
                                        <td><?php echo $due_date_display; ?></td>
                                        <td><?php echo htmlspecialchars($ch['drawer_full_name'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($ch['drawer_national_code'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($ch['drawer_mobile'] ?? '---'); ?></td>
                                        <td><?php echo htmlspecialchars($ch['drawer_father_name'] ?? '---'); ?></td>
                                        <td>
                                            <?php if (!empty($ch['is_received'])): ?>
                                                <span class="badge bg-success">دریافت شده</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">دریافت نشده</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $received_date_display; ?></td>
                                        <td><?php echo htmlspecialchars($ch['description'] ?? '---'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th colspan="3" class="text-start">جمع کل چک‌ها:</th>
                                    <th colspan="9"><?php echo number_format($checks_total); ?> ریال</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-muted">چکی برای این درخواست ثبت نشده است.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- دکمه‌های پایانی -->
<div class="row">
    <div class="col-12 text-center">
        <a href="<?php echo BASE_URL; ?>/modules/requests/index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت به لیست
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/requests/new.php?step=2&id=<?php echo $request_id; ?>" 
           class="btn btn-warning <?php echo !$can_continue ? 'disabled' : ''; ?>"
           <?php if (!$can_continue): ?> 
               onclick="return false;" 
               style="opacity: 0.5; cursor: not-allowed;"
               title="<?php echo $continue_message; ?>"
           <?php endif; ?>>
            <i class="bi bi-pencil"></i> ادامه ثبت
        </a>
        
        <?php if ($request['status'] == 'تایید شده'): ?>
            <a href="<?php echo BASE_URL; ?>/modules/contracts/print.php?id=<?php echo $request_id; ?>" class="btn btn-success" target="_blank">
                <i class="bi bi-printer"></i> چاپ قرارداد
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
.table td {
    vertical-align: middle;
}
.table th {
    vertical-align: middle;
}
.badge {
    padding: 5px 10px;
    border-radius: 20px;
}
.card-header {
    font-weight: 600;
}
.btn.disabled {
    pointer-events: none;
    opacity: 0.5;
}
tfoot {
    font-weight: bold;
}
</style>

<?php include '../../includes/footer.php'; ?>
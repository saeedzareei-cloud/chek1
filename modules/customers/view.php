<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance()->getConnection();
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customer_id <= 0) {
    setMessage('شناسه مشتری نامعتبر است.', 'danger');
    redirect('/modules/customers/index.php');
}

// دریافت اطلاعات مشتری
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    setMessage('مشتری یافت نشد.', 'danger');
    redirect('/modules/customers/index.php');
}

// درخواست‌های مشتری
$stmt = $db->prepare("
    SELECT r.id, r.request_number, r.request_date, r.total_amount, r.status
    FROM requests r
    WHERE r.customer_id = ?
    ORDER BY r.id DESC
");
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$requests = $stmt->get_result();

$page_title = 'مشاهده مشتری';
$header_icon = 'person';

include '../../includes/header.php';
?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-person"></i>
            اطلاعات مشتری
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-light btn-sm" href="<?php echo BASE_URL; ?>/modules/customers/index.php">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
            <a class="btn btn-warning btn-sm" href="<?php echo BASE_URL; ?>/modules/customers/edit.php?id=<?php echo (int)$customer['id']; ?>">
                <i class="bi bi-pencil"></i> ویرایش
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light">
                    <div class="text-muted small">نام و نام خانوادگی</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($customer['full_name'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light">
                    <div class="text-muted small">کد ملی</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($customer['national_code'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light">
                    <div class="text-muted small">شماره همراه</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($customer['mobile'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light">
                    <div class="text-muted small">نام پدر</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($customer['father_name'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="p-3 border rounded bg-light h-100">
                    <div class="text-muted small">آدرس</div>
                    <div class="fw-bold"><?php echo nl2br(htmlspecialchars($customer['address'] ?? '-')); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-success text-white">
        <i class="bi bi-list-check"></i> درخواست‌های این مشتری
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>شماره درخواست</th>
                        <th style="width:140px;">تاریخ</th>
                        <th style="width:160px;">مبلغ کل</th>
                        <th style="width:140px;">وضعیت</th>
                        <th style="width:120px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($requests && $requests->num_rows > 0): ?>
                    <?php $i=1; while($r = $requests->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['request_number']); ?></strong></td>
                            <td><?php echo function_exists('jdate') ? jdate('Y/m/d', strtotime($r['request_date'])) : htmlspecialchars($r['request_date']); ?></td>
                            <td class="text-start fw-bold"><?php echo function_exists('formatMoney') ? formatMoney($r['total_amount'] ?? 0) : number_format($r['total_amount'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($r['status']); ?></td>
                            <td>
                                <a class="btn btn-sm btn-info text-white" href="<?php echo BASE_URL; ?>/modules/requests/view.php?id=<?php echo (int)$r['id']; ?>" title="مشاهده">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (($r['status'] ?? '') === 'تایید شده'): ?>
                                    <a class="btn btn-sm btn-secondary" target="_blank" href="<?php echo BASE_URL; ?>/modules/contracts/print.php?id=<?php echo (int)$r['id']; ?>" title="چاپ قرارداد">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted p-4">درخواستی برای این مشتری ثبت نشده است.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

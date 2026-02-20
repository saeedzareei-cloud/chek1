<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

// فقط کاربران مجاز (مدیر سیستم، جانشین مدیر، یا کسانی که به گزارش‌ها دسترسی دارند)
if (!Auth::hasPermission('reports') && $_SESSION['access_level'] !== 'مدیر سیستم' && $_SESSION['access_level'] !== 'جانشین مدیر') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    redirect('/index.php');
}

$db = Database::getInstance()->getConnection();

// اطمینان از وجود جدول بانک‌ها
$db->query("CREATE TABLE IF NOT EXISTS banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

$check_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($check_id <= 0) {
    setMessage('شناسه چک نامعتبر است.', 'danger');
    redirect('/modules/reports/index.php?type=checks');
}

$stmt = $db->prepare("SELECT c.*, r.request_number, cu.full_name as customer_name
                      FROM checks c
                      JOIN requests r ON c.request_id = r.id
                      JOIN customers cu ON r.customer_id = cu.id
                      WHERE c.id = ?");
$stmt->bind_param('i', $check_id);
$stmt->execute();
$check = $stmt->get_result()->fetch_assoc();

if (!$check) {
    setMessage('چک یافت نشد.', 'danger');
    redirect('/modules/reports/index.php?type=checks');
}

// لیست بانک‌ها
$banks = [];
$bank_q = $db->query("SELECT name FROM banks WHERE is_active = 1 ORDER BY name");
if ($bank_q) {
    while ($b = $bank_q->fetch_assoc()) {
        $banks[] = $b['name'];
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_number = trim($_POST['check_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $due_date_j = trim($_POST['due_date'] ?? '');
    $amount = str_replace(',', '', $_POST['amount'] ?? '');
    $drawer_national_code = trim($_POST['drawer_national_code'] ?? '');
    $drawer_full_name = trim($_POST['drawer_full_name'] ?? '');
    $drawer_father_name = trim($_POST['drawer_father_name'] ?? '');
    $drawer_mobile = trim($_POST['drawer_mobile'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_received = (int)($_POST['is_received'] ?? 0);
    $received_date_j = trim($_POST['received_date'] ?? '');

    // تبدیل تاریخ شمسی به میلادی اگر با / باشد
    $due_date = '';
    if (!empty($due_date_j) && strpos($due_date_j, '/') !== false) {
        $p = explode('/', $due_date_j);
        if (count($p) === 3) {
            $g = jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
            $due_date = $g[0] . '-' . str_pad($g[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g[2], 2, '0', STR_PAD_LEFT);
        }
    } else {
        $due_date = $due_date_j;
    }

    if (empty($check_number) || empty($due_date) || empty($amount)) {
        $error = 'شماره چک، تاریخ سررسید و مبلغ ضروری هستند.';
    } else {
        $stmt = $db->prepare("UPDATE checks
                              SET check_number = ?, bank_name = ?, due_date = ?, original_due_date = ?, amount = ?,
                                  drawer_national_code = ?, drawer_full_name = ?, drawer_father_name = ?, drawer_mobile = ?, description = ?, is_received = ?, received_date = ?
                              WHERE id = ?");
        // original_due_date: اگر خالی بود همان due_date
        $orig = !empty($check['original_due_date']) ? $check['original_due_date'] : $due_date;
        $stmt->bind_param('ssssssssssisi', $check_number, $bank_name, $due_date, $orig, $amount,
                          $drawer_national_code, $drawer_full_name, $drawer_father_name, $drawer_mobile, $description, $is_received, $received_date, $check_id);
        if ($stmt->execute()) {
            setMessage('چک با موفقیت بروزرسانی شد.', 'success');
            redirect('/modules/reports/index.php?type=checks');
        } else {
            $error = 'خطا در بروزرسانی چک.';
        }
    }
}

$page_title = 'ویرایش چک';
$header_icon = 'pencil-square';
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square"></i> ویرایش چک</span>
        <a href="<?php echo BASE_URL; ?>/modules/reports/index.php?type=checks" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="alert alert-secondary">
            <strong>مشتری:</strong> <?php echo htmlspecialchars($check['customer_name']); ?> |
            <strong>شماره درخواست:</strong> <?php echo htmlspecialchars($check['request_number']); ?>
        </div>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">شماره چک *</label>
                    <input type="text" name="check_number" class="form-control" value="<?php echo htmlspecialchars($check['check_number']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">بانک</label>
                    <select name="bank_name" class="form-select">
                        <option value="">-- انتخاب بانک --</option>
                        <?php foreach ($banks as $bn): ?>
                            <option value="<?php echo htmlspecialchars($bn); ?>" <?php echo ($bn === $check['bank_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bn); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="<?php echo htmlspecialchars($check['bank_name']); ?>" <?php echo (!empty($check['bank_name']) && !in_array($check['bank_name'], $banks, true)) ? 'selected' : ''; ?>>
                            <?php echo !empty($check['bank_name']) ? htmlspecialchars($check['bank_name']) : 'سایر'; ?>
                        </option>
                    </select>
                    <small class="text-muted">اگر بانک در لیست نبود، از تنظیمات → بانک‌ها اضافه کنید.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاریخ سررسید (شمسی یا میلادی) *</label>
                    <input type="text" name="due_date" class="form-control" value="<?php echo jdate('Y/m/d', strtotime($check['due_date'])); ?>" required>
                    <small class="text-muted">فرمت پیشنهادی: 1404/01/15</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label">مبلغ (ریال) *</label>
                    <input type="text" name="amount" class="form-control" value="<?php echo number_format((float)$check['amount']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">کد ملی صادرکننده</label>
                    <input type="text" name="drawer_national_code" class="form-control" value="<?php echo htmlspecialchars($check['drawer_national_code']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">وضعیت وصول</label>
                    <select name="is_received" class="form-select" id="is_received">
                        <option value="0" <?php echo ((int)$check['is_received'] === 0) ? 'selected' : ''; ?>>وصول نشده</option>
                        <option value="1" <?php echo ((int)$check['is_received'] === 1) ? 'selected' : ''; ?>>وصول شده</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاریخ وصول (شمسی)</label>
                    <input type="text" name="received_date" class="form-control" placeholder="مثال: 1404/12/01"
                           value="<?php echo !empty($check['received_date']) ? jdate('Y/m/d', strtotime($check['received_date'])) : ''; ?>">
                </div>


                <div class="col-md-4">
                    <label class="form-label">نام صادرکننده</label>
                    <input type="text" name="drawer_full_name" class="form-control" value="<?php echo htmlspecialchars($check['drawer_full_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">نام پدر صادرکننده</label>
                    <input type="text" name="drawer_father_name" class="form-control" value="<?php echo htmlspecialchars($check['drawer_father_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">موبایل صادرکننده</label>
                    <input type="text" name="drawer_mobile" class="form-control" value="<?php echo htmlspecialchars($check['drawer_mobile']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">توضیحات</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($check['description']); ?></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-success" type="submit"><i class="bi bi-save"></i> ذخیره</button>
                <a class="btn btn-danger" href="<?php echo BASE_URL; ?>/modules/checks/delete.php?id=<?php echo (int)$check_id; ?>"
                   onclick="return confirm('آیا از حذف این چک مطمئن هستید؟');">
                   <i class="bi bi-trash"></i> حذف
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

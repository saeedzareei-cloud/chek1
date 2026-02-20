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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $national_code = trim($_POST['national_code'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($full_name === '') $errors[] = 'نام و نام خانوادگی الزامی است.';
    if (!preg_match('/^\d{10}$/', $national_code)) $errors[] = 'کد ملی باید ۱۰ رقم باشد.';
    if (!preg_match('/^\d{11}$/', $mobile)) $errors[] = 'شماره همراه باید ۱۱ رقم باشد.';

    // جلوگیری از تکرار کدملی برای مشتری دیگر
    $chk = $db->prepare("SELECT id FROM customers WHERE national_code = ? AND id <> ? LIMIT 1");
    $chk->bind_param('si', $national_code, $customer_id);
    $chk->execute();
    $dup = $chk->get_result()->fetch_assoc();
    if ($dup) $errors[] = 'این کد ملی قبلاً برای مشتری دیگری ثبت شده است.';

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE customers SET full_name=?, national_code=?, father_name=?, mobile=?, address=? WHERE id=?");
        $stmt->bind_param('sssssi', $full_name, $national_code, $father_name, $mobile, $address, $customer_id);
        if ($stmt->execute()) {
            setMessage('اطلاعات مشتری با موفقیت ذخیره شد.', 'success');
            redirect('/modules/customers/view.php?id=' . $customer_id);
        } else {
            $errors[] = 'خطا در ذخیره اطلاعات: ' . $db->error;
        }
    }
}

$page_title = 'ویرایش مشتری';
$header_icon = 'pencil';

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-warning d-flex justify-content-between align-items-center">
        <div><i class="bi bi-pencil"></i> ویرایش اطلاعات مشتری</div>
        <a class="btn btn-light btn-sm" href="<?php echo BASE_URL; ?>/modules/customers/view.php?id=<?php echo $customer_id; ?>">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">کد ملی</label>
                    <input type="text" class="form-control" name="national_code" maxlength="10" required value="<?php echo htmlspecialchars($customer['national_code'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">نام و نام خانوادگی</label>
                    <input type="text" class="form-control" name="full_name" required value="<?php echo htmlspecialchars($customer['full_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">نام پدر</label>
                    <input type="text" class="form-control" name="father_name" value="<?php echo htmlspecialchars($customer['father_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">شماره همراه</label>
                    <input type="text" class="form-control" name="mobile" maxlength="11" required value="<?php echo htmlspecialchars($customer['mobile'] ?? ''); ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">آدرس</label>
                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check2"></i> ذخیره
                    </button>
                    <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/modules/customers/view.php?id=<?php echo $customer_id; ?>">
                        انصراف
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

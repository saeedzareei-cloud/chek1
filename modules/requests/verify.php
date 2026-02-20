<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود و دسترسی
Auth::requireLogin();
if (!in_array($_SESSION['access_level'], ['مدیر سیستم', 'جانشین مدیر'])) {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/index.php';</script>";
    exit;
}

$db = Database::getInstance()->getConnection();
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// دریافت لیست درخواست‌های نیاز به تایید
// (برای سازگاری با داده‌های قبلی، وضعیت «منتظر تایید» هم در نظر گرفته می‌شود)
$pending_requests = $db->query("
    SELECT r.*, c.full_name as customer_name, c.national_code, u.full_name as registrar_name
    FROM requests r
    JOIN customers c ON r.customer_id = c.id
    LEFT JOIN users u ON r.registrar_user_id = u.id
    WHERE r.status IN ('ارجاع برای امضا', 'منتظر تایید')
    ORDER BY r.id DESC
");

// اگر id مشخص شده، اطلاعات آن درخواست را نمایش بده
if ($request_id > 0) {
    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("
        SELECT r.*, c.*, u.full_name as registrar_name 
        FROM requests r 
        JOIN customers c ON r.customer_id = c.id 
        LEFT JOIN users u ON r.registrar_user_id = u.id 
        WHERE r.id = ? AND r.status IN ('ارجاع برای امضا', 'منتظر تایید')
    ");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request) {
        setMessage('درخواست یافت نشد یا در وضعیت تایید نیست.', 'danger');
        echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/verify.php';</script>";
        exit;
    }

    // دریافت اقلام خریداری شده
    $items = $db->query("
        SELECT ri.*, 
               s.name as section_name, 
               t.tomb_number 
        FROM request_items ri 
        LEFT JOIN sections s ON ri.section_id = s.id 
        LEFT JOIN tombs t ON ri.tomb_id = t.id 
        WHERE ri.request_id = $request_id
    ");

    // دریافت چک‌ها
    $stmt = $db->prepare("SELECT * FROM checks WHERE request_id = ? ORDER BY due_date");
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $checks = $stmt->get_result();

    // محاسبه مجموع
    $total_amount = 0;
    if ($items && $items->num_rows > 0) {
        $items->data_seek(0);
        while ($item = $items->fetch_assoc()) {
            $total_amount += $item['price'] + $item['file_creation_fee'] + $item['stone_reservation_fee'];
        }
        $items->data_seek(0);
    }

    // اعتبارسنجی مبالغ
    $total_paid = ($request['cash_amount'] ?? 0) + ($request['check_amount'] ?? 0);
    $is_valid = abs($total_amount - $total_paid) < 1;

    // تایید یا رد درخواست
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_POST['action'] ?? '';
        $verifier_id = $_SESSION['user_id'];
        $now = date('Y-m-d H:i:s');
        
        if ($action == 'approve' && $is_valid) {
            // دریافت امضا
            $signature = '';
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
                $upload_dir = '../../assets/uploads/signatures/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $signature_name = time() . '_' . $_FILES['signature']['name'];
                if (move_uploaded_file($_FILES['signature']['tmp_name'], $upload_dir . $signature_name)) {
                    $signature = 'assets/uploads/signatures/' . $signature_name;
                    
                    // ذخیره امضا برای کاربر
                    $stmt = $db->prepare("UPDATE users SET signature_image = ? WHERE id = ?");
                    $stmt->bind_param('si', $signature, $verifier_id);
                    $stmt->execute();
                }
            }
            // الزام امضا برای تایید
            if (empty($signature)) {
                $sig_stmt = $db->prepare("SELECT signature_image FROM users WHERE id = ? LIMIT 1");
                $sig_stmt->bind_param('i', $verifier_id);
                $sig_stmt->execute();
                $sig_row = $sig_stmt->get_result()->fetch_assoc();
                if (empty($sig_row['signature_image'])) {
                    setMessage('برای تایید درخواست، ابتدا باید امضای مدیر ثبت شود (فایل امضا را انتخاب کنید).', 'danger');
                    echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/verify.php?id=" . (int)$request_id . "';</script>";
                    exit;
                }
            }


            $stmt = $db->prepare("
                UPDATE requests 
                SET status = 'تایید شده', 
                    verifier_user_id = ?, 
                    verified_at = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $verifier_id, $now, $request_id);
            
            if ($stmt->execute()) {
                setMessage('درخواست با موفقیت تایید شد.', 'success');
                echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/verify.php';</script>";
                exit;
            }
        } elseif ($action == 'reject') {
            $reason = $_POST['reject_reason'] ?? '';
            
            $stmt = $db->prepare("
                UPDATE requests 
                SET status = 'رد شده', 
                    verifier_user_id = ?, 
                    verified_at = ?, 
                    description = CONCAT(IFNULL(description, ''), '\nعلت رد: ', ?) 
                WHERE id = ?
            ");
            $stmt->bind_param('issi', $verifier_id, $now, $reason, $request_id);
            
            if ($stmt->execute()) {
                setMessage('درخواست رد شد.', 'warning');
                echo "<script>window.location.href = '" . BASE_URL . "/modules/requests/verify.php';</script>";
                exit;
            }
        }
    }
}

$page_title = 'تایید درخواست‌ها';
$header_icon = 'check-circle';

include '../../includes/header.php';
?>

<?php if ($request_id > 0 && isset($request)): ?>
    <!-- نمایش جزئیات درخواست برای تایید -->
    
    <?php if (!$is_valid): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>خطا در اعتبارسنجی مبالغ!</strong><br>
            مجموع اقلام (<?php echo formatMoney($total_amount); ?>) 
            با مجموع پرداختی (<?php echo formatMoney($total_paid); ?>) مطابقت ندارد.
            این درخواست قابل تایید نیست.
        </div>
    <?php endif; ?>
    
    <!-- اطلاعات درخواست -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-info-circle"></i> اطلاعات درخواست
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
                    <strong>ثبت کننده:</strong>
                    <p><?php echo htmlspecialchars($request['registrar_name'] ?? '-'); ?></p>
                </div>
                <div class="col-md-3">
                    <strong>وضعیت:</strong>
                    <p><span class="badge bg-warning"><?php echo htmlspecialchars($request['status']); ?></span></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مشخصات خریدار -->
    <div class="card mb-3">
        <div class="card-header bg-success text-white">
            <i class="bi bi-person"></i> مشخصات خریدار
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>کد ملی:</strong>
                    <p><?php echo htmlspecialchars($request['national_code']); ?></p>
                </div>
                <div class="col-md-3">
                    <strong>نام و نام خانوادگی:</strong>
                    <p><?php echo htmlspecialchars($request['full_name']); ?></p>
                </div>
                <div class="col-md-3">
                    <strong>نام پدر:</strong>
                    <p><?php echo htmlspecialchars($request['father_name'] ?: '-'); ?></p>
                </div>
                <div class="col-md-3">
                    <strong>شماره همراه:</strong>
                    <p><?php echo htmlspecialchars($request['mobile']); ?></p>
                </div>
                <?php if (!empty($request['address'])): ?>
                <div class="col-12 mt-2">
                    <strong>آدرس:</strong>
                    <p><?php echo nl2br(htmlspecialchars($request['address'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- اطلاعات قبور/آرامگاه‌ها -->
    <div class="card mb-3">
        <div class="card-header bg-info text-white">
            <i class="bi bi-grid"></i> اطلاعات قبور/آرامگاه‌ها
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead style="background-color: #4e73df; color: white;">
                        <tr>
                            <th style="padding: 12px;">ردیف</th>
                            <th style="padding: 12px;">نوع</th>
                            <th style="padding: 12px;">مشخصات</th>
                            <th style="padding: 12px;">قیمت پایه</th>
                            <th style="padding: 12px;">هزینه تشکیل پرونده</th>
                            <th style="padding: 12px;">هزینه سنگ رزرو</th>
                            <th style="padding: 12px;">جمع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        if ($items && $items->num_rows > 0):
                            while($item = $items->fetch_assoc()): 
                                $item_price = isset($item['price']) ? (int)$item['price'] : 0;
                                $item_file_fee = isset($item['file_creation_fee']) ? (int)$item['file_creation_fee'] : 0;
                                $item_stone_fee = isset($item['stone_reservation_fee']) ? (int)$item['stone_reservation_fee'] : 0;
                                $item_total = $item_price + $item_file_fee + $item_stone_fee;
                        ?>
                        <tr>
                            <td style="padding: 12px;"><?php echo $i++; ?></td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($item['item_type']); ?></td>
                            <td style="padding: 12px;">
                                <?php if ($item['item_type'] == 'قبر'): ?>
                                    قطعه: <?php echo htmlspecialchars($item['section_name']); ?><br>
                                    ردیف: <?php echo htmlspecialchars($item['row_number']); ?><br>
                                    شماره: <?php echo htmlspecialchars($item['grave_number']); ?> - 
                                    <?php echo $item['floor_count']; ?> طبقه
                                <?php else: ?>
                                    آرامگاه: <?php echo htmlspecialchars($item['tomb_number']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-start" style="padding: 12px;"><?php echo formatMoney($item_price); ?></td>
                            <td class="text-start" style="padding: 12px;"><?php echo formatMoney($item_file_fee); ?></td>
                            <td class="text-start" style="padding: 12px;"><?php echo formatMoney($item_stone_fee); ?></td>
                            <td class="text-start" style="padding: 12px;"><strong><?php echo formatMoney($item_total); ?></strong></td>
                        </tr>
                        <?php 
                            endwhile;
                        endif; 
                        ?>
                    </tbody>
                    <tfoot style="background-color: #e9ecef;">
                        <tr>
                            <th colspan="6" class="text-start" style="padding: 12px;">جمع کل:</th>
                            <th class="text-start" style="padding: 12px;"><?php echo formatMoney($total_amount); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- اطلاعات پرداخت -->
    <div class="card mb-3">
        <div class="card-header bg-warning">
            <i class="bi bi-cash"></i> اطلاعات پرداخت
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="alert alert-success">
                        <strong>مبلغ نقدی:</strong><br>
                        <span class="fs-5"><?php echo formatMoney($request['cash_amount'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info">
                        <strong>مبلغ چک‌ها:</strong><br>
                        <span class="fs-5"><?php echo formatMoney($request['check_amount'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-primary">
                        <strong>جمع پرداختی:</strong><br>
                        <span class="fs-5"><?php echo formatMoney($total_paid); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($checks && $checks->num_rows > 0): ?>
                <h6 class="mt-3">جزئیات چک‌ها:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead style="background-color: #4e73df; color: white;">
                            <tr>
                                <th style="padding: 8px;">ردیف</th>
                                <th style="padding: 8px;">شماره چک</th>
                                <th style="padding: 8px;">بانک</th>
                                <th style="padding: 8px;">تاریخ وصول</th>
                                <th style="padding: 8px;">مبلغ</th>
                                <th style="padding: 8px;">صادرکننده</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $j = 1;
                            while($check = $checks->fetch_assoc()): 
                            ?>
                            <tr>
                                <td style="padding: 8px;"><?php echo $j++; ?></td>
                                <td style="padding: 8px;"><?php echo htmlspecialchars($check['check_number']); ?></td>
                                <td style="padding: 8px;"><?php echo htmlspecialchars($check['bank_name'] ?: '-'); ?></td>
                                <td style="padding: 8px;"><?php echo jdate('Y/m/d', strtotime($check['due_date'])); ?></td>
                                <td class="text-start" style="padding: 8px;"><?php echo formatMoney($check['amount']); ?></td>
                                <td style="padding: 8px;">
                                    <?php echo htmlspecialchars($check['drawer_full_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($check['drawer_national_code']); ?></small>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- فرم تایید/رد -->
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-pencil-square"></i> اقدام نهایی
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ثبت امضا</label>
                        <div class="signature-box" onclick="document.getElementById('signature').click();">
                            <input type="file" id="signature" name="signature" accept="image/*" style="display: none;" onchange="previewSignature(this)">
                            <div id="signaturePreview" class="text-center p-3">
                                <i class="bi bi-pencil" style="font-size: 3rem;"></i>
                                <p class="mb-0">برای ثبت امضا کلیک کنید</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="reject_reason" class="form-label">علت رد (در صورت رد)</label>
                        <textarea class="form-control" id="reject_reason" name="reject_reason" rows="5" placeholder="دلیل رد درخواست را وارد کنید..."></textarea>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-center gap-3">
                    <?php if ($is_valid): ?>
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg" onclick="return confirm('آیا از تایید این درخواست اطمینان دارید؟')">
                            <i class="bi bi-check-circle"></i> تایید درخواست
                        </button>
                    <?php endif; ?>
                    
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg" onclick="return confirm('آیا از رد این درخواست اطمینان دارید؟')">
                        <i class="bi bi-x-circle"></i> رد درخواست
                    </button>
                    
                    <a href="<?php echo BASE_URL; ?>/modules/requests/verify.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-right"></i> بازگشت به لیست
                    </a>
                </div>
            </form>
        </div>
    </div>
    
<?php else: ?>
    <!-- لیست درخواست‌های نیاز به تایید -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-list-check"></i> درخواست‌های نیاز به تایید
        </div>
        <div class="card-body">
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #4e73df; color: white;">
                            <tr>
                                <th style="padding: 12px;">#</th>
                                <th style="padding: 12px;">شماره درخواست</th>
                                <th style="padding: 12px;">تاریخ</th>
                                <th style="padding: 12px;">مشتری</th>
                                <th style="padding: 12px;">کد ملی</th>
                                <th style="padding: 12px;">مبلغ کل</th>
                                <th style="padding: 12px;">ثبت کننده</th>
                                <th style="padding: 12px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            while($row = $pending_requests->fetch_assoc()): 
                            ?>
                            <tr>
                                <td style="padding: 12px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['request_number']); ?></td>
                                <td style="padding: 12px;"><?php echo jdate('Y/m/d', strtotime($row['request_date'])); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['national_code']); ?></td>
                                <td class="text-start" style="padding: 12px;"><?php echo formatMoney($row['total_amount'] ?? 0); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['registrar_name']); ?></td>
                                <td style="padding: 12px;">
                                    <a href="<?php echo BASE_URL; ?>/modules/requests/verify.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm" 
                                       style="background: #17a2b8; color: white; width: 70px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="بررسی درخواست"
                                       onmouseover="this.style.background='#138496'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#17a2b8'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-eye me-1"></i> بررسی
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h5 class="mt-3">درخواستی برای تایید وجود ندارد</h5>
                    <p class="text-muted">همه درخواست‌ها بررسی شده‌اند</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.signature-box {
    border: 2px dashed #ccc;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    min-height: 150px;
    cursor: pointer;
    background: #f9f9f9;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.signature-box:hover {
    border-color: #667eea;
    background: #f0f7ff;
    transform: scale(1.02);
}

.signature-box img {
    max-width: 200px;
    max-height: 80px;
    border-radius: 5px;
}

.badge {
    padding: 8px 12px;
    font-size: 0.9rem;
}

.table th {
    font-weight: 600;
}

.btn-lg {
    padding: 12px 30px;
    border-radius: 10px;
}

.table thead tr {
    background-color: #4e73df !important;
}

.table thead th {
    color: white;
    font-weight: 500;
    border: none;
}

@media (max-width: 768px) {
    .d-flex.gap-3 {
        flex-direction: column;
    }
    
    .btn-lg {
        width: 100%;
    }
}
</style>

<script>
function previewSignature(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('signaturePreview').innerHTML = 
                '<img src="' + e.target.result + '" class="img-fluid" style="max-height: 150px;">';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
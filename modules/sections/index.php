<?php

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// بررسی ورود
Auth::requireLogin();

// فقط مدیر سیستم می‌تواند قطعات را مدیریت کند
if ($_SESSION['access_level'] !== 'مدیر سیستم') {
    setMessage('شما دسترسی به این بخش را ندارید.', 'danger');
    echo "<script>window.location.href = '" . BASE_URL . "/index.php';</script>";
    exit;
}

$db = Database::getInstance()->getConnection();

// تابع تبدیل اعداد فارسی و عربی به انگلیسی
function convertPersianToEnglish($string) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    // حذف کاماها
    $string = str_replace(',', '', $string);
    
    // تبدیل اعداد فارسی و عربی به انگلیسی
    $string = str_replace($persian, $english, $string);
    $string = str_replace($arabic, $english, $string);
    
    return $string;
}

// پردازش افزودن/ویرایش قطعه
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $floor_count = (int)($_POST['floor_count'] ?? 1);
        
        // جمع‌آوری قیمت‌های طبقات
        $prices = [];
        $has_error = false;
        
        for ($i = 1; $i <= $floor_count; $i++) {
            $price_key = "price_floor_{$i}";
            $price_value = $_POST[$price_key] ?? '';
            
            if (empty($price_value) && $i == 1) {
                setMessage('قیمت طبقه اول الزامی است.', 'danger');
                $has_error = true;
                break;
            }
            
            if (!empty($price_value)) {
                // تبدیل اعداد فارسی به انگلیسی
                $price_clean = convertPersianToEnglish($price_value);
                
                if (is_numeric($price_clean)) {
                    $prices[$i] = (int)$price_clean;
                } else {
                    setMessage('فرمت قیمت نامعتبر است. لطفاً فقط از اعداد استفاده کنید.', 'danger');
                    $has_error = true;
                    break;
                }
            }
        }
        
        if (!$has_error) {
            // قیمت پایه (طبقه اول)
            $base_price = $prices[1] ?? 0;
            
            // تبدیل آرایه قیمت‌ها به JSON
            $prices_json = json_encode($prices, JSON_UNESCAPED_UNICODE);
            
            if ($id > 0) {
                // ویرایش
                $stmt = $db->prepare("UPDATE sections SET name = ?, description = ?, floor_count = ?, base_price = ?, prices_json = ? WHERE id = ?");
                $stmt->bind_param('ssissi', $name, $description, $floor_count, $base_price, $prices_json, $id);
                
                if ($stmt->execute()) {
                    setMessage('قطعه با موفقیت ویرایش شد.', 'success');
                } else {
                    setMessage('خطا در ویرایش قطعه: ' . $db->error, 'danger');
                }
            } else {
                // افزودن جدید
                $stmt = $db->prepare("INSERT INTO sections (name, description, floor_count, base_price, prices_json) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('ssiss', $name, $description, $floor_count, $base_price, $prices_json);
                
                if ($stmt->execute()) {
                    setMessage('قطعه با موفقیت اضافه شد.', 'success');
                } else {
                    setMessage('خطا در افزودن قطعه: ' . $db->error, 'danger');
                }
            }
            
            echo "<script>window.location.href = '" . BASE_URL . "/modules/sections/index.php';</script>";
            exit;
        }
    }
}

// حذف قطعه
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // بررسی وجود قبر در این قطعه
    $check = $db->prepare("SELECT COUNT(*) as count FROM request_items WHERE section_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        setMessage('این قطعه دارای قبر فروخته شده است و قابل حذف نمی‌باشد.', 'danger');
    } else {
        $stmt = $db->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            setMessage('قطعه با موفقیت حذف شد.', 'success');
        } else {
            setMessage('خطا در حذف قطعه.', 'danger');
        }
    }
    
    echo "<script>window.location.href = '" . BASE_URL . "/modules/sections/index.php';</script>";
    exit;
}

// دریافت لیست قطعات
$sections = $db->query("SELECT * FROM sections ORDER BY id DESC");

// محاسبه مجموع طبقات
$total_floors = 0;
if ($sections && $sections->num_rows > 0) {
    $sections->data_seek(0);
    while($row = $sections->fetch_assoc()) {
        $total_floors += $row['floor_count'];
    }
    $sections->data_seek(0);
}

$page_title = 'مدیریت قطعات';
$header_icon = 'grid';

include '../../includes/header.php';
?>

<!-- نمایش پیام‌ها -->
<?php echo displayMessage(); ?>

<!-- کارت آمار خلاصه -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-grid text-primary me-1"></i>
                        کل قطعات
                    </h6>
                    <h3><?php echo $sections ? $sections->num_rows : 0; ?></h3>
                </div>
                <i class="bi bi-grid-fill fs-1 text-primary"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-layers text-success me-1"></i>
                        مجموع طبقات
                    </h6>
                    <h3><?php echo number_format($total_floors); ?></h3>
                </div>
                <i class="bi bi-layers-fill fs-1 text-success"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-cash-stack text-warning me-1"></i>
                        میانگین قیمت پایه
                    </h6>
                    <h5 class="text-warning">
                        <?php 
                        if ($sections && $sections->num_rows > 0) {
                            $total_price = 0;
                            $sections->data_seek(0);
                            while($row = $sections->fetch_assoc()) {
                                $total_price += $row['base_price'];
                            }
                            $sections->data_seek(0);
                            $avg_price = $total_price / $sections->num_rows;
                            echo number_format($avg_price) . ' ریال';
                        } else {
                            echo '۰ ریال';
                        }
                        ?>
                    </h5>
                </div>
                <i class="bi bi-cash-stack fs-1 text-warning"></i>
            </div>
        </div>
    </div>
</div>

<!-- لیست قطعات -->
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list"></i>
            لیست قطعات
        </span>
        <div>
            <span class="badge bg-light text-dark me-2">
                <i class="bi bi-grid"></i> تعداد: <?php echo $sections ? $sections->num_rows : 0; ?>
            </span>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#sectionModal" onclick="resetForm()">
                <i class="bi bi-plus-circle"></i> قطعه جدید
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($sections && $sections->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="sectionsTable">
                    <thead>
                        <tr style="background-color: #4e73df !important;">
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">#</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">نام قطعه</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">تعداد طبقات</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">قیمت 1 طبقه</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">قیمت 2 طبقه</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">قیمت 3 طبقه</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">قیمت 4 طبقه</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">توضیحات</th>
                            <th style="padding: 12px; color: white !important; background-color: #4e73df !important; font-weight: bold; text-align: center; border: 1px solid #3a5cb8;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        $sections->data_seek(0);
                        while($row = $sections->fetch_assoc()): 
                            $prices = json_decode($row['prices_json'] ?? '{}', true);
                        ?>
                        <tr>
                            <td style="padding: 12px; vertical-align: middle;"><?php echo $i++; ?></td>
                            <td style="padding: 12px; vertical-align: middle;">
                                <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                            </td>
                            <td style="padding: 12px; vertical-align: middle; text-align: center;">
                                <span class="badge bg-info"><?php echo $row['floor_count']; ?> طبقه</span>
                            </td>
                            <td style="padding: 12px; vertical-align: middle; text-align: left;">
                                <?php echo isset($prices[1]) ? number_format($prices[1]) : number_format($row['base_price']); ?> ریال
                            </td>
                            <td style="padding: 12px; vertical-align: middle; text-align: left;">
                                <?php echo isset($prices[2]) ? number_format($prices[2]) : '-'; ?> ریال
                            </td>
                            <td style="padding: 12px; vertical-align: middle; text-align: left;">
                                <?php echo isset($prices[3]) ? number_format($prices[3]) : '-'; ?> ریال
                            </td>
                            <td style="padding: 12px; vertical-align: middle; text-align: left;">
                                <?php echo isset($prices[4]) ? number_format($prices[4]) : '-'; ?> ریال
                            </td>
                            <td style="padding: 12px; vertical-align: middle;">
                                <?php echo htmlspecialchars($row['description'] ?: '-'); ?>
                            </td>
                            <td style="padding: 12px; vertical-align: middle;">
                                <div class="d-flex gap-1 justify-content-center">
                                    <!-- دکمه ویرایش -->
                                    <button type="button" 
                                            class="btn btn-sm" 
                                            style="background: #17a2b8; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                            title="ویرایش"
                                            onclick='editSection(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            onmouseover="this.style.background='#138496'; this.style.transform='translateY(-2px)';"
                                            onmouseout="this.style.background='#17a2b8'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <!-- دکمه حذف -->
                                    <a href="javascript:void(0);" 
                                       onclick="deleteSection(<?php echo $row['id']; ?>)"
                                       class="btn btn-sm" 
                                       style="background: #dc3545; color: white; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;"
                                       title="حذف"
                                       onmouseover="this.style.background='#c82333'; this.style.transform='translateY(-2px)';"
                                       onmouseout="this.style.background='#dc3545'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-grid text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">قطعه‌ای تعریف نشده است</h5>
                <p class="text-muted">برای شروع، اولین قطعه خود را ایجاد کنید</p>
                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#sectionModal" onclick="resetForm()">
                    <i class="bi bi-plus-circle"></i> افزودن قطعه جدید
                </button>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($sections && $sections->num_rows > 0): ?>
    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <i class="bi bi-info-circle"></i>
            تعداد کل قطعات: <?php echo $sections->num_rows; ?>
        </small>
        <small class="text-muted">
            <i class="bi bi-calculator"></i>
            مجموع طبقات: <?php echo $total_floors; ?>
        </small>
    </div>
    <?php endif; ?>
</div>
<!-- مودال افزودن/ویرایش قطعه -->
<div class="modal fade" id="sectionModal" tabindex="-1" aria-labelledby="sectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="sectionForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="section_id" value="">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="sectionModalLabel">
                        <i class="bi bi-plus-circle"></i> <span id="modalTitle">افزودن قطعه جدید</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">نام قطعه <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="floor_count" class="form-label">تعداد طبقات <span class="text-danger">*</span></label>
                            <select class="form-control" id="floor_count" name="floor_count" onchange="updatePriceInputs()" required>
                                <option value="1">1 طبقه</option>
                                <option value="2">2 طبقه</option>
                                <option value="3">3 طبقه</option>
                                <option value="4">4 طبقه</option>
                                <option value="5">5 طبقه</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">قیمت‌گذاری بر اساس طبقات</h6>
                    
                    <div class="row" id="price_inputs_container">
                        <!-- قیمت‌ها با جاوااسکریپت پر می‌شوند -->
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        <small>قیمت طبقه اول الزامی است. سایر طبقات اختیاری می‌باشند.</small>
                    </div>
                    
<!--                    <div class="alert alert-warning mt-2 mb-0"> */
                         <i class="bi bi-exclamation-triangle"></i> */
                        <small>می‌توانید اعداد را به صورت فارسی یا انگلیسی وارد کنید.</small> */
                   </div>
                </div> -->
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> انصراف
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> ذخیره قطعه
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// تابع تبدیل اعداد فارسی به انگلیسی در سمت کلاینت
function convertPersianToEnglish(input) {
    const persianNumbers = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
        '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9'
    };
    const arabicNumbers = {
        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
        '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
    };
    
    let result = input.replace(/,/g, '');
    
    // تبدیل اعداد فارسی
    for (let persian in persianNumbers) {
        result = result.replace(new RegExp(persian, 'g'), persianNumbers[persian]);
    }
    
    // تبدیل اعداد عربی
    for (let arabic in arabicNumbers) {
        result = result.replace(new RegExp(arabic, 'g'), arabicNumbers[arabic]);
    }
    
    return result;
}

function deleteSection(id) {
    if (confirm('آیا از حذف این قطعه اطمینان دارید؟\nاین عمل غیرقابل بازگشت است.')) {
        window.location.href = '<?php echo BASE_URL; ?>/modules/sections/index.php?delete=' + id;
    }
}

// تابع reset فرم
function resetForm() {
    document.getElementById('section_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('floor_count').value = '1';
    document.getElementById('modalTitle').innerText = 'افزودن قطعه جدید';
    updatePriceInputs();
}

// تابع ویرایش قطعه
function editSection(section) {
    console.log('Editing section:', section); // برای دیباگ
    
    // تنظیم مقادیر فرم
    document.getElementById('section_id').value = section.id || '';
    document.getElementById('name').value = section.name || '';
    document.getElementById('description').value = section.description || '';
    document.getElementById('floor_count').value = section.floor_count || '1';
    document.getElementById('modalTitle').innerText = 'ویرایش قطعه';
    
    // دریافت قیمت‌ها
    let prices = {};
    try {
        prices = JSON.parse(section.prices_json || '{}');
    } catch(e) {
        console.log('Error parsing prices_json:', e);
        prices = {};
    }
    
    // اگر prices_json خالی بود، از base_price استفاده کن
    if (Object.keys(prices).length === 0 && section.base_price) {
        prices[1] = section.base_price;
    }
    
    // به‌روزرسانی فیلدهای قیمت
    updatePriceInputs();
    
    // پر کردن قیمت‌ها با تأخیر کمی برای اطمینان از ایجاد فیلدها
    setTimeout(function() {
        for (let i = 1; i <= section.floor_count; i++) {
            let priceInput = document.getElementById('price_floor_' + i);
            if (priceInput) {
                if (prices[i]) {
                    priceInput.value = Number(prices[i]).toLocaleString();
                } else {
                    priceInput.value = '';
                }
            }
        }
    }, 200);
    
    // باز کردن مودال
    var modal = new bootstrap.Modal(document.getElementById('sectionModal'));
    modal.show();
}

// به‌روزرسانی فیلدهای قیمت بر اساس تعداد طبقات
function updatePriceInputs() {
    let floorCount = parseInt(document.getElementById('floor_count').value);
    let container = document.getElementById('price_inputs_container');
    let html = '';
    
    for (let i = 1; i <= floorCount; i++) {
        html += `
            <div class="col-md-6 mb-3">
                <label for="price_floor_${i}" class="form-label">
                   قیمت ${i} طبقه (ریال)
                    ${i == 1 ? '<span class="text-danger">*</span>' : ''}
                </label>
                <div class="input-group">
                    <input type="text" 
                           class="form-control price-format" 
                           id="price_floor_${i}" 
                           name="price_floor_${i}" 
                           ${i == 1 ? 'required' : ''}
                           placeholder="مثال: 150,000,000"
                           oninput="formatPrice(this)">
                    <span class="input-group-text bg-primary text-white">ریال</span>
                </div>
                <small class="form-text text-muted">مثال: 150,000,000 یا ۱۵۰۰۰۰۰۰۰</small>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// فرمت کردن قیمت و تبدیل اعداد فارسی
function formatPrice(input) {
    let value = input.value;
    
    // تبدیل اعداد فارسی و عربی به انگلیسی
    const persianNumbers = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
        '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9'
    };
    const arabicNumbers = {
        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
        '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
    };
    
    // حذف کاماهای فعلی
    let cleanValue = value.replace(/,/g, '');
    
    // تبدیل اعداد فارسی
    for (let persian in persianNumbers) {
        cleanValue = cleanValue.replace(new RegExp(persian, 'g'), persianNumbers[persian]);
    }
    
    // تبدیل اعداد عربی
    for (let arabic in arabicNumbers) {
        cleanValue = cleanValue.replace(new RegExp(arabic, 'g'), arabicNumbers[arabic]);
    }
    
    // اگر مقدار عددی معتبر است، فرمت کن
    if (!isNaN(cleanValue) && cleanValue != '') {
        input.value = Number(cleanValue).toLocaleString();
    } else if (cleanValue == '') {
        input.value = '';
    }
}

// اجرای اولیه
document.addEventListener('DOMContentLoaded', function() {
    updatePriceInputs();
    
    // اطمینان از بارگذاری Bootstrap
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap not loaded!');
    }
});
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
    text-align: center;
}
.table td {
    vertical-align: middle;
    text-align: center;
}
.table td.text-start {
    text-align: left !important;
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
.modal-header {
    border-bottom: none;
}
.modal-footer {
    border-top: none;
}
/* استایل قوی برای هدر جدول */
#sectionsTable thead tr {
    background-color: #4e73df !important;
}

#sectionsTable thead tr th {
    color: white !important;
    background-color: #4e73df !important;
    border: 1px solid #3a5cb8 !important;
    font-weight: bold !important;
    text-align: center !important;
    padding: 12px !important;
}

/* استایل برای سلول‌های بدنه جدول */
#sectionsTable tbody tr td {
    padding: 12px;
    vertical-align: middle;
}

#sectionsTable tbody tr td.text-left {
    text-align: left !important;
}

/* استایل برای دکمه‌های عملیات */
#sectionsTable .btn-sm {
    transition: all 0.2s ease !important;
    margin: 0 2px;
}

#sectionsTable .btn-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
}
</style>

<?php include '../../includes/footer.php'; ?>
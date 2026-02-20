<?php
// بررسی وجود متغیرهای مورد نیاز
if (!isset($current_user)) {
    $current_user = Auth::getCurrentUser();
}

// دریافت تنظیمات لوگو از پایگاه داده
if (!isset($db)) {
    $db = Database::getInstance()->getConnection();
}

// دریافت لوگوی سایت از تنظیمات
$site_logo = 'assets/uploads/logos/63774974e15fbae41666f25e.png'; // مسیر پیش‌فرض

$logo_query = $db->query("SELECT setting_value FROM settings WHERE setting_key IN ('logo', 'logo_path') ORDER BY id DESC LIMIT 1");
if ($logo_query && $logo_query->num_rows > 0) {
    $logo_row = $logo_query->fetch_assoc();
    $logo_path = $logo_row['setting_value'];
    
    // بررسی وجود فایل
    if (!empty($logo_path) && file_exists(__DIR__ . '/../' . $logo_path)) {
        $site_logo = $logo_path;
    }
}

// تشخیص صفحه فعال
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));
$base_url = BASE_URL;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- آیکون صفحه (Favicon) - از لوگوی سایت استفاده کن -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_url . '/' . $site_logo; ?>">
    
    <title><?php echo $page_title ?? SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 RTL - محلی -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/bootstrap.rtl.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/bootstrap-icons.css">
    
    <!-- فونت وزیر -->
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('<?php echo $base_url; ?>/assets/fonts/Vazir.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        
        * {
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link i {
            margin-left: 10px;
            width: 24px;
            text-align: center;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(-5px);
        }
        
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.25);
            font-weight: bold;
            border-right: 3px solid white;
        }
        
        .main-content {
            margin-right: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .date-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .user-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }

        /* استایل برای لوگو */
        .sidebar-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin: 0 auto 10px;
            background: white;
            padding: 5px;
            object-fit: contain;
            display: block;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .sidebar-logo:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }
    </style>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/theme-modern.css?v=1">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <!-- لوگوی سایت از تنظیمات -->
            <img src="<?php echo $base_url . '/' . $site_logo; ?>" 
                 alt="لوگوی سایت" 
                 class="sidebar-logo"
                 onerror="this.onerror=null; this.src='<?php echo $base_url; ?>/assets/img/logo-placeholder.png'; this.classList.add('error');">
            <h5><?php echo SITE_NAME; ?></h5>
            <hr style="border-color: rgba(255,255,255,0.2);">
        </div>
        
        <nav class="nav flex-column">
            <?php if (Auth::hasPermission('dashboard')): ?>
            <a class="nav-link <?php echo ($current_page == 'index.php' && $current_module == '') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/index.php">
                <i class="bi bi-speedometer2"></i> داشبورد
            </a>
            <?php endif; ?>
            
                        <?php if (Auth::hasPermission('users')): ?>
            <a class="nav-link <?php echo ($current_module == 'users') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/users/index.php">
                <i class="bi bi-people"></i> مدیریت کاربران
            </a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('requests_new')): ?>
            <a class="nav-link <?php echo ($current_module == 'requests' && $current_page == 'new.php') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/requests/new.php">
                <i class="bi bi-plus-circle"></i> ثبت درخواست جدید
            </a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('requests_list')): ?>
            <a class="nav-link <?php echo ($current_module == 'requests' && $current_page == 'index.php') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/requests/index.php">
                <i class="bi bi-list-check"></i> لیست درخواست‌ها
            </a>
            <?php endif; ?>
            
                        <?php if (Auth::hasPermission('requests_verify')): ?>
            <a class="nav-link <?php echo ($current_module == 'requests' && $current_page == 'verify.php') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/requests/verify.php">
                <i class="bi bi-check-circle"></i> تایید درخواست‌ها
            </a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('sections')): ?>
            <a class="nav-link <?php echo ($current_module == 'sections') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/sections/index.php">
                <i class="bi bi-grid"></i> مدیریت قطعات
            </a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('tombs')): ?>
            <a class="nav-link <?php echo ($current_module == 'tombs') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/tombs/index.php">
                <i class="bi bi-building"></i> مدیریت آرامگاه‌ها
            </a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('customers')): ?>
            <a class="nav-link <?php echo ($current_module == 'customers') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/customers/index.php">
                <i class="bi bi-person-badge"></i> مشتریان
            </a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('reports')): ?>
            <a class="nav-link <?php echo ($current_module == 'reports') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/reports/index.php">
                <i class="bi bi-file-text"></i> گزارش‌ها
            </a>
            <?php endif; ?>
            
                        <?php if (Auth::hasPermission('settings')): ?>
            <a class="nav-link <?php echo ($current_module == 'settings') ? 'active' : ''; ?>" 
               href="<?php echo $base_url; ?>/modules/settings/index.php">
                <i class="bi bi-gear"></i> تنظیمات
            </a>
            <?php endif; ?>
            
            <hr style="border-color: rgba(255,255,255,0.2);">
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h5 class="mb-0">
                    <?php echo $page_title ?? 'داشبورد'; ?>
                </h5>
                <span class="user-badge ms-3">
                    <i class="bi bi-person-circle"></i> 
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'کاربر'); ?>
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- تاریخ -->
                <div class="date-display">
                    <i class="bi bi-calendar"></i>
                    <?php 
                    if (function_exists('jdate')) {
                        echo jdate('l') . '، ' . jdate('Y/m/d');
                    } else {
                        echo date('Y/m/d');
                    }
                    ?>
                </div>
                <!-- آیکون خروج از سایت با جهت چپ -->
                <a href="<?php echo $base_url; ?>/logout.php" 
                   class="btn btn-danger" 
                   style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0;"
                   title="خروج از سیستم"
                   onclick="return confirm('آیا از خروج از سیستم اطمینان دارید؟');">
                    <i class="bi bi-box-arrow-in-left" style="font-size: 18px;"></i>
                </a>
            </div>
        </div>
        
        <?php echo displayMessage(); ?>
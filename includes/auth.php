<?php
require_once __DIR__ . '/db.php';

class Auth {
    
    public static function login($username, $password) {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, password, full_name, access_level, signature_image FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // در مرحله توسعه، رمز عبور پیش‌فرض admin123
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['access_level'] = $user['access_level'];
                $_SESSION['signature'] = $user['signature_image'];
                $_SESSION['logged_in'] = true;
                
                // ثبت لاگ ورود
                self::logActivity($user['id'], 'ورود به سیستم');
                
                return true;
            }
        }
        
        return false;
    }
    
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            self::logActivity($_SESSION['user_id'], 'خروج از سیستم');
        }
        
        session_destroy();
        return true;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
    
    public static function hasPermission($menu_name) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        // مدیر سیستم به همه چیز دسترسی دارد
        if ($_SESSION['access_level'] == 'مدیر سیستم') {
            return true;
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT can_access FROM menu_permissions WHERE user_id = ? AND menu_name = ?");
        $stmt->bind_param("is", $_SESSION['user_id'], $menu_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $perm = $result->fetch_assoc();
            return $perm['can_access'] == 1;
        }
        
        return false;
    }

    /**
     * تعیین صفحه فرود کاربر پس از ورود بر اساس دسترسی‌های ثبت‌شده در menu_permissions
     * - مدیر سیستم: داشبورد
     * - سایرین: اولین صفحه مجاز مطابق اولویت منوها
     */
    public static function getLandingUrl(): string {
        if (!self::isLoggedIn()) {
            return BASE_URL . '/login.php';
        }

        // مدیر سیستم به داشبورد هدایت شود
        if (($_SESSION['access_level'] ?? '') === 'مدیر سیستم') {
            return BASE_URL . '/index.php';
        }

        // نگاشت نام منو به مسیر
        $menuToUrl = [
            'dashboard'       => '/index.php',
            'requests_new'    => '/modules/requests/new.php',
            'requests_list'   => '/modules/requests/index.php',
            'requests_verify' => '/modules/requests/verify.php',
            'customers'       => '/modules/customers/index.php',
            'tombs'           => '/modules/tombs/index.php',
            'sections'        => '/modules/sections/index.php',
            'users'           => '/modules/users/index.php',
            'reports'         => '/modules/reports/index.php',
            'settings'        => '/modules/settings/index.php',
        ];

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT menu_name FROM menu_permissions WHERE user_id = ? AND can_access = 1");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();

        $allowed = [];
        while ($row = $res->fetch_assoc()) {
            $allowed[$row['menu_name']] = true;
        }

        foreach ($menuToUrl as $menu => $url) {
            if (!empty($allowed[$menu])) {
                return BASE_URL . $url;
            }
        }

        // اگر هیچ دسترسی‌ای ندارد
        return BASE_URL . '/logout.php';
    }
    
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'access_level' => $_SESSION['access_level'],
            'signature' => $_SESSION['signature'] ?? null
        ];
    }
    
    private static function logActivity($user_id, $action) {
        // تابع ثبت لاگ - در آینده تکمیل می‌شود
    }
}

// تابع کمکی برای بررسی دسترسی
function checkPermission($menu) {
    if (!Auth::hasPermission($menu)) {
        header('HTTP/1.0 403 Forbidden');
        echo 'شما دسترسی به این بخش را ندارید.';
        exit;
    }
}
?>
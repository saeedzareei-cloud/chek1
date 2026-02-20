<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// استفاده از BASE_URL تعریف شده در config.php
$base_url = BASE_URL;

// مسیر مستقیم لوگو
$logo_display_path = $base_url . '/assets/uploads/logos/logo.png';

// بررسی وجود فایل
$full_logo_path = __DIR__ . '/assets/uploads/logos/logo.png';
$logo_exists = file_exists($full_logo_path);

// مدت زمان اعتبار نشست (30 دقیقه)
$timeout_duration = 1800;

// اگر کاربر قبلاً وارد شده، مستقیم بره داشبورد
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        session_start();
        $error = 'نشست شما منقضی شده است. لطفاً مجدداً وارد شوید.';
    } else {
        $_SESSION['last_activity'] = time();
        header('Location: ' . Auth::getLandingUrl());
        exit;
    }
}

$error = $error ?? '';

// پردازش فرم ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($username !== '' && $password !== '') {
        session_regenerate_id(true);

        if (Auth::login($username, $password)) {
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            if ($remember) {
                setcookie('remembered_user', $username, time() + (86400 * 30), "/");
            } else {
                setcookie('remembered_user', '', time() - 3600, "/");
            }

            header('Location: ' . Auth::getLandingUrl());
            exit;
        } else {
            $error = '❌ نام کاربری یا رمز عبور اشتباه است!';
        }
    } else {
        $error = '❌ لطفاً نام کاربری و رمز عبور را وارد کنید!';
    }
}

// بررسی کوکی
$remembered_user = $_COOKIE['remembered_user'] ?? '';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @font-face {
            font-family: 'Vazir';
            src: url('assets/fonts/Vazir.woff2') format('woff2'),
                 url('assets/fonts/Vazir.woff') format('woff');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
        }

        /* انیمیشن ذرات پس‌زمینه */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.5;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 950px; /* کاهش از 1100px به 950px */
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px; /* کاهش از 30px به 25px */
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25); /* کاهش سایه */
            animation: slideUp 0.8s ease-out;
            position: relative;
            z-index: 2;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* پنل سمت راست - فرم ورود */
        .login-panel {
            flex: 1;
            padding: 45px 40px; /* کاهش از 60px 50px به 45px 40px */
            display: flex;
            flex-direction: column;
            justify-content: center;
            animation: fadeInRight 0.8s ease-out 0.2s both;
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .welcome-text {
            margin-bottom: 25px; /* کاهش از 30px به 25px */
        }

        .welcome-text h2 {
            font-size: 26px; /* کاهش از 32px به 26px */
            color: #2d3748;
            margin-bottom: 5px; /* کاهش از 8px به 5px */
            font-weight: 700;
            animation: fadeInUp 0.6s ease-out;
        }

        .welcome-text p {
            color: #718096;
            font-size: 13px; /* کاهش از 14px به 13px */
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-form {
            width: 100%;
            max-width: 300px; /* کاهش از 350px به 300px */
        }

        .input-group {
            margin-bottom: 18px; /* کاهش از 20px به 18px */
            animation: fadeInUp 0.6s ease-out;
        }

        .input-group:nth-child(1) { animation-delay: 0.2s; }
        .input-group:nth-child(2) { animation-delay: 0.3s; }

        .input-group label {
            display: block;
            margin-bottom: 5px; /* کاهش از 8px به 5px */
            color: #4a5568;
            font-weight: 500;
            font-size: 13px; /* کاهش از 14px به 13px */
        }

        .input-with-icon {
            position: relative;
            transition: all 0.3s ease;
        }

        .input-with-icon:hover {
            transform: translateY(-2px);
        }

        .input-with-icon i {
            position: absolute;
            right: 12px; /* کاهش از 15px به 12px */
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px; /* کاهش از 18px به 16px */
            transition: all 0.3s ease;
        }

        .input-with-icon input {
            width: 100%;
            padding: 12px 40px 12px 15px; /* کاهش از 14px به 12px */
            border: 2px solid #e2e8f0;
            border-radius: 10px; /* کاهش از 12px به 10px */
            font-size: 13px; /* کاهش از 14px به 13px */
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
            transition: all 0.3s ease;
            background: #f7fafc;
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: scale(1.01); /* کاهش از 1.02 به 1.01 */
        }

        .input-with-icon input:focus + i {
            color: #667eea;
        }

        .show-password {
            position: absolute;
            left: 12px; /* کاهش از 15px به 12px */
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            font-size: 16px; /* کاهش از 18px به 16px */
            transition: all 0.3s ease;
        }

        .show-password:hover {
            color: #667eea;
            transform: translateY(-50%) scale(1.05); /* کاهش از 1.1 به 1.05 */
        }

        .remember-container {
            display: flex;
            justify-content: flex-end;
            margin: 12px 0 20px; /* کاهش از 15px 0 25px */
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 6px; /* کاهش از 8px به 6px */
            cursor: pointer;
            color: #4a5568;
            font-size: 13px; /* کاهش از 14px به 13px */
            transition: all 0.3s ease;
        }

        .remember-label:hover {
            color: #667eea;
        }

        .remember-label input[type="checkbox"] {
            width: 14px; /* کاهش از 16px به 14px */
            height: 14px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 12px; /* کاهش از 14px به 12px */
            border: none;
            border-radius: 10px; /* کاهش از 12px به 10px */
            font-size: 14px; /* کاهش از 16px به 14px */
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px; /* کاهش از 8px به 6px */
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 250px; /* کاهش از 300px به 250px */
            height: 250px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); /* کاهش سایه */
        }

        .btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 10px 12px; /* کاهش از 12px 15px */
            border-radius: 10px; /* کاهش از 12px به 10px */
            margin-bottom: 18px; /* کاهش از 20px به 18px */
            font-size: 13px; /* کاهش از 14px به 13px */
            border: 1px solid #feb2b2;
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* پنل سمت چپ - اطلاعات */
        .info-panel {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 45px 40px; /* کاهش از 60px 50px */
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            animation: fadeInLeft 0.8s ease-out 0.1s both;
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .info-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px; /* کاهش از 40px به 30px */
            position: relative;
            z-index: 2;
            animation: scaleIn 0.8s ease-out 0.2s both;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .logo-circle {
            width: 120px; /* کاهش از 170px به 120px */
            height: 120px;
            margin: 0 auto 15px; /* کاهش از 20px به 15px */
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.5); /* کاهش از 4px به 3px */
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15); /* کاهش سایه */
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .logo-circle:hover {
            transform: scale(1.03) rotate(3deg); /* کاهش افکت hover */
            border-color: white;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); /* کاهش از 15px به 10px */
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
            }
        }

        .logo-circle img {
            width: 80%; /* کاهش از 85% به 80% */
            height: 80%;
            object-fit: contain;
            padding: 5px;
        }

        .org-main-title {
            font-size: 18px; /* کاهش از 22px به 18px */
            font-weight: bold;
            margin-bottom: 3px; /* کاهش از 5px به 3px */
            line-height: 1.5;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        .org-sub-title {
            font-size: 16px; /* کاهش از 20px به 16px */
            margin-bottom: 8px; /* کاهش از 10px به 8px */
            opacity: 0.95;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }

        .org-description {
            font-size: 12px; /* کاهش از 14px به 12px */
            opacity: 0.9;
            margin-top: 5px;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }

        .features {
            margin-top: 30px; /* کاهش از 40px به 30px */
            position: relative;
            z-index: 2;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 18px; /* کاهش از 25px به 18px */
            animation: slideInRight 0.6s ease-out;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            transform: translateX(-3px); /* کاهش از -5px به -3px */
        }

        .feature-item:nth-child(1) { animation-delay: 0.6s; }
        .feature-item:nth-child(2) { animation-delay: 0.7s; }
        .feature-item:nth-child(3) { animation-delay: 0.8s; }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .feature-icon {
            width: 38px; /* کاهش از 45px به 38px */
            height: 38px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px; /* کاهش از 12px به 10px */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 12px; /* کاهش از 15px به 12px */
            font-size: 18px; /* کاهش از 20px به 18px */
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .feature-item:hover .feature-icon {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05) rotate(3deg); /* کاهش افکت */
        }

        .feature-text h3 {
            font-size: 14px; /* کاهش از 15px به 14px */
            margin-bottom: 2px; /* کاهش از 3px به 2px */
            font-weight: 600;
        }

        .feature-text p {
            font-size: 11px; /* کاهش از 12px به 11px */
            opacity: 0.8;
        }

        .copyright {
            position: absolute;
            bottom: 15px; /* کاهش از 20px به 15px */
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px; /* کاهش از 11px به 10px */
            opacity: 0.6;
            z-index: 2;
            animation: fadeIn 1s ease-out 1s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 0.6; }
        }

        @media (max-width: 900px) {
            .login-container {
                flex-direction: column-reverse;
                max-width: 400px; /* کاهش از 450px به 400px */
            }
            
            .login-panel, .info-panel {
                padding: 30px 25px; /* کاهش برای موبایل */
            }

            .logo-circle {
                width: 100px; /* کاهش برای موبایل */
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>

    <div class="login-container">
        <!-- پنل سمت راست - فرم ورود -->
        <div class="login-panel">
            <div class="welcome-text">
                <h2>ورود به سیستم</h2>
                <p>لطفاً اطلاعات حساب کاربری خود را وارد کنید</p>
            </div>

            <?php if($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="">
                <div class="input-group">
                    <label for="username">نام کاربری</label>
                    <div class="input-with-icon">
                        <input type="text" id="username" name="username" 
                               placeholder="نام کاربری خود را وارد کنید" 
                               value="<?php echo htmlspecialchars($remembered_user); ?>"
                               required>
                        <i class="bi bi-person-circle"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">رمز عبور</label>
                    <div class="input-with-icon">
                        <input type="password" id="password" name="password" 
                               placeholder="رمز عبور خود را وارد کنید" 
                               required>
                        <i class="bi bi-key"></i>
                        <button type="button" class="show-password" onclick="togglePassword()">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="remember-container">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" <?php echo $remembered_user ? 'checked' : ''; ?>>
                        <span>مرا به خاطر بسپار</span>
                    </label>
                </div>

                <button type="submit" class="btn">
                    <i class="bi bi-box-arrow-in-left"></i>
                    <span>ورود به سیستم</span>
                </button>
            </form>
        </div>

        <!-- پنل سمت چپ - اطلاعات -->
        <div class="info-panel">
            <div class="logo-section">
                <div class="logo-circle">
                    <?php if ($logo_exists): ?>
                        <img src="<?php echo $logo_display_path; ?>" alt="لوگوی سازمان">
                    <?php endif; ?>
                </div>
                <div class="org-main-title">سازمان مدیریت آرامستان‌ها</div>
                <div class="org-sub-title">شهرداری کرج</div>
                <div class="org-description">سیستم یکپارچه فروش اقساطی قبور</div>
            </div>

            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="feature-text">
                        <h3>امنیت پیشرفته</h3>
                        <p>حفاظت کامل از اطلاعات با سیستم رمزنگاری</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <div class="feature-text">
                        <h3>گزارشات حرفه‌ای</h3>
                        <p>آنالیز پیشرفته و گزارش‌های مالی دقیق</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-phone"></i>
                    </div>
                    <div class="feature-text">
                        <h3>دسترسی همه‌جا</h3>
                        <p>قابل استفاده در تمام دستگاه‌های موبایل و دسکتاپ</p>
                    </div>
                </div>
            </div>

            <div class="copyright">
                © ۱۴۰۴ - کلیه حقوق برای شهرداری کرج محفوظ است
            </div>
        </div>
    </div>

    <script>
        // ایجاد ذرات متحرک
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 15; // کاهش تعداد ذرات از 20 به 15
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 8 + 3; // کاهش سایز ذرات
                const left = Math.random() * 100;
                const top = Math.random() * 100;
                const duration = Math.random() * 15 + 10;
                const delay = Math.random() * 5;
                
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = left + '%';
                particle.style.top = top + '%';
                particle.style.animationDuration = duration + 's';
                particle.style.animationDelay = delay + 's';
                
                container.appendChild(particle);
            }
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.show-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // ایجاد ذرات هنگام بارگذاری صفحه
        createParticles();
        
        // فوکوس روی فیلد نام کاربری
        document.getElementById('username').focus();
    </script>
</body>
</html>
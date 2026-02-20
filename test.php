<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تست نمایش لوگو</title>
</head>
<body>
    <h1>تست نمایش لوگو</h1>
    
    <h3>BASE_URL: <?php echo BASE_URL; ?></h3>
    
    <h3>مسیرهای مختلف:</h3>
    <ul>
        <li>مسیر 1: <img src="assets/uploads/logos/logo.png" style="max-width: 200px; border: 1px solid red;"></li>
        <li>مسیر 2: <img src="/chek/assets/uploads/logos/logo.png" style="max-width: 200px; border: 1px solid blue;"></li>
        <li>مسیر 3: <img src="<?php echo BASE_URL; ?>/assets/uploads/logos/logo.png" style="max-width: 200px; border: 1px solid green;"></li>
    </ul>
    
    <h3>بررسی وجود فایل:</h3>
    <?php
    $paths = [
        'assets/uploads/logos/logo.png',
        __DIR__ . '/assets/uploads/logos/logo.png',
        __DIR__ . '/../assets/uploads/logos/logo.png'
    ];
    
    foreach ($paths as $path) {
        $exists = file_exists($path) ? '✅ وجود دارد' : '❌ وجود ندارد';
        echo "<p>مسیر: $path - $exists</p>";
    }
    ?>
</body>
</html>
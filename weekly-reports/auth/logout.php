<?php
/**
 * تسجيل الخروج
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// تسجيل العملية قبل إنهاء الجلسة
if (isLoggedIn()) {
    logActivity('logout', 'تسجيل خروج');
}

// إنهاء الجلسة
$_SESSION = [];

// حذف كوكيز الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// التوجيه لصفحة الدخول
redirect('pages/login.php?logout=1');

<?php
/**
 * معالجة تسجيل الدخول
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/login.php');
}

// التحقق من توكن CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    redirect('pages/login.php?error=csrf');
}

// الحصول على البيانات المدخلة
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// التحقق من صحة البيانات
if (empty($email) || empty($password)) {
    redirect('pages/login.php?error=empty');
}

// البحث عن المستخدم
$user = dbFetchOne("SELECT * FROM users WHERE email = ?", [$email]);

if (!$user || !password_verify($password, $user['password'])) {
    // تسجيل محاولة الدخول الفاشلة
    redirect('pages/login.php?error=invalid');
}

// تسجيل الدخول بنجاح
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_department_id'] = $user['department_id'];
$_SESSION['last_activity'] = time();

// تجديد معرف الجلسة للحماية من Session Fixation
session_regenerate_id(true);

// تذكرني - تمديد صلاحية الكوكيز
if ($remember) {
    $lifetime = 30 * 24 * 60 * 60; // 30 يوم
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        session_id(),
        time() + $lifetime,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// تسجيل العملية
logActivity('login', 'تسجيل دخول ناجح');

// التوجيه للوحة التحكم
redirect('pages/dashboard.php');

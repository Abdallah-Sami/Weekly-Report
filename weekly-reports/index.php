<?php
/**
 * الصفحة الرئيسية - إعادة التوجيه
 */
require_once __DIR__ . '/includes/functions.php';
startSecureSession();

if (isLoggedIn()) {
    redirect('pages/dashboard.php');
} else {
    redirect('pages/login.php');
}

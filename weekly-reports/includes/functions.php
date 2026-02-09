<?php
/**
 * الوظائف المشتركة
 * نظام إدارة التقارير الأسبوعية
 */

require_once __DIR__ . '/../config/database.php';

// =====================================================
// وظائف الجلسة والمصادقة
// =====================================================

/**
 * بدء الجلسة بإعدادات آمنة
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
    // التحقق من انتهاء الجلسة
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        redirect('pages/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * التحقق من تسجيل الدخول
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * إلزام تسجيل الدخول - توجيه للتسجيل إذا لم يكن مسجلاً
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('pages/login.php');
    }
}

/**
 * التحقق من الدور
 */
function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['user_role'], $roles)) {
        redirect('pages/dashboard.php?error=unauthorized');
    }
}

/**
 * التحقق من كون المستخدم مديراً عاماً
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * التحقق من كون المستخدم مدير قسم
 */
function isManager() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager';
}

/**
 * التحقق من كون المستخدم موظفاً
 */
function isEmployee() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee';
}

// =====================================================
// وظائف CSRF
// =====================================================

/**
 * إنشاء توكن CSRF
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من توكن CSRF
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * حقل CSRF مخفي للنماذج
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

// =====================================================
// وظائف الأمان
// =====================================================

/**
 * تنظيف المدخلات من XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * تنظيف المدخلات
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// =====================================================
// وظائف قاعدة البيانات
// =====================================================

/**
 * الحصول على اتصال قاعدة البيانات
 */
function db() {
    return Database::getInstance()->getConnection();
}

/**
 * تنفيذ استعلام مع معاملات
 */
function dbQuery($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * جلب صف واحد
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * جلب جميع الصفوف
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

// =====================================================
// وظائف المستخدمين
// =====================================================

/**
 * الحصول على بيانات المستخدم الحالي
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return dbFetchOne("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?", [$_SESSION['user_id']]);
}

/**
 * الحصول على اسم الدور بالعربية
 */
function getRoleName($role) {
    $roles = [
        'admin' => 'المدير العام',
        'manager' => 'مدير قسم',
        'employee' => 'موظف'
    ];
    return $roles[$role] ?? $role;
}

/**
 * الحصول على اسم حالة التقرير بالعربية
 */
function getStatusName($status) {
    $statuses = [
        'draft' => 'مسودة',
        'published' => 'منشور'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * الحصول على لون حالة التقرير
 */
function getStatusBadge($status) {
    $badges = [
        'draft' => 'warning',
        'published' => 'success'
    ];
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . getStatusName($status) . '</span>';
}

// =====================================================
// وظائف الإشعارات
// =====================================================

/**
 * الحصول على عدد الإشعارات غير المقروءة
 */
function getUnreadNotificationsCount() {
    if (!isLoggedIn()) return 0;
    $result = dbFetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    return $result['count'] ?? 0;
}

/**
 * الحصول على آخر الإشعارات
 */
function getLatestNotifications($limit = 5) {
    if (!isLoggedIn()) return [];
    return dbFetchAll(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$_SESSION['user_id'], $limit]
    );
}

/**
 * إنشاء إشعار جديد
 */
function createNotification($userId, $title, $message, $type = 'reminder') {
    dbQuery(
        "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)",
        [$userId, $title, $message, $type]
    );
}

/**
 * إنشاء إشعار لجميع المستخدمين أو مجموعة معينة
 */
function createBulkNotification($title, $message, $type, $role = null) {
    $sql = "SELECT id FROM users";
    $params = [];
    if ($role) {
        $sql .= " WHERE role = ?";
        $params[] = $role;
    }
    $users = dbFetchAll($sql, $params);
    foreach ($users as $user) {
        createNotification($user['id'], $title, $message, $type);
    }
}

// =====================================================
// وظائف سجل العمليات
// =====================================================

/**
 * تسجيل عملية في السجل
 */
function logActivity($action, $description = '') {
    if (!isLoggedIn()) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    dbQuery(
        "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)",
        [$_SESSION['user_id'], $action, $description, $ip]
    );
}

// =====================================================
// وظائف رفع الملفات
// =====================================================

/**
 * التحقق من صحة الملف المرفوع
 */
function validateUploadedFile($file) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'حدث خطأ أثناء رفع الملف';
        return $errors;
    }

    // التحقق من حجم الملف
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'حجم الملف يتجاوز الحد المسموح (5 ميجابايت)';
    }

    // التحقق من نوع الملف
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $errors[] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', ALLOWED_EXTENSIONS);
    }

    return $errors;
}

/**
 * رفع ملف وحفظه
 */
function uploadFile($file, $destination) {
    $errors = validateUploadedFile($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // إنشاء اسم فريد للملف
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newName = uniqid('file_', true) . '.' . $ext;
    $fullPath = UPLOAD_PATH . $destination . '/' . $newName;

    // إنشاء المجلد إذا لم يكن موجوداً
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [
            'success' => true,
            'file_name' => $file['name'],
            'file_path' => $destination . '/' . $newName,
            'file_size' => $file['size']
        ];
    }

    return ['success' => false, 'errors' => ['فشل في حفظ الملف']];
}

// =====================================================
// وظائف التواريخ
// =====================================================

/**
 * تنسيق التاريخ بالصيغة العربية
 */
function formatDate($date) {
    if (empty($date)) return '';
    return date('Y/m/d', strtotime($date));
}

/**
 * تنسيق التاريخ والوقت بالصيغة العربية
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    return date('Y/m/d h:i A', strtotime($datetime));
}

/**
 * الحصول على بداية ونهاية الأسبوع الحالي
 */
function getCurrentWeekRange() {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end = date('Y-m-d', strtotime('friday this week'));
    return ['start' => $start, 'end' => $end];
}

/**
 * التحقق من أن التاريخ في الأسبوع الحالي
 */
function isCurrentWeek($weekStart, $weekEnd) {
    $current = getCurrentWeekRange();
    return $weekStart === $current['start'] && $weekEnd === $current['end'];
}

/**
 * التحقق من إمكانية إنشاء تقرير
 * الجميع يمكنهم إنشاء تقارير
 */
function canCreateReport() {
    return isLoggedIn();
}

/**
 * التحقق من إمكانية تعديل تقرير
 * الموظف: لا يعدل
 * المدير: يعدل تقارير قسمه الحالية والسابقة
 * الرئيس: يعدل كل شيء
 */
function canEditReport($report) {
    if (isAdmin()) return true;
    if (isManager() && $report['created_by'] == $_SESSION['user_id']) return true;
    return false;
}

/**
 * التحقق من إمكانية حذف تقرير
 * الرئيس فقط
 */
function canDeleteReport() {
    return isAdmin();
}

// =====================================================
// وظائف التنقل
// =====================================================

/**
 * إعادة التوجيه
 */
function redirect($url) {
    if (strpos($url, 'http') !== 0) {
        $url = SITE_URL . '/' . ltrim($url, '/');
    }
    header("Location: $url");
    exit;
}

/**
 * الحصول على الصفحة الحالية
 */
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

/**
 * التحقق من الصفحة النشطة
 */
function isActivePage($page) {
    return getCurrentPage() === $page ? 'active' : '';
}

// =====================================================
// وظائف مساعدة
// =====================================================

/**
 * تنسيق حجم الملف
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' ميجابايت';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' كيلوبايت';
    }
    return $bytes . ' بايت';
}

/**
 * الحصول على أيقونة نوع الملف
 */
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fa-file-pdf text-danger',
        'docx' => 'fa-file-word text-primary',
        'doc' => 'fa-file-word text-primary',
        'xlsx' => 'fa-file-excel text-success',
        'xls' => 'fa-file-excel text-success',
        'jpg' => 'fa-file-image text-warning',
        'jpeg' => 'fa-file-image text-warning',
        'png' => 'fa-file-image text-warning',
    ];
    return $icons[$ext] ?? 'fa-file text-secondary';
}

/**
 * الحصول على جميع الأقسام
 */
function getDepartments() {
    return dbFetchAll("SELECT * FROM departments ORDER BY id");
}

/**
 * عرض رسالة نجاح أو خطأ
 */
function showAlert() {
    $html = '';
    if (isset($_SESSION['success'])) {
        $html .= '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        $html .= '<i class="fas fa-check-circle me-2"></i>' . e($_SESSION['success']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $html .= '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        $html .= '<i class="fas fa-exclamation-circle me-2"></i>' . e($_SESSION['error']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $html .= '</div>';
        unset($_SESSION['error']);
    }
    return $html;
}

/**
 * تعيين رسالة نجاح
 */
function setSuccess($message) {
    $_SESSION['success'] = $message;
}

/**
 * تعيين رسالة خطأ
 */
function setError($message) {
    $_SESSION['error'] = $message;
}

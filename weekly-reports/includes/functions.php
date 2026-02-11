<?php
/**
 * الوظائف المشتركة
 * نظام إدارة التقارير الأسبوعية
 */

require_once __DIR__ . '/../config/database.php';

// =====================================================
// وظائف الجلسة والمصادقة
// =====================================================

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        redirect('pages/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('pages/login.php');
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['user_role'], $roles)) {
        redirect('pages/dashboard.php?error=unauthorized');
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isDirector() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'director';
}

function isCoordinator() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'coordinator';
}

function isManager() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager';
}

function isEmployee() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee';
}

// =====================================================
// وظائف CSRF
// =====================================================

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

// =====================================================
// وظائف الأمان
// =====================================================

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// =====================================================
// وظائف قاعدة البيانات
// =====================================================

function db() {
    return Database::getInstance()->getConnection();
}

function dbQuery($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

// =====================================================
// وظائف المستخدمين
// =====================================================

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return dbFetchOne("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?", [$_SESSION['user_id']]);
}

function getRoleName($role) {
    $roles = [
        'admin' => 'مدير النظام',
        'director' => 'وكيلة الوكالة',
        'coordinator' => 'المنسق',
        'manager' => 'مدير قسم',
        'employee' => 'موظف'
    ];
    return $roles[$role] ?? $role;
}

function getStatusName($status) {
    $statuses = [
        'open' => 'مفتوح للتعبئة',
        'published' => 'منشور'
    ];
    return $statuses[$status] ?? $status;
}

function getStatusBadge($status) {
    $badges = [
        'open' => 'warning',
        'published' => 'success'
    ];
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . getStatusName($status) . '</span>';
}

// =====================================================
// وظائف الصلاحيات الجديدة
// =====================================================

function canCreateReport() {
    return isCoordinator() || isAdmin();
}

function canFillReport() {
    return (isManager() || isEmployee()) && isset($_SESSION['user_department_id']);
}

function canEditFullReport() {
    return isCoordinator() || isAdmin();
}

function canEditDepartmentSection($report, $departmentId) {
    if (isCoordinator() || isAdmin()) return true;
    if ((isManager() || isEmployee()) && $departmentId == $_SESSION['user_department_id'] && $report['status'] === 'open') return true;
    return false;
}

function canPublishReport() {
    return isCoordinator() || isAdmin();
}

function canDeleteReport() {
    return isAdmin();
}

function canViewReport($report) {
    if ($report['status'] === 'published') return true;
    if ($report['status'] === 'open') {
        if (isCoordinator() || isAdmin()) return true;
        if (isManager() || isEmployee()) return true;
    }
    return false;
}

function canEditReport($report) {
    if (isAdmin() || isCoordinator()) return true;
    if ((isManager() || isEmployee()) && $report['status'] === 'open') return true;
    return false;
}

function getOpenReport() {
    return dbFetchOne("SELECT * FROM reports WHERE status = 'open' ORDER BY created_at DESC LIMIT 1");
}

function isDepartmentFilled($reportId, $departmentId) {
    $detail = dbFetchOne(
        "SELECT id FROM report_details WHERE report_id = ? AND department_id = ? AND achievements IS NOT NULL AND achievements != ''",
        [$reportId, $departmentId]
    );
    return $detail ? true : false;
}

// =====================================================
// وظائف الإشعارات
// =====================================================

function getUnreadNotificationsCount() {
    if (!isLoggedIn()) return 0;
    $result = dbFetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
    return $result['count'] ?? 0;
}

function getLatestNotifications($limit = 5) {
    if (!isLoggedIn()) return [];
    return dbFetchAll(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$_SESSION['user_id'], $limit]
    );
}

function createNotification($userId, $title, $message, $type = 'reminder') {
    dbQuery(
        "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)",
        [$userId, $title, $message, $type]
    );
}

function createBulkNotification($title, $message, $type, $role = null) {
    $sql = "SELECT id FROM users";
    $params = [];
    if ($role) {
        if (is_array($role)) {
            $placeholders = implode(',', array_fill(0, count($role), '?'));
            $sql .= " WHERE role IN ($placeholders)";
            $params = $role;
        } else {
            $sql .= " WHERE role = ?";
            $params[] = $role;
        }
    }
    $users = dbFetchAll($sql, $params);
    foreach ($users as $user) {
        createNotification($user['id'], $title, $message, $type);
    }
}

function notifyCoordinators($title, $message, $type = 'section_filled') {
    $coordinators = dbFetchAll("SELECT id FROM users WHERE role IN ('coordinator', 'admin')");
    foreach ($coordinators as $user) {
        createNotification($user['id'], $title, $message, $type);
    }
}

function notifyDirector($title, $message, $type = 'report_published') {
    $directors = dbFetchAll("SELECT id FROM users WHERE role = 'director'");
    foreach ($directors as $user) {
        createNotification($user['id'], $title, $message, $type);
    }
}

// =====================================================
// وظائف سجل العمليات
// =====================================================

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

function validateUploadedFile($file) {
    $errors = [];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'حدث خطأ أثناء رفع الملف';
        return $errors;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'حجم الملف يتجاوز الحد المسموح (5 ميجابايت)';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $errors[] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', ALLOWED_EXTENSIONS);
    }
    return $errors;
}

function uploadFile($file, $destination) {
    $errors = validateUploadedFile($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newName = uniqid('file_', true) . '.' . $ext;
    $fullPath = UPLOAD_PATH . $destination . '/' . $newName;
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

function formatDate($date) {
    if (empty($date)) return '';
    return date('Y/m/d', strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    return date('Y/m/d h:i A', strtotime($datetime));
}

function getCurrentWeekRange() {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end = date('Y-m-d', strtotime('friday this week'));
    return ['start' => $start, 'end' => $end];
}

function isCurrentWeek($weekStart, $weekEnd) {
    $current = getCurrentWeekRange();
    return $weekStart === $current['start'] && $weekEnd === $current['end'];
}

// =====================================================
// وظائف التنقل
// =====================================================

function redirect($url) {
    if (strpos($url, 'http') !== 0) {
        $url = SITE_URL . '/' . ltrim($url, '/');
    }
    header("Location: $url");
    exit;
}

function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

function isActivePage($page) {
    return getCurrentPage() === $page ? 'active' : '';
}

// =====================================================
// وظائف مساعدة
// =====================================================

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' ميجابايت';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' كيلوبايت';
    }
    return $bytes . ' بايت';
}

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

function getDepartments() {
    return dbFetchAll("SELECT * FROM departments ORDER BY id");
}

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

function setSuccess($message) {
    $_SESSION['success'] = $message;
}

function setError($message) {
    $_SESSION['error'] = $message;
}

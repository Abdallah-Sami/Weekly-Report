<?php
/**
 * تحديد إشعار كمقروء (AJAX)
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مسجل الدخول']);
    exit;
}

$notifId = (int)($_POST['id'] ?? 0);
$markAll = isset($_POST['all']);

try {
    if ($markAll) {
        dbQuery("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$_SESSION['user_id']]);
    } elseif ($notifId) {
        dbQuery("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$notifId, $_SESSION['user_id']]);
    }

    $unreadCount = getUnreadNotificationsCount();
    echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
}

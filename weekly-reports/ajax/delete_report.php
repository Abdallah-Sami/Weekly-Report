<?php
/**
 * حذف تقرير (AJAX)
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit;
}

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'خطأ في التحقق من الأمان']);
    exit;
}

$reportId = (int)($_POST['id'] ?? 0);
if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'رقم التقرير غير صحيح']);
    exit;
}

try {
    // حذف المرفقات من الخادم
    $attachments = dbFetchAll(
        "SELECT a.* FROM attachments a JOIN report_details rd ON a.report_detail_id = rd.id WHERE rd.report_id = ?",
        [$reportId]
    );
    foreach ($attachments as $att) {
        $filePath = UPLOAD_PATH . $att['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // حذف التقرير (CASCADE يحذف التفاصيل والمرفقات)
    dbQuery("DELETE FROM reports WHERE id = ?", [$reportId]);

    logActivity('delete_report', "حذف التقرير #$reportId");
    echo json_encode(['success' => true, 'message' => 'تم حذف التقرير بنجاح']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء الحذف']);
}

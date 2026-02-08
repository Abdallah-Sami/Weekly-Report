<?php
/**
 * رفع مرفقات (AJAX)
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'لم يتم إرسال ملف']);
    exit;
}

$result = uploadFile($_FILES['file'], 'attachments');

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'file_name' => $result['file_name'],
        'file_path' => $result['file_path'],
        'file_size' => $result['file_size']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => implode(', ', $result['errors'])]);
}

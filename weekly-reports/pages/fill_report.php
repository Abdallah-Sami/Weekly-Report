<?php
/**
 * صفحة تعبئة التقرير - المدير والموظف يعبئون خانة قسمهم
 */
$pageTitle = 'تعبئة التقرير';
require_once __DIR__ . '/../includes/header.php';
requireRole(['manager', 'employee']);

// التحقق من وجود القسم
if (empty($_SESSION['user_department_id'])) {
    setError('لم يتم تحديد قسمك. يرجى التواصل مع مدير النظام.');
    redirect('pages/dashboard.php');
}

$reportId = (int)($_GET['id'] ?? 0);
if (!$reportId) {
    setError('رقم التقرير غير صحيح');
    redirect('pages/dashboard.php');
}

$report = dbFetchOne("SELECT * FROM reports WHERE id = ?", [$reportId]);
if (!$report) {
    setError('التقرير غير موجود');
    redirect('pages/dashboard.php');
}

if ($report['status'] !== 'open') {
    setError('هذا التقرير مغلق ولا يمكن التعبئة');
    redirect('pages/view_report.php?id=' . $reportId);
}

$deptId = $_SESSION['user_department_id'];
$department = dbFetchOne("SELECT * FROM departments WHERE id = ?", [$deptId]);

// جلب التفاصيل الموجودة
$existingDetail = dbFetchOne(
    "SELECT * FROM report_details WHERE report_id = ? AND department_id = ?",
    [$reportId, $deptId]
);

// جلب المرفقات
$existingAttachments = [];
if ($existingDetail) {
    $existingAttachments = dbFetchAll("SELECT * FROM attachments WHERE report_detail_id = ?", [$existingDetail['id']]);
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/fill_report.php?id=' . $reportId);
    }

    $achievements = $_POST['achievements'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (empty(trim($achievements))) {
        setError('يرجى كتابة الإنجازات');
        redirect('pages/fill_report.php?id=' . $reportId);
    }

    try {
        db()->beginTransaction();

        if ($existingDetail) {
            // تحديث
            dbQuery(
                "UPDATE report_details SET achievements = ?, notes = ? WHERE id = ?",
                [$achievements, $notes, $existingDetail['id']]
            );
            $detailId = $existingDetail['id'];
        } else {
            // إضافة
            dbQuery(
                "INSERT INTO report_details (report_id, department_id, achievements, notes) VALUES (?, ?, ?, ?)",
                [$reportId, $deptId, $achievements, $notes]
            );
            $detailId = db()->lastInsertId();
        }

        // حذف المرفقات المحددة
        $deleteAttachments = $_POST['delete_attachments'] ?? [];
        foreach ($deleteAttachments as $attId) {
            $att = dbFetchOne("SELECT * FROM attachments WHERE id = ? AND report_detail_id = ?", [(int)$attId, $detailId]);
            if ($att) {
                $filePath = UPLOAD_PATH . $att['file_path'];
                if (file_exists($filePath)) unlink($filePath);
                dbQuery("DELETE FROM attachments WHERE id = ?", [(int)$attId]);
            }
        }

        // رفع مرفقات جديدة
        if (isset($_FILES['attachments'])) {
            $files = $_FILES['attachments'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    $result = uploadFile($file, 'attachments');
                    if ($result['success']) {
                        dbQuery(
                            "INSERT INTO attachments (report_detail_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?)",
                            [$detailId, $result['file_name'], $result['file_path'], $result['file_size']]
                        );
                    }
                }
            }
        }

        db()->commit();

        // إشعار للمنسق
        notifyCoordinators(
            'تعبئة قسم: ' . $department['name'],
            'قسم ' . $department['name'] . ' عبّأ التقرير الأسبوعي للفترة من ' . formatDate($report['week_start']) . ' إلى ' . formatDate($report['week_end']),
            'section_filled'
        );

        logActivity('fill_report', "تعبئة قسم {$department['name']} في التقرير #$reportId");
        setSuccess('تم حفظ الإنجازات بنجاح');
        redirect('pages/view_report.php?id=' . $reportId);

    } catch (Exception $e) {
        db()->rollBack();
        setError('حدث خطأ: ' . $e->getMessage());
        redirect('pages/fill_report.php?id=' . $reportId);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-edit me-2"></i>تعبئة تقرير الأسبوع</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">تعبئة التقرير</li>
            </ol>
        </nav>
    </div>
    <a href="<?= SITE_URL ?>/pages/view_report.php?id=<?= $reportId ?>" class="btn btn-outline-primary">
        <i class="fas fa-eye me-1"></i>عرض التقرير
    </a>
</div>

<!-- معلومات التقرير -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>فترة التقرير:</strong>
                <?= formatDate($report['week_start']) ?> - <?= formatDate($report['week_end']) ?>
            </div>
            <div class="col-md-4">
                <strong>الحالة:</strong> <?= getStatusBadge($report['status']) ?>
            </div>
            <div class="col-md-4">
                <strong>القسم:</strong>
                <span class="badge bg-primary"><?= e($department['name']) ?></span>
            </div>
        </div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">
                <i class="fas fa-building me-2"></i>
                إنجازات <?= e($department['name']) ?>
            </h6>
        </div>
        <div class="card-body">
            <!-- الإنجازات -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="fas fa-trophy me-1 text-warning"></i>الإنجازات <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" name="achievements" rows="8"
                          placeholder="اكتب إنجازات القسم لهذا الأسبوع..." required><?= e($existingDetail['achievements'] ?? '') ?></textarea>
            </div>

            <!-- الشواهد -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="fas fa-paperclip me-1 text-info"></i>الشواهد والمرفقات
                </label>

                <?php if (!empty($existingAttachments)): ?>
                <div class="mb-3">
                    <label class="form-label small text-muted">المرفقات الحالية:</label>
                    <?php foreach ($existingAttachments as $att): ?>
                    <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
                        <i class="fas <?= getFileIcon($att['file_name']) ?> me-2"></i>
                        <span class="small flex-grow-1"><?= e($att['file_name']) ?></span>
                        <small class="text-muted me-2"><?= formatFileSize($att['file_size']) ?></small>
                        <label class="text-danger small" style="cursor:pointer">
                            <input type="checkbox" name="delete_attachments[]" value="<?= $att['id'] ?>" class="me-1">
                            حذف
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="upload-zone">
                    <input type="file" name="attachments[]" class="form-control" multiple
                           accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
                    <small class="text-muted mt-1 d-block">JPG, PNG, PDF, DOCX, XLSX - حد أقصى 5 ميجابايت لكل ملف</small>
                </div>
            </div>

            <!-- ملاحظات -->
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="fas fa-sticky-note me-1 text-success"></i>ملاحظات
                </label>
                <textarea class="form-control" name="notes" rows="4"
                          placeholder="ملاحظات اختيارية..."><?= e($existingDetail['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <a href="<?= SITE_URL ?>/pages/dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i>إلغاء
        </a>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i>حفظ الإنجازات
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

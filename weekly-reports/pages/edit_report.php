<?php
/**
 * صفحة تعديل التقرير
 */
$pageTitle = 'تعديل التقرير';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'manager']);

$reportId = (int)($_GET['id'] ?? 0);
if (!$reportId) {
    setError('رقم التقرير غير صحيح');
    redirect('pages/reports_archive.php');
}

$report = dbFetchOne("SELECT * FROM reports WHERE id = ?", [$reportId]);
if (!$report) {
    setError('التقرير غير موجود');
    redirect('pages/reports_archive.php');
}

// التحقق من الصلاحيات
if (isManager()) {
    if ($report['created_by'] != $_SESSION['user_id'] || !isCurrentWeek($report['week_start'], $report['week_end'])) {
        setError('ليس لديك صلاحية تعديل هذا التقرير');
        redirect('pages/reports_archive.php');
    }
}

$departments = getDepartments();

// جلب التفاصيل الحالية
$existingDetails = dbFetchAll(
    "SELECT rd.*, d.name as department_name FROM report_details rd JOIN departments d ON rd.department_id = d.id WHERE rd.report_id = ? ORDER BY d.id",
    [$reportId]
);
$detailsMap = [];
foreach ($existingDetails as $d) {
    $detailsMap[$d['department_id']] = $d;
}

// جلب المرفقات
$attachmentsMap = [];
foreach ($existingDetails as $d) {
    $attachmentsMap[$d['department_id']] = dbFetchAll("SELECT * FROM attachments WHERE report_detail_id = ?", [$d['id']]);
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/edit_report.php?id=' . $reportId);
    }

    $weekStart = $_POST['week_start'] ?? '';
    $weekEnd = $_POST['week_end'] ?? '';
    $status = ($_POST['action'] === 'publish') ? 'published' : 'draft';

    try {
        db()->beginTransaction();

        dbQuery(
            "UPDATE reports SET week_start = ?, week_end = ?, status = ?, updated_at = NOW() WHERE id = ?",
            [$weekStart, $weekEnd, $status, $reportId]
        );

        foreach ($departments as $dept) {
            if (isManager() && $dept['id'] != $_SESSION['user_department_id']) continue;

            $achievements = $_POST['achievements_' . $dept['id']] ?? '';
            $notes = $_POST['notes_' . $dept['id']] ?? '';

            if (isset($detailsMap[$dept['id']])) {
                dbQuery(
                    "UPDATE report_details SET achievements = ?, notes = ? WHERE id = ?",
                    [$achievements, $notes, $detailsMap[$dept['id']]['id']]
                );
                $detailId = $detailsMap[$dept['id']]['id'];
            } elseif (!empty($achievements)) {
                dbQuery(
                    "INSERT INTO report_details (report_id, department_id, achievements, notes) VALUES (?, ?, ?, ?)",
                    [$reportId, $dept['id'], $achievements, $notes]
                );
                $detailId = db()->lastInsertId();
            } else {
                continue;
            }

            // حذف المرفقات المحددة
            $deleteAttachments = $_POST['delete_attachments'] ?? [];
            foreach ($deleteAttachments as $attId) {
                $att = dbFetchOne("SELECT * FROM attachments WHERE id = ?", [(int)$attId]);
                if ($att) {
                    $filePath = UPLOAD_PATH . $att['file_path'];
                    if (file_exists($filePath)) unlink($filePath);
                    dbQuery("DELETE FROM attachments WHERE id = ?", [(int)$attId]);
                }
            }

            // رفع مرفقات جديدة
            if (isset($_FILES['attachments_' . $dept['id']])) {
                $files = $_FILES['attachments_' . $dept['id']];
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
        }

        db()->commit();

        createBulkNotification('تحديث تقرير', "تم تحديث التقرير #$reportId", 'report_updated');
        logActivity('update_report', "تعديل التقرير #$reportId");
        setSuccess('تم تحديث التقرير بنجاح');
        redirect('pages/view_report.php?id=' . $reportId);

    } catch (Exception $e) {
        db()->rollBack();
        setError('حدث خطأ: ' . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-edit me-2"></i>تعديل التقرير #<?= $reportId ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">تعديل التقرير</li>
            </ol>
        </nav>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" id="editReportForm">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>معلومات التقرير</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="week_start" class="form-label fw-bold">من تاريخ</label>
                    <input type="date" class="form-control" name="week_start" value="<?= e($report['week_start']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="week_end" class="form-label fw-bold">إلى تاريخ</label>
                    <input type="date" class="form-control" name="week_end" value="<?= e($report['week_end']) ?>" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-primary"></i>إنجازات الأقسام</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-top mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:15%">القسم</th>
                            <th style="width:35%">الإنجازات</th>
                            <th style="width:30%">الشواهد</th>
                            <th style="width:20%">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept):
                            $disabled = (isManager() && $dept['id'] != $_SESSION['user_department_id']) ? 'disabled' : '';
                            $detail = $detailsMap[$dept['id']] ?? null;
                            $atts = $attachmentsMap[$dept['id']] ?? [];
                        ?>
                        <tr>
                            <td class="fw-bold"><?= e($dept['name']) ?></td>
                            <td>
                                <textarea class="form-control" name="achievements_<?= $dept['id'] ?>"
                                          rows="5" <?= $disabled ?>><?= e($detail['achievements'] ?? '') ?></textarea>
                            </td>
                            <td>
                                <?php if (empty($disabled)): ?>
                                <!-- المرفقات الحالية -->
                                <?php foreach ($atts as $att): ?>
                                <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
                                    <i class="fas <?= getFileIcon($att['file_name']) ?> me-2"></i>
                                    <span class="small flex-grow-1"><?= e($att['file_name']) ?></span>
                                    <label class="text-danger small" style="cursor:pointer">
                                        <input type="checkbox" name="delete_attachments[]" value="<?= $att['id'] ?>" class="me-1">
                                        حذف
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <input type="file" name="attachments_<?= $dept['id'] ?>[]" class="form-control mt-2" multiple
                                       accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
                                <small class="text-muted">إضافة مرفقات جديدة</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <textarea class="form-control" name="notes_<?= $dept['id'] ?>"
                                          rows="5" <?= $disabled ?>><?= e($detail['notes'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <a href="<?= SITE_URL ?>/pages/view_report.php?id=<?= $reportId ?>" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i>إلغاء
        </a>
        <div>
            <button type="submit" name="action" value="draft" class="btn btn-warning me-2">
                <i class="fas fa-save me-1"></i>حفظ كمسودة
            </button>
            <button type="submit" name="action" value="publish" class="btn btn-success">
                <i class="fas fa-paper-plane me-1"></i>نشر التقرير
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

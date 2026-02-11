<?php
/**
 * صفحة تعديل التقرير - للمنسق والمدير فقط
 */
$pageTitle = 'تعديل التقرير';
require_once __DIR__ . '/../includes/header.php';
requireRole(['coordinator']);

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

    $weekStart = $_POST['week_start'] ?? $report['week_start'];
    $weekEnd = $_POST['week_end'] ?? $report['week_end'];
    $action = $_POST['action'] ?? 'save';

    // تحديد الحالة
    if ($action === 'publish') {
        $status = 'published';
    } else {
        $status = $report['status']; // يبقى على حالته الحالية (open)
    }

    try {
        db()->beginTransaction();

        dbQuery(
            "UPDATE reports SET week_start = ?, week_end = ?, status = ?, updated_at = NOW() WHERE id = ?",
            [$weekStart, $weekEnd, $status, $reportId]
        );

        // المنسق/المدير يستطيع تعديل جميع الأقسام
        foreach ($departments as $dept) {
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
                    "INSERT INTO report_details (report_id, department_id, achievements, notes, filled_by) VALUES (?, ?, ?, ?, ?)",
                    [$reportId, $dept['id'], $achievements, $notes, $_SESSION['user_id']]
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

        if ($action === 'publish') {
            // إشعار الوكيلة عند النشر
            notifyDirector('تقرير جديد منشور', "تم نشر التقرير #$reportId", 'report_published');
            // إشعار جميع المستخدمين
            createBulkNotification('تقرير منشور', "تم نشر التقرير الأسبوعي #$reportId", 'report_published');
            logActivity('publish_report', "نشر التقرير #$reportId");
            setSuccess('تم نشر التقرير بنجاح');
        } else {
            logActivity('update_report', "تعديل التقرير #$reportId");
            setSuccess('تم تحديث التقرير بنجاح');
        }

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
    <div>
        <?= getStatusBadge($report['status']) ?>
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
                            $detail = $detailsMap[$dept['id']] ?? null;
                            $atts = $attachmentsMap[$dept['id']] ?? [];
                        ?>
                        <tr>
                            <td class="fw-bold">
                                <?= e($dept['name']) ?>
                                <?php if ($detail && !empty($detail['achievements'])): ?>
                                    <br><small class="text-success"><i class="fas fa-check-circle me-1"></i>معبأ</small>
                                <?php else: ?>
                                    <br><small class="text-warning"><i class="fas fa-clock me-1"></i>لم يُعبأ</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <textarea class="form-control" name="achievements_<?= $dept['id'] ?>"
                                          rows="5"><?= e($detail['achievements'] ?? '') ?></textarea>
                            </td>
                            <td>
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
                            </td>
                            <td>
                                <textarea class="form-control" name="notes_<?= $dept['id'] ?>"
                                          rows="5"><?= e($detail['notes'] ?? '') ?></textarea>
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
            <button type="submit" name="action" value="save" class="btn btn-warning me-2">
                <i class="fas fa-save me-1"></i>حفظ التغييرات
            </button>
            <?php if ($report['status'] === 'open'): ?>
            <button type="submit" name="action" value="publish" class="btn btn-success"
                    onclick="return confirm('هل تريد نشر هذا التقرير؟ لن يمكن التعديل عليه بعد النشر.')">
                <i class="fas fa-paper-plane me-1"></i>نشر التقرير
            </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

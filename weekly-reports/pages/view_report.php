<?php
/**
 * صفحة عرض التقرير
 */
$pageTitle = 'عرض التقرير';
require_once __DIR__ . '/../includes/header.php';

$reportId = (int)($_GET['id'] ?? 0);
if (!$reportId) {
    setError('رقم التقرير غير صحيح');
    redirect('pages/reports_archive.php');
}

// جلب بيانات التقرير
$report = dbFetchOne(
    "SELECT r.*, u.name as creator_name FROM reports r JOIN users u ON r.created_by = u.id WHERE r.id = ?",
    [$reportId]
);

if (!$report) {
    setError('التقرير غير موجود');
    redirect('pages/reports_archive.php');
}

// جلب تفاصيل التقرير مع الأقسام
$details = dbFetchAll(
    "SELECT rd.*, d.name as department_name
     FROM report_details rd
     JOIN departments d ON rd.department_id = d.id
     WHERE rd.report_id = ?
     ORDER BY d.id",
    [$reportId]
);

// جلب المرفقات لكل تفصيل
$attachmentsByDetail = [];
foreach ($details as $detail) {
    $attachmentsByDetail[$detail['id']] = dbFetchAll(
        "SELECT * FROM attachments WHERE report_detail_id = ?",
        [$detail['id']]
    );
}

// التحقق من إمكانية التعديل
$canEdit = false;
if (isAdmin()) {
    $canEdit = true;
} elseif (isManager() && $report['created_by'] == $_SESSION['user_id'] && isCurrentWeek($report['week_start'], $report['week_end'])) {
    $canEdit = true;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>عرض التقرير #<?= $reportId ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/reports_archive.php">الأرشيف</a></li>
                <li class="breadcrumb-item active">تقرير #<?= $reportId ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if ($canEdit): ?>
        <a href="<?= SITE_URL ?>/pages/edit_report.php?id=<?= $reportId ?>" class="btn btn-warning me-1">
            <i class="fas fa-edit me-1"></i>تعديل
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="fas fa-print me-1"></i>طباعة
        </button>
        <a href="<?= SITE_URL ?>/pages/reports_archive.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-right me-1"></i>رجوع
        </a>
    </div>
</div>

<!-- معلومات التقرير -->
<div class="card border-0 shadow-sm mb-4" id="printArea">
    <div class="card-header bg-primary text-white text-center py-3">
        <h5 class="mb-1">وكالة البحوث والتنمية الصناعية</h5>
        <p class="mb-0 opacity-75">تقرير الإنجازات الأسبوعي</p>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <strong>فترة التقرير:</strong>
                <?= formatDate($report['week_start']) ?> - <?= formatDate($report['week_end']) ?>
            </div>
            <div class="col-md-4">
                <strong>الحالة:</strong> <?= getStatusBadge($report['status']) ?>
            </div>
            <div class="col-md-4">
                <strong>أنشئ بواسطة:</strong> <?= e($report['creator_name']) ?>
                <br><small class="text-muted"><?= formatDateTime($report['created_at']) ?></small>
            </div>
        </div>

        <!-- جدول الإنجازات -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width:15%">القسم</th>
                        <th style="width:40%">الإنجازات</th>
                        <th style="width:25%">الشواهد</th>
                        <th style="width:20%">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">لا توجد تفاصيل لهذا التقرير</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($details as $detail): ?>
                        <tr>
                            <td class="fw-bold">
                                <i class="fas fa-building text-primary me-1"></i>
                                <?= e($detail['department_name']) ?>
                            </td>
                            <td>
                                <div class="report-content"><?= nl2br(e($detail['achievements'])) ?></div>
                            </td>
                            <td>
                                <?php
                                $attachments = $attachmentsByDetail[$detail['id']] ?? [];
                                if (empty($attachments)):
                                ?>
                                    <span class="text-muted small">لا توجد شواهد</span>
                                <?php else: ?>
                                    <?php foreach ($attachments as $att): ?>
                                    <div class="mb-2">
                                        <a href="<?= SITE_URL ?>/uploads/<?= e($att['file_path']) ?>"
                                           class="text-decoration-none" target="_blank" download>
                                            <i class="fas <?= getFileIcon($att['file_name']) ?> me-1"></i>
                                            <span class="small"><?= e($att['file_name']) ?></span>
                                        </a>
                                        <br><small class="text-muted"><?= formatFileSize($att['file_size']) ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($detail['notes']) ? nl2br(e($detail['notes'])) : '<span class="text-muted small">لا توجد ملاحظات</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

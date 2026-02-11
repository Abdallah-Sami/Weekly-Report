<?php
/**
 * صفحة عرض التقرير
 */
$pageTitle = 'عرض التقرير';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

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

// التحقق من صلاحية العرض
if (!canViewReport($report)) {
    setError('ليس لديك صلاحية عرض هذا التقرير');
    redirect('pages/dashboard.php');
}

// وكيلة الوكالة تشاهد المنشور فقط
if (isDirector() && $report['status'] !== 'published') {
    setError('هذا التقرير لم يُنشر بعد');
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

// جلب جميع الأقسام لعرض حالة التعبئة
$departments = getDepartments();
$filledDepts = [];
foreach ($details as $d) {
    $filledDepts[$d['department_id']] = $d;
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
        <?php if (canFillReport() && $report['status'] === 'open'): ?>
        <a href="<?= SITE_URL ?>/pages/fill_report.php?id=<?= $reportId ?>" class="btn btn-success me-1">
            <i class="fas fa-pen me-1"></i>تعبئة قسمي
        </a>
        <?php endif; ?>
        <?php if (canEditFullReport()): ?>
        <a href="<?= SITE_URL ?>/pages/edit_report.php?id=<?= $reportId ?>" class="btn btn-warning me-1">
            <i class="fas fa-edit me-1"></i>تعديل
        </a>
        <?php if ($report['status'] === 'open'): ?>
        <form method="POST" action="<?= SITE_URL ?>/pages/edit_report.php?id=<?= $reportId ?>" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="publish">
            <input type="hidden" name="week_start" value="<?= e($report['week_start']) ?>">
            <input type="hidden" name="week_end" value="<?= e($report['week_end']) ?>">
            <button type="submit" class="btn btn-primary me-1" onclick="return confirm('هل تريد نشر هذا التقرير؟')">
                <i class="fas fa-paper-plane me-1"></i>نشر
            </button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-secondary me-1">
            <i class="fas fa-print me-1"></i>طباعة
        </button>
        <a href="<?= SITE_URL ?>/pages/reports_archive.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-right me-1"></i>رجوع
        </a>
    </div>
</div>

<?php if ($report['status'] === 'open' && canEditFullReport()): ?>
<!-- حالة تعبئة الأقسام -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-dark">
        <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>حالة تعبئة الأقسام</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($departments as $dept): ?>
            <div class="col-md-3">
                <div class="d-flex align-items-center">
                    <?php if (isset($filledDepts[$dept['id']]) && !empty($filledDepts[$dept['id']]['achievements'])): ?>
                        <i class="fas fa-check-circle text-success me-2 fa-lg"></i>
                    <?php else: ?>
                        <i class="fas fa-clock text-warning me-2 fa-lg"></i>
                    <?php endif; ?>
                    <span><?= e($dept['name']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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
                    <?php
                    // عرض جميع الأقسام (المعبأة وغير المعبأة)
                    foreach ($departments as $dept):
                        $detail = $filledDepts[$dept['id']] ?? null;
                        // للمدير/الموظف في التقرير المفتوح: عرض قسمهم فقط
                        if ($report['status'] === 'open' && (isManager() || isEmployee()) && $dept['id'] != ($_SESSION['user_department_id'] ?? 0)) {
                            continue;
                        }
                    ?>
                    <tr>
                        <td class="fw-bold">
                            <i class="fas fa-building text-primary me-1"></i>
                            <?= e($dept['name']) ?>
                            <?php if (!$detail && $report['status'] === 'open'): ?>
                                <br><small class="text-warning"><i class="fas fa-clock me-1"></i>لم يُعبأ</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($detail && !empty($detail['achievements'])): ?>
                                <div class="report-content"><?= nl2br(e($detail['achievements'])) ?></div>
                            <?php else: ?>
                                <span class="text-muted small">لم يتم إدخال الإنجازات بعد</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $attachments = $detail ? ($attachmentsByDetail[$detail['id']] ?? []) : [];
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
                            <?= ($detail && !empty($detail['notes'])) ? nl2br(e($detail['notes'])) : '<span class="text-muted small">لا توجد ملاحظات</span>' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * صفحة إنشاء تقرير أسبوعي جديد
 * المنسق والأدمن فقط
 */
$pageTitle = 'إنشاء تقرير جديد';
require_once __DIR__ . '/../includes/header.php';
requireRole(['coordinator']);

$weekRange = getCurrentWeekRange();

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/create_report.php');
    }

    $weekStart = $_POST['week_start'] ?? '';
    $weekEnd = $_POST['week_end'] ?? '';

    if (empty($weekStart) || empty($weekEnd)) {
        setError('يرجى تحديد فترة التقرير');
        redirect('pages/create_report.php');
    }

    try {
        dbQuery(
            "INSERT INTO reports (week_start, week_end, status, created_by) VALUES (?, ?, 'open', ?)",
            [$weekStart, $weekEnd, $_SESSION['user_id']]
        );
        $reportId = db()->lastInsertId();

        // إشعار لكل المدراء والموظفين
        createBulkNotification(
            'تقرير أسبوعي جديد جاهز للتعبئة',
            'تقرير أسبوعي جديد جاهز للتعبئة للفترة من ' . formatDate($weekStart) . ' إلى ' . formatDate($weekEnd),
            'report_created',
            ['manager', 'employee']
        );

        logActivity('create_report', "إنشاء تقرير #$reportId");
        setSuccess('تم إنشاء التقرير بنجاح وإرسال إشعارات للأقسام');
        redirect('pages/view_report.php?id=' . $reportId);

    } catch (Exception $e) {
        setError('حدث خطأ أثناء إنشاء التقرير: ' . $e->getMessage());
        redirect('pages/create_report.php');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-plus-circle me-2"></i>إنشاء تقرير أسبوعي جديد</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">إنشاء تقرير</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" id="reportForm">
            <?= csrfField() ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>معلومات التقرير</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h5 class="fw-bold text-primary">وكيلة البحوث والتنمية الصناعية</h5>
                        <p class="text-muted">إنشاء تقرير إنجازات أسبوعي جديد</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        سيتم إنشاء تقرير مفتوح للتعبئة. سيتمكن مدراء وموظفو الأقسام من تعبئة إنجازاتهم.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="week_start" class="form-label fw-bold">
                                <i class="fas fa-calendar me-1"></i>من تاريخ
                            </label>
                            <input type="date" class="form-control" id="week_start" name="week_start"
                                   value="<?= $weekRange['start'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="week_end" class="form-label fw-bold">
                                <i class="fas fa-calendar me-1"></i>إلى تاريخ
                            </label>
                            <input type="date" class="form-control" id="week_end" name="week_end"
                                   value="<?= $weekRange['end'] ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-4">
                <a href="<?= SITE_URL ?>/pages/dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-1"></i>إنشاء التقرير
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

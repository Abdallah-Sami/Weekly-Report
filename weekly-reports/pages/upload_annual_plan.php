<?php
/**
 * صفحة رفع وإدارة الخطط السنوية مع نظام تتبع المؤشرات
 */
$pageTitle = 'الخطط السنوية';
require_once __DIR__ . '/../includes/header.php';

$departments = getDepartments();

// معالجة رفع خطة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/upload_annual_plan.php');
    }

    // رفع خطة جديدة
    if ($_POST['action'] === 'upload_plan' && (isAdmin() || isManager())) {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $year = (int)($_POST['year'] ?? date('Y'));

        if (isManager() && $deptId != $_SESSION['user_department_id']) {
            setError('ليس لديك صلاحية رفع خطة لهذا القسم');
            redirect('pages/upload_annual_plan.php');
        }

        if (isset($_FILES['plan_file']) && $_FILES['plan_file']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['plan_file'], 'annual_plans');
            if ($result['success']) {
                dbQuery(
                    "INSERT INTO annual_plans (department_id, year, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)",
                    [$deptId, $year, $result['file_name'], $result['file_path'], $_SESSION['user_id']]
                );
                logActivity('upload_plan', "رفع خطة سنوية للقسم #$deptId للعام $year");
                setSuccess('تم رفع الخطة السنوية بنجاح');
            } else {
                setError(implode('<br>', $result['errors']));
            }
        } else {
            setError('يرجى اختيار ملف');
        }
        redirect('pages/upload_annual_plan.php');
    }

    // إضافة مؤشر جديد
    if ($_POST['action'] === 'add_indicator' && (isAdmin() || isManager())) {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $indicatorName = trim($_POST['indicator_name'] ?? '');
        $targetValue = (float)($_POST['target_value'] ?? 0);
        $unit = trim($_POST['unit'] ?? '%');
        $quarter = $_POST['quarter'] ?? 'annual';

        if ($planId && $indicatorName) {
            dbQuery(
                "INSERT INTO plan_indicators (plan_id, indicator_name, target_value, unit, quarter, updated_by) VALUES (?, ?, ?, ?, ?, ?)",
                [$planId, $indicatorName, $targetValue, $unit, $quarter, $_SESSION['user_id']]
            );
            logActivity('add_indicator', "إضافة مؤشر '$indicatorName' للخطة #$planId");
            setSuccess('تم إضافة المؤشر بنجاح');
        } else {
            setError('يرجى ملء جميع الحقول المطلوبة');
        }
        redirect('pages/upload_annual_plan.php?plan_id=' . $planId);
    }

    // تحديث مؤشر
    if ($_POST['action'] === 'update_indicator' && (isAdmin() || isManager())) {
        $indicatorId = (int)($_POST['indicator_id'] ?? 0);
        $currentValue = (float)($_POST['current_value'] ?? 0);
        $status = $_POST['status'] ?? 'not_started';
        $notes = trim($_POST['notes'] ?? '');

        if ($indicatorId) {
            dbQuery(
                "UPDATE plan_indicators SET current_value = ?, status = ?, notes = ?, updated_by = ? WHERE id = ?",
                [$currentValue, $status, $notes, $_SESSION['user_id'], $indicatorId]
            );
            logActivity('update_indicator', "تحديث المؤشر #$indicatorId");
            setSuccess('تم تحديث المؤشر بنجاح');
        }

        $indicator = dbFetchOne("SELECT plan_id FROM plan_indicators WHERE id = ?", [$indicatorId]);
        redirect('pages/upload_annual_plan.php?plan_id=' . ($indicator['plan_id'] ?? ''));
    }

    // حذف مؤشر
    if ($_POST['action'] === 'delete_indicator' && isAdmin()) {
        $indicatorId = (int)($_POST['indicator_id'] ?? 0);
        $indicator = dbFetchOne("SELECT plan_id FROM plan_indicators WHERE id = ?", [$indicatorId]);
        if ($indicatorId) {
            dbQuery("DELETE FROM plan_indicators WHERE id = ?", [$indicatorId]);
            logActivity('delete_indicator', "حذف المؤشر #$indicatorId");
            setSuccess('تم حذف المؤشر بنجاح');
        }
        redirect('pages/upload_annual_plan.php?plan_id=' . ($indicator['plan_id'] ?? ''));
    }
}

// جلب الخطط
$plansQuery = "SELECT ap.*, d.name as department_name, u.name as uploader_name
               FROM annual_plans ap
               JOIN departments d ON ap.department_id = d.id
               JOIN users u ON ap.uploaded_by = u.id";
$plansParams = [];

if (isManager()) {
    $plansQuery .= " WHERE ap.department_id = ?";
    $plansParams[] = $_SESSION['user_department_id'];
}

$plansQuery .= " ORDER BY ap.year DESC, ap.uploaded_at DESC";
$plans = dbFetchAll($plansQuery, $plansParams);

// إذا تم اختيار خطة معينة، جلب مؤشراتها
$selectedPlanId = (int)($_GET['plan_id'] ?? 0);
$selectedPlan = null;
$indicators = [];
if ($selectedPlanId) {
    $selectedPlan = dbFetchOne(
        "SELECT ap.*, d.name as department_name FROM annual_plans ap JOIN departments d ON ap.department_id = d.id WHERE ap.id = ?",
        [$selectedPlanId]
    );
    if ($selectedPlan) {
        $indicators = dbFetchAll(
            "SELECT pi.*, u.name as updater_name FROM plan_indicators pi LEFT JOIN users u ON pi.updated_by = u.id WHERE pi.plan_id = ? ORDER BY pi.quarter, pi.id",
            [$selectedPlanId]
        );
    }
}

// حساب نسبة الإنجاز لخطة معينة
function getPlanProgress($planId) {
    $result = dbFetchOne(
        "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'achieved' THEN 1 ELSE 0 END) as achieved, AVG(CASE WHEN target_value > 0 THEN LEAST((current_value / target_value) * 100, 100) ELSE 0 END) as avg_progress FROM plan_indicators WHERE plan_id = ?",
        [$planId]
    );
    return $result;
}

function getIndicatorStatusBadge($status) {
    $badges = [
        'not_started' => '<span class="badge bg-secondary">لم يبدأ</span>',
        'in_progress' => '<span class="badge bg-info">جاري التنفيذ</span>',
        'achieved' => '<span class="badge bg-success">مكتمل</span>',
        'delayed' => '<span class="badge bg-danger">متأخر</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">غير محدد</span>';
}

function getQuarterName($quarter) {
    $names = [
        'Q1' => 'الربع الأول',
        'Q2' => 'الربع الثاني',
        'Q3' => 'الربع الثالث',
        'Q4' => 'الربع الرابع',
        'annual' => 'سنوي'
    ];
    return $names[$quarter] ?? $quarter;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>الخطط السنوية</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">الخطط السنوية</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (isAdmin() || isManager()): ?>
<!-- نموذج رفع خطة جديدة -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="mb-0"><i class="fas fa-upload me-2"></i>رفع خطة سنوية جديدة</h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_plan">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">القسم</label>
                    <select class="form-select" name="department_id" required>
                        <?php foreach ($departments as $dept): ?>
                            <?php if (isAdmin() || $dept['id'] == $_SESSION['user_department_id']): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">السنة</label>
                    <select class="form-select" name="year">
                        <?php for ($y = date('Y') + 1; $y >= date('Y') - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">ملف الخطة</label>
                    <input type="file" class="form-control" name="plan_file" required
                           accept=".pdf,.docx,.xlsx">
                    <small class="text-muted">PDF, Word, Excel - حد أقصى 5 ميجابايت</small>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-upload me-1"></i>رفع
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- جدول الخطط -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>سجل الخطط السنوية</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>القسم</th>
                        <th>السنة</th>
                        <th>اسم الملف</th>
                        <th>نسبة الإنجاز</th>
                        <th>رُفع بواسطة</th>
                        <th>تاريخ الرفع</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3 d-block"></i>
                            لا توجد خطط سنوية
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($plans as $i => $plan):
                            $progress = getPlanProgress($plan['id']);
                            $avgProgress = round($progress['avg_progress'] ?? 0);
                            $progressColor = $avgProgress >= 75 ? 'success' : ($avgProgress >= 50 ? 'info' : ($avgProgress >= 25 ? 'warning' : 'danger'));
                        ?>
                        <tr class="<?= $selectedPlanId == $plan['id'] ? 'table-active' : '' ?>">
                            <td><?= $i + 1 ?></td>
                            <td><i class="fas fa-building text-primary me-1"></i><?= e($plan['department_name']) ?></td>
                            <td><span class="badge bg-primary"><?= $plan['year'] ?></span></td>
                            <td>
                                <i class="fas <?= getFileIcon($plan['file_name']) ?> me-1"></i>
                                <?= e($plan['file_name']) ?>
                            </td>
                            <td style="min-width: 140px;">
                                <?php if ($progress['total'] > 0): ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= $avgProgress ?>%"
                                         title="<?= $avgProgress ?>%">
                                        <?= $avgProgress ?>%
                                    </div>
                                </div>
                                <small class="text-muted"><?= $progress['achieved'] ?>/<?= $progress['total'] ?> مؤشر مكتمل</small>
                                <?php else: ?>
                                <small class="text-muted">لا توجد مؤشرات</small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($plan['uploader_name']) ?></td>
                            <td><small class="text-muted"><?= formatDateTime($plan['uploaded_at']) ?></small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= SITE_URL ?>/uploads/<?= e($plan['file_path']) ?>"
                                       class="btn btn-outline-primary" download title="تحميل">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="<?= SITE_URL ?>/pages/upload_annual_plan.php?plan_id=<?= $plan['id'] ?>"
                                       class="btn btn-outline-success" title="المؤشرات">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($selectedPlan): ?>
<!-- قسم تتبع المؤشرات -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">
            <i class="fas fa-chart-line me-2 text-success"></i>
            مؤشرات الأداء - <?= e($selectedPlan['department_name']) ?> (<?= $selectedPlan['year'] ?>)
        </h6>
        <a href="<?= SITE_URL ?>/pages/upload_annual_plan.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times me-1"></i>إغلاق
        </a>
    </div>

    <?php if (isAdmin() || isManager()): ?>
    <!-- نموذج إضافة مؤشر جديد -->
    <div class="card-body border-bottom bg-light">
        <h6 class="fw-bold mb-3"><i class="fas fa-plus-circle me-1 text-primary"></i>إضافة مؤشر جديد</h6>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_indicator">
            <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">اسم المؤشر</label>
                    <input type="text" class="form-control form-control-sm" name="indicator_name" required
                           placeholder="مثال: نسبة إنجاز المشاريع">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">القيمة المستهدفة</label>
                    <input type="number" class="form-control form-control-sm" name="target_value" step="0.01" value="100" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">الوحدة</label>
                    <select class="form-select form-select-sm" name="unit">
                        <option value="%">نسبة مئوية (%)</option>
                        <option value="عدد">عدد</option>
                        <option value="ريال">ريال</option>
                        <option value="يوم">يوم</option>
                        <option value="مشروع">مشروع</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">الفترة</label>
                    <select class="form-select form-select-sm" name="quarter">
                        <option value="annual">سنوي</option>
                        <option value="Q1">الربع الأول</option>
                        <option value="Q2">الربع الثاني</option>
                        <option value="Q3">الربع الثالث</option>
                        <option value="Q4">الربع الرابع</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-plus me-1"></i>إضافة المؤشر
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- جدول المؤشرات -->
    <div class="card-body p-0">
        <?php if (empty($indicators)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-chart-bar fa-3x mb-3 d-block"></i>
            <p>لا توجد مؤشرات أداء لهذه الخطة بعد</p>
            <?php if (isAdmin() || isManager()): ?>
            <p class="small">استخدم النموذج أعلاه لإضافة مؤشرات جديدة</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>المؤشر</th>
                        <th>الفترة</th>
                        <th>المستهدف</th>
                        <th>المتحقق</th>
                        <th>نسبة الإنجاز</th>
                        <th>الحالة</th>
                        <th>ملاحظات</th>
                        <?php if (isAdmin() || isManager()): ?>
                        <th>إجراءات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($indicators as $idx => $ind):
                        $pct = $ind['target_value'] > 0 ? min(round(($ind['current_value'] / $ind['target_value']) * 100), 100) : 0;
                        $barColor = $pct >= 75 ? 'success' : ($pct >= 50 ? 'info' : ($pct >= 25 ? 'warning' : 'danger'));
                    ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td class="fw-bold"><?= e($ind['indicator_name']) ?></td>
                        <td><span class="badge bg-outline-primary border"><?= getQuarterName($ind['quarter']) ?></span></td>
                        <td><?= number_format($ind['target_value'], 2) ?> <?= e($ind['unit']) ?></td>
                        <td><?= number_format($ind['current_value'], 2) ?> <?= e($ind['unit']) ?></td>
                        <td style="min-width: 120px;">
                            <div class="progress" style="height: 18px;">
                                <div class="progress-bar bg-<?= $barColor ?>" style="width: <?= $pct ?>%">
                                    <?= $pct ?>%
                                </div>
                            </div>
                        </td>
                        <td><?= getIndicatorStatusBadge($ind['status']) ?></td>
                        <td><small class="text-muted"><?= e($ind['notes'] ?? '') ?></small></td>
                        <?php if (isAdmin() || isManager()): ?>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#updateModal<?= $ind['id'] ?>" title="تحديث">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <?php if (isAdmin()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المؤشر؟')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_indicator">
                                <input type="hidden" name="indicator_id" value="<?= $ind['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>

                    <?php if (isAdmin() || isManager()): ?>
                    <!-- Modal تحديث المؤشر -->
                    <div class="modal fade" id="updateModal<?= $ind['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_indicator">
                                    <input type="hidden" name="indicator_id" value="<?= $ind['id'] ?>">
                                    <div class="modal-header">
                                        <h6 class="modal-title fw-bold">
                                            <i class="fas fa-sync-alt me-2"></i>تحديث المؤشر
                                        </h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">المؤشر</label>
                                            <p class="form-control-plaintext"><?= e($ind['indicator_name']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">القيمة المتحققة</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="current_value"
                                                       step="0.01" value="<?= $ind['current_value'] ?>" required>
                                                <span class="input-group-text"><?= e($ind['unit']) ?></span>
                                            </div>
                                            <small class="text-muted">المستهدف: <?= number_format($ind['target_value'], 2) ?> <?= e($ind['unit']) ?></small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">الحالة</label>
                                            <select class="form-select" name="status">
                                                <option value="not_started" <?= $ind['status'] === 'not_started' ? 'selected' : '' ?>>لم يبدأ</option>
                                                <option value="in_progress" <?= $ind['status'] === 'in_progress' ? 'selected' : '' ?>>جاري التنفيذ</option>
                                                <option value="achieved" <?= $ind['status'] === 'achieved' ? 'selected' : '' ?>>مكتمل</option>
                                                <option value="delayed" <?= $ind['status'] === 'delayed' ? 'selected' : '' ?>>متأخر</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">ملاحظات</label>
                                            <textarea class="form-control" name="notes" rows="3"><?= e($ind['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>حفظ التحديث
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ملخص المؤشرات -->
        <?php
        $totalIndicators = count($indicators);
        $achievedCount = count(array_filter($indicators, fn($i) => $i['status'] === 'achieved'));
        $inProgressCount = count(array_filter($indicators, fn($i) => $i['status'] === 'in_progress'));
        $delayedCount = count(array_filter($indicators, fn($i) => $i['status'] === 'delayed'));
        $notStartedCount = count(array_filter($indicators, fn($i) => $i['status'] === 'not_started'));
        ?>
        <div class="card-footer bg-light">
            <div class="row text-center">
                <div class="col">
                    <h5 class="mb-0 text-primary"><?= $totalIndicators ?></h5>
                    <small class="text-muted">إجمالي المؤشرات</small>
                </div>
                <div class="col">
                    <h5 class="mb-0 text-success"><?= $achievedCount ?></h5>
                    <small class="text-muted">مكتمل</small>
                </div>
                <div class="col">
                    <h5 class="mb-0 text-info"><?= $inProgressCount ?></h5>
                    <small class="text-muted">جاري التنفيذ</small>
                </div>
                <div class="col">
                    <h5 class="mb-0 text-danger"><?= $delayedCount ?></h5>
                    <small class="text-muted">متأخر</small>
                </div>
                <div class="col">
                    <h5 class="mb-0 text-secondary"><?= $notStartedCount ?></h5>
                    <small class="text-muted">لم يبدأ</small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

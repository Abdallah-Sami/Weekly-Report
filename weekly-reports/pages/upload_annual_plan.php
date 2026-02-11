<?php
/**
 * صفحة رفع وإدارة الخطط السنوية
 * - المدير (admin): كل الصلاحيات
 * - وكيلة الوكالة (director): عرض وتحميل جميع الخطط (قراءة فقط)
 * - المنسق (coordinator): عرض وتحميل جميع الخطط (قراءة فقط)
 * - مدير القسم (manager): رفع وتحميل خطط قسمه فقط
 * - الموظف (employee): لا يمكنه الوصول
 */
$pageTitle = 'الخطط السنوية';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

// الموظف لا يمكنه الوصول
if (isEmployee()) {
    setError('ليس لديك صلاحية الوصول لهذه الصفحة');
    redirect('pages/dashboard.php');
}

$departments = getDepartments();
$canUpload = isAdmin() || isManager();

// معالجة رفع خطة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/upload_annual_plan.php');
    }

    if ($_POST['action'] === 'upload_plan' && $canUpload) {
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
}

// جلب الخطط
$plansQuery = "SELECT ap.*, d.name as department_name, u.name as uploader_name
               FROM annual_plans ap
               JOIN departments d ON ap.department_id = d.id
               JOIN users u ON ap.uploaded_by = u.id";
$plansParams = [];

// مدير القسم يرى خطط قسمه فقط
if (isManager()) {
    $plansQuery .= " WHERE ap.department_id = ?";
    $plansParams[] = $_SESSION['user_department_id'];
}

$plansQuery .= " ORDER BY ap.year DESC, ap.uploaded_at DESC";
$plans = dbFetchAll($plansQuery, $plansParams);
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

<?php if ($canUpload): ?>
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
                        <th>رُفع بواسطة</th>
                        <th>تاريخ الرفع</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3 d-block"></i>
                            لا توجد خطط سنوية
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($plans as $i => $plan): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><i class="fas fa-building text-primary me-1"></i><?= e($plan['department_name']) ?></td>
                            <td><span class="badge bg-primary"><?= $plan['year'] ?></span></td>
                            <td>
                                <i class="fas <?= getFileIcon($plan['file_name']) ?> me-1"></i>
                                <?= e($plan['file_name']) ?>
                            </td>
                            <td><?= e($plan['uploader_name']) ?></td>
                            <td><small class="text-muted"><?= formatDateTime($plan['uploaded_at']) ?></small></td>
                            <td>
                                <a href="<?= SITE_URL ?>/uploads/<?= e($plan['file_path']) ?>"
                                   class="btn btn-sm btn-outline-primary" download title="تحميل">
                                    <i class="fas fa-download"></i>
                                </a>
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

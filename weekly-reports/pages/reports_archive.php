<?php
/**
 * أرشيف التقارير
 */
$pageTitle = 'أرشيف التقارير';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

// معاملات الفلترة
$filterStatus = $_GET['status'] ?? '';
$filterDept = (int)($_GET['department'] ?? 0);
$filterFrom = $_GET['from'] ?? '';
$filterTo = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// بناء الاستعلام
$where = [];
$params = [];

// وكيلة الوكالة ترى المنشور فقط
if (isDirector()) {
    $where[] = "r.status = 'published'";
}

if ($filterStatus && in_array($filterStatus, ['open', 'published'])) {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
}
if ($filterDept) {
    $where[] = "rd.department_id = ?";
    $params[] = $filterDept;
}
if ($filterFrom) {
    $where[] = "r.week_start >= ?";
    $params[] = $filterFrom;
}
if ($filterTo) {
    $where[] = "r.week_end <= ?";
    $params[] = $filterTo;
}
if ($search) {
    $where[] = "(rd.achievements LIKE ? OR rd.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// عدد النتائج
$countSql = "SELECT COUNT(DISTINCT r.id) as total FROM reports r LEFT JOIN report_details rd ON r.id = rd.report_id $whereClause";
$totalResult = dbFetchOne($countSql, $params);
$total = $totalResult['total'] ?? 0;
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// جلب التقارير
$sql = "SELECT DISTINCT r.*, u.name as creator_name
        FROM reports r
        JOIN users u ON r.created_by = u.id
        LEFT JOIN report_details rd ON r.id = rd.report_id
        $whereClause
        ORDER BY r.created_at DESC
        LIMIT $perPage OFFSET $offset";

$reports = dbFetchAll($sql, $params);
$departments = getDepartments();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-archive me-2"></i>أرشيف التقارير</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">الأرشيف</li>
            </ol>
        </nav>
    </div>
    <?php if (canCreateReport()): ?>
    <a href="<?= SITE_URL ?>/pages/create_report.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>تقرير جديد
    </a>
    <?php endif; ?>
</div>

<!-- فلترة البحث -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">من تاريخ</label>
                <input type="date" class="form-control form-control-sm" name="from" value="<?= e($filterFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">إلى تاريخ</label>
                <input type="date" class="form-control form-control-sm" name="to" value="<?= e($filterTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">القسم</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">الكل</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= $filterDept == $dept['id'] ? 'selected' : '' ?>>
                        <?= e($dept['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!isDirector()): ?>
            <div class="col-md-2">
                <label class="form-label small">الحالة</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">الكل</option>
                    <option value="open" <?= $filterStatus === 'open' ? 'selected' : '' ?>>مفتوح للتعبئة</option>
                    <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>منشور</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small">بحث</label>
                <input type="text" class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="بحث...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-search me-1"></i>بحث
                </button>
            </div>
        </form>
    </div>
</div>

<!-- جدول التقارير -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>فترة التقرير</th>
                        <th>أنشئ بواسطة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            لا توجد تقارير
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><span class="fw-bold text-primary">#<?= $r['id'] ?></span></td>
                            <td><?= formatDate($r['week_start']) ?> - <?= formatDate($r['week_end']) ?></td>
                            <td><?= e($r['creator_name']) ?></td>
                            <td><small class="text-muted"><?= formatDateTime($r['created_at']) ?></small></td>
                            <td><?= getStatusBadge($r['status']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= SITE_URL ?>/pages/view_report.php?id=<?= $r['id'] ?>" class="btn btn-outline-primary" title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (canFillReport() && $r['status'] === 'open'): ?>
                                    <a href="<?= SITE_URL ?>/pages/fill_report.php?id=<?= $r['id'] ?>" class="btn btn-outline-success" title="تعبئة قسمي">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (canEditFullReport() && $r['status'] === 'open'): ?>
                                    <a href="<?= SITE_URL ?>/pages/edit_report.php?id=<?= $r['id'] ?>" class="btn btn-outline-warning" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (canDeleteReport()): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteReport(<?= $r['id'] ?>)" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ترقيم الصفحات -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&status=<?= e($filterStatus) ?>&department=<?= $filterDept ?>&from=<?= e($filterFrom) ?>&to=<?= e($filterTo) ?>&search=<?= e($search) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
$extraScripts = "
<script>
function deleteReport(id) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذا التقرير؟ لا يمكن التراجع عن هذا الإجراء.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then(function(result) {
        if (result.isConfirmed) {
            fetch('" . SITE_URL . "/ajax/delete_report.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&csrf_token=' + '" . generateCsrfToken() . "'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire('تم الحذف', 'تم حذف التقرير بنجاح', 'success').then(function() { location.reload(); });
                } else {
                    Swal.fire('خطأ', data.message || 'حدث خطأ', 'error');
                }
            });
        }
    });
}
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>

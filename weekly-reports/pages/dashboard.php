<?php
/**
 * لوحة التحكم الرئيسية
 */
$pageTitle = 'لوحة التحكم';
require_once __DIR__ . '/../includes/header.php';

// إحصائيات التقارير لهذا الشهر
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$monthReports = dbFetchOne(
    "SELECT COUNT(*) as count FROM reports WHERE created_at BETWEEN ? AND ? AND status = 'published'",
    [$monthStart, $monthEnd . ' 23:59:59']
);

// عدد التقارير لكل قسم
$deptReports = dbFetchAll(
    "SELECT d.name, COUNT(DISTINCT r.id) as count
     FROM departments d
     LEFT JOIN report_details rd ON d.id = rd.department_id
     LEFT JOIN reports r ON rd.report_id = r.id AND r.status = 'published'
     GROUP BY d.id, d.name
     ORDER BY d.id"
);

// إجمالي التقارير
$totalReports = dbFetchOne("SELECT COUNT(*) as count FROM reports");

// تقارير المسودة
$draftReports = dbFetchOne("SELECT COUNT(*) as count FROM reports WHERE status = 'draft'");

// آخر 5 تقارير
$latestReports = dbFetchAll(
    "SELECT r.*, u.name as creator_name
     FROM reports r
     JOIN users u ON r.created_by = u.id
     ORDER BY r.created_at DESC
     LIMIT 5"
);
?>

<!-- عنوان الصفحة -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-home me-2"></i>لوحة التحكم</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">الرئيسية</li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if (isAdmin() || isManager()): ?>
        <a href="<?= SITE_URL ?>/pages/create_report.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>إنشاء تقرير جديد
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- بطاقات الإحصائيات -->
<div class="row g-3 mb-4">
    <!-- تقارير هذا الشهر -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary-light text-primary rounded-circle">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="me-3">
                        <h3 class="mb-0 fw-bold"><?= $monthReports['count'] ?? 0 ?></h3>
                        <p class="text-muted mb-0 small">تقارير هذا الشهر</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- إجمالي التقارير -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success-light text-success rounded-circle">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="me-3">
                        <h3 class="mb-0 fw-bold"><?= $totalReports['count'] ?? 0 ?></h3>
                        <p class="text-muted mb-0 small">إجمالي التقارير</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- المسودات -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning-light text-warning rounded-circle">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="me-3">
                        <h3 class="mb-0 fw-bold"><?= $draftReports['count'] ?? 0 ?></h3>
                        <p class="text-muted mb-0 small">مسودات</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الأقسام -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info-light text-info rounded-circle">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="me-3">
                        <h3 class="mb-0 fw-bold">4</h3>
                        <p class="text-muted mb-0 small">الأقسام</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- الأزرار السريعة -->
<div class="row g-3 mb-4">
    <?php if (isAdmin() || isManager()): ?>
    <div class="col-md-4">
        <a href="<?= SITE_URL ?>/pages/create_report.php" class="card quick-action-card border-0 shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <div class="quick-action-icon bg-primary text-white rounded-circle mx-auto mb-3">
                    <i class="fas fa-plus-circle fa-2x"></i>
                </div>
                <h6 class="fw-bold text-dark">إنشاء تقرير جديد</h6>
                <p class="text-muted small mb-0">إنشاء تقرير الإنجازات الأسبوعي</p>
            </div>
        </a>
    </div>
    <?php endif; ?>
    <div class="col-md-4">
        <a href="<?= SITE_URL ?>/pages/reports_archive.php" class="card quick-action-card border-0 shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <div class="quick-action-icon bg-success text-white rounded-circle mx-auto mb-3">
                    <i class="fas fa-archive fa-2x"></i>
                </div>
                <h6 class="fw-bold text-dark">أرشيف التقارير</h6>
                <p class="text-muted small mb-0">عرض جميع التقارير السابقة</p>
            </div>
        </a>
    </div>
    <?php if (isAdmin() || isManager()): ?>
    <div class="col-md-4">
        <a href="<?= SITE_URL ?>/pages/upload_annual_plan.php" class="card quick-action-card border-0 shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <div class="quick-action-icon bg-warning text-white rounded-circle mx-auto mb-3">
                    <i class="fas fa-calendar-alt fa-2x"></i>
                </div>
                <h6 class="fw-bold text-dark">الخطط السنوية</h6>
                <p class="text-muted small mb-0">رفع وإدارة الخطط السنوية</p>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- آخر التقارير -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-clock me-2 text-primary"></i>آخر التقارير المرفوعة</h6>
                <a href="<?= SITE_URL ?>/pages/reports_archive.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($latestReports)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>لا توجد تقارير بعد</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>فترة التقرير</th>
                                    <th>أنشأ بواسطة</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latestReports as $report): ?>
                                <tr>
                                    <td><span class="fw-bold text-primary">#<?= $report['id'] ?></span></td>
                                    <td>
                                        <small>
                                            <?= formatDate($report['week_start']) ?> - <?= formatDate($report['week_end']) ?>
                                        </small>
                                    </td>
                                    <td><?= e($report['creator_name']) ?></td>
                                    <td><?= getStatusBadge($report['status']) ?></td>
                                    <td><small class="text-muted"><?= formatDateTime($report['created_at']) ?></small></td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/pages/view_report.php?id=<?= $report['id'] ?>"
                                           class="btn btn-sm btn-outline-primary" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- تقارير الأقسام -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-success"></i>تقارير الأقسام</h6>
            </div>
            <div class="card-body">
                <?php foreach ($deptReports as $dept): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <i class="fas fa-building text-muted me-2"></i>
                        <span class="small"><?= e($dept['name']) ?></span>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= $dept['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- آخر الإشعارات -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-bell me-2 text-warning"></i>آخر الإشعارات</h6>
                <a href="<?= SITE_URL ?>/pages/notifications.php" class="btn btn-sm btn-outline-warning">عرض الكل</a>
            </div>
            <div class="card-body">
                <?php
                $recentNotifs = getLatestNotifications(3);
                if (empty($recentNotifs)):
                ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-bell-slash fa-2x mb-2"></i>
                        <p class="small mb-0">لا توجد إشعارات</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentNotifs as $notif): ?>
                    <div class="d-flex align-items-start mb-3 pb-2 border-bottom <?= !$notif['is_read'] ? 'bg-light p-2 rounded' : '' ?>">
                        <div class="me-2">
                            <?php if ($notif['type'] === 'reminder'): ?>
                                <i class="fas fa-clock text-warning"></i>
                            <?php elseif ($notif['type'] === 'report_created'): ?>
                                <i class="fas fa-file-alt text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-edit text-info"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="fw-bold small"><?= e($notif['title']) ?></div>
                            <div class="text-muted smaller"><?= formatDateTime($notif['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

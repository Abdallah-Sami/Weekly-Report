<?php
/**
 * صفحة الإشعارات
 */
$pageTitle = 'الإشعارات';
require_once __DIR__ . '/../includes/header.php';

// تحديد الكل كمقروء
if (isset($_GET['mark_all_read'])) {
    dbQuery("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$_SESSION['user_id']]);
    setSuccess('تم تحديد جميع الإشعارات كمقروءة');
    redirect('pages/notifications.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$total = dbFetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?", [$_SESSION['user_id']])['c'];
$totalPages = ceil($total / $perPage);

$notifications = dbFetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$_SESSION['user_id'], $perPage, $offset]
);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-bell me-2"></i>الإشعارات</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">الإشعارات</li>
            </ol>
        </nav>
    </div>
    <?php if ($total > 0): ?>
    <a href="?mark_all_read=1" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-check-double me-1"></i>تحديد الكل كمقروء
    </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-bell-slash fa-3x mb-3"></i>
                <p>لا توجد إشعارات</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notif): ?>
                <div class="list-group-item <?= !$notif['is_read'] ? 'list-group-item-light' : '' ?>" id="notif-<?= $notif['id'] ?>">
                    <div class="d-flex align-items-start">
                        <div class="me-3 mt-1">
                            <?php if ($notif['type'] === 'reminder'): ?>
                                <div class="rounded-circle bg-warning-light text-warning p-2"><i class="fas fa-clock"></i></div>
                            <?php elseif ($notif['type'] === 'report_created'): ?>
                                <div class="rounded-circle bg-success-light text-success p-2"><i class="fas fa-file-alt"></i></div>
                            <?php else: ?>
                                <div class="rounded-circle bg-info-light text-info p-2"><i class="fas fa-edit"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1 <?= !$notif['is_read'] ? 'fw-bold' : '' ?>"><?= e($notif['title']) ?></h6>
                                <small class="text-muted"><?= formatDateTime($notif['created_at']) ?></small>
                            </div>
                            <p class="mb-1 text-muted"><?= e($notif['message']) ?></p>
                            <?php if (!$notif['is_read']): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="markNotificationRead(<?= $notif['id'] ?>)">
                                <i class="fas fa-check me-1"></i>تحديد كمقروء
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

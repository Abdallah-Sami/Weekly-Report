<?php
/**
 * رأس الصفحة المشترك
 */
ob_start();
require_once __DIR__ . '/functions.php';
startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$unreadCount = getUnreadNotificationsCount();
$latestNotifications = getLatestNotifications(5);
$pageTitle = $pageTitle ?? 'لوحة التحكم';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(SITE_NAME) ?></title>

    <!-- Google Fonts - Tajawal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- أنماط مخصصة -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
    <!-- شريط التنقل العلوي -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <!-- زر القائمة الجانبية للجوال -->
            <button class="btn btn-link text-white d-lg-none me-2 p-0" id="sidebarToggle" style="font-size: 1.2rem;">
                <i class="fas fa-bars"></i>
            </button>

            <!-- اسم الموقع -->
            <a class="navbar-brand" href="<?= SITE_URL ?>/pages/dashboard.php">
                <i class="fas fa-chart-line me-2"></i>
                <span class="d-none d-sm-inline"><?= e(SITE_NAME) ?></span>
                <span class="d-sm-none">التقارير</span>
            </a>

            <!-- القائمة اليمنى -->
            <div class="d-flex align-items-center ms-auto gap-1">
                <!-- الإشعارات -->
                <div class="dropdown">
                    <button class="btn btn-link text-white position-relative px-2" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 1.1rem;">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-start notification-dropdown shadow" style="width: 350px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <strong>الإشعارات</strong>
                            <?php if ($unreadCount > 0): ?>
                                <a href="#" class="text-primary small" onclick="markAllRead()">تحديد الكل كمقروء</a>
                            <?php endif; ?>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (empty($latestNotifications)): ?>
                            <li class="text-center text-muted py-3">
                                <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                                لا توجد إشعارات
                            </li>
                        <?php else: ?>
                            <?php foreach ($latestNotifications as $notif): ?>
                                <li>
                                    <a class="dropdown-item notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>"
                                       href="#" onclick="markNotificationRead(<?= $notif['id'] ?>)">
                                        <div class="d-flex align-items-start">
                                            <div class="notification-icon me-2">
                                                <?php if ($notif['type'] === 'reminder'): ?>
                                                    <i class="fas fa-clock text-warning"></i>
                                                <?php elseif ($notif['type'] === 'report_created'): ?>
                                                    <i class="fas fa-file-alt text-success"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-edit text-info"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold small"><?= e($notif['title']) ?></div>
                                                <div class="text-muted small text-truncate"><?= e($notif['message']) ?></div>
                                                <div class="text-muted smaller"><?= formatDateTime($notif['created_at']) ?></div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li class="text-center">
                            <a class="dropdown-item text-primary" href="<?= SITE_URL ?>/pages/notifications.php">
                                عرض جميع الإشعارات
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- فاصل -->
                <div class="vr mx-2 opacity-25" style="height: 24px; align-self: center;"></div>

                <!-- قائمة المستخدم -->
                <div class="dropdown">
                    <button class="btn btn-link text-white dropdown-toggle text-decoration-none d-flex align-items-center gap-2 px-2" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:32px; height:32px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); font-size: 0.8rem;">
                            <?= mb_substr($currentUser['name'] ?? '', 0, 1) ?>
                        </div>
                        <span class="d-none d-md-inline" style="font-size: 0.85rem;"><?= e($currentUser['name'] ?? '') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-start">
                        <li class="dropdown-header">
                            <div class="fw-bold"><?= e($currentUser['name'] ?? '') ?></div>
                            <div class="small text-muted"><?= getRoleName($currentUser['role'] ?? '') ?></div>
                            <?php if ($currentUser['department_name'] ?? null): ?>
                            <div class="smaller text-muted"><?= e($currentUser['department_name']) ?></div>
                            <?php endif; ?>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= SITE_URL ?>/auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- الحاوية الرئيسية -->
    <div class="d-flex" id="wrapper">
        <!-- القائمة الجانبية -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- المحتوى الرئيسي -->
        <div class="flex-grow-1" id="page-content">
            <div class="container-fluid p-4">
                <?= showAlert() ?>

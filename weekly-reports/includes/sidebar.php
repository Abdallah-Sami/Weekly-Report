<?php
/**
 * القائمة الجانبية
 */
?>
<div class="sidebar bg-dark text-white" id="sidebar">
    <div class="sidebar-header text-center py-3 border-bottom border-secondary">
        <h6 class="mb-0">
            <i class="fas fa-building me-1"></i>
            لوحة التحكم
        </h6>
    </div>
    <nav class="sidebar-nav">
        <ul class="nav flex-column py-2">
            <!-- لوحة التحكم -->
            <li class="nav-item">
                <a class="nav-link <?= isActivePage('dashboard') ?>" href="<?= SITE_URL ?>/pages/dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    <span>الرئيسية</span>
                </a>
            </li>

            <!-- إنشاء تقرير - متاح للجميع -->
            <li class="nav-item">
                <a class="nav-link <?= isActivePage('create_report') ?>" href="<?= SITE_URL ?>/pages/create_report.php">
                    <i class="fas fa-plus-circle me-2"></i>
                    <span>إنشاء تقرير جديد</span>
                </a>
            </li>

            <!-- أرشيف التقارير -->
            <li class="nav-item">
                <a class="nav-link <?= isActivePage('reports_archive') ?>" href="<?= SITE_URL ?>/pages/reports_archive.php">
                    <i class="fas fa-archive me-2"></i>
                    <span>أرشيف التقارير</span>
                </a>
            </li>

            <!-- الخطط السنوية -->
            <li class="nav-item">
                <a class="nav-link <?= isActivePage('upload_annual_plan') ?>" href="<?= SITE_URL ?>/pages/upload_annual_plan.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <span>الخطط السنوية</span>
                </a>
            </li>

            <!-- الإشعارات -->
            <li class="nav-item">
                <a class="nav-link <?= isActivePage('notifications') ?>" href="<?= SITE_URL ?>/pages/notifications.php">
                    <i class="fas fa-bell me-2"></i>
                    <span>الإشعارات</span>
                    <?php $count = getUnreadNotificationsCount(); if ($count > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $count ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <?php if (isAdmin()): ?>
            <!-- الفاصل -->
            <li class="nav-item mt-2 mb-1">
                <span class="nav-link text-muted small text-uppercase">
                    <i class="fas fa-cog me-2"></i>الإدارة
                </span>
            </li>

            <!-- إدارة المستخدمين -->
            <li class="nav-item">
                <a class="nav-link <?= isActivePage('users_management') ?>" href="<?= SITE_URL ?>/pages/users_management.php">
                    <i class="fas fa-users-cog me-2"></i>
                    <span>إدارة المستخدمين</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- معلومات المستخدم أسفل القائمة -->
    <div class="sidebar-footer border-top border-secondary p-3 mt-auto">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-user-circle fa-2x text-light"></i>
            </div>
            <div class="flex-grow-1 me-2 overflow-hidden">
                <div class="fw-bold small text-truncate"><?= e($currentUser['name'] ?? '') ?></div>
                <div class="text-muted smaller"><?= getRoleName($currentUser['role'] ?? '') ?></div>
            </div>
        </div>
    </div>
</div>

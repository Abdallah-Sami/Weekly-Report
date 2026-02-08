/**
 * السكريبت المخصص
 * نظام إدارة التقارير الأسبوعية
 */

document.addEventListener('DOMContentLoaded', function() {
    // =====================================================
    // القائمة الجانبية للجوال
    // =====================================================
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        // إنشاء طبقة التعتيم
        var overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
});

// =====================================================
// وظائف الإشعارات
// =====================================================

/**
 * تحديد إشعار كمقروء
 */
function markNotificationRead(id) {
    fetch(getSiteUrl() + '/ajax/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            // تحديث واجهة الإشعار
            var notifEl = document.getElementById('notif-' + id);
            if (notifEl) {
                notifEl.classList.remove('list-group-item-light');
                var btn = notifEl.querySelector('.btn');
                if (btn) btn.remove();
            }
            updateNotificationBadge(data.unread_count);
        }
    })
    .catch(function(err) { console.error('خطأ:', err); });
}

/**
 * تحديد جميع الإشعارات كمقروءة
 */
function markAllRead() {
    fetch(getSiteUrl() + '/ajax/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'all=1'
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            updateNotificationBadge(0);
            // تحديث العناصر المرئية
            document.querySelectorAll('.notification-item.unread').forEach(function(el) {
                el.classList.remove('unread');
            });
        }
    })
    .catch(function(err) { console.error('خطأ:', err); });
}

/**
 * تحديث شارة عدد الإشعارات
 */
function updateNotificationBadge(count) {
    var badges = document.querySelectorAll('.notification-badge');
    badges.forEach(function(badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    });
}

/**
 * الحصول على رابط الموقع
 */
function getSiteUrl() {
    // استخراج الرابط من العنصر الموجود
    var link = document.querySelector('a[href*="dashboard"]');
    if (link) {
        var href = link.getAttribute('href');
        return href.replace('/pages/dashboard.php', '');
    }
    return '';
}

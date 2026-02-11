<?php
/**
 * مهمة مجدولة: إرسال تذكير أسبوعي
 * يتم تشغيلها كل يوم اثنين الساعة 9:00 صباحاً
 *
 * إعداد Cron Job:
 * 0 9 * * 1 /usr/bin/php /path/to/weekly-reports/cron/send_weekly_reminder.php
 *
 * النظام الجديد:
 * - يتحقق من وجود تقرير مفتوح (status = 'open')
 * - يرسل تذكير للأقسام التي لم تعبئ بعد
 * - يرسل للمدراء والموظفين في تلك الأقسام
 */

require_once __DIR__ . '/../config/database.php';

// البحث عن التقرير المفتوح
$openReport = dbFetchOne("SELECT * FROM reports WHERE status = 'open' ORDER BY created_at DESC LIMIT 1");

if (!$openReport) {
    echo date('Y-m-d H:i:s') . " - لا يوجد تقرير مفتوح حالياً\n";
    exit;
}

$weekStart = formatDate($openReport['week_start']);
$weekEnd = formatDate($openReport['week_end']);
$reportId = $openReport['id'];

// جلب الأقسام التي لم تعبئ بعد
$departments = dbFetchAll("SELECT * FROM departments ORDER BY id");
$reminderCount = 0;

foreach ($departments as $dept) {
    // التحقق هل القسم عبأ أم لا
    $detail = dbFetchOne(
        "SELECT id FROM report_details WHERE report_id = ? AND department_id = ? AND achievements IS NOT NULL AND achievements != ''",
        [$reportId, $dept['id']]
    );

    if ($detail) {
        // القسم عبأ بالفعل
        continue;
    }

    // جلب المدراء والموظفين في هذا القسم
    $users = dbFetchAll(
        "SELECT u.*, d.name as department_name FROM users u JOIN departments d ON u.department_id = d.id WHERE u.department_id = ? AND u.role IN ('manager', 'employee')",
        [$dept['id']]
    );

    foreach ($users as $user) {
        // إنشاء إشعار داخلي
        $title = 'تذكير: تعبئة التقرير الأسبوعي';
        $message = "مرحباً {$user['name']}، يرجى تعبئة إنجازات قسم {$user['department_name']} في التقرير الأسبوعي للفترة من $weekStart إلى $weekEnd";

        $stmt = db()->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'reminder')");
        $stmt->execute([$user['id'], $title, $message]);

        // إرسال بريد إلكتروني
        $to = $user['email'];
        $subject = "=?UTF-8?B?" . base64_encode('تذكير: تعبئة التقرير الأسبوعي') . "?=";
        $htmlBody = "
        <div dir='rtl' style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2 style='color: #0e7490;'>وكالة البحوث والتنمية الصناعية</h2>
            <h3>تذكير بتعبئة التقرير الأسبوعي</h3>
            <p>مرحباً <strong>{$user['name']}</strong>،</p>
            <p>نذكركم بتعبئة إنجازات قسم <strong>{$user['department_name']}</strong> في التقرير الأسبوعي.</p>
            <p>الفترة: من <strong>$weekStart</strong> إلى <strong>$weekEnd</strong></p>
            <p><a href='" . SITE_URL . "/pages/fill_report.php?id=$reportId' style='background: #0e7490; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>تعبئة التقرير الآن</a></p>
            <hr>
            <p style='color: #666; font-size: 12px;'>هذه رسالة تلقائية من نظام إدارة التقارير الأسبوعية</p>
        </div>";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";

        @mail($to, $subject, $htmlBody, $headers);
        $reminderCount++;
    }
}

echo date('Y-m-d H:i:s') . " - تم إرسال $reminderCount تذكير للأقسام التي لم تعبئ بعد\n";

<?php
/**
 * مهمة مجدولة: إرسال تذكير أسبوعي لمدراء الأقسام
 * يتم تشغيلها كل يوم اثنين الساعة 9:00 صباحاً
 *
 * إعداد Cron Job:
 * 0 9 * * 1 /usr/bin/php /path/to/weekly-reports/cron/send_weekly_reminder.php
 */

require_once __DIR__ . '/../config/database.php';

// جلب مدراء الأقسام
$managers = dbFetchAll("SELECT u.*, d.name as department_name FROM users u JOIN departments d ON u.department_id = d.id WHERE u.role = 'manager'");

$weekStart = date('Y/m/d', strtotime('monday this week'));
$weekEnd = date('Y/m/d', strtotime('friday this week'));

foreach ($managers as $manager) {
    // إنشاء إشعار داخلي
    $title = 'تذكير: رفع التقرير الأسبوعي';
    $message = "مرحباً {$manager['name']}، يرجى رفع تقرير الإنجازات الأسبوعي لـ{$manager['department_name']} للفترة من $weekStart إلى $weekEnd";

    $stmt = db()->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'reminder')");
    $stmt->execute([$manager['id'], $title, $message]);

    // إرسال بريد إلكتروني
    $to = $manager['email'];
    $subject = "=?UTF-8?B?" . base64_encode('تذكير: رفع التقرير الأسبوعي') . "?=";
    $htmlBody = "
    <div dir='rtl' style='font-family: Arial, sans-serif; padding: 20px;'>
        <h2 style='color: #2563eb;'>وكالة البحوث والتنمية الصناعية</h2>
        <h3>تذكير بموعد رفع التقرير الأسبوعي</h3>
        <p>مرحباً <strong>{$manager['name']}</strong>،</p>
        <p>نذكركم بموعد رفع تقرير الإنجازات الأسبوعي لقسم <strong>{$manager['department_name']}</strong></p>
        <p>الفترة: من <strong>$weekStart</strong> إلى <strong>$weekEnd</strong></p>
        <p><a href='" . SITE_URL . "/pages/create_report.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>رفع التقرير الآن</a></p>
        <hr>
        <p style='color: #666; font-size: 12px;'>هذه رسالة تلقائية من نظام إدارة التقارير الأسبوعية</p>
    </div>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";

    @mail($to, $subject, $htmlBody, $headers);
}

echo date('Y-m-d H:i:s') . " - تم إرسال " . count($managers) . " تذكير\n";

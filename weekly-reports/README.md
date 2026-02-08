# نظام إدارة التقارير الأسبوعية

## وكالة البحوث والتنمية الصناعية

نظام متكامل لإدارة التقارير الأسبوعية مع نظام صلاحيات متعدد المستويات.

---

## المتطلبات

- PHP 7.4 أو أحدث
- MySQL 5.7 أو أحدث
- Apache مع mod_rewrite (أو Nginx)
- إضافة PDO لـ PHP

---

## خطوات التنصيب

### 1. إعداد قاعدة البيانات

```bash
# استيراد قاعدة البيانات
mysql -u root -p < sql/database.sql
```

أو من خلال phpMyAdmin: استيراد ملف `sql/database.sql`

### 2. إعداد الاتصال بقاعدة البيانات

عدّل ملف `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'weekly_reports_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. إعداد رابط الموقع

في نفس الملف `config/database.php`:

```php
define('SITE_URL', 'http://localhost/weekly-reports');
```

### 4. صلاحيات المجلدات

```bash
chmod -R 755 uploads/
chmod -R 755 uploads/attachments/
chmod -R 755 uploads/annual_plans/
```

### 5. إعداد Cron Job للتذكير الأسبوعي

```bash
# إضافة مهمة مجدولة تعمل كل اثنين الساعة 9 صباحاً
crontab -e

# أضف السطر التالي:
0 9 * * 1 /usr/bin/php /path/to/weekly-reports/cron/send_weekly_reminder.php
```

---

## بيانات تسجيل الدخول الافتراضية

| الدور | البريد الإلكتروني | كلمة المرور |
|-------|-------------------|-------------|
| المدير العام | admin@example.com | Admin@123 |
| مدير التنمية الصناعية | manager1@example.com | Pass@123 |
| مدير مصادر التعلم | manager2@example.com | Pass@123 |
| مدير الابتكار | manager3@example.com | Pass@123 |
| مدير البحوث | manager4@example.com | Pass@123 |
| موظف | employee@example.com | Pass@123 |

---

## الأقسام

1. قسم التنمية الصناعية
2. قسم مصادر التعلم
3. قسم الابتكار
4. قسم البحوث

---

## نظام الصلاحيات

### المدير العام (admin)
- عرض وتعديل وحذف جميع التقارير
- إدارة المستخدمين
- عرض جميع الخطط السنوية
- وصول كامل للنظام

### مدير القسم (manager)
- إنشاء تقارير لقسمه
- تعديل تقارير الأسبوع الحالي
- رفع الشواهد والمرفقات
- رفع الخطة السنوية لقسمه

### موظف (employee)
- عرض التقارير (قراءة فقط)
- تحميل الشواهد والمرفقات

---

## هيكل الملفات

```
weekly-reports/
├── config/database.php          # إعدادات قاعدة البيانات
├── includes/
│   ├── header.php               # رأس الصفحة
│   ├── footer.php               # تذييل الصفحة
│   ├── sidebar.php              # القائمة الجانبية
│   └── functions.php            # الوظائف المشتركة
├── assets/css/style.css         # الأنماط المخصصة
├── assets/js/script.js          # السكريبت المخصص
├── uploads/                     # مجلد الملفات المرفوعة
├── pages/                       # صفحات النظام
├── ajax/                        # طلبات AJAX
├── cron/                        # المهام المجدولة
├── auth/                        # المصادقة
├── sql/database.sql             # ملف قاعدة البيانات
└── index.php                    # نقطة الدخول
```

---

## الميزات التقنية

- **الأمان**: CSRF Protection, XSS Prevention, SQL Injection Prevention, Bcrypt Password Hashing
- **التصميم**: Bootstrap 5 RTL, Font Awesome 6, SweetAlert2
- **التصميم المتجاوب**: يعمل على جميع الأجهزة
- **Session Timeout**: انتهاء الجلسة بعد 30 دقيقة من عدم النشاط
- **نظام الإشعارات**: إشعارات داخلية + تذكير بالبريد الإلكتروني
- **سجل العمليات**: تسجيل جميع الأنشطة

---

## الترخيص

جميع الحقوق محفوظة - وكالة البحوث والتنمية الصناعية

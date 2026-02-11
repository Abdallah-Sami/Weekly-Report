<?php
/**
 * إعدادات الاتصال بقاعدة البيانات
 * نظام إدارة التقارير الأسبوعية
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'weekly_reports_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_NAME', 'وكيلة البحوث والتنمية الصناعية');
define('SITE_URL', 'http://localhost/weekly-reports');
define('BASE_PATH', dirname(__DIR__));

// إعدادات الجلسة
define('SESSION_TIMEOUT', 1800); // 30 دقيقة

// إعدادات رفع الملفات
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 ميجابايت
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx']);
define('UPLOAD_PATH', BASE_PATH . '/uploads/');

// إعدادات البريد الإلكتروني
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'نظام التقارير الأسبوعية');

/**
 * فئة الاتصال بقاعدة البيانات (Singleton Pattern)
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
        }
    }

    /**
     * الحصول على نسخة واحدة من الاتصال
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * الحصول على كائن PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    // منع النسخ
    private function __clone() {}

    // منع إعادة الإنشاء
    public function __wakeup() {
        throw new \Exception("لا يمكن إعادة إنشاء الاتصال");
    }
}

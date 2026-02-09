-- =====================================================
-- نظام إدارة التقارير الأسبوعية
-- وكالة البحوث والتنمية الصناعية
-- =====================================================

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS weekly_reports_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE weekly_reports_db;

-- =====================================================
-- جدول الأقسام
-- =====================================================
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المستخدمين
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee',
    department_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول التقارير
-- =====================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول تفاصيل التقارير
-- =====================================================
CREATE TABLE IF NOT EXISTS report_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    department_id INT NOT NULL,
    achievements TEXT,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المرفقات (الشواهد)
-- =====================================================
CREATE TABLE IF NOT EXISTS attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_detail_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_detail_id) REFERENCES report_details(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الخطط السنوية
-- =====================================================
CREATE TABLE IF NOT EXISTS annual_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    year YEAR NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول مؤشرات الأداء (KPIs) للخطط السنوية
-- =====================================================
CREATE TABLE IF NOT EXISTS plan_indicators (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    indicator_name VARCHAR(255) NOT NULL,
    target_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    current_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit VARCHAR(50) DEFAULT '%',
    quarter ENUM('Q1', 'Q2', 'Q3', 'Q4', 'annual') NOT NULL DEFAULT 'annual',
    status ENUM('not_started', 'in_progress', 'achieved', 'delayed') NOT NULL DEFAULT 'not_started',
    notes TEXT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES annual_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الإشعارات
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    type ENUM('reminder', 'report_created', 'report_updated') NOT NULL DEFAULT 'reminder',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول سجل العمليات (Logs)
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- إدخال البيانات الأولية
-- =====================================================

-- الأقسام الأربعة
INSERT INTO departments (name) VALUES
('قسم التنمية الصناعية'),
('قسم مصادر التعلم'),
('قسم الابتكار'),
('قسم البحوث');

-- المستخدمين
-- كلمات المرور مشفرة بـ bcrypt
-- Admin@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('المدير العام', 'admin@example.com', '$2y$12$u206Rg6HsXp9N9olqNzcX.sjzthtH2vkpGXwM5rjy8PM7Tqi7SFjW', 'admin', NULL);

-- Pass@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('مدير التنمية الصناعية', 'manager1@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 1),
('مدير مصادر التعلم', 'manager2@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 2),
('مدير الابتكار', 'manager3@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 3),
('مدير البحوث', 'manager4@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 4);

-- Pass@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('موظف', 'employee@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'employee', 1);

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
    role ENUM('admin', 'director', 'coordinator', 'manager', 'employee') NOT NULL DEFAULT 'employee',
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
    status ENUM('open', 'published') NOT NULL DEFAULT 'open',
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
-- جدول الإشعارات
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    type ENUM('reminder', 'report_created', 'report_updated', 'report_published', 'section_filled') NOT NULL DEFAULT 'reminder',
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
-- Admin@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('مدير النظام', 'admin@example.com', '$2y$12$u206Rg6HsXp9N9olqNzcX.sjzthtH2vkpGXwM5rjy8PM7Tqi7SFjW', 'admin', NULL);

-- Pass@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('وكيلة الوكالة', 'director@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'director', NULL),
('المنسق', 'coordinator@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'coordinator', NULL);

-- مدراء الأقسام - Pass@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('مدير التنمية الصناعية', 'mgr.industrial@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 1),
('مدير مصادر التعلم', 'mgr.learning@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 2),
('مدير الابتكار', 'mgr.innovation@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 3),
('مدير البحوث', 'mgr.research@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'manager', 4);

-- موظفي الأقسام - Pass@123
INSERT INTO users (name, email, password, role, department_id) VALUES
('موظف التنمية الصناعية', 'emp.industrial@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'employee', 1),
('موظف مصادر التعلم', 'emp.learning@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'employee', 2),
('موظف الابتكار', 'emp.innovation@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'employee', 3),
('موظف البحوث', 'emp.research@example.com', '$2y$12$ANcbaTeC5zObXgdTaquceuH.apjAII9bnThcvvuRrn86OgcHCOiJS', 'employee', 4);

<?php
/**
 * صفحة تسجيل الدخول
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// إعادة التوجيه إذا كان مسجلاً بالفعل
if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
}
if (isset($_GET['logout'])) {
    $error = ''; // تم تسجيل الخروج بنجاح
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #2563eb 50%, #1d4ed8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .login-body {
            padding: 2rem;
            background: white;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37,99,235,0.25);
        }
        .btn-login {
            background: var(--primary);
            border-color: var(--primary);
            padding: 0.75rem;
            font-size: 1.1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37,99,235,0.4);
        }
        .input-group-text {
            background: #f8f9fa;
            border-left: none;
        }
        .form-control {
            border-right: none;
        }
        .input-group .form-control:focus + .input-group-text {
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card login-card mx-auto">
                    <!-- رأس نموذج الدخول -->
                    <div class="login-header">
                        <i class="fas fa-building"></i>
                        <h4 class="mb-1"><?= e(SITE_NAME) ?></h4>
                        <p class="mb-0 opacity-75">نظام إدارة التقارير الأسبوعية</p>
                    </div>

                    <!-- نموذج الدخول -->
                    <div class="login-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?= e($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['logout'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-1"></i>
                                تم تسجيل الخروج بنجاح
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= SITE_URL ?>/auth/login_process.php" id="loginForm">
                            <?= csrfField() ?>

                            <!-- البريد الإلكتروني -->
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">
                                    <i class="fas fa-envelope me-1 text-muted"></i>البريد الإلكتروني
                                </label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="email" name="email"
                                           placeholder="أدخل البريد الإلكتروني" required autofocus
                                           dir="ltr">
                                    <span class="input-group-text"><i class="fas fa-at"></i></span>
                                </div>
                            </div>

                            <!-- كلمة المرور -->
                            <div class="mb-3">
                                <label for="password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-1 text-muted"></i>كلمة المرور
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="أدخل كلمة المرور" required dir="ltr">
                                    <span class="input-group-text" style="cursor: pointer" onclick="togglePassword()">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </span>
                                </div>
                            </div>

                            <!-- تذكرني -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">تذكرني</label>
                                </div>
                            </div>

                            <!-- زر الدخول -->
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                            </button>
                        </form>
                    </div>
                </div>

                <!-- حقوق النشر -->
                <p class="text-center text-white-50 mt-3 small">
                    &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> - جميع الحقوق محفوظة
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // إظهار/إخفاء كلمة المرور
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // عرض رسالة خطأ عند فشل تسجيل الدخول
        <?php if (isset($_GET['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'خطأ في تسجيل الدخول',
            text: '<?= e($_GET['error'] === 'invalid' ? 'البريد الإلكتروني أو كلمة المرور غير صحيحة' : 'حدث خطأ غير متوقع') ?>',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563eb'
        });
        <?php endif; ?>
    </script>
</body>
</html>

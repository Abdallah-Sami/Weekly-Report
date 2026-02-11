<?php
/**
 * صفحة تسجيل الدخول - تصميم عصري واحترافي
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
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
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Tajawal', sans-serif;
            min-height: 100vh;
            display: flex;
            overflow: hidden;
            background: #0f172a;
        }

        /* ====== الجانب الأيسر - الصورة/الرسومات ====== */
        .login-visual {
            flex: 1;
            background: linear-gradient(135deg, #0c4a6e 0%, #075985 30%, #0369a1 60%, #0284c7 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-visual::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            border-radius: 50%;
        }

        .login-visual::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            bottom: -50px;
            right: -50px;
            border-radius: 50%;
        }

        .visual-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            padding: 3rem;
        }

        .visual-content .logo-icon {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2.5rem;
            border: 1px solid rgba(255,255,255,0.2);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .visual-content h2 {
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .visual-content p {
            font-size: 1.05rem;
            opacity: 0.85;
            line-height: 1.8;
            max-width: 350px;
            margin: 0 auto;
        }

        /* الأشكال الهندسية المتحركة */
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: shapeFloat 8s ease-in-out infinite;
        }

        .shape:nth-child(1) { width: 80px; height: 80px; top: 15%; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 50px; height: 50px; top: 70%; left: 80%; animation-delay: 2s; border-radius: 12px; }
        .shape:nth-child(3) { width: 120px; height: 120px; top: 50%; left: 20%; animation-delay: 4s; }
        .shape:nth-child(4) { width: 40px; height: 40px; top: 25%; left: 75%; animation-delay: 1s; border-radius: 8px; }
        .shape:nth-child(5) { width: 60px; height: 60px; top: 80%; left: 40%; animation-delay: 3s; }

        @keyframes shapeFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.6; }
        }

        /* ====== الجانب الأيمن - النموذج ====== */
        .login-form-side {
            width: 520px;
            min-width: 520px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .login-form-wrapper {
            width: 100%;
            max-width: 380px;
        }

        .login-brand {
            margin-bottom: 2.5rem;
        }

        .login-brand .brand-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #0369a1, #0ea5e9);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
        }

        .login-brand h3 {
            font-weight: 800;
            color: #0f172a;
            font-size: 1.5rem;
            margin-bottom: 0.4rem;
        }

        .login-brand p {
            color: #94a3b8;
            font-size: 0.95rem;
            margin: 0;
        }

        /* حقول الإدخال */
        .form-floating-custom {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .form-floating-custom .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-floating-custom .input-wrapper {
            position: relative;
        }

        .form-floating-custom .input-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            z-index: 3;
            transition: color 0.3s;
        }

        .form-floating-custom .form-control {
            padding: 0.8rem 2.75rem 0.8rem 2.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.3s ease;
            height: auto;
            color: #1e293b;
        }

        .form-floating-custom .form-control:focus {
            border-color: #0284c7;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
        }

        .form-floating-custom .form-control:focus ~ .input-icon,
        .form-floating-custom .form-control:focus + .input-icon {
            color: #0284c7;
        }

        .form-floating-custom .toggle-password {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            z-index: 3;
            padding: 0;
            transition: color 0.3s;
        }

        .form-floating-custom .toggle-password:hover {
            color: #2563eb;
        }

        /* تذكرني */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }

        .custom-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .custom-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0284c7;
            border-radius: 4px;
            cursor: pointer;
        }

        .custom-check label {
            color: #64748b;
            font-size: 0.875rem;
            cursor: pointer;
            user-select: none;
        }

        /* زر الدخول */
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, #0369a1, #0ea5e9);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(3, 105, 161, 0.45);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* الحقوق */
        .login-footer {
            text-align: center;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }

        .login-footer p {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* التنبيهات */
        .custom-alert {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .custom-alert.alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .custom-alert.alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        /* متجاوب */
        @media (max-width: 991.98px) {
            .login-visual { display: none; }
            .login-form-side {
                width: 100%;
                min-width: 100%;
            }
        }

        @media (max-width: 575.98px) {
            .login-form-side { padding: 2rem 1.5rem; }
            .login-brand h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <!-- الجانب الأيمن: النموذج -->
    <div class="login-form-side">
        <div class="login-form-wrapper">
            <!-- العلامة التجارية -->
            <div class="login-brand">
                <div class="brand-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>مرحباً بك</h3>
                <p>سجّل دخولك للوصول إلى نظام التقارير</p>
            </div>

            <!-- التنبيهات -->
            <?php if (!empty($error)): ?>
                <div class="custom-alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="custom-alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    تم تسجيل الخروج بنجاح
                </div>
            <?php endif; ?>

            <!-- النموذج -->
            <form method="POST" action="<?= SITE_URL ?>/auth/login_process.php" id="loginForm">
                <?= csrfField() ?>

                <!-- البريد الإلكتروني -->
                <div class="form-floating-custom">
                    <label class="form-label">البريد الإلكتروني</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="example@domain.com" required autofocus dir="ltr">
                    </div>
                </div>

                <!-- كلمة المرور -->
                <div class="form-floating-custom">
                    <label class="form-label">كلمة المرور</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="أدخل كلمة المرور" required dir="ltr">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- تذكرني -->
                <div class="remember-row">
                    <div class="custom-check">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">تذكرني</label>
                    </div>
                </div>

                <!-- زر الدخول -->
                <button type="submit" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket me-2"></i>
                    تسجيل الدخول
                </button>
            </form>

            <!-- الحقوق -->
            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
            </div>
        </div>
    </div>

    <!-- الجانب الأيسر: الرسومات -->
    <div class="login-visual">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        <div class="visual-content">
            <div class="logo-icon" style="background:none; border:none; width:auto; height:auto; border-radius:0;">
                <img src="<?= SITE_URL ?>/assets/img/logo.png" alt="الشعار" style="height: 90px;">
            </div>
            <h2><?= e(SITE_NAME) ?></h2>
            <p>نظام إدارة التقارير الأسبوعية<br>لمتابعة إنجازات الأقسام وتوثيق الأعمال بكفاءة عالية</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
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

        <?php if (isset($_GET['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'خطأ في تسجيل الدخول',
            text: '<?= e($_GET['error'] === 'invalid' ? 'البريد الإلكتروني أو كلمة المرور غير صحيحة' : 'حدث خطأ غير متوقع') ?>',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#0284c7'
        });
        <?php endif; ?>
    </script>
</body>
</html>

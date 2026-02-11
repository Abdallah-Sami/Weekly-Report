<?php
/**
 * إدارة المستخدمين - للمدير العام فقط
 */
$pageTitle = 'إدارة المستخدمين';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$departments = getDepartments();

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/users_management.php');
    }

    $action = $_POST['form_action'] ?? '';

    // إضافة مستخدم جديد
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        // الأدوار التي لا تحتاج قسم
        if (in_array($role, ['admin', 'director', 'coordinator'])) {
            $deptId = null;
        }

        if (empty($name) || empty($email) || empty($password)) {
            setError('جميع الحقول مطلوبة');
        } else {
            $existing = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                setError('البريد الإلكتروني مستخدم بالفعل');
            } else {
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);
                dbQuery(
                    "INSERT INTO users (name, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)",
                    [$name, $email, $hashedPass, $role, $deptId]
                );
                logActivity('add_user', "إضافة مستخدم: $email");
                setSuccess('تم إضافة المستخدم بنجاح');
            }
        }
    }

    // تعديل مستخدم
    elseif ($action === 'edit') {
        $userId = (int)$_POST['user_id'];
        $name = sanitize($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'employee';
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        // الأدوار التي لا تحتاج قسم
        if (in_array($role, ['admin', 'director', 'coordinator'])) {
            $deptId = null;
        }

        $existing = dbFetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existing) {
            setError('البريد الإلكتروني مستخدم بالفعل');
        } else {
            dbQuery(
                "UPDATE users SET name = ?, email = ?, role = ?, department_id = ? WHERE id = ?",
                [$name, $email, $role, $deptId, $userId]
            );
            // تحديث كلمة المرور إذا تم إدخالها
            if (!empty($_POST['password'])) {
                $hashedPass = password_hash($_POST['password'], PASSWORD_BCRYPT);
                dbQuery("UPDATE users SET password = ? WHERE id = ?", [$hashedPass, $userId]);
            }
            logActivity('edit_user', "تعديل مستخدم #$userId");
            setSuccess('تم تحديث بيانات المستخدم بنجاح');
        }
    }

    // حذف مستخدم
    elseif ($action === 'delete') {
        $userId = (int)$_POST['user_id'];
        if ($userId == $_SESSION['user_id']) {
            setError('لا يمكنك حذف حسابك');
        } else {
            dbQuery("DELETE FROM users WHERE id = ?", [$userId]);
            logActivity('delete_user', "حذف مستخدم #$userId");
            setSuccess('تم حذف المستخدم بنجاح');
        }
    }

    redirect('pages/users_management.php');
}

// جلب المستخدمين
$users = dbFetchAll(
    "SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.id"
);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-users-cog me-2"></i>إدارة المستخدمين</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">إدارة المستخدمين</li>
            </ol>
        </nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-1"></i>إضافة مستخدم
    </button>
</div>

<!-- جدول المستخدمين -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الدور</th>
                        <th>القسم</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><i class="fas fa-user-circle text-primary me-1"></i><?= e($user['name']) ?></td>
                        <td dir="ltr" class="text-end"><?= e($user['email']) ?></td>
                        <td>
                            <?php
                            $roleBadges = [
                                'admin' => 'danger',
                                'director' => 'info',
                                'coordinator' => 'success',
                                'manager' => 'primary',
                                'employee' => 'secondary'
                            ];
                            ?>
                            <span class="badge bg-<?= $roleBadges[$user['role']] ?? 'secondary' ?>">
                                <?= getRoleName($user['role']) ?>
                            </span>
                        </td>
                        <td><?= e($user['department_name'] ?? '-') ?></td>
                        <td><small class="text-muted"><?= formatDateTime($user['created_at']) ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-warning" onclick="editUser(<?= htmlspecialchars(json_encode($user), ENT_QUOTES) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= e($user['name']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- نافذة إضافة مستخدم -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>إضافة مستخدم جديد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الاسم</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">البريد الإلكتروني</label>
                        <input type="email" class="form-control" name="email" required dir="ltr">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور</label>
                        <input type="password" class="form-control" name="password" required dir="ltr" minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الدور</label>
                        <select class="form-select" name="role" id="addRole" onchange="toggleDeptField('add')">
                            <option value="employee">موظف</option>
                            <option value="manager">مدير قسم</option>
                            <option value="coordinator">المنسق</option>
                            <option value="director">وكيلة الوكالة</option>
                            <option value="admin">مدير النظام</option>
                        </select>
                    </div>
                    <div class="mb-3" id="addDeptField">
                        <label class="form-label fw-bold">القسم</label>
                        <select class="form-select" name="department_id">
                            <option value="">-- اختر القسم --</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل مستخدم -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل المستخدم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الاسم</label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">البريد الإلكتروني</label>
                        <input type="email" class="form-control" name="email" id="editEmail" required dir="ltr">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الجديدة <small class="text-muted">(اتركها فارغة لعدم التغيير)</small></label>
                        <input type="password" class="form-control" name="password" dir="ltr">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الدور</label>
                        <select class="form-select" name="role" id="editRole" onchange="toggleDeptField('edit')">
                            <option value="employee">موظف</option>
                            <option value="manager">مدير قسم</option>
                            <option value="coordinator">المنسق</option>
                            <option value="director">وكيلة الوكالة</option>
                            <option value="admin">مدير النظام</option>
                        </select>
                    </div>
                    <div class="mb-3" id="editDeptField">
                        <label class="form-label fw-bold">القسم</label>
                        <select class="form-select" name="department_id" id="editDeptId">
                            <option value="">-- اختر القسم --</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>تحديث</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نموذج حذف مخفي -->
<form method="POST" id="deleteUserForm" style="display:none">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="delete">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<?php
$extraScripts = "
<script>
function toggleDeptField(prefix) {
    var role = document.getElementById(prefix + 'Role').value;
    var field = document.getElementById(prefix + 'DeptField');
    // إخفاء حقل القسم للأدوار التي لا تحتاج قسم
    if (role === 'admin' || role === 'director' || role === 'coordinator') {
        field.style.display = 'none';
    } else {
        field.style.display = 'block';
    }
}

function editUser(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editDeptId').value = user.department_id || '';
    toggleDeptField('edit');
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function deleteUser(id, name) {
    Swal.fire({
        title: 'تأكيد الحذف',
        html: 'هل أنت متأكد من حذف المستخدم <strong>' + name + '</strong>؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserForm').submit();
        }
    });
}
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>

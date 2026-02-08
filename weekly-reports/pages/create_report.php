<?php
/**
 * صفحة إنشاء تقرير أسبوعي جديد
 */
$pageTitle = 'إنشاء تقرير جديد';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'manager']);

$departments = getDepartments();
$weekRange = getCurrentWeekRange();

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setError('خطأ في التحقق من الأمان');
        redirect('pages/create_report.php');
    }

    $weekStart = $_POST['week_start'] ?? '';
    $weekEnd = $_POST['week_end'] ?? '';
    $status = ($_POST['action'] === 'publish') ? 'published' : 'draft';

    if (empty($weekStart) || empty($weekEnd)) {
        setError('يرجى تحديد فترة التقرير');
        redirect('pages/create_report.php');
    }

    try {
        db()->beginTransaction();

        // إنشاء التقرير
        dbQuery(
            "INSERT INTO reports (week_start, week_end, status, created_by) VALUES (?, ?, ?, ?)",
            [$weekStart, $weekEnd, $status, $_SESSION['user_id']]
        );
        $reportId = db()->lastInsertId();

        // إدخال تفاصيل كل قسم
        foreach ($departments as $dept) {
            $achievements = $_POST['achievements_' . $dept['id']] ?? '';
            $notes = $_POST['notes_' . $dept['id']] ?? '';

            // مدير القسم يكتب لقسمه فقط
            if (isManager() && $dept['id'] != $_SESSION['user_department_id']) {
                continue;
            }

            if (!empty($achievements)) {
                dbQuery(
                    "INSERT INTO report_details (report_id, department_id, achievements, notes) VALUES (?, ?, ?, ?)",
                    [$reportId, $dept['id'], $achievements, $notes]
                );
                $detailId = db()->lastInsertId();

                // معالجة المرفقات
                if (isset($_FILES['attachments_' . $dept['id']])) {
                    $files = $_FILES['attachments_' . $dept['id']];
                    $fileCount = is_array($files['name']) ? count($files['name']) : 0;

                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $files['name'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];
                            $result = uploadFile($file, 'attachments');
                            if ($result['success']) {
                                dbQuery(
                                    "INSERT INTO attachments (report_detail_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?)",
                                    [$detailId, $result['file_name'], $result['file_path'], $result['file_size']]
                                );
                            }
                        }
                    }
                }
            }
        }

        db()->commit();

        // إنشاء إشعارات
        $statusText = $status === 'published' ? 'نشر' : 'حفظ كمسودة';
        $notifType = $status === 'published' ? 'report_created' : 'report_updated';
        createBulkNotification(
            'تقرير جديد',
            "تم $statusText تقرير أسبوعي جديد للفترة من " . formatDate($weekStart) . " إلى " . formatDate($weekEnd),
            $notifType
        );

        logActivity('create_report', "إنشاء تقرير #$reportId - $statusText");
        setSuccess("تم $statusText التقرير بنجاح");
        redirect('pages/view_report.php?id=' . $reportId);

    } catch (Exception $e) {
        db()->rollBack();
        setError('حدث خطأ أثناء حفظ التقرير: ' . $e->getMessage());
        redirect('pages/create_report.php');
    }
}
?>

<!-- عنوان الصفحة -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-plus-circle me-2"></i>إنشاء تقرير أسبوعي جديد</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/pages/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item active">إنشاء تقرير</li>
            </ol>
        </nav>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" id="reportForm">
    <?= csrfField() ?>

    <!-- معلومات التقرير -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>معلومات التقرير</h6>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <h5 class="fw-bold text-primary">وكالة البحوث والتنمية الصناعية</h5>
                <p class="text-muted">تقرير الإنجازات الأسبوعي</p>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="week_start" class="form-label fw-bold">
                        <i class="fas fa-calendar me-1"></i>من تاريخ
                    </label>
                    <input type="date" class="form-control" id="week_start" name="week_start"
                           value="<?= $weekRange['start'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="week_end" class="form-label fw-bold">
                        <i class="fas fa-calendar me-1"></i>إلى تاريخ
                    </label>
                    <input type="date" class="form-control" id="week_end" name="week_end"
                           value="<?= $weekRange['end'] ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول الأقسام -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-primary"></i>إنجازات الأقسام</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-top mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 15%">القسم</th>
                            <th style="width: 40%">الإنجازات</th>
                            <th style="width: 25%">الشواهد</th>
                            <th style="width: 20%">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                        <?php
                            // مدير القسم يرى قسمه فقط
                            $disabled = (isManager() && $dept['id'] != $_SESSION['user_department_id']) ? 'disabled' : '';
                            $readonly = !empty($disabled);
                        ?>
                        <tr <?= $readonly ? 'class="table-light"' : '' ?>>
                            <td class="fw-bold align-middle">
                                <i class="fas fa-building text-primary me-1"></i>
                                <?= e($dept['name']) ?>
                                <?php if ($readonly): ?>
                                    <br><small class="text-muted">(قسم آخر)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <textarea class="form-control" name="achievements_<?= $dept['id'] ?>"
                                          rows="5" placeholder="اكتب إنجازات القسم هنا..."
                                          <?= $disabled ?>></textarea>
                            </td>
                            <td>
                                <?php if (!$readonly): ?>
                                <div class="upload-zone" id="uploadZone_<?= $dept['id'] ?>">
                                    <input type="file" name="attachments_<?= $dept['id'] ?>[]"
                                           class="form-control mb-2" multiple
                                           accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        الأنواع المسموحة: JPG, PNG, PDF, DOCX, XLSX
                                        <br>الحد الأقصى: 5 ميجابايت لكل ملف
                                    </small>
                                    <div class="file-preview mt-2" id="preview_<?= $dept['id'] ?>"></div>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">غير متاح</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <textarea class="form-control" name="notes_<?= $dept['id'] ?>"
                                          rows="5" placeholder="ملاحظات اختيارية..."
                                          <?= $disabled ?>></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- أزرار الإجراءات -->
    <div class="d-flex justify-content-between mb-4">
        <a href="<?= SITE_URL ?>/pages/dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i>إلغاء
        </a>
        <div>
            <button type="submit" name="action" value="draft" class="btn btn-warning me-2">
                <i class="fas fa-save me-1"></i>حفظ كمسودة
            </button>
            <button type="submit" name="action" value="publish" class="btn btn-success">
                <i class="fas fa-paper-plane me-1"></i>نشر التقرير
            </button>
        </div>
    </div>
</form>

<?php
$extraScripts = "
<script>
// معاينة الملفات المرفقة
document.querySelectorAll('input[type=file]').forEach(function(input) {
    input.addEventListener('change', function() {
        var deptId = this.name.match(/\\d+/)[0];
        var preview = document.getElementById('preview_' + deptId);
        preview.innerHTML = '';
        Array.from(this.files).forEach(function(file) {
            var div = document.createElement('div');
            div.className = 'badge bg-light text-dark border me-1 mb-1 p-2';
            div.innerHTML = '<i class=\"fas fa-file me-1\"></i>' + file.name;
            preview.appendChild(div);
        });
    });
});

// تأكيد النشر
document.querySelector('button[value=publish]').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'تأكيد النشر',
        text: 'هل أنت متأكد من نشر هذا التقرير؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، انشر التقرير',
        cancelButtonText: 'إلغاء'
    }).then(function(result) {
        if (result.isConfirmed) {
            var form = document.getElementById('reportForm');
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'publish';
            form.appendChild(input);
            form.submit();
        }
    });
});
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>

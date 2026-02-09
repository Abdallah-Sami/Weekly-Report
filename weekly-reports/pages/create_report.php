<?php
/**
 * صفحة إنشاء تقرير أسبوعي جديد
 * متاح لجميع المستخدمين
 */
$pageTitle = 'إنشاء تقرير جديد';
require_once __DIR__ . '/../includes/header.php';

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

    $rowDepts = $_POST['row_department'] ?? [];
    $rowAchievements = $_POST['row_achievements'] ?? [];
    $rowNotes = $_POST['row_notes'] ?? [];

    if (empty($rowDepts) || empty(array_filter($rowAchievements))) {
        setError('يرجى إضافة صف واحد على الأقل مع إنجازات');
        redirect('pages/create_report.php');
    }

    try {
        db()->beginTransaction();

        dbQuery(
            "INSERT INTO reports (week_start, week_end, status, created_by) VALUES (?, ?, ?, ?)",
            [$weekStart, $weekEnd, $status, $_SESSION['user_id']]
        );
        $reportId = db()->lastInsertId();

        for ($i = 0; $i < count($rowDepts); $i++) {
            $deptId = (int)($rowDepts[$i] ?? 0);
            $achievements = $rowAchievements[$i] ?? '';
            $notes = $rowNotes[$i] ?? '';

            if (empty($deptId) || empty(trim($achievements))) continue;

            dbQuery(
                "INSERT INTO report_details (report_id, department_id, achievements, notes) VALUES (?, ?, ?, ?)",
                [$reportId, $deptId, $achievements, $notes]
            );
            $detailId = db()->lastInsertId();

            $fileKey = 'row_attachments_' . $i;
            if (isset($_FILES[$fileKey])) {
                $files = $_FILES[$fileKey];
                $fileCount = is_array($files['name']) ? count($files['name']) : 0;
                for ($f = 0; $f < $fileCount; $f++) {
                    if ($files['error'][$f] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $files['name'][$f],
                            'tmp_name' => $files['tmp_name'][$f],
                            'error' => $files['error'][$f],
                            'size' => $files['size'][$f]
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

        db()->commit();

        $statusText = $status === 'published' ? 'نشر' : 'حفظ كمسودة';
        createBulkNotification(
            'تقرير جديد',
            "تم $statusText تقرير أسبوعي جديد للفترة من " . formatDate($weekStart) . " إلى " . formatDate($weekEnd),
            $status === 'published' ? 'report_created' : 'report_updated'
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

    <!-- جدول الإنجازات الديناميكي -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-primary"></i>إنجازات الأقسام</h6>
            <button type="button" class="btn btn-primary btn-sm" id="addRowBtn">
                <i class="fas fa-plus me-1"></i>إضافة خانة جديدة
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-top mb-0" id="reportTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%">القسم</th>
                            <th style="width: 35%">الإنجازات</th>
                            <th style="width: 20%">الشواهد</th>
                            <th style="width: 20%">ملاحظات</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody id="reportRows">
                        <tr class="report-row" data-row="0">
                            <td>
                                <select class="form-select" name="row_department[]" required>
                                    <option value="">-- اختر القسم --</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <textarea class="form-control" name="row_achievements[]"
                                          rows="4" placeholder="اكتب الإنجازات هنا..." required></textarea>
                            </td>
                            <td>
                                <div class="upload-zone">
                                    <input type="file" name="row_attachments_0[]"
                                           class="form-control form-control-sm mb-2" multiple
                                           accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx">
                                    <small class="text-muted d-block">JPG, PNG, PDF, DOCX, XLSX<br>حد أقصى 5 ميجابايت</small>
                                    <div class="file-preview mt-1"></div>
                                </div>
                            </td>
                            <td>
                                <textarea class="form-control" name="row_notes[]"
                                          rows="4" placeholder="ملاحظات اختيارية..."></textarea>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-row" title="حذف" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
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
            <button type="submit" name="action" value="publish" class="btn btn-success" id="publishBtn">
                <i class="fas fa-paper-plane me-1"></i>نشر التقرير
            </button>
        </div>
    </div>
</form>

<?php
$deptOptionsJs = '';
foreach ($departments as $dept) {
    $deptOptionsJs .= '<option value=\\"' . $dept['id'] . '\\">' . e($dept['name']) . '</option>';
}

$extraScripts = '
<script>
var rowCounter = 1;
var deptOptions = \'<option value="">-- اختر القسم --</option>' . $deptOptionsJs . '\';

document.getElementById("addRowBtn").addEventListener("click", function() {
    var tbody = document.getElementById("reportRows");
    var tr = document.createElement("tr");
    tr.className = "report-row fade-in";
    tr.setAttribute("data-row", rowCounter);
    tr.innerHTML = \'<td><select class="form-select" name="row_department[]" required>\' + deptOptions + \'</select></td>\' +
        \'<td><textarea class="form-control" name="row_achievements[]" rows="4" placeholder="اكتب الإنجازات هنا..." required></textarea></td>\' +
        \'<td><div class="upload-zone"><input type="file" name="row_attachments_\' + rowCounter + \'[]" class="form-control form-control-sm mb-2" multiple accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx"><small class="text-muted d-block">JPG, PNG, PDF, DOCX, XLSX<br>حد أقصى 5 ميجابايت</small><div class="file-preview mt-1"></div></div></td>\' +
        \'<td><textarea class="form-control" name="row_notes[]" rows="4" placeholder="ملاحظات اختيارية..."></textarea></td>\' +
        \'<td class="text-center align-middle"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="حذف"><i class="fas fa-trash"></i></button></td>\';
    tbody.appendChild(tr);
    rowCounter++;
    updateRemoveButtons();
});

document.getElementById("reportRows").addEventListener("click", function(e) {
    var btn = e.target.closest(".remove-row");
    if (btn) {
        Swal.fire({
            title: "حذف الخانة؟",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "نعم",
            cancelButtonText: "إلغاء"
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.closest("tr").remove();
                updateRemoveButtons();
            }
        });
    }
});

function updateRemoveButtons() {
    var rows = document.querySelectorAll(".report-row");
    rows.forEach(function(row) {
        row.querySelector(".remove-row").disabled = rows.length <= 1;
    });
}

document.getElementById("reportRows").addEventListener("change", function(e) {
    if (e.target.type === "file") {
        var preview = e.target.closest(".upload-zone").querySelector(".file-preview");
        preview.innerHTML = "";
        Array.from(e.target.files).forEach(function(file) {
            var div = document.createElement("div");
            div.className = "badge bg-light text-dark border me-1 mb-1 p-2";
            div.innerHTML = \'<i class="fas fa-file me-1"></i>\' + file.name;
            preview.appendChild(div);
        });
    }
});

document.getElementById("publishBtn").addEventListener("click", function(e) {
    e.preventDefault();
    Swal.fire({
        title: "تأكيد النشر",
        text: "هل أنت متأكد من نشر هذا التقرير؟",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#059669",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "نعم، انشر التقرير",
        cancelButtonText: "إلغاء"
    }).then(function(result) {
        if (result.isConfirmed) {
            var form = document.getElementById("reportForm");
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "action";
            input.value = "publish";
            form.appendChild(input);
            form.submit();
        }
    });
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>

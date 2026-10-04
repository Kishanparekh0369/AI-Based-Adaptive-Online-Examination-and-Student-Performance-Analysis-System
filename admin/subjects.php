<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/"; $base_path = "../"; $page_title = "Manage Subjects";
$edit_id = ""; $subject_name = ""; $subject_code = ""; $description = ""; $status = "Active"; $subject_course = ""; $subject_semester = "";
if(isset($_GET["delete"])){ $id=(int)$_GET["delete"]; mysqli_query($conn,"DELETE FROM subjects WHERE id=$id"); redirect_alert("Subject deleted successfully","subjects.php"); }
if(isset($_GET["edit"])){ $edit_id=$_GET["edit"]; $result=mysqli_query($conn,"SELECT * FROM subjects WHERE id='$edit_id'"); if(mysqli_num_rows($result)>0){ $row=mysqli_fetch_assoc($result); $subject_name=$row["subject_name"]; $subject_code=$row["subject_code"]; $description=$row["description"]; $status=$row["status"]; $subject_course=$row["course"] ?? ""; $subject_semester=$row["semester"] ?? ""; } }
if($_SERVER["REQUEST_METHOD"]=="POST")
{
    $edit_id=clean_data($_POST["edit_id"]); $subject_name=clean_data($_POST["subject_name"]); $subject_code=clean_data($_POST["subject_code"]); $description=clean_data($_POST["description"]); $status=clean_data($_POST["status"]);
    $subject_course=trim($_POST["course"] ?? ""); $subject_semester=trim($_POST["semester"] ?? "");
    if($subject_name=="" || $subject_code==""){ redirect_alert("Subject name and code are required","subjects.php"); }
    if($subject_course!=="" && !academic_valid_course($subject_course)){ redirect_alert("Please select a valid course/program.","subjects.php"); }
    if($subject_semester!=="" && !academic_valid_semester($subject_semester)){ redirect_alert("Please select a valid semester.","subjects.php"); }
    $course_sql = $subject_course !== "" ? "'" . mysqli_real_escape_string($conn, $subject_course) . "'" : "NULL";
    $semester_sql = $subject_semester !== "" ? "'" . mysqli_real_escape_string($conn, $subject_semester) . "'" : "NULL";
    $check=mysqli_query($conn,"SELECT * FROM subjects WHERE (subject_name='$subject_name' OR subject_code='$subject_code') AND id!='$edit_id'");
    if(mysqli_num_rows($check)>0){ redirect_alert("Subject name or code already exists","subjects.php"); }
    if($edit_id!=""){ mysqli_query($conn,"UPDATE subjects SET subject_name='$subject_name', subject_code='$subject_code', description='$description', status='$status', course=$course_sql, semester=$semester_sql WHERE id='$edit_id'"); redirect_alert("Subject updated successfully","subjects.php"); }
    else { mysqli_query($conn,"INSERT INTO subjects (subject_name,subject_code,description,status,course,semester) VALUES ('$subject_name','$subject_code','$description','$status',$course_sql,$semester_sql)"); notify_user("admin", null, "Subject Added", $subject_name . " was added successfully.", "subjects.php"); redirect_alert("Subject inserted successfully","subjects.php"); }
}
include("../app/layouts/admin_header.php");

// ---- Filters (validated server-side) ----
$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_course = isset($_GET["course"]) ? trim((string)$_GET["course"]) : "";
if ($f_course !== "" && !academic_valid_course($f_course)) { $f_course = ""; }
$f_semester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
if ($f_semester !== "" && !academic_valid_semester($f_semester)) { $f_semester = ""; }
$f_status = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "";
if (!in_array($f_status, array("", "Active", "Inactive"), true)) { $f_status = ""; }
$f_page = max(1, (int)($_GET["page"] ?? 1));
$f_per = 10;

$conds = array(); $types = ""; $params = array();
if ($f_search !== "") {
    $like = "%" . $f_search . "%";
    $conds[] = "(s.subject_name LIKE ? OR s.subject_code LIKE ? OR s.description LIKE ?)";
    $types .= "sss"; array_push($params, $like, $like, $like);
}
if ($f_course !== "") { $conds[] = "(s.course IS NULL OR s.course='' OR s.course=?)"; $types .= "s"; $params[] = $f_course; }
if ($f_semester !== "") { $conds[] = "(s.semester IS NULL OR s.semester='' OR s.semester=?)"; $types .= "s"; $params[] = $f_semester; }
if ($f_status !== "") { $conds[] = "s.status = ?"; $types .= "s"; $params[] = $f_status; }
$where_sql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

function admin_sub_all($conn, $sql, $types, $params)
{
    $out = array();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $out; }
    if ($types !== "" && !empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    mysqli_stmt_close($stmt);
    return $out;
}

$count_rows = admin_sub_all($conn, "SELECT COUNT(*) AS c FROM subjects s $where_sql", $types, $params);
$total_rows = $count_rows ? (int)$count_rows[0]["c"] : 0;
$total_pages = max(1, (int)ceil($total_rows / $f_per));
if ($f_page > $total_pages) { $f_page = $total_pages; }
$offset = ($f_page - 1) * $f_per;
$subjects_data = admin_sub_all($conn, "SELECT s.*, COUNT(DISTINCT e.id) AS exam_count, COUNT(DISTINCT q.id) AS question_count FROM subjects s LEFT JOIN exams e ON e.subject_id=s.id LEFT JOIN questions q ON q.subject_id=s.id $where_sql GROUP BY s.id ORDER BY s.id ASC LIMIT $f_per OFFSET $offset", $types, $params);

// ---- Global summary stats (real counts) ----
$stat_total = (int)(admin_sub_all($conn, "SELECT COUNT(*) AS c FROM subjects", "", array())[0]["c"] ?? 0);
$stat_active = (int)(admin_sub_all($conn, "SELECT COUNT(*) AS c FROM subjects WHERE status='Active'", "", array())[0]["c"] ?? 0);
$stat_inactive = max(0, $stat_total - $stat_active);
$stat_courses = (int)(admin_sub_all($conn, "SELECT COUNT(DISTINCT course) AS c FROM subjects WHERE course IS NOT NULL AND course<>''", "", array())[0]["c"] ?? 0);
$stat_semesters = (int)(admin_sub_all($conn, "SELECT COUNT(DISTINCT semester) AS c FROM subjects WHERE semester IS NOT NULL AND semester<>''", "", array())[0]["c"] ?? 0);

$f_params = array("search" => $f_search, "course" => $f_course, "semester" => $f_semester, "status" => $f_status);
$f_chips = array();
if ($f_search !== "") { $f_chips[] = array("Search: " . $f_search, "subjects.php" . admin_qs($f_params, array("search" => "", "page" => 1))); }
if ($f_course !== "") { $f_chips[] = array("Course: " . $f_course, "subjects.php" . admin_qs($f_params, array("course" => "", "page" => 1))); }
if ($f_semester !== "") { $f_chips[] = array("Semester: " . $f_semester, "subjects.php" . admin_qs($f_params, array("semester" => "", "page" => 1))); }
if ($f_status !== "") { $f_chips[] = array("Status: " . $f_status, "subjects.php" . admin_qs($f_params, array("status" => "", "page" => 1))); }
?>
<div class="container-fluid"><div class="row g-0"><div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div><div class="col-lg-10 p-4">
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb dashboard-breadcrumb"><li class="breadcrumb-item">Admin</li><li class="breadcrumb-item active">Subject Management</li></ol></nav>
<div class="sp-page-head sp-fade-up">
    <div>
        <span class="sp-eyebrow"><i class="bi bi-book"></i> Academic Management</span>
        <h1>Manage Subjects</h1>
        <p>Create subjects with optional course and semester scope.</p>
    </div>
    <a href="#subjectFormCard" class="btn btn-gradient"><i class="bi bi-plus-lg me-1"></i><?php echo $edit_id != "" ? "Edit Subject" : "Add Subject"; ?></a>
</div>

<?php
admin_stat_cards(array(
    array("Total Subjects", $stat_total, "bi-book-fill", "blue", "All subjects"),
    array("Active Subjects", $stat_active, "bi-bookmark-check-fill", "green", "Available for exams"),
    array("Inactive Subjects", $stat_inactive, "bi-bookmark-x-fill", "rose", "Hidden"),
    array("Courses Covered", $stat_courses, "bi-journal-bookmark-fill", "violet", "Distinct programs"),
    array("Semesters Covered", $stat_semesters, "bi-layers-fill", "amber", "Distinct terms")
));
?>

<form method="post" class="form-card p-4 mb-4 sp-fade-up" id="subjectFormCard">
    <input type="hidden" name="edit_id" value="<?php echo h($edit_id); ?>">
    <h5 class="fw-bold mb-3"><i class="bi bi-<?php echo $edit_id != "" ? "pencil-square" : "plus-circle"; ?> me-2 text-primary"></i><?php echo $edit_id != "" ? "Edit Subject" : "Add New Subject"; ?></h5>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Subject Name</label><input class="form-control" name="subject_name" value="<?php echo h($subject_name); ?>" required></div>
        <div class="col-md-2"><label class="form-label">Subject Code</label><input class="form-control" name="subject_code" value="<?php echo h($subject_code); ?>" required></div>
        <div class="col-md-3"><label class="form-label">Description</label><input class="form-control" name="description" value="<?php echo h($description); ?>"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option <?php if ($status == "Active") { echo "selected"; } ?>>Active</option><option <?php if ($status == "Inactive") { echo "selected"; } ?>>Inactive</option></select></div>
        <div class="col-md-3"><label class="form-label">Course <span class="text-muted fw-normal">(optional)</span></label><select class="form-select" name="course"><option value="">All Courses</option><?php foreach (academic_course_options() as $c_opt) { ?><option value="<?php echo h($c_opt); ?>" <?php if ($subject_course === $c_opt) { echo "selected"; } ?>><?php echo h($c_opt); ?></option><?php } ?></select></div>
        <div class="col-md-3"><label class="form-label">Semester <span class="text-muted fw-normal">(optional)</span></label><select class="form-select" name="semester"><option value="">All Semesters</option><?php foreach (academic_semester_options() as $s_opt) { ?><option value="<?php echo h($s_opt); ?>" <?php if ($subject_semester === $s_opt) { echo "selected"; } ?>><?php echo h($s_opt); ?></option><?php } ?></select></div>
    </div>
    <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Leave Course/Semester empty for a universal subject. When set, faculty can create exams for this subject only in that academic scope.</small>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-gradient" type="submit"><i class="bi bi-check-lg me-1"></i><?php echo $edit_id != "" ? "Update Subject" : "Add Subject"; ?></button>
        <?php if ($edit_id != "") { ?><a class="btn btn-secondary" href="subjects.php">Cancel Edit</a><?php } ?>
    </div>
</form>

<form method="get" class="toolbar-card mb-4 sp-fade-up">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1" for="fSearch">Search Subjects</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="fSearch" name="search" value="<?php echo h($f_search); ?>" class="form-control" placeholder="Name, code or description...">
            </div>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1" for="fCourse">Course</label>
            <select id="fCourse" name="course" class="form-select">
                <option value="">All Courses</option>
                <?php foreach (academic_course_options() as $c_opt) { ?><option value="<?php echo h($c_opt); ?>" <?php if ($f_course === $c_opt) echo "selected"; ?>><?php echo h($c_opt); ?></option><?php } ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1" for="fSemester">Semester</label>
            <select id="fSemester" name="semester" class="form-select">
                <option value="">All Semesters</option>
                <?php foreach (academic_semester_options() as $s_opt) { ?><option value="<?php echo h($s_opt); ?>" <?php if ($f_semester === $s_opt) echo "selected"; ?>><?php echo h($s_opt); ?></option><?php } ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1" for="fStatus">Status</label>
            <select id="fStatus" name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="Active" <?php if ($f_status === "Active") echo "selected"; ?>>Active</option>
                <option value="Inactive" <?php if ($f_status === "Inactive") echo "selected"; ?>>Inactive</option>
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
            <a class="btn btn-outline-secondary" href="subjects.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
    <?php admin_chips($f_chips, "subjects.php"); ?>
</form>

<div class="table-card p-3 sp-fade-up">
    <div class="table-responsive">
    <table class="table table-hover align-middle admin-table mb-0">
        <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Academic Scope</th><th>Exams</th><th>Questions</th><th>Description</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
        <tbody>
            <?php if (!empty($subjects_data)) { foreach ($subjects_data as $row) {
                $sid = (int)$row["id"];
                $scope = trim(($row["course"] ?? "") . " " . ($row["semester"] ?? ""));
            ?>
                <tr>
                    <td data-label="ID"><?php echo $sid; ?></td>
                    <td data-label="Name"><strong><?php echo h($row["subject_name"]); ?></strong></td>
                    <td data-label="Code"><span class="badge bg-primary-soft text-primary"><?php echo h($row["subject_code"]); ?></span></td>
                    <td data-label="Scope"><?php if ($scope !== "") { ?><span class="badge bg-primary-soft text-primary"><?php echo h($scope); ?></span><?php } else { ?><span class="badge bg-light text-dark">Universal</span><?php } ?></td>
                    <td data-label="Exams"><span class="badge bg-info text-dark"><?php echo (int)$row["exam_count"]; ?></span></td>
                    <td data-label="Questions"><span class="badge bg-success"><?php echo (int)$row["question_count"]; ?></span></td>
                    <td data-label="Description"><small class="text-muted"><?php echo h($row["description"] ?: "-"); ?></small></td>
                    <td data-label="Status"><?php echo admin_badge($row["status"]); ?></td>
                    <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewSubjectModal<?php echo $sid; ?>" title="View details" aria-label="View details"><i class="bi bi-eye"></i></button>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#subjectEdit<?php echo $sid; ?>" title="Edit subject" aria-label="Edit subject"><i class="bi bi-pencil"></i></button>
                                        <a class="btn btn-sm btn-danger js-confirm-delete" href="subjects.php?delete=<?php echo $sid; ?>" title="Delete" aria-label="Delete subject"><i class="bi bi-trash"></i></a>
                                    </div>
                    </td>
                </tr>
            <?php } } else {
                admin_empty_row(9, "bi-book", "No subjects found.", "Try changing your filters or add a new subject above.");
            } ?>
        </tbody>
    </table>
    </div>
    <?php admin_pagination($total_rows, $f_page, $f_per, $f_params, "subjects.php"); ?>
</div>

<?php foreach ($subjects_data as $row) {
    $sid = (int)$row["id"];
    $scope = trim(($row["course"] ?? "") . " " . ($row["semester"] ?? ""));
?>
    <div class="modal fade" id="viewSubjectModal<?php echo $sid; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content professional-modal">
                <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-book me-2 text-primary"></i>Subject Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <h5 class="mb-1"><?php echo h($row["subject_name"]); ?></h5>
                    <p class="text-muted small mb-3"><?php echo h($row["subject_code"]); ?> &middot; <?php echo admin_badge($row["status"]); ?></p>
                    <div class="admin-detail-grid">
                        <div class="admin-detail-item"><small>Academic Scope</small><strong><?php echo $scope !== "" ? h($scope) : "Universal (all courses)"; ?></strong></div>
                        <div class="admin-detail-item"><small>Description</small><strong style="font-weight:500;"><?php echo h($row["description"] ?: "-"); ?></strong></div>
                        <div class="admin-detail-item"><small>Exams Created</small><strong><?php echo (int)$row["exam_count"]; ?></strong></div>
                        <div class="admin-detail-item"><small>Questions Banked</small><strong><?php echo (int)$row["question_count"]; ?></strong></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-primary btn-sm" href="exams.php?search=<?php echo urlencode($row["subject_name"]); ?>"><i class="bi bi-calendar-check me-1"></i>View Exams</a>
                    <a class="btn btn-outline-primary btn-sm" href="questions.php?subject_id=<?php echo $sid; ?>"><i class="bi bi-patch-question me-1"></i>View Questions</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="subjectEdit<?php echo $sid; ?>" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" class="modal-content professional-modal"><div class="modal-header"><h5 class="modal-title">Edit Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="edit_id" value="<?php echo $sid; ?>"><div class="row g-3"><div class="col-md-6 form-floating"><input id="subjectName<?php echo $sid; ?>" class="form-control" name="subject_name" value="<?php echo h($row["subject_name"]); ?>" placeholder="Subject Name" required><label for="subjectName<?php echo $sid; ?>">Subject Name</label></div><div class="col-md-6 form-floating"><input id="subjectCode<?php echo $sid; ?>" class="form-control" name="subject_code" value="<?php echo h($row["subject_code"]); ?>" placeholder="Subject Code" required><label for="subjectCode<?php echo $sid; ?>">Subject Code</label></div><div class="col-md-8 form-floating"><input id="subjectDesc<?php echo $sid; ?>" class="form-control" name="description" value="<?php echo h($row["description"]); ?>" placeholder="Description"><label for="subjectDesc<?php echo $sid; ?>">Description</label></div><div class="col-md-4 form-floating"><select id="subjectStatus<?php echo $sid; ?>" class="form-select" name="status"><option <?php if ($row["status"] == "Active") { echo "selected"; } ?>>Active</option><option <?php if ($row["status"] == "Inactive") { echo "selected"; } ?>>Inactive</option></select><label for="subjectStatus<?php echo $sid; ?>">Status</label></div><div class="col-md-6 form-floating"><select id="subjectCourse<?php echo $sid; ?>" class="form-select" name="course"><option value="">All Courses (Universal)</option><?php foreach (academic_course_options() as $c_opt) { ?><option value="<?php echo h($c_opt); ?>" <?php if (($row["course"] ?? "") === $c_opt) { echo "selected"; } ?>><?php echo h($c_opt); ?></option><?php } ?></select><label for="subjectCourse<?php echo $sid; ?>">Course</label></div><div class="col-md-6 form-floating"><select id="subjectSemester<?php echo $sid; ?>" class="form-select" name="semester"><option value="">All Semesters (Universal)</option><?php foreach (academic_semester_options() as $s_opt) { ?><option value="<?php echo h($s_opt); ?>" <?php if (($row["semester"] ?? "") === $s_opt) { echo "selected"; } ?>><?php echo h($s_opt); ?></option><?php } ?></select><label for="subjectSemester<?php echo $sid; ?>">Semester</label></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" type="submit">Save Changes</button></div></form></div></div>
<?php } ?>
</div></div></div>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

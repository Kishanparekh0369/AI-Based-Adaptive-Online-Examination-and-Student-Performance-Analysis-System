<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Exam Library";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

// Assigned subjects list (filters)
$exam_subject_list = array();
$subjects = mysqli_query($conn, "SELECT s.* FROM subjects s INNER JOIN faculty_subjects fs ON s.id=fs.subject_id WHERE fs.faculty_id=$faculty_id AND s.status='Active' ORDER BY s.subject_name ASC");
if ($subjects) {
    while ($sl_row = mysqli_fetch_assoc($subjects)) {
        $exam_subject_list[] = $sl_row;
    }
}

// Filters (validated)
$search = isset($_GET["search"]) ? clean_data($_GET["search"]) : "";
$search_s = mysqli_real_escape_string($conn, $search);
$filter_sub = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;
$filter_status = isset($_GET["status"]) ? clean_data($_GET["status"]) : "";
if ($filter_status !== "Active" && $filter_status !== "Inactive") { $filter_status = ""; }
$filter_mode = isset($_GET["mode"]) ? clean_data($_GET["mode"]) : "";
if ($filter_mode !== "Standard" && $filter_mode !== "Adaptive") { $filter_mode = ""; }
$filter_course = isset($_GET["course"]) ? trim($_GET["course"]) : "";
if ($filter_course !== "" && !academic_valid_course($filter_course)) { $filter_course = ""; }
$filter_semester = isset($_GET["semester"]) ? trim($_GET["semester"]) : "";
if ($filter_semester !== "" && !academic_valid_semester($filter_semester)) { $filter_semester = ""; }
$exam_page = isset($_GET["epage"]) ? max((int)$_GET["epage"], 1) : 1;
$exams_per_page = 8;

$where_clause = " WHERE e.subject_id IN ($subject_ids_csv) ";
if ($search !== "") {
    $where_clause .= " AND (e.exam_title LIKE '%$search_s%' OR s.subject_name LIKE '%$search_s%') ";
}
if ($filter_sub > 0 && faculty_has_subject($faculty_id, $filter_sub)) {
    $where_clause .= " AND e.subject_id=$filter_sub ";
}
if ($filter_status !== "") {
    $where_clause .= " AND e.status='" . mysqli_real_escape_string($conn, $filter_status) . "' ";
}
if ($filter_mode !== "") {
    $where_clause .= " AND e.exam_mode='" . mysqli_real_escape_string($conn, $filter_mode) . "' ";
}
if ($filter_course !== "") {
    $where_clause .= " AND e.course='" . mysqli_real_escape_string($conn, $filter_course) . "' ";
}
if ($filter_semester !== "") {
    $where_clause .= " AND e.semester='" . mysqli_real_escape_string($conn, $filter_semester) . "' ";
}

$total_exam_rows = 0;
$exam_count_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id $where_clause");
if ($exam_count_res && $exam_count_row = mysqli_fetch_assoc($exam_count_res)) {
    $total_exam_rows = (int)$exam_count_row["total"];
}
$total_exam_pages = max((int)ceil($total_exam_rows / $exams_per_page), 1);
if ($exam_page > $total_exam_pages) { $exam_page = $total_exam_pages; }
$exam_offset = ($exam_page - 1) * $exams_per_page;

$exams_result = mysqli_query($conn, "SELECT e.*, s.subject_name, (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.id) AS attempt_count FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id $where_clause ORDER BY e.id DESC LIMIT $exams_per_page OFFSET $exam_offset");
$exams_data = [];
if ($exams_result) {
    while ($e_row = mysqli_fetch_assoc($exams_result)) {
        $eq_ids = [];
        $eq_res = mysqli_query($conn, "SELECT question_id FROM exam_questions WHERE exam_id='" . $e_row["id"] . "'");
        while ($eq = mysqli_fetch_assoc($eq_res)) {
            $eq_ids[] = $eq["question_id"];
        }
        $e_row["edit_questions"] = $eq_ids;
        $e_row["question_count"] = count($eq_ids);
        $exams_data[] = $e_row;
    }
}

// Scope-wide counts for the stat row (unfiltered)
$scope_counts = array("total" => 0, "active" => 0, "adaptive" => 0, "standard" => 0);
$scope_res = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(status='Active') AS active, SUM(exam_mode='Adaptive') AS adaptive, SUM(exam_mode='Standard' OR exam_mode IS NULL OR exam_mode='') AS standard FROM exams WHERE subject_id IN ($subject_ids_csv)");
if ($scope_res && $scope_row = mysqli_fetch_assoc($scope_res)) {
    $scope_counts["total"] = (int)$scope_row["total"];
    $scope_counts["active"] = (int)($scope_row["active"] ?? 0);
    $scope_counts["adaptive"] = (int)($scope_row["adaptive"] ?? 0);
    $scope_counts["standard"] = (int)($scope_row["standard"] ?? 0);
}

function fp_library_page_url($params) {
    $parts = array();
    foreach ($params as $k => $v) {
        if ($v === "" || $v === 0 || $v === "0") { continue; }
        $parts[] = urlencode($k) . "=" . urlencode((string)$v);
    }
    return "exam_library.php" . ($parts ? "?" . implode("&", $parts) : "");
}
$fp_base_params = array("search" => $search, "subject_id" => $filter_sub, "status" => $filter_status, "mode" => $filter_mode, "course" => $filter_course, "semester" => $filter_semester);

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item"><a href="exams.php">Exams</a></li>
                    <li class="breadcrumb-item active">Library</li>
                </ol>
            </nav>

            <!-- Page head -->
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-collection"></i> Exam Library</span>
                    <h1>Exams in Your Scope</h1>
                    <p><?php echo $total_exam_rows; ?> exam<?php echo $total_exam_rows === 1 ? "" : "s"; ?> found · page <?php echo $exam_page; ?> of <?php echo $total_exam_pages; ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="sp-btn sp-btn-ghost" data-fp-refresh><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                    <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-plus-circle"></i> Create New Exam</a>
                </div>
            </div>

            <?php if (count($assigned_subject_ids) == 0) { ?>
                <div class="dashboard-panel text-center py-5">
                    <i class="bi bi-exclamation-triangle fs-1 text-warning d-block mb-3"></i>
                    <h4>No Subjects Assigned</h4>
                    <p class="text-muted">You cannot manage exams because you have not been assigned any subjects yet. Please contact the Admin.</p>
                </div>
            <?php } else { ?>

            <!-- Stat row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="1">
                    <div class="sp-stat">
                        <span class="sp-stat-icon"><i class="bi bi-collection"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $scope_counts["total"]; ?>">0</div>
                        <div class="sp-stat-label">Total Exams</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="2">
                    <div class="sp-stat is-success">
                        <span class="sp-stat-icon"><i class="bi bi-check2-circle"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $scope_counts["active"]; ?>">0</div>
                        <div class="sp-stat-label">Active</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="3">
                    <div class="sp-stat is-indigo">
                        <span class="sp-stat-icon"><i class="bi bi-robot"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $scope_counts["adaptive"]; ?>">0</div>
                        <div class="sp-stat-label">Adaptive</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="4">
                    <div class="sp-stat is-info">
                        <span class="sp-stat-icon"><i class="bi bi-gear"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $scope_counts["standard"]; ?>">0</div>
                        <div class="sp-stat-label">Standard</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="sp-filters sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="fpExamSearch">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" id="fpExamSearch" name="search" value="<?php echo h($search); ?>" placeholder="Exam title or subject...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpExamCourse">Course</label>
                        <select class="form-select" id="fpExamCourse" name="course">
                            <option value="">All Courses</option>
                            <?php foreach (academic_course_options() as $c_opt) { ?>
                                <option value="<?php echo h($c_opt); ?>" <?php echo $filter_course === $c_opt ? "selected" : ""; ?>><?php echo h($c_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpExamSemester">Semester</label>
                        <select class="form-select" id="fpExamSemester" name="semester">
                            <option value="">All Semesters</option>
                            <?php foreach (academic_semester_options() as $s_opt) { ?>
                                <option value="<?php echo h($s_opt); ?>" <?php echo $filter_semester === $s_opt ? "selected" : ""; ?>><?php echo h($s_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fpExamSubject">Subject</label>
                        <select class="form-select" id="fpExamSubject" name="subject_id">
                            <option value="">All Assigned Subjects</option>
                            <?php foreach ($exam_subject_list as $fsub) { ?>
                                <option value="<?php echo (int)$fsub["id"]; ?>" <?php echo $filter_sub === (int)$fsub["id"] ? "selected" : ""; ?>><?php echo h($fsub["subject_name"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpExamStatus">Status</label>
                        <select class="form-select" id="fpExamStatus" name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $filter_status === "Active" ? "selected" : ""; ?>>Active</option>
                            <option value="Inactive" <?php echo $filter_status === "Inactive" ? "selected" : ""; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpExamMode">Mode</label>
                        <select class="form-select" id="fpExamMode" name="mode">
                            <option value="">All Modes</option>
                            <option value="Standard" <?php echo $filter_mode === "Standard" ? "selected" : ""; ?>>Standard</option>
                            <option value="Adaptive" <?php echo $filter_mode === "Adaptive" ? "selected" : ""; ?>>Adaptive</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex gap-2">
                        <button type="submit" class="sp-btn sp-btn-primary w-100" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
                    </div>
                </div>
                <div class="mt-2">
                    <?php if ($search !== "" || $filter_sub > 0 || $filter_status !== "" || $filter_mode !== "" || $filter_course !== "" || $filter_semester !== "") { ?>
                        <a href="exam_library.php" class="small text-decoration-none"><i class="bi bi-x-circle me-1"></i>Clear all filters</a>
                    <?php } else { ?>
                        <span class="sp-filter-count">Showing <?php echo count($exams_data); ?> of <?php echo $total_exam_rows; ?></span>
                    <?php } ?>
                </div>
            </form>

            <!-- Table -->
            <div class="table-card p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle sp-table">
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Marks</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($exams_data) > 0) {
                            foreach ($exams_data as $row) {
                                $fp_mode = $row["exam_mode"] ?? "Standard";
                                $fp_active = $row["status"] == "Active";
                        ?>
                                <tr>
                                    <td data-label="Exam"><strong><?php echo h($row["exam_title"]); ?></strong><?php if (trim($row["course"] ?? "") === "" || trim($row["semester"] ?? "") === "") { ?><br><small class="badge bg-warning text-dark mt-1">Scope not configured</small><?php } ?></td>
                                    <td data-label="Course"><?php echo trim($row["course"] ?? "") !== "" ? h($row["course"]) : '<span class="text-muted">—</span>'; ?></td>
                                    <td data-label="Semester"><?php echo trim($row["semester"] ?? "") !== "" ? h($row["semester"]) : '<span class="text-muted">—</span>'; ?></td>
                                    <td data-label="Subject"><span class="badge bg-primary-soft text-primary"><?php echo h($row["subject_name"]); ?></span></td>
                                    <td data-label="Date"><?php echo date("M j, Y", strtotime($row["exam_date"])); ?></td>
                                    <td data-label="Duration"><?php echo (int)$row["duration"]; ?> mins</td>
                                    <td data-label="Marks"><?php echo (int)$row["pass_marks"]; ?> / <?php echo (int)$row["total_marks"]; ?><br><small class="text-muted"><i class="bi bi-people me-1"></i><?php echo (int)($row["attempt_count"] ?? 0); ?> attempts</small></td>
                                    <td data-label="Mode"><span class="sp-pill <?php echo $fp_mode === "Adaptive" ? "is-adaptive" : "is-available"; ?>"><i class="bi bi-<?php echo $fp_mode === "Adaptive" ? "robot" : "gear"; ?>"></i><?php echo h($fp_mode); ?></span></td>
                                    <td data-label="Status"><span class="sp-pill <?php echo $fp_active ? "is-completed" : "is-expired"; ?>"><i class="bi bi-<?php echo $fp_active ? "check2-circle" : "pause-circle"; ?>"></i><?php echo h($row["status"]); ?></span></td>
                                    <td class="text-end text-nowrap" data-label="Actions">
                                        <a class="btn btn-sm btn-info" href="exam_view.php?id=<?php echo (int)$row["id"]; ?>" title="View Details" aria-label="View exam details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a class="btn btn-sm btn-warning" href="exams.php?edit=<?php echo (int)$row["id"]; ?>" title="Edit Exam" aria-label="Edit exam">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary js-confirm-clone" href="exams.php?clone=<?php echo (int)$row["id"]; ?>" title="Duplicate as Draft" aria-label="Duplicate exam">
                                            <i class="bi bi-copy"></i>
                                        </a>
                                        <a class="btn btn-sm <?php echo $fp_active ? "btn-outline-secondary" : "btn-outline-success"; ?> js-confirm-toggle" href="exams.php?toggle_status=<?php echo (int)$row["id"]; ?>" title="<?php echo $fp_active ? "Set Inactive" : "Set Active"; ?>" aria-label="Toggle exam status">
                                            <i class="bi bi-power"></i>
                                        </a>
                                        <a class="btn btn-sm btn-danger js-confirm-delete" href="exams.php?delete=<?php echo (int)$row["id"]; ?>" title="Delete Exam" aria-label="Delete exam" data-attempts="<?php echo (int)($row["attempt_count"] ?? 0); ?>">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="10">
                                    <div class="sp-empty">
                                        <div class="sp-empty-icon"><i class="bi bi-calendar-x"></i></div>
                                        <h4>No Exams Found</h4>
                                        <p>No exams match your current filters. Try adjusting them, or create your first exam.</p>
                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                            <a href="exam_library.php" class="sp-btn sp-btn-ghost"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
                                            <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-plus-circle"></i> Create Exam</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>

                <?php if ($total_exam_pages > 1) { ?>
                    <nav aria-label="Exam pages" class="mt-3">
                        <ul class="pagination justify-content-center flex-wrap gap-1 mb-0">
                            <li class="page-item <?php echo $exam_page <= 1 ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $exam_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h(fp_library_page_url(array_merge($fp_base_params, array("epage" => max(1, $exam_page - 1))))); ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for ($fp_p = 1; $fp_p <= $total_exam_pages; $fp_p++) { ?>
                                <li class="page-item <?php echo $fp_p === $exam_page ? "active" : ""; ?>">
                                    <a class="page-link" href="<?php echo h(fp_library_page_url(array_merge($fp_base_params, array("epage" => $fp_p)))); ?>"><?php echo $fp_p; ?></a>
                                </li>
                            <?php } ?>
                            <li class="page-item <?php echo $exam_page >= $total_exam_pages ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $exam_page >= $total_exam_pages ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h(fp_library_page_url(array_merge($fp_base_params, array("epage" => min($total_exam_pages, $exam_page + 1))))); ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php } ?>
            </div>

            <?php } ?>
        </div>
    </div>
</div>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>
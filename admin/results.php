<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Exam Results";
include("../app/layouts/admin_header.php");

function admin_student_profile_avatar($profile_image, $class_name) {
    $profile_image = trim((string)$profile_image);
    if ($profile_image !== "" && file_exists("../assets/uploads/profile/" . $profile_image)) {
        return '<img src="../assets/uploads/profile/' . h($profile_image) . '" class="' . h($class_name) . '" style="object-fit:cover" alt="Student profile photo">';
    }
    return '<span class="' . h($class_name) . '"><i class="bi bi-person-fill"></i></span>';
}

// ---- Filter option lists (real distinct values) ----
$course_opts = array();
$cr = mysqli_query($conn, "SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course<>'' ORDER BY course ASC");
if ($cr) { while ($crow = mysqli_fetch_assoc($cr)) { $course_opts[] = $crow["course"]; } }
$semester_opts = array();
$sr = mysqli_query($conn, "SELECT DISTINCT semester, course FROM students WHERE semester IS NOT NULL AND semester<>'' ORDER BY semester ASC");
if ($sr) { while ($srow = mysqli_fetch_assoc($sr)) { $semester_opts[] = $srow; } }
$subject_opts = array();
$subr = mysqli_query($conn, "SELECT id, subject_name, course, semester FROM subjects WHERE status='Active' ORDER BY subject_name ASC");
if ($subr) { while ($surow = mysqli_fetch_assoc($subr)) { $subject_opts[] = $surow; } }
$exam_opts = array();
$exr = mysqli_query($conn, "SELECT id, exam_title, subject_id, course, semester FROM exams ORDER BY id DESC");
if ($exr) { while ($exrow = mysqli_fetch_assoc($exr)) { $exam_opts[] = $exrow; } }

// ---- Filters (validated server-side) ----
$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_course = isset($_GET["course"]) ? trim((string)$_GET["course"]) : "";
if ($f_course !== "" && !in_array($f_course, $course_opts, true)) { $f_course = ""; }
$f_semester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
$f_subject = isset($_GET["subject"]) ? (int)$_GET["subject"] : 0;
$f_exam = isset($_GET["exam"]) ? (int)$_GET["exam"] : 0;
$f_status = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "";
if (!in_array($f_status, array("", "Passed", "Failed"), true)) { $f_status = ""; }
$f_from = isset($_GET["date_from"]) ? trim((string)$_GET["date_from"]) : "";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) { $f_from = ""; }
$f_to = isset($_GET["date_to"]) ? trim((string)$_GET["date_to"]) : "";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to)) { $f_to = ""; }
$f_page = max(1, (int)($_GET["page"] ?? 1));
$f_per = 10;

$conds = array(); $types = ""; $params = array();
if ($f_search !== "") {
    $like = "%" . $f_search . "%";
    $conds[] = "(st.name LIKE ? OR st.email LIKE ? OR e.exam_title LIKE ? OR s.subject_name LIKE ?)";
    $types .= "ssss"; array_push($params, $like, $like, $like, $like);
}
if ($f_course !== "") { $conds[] = "st.course = ?"; $types .= "s"; $params[] = $f_course; }
if ($f_semester !== "") { $conds[] = "st.semester = ?"; $types .= "s"; $params[] = $f_semester; }
if ($f_subject > 0) { $conds[] = "e.subject_id = ?"; $types .= "i"; $params[] = $f_subject; }
if ($f_exam > 0) { $conds[] = "r.exam_id = ?"; $types .= "i"; $params[] = $f_exam; }
if ($f_status === "Passed") { $conds[] = "r.status = 'Pass'"; }
elseif ($f_status === "Failed") { $conds[] = "r.status != 'Pass'"; }
if ($f_from !== "") { $conds[] = "r.submitted_at >= ?"; $types .= "s"; $params[] = $f_from . " 00:00:00"; }
if ($f_to !== "") { $conds[] = "r.submitted_at <= ?"; $types .= "s"; $params[] = $f_to . " 23:59:59"; }
$where_sql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";
$from_sql = "FROM results r LEFT JOIN students st ON st.id=r.student_id LEFT JOIN exams e ON e.id=r.exam_id LEFT JOIN subjects s ON s.id=e.subject_id";

function admin_results_all($conn, $sql, $types, $params)
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

$count_rows = admin_results_all($conn, "SELECT COUNT(*) AS c $from_sql $where_sql", $types, $params);
$total_rows = $count_rows ? (int)$count_rows[0]["c"] : 0;
$total_pages = max(1, (int)ceil($total_rows / $f_per));
if ($f_page > $total_pages) { $f_page = $total_pages; }
$offset = ($f_page - 1) * $f_per;

$rows = admin_results_all($conn, "SELECT r.*, st.name AS student_name, st.email AS student_email, st.course AS student_course, st.semester AS student_semester, st.profile_image, e.exam_title, e.total_marks AS exam_total, e.pass_marks, s.subject_name $from_sql $where_sql ORDER BY r.id DESC LIMIT $f_per OFFSET $offset", $types, $params);

// ---- Summary stats for the current filtered view (real aggregates) ----
$agg_rows = admin_results_all($conn, "SELECT COUNT(*) AS n, SUM(r.status='Pass') AS passc, AVG(r.percentage) AS avgp, AVG(r.marks_obtained) AS avgm, MAX(r.percentage) AS maxp $from_sql $where_sql", $types, $params);
$agg = $agg_rows ? $agg_rows[0] : array("n" => 0, "passc" => 0, "avgp" => null, "avgm" => null, "maxp" => null);
$stat_total = (int)($agg["n"] ?? 0);
$stat_pass = (int)($agg["passc"] ?? 0);
$stat_fail = max(0, $stat_total - $stat_pass);
$stat_avgp = $stat_total > 0 && $agg["avgp"] !== null ? round((float)$agg["avgp"], 1) : 0;
$stat_avgm = $stat_total > 0 && $agg["avgm"] !== null ? round((float)$agg["avgm"], 1) : 0;
$stat_max = $stat_total > 0 && $agg["maxp"] !== null ? round((float)$agg["maxp"], 1) : 0;

$f_params = array("search" => $f_search, "course" => $f_course, "semester" => $f_semester, "subject" => $f_subject > 0 ? $f_subject : "", "exam" => $f_exam > 0 ? $f_exam : "", "status" => $f_status, "date_from" => $f_from, "date_to" => $f_to);
$f_chips = array();
if ($f_search !== "") { $f_chips[] = array("Search: " . $f_search, "results.php" . admin_qs($f_params, array("search" => "", "page" => 1))); }
if ($f_course !== "") { $f_chips[] = array("Course: " . $f_course, "results.php" . admin_qs($f_params, array("course" => "", "semester" => "", "page" => 1))); }
if ($f_semester !== "") { $f_chips[] = array("Semester: " . $f_semester, "results.php" . admin_qs($f_params, array("semester" => "", "page" => 1))); }
if ($f_subject > 0) {
    $fsn = "Subject #" . $f_subject;
    foreach ($subject_opts as $so) { if ((int)$so["id"] === $f_subject) { $fsn = $so["subject_name"]; break; } }
    $f_chips[] = array("Subject: " . $fsn, "results.php" . admin_qs($f_params, array("subject" => "", "exam" => "", "page" => 1)));
}
if ($f_exam > 0) {
    $fen = "Exam #" . $f_exam;
    foreach ($exam_opts as $eo) { if ((int)$eo["id"] === $f_exam) { $fen = $eo["exam_title"]; break; } }
    $f_chips[] = array("Exam: " . $fen, "results.php" . admin_qs($f_params, array("exam" => "", "page" => 1)));
}
if ($f_status !== "") { $f_chips[] = array("Status: " . $f_status, "results.php" . admin_qs($f_params, array("status" => "", "page" => 1))); }
if ($f_from !== "" || $f_to !== "") { $f_chips[] = array("Date: " . ($f_from !== "" ? $f_from : "…") . " to " . ($f_to !== "" ? $f_to : "…"), "results.php" . admin_qs($f_params, array("date_from" => "", "date_to" => "", "page" => 1))); }
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <?php admin_page_head("Results", "bi-award", "Analytics & Results", "Exam Results", "View, filter and analyze examination results across courses, semesters, subjects and exams."); ?>

            <?php
            admin_stat_cards(array(
                array("Total Results", $stat_total, "bi-clipboard-data-fill", "blue", "Attempts in view"),
                array("Passed", $stat_pass, "bi-check-circle-fill", "green", "Status = Pass"),
                array("Failed", $stat_fail, "bi-x-circle-fill", "rose", "Status = Fail"),
                array("Average %", $stat_avgp . "%", "bi-percent", "violet", "Mean percentage"),
                array("Avg Marks", $stat_avgm, "bi-award-fill", "amber", "Mean obtained"),
                array("Highest %", $stat_max . "%", "bi-trophy-fill", "cyan", "Best attempt")
            ));
            ?>

            <!-- Filters -->
            <form method="get" class="toolbar-card mb-4 sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fSearch">Search Student</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="fSearch" name="search" value="<?php echo h($f_search); ?>" class="form-control" placeholder="Student, exam or subject...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fCourse">Course</label>
                        <select id="fCourse" name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($course_opts as $co) { ?><option value="<?php echo h($co); ?>" <?php if ($f_course === $co) echo "selected"; ?>><?php echo h($co); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fSemester">Semester</label>
                        <select id="fSemester" name="semester" class="form-select" data-dep-on="course" data-dep-attr="course">
                            <option value="">All Semesters</option>
                            <?php foreach ($semester_opts as $so) { ?><option value="<?php echo h($so["semester"]); ?>" data-course="<?php echo h($so["course"]); ?>" <?php if ($f_semester === $so["semester"]) echo "selected"; ?>><?php echo h($so["semester"]); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fSubject">Subject</label>
                        <select id="fSubject" name="subject" class="form-select" data-dep-on="course,semester" data-dep-attr="course,semester">
                            <option value="">All Subjects</option>
                            <?php foreach ($subject_opts as $so) { ?><option value="<?php echo (int)$so["id"]; ?>" data-course="<?php echo h((string)($so["course"] ?? "")); ?>" data-semester="<?php echo h((string)($so["semester"] ?? "")); ?>" <?php if ($f_subject === (int)$so["id"]) echo "selected"; ?>><?php echo h($so["subject_name"]); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fExam">Exam</label>
                        <select id="fExam" name="exam" class="form-select" data-dep-on="subject" data-dep-attr="subject">
                            <option value="">All Exams</option>
                            <?php foreach ($exam_opts as $eo) { ?><option value="<?php echo (int)$eo["id"]; ?>" data-subject="<?php echo (int)$eo["subject_id"]; ?>" <?php if ($f_exam === (int)$eo["id"]) echo "selected"; ?>><?php echo h($eo["exam_title"]); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fStatus">Status</label>
                        <select id="fStatus" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="Passed" <?php if ($f_status === "Passed") echo "selected"; ?>>Passed</option>
                            <option value="Failed" <?php if ($f_status === "Failed") echo "selected"; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fFrom">From Date</label>
                        <input type="date" id="fFrom" name="date_from" value="<?php echo h($f_from); ?>" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fTo">To Date</label>
                        <input type="date" id="fTo" name="date_to" value="<?php echo h($f_to); ?>" class="form-control">
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                        <a class="btn btn-outline-secondary" href="results.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <?php admin_chips($f_chips, "results.php"); ?>
            </form>

            <!-- Results Table -->
            <div class="table-card p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Subject</th>
                            <th>Exam</th>
                            <th>Marks</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Attempt Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)) { foreach ($rows as $r) {
                            $tq = max((int)$r["total_questions"], 0);
                            $tm = max((int)($r["exam_total"] ?? 0), $tq, 1);
                        ?>
                            <tr>
                                <td data-label="Student"><div class="d-flex align-items-center gap-2"><?php echo admin_student_profile_avatar($r["profile_image"] ?? "", "student-row-avatar"); ?><div class="admin-cell-main"><strong><?php echo h($r["student_name"] ?? ("#" . (int)$r["student_id"])); ?></strong><small class="text-muted"><?php echo h($r["student_email"] ?? ""); ?></small></div></div></td>
                                <td data-label="Course"><?php echo h($r["student_course"] ?? "-"); ?></td>
                                <td data-label="Semester"><?php echo h($r["student_semester"] ?? "-"); ?></td>
                                <td data-label="Subject"><span class="badge bg-primary-soft text-primary"><?php echo h($r["subject_name"] ?? "-"); ?></span></td>
                                <td data-label="Exam"><?php echo h($r["exam_title"] ?? "-"); ?></td>
                                <td data-label="Marks"><strong><?php echo (int)($r["marks_obtained"] ?? 0); ?> / <?php echo $tm; ?></strong></td>
                                <td data-label="Percentage"><strong><?php echo number_format((float)$r["percentage"], 1); ?>%</strong></td>
                                <td data-label="Status"><?php echo admin_badge($r["status"]); ?></td>
                                <td data-label="Attempt Date"><small class="text-muted"><?php echo !empty($r["submitted_at"]) ? h(date("d M Y, h:i A", strtotime($r["submitted_at"]))) : "-"; ?></small></td>
                                <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewResultModal<?php echo (int)$r["id"]; ?>" title="View result details" aria-label="View result details"><i class="bi bi-eye"></i></button>
                                    <a class="btn btn-sm btn-outline-primary" href="results_export.php?student_id=<?php echo (int)$r["student_id"]; ?>" target="_blank" title="Download student PDF" aria-label="Download student PDF"><i class="bi bi-download"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } } else {
                            admin_empty_row(10, "bi-award", "No examination results found.", "Try changing your filters to see attempts.");
                        } ?>
                    </tbody>
                </table>
                </div>
                <?php admin_pagination($total_rows, $f_page, $f_per, $f_params, "results.php"); ?>
            </div>

            <!-- Detail Modals -->
            <?php foreach ($rows as $r) {
                $tq = max((int)$r["total_questions"], 0);
                $tm = max((int)($r["exam_total"] ?? 0), $tq, 1);
                $sk = max(0, $tq - (int)$r["correct_answers"] - (int)$r["wrong_answers"]);
                $rid = (int)$r["id"];
            ?>
                <div class="modal fade" id="viewResultModal<?php echo $rid; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content professional-modal">
                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-award me-2 text-primary"></i>Result Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                            <div class="modal-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <?php echo admin_student_profile_avatar($r["profile_image"] ?? "", "student-row-avatar"); ?>
                                    <div class="min-w-0">
                                        <h5 class="mb-0"><?php echo h($r["student_name"] ?? ("#" . (int)$r["student_id"])); ?></h5>
                                        <small class="text-muted"><?php echo h($r["exam_title"] ?? ""); ?> &middot; <?php echo h($r["subject_name"] ?? ""); ?></small>
                                    </div>
                                    <span class="ms-auto"><?php echo admin_badge($r["status"]); ?></span>
                                </div>
                                <div class="admin-detail-grid">
                                    <div class="admin-detail-item"><small>Course</small><strong><?php echo h($r["student_course"] ?? "-"); ?></strong></div>
                                    <div class="admin-detail-item"><small>Semester</small><strong><?php echo h($r["student_semester"] ?? "-"); ?></strong></div>
                                    <div class="admin-detail-item"><small>Score</small><strong><?php echo (int)($r["marks_obtained"] ?? 0); ?> / <?php echo $tm; ?></strong></div>
                                    <div class="admin-detail-item"><small>Percentage</small><strong><?php echo number_format((float)$r["percentage"], 1); ?>%</strong></div>
                                    <div class="admin-detail-item"><small>Passing Marks</small><strong><?php echo h((string)($r["pass_marks"] ?? "-")); ?> / <?php echo $tm; ?></strong></div>
                                    <div class="admin-detail-item"><small>Correct Answers</small><strong><?php echo (int)$r["correct_answers"]; ?></strong></div>
                                    <div class="admin-detail-item"><small>Incorrect Answers</small><strong><?php echo (int)$r["wrong_answers"]; ?></strong></div>
                                    <div class="admin-detail-item"><small>Skipped</small><strong><?php echo $sk; ?></strong></div>
                                    <div class="admin-detail-item"><small>Time Taken</small><strong><?php echo h(admin_fmt_duration((int)($r["time_spent_seconds"] ?? 0))); ?></strong></div>
                                    <div class="admin-detail-item"><small>Attempt Date</small><strong><?php echo !empty($r["submitted_at"]) ? h(date("d M Y, h:i A", strtotime($r["submitted_at"]))) : "-"; ?></strong></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a class="btn btn-outline-primary btn-sm" href="results_export.php?student_id=<?php echo (int)$r["student_id"]; ?>" target="_blank"><i class="bi bi-download me-1"></i>Student PDF</a>
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

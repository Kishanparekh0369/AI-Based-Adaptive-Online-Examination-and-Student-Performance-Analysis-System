<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Students";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$scope_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

// ---- Filters (all validated; scope always enforced) ----
$f_search = trim((string)($_GET["search"] ?? ""));
$f_course = trim((string)($_GET["course"] ?? ""));
$f_semester = trim((string)($_GET["semester"] ?? ""));
$f_subject = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;
if ($f_subject > 0 && !faculty_has_subject($faculty_id, $f_subject)) { $f_subject = 0; }
$f_perf = trim((string)($_GET["perf"] ?? ""));
if (!in_array($f_perf, array("", "strong", "average", "needs"), true)) { $f_perf = ""; }
$f_status = trim((string)($_GET["status"] ?? ""));
if (!in_array($f_status, array("", "Active", "Inactive"), true)) { $f_status = ""; }
$f_sort = trim((string)($_GET["sort"] ?? "avg_desc"));
$f_sort_map = array(
    "name_asc" => "st.name ASC",
    "name_desc" => "st.name DESC",
    "avg_desc" => "avg_score DESC",
    "avg_asc" => "avg_score ASC",
    "exams_desc" => "exams_taken DESC",
    "course_asc" => "st.course ASC, st.name ASC",
    "semester_asc" => "st.semester ASC, st.name ASC",
);
if (!isset($f_sort_map[$f_sort])) { $f_sort = "avg_desc"; }
$fp_page = max(1, (int)($_GET["page"] ?? 1));
$f_per_page = 12;

function fp_students_url($params) {
    $params["page"] = $params["page"] ?? 1;
    return "students.php?" . http_build_query(array_filter($params, function ($v) {
        return $v !== "" && $v !== 0 && $v !== "0" && $v !== null;
    }));
}
$fp_base_params = array(
    "search" => $f_search, "course" => $f_course, "semester" => $f_semester,
    "subject_id" => $f_subject, "perf" => $f_perf, "status" => $f_status, "sort" => $f_sort,
);

// ---- Scope statistics (authorized scope only) ----
$fp_total_students = 0;
$fp_active_students = 0;
$fp_avg_perf = 0;
$fp_attempts = 0;
$fp_s1 = mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS c,
    SUM(CASE WHEN st.status='Active' THEN 1 ELSE 0 END) AS active_c,
    AVG(r.percentage) AS avgp, COUNT(r.id) AS attempts
    FROM results r
    INNER JOIN exams e ON r.exam_id=e.id
    LEFT JOIN students st ON st.id=r.student_id
    WHERE e.subject_id IN ($scope_csv)");
if ($fp_s1 && $fp_srow = mysqli_fetch_assoc($fp_s1)) {
    $fp_total_students = (int)$fp_srow["c"];
    // active_c counts rows not distinct students; compute distinctly below
    $fp_avg_perf = $fp_srow["avgp"] !== null ? round((float)$fp_srow["avgp"], 1) : 0;
    $fp_attempts = (int)$fp_srow["attempts"];
}
$fp_s2 = mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id LEFT JOIN students st ON st.id=r.student_id WHERE e.subject_id IN ($scope_csv) AND st.status='Active'");
if ($fp_s2 && $fp_srow2 = mysqli_fetch_assoc($fp_s2)) { $fp_active_students = (int)$fp_srow2["c"]; }

// ---- Distinct filter options (in scope) ----
$fp_courses = array();
$fp_q = mysqli_query($conn, "SELECT DISTINCT st.course FROM students st INNER JOIN results r ON r.student_id=st.id INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($scope_csv) AND st.course<>'' ORDER BY st.course ASC");
if ($fp_q) { while ($r = mysqli_fetch_assoc($fp_q)) { $fp_courses[] = $r["course"]; } }
$fp_semesters = array();
$fp_q = mysqli_query($conn, "SELECT DISTINCT st.semester FROM students st INNER JOIN results r ON r.student_id=st.id INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($scope_csv) AND st.semester<>'' ORDER BY st.semester ASC");
if ($fp_q) { while ($r = mysqli_fetch_assoc($fp_q)) { $fp_semesters[] = $r["semester"]; } }
$fp_subject_opts = get_faculty_assigned_subjects($faculty_id);

// ---- Main query (prepared; dynamic parts whitelisted) ----
$fp_where = "e.subject_id IN ($scope_csv)";
$fp_types = "";
$fp_params = array();
if ($f_search !== "") {
    $fp_where .= " AND (st.name LIKE ? OR st.email LIKE ? OR CAST(st.id AS CHAR) LIKE ?)";
    $like = "%" . $f_search . "%";
    $fp_types .= "sss";
    array_push($fp_params, $like, $like, $like);
}
if ($f_course !== "") { $fp_where .= " AND st.course=?"; $fp_types .= "s"; $fp_params[] = $f_course; }
if ($f_semester !== "") { $fp_where .= " AND st.semester=?"; $fp_types .= "s"; $fp_params[] = $f_semester; }
if ($f_subject > 0) { $fp_where .= " AND e.subject_id=$f_subject"; }
if ($f_status !== "") { $fp_where .= " AND st.status=?"; $fp_types .= "s"; $fp_params[] = $f_status; }
$fp_having = "";
if ($f_perf === "strong") { $fp_having = " HAVING avg_score >= 80 "; }
elseif ($f_perf === "average") { $fp_having = " HAVING avg_score >= 60 AND avg_score < 80 "; }
elseif ($f_perf === "needs") { $fp_having = " HAVING avg_score < 60 "; }

$fp_base_sql = "FROM students st
    INNER JOIN results r ON r.student_id=st.id
    INNER JOIN exams e ON r.exam_id=e.id
    LEFT JOIN subjects s ON e.subject_id=s.id
    WHERE $fp_where";

$fp_count_sql = "SELECT COUNT(*) AS c FROM (SELECT st.id $fp_base_sql GROUP BY st.id $fp_having) AS t";
$fp_total = 0;
$fp_cstmt = mysqli_prepare($conn, $fp_count_sql);
if ($fp_cstmt) {
    if ($fp_types !== "") { mysqli_stmt_bind_param($fp_cstmt, $fp_types, ...$fp_params); }
    mysqli_stmt_execute($fp_cstmt);
    $fp_cres = mysqli_stmt_get_result($fp_cstmt);
    if ($fp_cres && $fp_crow = mysqli_fetch_assoc($fp_cres)) { $fp_total = (int)$fp_crow["c"]; }
    mysqli_stmt_close($fp_cstmt);
}
$fp_pages = max(1, (int)ceil($fp_total / $f_per_page));
if ($fp_page > $fp_pages) { $fp_page = $fp_pages; }
$fp_offset = ($fp_page - 1) * $f_per_page;

$fp_sql = "SELECT st.id, st.name, st.email, st.course, st.semester, st.status, st.profile_image, st.last_login,
    COUNT(r.id) AS exams_taken,
    AVG(r.percentage) AS avg_score,
    SUM(r.correct_answers) AS correct_total,
    SUM(r.total_questions) AS q_total,
    GROUP_CONCAT(DISTINCT s.subject_name SEPARATOR ', ') AS subjects,
    MAX(r.submitted_at) AS last_active,
    (SELECT r2.ai_insight FROM results r2 WHERE r2.student_id=st.id AND r2.ai_insight IS NOT NULL AND TRIM(r2.ai_insight)<>'' ORDER BY r2.id DESC LIMIT 1) AS latest_feedback
    $fp_base_sql GROUP BY st.id $fp_having ORDER BY " . $f_sort_map[$f_sort] . " LIMIT $f_per_page OFFSET $fp_offset";
$fp_rows = array();
$fp_stmt = mysqli_prepare($conn, $fp_sql);
if ($fp_stmt) {
    if ($fp_types !== "") { mysqli_stmt_bind_param($fp_stmt, $fp_types, ...$fp_params); }
    mysqli_stmt_execute($fp_stmt);
    $fp_res = mysqli_stmt_get_result($fp_stmt);
    if ($fp_res) {
        while ($row = mysqli_fetch_assoc($fp_res)) { $fp_rows[] = $row; }
    }
    mysqli_stmt_close($fp_stmt);
}
$fp_from = $fp_total > 0 ? ($fp_offset + 1) : 0;
$fp_to = min($fp_total, $fp_offset + $f_per_page);

function fp_perf_band($avg) {
    if ($avg >= 80) { return array("Strong", "is-completed"); }
    if ($avg >= 60) { return array("Average", "is-upcoming"); }
    return array("Needs Attention", "is-expired");
}
function fp_initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $ini = "";
    foreach (array_slice($parts, 0, 2) as $p) { $ini .= strtoupper(mb_substr($p, 0, 1)); }
    return $ini !== "" ? $ini : "S";
}

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Students</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-people"></i> Learners</span>
                    <h1>Students</h1>
                    <p>View and analyze students assigned to your subjects.</p>
                </div>
                <button type="button" class="sp-btn sp-btn-ghost" data-fp-refresh><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="1">
                    <div class="sp-stat">
                        <span class="sp-stat-icon"><i class="bi bi-people"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_total_students; ?>">0</div>
                        <div class="sp-stat-label">Total Students</div>
                        <div class="sp-stat-foot">In your scope</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="2">
                    <div class="sp-stat is-success">
                        <span class="sp-stat-icon"><i class="bi bi-person-check"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_active_students; ?>">0</div>
                        <div class="sp-stat-label">Active Students</div>
                        <div class="sp-stat-foot">Active accounts</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="3">
                    <div class="sp-stat is-warning">
                        <span class="sp-stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div class="sp-stat-value"><?php echo $fp_avg_perf; ?>%</div>
                        <div class="sp-stat-label">Average Performance</div>
                        <div class="sp-stat-foot"><?php echo $fp_attempts; ?> exams completed</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="4">
                    <div class="sp-stat is-info">
                        <span class="sp-stat-icon"><i class="bi bi-journal-check"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_attempts; ?>">0</div>
                        <div class="sp-stat-label">Exams Completed</div>
                        <div class="sp-stat-foot">Total attempts</div>
                    </div>
                </div>
            </div>

            <form method="get" class="sp-filters sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="fpStuSearch">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" id="fpStuSearch" name="search" value="<?php echo h($f_search); ?>" placeholder="Name, email or ID..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpStuCourse">Course</label>
                        <select name="course" id="fpStuCourse" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($fp_courses as $c) { ?>
                                <option value="<?php echo h($c); ?>" <?php echo $f_course === $c ? "selected" : ""; ?>><?php echo h($c); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpStuSem">Semester</label>
                        <select name="semester" id="fpStuSem" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($fp_semesters as $sm) { ?>
                                <option value="<?php echo h($sm); ?>" <?php echo $f_semester === $sm ? "selected" : ""; ?>><?php echo h($sm); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpStuSubject">Subject</label>
                        <select name="subject_id" id="fpStuSubject" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($fp_subject_opts as $so) { ?>
                                <option value="<?php echo (int)$so["id"]; ?>" <?php echo $f_subject === (int)$so["id"] ? "selected" : ""; ?>><?php echo h($so["subject_name"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpStuPerf">Performance</label>
                        <select name="perf" id="fpStuPerf" class="form-select">
                            <option value="">All Levels</option>
                            <option value="strong" <?php echo $f_perf === "strong" ? "selected" : ""; ?>>Strong (80%+)</option>
                            <option value="average" <?php echo $f_perf === "average" ? "selected" : ""; ?>>Average (60–79%)</option>
                            <option value="needs" <?php echo $f_perf === "needs" ? "selected" : ""; ?>>Needs Attention (&lt;60%)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpStuStatus">Status</label>
                        <select name="status" id="fpStuStatus" class="form-select">
                            <option value="">All</option>
                            <option value="Active" <?php echo $f_status === "Active" ? "selected" : ""; ?>>Active</option>
                            <option value="Inactive" <?php echo $f_status === "Inactive" ? "selected" : ""; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpStuSort">Sort by</label>
                        <select name="sort" id="fpStuSort" class="form-select">
                            <option value="avg_desc" <?php echo $f_sort === "avg_desc" ? "selected" : ""; ?>>Top Average</option>
                            <option value="avg_asc" <?php echo $f_sort === "avg_asc" ? "selected" : ""; ?>>Lowest Average</option>
                            <option value="exams_desc" <?php echo $f_sort === "exams_desc" ? "selected" : ""; ?>>Most Exams</option>
                            <option value="name_asc" <?php echo $f_sort === "name_asc" ? "selected" : ""; ?>>Name (A–Z)</option>
                            <option value="name_desc" <?php echo $f_sort === "name_desc" ? "selected" : ""; ?>>Name (Z–A)</option>
                            <option value="course_asc" <?php echo $f_sort === "course_asc" ? "selected" : ""; ?>>Course</option>
                            <option value="semester_asc" <?php echo $f_sort === "semester_asc" ? "selected" : ""; ?>>Semester</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="sp-btn sp-btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="students.php" class="sp-btn sp-btn-ghost" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-circle"></i></a>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="sp-filter-count">Showing <?php echo $fp_from; ?>–<?php echo $fp_to; ?> of <?php echo $fp_total; ?> students</span>
                </div>
            </form>

            <div class="table-card p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle sp-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>ID</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Subjects</th>
                            <th>Exams</th>
                            <th>Average</th>
                            <th>Performance</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($fp_rows)) { ?>
                            <?php foreach ($fp_rows as $sr) {
                                $sid = (int)$sr["id"];
                                $avg = round((float)($sr["avg_score"] ?? 0), 1);
                                list($band_label, $band_cls) = fp_perf_band($avg);
                                $qt = max((int)($sr["q_total"] ?? 0), 0);
                                $acc = $qt > 0 ? round(((int)($sr["correct_total"] ?? 0) / $qt) * 100, 1) : 0;
                                $photo = "";
                                if (!empty($sr["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/profile/" . $sr["profile_image"])) {
                                    $photo = "../assets/uploads/profile/" . $sr["profile_image"];
                                }
                            ?>
                                <tr>
                                    <td data-label="Student">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($photo !== "") { ?>
                                                <img src="<?php echo h($photo); ?>" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;" alt="">
                                            <?php } else { ?>
                                                <span class="profile-avatar" style="width:36px;height:36px;font-size:0.85rem;"><?php echo h(fp_initials($sr["name"])); ?></span>
                                            <?php } ?>
                                            <div class="min-w-0">
                                                <strong class="d-block text-truncate" style="max-width:170px;"><?php echo h($sr["name"]); ?></strong>
                                                <small class="text-muted d-block text-truncate" style="max-width:170px;"><?php echo h($sr["email"]); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="ID">#<?php echo $sid; ?></td>
                                    <td data-label="Course"><?php echo h($sr["course"] !== "" ? $sr["course"] : "—"); ?></td>
                                    <td data-label="Semester"><?php echo h($sr["semester"] !== "" ? $sr["semester"] : "—"); ?></td>
                                    <td data-label="Subjects"><small class="text-muted"><?php echo h($sr["subjects"] ?? "—"); ?></small></td>
                                    <td data-label="Exams"><strong><?php echo (int)$sr["exams_taken"]; ?></strong></td>
                                    <td data-label="Average"><strong><?php echo $avg; ?>%</strong><br><small class="text-muted"><?php echo $acc; ?>% acc.</small></td>
                                    <td data-label="Performance"><span class="sp-pill <?php echo $band_cls; ?>"><i class="bi bi-<?php echo $band_cls === "is-completed" ? "check2-circle" : ($band_cls === "is-upcoming" ? "dash-circle" : "exclamation-circle"); ?>"></i><?php echo $band_label; ?></span></td>
                                    <td data-label="Status"><span class="sp-pill <?php echo strcasecmp($sr["status"], "Active") === 0 ? "is-completed" : "is-expired"; ?>"><?php echo h($sr["status"]); ?></span></td>
                                    <td class="text-end" data-label="Action">
                                        <button type="button" class="sp-btn sp-btn-soft" data-bs-toggle="modal" data-bs-target="#fpStudentModal<?php echo $sid; ?>"><i class="bi bi-eye"></i> View</button>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="10">
                                    <div class="sp-empty">
                                        <div class="sp-empty-icon"><i class="bi bi-people"></i></div>
                                        <h4>No Students Found</h4>
                                        <p>No students match the current filters. Adjust them or check back after students attempt your exams.</p>
                                        <a href="students.php" class="sp-btn sp-btn-primary"><i class="bi bi-arrow-clockwise"></i> Reset Filters</a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>

                <?php if ($fp_pages > 1) { ?>
                    <nav aria-label="Student pages" class="mt-3">
                        <ul class="pagination justify-content-center flex-wrap gap-1 mb-0">
                            <li class="page-item <?php echo $fp_page <= 1 ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $fp_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h(fp_students_url(array_merge($fp_base_params, array("page" => max(1, $fp_page - 1))))); ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for ($fp_p = 1; $fp_p <= $fp_pages; $fp_p++) { ?>
                                <li class="page-item <?php echo $fp_p === $fp_page ? "active" : ""; ?>">
                                    <a class="page-link" href="<?php echo h(fp_students_url(array_merge($fp_base_params, array("page" => $fp_p)))); ?>"><?php echo $fp_p; ?></a>
                                </li>
                            <?php } ?>
                            <li class="page-item <?php echo $fp_page >= $fp_pages ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $fp_page >= $fp_pages ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h(fp_students_url(array_merge($fp_base_params, array("page" => min($fp_pages, $fp_page + 1))))); ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php } ?>
            </div>

            <?php foreach ($fp_rows as $sr) {
                $sid = (int)$sr["id"];
                $avg = round((float)($sr["avg_score"] ?? 0), 1);
                list($band_label, $band_cls) = fp_perf_band($avg);
                $qt = max((int)($sr["q_total"] ?? 0), 0);
                $acc = $qt > 0 ? round(((int)($sr["correct_total"] ?? 0) / $qt) * 100, 1) : 0;
                // Recent attempts (authorized scope only)
                $fp_att = array();
                $fp_aq = mysqli_query($conn, "SELECT r.percentage, r.status, r.submitted_at, e.exam_title, s.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id WHERE r.student_id=$sid AND e.subject_id IN ($scope_csv) ORDER BY r.id DESC LIMIT 5");
                if ($fp_aq) { while ($ar = mysqli_fetch_assoc($fp_aq)) { $fp_att[] = $ar; } }
                // Score trend (last 6, oldest first)
                $fp_trend = array();
                $fp_tq = mysqli_query($conn, "SELECT r.percentage FROM results r LEFT JOIN exams e ON r.exam_id=e.id WHERE r.student_id=$sid AND e.subject_id IN ($scope_csv) ORDER BY r.id DESC LIMIT 6");
                if ($fp_tq) { while ($tr = mysqli_fetch_assoc($fp_tq)) { array_unshift($fp_trend, round((float)$tr["percentage"], 1)); } }
            ?>
            <div class="modal fade" id="fpStudentModal<?php echo $sid; ?>" tabindex="-1" aria-hidden="true" aria-labelledby="fpStudentModalLabel<?php echo $sid; ?>">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content professional-modal">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="fpStudentModalLabel<?php echo $sid; ?>"><i class="bi bi-person-badge me-2 text-primary"></i><?php echo h($sr["name"]); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-envelope"></i>Email</div><span class="sp-info-value"><?php echo h($sr["email"]); ?></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-mortarboard"></i>Program</div><span class="sp-info-value"><?php echo h($sr["course"] !== "" ? $sr["course"] : "Not set"); ?> &middot; <?php echo h($sr["semester"] !== "" ? $sr["semester"] : "Not set"); ?></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-book"></i>Your Subjects</div><span class="sp-info-value"><?php echo h($sr["subjects"] ?? "—"); ?></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-clock-history"></i>Last Active</div><span class="sp-info-value"><?php echo !empty($sr["last_active"]) ? date("d M Y", strtotime($sr["last_active"])) : "—"; ?></span></div>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6 col-md-3"><div class="sp-info-item text-center"><div class="sp-info-label justify-content-center">Exams</div><span class="sp-info-value"><?php echo (int)$sr["exams_taken"]; ?></span></div></div>
                                <div class="col-6 col-md-3"><div class="sp-info-item text-center"><div class="sp-info-label justify-content-center">Average</div><span class="sp-info-value"><?php echo $avg; ?>%</span></div></div>
                                <div class="col-6 col-md-3"><div class="sp-info-item text-center"><div class="sp-info-label justify-content-center">Accuracy</div><span class="sp-info-value"><?php echo $acc; ?>%</span></div></div>
                                <div class="col-6 col-md-3"><div class="sp-info-item text-center"><div class="sp-info-label justify-content-center">Level</div><span class="sp-pill <?php echo $band_cls; ?>"><?php echo $band_label; ?></span></div></div>
                            </div>
                            <?php if (!empty($fp_trend)) { ?>
                                <h6 class="fw-bold mb-2"><i class="bi bi-graph-up me-1 text-primary"></i>Recent Trend</h6>
                                <div class="d-flex align-items-end gap-1 mb-3" style="height:64px;" role="img" aria-label="Recent score trend">
                                    <?php foreach ($fp_trend as $tp) { ?>
                                        <div class="flex-fill rounded-top" title="<?php echo $tp; ?>%" style="height:<?php echo max(6, (int)$tp); ?>%;background:linear-gradient(180deg,#4F46E5,#2563EB);min-height:6px;"></div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1 text-primary"></i>Recent Attempts (Your Subjects)</h6>
                            <?php if (!empty($fp_att)) { ?>
                                <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Exam</th><th>Subject</th><th>Score</th><th>Status</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($fp_att as $ar) { ?>
                                            <tr>
                                                <td><?php echo h($ar["exam_title"] ?? "Exam"); ?></td>
                                                <td><small class="text-muted"><?php echo h($ar["subject_name"] ?? ""); ?></small></td>
                                                <td><strong><?php echo number_format((float)$ar["percentage"], 1); ?>%</strong></td>
                                                <td><span class="sp-pill <?php echo $ar["status"] === "Pass" ? "is-completed" : "is-expired"; ?>"><?php echo h($ar["status"]); ?></span></td>
                                                <td><small class="text-muted"><?php echo date("d M Y", strtotime($ar["submitted_at"])); ?></small></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted small mb-0">No attempts recorded in your subjects.</p>
                            <?php } ?>
                            <?php if (!empty($sr["latest_feedback"])) { ?>
                                <h6 class="fw-bold mt-3 mb-2"><i class="bi bi-robot me-1 text-primary"></i>Latest AI Feedback</h6>
                                <div class="alert alert-info small mb-0"><?php echo h(mb_substr($sr["latest_feedback"], 0, 600)); ?></div>
                            <?php } ?>
                        </div>
                        <div class="modal-footer">
                            <a class="sp-btn sp-btn-soft" href="results.php?search=<?php echo urlencode($sr["email"]); ?>"><i class="bi bi-trophy"></i> View Results</a>
                            <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

        </div>
    </div>
</div>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>
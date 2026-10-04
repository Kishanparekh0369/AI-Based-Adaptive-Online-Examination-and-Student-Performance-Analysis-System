<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Student Performance Analysis";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

// Optional single-subject scope (e.g. from subject cards). Validated against
// the faculty's own assignments; unauthorized ids fall back to full scope.
$perf_filter_sub = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;
$perf_filter_name = "";
$perf_scope_csv = $subject_ids_csv;
if ($perf_filter_sub > 0 && faculty_has_subject($faculty_id, $perf_filter_sub)) {
    $perf_scope_csv = (string)$perf_filter_sub;
    $perf_name_res = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id=$perf_filter_sub LIMIT 1");
    if ($perf_name_res && $perf_name_row = mysqli_fetch_assoc($perf_name_res)) {
        $perf_filter_name = (string)$perf_name_row["subject_name"];
    }
} else {
    $perf_filter_sub = 0;
}
$perf_scope_label = $perf_filter_sub > 0 ? $perf_filter_name : "Assigned subjects only";

// Academic scope filters (validated; constrained to faculty scope)
$perf_course = isset($_GET["course"]) ? trim($_GET["course"]) : "";
if ($perf_course !== "" && !academic_valid_course($perf_course)) { $perf_course = ""; }
$perf_semester = isset($_GET["semester"]) ? trim($_GET["semester"]) : "";
if ($perf_semester !== "" && !academic_valid_semester($perf_semester)) { $perf_semester = ""; }
$perf_acad_scope = "";
if ($perf_course !== "") { $perf_acad_scope .= " AND st.course='" . mysqli_real_escape_string($conn, $perf_course) . "' "; }
if ($perf_semester !== "") { $perf_acad_scope .= " AND st.semester='" . mysqli_real_escape_string($conn, $perf_semester) . "' "; }

function faculty_student_profile_avatar($profile_image, $class_name) {
    $profile_image = trim((string)$profile_image);
    if ($profile_image !== "" && file_exists("../assets/uploads/profile/" . $profile_image)) {
        return '<img src="../assets/uploads/profile/' . h($profile_image) . '" class="' . h($class_name) . '" style="object-fit:cover" alt="Student profile photo">';
    }
    return '<span class="' . h($class_name) . '"><i class="bi bi-person-fill"></i></span>';
}

$search = isset($_GET["search"]) ? clean_data($_GET["search"]) : "";
$search_s = mysqli_real_escape_string($conn, $search);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Summary statistics for assigned subjects (+ academic scope)
$summary_result = mysqli_query($conn, "SELECT COUNT(r.id) AS total_results, AVG(r.percentage) AS avgp, MAX(r.percentage) AS highest, MIN(r.percentage) AS lowest,
    SUM(CASE WHEN r.status='Pass' THEN 1 ELSE 0 END) AS pass_count,
    SUM(CASE WHEN r.status='Fail' THEN 1 ELSE 0 END) AS fail_count,
    SUM(r.correct_answers) AS correct_total,
    SUM(r.wrong_answers) AS wrong_total
    FROM results r
    INNER JOIN exams e ON r.exam_id=e.id
    INNER JOIN students st ON r.student_id=st.id
    WHERE e.subject_id IN ($perf_scope_csv)$perf_acad_scope");
$summary = $summary_result ? mysqli_fetch_assoc($summary_result) : array();

$students_cnt = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS total FROM results r INNER JOIN exams e ON r.exam_id=e.id INNER JOIN students st ON r.student_id=st.id WHERE e.subject_id IN ($perf_scope_csv)$perf_acad_scope"))['total'] ?? 0;
$exams_cnt = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM exams WHERE subject_id IN ($perf_scope_csv)"))['total'] ?? 0;

// Subject wise chart data (assigned subjects + academic scope)
$subject = mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp
    FROM subjects s
    LEFT JOIN exams e ON e.subject_id=s.id
    LEFT JOIN results r ON r.exam_id=e.id
    LEFT JOIN students st ON r.student_id=st.id" . (($perf_course !== "" || $perf_semester !== "") ? $perf_acad_scope : "") . "
    WHERE s.id IN ($perf_scope_csv)
    GROUP BY s.id, s.subject_name
    ORDER BY s.subject_name ASC");
$subject_labels = ""; 
$subject_values = "";
if ($subject) {
    while ($row = mysqli_fetch_assoc($subject)) { 
        $subject_labels .= "'" . addslashes($row["subject_name"]) . "',"; 
        $subject_values .= round((float)($row["avgp"] ?? 0), 2) . ","; 
    }
}
if ($subject_labels == "") { $subject_labels = "'No Data'"; $subject_values = "0"; }

$pass_count = (int)($summary["pass_count"] ?? 0);
$fail_count = (int)($summary["fail_count"] ?? 0);

// Student list query (restricted to results in assigned subjects)
$students_query_sql = "SELECT LOWER(TRIM(st.name)) AS name_key, TRIM(st.name) AS display_name, 
    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(st.profile_image, '') ORDER BY st.id SEPARATOR '||'), '||', 1) AS profile_image, 
    GROUP_CONCAT(DISTINCT st.email SEPARATOR ', ') AS emails, 
    GROUP_CONCAT(DISTINCT st.course SEPARATOR ', ') AS courses, 
    GROUP_CONCAT(DISTINCT s.subject_name SEPARATOR ', ') AS subjects, 
    COUNT(DISTINCT st.id) AS account_count, 
    COUNT(r.id) AS exams_taken, 
    AVG(r.percentage) AS avg_percentage, 
    SUM(r.correct_answers) AS correct_total, 
    SUM(r.wrong_answers) AS wrong_total, 
    MAX(r.percentage) AS highest_score, 
    MIN(r.percentage) AS lowest_score 
    FROM students st 
    INNER JOIN results r ON r.student_id=st.id 
    INNER JOIN exams e ON r.exam_id=e.id 
    INNER JOIN subjects s ON e.subject_id=s.id
    WHERE e.subject_id IN ($perf_scope_csv)$perf_acad_scope ";

if ($search !== "") {
    $students_query_sql .= " AND (st.name LIKE '%$search_s%' OR st.email LIKE '%$search_s%' OR st.course LIKE '%$search_s%' OR s.subject_name LIKE '%$search_s%') ";
}

$students_query_sql .= " GROUP BY name_key, display_name ORDER BY avg_percentage DESC";

// Count total for pagination
$count_sql = "SELECT COUNT(*) AS c FROM ($students_query_sql) AS t";
$total_students_filtered = (int)mysqli_fetch_assoc(mysqli_query($conn, $count_sql))['c'];
$total_pages = max(1, ceil($total_students_filtered / $per_page));

// Add pagination
$students_query_sql .= " LIMIT $per_page OFFSET $offset";
$result = mysqli_query($conn, $students_query_sql);

// URL builder
function build_perf_url($page, $params = []) {
    $defaults = ['search' => ''];
    $params = array_merge($defaults, $params, ['page' => $page]);
    return "performance.php?" . http_build_query(array_filter($params));
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
                    <li class="breadcrumb-item active">Performance Analysis</li>
                </ol>
            </nav>

            <h2 class="fw-bold mb-1">Student Performance Analysis</h2>
            <p class="text-muted small mb-4">Detailed student analytics and performance breakdown strictly for your assigned subjects</p>

            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <?php
                $stats = array(
                    array("Overall Average", number_format((float)($summary["avgp"] ?? 0), 1) . "%", "bi-percent", "dash-tone-blue"),
                    array("Highest Score", number_format((float)($summary["highest"] ?? 0), 1) . "%", "bi-trophy", "dash-tone-warning"),
                    array("Lowest Score", number_format((float)($summary["lowest"] ?? 0), 1) . "%", "bi-graph-down", "dash-tone-secondary"),
                    array("Assessed Students", $students_cnt, "bi-people", "dash-tone-success"),
                    array("Total Exams", $exams_cnt, "bi-calendar-check", "dash-tone-info"),
                    array("Completed Attempts", $summary["total_results"] ?? 0, "bi-check2-circle", "dash-tone-danger")
                );
                $si = 0;
                foreach ($stats as $stat) { $si++; ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min($si, 6); ?>">
                        <div class="dash-stat <?php echo $stat[3]; ?>">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $stat[2]; ?>"></i></span>
                            </div>
                            <div class="dash-stat-value"><?php echo $stat[1]; ?></div>
                            <p class="dash-stat-label"><?php echo $stat[0]; ?></p>
                            <p class="dash-stat-sub"><?php echo h($perf_scope_label); ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Performance Charts -->
            <div class="row g-4 mb-4">
                <div class="col-xl-7 sp-fade-up">
                    <div class="dashboard-panel h-100 sp-chart-wrapper">
                        <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Assigned Subject Averages</h5>
                        <div class="chart-container chart-container-md"><canvas id="facultySubjectPerformance" role="img" aria-label="Assigned subject averages bar chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-5 sp-fade-up">
                    <div class="dashboard-panel h-100 sp-chart-wrapper">
                        <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Pass / Fail Split</h5>
                        <div class="chart-container chart-container-doughnut"><canvas id="facultyPassFail" role="img" aria-label="Pass fail split doughnut chart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="sp-filters sp-fade-up">
                <form method="get" id="performanceSearchForm" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="1">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1" for="perfSearchInput">Search Students</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" id="perfSearchInput" value="<?php echo h($search); ?>" class="form-control" placeholder="Name, email, course..." aria-label="Search students">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1" for="perfCourseSelect">Course</label>
                        <select name="course" id="perfCourseSelect" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach (academic_course_options() as $c_opt) { ?>
                                <option value="<?php echo h($c_opt); ?>" <?php echo $perf_course === $c_opt ? "selected" : ""; ?>><?php echo h($c_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1" for="perfSemSelect">Semester</label>
                        <select name="semester" id="perfSemSelect" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach (academic_semester_options() as $s_opt) { ?>
                                <option value="<?php echo h($s_opt); ?>" <?php echo $perf_semester === $s_opt ? "selected" : ""; ?>><?php echo h($s_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1" for="perfSubjectSelect">Subject Scope</label>
                        <select name="subject_id" id="perfSubjectSelect" class="form-select">
                            <option value="">All Assigned Subjects</option>
                            <?php foreach (get_faculty_assigned_subjects($faculty_id) as $perf_sub) { ?>
                                <option value="<?php echo (int)$perf_sub["id"]; ?>" <?php echo $perf_filter_sub === (int)$perf_sub["id"] ? "selected" : ""; ?>><?php echo h($perf_sub["subject_name"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="submit" class="sp-btn sp-btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                    </div>
                    <div class="col-6 col-md-1">
                        <button type="button" class="sp-btn sp-btn-ghost w-100" data-fp-refresh aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>
                </form>
                <?php if ($perf_filter_sub > 0) { ?>
                    <div class="mt-2">
                        <span class="sp-pill is-available"><i class="bi bi-book"></i>Filtered: <?php echo h($perf_filter_name); ?></span>
                        <a href="performance.php<?php echo $search !== "" ? "?search=" . urlencode($search) : ""; ?>" class="small text-decoration-none ms-2"><i class="bi bi-x-circle me-1"></i>Clear subject filter</a>
                    </div>
                <?php } ?>
            </div>

            <!-- Student Performance Cards Grid -->
            <div class="row g-3" id="performanceCards">
                <?php if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $avg = round((float)($row["avg_percentage"] ?? 0), 1);
                        $grade = $avg >= 90 ? "A+" : ($avg >= 75 ? "A" : ($avg >= 60 ? "B" : ($avg >= 40 ? "C" : "F")));
                        $gradeColor = $grade == "F" ? "#dc2626" : ($grade == "C" ? "#d97706" : "#059669");
                        $highest = $row["highest_score"] ?? 0;
                        $lowest = $row["lowest_score"] ?? 0;
                        $emails = h($row["emails"]);
                        $searchText = strtolower(trim(($row["display_name"] ?? "") . " " . ($row["emails"] ?? "") . " " . ($row["courses"] ?? "") . " " . ($row["subjects"] ?? "")));
                        $accountCount = (int)$row["account_count"];
                        $nameKey = preg_replace('/[^a-z0-9]/', '', $row["name_key"]);
                        $modalId = "perfModal_" . $nameKey;
                    ?>
                    <div class="col-xl-6 sp-fade-up" data-search="<?php echo h($searchText); ?>">
                        <div class="sp-perf-card sp-card-hover" onclick="new bootstrap.Modal(document.getElementById('<?php echo $modalId; ?>')).show()" style="cursor:pointer;">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <?php echo faculty_student_profile_avatar($row["profile_image"] ?? "", "perf-avatar"); ?>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h5 class="fw-bold mb-0"><?php echo h($row["display_name"]); ?></h5>
                                        <?php if ($accountCount > 1): ?><span class="mult-acct"><i class="bi bi-layers me-1"></i><?php echo $accountCount; ?> accounts</span><?php endif; ?>
                                        <span class="grade-badge" style="background:<?php echo $gradeColor; ?>15;color:<?php echo $gradeColor; ?>;margin-left:auto"><?php echo $grade; ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo $emails; ?></small>
                                </div>
                            </div>
                            <div class="row g-1 mb-2">
                                <div class="col-3"><div class="mini-stat"><small>Average</small><strong class="perf-avg"><?php echo number_format($avg, 1); ?>%</strong></div></div>
                                <div class="col-3"><div class="mini-stat"><small>Attempts</small><strong><?php echo h($row["exams_taken"]); ?></strong></div></div>
                                <div class="col-3"><div class="mini-stat"><small>Highest</small><strong><?php echo number_format((float)$highest, 1); ?>%</strong></div></div>
                                <div class="col-3"><div class="mini-stat"><small>Lowest</small><strong><?php echo number_format((float)$lowest, 1); ?>%</strong></div></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <small class="text-muted"><i class="bi bi-book me-1"></i><?php echo h($row["subjects"]); ?></small>
                                <span class="small text-primary fw-bold">View Breakdown <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Breakdown Modal -->
                    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content professional-modal">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-mortarboard me-2"></i>Performance: <?php echo h($row["display_name"]); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded-3">
                                        <?php echo faculty_student_profile_avatar($row["profile_image"] ?? "", "perf-avatar"); ?>
                                        <div>
                                            <h5 class="fw-bold mb-0"><?php echo h($row["display_name"]); ?></h5>
                                            <small class="text-muted"><?php echo $emails; ?> &bull; <?php echo h($row["courses"]); ?></small>
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-2">Exams in Your Assigned Subjects:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle sp-table">
                                            <thead>
                                                <tr>
                                                    <th>Exam</th>
                                                    <th>Subject</th>
                                                    <th>Score</th>
                                                    <th>Result</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $student_name_safe = mysqli_real_escape_string($conn, $row["display_name"]);
                                                $drill_sql = "SELECT r.*, e.exam_title, e.total_marks AS exam_total_marks, s.subject_name 
                                                    FROM results r 
                                                    INNER JOIN exams e ON r.exam_id=e.id 
                                                    INNER JOIN subjects s ON e.subject_id=s.id 
                                                    INNER JOIN students st ON r.student_id=st.id 
                                                    WHERE LOWER(TRIM(st.name))='" . mysqli_real_escape_string($conn, $row["name_key"]) . "'
                                                    AND e.subject_id IN ($perf_scope_csv)$perf_acad_scope
                                                    ORDER BY r.submitted_at DESC";
                                                $drill_res = mysqli_query($conn, $drill_sql);
                                                if ($drill_res && mysqli_num_rows($drill_res) > 0) {
                                                    while ($dr = mysqli_fetch_assoc($drill_res)) {
                                                        $score_obtained = (float)($dr["marks_obtained"] ?? $dr["correct_answers"] ?? 0);
                                                        $total_marks_val = (float)($dr["exam_total_marks"] ?? $dr["total_questions"] ?? 0);
                                                        if ($total_marks_val <= 0) {
                                                            $total_marks_val = max(1.0, (float)($dr["total_questions"] ?? $score_obtained));
                                                        }
                                                        $calc_pct = isset($dr["percentage"]) && is_numeric($dr["percentage"]) && (float)$dr["percentage"] > 0
                                                            ? (float)$dr["percentage"]
                                                            : round(($score_obtained / $total_marks_val) * 100, 1);
                                                        ?>
                                                        <tr>
                                                            <td><strong><?php echo h($dr["exam_title"]); ?></strong></td>
                                                            <td><span class="badge bg-primary-soft text-primary"><?php echo h($dr["subject_name"]); ?></span></td>
                                                            <td>
                                                                <div class="sp-score-ring" style="--p:<?php echo (int)round($calc_pct); ?>;--c:<?php echo $calc_pct >= 40 ? '#22C55E' : '#EF4444'; ?>;width:40px;height:40px;">
                                                                    <div class="sp-score-ring-inner"><strong><?php echo number_format($calc_pct, 1); ?>%</strong></div>
                                                                </div>
                                                            </td>
                                                            <td><span class="badge bg-<?php echo $dr["status"] == "Pass" ? "success" : "danger"; ?>"><?php echo h($dr["status"]); ?></span></td>
                                                            <td><small class="text-muted"><?php echo date("M j, Y", strtotime($dr["submitted_at"])); ?></small></td>
                                                        </tr>
                                                    <?php }
                                                } else { ?>
                                                    <tr><td colspan="5" class="text-center text-muted py-4">No exams recorded for this student in your subjects.</td></tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php }
                } else { ?>
                    <div class="col-12">
                        <div class="dashboard-panel text-center py-5 sp-empty">
                            <i class="bi bi-graph-down fs-1 text-muted d-block mb-2"></i>
                            <h5>No Student Performance Data</h5>
                            <p class="text-muted mb-0">No exam attempts found for your assigned subjects.</p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
                <nav class="sp-pagination mt-4" aria-label="Performance pagination">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1) $start = max(1, $end - 4);
                        if ($end < $total_pages) $end = min($total_pages, $start + 4);
                        $perf_page_params = array('search' => $search);
                        if ($perf_filter_sub > 0) { $perf_page_params['subject_id'] = $perf_filter_sub; }
                        if ($perf_course !== '') { $perf_page_params['course'] = $perf_course; }
                        if ($perf_semester !== '') { $perf_page_params['semester'] = $perf_semester; }

                        if ($page > 1) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo build_perf_url($page - 1, $perf_page_params); ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>
                        <?php }
                        for ($i = $start; $i <= $end; $i++) { ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo build_perf_url($i, $perf_page_params); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php }
                        if ($page < $total_pages) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo build_perf_url($page + 1, $perf_page_params); ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>
                        <?php } ?>
                    </ul>
                </nav>
            <?php } ?>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Subject chart
    if (typeof ChartManager !== "undefined") {
        ChartManager.create('facultySubjectPerformance', {
            type: 'bar',
            data: {
                labels: [<?php echo $subject_labels; ?>],
                datasets: [{
                    label: 'Avg Score (%)',
                    data: [<?php echo $subject_values; ?>],
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { callback: function(v) { return v + "%"; } } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Pass / Fail Split
    if (typeof ChartManager !== "undefined") {
        ChartManager.create('facultyPassFail', {
            type: 'doughnut',
            data: {
                labels: ['Pass', 'Fail'],
                datasets: [{
                    data: [<?php echo $pass_count; ?>, <?php echo $fail_count; ?>],
                    backgroundColor: ['#22C55E', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } } },
                cutout: '65%'
            }
        });
    }

    // Client-side live filter for performance cards
    const searchInput = document.querySelector('[name="search"]');
    const grid = document.getElementById('performanceCards');
    if (searchInput && grid) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const term = this.value.toLowerCase();
                const cards = grid.querySelectorAll('[data-search]');
                let visibleCount = 0;
                cards.forEach(card => {
                    const text = card.getAttribute('data-search');
                    const show = text.indexOf(term) >= 0;
                    card.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });
                const emptyState = grid.querySelector('.sp-empty');
                if (emptyState) emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }, 150);
        });
    }
});
</script>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>
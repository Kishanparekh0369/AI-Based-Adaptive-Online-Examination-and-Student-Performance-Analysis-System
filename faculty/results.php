<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Subject Results";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

function faculty_res_avatar($profile_image, $class_name) {
    $profile_image = trim((string)$profile_image);
    if ($profile_image !== "" && file_exists("../assets/uploads/profile/" . $profile_image)) {
        return '<img src="../assets/uploads/profile/' . h($profile_image) . '" class="' . h($class_name) . '" style="object-fit:cover" alt="Student profile">';
    }
    return '<span class="' . h($class_name) . '"><i class="bi bi-person-fill"></i></span>';
}

$search = isset($_GET["search"]) ? clean_data($_GET["search"]) : "";
$search_s = mysqli_real_escape_string($conn, $search);
$res_course = isset($_GET["course"]) ? trim($_GET["course"]) : "";
if ($res_course !== "" && !academic_valid_course($res_course)) { $res_course = ""; }
$res_semester = isset($_GET["semester"]) ? trim($_GET["semester"]) : "";
if ($res_semester !== "" && !academic_valid_semester($res_semester)) { $res_semester = ""; }
$res_acad_scope = "";
if ($res_course !== "") { $res_acad_scope .= " AND st.course='" . mysqli_real_escape_string($conn, $res_course) . "' "; }
if ($res_semester !== "") { $res_acad_scope .= " AND st.semester='" . mysqli_real_escape_string($conn, $res_semester) . "' "; }

// Optional exam scope (validated against assigned subjects only)
$filter_exam = isset($_GET["exam_id"]) ? (int)$_GET["exam_id"] : 0;
$filter_exam_name = "";
$res_exam_scope = "";
if ($filter_exam > 0) {
    $ev_check = mysqli_query($conn, "SELECT e.exam_title FROM exams e WHERE e.id=$filter_exam AND e.subject_id IN ($subject_ids_csv) LIMIT 1");
    if ($ev_check && $ev_row = mysqli_fetch_assoc($ev_check)) {
        $filter_exam_name = (string)$ev_row["exam_title"];
        $res_exam_scope = " AND r.exam_id=$filter_exam ";
    } else {
        $filter_exam = 0;
    }
}
$res_exam_list = mysqli_query($conn, "SELECT id, exam_title FROM exams WHERE subject_id IN ($subject_ids_csv) ORDER BY exam_title ASC");

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Summary metrics (respect exam + academic scope when set)
$total_students = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT st.id) AS c FROM students st INNER JOIN results r ON r.student_id=st.id INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv)$res_exam_scope$res_acad_scope"))['c'];
$total_attempts = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(r.id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id INNER JOIN students st ON r.student_id=st.id WHERE e.subject_id IN ($subject_ids_csv)$res_exam_scope$res_acad_scope"))['c'];
$avg_score = round((float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(r.percentage) AS a FROM results r INNER JOIN exams e ON r.exam_id=e.id INNER JOIN students st ON r.student_id=st.id WHERE e.subject_id IN ($subject_ids_csv)$res_exam_scope$res_acad_scope"))['a'] ?? 0, 1);
$pass_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(r.id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id INNER JOIN students st ON r.student_id=st.id WHERE e.subject_id IN ($subject_ids_csv)$res_exam_scope$res_acad_scope AND r.status='Pass'"))['c'];
$pass_rate = $total_attempts > 0 ? round(($pass_count / $total_attempts) * 100, 1) : 0;

// Build students query with grouping
$students_query_sql = "SELECT LOWER(TRIM(st.name)) AS name_key, TRIM(st.name) AS display_name, 
    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(st.profile_image, '') ORDER BY st.id SEPARATOR '||'), '||', 1) AS profile_image, 
    GROUP_CONCAT(DISTINCT st.email SEPARATOR ', ') AS emails, 
    GROUP_CONCAT(DISTINCT st.id) AS student_ids, 
    COUNT(DISTINCT st.id) AS account_count, 
    COUNT(r.id) AS total_exams, 
    AVG(r.percentage) AS avgp, 
    SUM(CASE WHEN r.status='Pass' THEN 1 ELSE 0 END) AS pass_count, 
    MAX(r.submitted_at) AS last_exam 
    FROM students st 
    INNER JOIN results r ON r.student_id=st.id 
    INNER JOIN exams e ON r.exam_id=e.id
    WHERE e.subject_id IN ($subject_ids_csv)$res_exam_scope$res_acad_scope ";

if ($search !== "") {
    $students_query_sql .= " AND (st.name LIKE '%$search_s%' OR st.email LIKE '%$search_s%' OR st.course LIKE '%$search_s%') ";
}

$students_query_sql .= " GROUP BY name_key, display_name ORDER BY avgp DESC";

// Count total for pagination
$count_sql = "SELECT COUNT(*) AS c FROM ($students_query_sql) AS t";
$total_students_filtered = (int)mysqli_fetch_assoc(mysqli_query($conn, $count_sql))['c'];
$total_pages = max(1, ceil($total_students_filtered / $per_page));

// Add pagination
$students_query_sql .= " LIMIT $per_page OFFSET $offset";
$students = mysqli_query($conn, $students_query_sql);

// URL builder
function build_results_url($page, $params = []) {
    $defaults = ['search' => ''];
    $params = array_merge($defaults, $params, ['page' => $page]);
    return "results.php?" . http_build_query(array_filter($params));
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
                    <li class="breadcrumb-item active">Results</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 sp-fade-up">
                <div>
                    <h2 class="fw-bold mb-1">Student Result Cards</h2>
                    <p class="text-muted small mb-0">Individual student performance across your assigned subjects</p>
                </div>
                <a href="results_export.php" class="btn btn-outline-danger sp-fade-up" data-delay="1">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                </a>
            </div>

            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <?php
                $stat_cards = [
                    ['Assessed Students', $total_students, 'bi-people', 'dash-tone-blue', 'With exam attempts'],
                    ['Total Attempts', $total_attempts, 'bi-calendar-check', 'dash-tone-cyan', 'All submissions'],
                    ['Average Score', $avg_score . '%', 'bi-graph-up', 'dash-tone-violet', 'Mean percentage'],
                    ['Pass Rate', $pass_rate . '%', 'bi-award', 'dash-tone-rose', "$pass_count passed of $total_attempts"]
                ];
                $si = 0;
                foreach ($stat_cards as $sc) { $si++; ?>
                    <div class="col-6 col-md-3 sp-fade-up" data-delay="<?php echo min($si, 4); ?>">
                        <div class="dash-stat <?php echo $sc[3]; ?>">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $sc[2]; ?>"></i></span>
                            </div>
                            <div class="dash-stat-value"><?php echo h($sc[1]); ?></div>
                            <p class="dash-stat-label"><?php echo h($sc[0]); ?></p>
                            <p class="dash-stat-sub"><?php echo h($sc[4]); ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Search -->
            <div class="sp-filters sp-fade-up">
                <form method="get" id="resultsSearchForm" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="1">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1" for="resSearchInput">Search Students</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" id="resSearchInput" value="<?php echo h($search); ?>" class="form-control" placeholder="Name, email, or course..." aria-label="Search students">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1" for="resCourseSelect">Course</label>
                        <select name="course" id="resCourseSelect" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach (academic_course_options() as $c_opt) { ?>
                                <option value="<?php echo h($c_opt); ?>" <?php echo $res_course === $c_opt ? "selected" : ""; ?>><?php echo h($c_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1" for="resSemSelect">Semester</label>
                        <select name="semester" id="resSemSelect" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach (academic_semester_options() as $s_opt) { ?>
                                <option value="<?php echo h($s_opt); ?>" <?php echo $res_semester === $s_opt ? "selected" : ""; ?>><?php echo h($s_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1" for="resExamSelect">Exam</label>
                        <select name="exam_id" id="resExamSelect" class="form-select">
                            <option value="">All Exams</option>
                            <?php if ($res_exam_list) { while ($ex_opt = mysqli_fetch_assoc($res_exam_list)) { ?>
                                <option value="<?php echo (int)$ex_opt["id"]; ?>" <?php echo $filter_exam === (int)$ex_opt["id"] ? "selected" : ""; ?>><?php echo h($ex_opt["exam_title"]); ?></option>
                            <?php } } ?>
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
                <?php if ($filter_exam > 0) { ?>
                    <div class="mt-2">
                        <span class="sp-pill is-available"><i class="bi bi-journal-check"></i>Exam: <?php echo h($filter_exam_name); ?></span>
                        <a href="results.php<?php echo $search !== "" ? "?search=" . urlencode($search) : ""; ?>" class="small text-decoration-none ms-2"><i class="bi bi-x-circle me-1"></i>Clear exam filter</a>
                    </div>
                <?php } ?>
            </div>

            <!-- Student Result Cards Grid -->
            <div class="row g-3" id="resultsGrid">
                <?php if ($students && mysqli_num_rows($students) > 0) {
                    while ($student = mysqli_fetch_assoc($students)) {
                        $pass_percent = $student["total_exams"] > 0 ? round(($student["pass_count"] / $student["total_exams"]) * 100, 1) : 0;
                        $avgp = $student["avgp"] ? number_format((float)$student["avgp"], 1) : "0.0";
                        $emails = h($student["emails"]);
                        $nameKey = preg_replace('/[^a-z0-9]/', '', $student["name_key"]);
                        $modalId = "histModal_" . $nameKey;
                        $ids = $student["student_ids"];
                        $best_subject = "N/A";
                        $best_score = "0.0";

                        if ($ids) {
                            $best_q = mysqli_query($conn, "SELECT s.subject_name, MAX(r.percentage) AS best_score 
                                FROM results r 
                                INNER JOIN exams e ON r.exam_id=e.id 
                                INNER JOIN subjects s ON e.subject_id=s.id 
                                WHERE r.student_id IN ($ids) AND e.subject_id IN ($subject_ids_csv) 
                                GROUP BY s.id, s.subject_name 
                                ORDER BY best_score DESC LIMIT 1");
                            if ($best_q && $best_row = mysqli_fetch_assoc($best_q)) {
                                $best_subject = $best_row["subject_name"] ?: "N/A";
                                $best_score = number_format((float)$best_row["best_score"], 1);
                            }
                        }
                    ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up" data-search="<?php echo strtolower(h($student["display_name"] . ' ' . $emails)); ?>">
                        <div class="student-card result-profile-card h-100 sp-card-hover">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <?php echo faculty_res_avatar($student["profile_image"] ?? "", "student-card-avatar"); ?>
                                <div class="min-w-0">
                                    <h5 class="mb-0 fw-bold"><?php echo h($student["display_name"]); ?></h5>
                                    <p class="mb-0 text-muted small text-truncate" style="max-width:200px;"><?php echo $emails; ?></p>
                                </div>
                            </div>

                            <div class="result-profile-metrics mb-3">
                                <div class="metric-chip">
                                    <span>Average</span>
                                    <strong class="sp-score"><?php echo $avgp; ?>%</strong>
                                </div>
                                <div class="metric-chip">
                                    <span>Pass Rate</span>
                                    <strong><?php echo $pass_percent; ?>%</strong>
                                </div>
                                <div class="metric-chip">
                                    <span>Attempts</span>
                                    <strong><?php echo $student["total_exams"]; ?></strong>
                                </div>
                            </div>

                            <div class="result-profile-best p-2 bg-light rounded-3 mb-3 small">
                                <span class="text-muted">Top Subject:</span>
                                <strong><?php echo h($best_subject); ?> (<?php echo $best_score; ?>%)</strong>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <button type="button" class="btn btn-sm btn-gradient" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                    <i class="bi bi-clock-history me-1"></i>Exam History
                                </button>
                                <a href="results_export.php?student_id=<?php echo $ids; ?>" class="btn btn-sm btn-outline-danger" title="Export PDF">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Exam History Modal -->
                    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content professional-modal">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-award me-2"></i>Results History: <?php echo h($student["display_name"]); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle sp-table">
                                            <thead>
                                                <tr>
                                                    <th>Exam Title</th>
                                                    <th>Subject</th>
                                                    <th>Mode</th>
                                                    <th>Score</th>
                                                    <th>Percentage</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $hist_sql = "SELECT r.*, e.exam_title, e.exam_mode, e.total_marks AS exam_total_marks, s.subject_name 
                                                    FROM results r 
                                                    INNER JOIN exams e ON r.exam_id=e.id 
                                                    INNER JOIN subjects s ON e.subject_id=s.id 
                                                    WHERE r.student_id IN ($ids) AND e.subject_id IN ($subject_ids_csv) 
                                                    ORDER BY r.submitted_at DESC";
                                                $hist_res = mysqli_query($conn, $hist_sql);
                                                if ($hist_res && mysqli_num_rows($hist_res) > 0) {
                                                    while ($hr = mysqli_fetch_assoc($hist_res)) {
                                                        $score_obtained = (float)($hr["marks_obtained"] ?? $hr["correct_answers"] ?? 0);
                                                        $total_marks_val = (float)($hr["exam_total_marks"] ?? $hr["total_questions"] ?? 0);
                                                        if ($total_marks_val <= 0) {
                                                            $total_marks_val = max(1.0, (float)($hr["total_questions"] ?? $score_obtained));
                                                        }
                                                        $hr_pct = isset($hr["percentage"]) && is_numeric($hr["percentage"]) && (float)$hr["percentage"] > 0
                                                            ? (float)$hr["percentage"]
                                                            : round(($score_obtained / $total_marks_val) * 100, 1);
                                                        ?>
                                                        <tr>
                                                            <td><strong><?php echo h($hr["exam_title"]); ?></strong></td>
                                                            <td><span class="badge bg-primary-soft text-primary"><?php echo h($hr["subject_name"]); ?></span></td>
                                                            <td><span class="badge bg-light text-dark"><?php echo h($hr["exam_mode"] ?? "Standard"); ?></span></td>
                                                            <td><?php echo (int)$score_obtained; ?> / <?php echo (int)$total_marks_val; ?></td>
                                                            <td>
                                                                <div class="sp-score-ring" style="--p:<?php echo (int)round($hr_pct); ?>;--c:<?php echo $hr_pct >= 40 ? '#22C55E' : '#EF4444'; ?>;width:40px;height:40px;">
                                                                    <div class="sp-score-ring-inner"><strong><?php echo number_format($hr_pct, 1); ?>%</strong></div>
                                                                </div>
                                                            </td>
                                                            <td><span class="badge bg-<?php echo $hr["status"] == "Pass" ? "success" : "danger"; ?>"><?php echo h($hr["status"]); ?></span></td>
                                                            <td><small class="text-muted"><?php echo date("M j, Y g:i A", strtotime($hr["submitted_at"])); ?></small></td>
                                                        </tr>
                                                    <?php }
                                                } else { ?>
                                                    <tr><td colspan="7" class="text-center text-muted py-4">No exam submissions found for your assigned subjects.</td></tr>
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
                            <i class="bi bi-award fs-1 text-muted d-block mb-2"></i>
                            <h5>No Results Found</h5>
                            <p class="text-muted mb-0">No exam results recorded yet for your assigned subjects.</p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) {
                $res_page_extra = array('search' => $search);
                if ($filter_exam > 0) { $res_page_extra['exam_id'] = $filter_exam; }
                if ($res_course !== '') { $res_page_extra['course'] = $res_course; }
                if ($res_semester !== '') { $res_page_extra['semester'] = $res_semester; }
            ?>
                <nav class="sp-pagination mt-4" aria-label="Results pagination">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1) $start = max(1, $end - 4);
                        if ($end < $total_pages) $end = min($total_pages, $start + 4);
                        
                        if ($page > 1) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo build_results_url($page - 1, $res_page_extra); ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>
                        <?php }
                        for ($i = $start; $i <= $end; $i++) { ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo build_results_url($i, $res_page_extra); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php }
                        if ($page < $total_pages) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo build_results_url($page + 1, $res_page_extra); ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>
                        <?php } ?>
                    </ul>
                </nav>
            <?php } ?>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client-side live filter for result cards
    const searchInput = document.querySelector('[name="search"]');
    const grid = document.getElementById('resultsGrid');
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
                // Show/hide empty state
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
<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "My Assigned Subjects";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subjects = get_faculty_assigned_subjects($faculty_id);
$assigned_ids = array();
foreach ($assigned_subjects as $fp_a) { $assigned_ids[] = (int)$fp_a["id"]; }
$scope_csv = !empty($assigned_ids) ? implode(",", $assigned_ids) : "0";

// Filters: server-side search + whitelisted sort (subjects table has no
// semester/course columns, so sorting is the honest secondary filter)
$fp_search = trim((string)($_GET["search"] ?? ""));
$fp_sort = trim((string)($_GET["sort"] ?? "name_asc"));
$fp_sort_whitelist = array("name_asc", "name_desc", "questions_desc", "exams_desc", "avg_desc");
if (!in_array($fp_sort, $fp_sort_whitelist, true)) { $fp_sort = "name_asc"; }
$fp_page = max(1, (int)($_GET["page"] ?? 1));
$fp_per_page = 8;

// Per-subject aggregates in one grouped query (no N+1 for results)
$fp_agg = array();
if (!empty($assigned_ids)) {
    $fp_agg_q = mysqli_query($conn, "SELECT e.subject_id,
        COUNT(r.id) AS attempts,
        AVG(r.percentage) AS avg_p,
        COUNT(DISTINCT r.student_id) AS students
        FROM results r INNER JOIN exams e ON r.exam_id=e.id
        WHERE e.subject_id IN ($scope_csv) GROUP BY e.subject_id");
    if ($fp_agg_q) {
        while ($fp_ar = mysqli_fetch_assoc($fp_agg_q)) {
            $fp_agg[(int)$fp_ar["subject_id"]] = $fp_ar;
        }
    }
}

// Subject overview totals (same data source, aggregated once)
$fp_tot_q = 0;
$fp_tot_e = 0;
$fp_tot_attempts = 0;
$fp_tot_students = 0;
$fp_sum_pct = 0;
$fp_cnt_pct = 0;
$fp_stu_done = array();
foreach ($assigned_subjects as $fp_s) {
    $fp_sid = (int)$fp_s["id"];
    $fp_qr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions WHERE subject_id=$fp_sid");
    if ($fp_qr && $fp_qrow = mysqli_fetch_assoc($fp_qr)) { $fp_tot_q += (int)$fp_qrow["c"]; }
    $fp_er = mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams WHERE subject_id=$fp_sid");
    if ($fp_er && $fp_erow = mysqli_fetch_assoc($fp_er)) { $fp_tot_e += (int)$fp_erow["c"]; }
    if (isset($fp_agg[$fp_sid])) {
        $fp_tot_attempts += (int)($fp_agg[$fp_sid]["attempts"] ?? 0);
        if ($fp_agg[$fp_sid]["avg_p"] !== null) { $fp_sum_pct += (float)$fp_agg[$fp_sid]["avg_p"]; $fp_cnt_pct++; }
    }
}
if (!empty($assigned_ids)) {
    $fp_sc = mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($scope_csv)");
    if ($fp_sc && $fp_srow = mysqli_fetch_assoc($fp_sc)) { $fp_tot_students = (int)$fp_srow["c"]; }
}
$fp_overall_avg = $fp_cnt_pct > 0 ? round($fp_sum_pct / $fp_cnt_pct, 1) : 0;

// Apply search filter + sorting in PHP (assigned set is small and already loaded)
$fp_filtered = array();
foreach ($assigned_subjects as $fp_s) {
    if ($fp_search !== "") {
        $hay = strtolower($fp_s["subject_name"] . " " . ($fp_s["subject_code"] ?? "") . " " . ($fp_s["description"] ?? ""));
        if (strpos($hay, strtolower($fp_search)) === false) { continue; }
    }
    $fp_filtered[] = $fp_s;
}
// Decorate with counts (needed for count-based sorts and cards)
foreach ($fp_filtered as $fp_k => $fp_s) {
    $fp_sid = (int)$fp_s["id"];
    $fp_filtered[$fp_k]["_q"] = 0;
    $fp_filtered[$fp_k]["_e"] = 0;
    $fp_filtered[$fp_k]["_a"] = 0;
    $fp_q1 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions WHERE subject_id=$fp_sid");
    if ($fp_q1 && $fp_qr1 = mysqli_fetch_assoc($fp_q1)) { $fp_filtered[$fp_k]["_q"] = (int)$fp_qr1["c"]; }
    $fp_e1 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams WHERE subject_id=$fp_sid");
    if ($fp_e1 && $fp_er1 = mysqli_fetch_assoc($fp_e1)) { $fp_filtered[$fp_k]["_e"] = (int)$fp_er1["c"]; }
    if (isset($fp_agg[$fp_sid]) && $fp_agg[$fp_sid]["avg_p"] !== null) { $fp_filtered[$fp_k]["_a"] = round((float)$fp_agg[$fp_sid]["avg_p"], 1); }
}
if ($fp_sort === "name_desc") { usort($fp_filtered, function ($a, $b) { return strcasecmp($b["subject_name"], $a["subject_name"]); }); }
elseif ($fp_sort === "questions_desc") { usort($fp_filtered, function ($a, $b) { return $b["_q"] <=> $a["_q"]; }); }
elseif ($fp_sort === "exams_desc") { usort($fp_filtered, function ($a, $b) { return $b["_e"] <=> $a["_e"]; }); }
elseif ($fp_sort === "avg_desc") { usort($fp_filtered, function ($a, $b) { return $b["_a"] <=> $a["_a"]; }); }
else { usort($fp_filtered, function ($a, $b) { return strcasecmp($a["subject_name"], $b["subject_name"]); }); }

$fp_total = count($fp_filtered);
$fp_pages = max(1, (int)ceil($fp_total / $fp_per_page));
if ($fp_page > $fp_pages) { $fp_page = $fp_pages; }
$fp_page_items = array_slice($fp_filtered, ($fp_page - 1) * $fp_per_page, $fp_per_page);
$fp_from = $fp_total > 0 ? (($fp_page - 1) * $fp_per_page + 1) : 0;
$fp_to = min($fp_total, $fp_page * $fp_per_page);
function fp_subjects_url($page, $search, $sort) {
    $p = array();
    if ($search !== "") { $p["search"] = $search; }
    if ($sort !== "" && $sort !== "name_asc") { $p["sort"] = $sort; }
    $p["page"] = $page;
    return "subjects.php?" . http_build_query($p);
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
                    <li class="breadcrumb-item active">My Subjects</li>
                </ol>
            </nav>

            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-book"></i> Teaching Scope</span>
                    <h1>My Assigned Subjects</h1>
                    <p>You have permission to create exams, questions, and view performance for these subjects.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="sp-btn sp-btn-ghost" data-fp-refresh title="Reload subjects"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                    <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-calendar-plus"></i>Create Exam</a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="1">
                    <div class="sp-stat">
                        <span class="sp-stat-icon"><i class="bi bi-journal-bookmark"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo count($assigned_subjects); ?>">0</div>
                        <div class="sp-stat-label">Assigned Subjects</div>
                        <div class="sp-stat-foot">Subjects in your scope</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="2">
                    <div class="sp-stat is-success">
                        <span class="sp-stat-icon"><i class="bi bi-people"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_tot_students; ?>">0</div>
                        <div class="sp-stat-label">Total Students</div>
                        <div class="sp-stat-foot">Attempted your subjects</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="3">
                    <div class="sp-stat is-info">
                        <span class="sp-stat-icon"><i class="bi bi-journal-check"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_tot_e; ?>">0</div>
                        <div class="sp-stat-label">Total Exams</div>
                        <div class="sp-stat-foot"><?php echo $fp_tot_q; ?> questions in bank</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 sp-fade-up" data-delay="4">
                    <div class="sp-stat is-warning">
                        <span class="sp-stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div class="sp-stat-value"><?php echo $fp_overall_avg; ?>%</div>
                        <div class="sp-stat-label">Average Performance</div>
                        <div class="sp-stat-foot"><?php echo $fp_tot_attempts; ?> attempts overall</div>
                    </div>
                </div>
            </div>

            <?php if (count($assigned_subjects) > 0) { ?>
            <form method="get" class="sp-filters sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1" for="fpSubjectSearch">Search subjects</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" id="fpSubjectSearch" name="search" value="<?php echo h($fp_search); ?>" placeholder="Type subject name or code..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fpSubjectSort">Sort by</label>
                        <select name="sort" id="fpSubjectSort" class="form-select">
                            <option value="name_asc" <?php echo $fp_sort === "name_asc" ? "selected" : ""; ?>>Name (A–Z)</option>
                            <option value="name_desc" <?php echo $fp_sort === "name_desc" ? "selected" : ""; ?>>Name (Z–A)</option>
                            <option value="avg_desc" <?php echo $fp_sort === "avg_desc" ? "selected" : ""; ?>>Best Average</option>
                            <option value="exams_desc" <?php echo $fp_sort === "exams_desc" ? "selected" : ""; ?>>Most Exams</option>
                            <option value="questions_desc" <?php echo $fp_sort === "questions_desc" ? "selected" : ""; ?>>Most Questions</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-2">
                        <button type="submit" class="sp-btn sp-btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                        <button type="button" class="sp-btn sp-btn-ghost" data-fp-refresh aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>
                </div>
                <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="sp-filter-count">Showing <?php echo $fp_from; ?>–<?php echo $fp_to; ?> of <?php echo $fp_total; ?> subjects</span>
                    <?php if ($fp_search !== "" || $fp_sort !== "name_asc") { ?>
                        <a href="subjects.php" class="small text-decoration-none"><i class="bi bi-x-circle me-1"></i>Clear filters</a>
                    <?php } ?>
                </div>
            </form>
            <?php } ?>

            <?php if (count($assigned_subjects) > 0) { ?>
                <?php if (!empty($fp_page_items)) { ?>
                <div class="row g-4" id="fpSubjectCards">
                    <?php $fp_i = 0; foreach ($fp_page_items as $sub) {
                        $fp_i++;
                        $sid = (int)$sub["id"];
                        $q_cnt = (int)($sub["_q"] ?? 0);
                        $e_cnt = (int)($sub["_e"] ?? 0);
                        $avg_score = round((float)($sub["_a"] ?? 0), 1);
                        $attempts = isset($fp_agg[$sid]) ? (int)($fp_agg[$sid]["attempts"] ?? 0) : 0;
                        $students = isset($fp_agg[$sid]) ? (int)($fp_agg[$sid]["students"] ?? 0) : 0;
                        $avg_cls = $avg_score >= 70 ? "is-completed" : ($avg_score >= 40 ? "is-upcoming" : "is-expired");
                        $status_active = strcasecmp(trim($sub["status"] ?? "Active"), "Active") === 0;
                    ?>
                        <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min(($fp_i % 3) + 1, 6); ?>">
                            <div class="sp-exam-card h-100">
                                <div class="sp-exam-head">
                                    <div>
                                        <h3 class="sp-exam-title"><?php echo h($sub["subject_name"]); ?></h3>
                                        <span class="sp-exam-subject"><i class="bi bi-journal-bookmark"></i><?php echo h($sub["subject_code"] ?? ""); ?></span>
                                    </div>
                                    <span class="sp-pill <?php echo $status_active ? "is-completed" : "is-expired"; ?>"><i class="bi bi-<?php echo $status_active ? "check2-circle" : "pause-circle"; ?>"></i><?php echo h($sub["status"] ?? "Active"); ?></span>
                                </div>
                                <p class="text-muted small mb-3"><?php echo !empty($sub["description"]) ? h($sub["description"]) : "No description provided."; ?></p>
                                <div class="sp-exam-meta">
                                    <div class="sp-meta-item"><i class="bi bi-people"></i><span><?php echo $students; ?> Students</span></div>
                                    <div class="sp-meta-item"><i class="bi bi-journal-check"></i><span><?php echo $e_cnt; ?> Exams</span></div>
                                    <div class="sp-meta-item"><i class="bi bi-patch-question"></i><span><?php echo $q_cnt; ?> Questions</span></div>
                                    <div class="sp-meta-item"><i class="bi bi-award"></i><span><?php echo $avg_score; ?>% Avg</span></div>
                                </div>
                                <div class="sp-exam-foot">
                                    <a class="sp-btn sp-btn-soft" href="students.php?subject_id=<?php echo $sid; ?>"><i class="bi bi-people"></i> Students</a>
                                    <a class="sp-btn sp-btn-soft" href="exams.php?subject_id=<?php echo $sid; ?>"><i class="bi bi-calendar-check"></i> Exams</a>
                                    <a class="sp-btn sp-btn-ghost" href="performance.php?subject_id=<?php echo $sid; ?>" title="Performance" aria-label="Performance"><i class="bi bi-graph-up"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($fp_pages > 1) { ?>
                    <nav aria-label="Subject pages" class="mt-4">
                        <ul class="pagination justify-content-center flex-wrap gap-1 mb-0">
                            <li class="page-item <?php echo $fp_page <= 1 ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $fp_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h(fp_subjects_url(max(1, $fp_page - 1), $fp_search, $fp_sort)); ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for ($fp_p = 1; $fp_p <= $fp_pages; $fp_p++) { ?>
                                <li class="page-item <?php echo $fp_p === $fp_page ? "active" : ""; ?>">
                                    <a class="page-link" href="<?php echo h(fp_subjects_url($fp_p, $fp_search, $fp_sort)); ?>"><?php echo $fp_p; ?></a>
                                </li>
                            <?php } ?>
                            <li class="page-item <?php echo $fp_page >= $fp_pages ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $fp_page >= $fp_pages ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h(fp_subjects_url(min($fp_pages, $fp_page + 1), $fp_search, $fp_sort)); ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php } ?>
                <?php } else { ?>
                <div class="sp-empty sp-fade-up">
                    <div class="sp-empty-icon"><i class="bi bi-search"></i></div>
                    <h4>No Subjects Match</h4>
                    <p>No assigned subjects match your current search. Try a different keyword or clear the search.</p>
                    <a href="subjects.php" class="sp-btn sp-btn-primary"><i class="bi bi-arrow-clockwise"></i> Show All</a>
                </div>
                <?php } ?>
            <?php } else { ?>
                <div class="sp-empty sp-fade-up">
                    <div class="sp-empty-icon"><i class="bi bi-book-half"></i></div>
                    <h4>No Subjects Assigned</h4>
                    <p>You do not have any assigned subjects yet. Please contact the administrator to assign subjects to your account.</p>
                </div>
            <?php } ?>

        </div>
    </div>
</div>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>

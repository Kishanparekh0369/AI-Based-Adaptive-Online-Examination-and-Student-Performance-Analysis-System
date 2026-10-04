<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Exams";
include("../app/layouts/student_header.php");

$student_id = (int)$_SESSION["student_id"];
$today = date("Y-m-d");

// Academic scope comes ONLY from the authenticated student's DB record.
// Never from $_GET/$_POST — URL parameters cannot change eligibility.
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT course, semester FROM students WHERE id=$student_id LIMIT 1"));
$my_course = trim((string)($me["course"] ?? ""));
$my_semester = trim((string)($me["semester"] ?? ""));
$has_scope = ($my_course !== "" && $my_semester !== "");

$search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$subject_filter = isset($_GET["subject"]) ? (int)$_GET["subject"] : 0;
$status_filter = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "";

// Subject dropdown: only subjects having scope-visible exams for this student.
$subjects_list = array();
if ($has_scope) {
    $sub_stmt = mysqli_prepare($conn, "SELECT DISTINCT s.id, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE e.status='Active' AND e.course=? AND e.semester=? ORDER BY s.subject_name ASC");
    if ($sub_stmt) {
        mysqli_stmt_bind_param($sub_stmt, "ss", $my_course, $my_semester);
        mysqli_stmt_execute($sub_stmt);
        $sub_res = mysqli_stmt_get_result($sub_stmt);
        if ($sub_res) {
            while ($s = mysqli_fetch_assoc($sub_res)) {
                $subjects_list[] = $s;
            }
        }
        mysqli_stmt_close($sub_stmt);
    }
}

$sql = "SELECT e.*, s.subject_name,
        (SELECT r.id FROM results r WHERE r.exam_id=e.id AND r.student_id=? ORDER BY r.id DESC LIMIT 1) AS result_id,
        (SELECT r.percentage FROM results r WHERE r.exam_id=e.id AND r.student_id=? ORDER BY r.id DESC LIMIT 1) AS result_percentage,
        (SELECT r.status FROM results r WHERE r.exam_id=e.id AND r.student_id=? ORDER BY r.id DESC LIMIT 1) AS result_status,
        (SELECT b.id FROM exam_attempt_blocks b WHERE b.exam_id=e.id AND b.student_id=? LIMIT 1) AS blocked_id,
        (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id) AS question_count
        FROM exams e
        LEFT JOIN subjects s ON e.subject_id=s.id
        WHERE e.status='Active'";
$params = array($student_id, $student_id, $student_id, $student_id);
$types = "iiii";

// Academic targeting: only exams configured for the student's own course+semester.
// Legacy exams with unconfigured scope ('') stay hidden by default.
if ($has_scope) {
    $sql .= " AND e.course=? AND e.semester=?";
    $params[] = $my_course;
    $params[] = $my_semester;
    $types .= "ss";
} else {
    $sql .= " AND 1=0";
}

if ($search !== "") {
    $sql .= " AND (e.exam_title LIKE ? OR s.subject_name LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
if ($subject_filter > 0) {
    $sql .= " AND e.subject_id=?";
    $params[] = $subject_filter;
    $types .= "i";
}
$sql .= " ORDER BY e.exam_date DESC, e.id DESC";

$exams = array();
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $exam_date = $row["exam_date"];
            if ($row["result_id"] !== null) {
                $status_key = "Completed";
            } elseif ($row["blocked_id"] !== null) {
                $status_key = "Blocked";
            } elseif ($exam_date !== "" && $exam_date > $today) {
                $status_key = "Upcoming";
            } else {
                $status_key = "Available";
            }
            $row["status_key"] = $status_key;
            $exams[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

if ($status_filter !== "") {
    $exams = array_values(array_filter($exams, function ($e) use ($status_filter) {
        return strtolower($e["status_key"]) === strtolower($status_filter);
    }));
}

$status_counts = array("Available" => 0, "Upcoming" => 0, "Completed" => 0, "Blocked" => 0);
$base_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM exams WHERE status='Active' AND course=? AND semester=?");
$total_active = 0;
if ($base_count_stmt && $has_scope) {
    mysqli_stmt_bind_param($base_count_stmt, "ss", $my_course, $my_semester);
    mysqli_stmt_execute($base_count_stmt);
    $base_count_res = mysqli_stmt_get_result($base_count_stmt);
    $total_active = $base_count_res ? (int)mysqli_fetch_assoc($base_count_res)["total"] : 0;
    mysqli_stmt_close($base_count_stmt);
}

$all_for_count = mysqli_prepare($conn, "SELECT e.id,
    (SELECT r.id FROM results r WHERE r.exam_id=e.id AND r.student_id=? LIMIT 1) AS result_id,
    (SELECT b.id FROM exam_attempt_blocks b WHERE b.exam_id=e.id AND b.student_id=? LIMIT 1) AS blocked_id,
    e.exam_date
    FROM exams e WHERE e.status='Active' AND e.course=? AND e.semester=?");
if ($all_for_count && $has_scope) {
    mysqli_stmt_bind_param($all_for_count, "iiss", $student_id, $student_id, $my_course, $my_semester);
    mysqli_stmt_execute($all_for_count);
    $cres = mysqli_stmt_get_result($all_for_count);
    if ($cres) {
        while ($c = mysqli_fetch_assoc($cres)) {
            if ($c["result_id"] !== null) { $status_counts["Completed"]++; }
            elseif ($c["blocked_id"] !== null) { $status_counts["Blocked"]++; }
            elseif ($c["exam_date"] > $today) { $status_counts["Upcoming"]++; }
            else { $status_counts["Available"]++; }
        }
    }
    mysqli_stmt_close($all_for_count);
}
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-journal-check"></i> Examinations</span>
                        <h1>Available Exams</h1>
                        <p>Browse and attempt the examinations assigned to your account.</p>
                        <?php if ($has_scope) { ?>
                            <p class="mb-0"><span class="sp-pill is-available"><i class="bi bi-mortarboard"></i><?php echo h($my_course); ?> &middot; <?php echo h($my_semester); ?></span></p>
                        <?php } else { ?>
                            <p class="mb-0"><span class="sp-pill is-expired"><i class="bi bi-exclamation-triangle"></i>Academic scope missing — contact admin to set your course &amp; semester.</span></p>
                        <?php } ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="sp-btn sp-btn-primary" onclick="if(window.StudentAI){StudentAI.ask('Help me prepare for my upcoming and available exams. What should I focus on?');}"><i class="bi bi-robot"></i> Help Me Prepare</button>
                        <a href="result.php" class="sp-btn sp-btn-soft"><i class="bi bi-trophy"></i> My Results</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="1">
                        <div class="sp-stat">
                            <span class="sp-stat-icon"><i class="bi bi-collection"></i></span>
                            <div class="sp-stat-value"><?php echo (int)$total_active; ?></div>
                            <div class="sp-stat-label">Total Active Exams</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="2">
                        <div class="sp-stat is-success">
                            <span class="sp-stat-icon"><i class="bi bi-check2-circle"></i></span>
                            <div class="sp-stat-value"><?php echo (int)$status_counts["Completed"]; ?></div>
                            <div class="sp-stat-label">Completed</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="3">
                        <div class="sp-stat is-info">
                            <span class="sp-stat-icon"><i class="bi bi-play-circle"></i></span>
                            <div class="sp-stat-value"><?php echo (int)$status_counts["Available"]; ?></div>
                            <div class="sp-stat-label">Available Now</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="4">
                        <div class="sp-stat is-warning">
                            <span class="sp-stat-icon"><i class="bi bi-calendar-event"></i></span>
                            <div class="sp-stat-value"><?php echo (int)$status_counts["Upcoming"]; ?></div>
                            <div class="sp-stat-label">Upcoming</div>
                        </div>
                    </div>
                </div>

                <form method="get" class="sp-filters sp-fade-up" data-exam-filter-scope>
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-5 col-md-6">
                            <label class="form-label small fw-semibold mb-1" for="examSearch">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control" id="examSearch" name="search" value="<?php echo h($search); ?>" placeholder="Search exams or subjects..." data-exam-search>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small fw-semibold mb-1" for="examSubject">Subject</label>
                            <select class="form-select" id="examSubject" name="subject" data-exam-subject>
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects_list as $s) { ?>
                                    <option value="<?php echo (int)$s["id"]; ?>" <?php echo $subject_filter === (int)$s["id"] ? "selected" : ""; ?>><?php echo h($s["subject_name"]); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small fw-semibold mb-1" for="examStatus">Status</label>
                            <select class="form-select" id="examStatus" name="status" data-exam-status>
                                <option value="">All Status</option>
                                <?php foreach (array("Available", "Upcoming", "Completed", "Blocked") as $st) { ?>
                                    <option value="<?php echo strtolower($st); ?>" <?php echo strtolower($status_filter) === strtolower($st) ? "selected" : ""; ?>><?php echo $st; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6 d-flex gap-2">
                            <button type="submit" class="sp-btn sp-btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                            <button type="button" class="sp-btn sp-btn-ghost" data-exam-reset aria-label="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <span class="sp-filter-count" data-exam-count><?php echo count($exams); ?> exams</span>
                        <?php if ($search !== "" || $subject_filter > 0 || $status_filter !== "") { ?>
                            <a href="exams.php" class="small text-decoration-none">Clear all filters</a>
                        <?php } ?>
                    </div>
                </form>

                <div class="row g-4" id="examCards">
                    <?php if (!empty($exams)) { $i = 0; foreach ($exams as $row) {
                        $i++;
                        $is_adaptive = ($row["exam_mode"] ?? "Standard") === "Adaptive";
                        $status_key = $row["status_key"];
                        $card_class = $status_key === "Completed" ? "is-completed" : ($status_key === "Blocked" ? "is-blocked" : "");
                        $pill_class = "is-" . strtolower($status_key);
                        $qcount = $is_adaptive ? (int)($row["max_questions"] ?? 10) : (int)$row["question_count"];
                        if ($qcount <= 0) { $qcount = $is_adaptive ? 10 : (int)$row["total_marks"]; }
                        $search_text = strtolower($row["exam_title"] . " " . $row["subject_name"]);
                    ?>
                        <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min($i, 6); ?>"
                             data-exam-card
                             data-search="<?php echo h($search_text); ?>"
                             data-subject="<?php echo (int)$row["subject_id"]; ?>"
                             data-status="<?php echo h(strtolower($status_key)); ?>">
                            <div class="sp-exam-card <?php echo $card_class; ?>">
                                <div class="sp-exam-head">
                                    <div>
                                        <h3 class="sp-exam-title"><?php echo h($row["exam_title"]); ?></h3>
                                        <span class="sp-exam-subject"><i class="bi bi-book"></i><?php echo h($row["subject_name"] ?? "General"); ?></span>
                                    <?php if (trim($row["course"] ?? "") !== "" || trim($row["semester"] ?? "") !== "") { ?>
                                        <span class="sp-exam-subject"><i class="bi bi-mortarboard"></i><?php echo h(trim(($row["course"] ?? "") . " • " . ($row["semester"] ?? ""))); ?></span>
                                    <?php } ?>
                                    </div>
                                    <span class="sp-pill <?php echo $pill_class; ?>">
                                        <?php if ($status_key === "Completed") { ?><i class="bi bi-check2-circle"></i>
                                        <?php } elseif ($status_key === "Blocked") { ?><i class="bi bi-slash-circle"></i>
                                        <?php } elseif ($status_key === "Upcoming") { ?><i class="bi bi-clock"></i>
                                        <?php } else { ?><i class="bi bi-play-circle"></i><?php } ?>
                                        <?php echo h($status_key); ?>
                                    </span>
                                </div>

                                <?php if ($is_adaptive) { ?>
                                    <span class="sp-pill is-adaptive mb-2"><i class="bi bi-robot"></i> Adaptive Mode</span>
                                <?php } else { ?>
                                    <span class="sp-pill is-available mb-2"><i class="bi bi-gear"></i> Standard Mode</span>
                                <?php } ?>

                                <div class="sp-exam-meta">
                                    <div class="sp-meta-item"><i class="bi bi-patch-question"></i><span><?php echo $qcount; ?> Questions</span></div>
                                    <div class="sp-meta-item"><i class="bi bi-stopwatch"></i><span><?php echo (int)$row["duration"]; ?> Minutes</span></div>
                                    <div class="sp-meta-item"><i class="bi bi-calendar3"></i><span><?php echo $row["exam_date"] !== "" ? date("d M Y", strtotime($row["exam_date"])) : "Not set"; ?></span></div>
                                    <div class="sp-meta-item"><i class="bi bi-award"></i><span><?php echo (int)$row["total_marks"]; ?> Marks</span></div>
                                    <div class="sp-meta-item"><i class="bi bi-flag"></i><span>Pass: <?php echo (int)$row["pass_marks"]; ?> Marks</span></div>
                                    <?php if ($status_key === "Completed") { ?>
                                        <div class="sp-meta-item"><i class="bi bi-graph-up"></i><span>Score <?php echo number_format((float)$row["result_percentage"], 1); ?>%</span></div>
                                        <div class="sp-meta-item"><i class="bi bi-flag"></i><span><?php echo h($row["result_status"]); ?></span></div>
                                    <?php } ?>
                                </div>

                                <div class="sp-exam-foot">
                                    <?php if ($status_key === "Completed") { ?>
                                        <span class="small text-muted">Attempted on <?php echo date("d M Y", strtotime($row["exam_date"])); ?></span>
                                        <a class="sp-btn sp-btn-soft" href="result.php?exam_id=<?php echo (int)$row["id"]; ?>"><i class="bi bi-bar-chart"></i> View Result</a>
                                    <?php } elseif ($status_key === "Blocked") { ?>
                                        <span class="small text-danger"><i class="bi bi-info-circle me-1"></i>Attempt blocked</span>
                                        <button class="sp-btn sp-btn-ghost is-disabled" type="button" disabled>Not Available</button>
                                    <?php } elseif ($status_key === "Upcoming") { ?>
                                        <span class="small text-muted"><i class="bi bi-calendar-event me-1"></i>Starts <?php echo date("d M Y", strtotime($row["exam_date"])); ?></span>
                                        <button class="sp-btn sp-btn-ghost is-disabled" type="button" disabled>Upcoming</button>
                                    <?php } else {
                                        $exam_link = $is_adaptive ? "adaptive_exam.php" : "start_exam.php";
                                    ?>
                                        <span class="small text-muted">Ready to attempt</span>
                                        <a class="sp-btn sp-btn-primary" href="<?php echo $exam_link; ?>?id=<?php echo (int)$row["id"]; ?>">Start Exam <i class="bi bi-arrow-right"></i></a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } } ?>
                </div>

                <div class="sp-empty" data-exam-empty style="<?php echo empty($exams) ? "" : "display:none"; ?>">
                    <div class="sp-empty-icon"><i class="bi bi-journal-x"></i></div>
                    <h4>No Exams Found</h4>
                    <p>
                        <?php if ($search !== "" || $subject_filter > 0 || $status_filter !== "") { ?>
                            No examinations match your current filters. Try adjusting or clearing them.
                        <?php } else { ?>
                            There are currently no examinations available for your account. Please check back later.
                        <?php } ?>
                    </p>
                    <a href="exams.php" class="sp-btn sp-btn-primary" onclick="var b=this;b.classList.add('disabled');b.setAttribute('aria-disabled','true');b.innerHTML='<span class=&quot;spinner-border spinner-border-sm me-1&quot; aria-hidden=&quot;true&quot;></span>Refreshing...';"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
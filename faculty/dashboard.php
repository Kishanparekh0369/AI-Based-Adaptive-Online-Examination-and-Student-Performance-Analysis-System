<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Faculty Dashboard";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$assigned_subjects = get_faculty_assigned_subjects($faculty_id);

$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

/* ══════════════════════════════════════════════════
   ACADEMIC SCOPE
   Source of truth (no duplicate columns created):
   - Course / Semester live ONLY in `students`
   - Faculty reach = assigned subjects -> exams -> results -> students
   Every stat below is constrained to this scope first,
   then narrowed by the validated academic filters.
   ══════════════════════════════════════════════════ */

// 1. Allowed courses / semesters = DISTINCT values of students
//    who attempted exams in this faculty's assigned subjects.
$allowed_courses = array();
$allowed_semesters = array();
if (!empty($assigned_subject_ids)) {
    $scope_res = mysqli_query($conn, "SELECT DISTINCT st.course, st.semester
        FROM results r
        INNER JOIN exams e ON r.exam_id=e.id
        INNER JOIN students st ON r.student_id=st.id
        WHERE e.subject_id IN ($subject_ids_csv)");
    if ($scope_res) {
        while ($srow = mysqli_fetch_assoc($scope_res)) {
            $c = trim((string)($srow["course"] ?? ""));
            $s = trim((string)($srow["semester"] ?? ""));
            if ($c !== "" && !in_array($c, $allowed_courses, true)) { $allowed_courses[] = $c; }
            if ($s !== "" && !in_array($s, $allowed_semesters, true)) { $allowed_semesters[] = $s; }
        }
    }
    sort($allowed_courses);
    sort($allowed_semesters);
}

// 2. Parse + validate filters (never trust raw GET).
$f_course = isset($_GET["f_course"]) ? trim((string)$_GET["f_course"]) : "";
$f_semester = isset($_GET["f_semester"]) ? trim((string)$_GET["f_semester"]) : "";
$f_subject = isset($_GET["f_subject"]) ? (int)$_GET["f_subject"] : 0;
$f_exam = isset($_GET["f_exam"]) ? (int)$_GET["f_exam"] : 0;

if ($f_course !== "" && !in_array($f_course, $allowed_courses, true)) { $f_course = ""; }
if ($f_semester !== "" && !in_array($f_semester, $allowed_semesters, true)) { $f_semester = ""; }
if ($f_subject > 0 && !in_array($f_subject, $assigned_subject_ids)) { $f_subject = 0; }
if ($f_exam > 0) {
    $exam_check = mysqli_query($conn, "SELECT subject_id FROM exams WHERE id=$f_exam LIMIT 1");
    $exam_row = $exam_check ? mysqli_fetch_assoc($exam_check) : null;
    if (!$exam_row || !in_array((int)$exam_row["subject_id"], $assigned_subject_ids)) {
        $f_exam = 0;
    } else if ($f_subject > 0 && (int)$exam_row["subject_id"] !== $f_subject) {
        $f_exam = 0; // exam must belong to the selected subject
    }
}

$filters_active = ($f_course !== "" || $f_semester !== "" || $f_subject > 0 || $f_exam > 0);

// 3. Exam-level scope (subject / exam filter).
$scoped_subject_ids = $f_subject > 0 ? array($f_subject) : $assigned_subject_ids;
$scoped_subject_csv = !empty($scoped_subject_ids) ? implode(",", array_map("intval", $scoped_subject_ids)) : "0";

$scoped_exam_ids = array();
if (!empty($scoped_subject_ids)) {
    $exam_sql = "SELECT id FROM exams WHERE subject_id IN ($scoped_subject_csv)";
    if ($f_exam > 0) { $exam_sql .= " AND id=$f_exam"; }
    $exam_res = mysqli_query($conn, $exam_sql);
    if ($exam_res) {
        while ($erow = mysqli_fetch_assoc($exam_res)) { $scoped_exam_ids[] = (int)$erow["id"]; }
    }
}
$scoped_exam_csv = !empty($scoped_exam_ids) ? implode(",", $scoped_exam_ids) : "0";

// 4. Student-level scope (course / semester filter, escaped).
$student_scope_sql = "";
if ($f_course !== "") {
    $student_scope_sql .= " AND st.course='" . mysqli_real_escape_string($conn, $f_course) . "'";
}
if ($f_semester !== "") {
    $student_scope_sql .= " AND st.semester='" . mysqli_real_escape_string($conn, $f_semester) . "'";
}

// 5. Exams dropdown (respects selected subject).
$filter_exams = array();
if (!empty($scoped_subject_ids)) {
    $fe_res = mysqli_query($conn, "SELECT id, exam_title FROM exams WHERE subject_id IN ($scoped_subject_csv) ORDER BY exam_title ASC");
    if ($fe_res) {
        while ($fer = mysqli_fetch_assoc($fe_res)) { $filter_exams[] = $fer; }
    }
}

// ─── Summary Metrics (academically aware) ───
$total_subjects = $f_subject > 0 ? 1 : count($assigned_subject_ids);

$total_questions = 0;
$q_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM questions WHERE subject_id IN ($scoped_subject_csv)");
if ($q_res && $q_row = mysqli_fetch_assoc($q_res)) {
    $total_questions = (int)$q_row["total"];
}

$total_exams = count($scoped_exam_ids);

// Scoped students (distinct attempting students after all filters)
$total_students = 0;
$st_res = mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS total
    FROM results r
    INNER JOIN exams e ON r.exam_id=e.id
    INNER JOIN students st ON r.student_id=st.id
    WHERE r.exam_id IN ($scoped_exam_csv)$student_scope_sql");
if ($st_res && $st_row = mysqli_fetch_assoc($st_res)) {
    $total_students = (int)$st_row["total"];
}

$total_attempts = 0;
$avg_score = 0;
$pass_count = 0;
$r_res = mysqli_query($conn, "SELECT COUNT(*) AS total, AVG(r.percentage) AS avg_p, SUM(CASE WHEN r.status='Pass' THEN 1 ELSE 0 END) AS pass_cnt
    FROM results r
    INNER JOIN exams e ON r.exam_id=e.id
    INNER JOIN students st ON r.student_id=st.id
    WHERE r.exam_id IN ($scoped_exam_csv)$student_scope_sql");
if ($r_res && $r_row = mysqli_fetch_assoc($r_res)) {
    $total_attempts = (int)$r_row["total"];
    $avg_score = round((float)($r_row["avg_p"] ?? 0), 1);
    $pass_count = (int)($r_row["pass_cnt"] ?? 0);
}
$pass_rate = $total_attempts > 0 ? round(($pass_count / $total_attempts) * 100, 1) : 0;

// Subject performance data for chart (academically aware)
$chart_labels = "";
$chart_values = "";
if (!empty($scoped_subject_ids)) {
    $sub_perf = mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp
        FROM subjects s
        LEFT JOIN exams e ON e.subject_id=s.id" . ($f_exam > 0 ? " AND e.id=$f_exam" : "") . "
        LEFT JOIN results r ON r.exam_id=e.id
        LEFT JOIN students st ON r.student_id=st.id" . (($f_course !== "" || $f_semester !== "") ? $student_scope_sql : "") . "
        WHERE s.id IN ($scoped_subject_csv)
        GROUP BY s.id, s.subject_name
        ORDER BY s.subject_name ASC");
    if ($sub_perf) {
        while ($p_row = mysqli_fetch_assoc($sub_perf)) {
            $chart_labels .= "'" . addslashes($p_row["subject_name"]) . "',";
            $chart_values .= round((float)($p_row["avgp"] ?? 0), 1) . ",";
        }
    }
}
if ($chart_labels == "") {
    $chart_labels = "'No Data'";
    $chart_values = "0";
}

// Recent exams (enriched: questions, attempts, avg, derived course/semester scope)
$recent_exams = array();
if (!empty($scoped_exam_ids)) {
    $recent_e_res = mysqli_query($conn, "SELECT e.*, s.subject_name,
        (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id) AS q_count
        FROM exams e
        LEFT JOIN subjects s ON e.subject_id=s.id
        WHERE e.id IN ($scoped_exam_csv)
        ORDER BY e.id DESC LIMIT 5");
    if ($recent_e_res) {
        while ($re = mysqli_fetch_assoc($recent_e_res)) {
            $eid = (int)$re["id"];
            $att = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c, AVG(r.percentage) AS a
                FROM results r INNER JOIN students st ON r.student_id=st.id
                WHERE r.exam_id=$eid$student_scope_sql"));
            $re["attempts"] = (int)($att["c"] ?? 0);
            $re["avgp"] = round((float)($att["a"] ?? 0), 1);
            $cs = mysqli_query($conn, "SELECT DISTINCT st.course, st.semester
                FROM results r INNER JOIN students st ON r.student_id=st.id
                WHERE r.exam_id=$eid$student_scope_sql LIMIT 4");
            $courses = array(); $semesters = array(); $more = 0;
            if ($cs) {
                while ($crow = mysqli_fetch_assoc($cs)) {
                    if (!in_array($crow["course"], $courses, true)) { $courses[] = $crow["course"]; }
                    if (!in_array($crow["semester"], $semesters, true)) { $semesters[] = $crow["semester"]; }
                }
                $total_cs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT CONCAT(st.course,'|',st.semester)) AS c
                    FROM results r INNER JOIN students st ON r.student_id=st.id WHERE r.exam_id=$eid$student_scope_sql"));
                $more = max(0, (int)($total_cs["c"] ?? 0) - 4);
            }
            $re["scope_courses"] = $courses;
            $re["scope_semesters"] = $semesters;
            $re["scope_more"] = $more;
            $recent_exams[] = $re;
        }
    }
}

// Recent results (with academic context)
$recent_results = array();
if (!empty($scoped_exam_ids)) {
    $recent_r_res = mysqli_query($conn, "SELECT r.*, st.name AS student_name, st.course AS student_course, st.semester AS student_semester,
        st.profile_image AS student_photo, e.exam_title, s.subject_name
        FROM results r
        INNER JOIN exams e ON r.exam_id=e.id
        INNER JOIN subjects s ON e.subject_id=s.id
        INNER JOIN students st ON r.student_id=st.id
        WHERE r.exam_id IN ($scoped_exam_csv)$student_scope_sql
        ORDER BY r.id DESC LIMIT 5");
    if ($recent_r_res) {
        while ($rr = mysqli_fetch_assoc($recent_r_res)) {
            $recent_results[] = $rr;
        }
    }
}

// Assigned subjects enriched (filtered + per-subject academic stats)
$display_subjects = $f_subject > 0
    ? array_values(array_filter($assigned_subjects, function ($s) use ($f_subject) { return (int)$s["id"] === $f_subject; }))
    : $assigned_subjects;
foreach ($display_subjects as &$dsub) {
    $sub_id = (int)$dsub["id"];
    $sub_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions WHERE subject_id=$sub_id"));
    $dsub["q_cnt"] = (int)($sub_q["c"] ?? 0);
    $sub_e = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams WHERE subject_id=$sub_id" . ($f_exam > 0 ? " AND id=$f_exam" : "")));
    $dsub["e_cnt"] = (int)($sub_e["c"] ?? 0);
    $sub_s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS students, AVG(r.percentage) AS avgp
        FROM results r INNER JOIN exams e ON r.exam_id=e.id INNER JOIN students st ON r.student_id=st.id
        WHERE e.subject_id=$sub_id" . ($f_exam > 0 ? " AND e.id=$f_exam" : "") . $student_scope_sql));
    $dsub["students"] = (int)($sub_s["students"] ?? 0);
    $dsub["avgp"] = round((float)($sub_s["avgp"] ?? 0), 1);
}
unset($dsub);

// Scope summary chips
$scope_course_label = $f_course !== "" ? $f_course : (count($allowed_courses) === 1 ? $allowed_courses[0] : (count($allowed_courses) > 1 ? "Multiple (" . count($allowed_courses) . ")" : "—"));
$scope_sem_label = $f_semester !== "" ? $f_semester : (count($allowed_semesters) === 1 ? $allowed_semesters[0] : (count($allowed_semesters) > 1 ? "Multiple (" . count($allowed_semesters) . ")" : "—"));
$has_scope = !empty($allowed_courses) || !empty($allowed_semesters) || !empty($assigned_subject_ids);

// Fetch faculty info
$faculty_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM faculty WHERE id=$faculty_id"));

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Dashboard<?php if ($f_course !== "") { echo " / " . h($f_course); } ?><?php if ($f_semester !== "") { echo " / " . h($f_semester); } ?></li>
                </ol>
            </nav>

            <!-- Hero Banner -->
            <?php
            $fp_hour = (int)date("G");
            $fp_greet = $fp_hour < 12 ? "Good morning" : ($fp_hour < 17 ? "Good afternoon" : "Good evening");
            $fp_first = isset($fp_top_name) ? trim((string)$fp_top_name) : "";
            $fp_first = $fp_first !== "" ? explode(" ", preg_replace('/\s+/', ' ', $fp_first))[0] : "Professor";
            $fp_first = ucwords(strtolower($fp_first));
            $fp_hero_name = isset($fp_top_name) && trim((string)$fp_top_name) !== "" ? $fp_top_name : ($_SESSION["faculty_name"] ?? "Faculty");
            $fp_hero_photo = isset($fp_top_photo) ? $fp_top_photo : "";
            ?>
            <section class="dash-hero mb-4" aria-label="Welcome">
                <span class="dash-orb dash-orb-1" aria-hidden="true"></span>
                <span class="dash-orb dash-orb-2" aria-hidden="true"></span>
                <div class="dash-hero-inner">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <div class="sp-fade-up">
                                <span class="dash-eyebrow"><span class="live-dot" aria-hidden="true"></span>Faculty Portal &middot; <span class="live-date"></span></span>
                            </div>
                            <h1 class="dash-title sp-fade-up" data-delay="1"><?php echo h($fp_greet); ?>, <span class="grad"><?php echo h($fp_first); ?></span></h1>
                            <p class="dash-sub sp-fade-up" data-delay="2">Manage your assigned subjects, create AI-powered exams, curate questions, and analyze student performance.</p>
                            <div class="dash-idchips sp-fade-up" data-delay="2">
                                <span class="dash-idchip"><i class="bi bi-person-badge"></i>Faculty ID: #<?php echo (int)$faculty_id; ?></span>
                                <span class="dash-idchip"><i class="bi bi-book"></i><?php echo (int)$total_subjects; ?> Subjects</span>
                                <span class="dash-idchip"><i class="bi bi-journal-check"></i><?php echo (int)$total_exams; ?> Exams</span>
                                <span class="dash-idchip"><i class="bi bi-clock"></i><span class="live-clock"></span></span>
                            </div>
                            <div class="dash-ctas sp-fade-up" data-delay="3">
                                <a class="dash-cta-primary" href="exams.php"><i class="bi bi-plus-circle-fill"></i>Create Exam</a>
                                <a class="dash-cta-ghost" href="questions.php"><i class="bi bi-robot"></i>Question Bank &amp; AI</a>
                                <a class="dash-cta-ghost" href="performance.php"><i class="bi bi-graph-up-arrow"></i>Performance</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="dash-profile-card sp-fade-up" data-delay="2">
                                <span class="dash-avatar-wrap">
                                    <?php if ($fp_hero_photo !== "") { ?>
                                        <img src="<?php echo h($fp_hero_photo); ?>" alt="Profile photo">
                                    <?php } else { ?>
                                        <span class="dash-avatar-fallback"><?php echo h(strtoupper(substr($fp_hero_name, 0, 1))); ?></span>
                                    <?php } ?>
                                    <span class="dash-online-dot" title="Online" aria-hidden="true"></span>
                                </span>
                                <div class="dash-profile-meta">
                                    <strong><?php echo h($fp_hero_name); ?></strong>
                                    <small>Faculty Member</small>
                                    <div class="dash-mini-stats">
                                        <div><b><?php echo (int)$total_subjects; ?></b><span>Subjects</span></div>
                                        <div><b><?php echo (int)$total_exams; ?></b><span>Exams</span></div>
                                        <div><b><?php echo (int)$total_attempts; ?></b><span>Attempts</span></div>
                                    </div>
                                </div>
                                <a class="dash-cta-ghost" style="padding:10px 13px;" href="profile.php" title="My Profile" aria-label="My Profile"><i class="bi bi-arrow-up-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ Academic Overview ═══ -->
            <section class="dashboard-panel mb-4 sp-fade-up" aria-label="Academic overview">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Academic Overview</h5>
                    <?php if ($filters_active) { ?>
                        <span class="badge bg-primary-soft text-primary"><i class="bi bi-funnel-fill me-1"></i>Filtered view</span>
                    <?php } else { ?>
                        <span class="badge bg-light text-dark">Full faculty scope</span>
                    <?php } ?>
                </div>
                <?php if (!$has_scope) { ?>
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-mortarboard fs-2 d-block mb-2 text-warning"></i>
                        <strong class="d-block text-dark">Academic Scope Not Assigned</strong>
                        <p class="mb-0 small">Your academic course or semester assignment has not been configured yet. No student attempts exist in your assigned subjects.</p>
                    </div>
                <?php } else { ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="settings-info-item h-100">
                                <label><i class="bi bi-mortarboard me-1"></i>Course / Program</label>
                                <?php if (count($allowed_courses) > 1 && $f_course === "") { ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach ($allowed_courses as $c) { ?>
                                            <a class="badge bg-primary-soft text-primary text-decoration-none" href="?f_course=<?php echo urlencode($c); ?><?php if ($f_semester !== "") { echo "&amp;f_semester=" . urlencode($f_semester); } ?>"><?php echo h($c); ?></a>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <span class="fs-5"><?php echo h($scope_course_label); ?></span>
                                <?php } ?>
                                <small class="d-block text-muted mt-1"><?php echo (int)$total_students; ?> students in current view</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="settings-info-item h-100">
                                <label><i class="bi bi-layers me-1"></i>Semester / Year</label>
                                <?php if (count($allowed_semesters) > 1 && $f_semester === "") { ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach ($allowed_semesters as $s) { ?>
                                            <a class="badge bg-primary-soft text-primary text-decoration-none" href="?f_semester=<?php echo urlencode($s); ?><?php if ($f_course !== "") { echo "&amp;f_course=" . urlencode($f_course); } ?>"><?php echo h($s); ?></a>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <span class="fs-5"><?php echo h($scope_sem_label); ?></span>
                                <?php } ?>
                                <small class="d-block text-muted mt-1"><?php echo (int)$total_exams; ?> exams in current view</small>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-3"><i class="bi bi-info-circle me-1"></i>Showing academic data for this faculty scope — derived from students who attempted your assigned-subject exams. Values come from the live database, never hard-coded.</p>
                <?php } ?>
            </section>

            <!-- ═══ Academic Filter Bar ═══ -->
            <form method="get" class="toolbar-card mb-4 sp-fade-up" aria-label="Academic scope filters">
                <div class="fw-bold mb-2"><i class="bi bi-sliders me-2 text-primary"></i>Academic Scope</div>
                <div class="row g-2">
                    <div class="col-12 col-sm-6 col-lg">
                        <label class="form-label small fw-semibold" for="fCourse">Course / Program</label>
                        <select id="fCourse" name="f_course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($allowed_courses as $c) { ?>
                                <option value="<?php echo h($c); ?>" <?php if ($f_course === $c) { echo "selected"; } ?>><?php echo h($c); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg">
                        <label class="form-label small fw-semibold" for="fSemester">Semester / Year</label>
                        <select id="fSemester" name="f_semester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach ($allowed_semesters as $s) { ?>
                                <option value="<?php echo h($s); ?>" <?php if ($f_semester === $s) { echo "selected"; } ?>><?php echo h($s); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg">
                        <label class="form-label small fw-semibold" for="fSubject">Subject</label>
                        <select id="fSubject" name="f_subject" class="form-select">
                            <option value="0">All Subjects</option>
                            <?php foreach ($assigned_subjects as $as) { ?>
                                <option value="<?php echo (int)$as["id"]; ?>" <?php if ($f_subject === (int)$as["id"]) { echo "selected"; } ?>><?php echo h($as["subject_name"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg">
                        <label class="form-label small fw-semibold" for="fExam">Exam</label>
                        <select id="fExam" name="f_exam" class="form-select">
                            <option value="0">All Exams</option>
                            <?php foreach ($filter_exams as $fe) { ?>
                                <option value="<?php echo (int)$fe["id"]; ?>" <?php if ($f_exam === (int)$fe["id"]) { echo "selected"; } ?>><?php echo h($fe["exam_title"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-auto d-flex align-items-end gap-2">
                        <button class="btn btn-gradient flex-fill flex-lg-grow-0" type="submit"><i class="bi bi-funnel-fill me-1"></i>Apply Filters</button>
                        <?php if ($filters_active) { ?>
                            <a class="btn btn-outline-secondary" href="dashboard.php">Reset</a>
                        <?php } ?>
                    </div>
                </div>
            </form>

            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <?php
                $cards = array(
                    array("Assigned Subjects", $total_subjects, $f_subject > 0 ? "Filtered to 1 subject" : "Subjects in your scope", "bi-book-fill", "subjects.php" . ($f_subject > 0 ? "" : ""), "dash-tone-blue", "", "In scope"),
                    array("Total Exams", $total_exams, "Exams in current scope", "bi-calendar-check-fill", "exams.php", "dash-tone-green", "", $total_exams > 0 ? "Active" : "None yet"),
                    array("Question Bank", $total_questions, "MCQ questions in scope", "bi-patch-question-fill", "questions.php", "dash-tone-amber", "", $total_questions > 0 ? "Ready" : "Empty"),
                    array("Students", $total_students, "Students in current scope", "bi-people-fill", "results.php", "dash-tone-cyan", "", $pass_rate . "% pass"),
                    array("Average Score", $avg_score, "Average in current scope", "bi-speedometer2", "performance.php", "dash-tone-violet", "%", "Grade " . ($avg_score >= 90 ? "A+" : ($avg_score >= 75 ? "A" : ($avg_score >= 60 ? "B" : ($avg_score >= 40 ? "C" : "F"))))),
                    array("Pass Rate", $pass_rate, "Pass rate in current scope", "bi-award-fill", "performance.php", "dash-tone-rose", "%", $pass_count . " passed")
                );
                $fc_delay = 0;
                foreach ($cards as $card) {
                    $fc_delay++;
                ?>
                    <div class="col-6 col-lg-4 col-xl sp-fade-up" data-delay="<?php echo min($fc_delay, 6); ?>">
                        <div class="dash-stat <?php echo $card[5]; ?>">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $card[3]; ?>"></i></span>
                                <span class="dash-stat-trend"><?php echo h($card[7]); ?></span>
                            </div>
                            <div class="dash-stat-value" data-count="<?php echo is_numeric($card[1]) ? $card[1] : 0; ?>"<?php if ($card[6] !== "") { echo " data-suffix='" . h($card[6]) . "'"; } ?>><?php echo h($card[1]); ?><?php echo h($card[6]); ?></div>
                            <p class="dash-stat-label"><?php echo h($card[0]); ?></p>
                            <p class="dash-stat-sub"><?php echo h($card[2]); ?></p>
                            <a href="<?php echo $card[4]; ?>" class="dash-stat-link" aria-label="Open <?php echo h($card[0]); ?>"><span>Open</span><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Assigned Subjects Preview & Charts -->
            <div class="row g-4 mb-4">
                <div class="col-xl-7">
                    <div class="dashboard-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Subject Performance Analysis</h5>
                            <span class="badge bg-primary-soft text-primary">Your Subjects<?php if ($filters_active) { echo " • Filtered"; } ?></span>
                        </div>
                        <p class="text-muted small">Average student score across your assigned subjects<?php if ($f_course !== "" || $f_semester !== "") { echo " (" . h($f_course !== "" ? $f_course : "All courses") . " • " . h($f_semester !== "" ? $f_semester : "All semesters") . ")"; } ?>.</p>
                        <div class="chart-container chart-container-md"><canvas id="facultySubjectChart" role="img" aria-label="Subject performance bar chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="dashboard-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-book me-2 text-success"></i>My Assigned Subjects (<?php echo count($display_subjects); ?>)</h5>
                            <a href="subjects.php" class="small">View All</a>
                        </div>
                        <?php if (count($display_subjects) > 0) { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($display_subjects as $sub) {
                                    $sub_id = (int)$sub["id"];
                                ?>
                                    <div class="list-group-item px-0 py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="min-w-0">
                                                <strong class="d-block text-truncate"><?php echo h($sub["subject_name"]); ?></strong>
                                                <small class="text-muted"><?php echo h($sub["subject_code"]); ?> &bull; <?php echo (int)$sub["q_cnt"]; ?> Questions &bull; <?php echo (int)$sub["e_cnt"]; ?> Exams</small>
                                            </div>
                                            <div class="btn-group btn-group-sm flex-shrink-0">
                                                <a href="exams.php?subject_id=<?php echo $sub_id; ?>" class="btn btn-outline-primary" title="Exams"><i class="bi bi-calendar-check"></i></a>
                                                <a href="questions.php?subject_id=<?php echo $sub_id; ?>" class="btn btn-outline-secondary" title="Questions"><i class="bi bi-patch-question"></i></a>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge bg-primary-soft text-primary"><i class="bi bi-people me-1"></i><?php echo (int)$sub["students"]; ?> Students</span>
                                            <span class="badge bg-success-soft text-success"><i class="bi bi-graph-up me-1"></i><?php echo number_format((float)$sub["avgp"], 1); ?>% Avg</span>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle fs-2 d-block mb-2 text-warning"></i>
                                <p class="mb-0">No subjects currently assigned to your account.<br>Please contact the Administrator.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Recent Exams & Results -->
            <div class="row g-4">
                <div class="col-xl-6">
                    <div class="table-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2 text-info"></i>Recent Exams</h5>
                            <a href="exams.php" class="btn btn-sm btn-outline-primary">Create Exam</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Scope</th>
                                        <th>Qs / Attempts</th>
                                        <th>Avg</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_exams) > 0) {
                                        foreach ($recent_exams as $rex) { ?>
                                            <tr>
                                                <td>
                                                    <strong class="d-block"><?php echo h($rex["exam_title"]); ?></strong>
                                                    <small class="text-muted"><?php echo h($rex["subject_name"]); ?> &bull; <?php echo h($rex["exam_mode"] ?? "Standard"); ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($rex["scope_courses"]) || !empty($rex["scope_semesters"])) { ?>
                                                        <small class="text-muted d-block"><?php echo h(implode(", ", array_slice($rex["scope_courses"], 0, 2))); ?></small>
                                                        <small class="text-muted"><?php echo h(implode(", ", array_slice($rex["scope_semesters"], 0, 2))); ?><?php if ($rex["scope_more"] > 0) { echo " +" . (int)$rex["scope_more"]; } ?></small>
                                                    <?php } else { ?>
                                                        <small class="text-muted">No attempts yet</small>
                                                    <?php } ?>
                                                </td>
                                                <td><small><strong><?php echo (int)$rex["q_count"]; ?></strong> Qs<br><?php echo (int)$rex["attempts"]; ?> attempts</small></td>
                                                <td><strong><?php echo number_format((float)$rex["avgp"], 1); ?>%</strong></td>
                                                <td><span class="badge bg-<?php echo $rex["status"] == "Active" ? "success" : "secondary"; ?>"><?php echo h($rex["status"]); ?></span></td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No exams found for the current scope.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="table-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2 text-danger"></i>Recent Student Results</h5>
                            <a href="results.php" class="btn btn-sm btn-outline-primary">All Results</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Exam</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_results) > 0) {
                                        foreach ($recent_results as $rr) { ?>
                                            <tr>
                                                <td>
                                                    <strong class="d-block"><?php echo h($rr["student_name"] ?? "Student"); ?></strong>
                                                    <small class="text-muted"><?php echo h($rr["student_course"] ?? ""); ?><?php if (!empty($rr["student_course"]) && !empty($rr["student_semester"])) { echo " • "; } ?><?php echo h($rr["student_semester"] ?? ""); ?></small>
                                                </td>
                                                <td><small class="text-muted"><?php echo h($rr["exam_title"]); ?></small></td>
                                                <td><strong><?php echo number_format((float)$rr["percentage"], 1); ?>%</strong></td>
                                                <td><span class="badge bg-<?php echo $rr["status"] == "Pass" ? "success" : "danger"; ?>"><?php echo h($rr["status"]); ?></span></td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No student attempts yet for the current scope.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var ctx = document.getElementById('facultySubjectChart');
    if (ctx && typeof ChartManager !== "undefined") {
        ChartManager.create('facultySubjectChart', {
            type: 'bar',
            data: {
                labels: [<?php echo $chart_labels; ?>],
                datasets: [{
                    label: 'Avg Score (%)',
                    data: [<?php echo $chart_values; ?>],
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function(value) { return value + "%"; } }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>
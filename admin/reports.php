<?php
require_once("../app/config/database.php");
$conn = conn(); require_admin();
$asset_path = "../assets/"; $base_path = "../"; $page_title = "Reports & Analytics";

// ─── Filters ───────────────────────────────
$search   = clean_data($_GET['search'] ?? '');
$exam_id  = (int)($_GET['exam_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$status   = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$attempt_type = $_GET['attempt_type'] ?? 'latest'; // latest|highest|all
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// ─── Dashboard Summary ─────────────────────
$total_students = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM students"))['c'];
$total_exams    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams"))['c'];
$total_attempts = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results"))['c'];
$avg_score      = round((float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(percentage) AS a FROM results"))['a'], 1);
$pass_count     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE status='Pass'"))['c'];
$fail_count     = max(0, $total_attempts - $pass_count);
$pass_rate      = $total_attempts > 0 ? round(($pass_count / $total_attempts) * 100, 1) : 0;
$fail_rate      = round(100 - $pass_rate, 1);

// ─── AI Insights ────────────────────────────
$top_subject = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id ORDER BY avgp DESC LIMIT 1"));
$low_subject = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id ORDER BY avgp ASC LIMIT 1"));
$at_risk     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM results WHERE percentage < 40"))['c'];
$top_subject_name = $top_subject ? $top_subject['subject_name'] : 'N/A';
$low_subject_name = $low_subject ? $low_subject['subject_name'] : 'N/A';

// ─── Build WHERE for main query ────────────
$where = "1=1";
$params = [];
if ($search) { $s = mysqli_real_escape_string($conn, $search); $where .= " AND (st.name LIKE '%$s%' OR st.email LIKE '%$s%' OR e.exam_title LIKE '%$s%' OR sub.subject_name LIKE '%$s%')"; }
if ($exam_id) { $where .= " AND e.id=$exam_id"; }
if ($subject_id) { $where .= " AND e.subject_id=$subject_id"; }
if ($status === 'Pass') { $where .= " AND r.status='Pass'"; }
elseif ($status === 'Fail') { $where .= " AND r.status!='Pass'"; }
if ($date_from) { $df = mysqli_real_escape_string($conn, $date_from); $where .= " AND r.submitted_at >= '$df 00:00:00'"; }
if ($date_to)   { $dt = mysqli_real_escape_string($conn, $date_to);   $where .= " AND r.submitted_at <= '$dt 23:59:59'"; }

// ─── Attempt type filtering ────────────────
$attempt_subquery = "";
if ($attempt_type === 'latest') {
    $attempt_subquery = " AND r.id IN (SELECT MAX(r2.id) FROM results r2 WHERE r2.student_id=r.student_id GROUP BY r2.exam_id)";
} elseif ($attempt_type === 'highest') {
    $attempt_subquery = " AND r.id IN (SELECT r3.id FROM results r3 INNER JOIN (SELECT student_id, exam_id, MAX(percentage) AS maxp FROM results GROUP BY student_id, exam_id) best ON r3.student_id=best.student_id AND r3.exam_id=best.exam_id AND r3.percentage=best.maxp)";
}
$where .= $attempt_subquery;

// ─── Main query (paginated, grouped by student) ──
$countQ = mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS c FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where");
$total_students_filtered = (int)mysqli_fetch_assoc($countQ)['c'];
$total_pages = max(1, ceil($total_students_filtered / $per_page));
$offset = ($page - 1) * $per_page;

$studentsQ = mysqli_query($conn, "SELECT st.id, st.name, st.email, st.course, st.semester, st.profile_image,
    COUNT(r.id) AS attempt_count,
    AVG(r.percentage) AS avg_pct,
    SUM(CASE WHEN r.status='Pass' THEN 1 ELSE 0 END) AS pass_count,
    SUM(CASE WHEN r.status!='Pass' THEN 1 ELSE 0 END) AS fail_count
    FROM results r
    LEFT JOIN students st ON r.student_id=st.id
    LEFT JOIN exams e ON r.exam_id=e.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE $where
    GROUP BY st.id
    ORDER BY st.id ASC
    LIMIT $per_page OFFSET $offset");

function admin_reports_avatar($row) {
    $img = trim((string)($row["profile_image"] ?? ""));
    $name = (string)($row["name"] ?? "S");
    if ($img !== "" && file_exists(__DIR__ . "/../assets/uploads/profile/" . $img)) {
        return '<img src="../assets/uploads/profile/' . h($img) . '" alt="Profile photo of ' . h($name) . '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">';
    }
    return '<span class="rp-icon" style="width:36px;height:36px;border-radius:50%;background:#e0e7ff;color:#4338ca;font-size:.85rem;font-weight:700;flex-shrink:0;" aria-hidden="true">' . h(strtoupper(substr($name, 0, 1))) . '</span>';
}

// ─── Subject / Exam dropdown data ──────────
$subjects_list = mysqli_query($conn, "SELECT * FROM subjects ORDER BY id ASC");
$exams_list    = mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id ORDER BY e.id ASC");

// ─── Charts data ───────────────────────────
$filtered_summary_q = mysqli_query($conn, "SELECT COUNT(r.id) AS attempts, AVG(r.percentage) AS avgp, SUM(CASE WHEN r.status='Pass' THEN 1 ELSE 0 END) AS pass_count, SUM(CASE WHEN r.status!='Pass' THEN 1 ELSE 0 END) AS fail_count FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where");
$filtered_summary = $filtered_summary_q ? mysqli_fetch_assoc($filtered_summary_q) : array();
$chart_attempts = (int)($filtered_summary['attempts'] ?? 0);
$chart_pass_count = (int)($filtered_summary['pass_count'] ?? 0);
$chart_fail_count = (int)($filtered_summary['fail_count'] ?? 0);
$chart_avg_score = $chart_attempts > 0 ? round((float)($filtered_summary['avgp'] ?? 0), 1) : 0;
$pass_fail_data = "$chart_pass_count, $chart_fail_count";
$subject_chart_q = mysqli_query($conn, "SELECT sub.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where GROUP BY sub.id, sub.subject_name ORDER BY sub.subject_name");
$subj_chart_labels = ''; $subj_chart_values = '';
while ($r = mysqli_fetch_assoc($subject_chart_q)) {
    $subj_chart_labels .= "'" . addslashes($r['subject_name']) . "',";
    $subj_chart_values .= round((float)$r['avgp'], 1) . ",";
}
$attempts_chart_q = mysqli_query($conn, "SELECT DATE_FORMAT(r.submitted_at, '%Y-%m') AS ym, DATE_FORMAT(r.submitted_at, '%b %y') AS label, COUNT(*) AS c FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where GROUP BY ym ORDER BY ym ASC LIMIT 12");
$attempt_chart_labels = ''; $attempt_chart_values = '';
while ($r = mysqli_fetch_assoc($attempts_chart_q)) {
    $attempt_chart_labels .= "'" . addslashes($r['label']) . "',";
    $attempt_chart_values .= (int)$r['c'] . ",";
}

// ─── Difficulty chart ──────────────────────
$diff_data = [];
$stdQ = mysqli_query($conn, "SELECT q.difficulty, COUNT(sa.id) AS total, SUM(sa.is_correct) AS correct FROM student_answers sa LEFT JOIN questions q ON sa.question_id=q.id GROUP BY q.difficulty");
if ($stdQ) { while ($r = mysqli_fetch_assoc($stdQ)) { $d = $r['difficulty']; if (!isset($diff_data[$d])) $diff_data[$d] = ['total'=>0,'correct'=>0]; $diff_data[$d]['total'] += (int)$r['total']; $diff_data[$d]['correct'] += (int)$r['correct']; } }
$adpQ = mysqli_query($conn, "SELECT q.difficulty, COUNT(aa.id) AS total, SUM(aa.is_correct) AS correct FROM adaptive_answers aa LEFT JOIN questions q ON aa.question_id=q.id GROUP BY q.difficulty");
if ($adpQ) { while ($r = mysqli_fetch_assoc($adpQ)) { $d = $r['difficulty']; if (!isset($diff_data[$d])) $diff_data[$d] = ['total'=>0,'correct'=>0]; $diff_data[$d]['total'] += (int)$r['total']; $diff_data[$d]['correct'] += (int)$r['correct']; } }
$diff_labels = ''; $diff_values = '';
foreach (['Easy','Medium','Hard'] as $d) {
    $dt = $diff_data[$d] ?? ['total'=>0,'correct'=>0];
    $diff_labels .= "'$d',";
    $diff_values .= ($dt['total']>0?round(($dt['correct']/$dt['total'])*100,1):0).",";
}

include("../app/layouts/admin_header.php");
?>
<div class="container-fluid px-0">
<div class="row g-0">
<div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
<div class="col-lg-10 p-3 p-md-4" style="background:#f1f5f9;min-height:100vh;">

<!-- ═══ HEADER ═══ -->
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb dashboard-breadcrumb"><li class="breadcrumb-item">Admin</li><li class="breadcrumb-item active">Reports &amp; Analytics</li></ol></nav>
<div class="sp-page-head sp-fade-up">
    <div>
        <span class="sp-eyebrow"><i class="bi bi-bar-chart-line"></i> Analytics &amp; Results</span>
        <h1>Reports &amp; Analytics</h1>
        <p>Enterprise-grade performance overview.</p>
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown rp-export-dd">
            <button type="button" class="rp-export-btn dropdown-toggle" data-bs-toggle="dropdown" data-bs-offset="0,8" aria-expanded="false" aria-label="Export reports"><i class="bi bi-download" aria-hidden="true"></i>Export</button>
            <ul class="dropdown-menu dropdown-menu-end" aria-label="Export options">
                <li><h6 class="dropdown-header">PDF Reports</h6></li>
                <li><a class="dropdown-item" href="reports_export.php?type=exam_pdf&<?php echo $_SERVER['QUERY_STRING']; ?>"><i class="bi bi-file-pdf text-danger"></i>Exam Report PDF</a></li>
                <li><a class="dropdown-item" href="reports_export.php?type=analytics_pdf&<?php echo $_SERVER['QUERY_STRING']; ?>"><i class="bi bi-file-pdf text-danger"></i>Analytics PDF</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Spreadsheets</h6></li>
                <li><a class="dropdown-item" href="reports_export.php?type=excel&<?php echo $_SERVER['QUERY_STRING']; ?>"><i class="bi bi-file-earmark-excel text-success"></i>Export Excel</a></li>
                <li><a class="dropdown-item" href="reports_export.php?type=csv&<?php echo $_SERVER['QUERY_STRING']; ?>"><i class="bi bi-file-earmark-spreadsheet text-primary"></i>Export CSV</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="window.print();return false;"><i class="bi bi-printer text-secondary"></i>Print Page</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- ═══ DASHBOARD CARDS ═══ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3 sp-fade-up" data-delay="1">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#dbeafe;color:#1d4ed8"><i class="bi bi-people"></i></div>
            <div><h3 data-count="<?php echo $total_students; ?>">0</h3><small>Total Students</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="2">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#d1fae5;color:#065f46"><i class="bi bi-journal"></i></div>
            <div><h3 data-count="<?php echo $total_exams; ?>">0</h3><small>Total Exams</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="3">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-clock-history"></i></div>
            <div><h3 data-count="<?php echo $total_attempts; ?>">0</h3><small>Total Attempts</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="4">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#e0e7ff;color:#4338ca"><i class="bi bi-graph-up"></i></div>
            <div><h3><?php echo $avg_score; ?>%</h3><small>Average Score</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="5">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#d1fae5;color:#065f46"><i class="bi bi-check-circle"></i></div>
            <div><h3><?php echo $pass_rate; ?>%</h3><small>Pass Rate</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="6">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#fee2e2;color:#991b1b"><i class="bi bi-x-circle"></i></div>
            <div><h3><?php echo $fail_rate; ?>%</h3><small>Fail Rate</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="1">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#f3e8ff;color:#7c3aed"><i class="bi bi-trophy"></i></div>
            <div><h3 style="font-size:1rem"><?php echo h($top_subject_name); ?></h3><small>Top Subject</small></div>
        </div>
    </div>
    <div class="col-6 col-md-3 sp-fade-up" data-delay="2">
        <div class="rp-dash-card d-flex align-items-center gap-3">
            <div class="rp-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-exclamation-triangle"></i></div>
            <div><h3 data-count="<?php echo $at_risk; ?>">0</h3><small>Students at Risk</small></div>
        </div>
    </div>
</div>

<!-- ═══ AI INSIGHTS ═══ -->
<div class="rp-ai-card mb-3 d-flex flex-wrap align-items-center gap-3 sp-fade-up" data-delay="3">
    <i class="bi bi-robot fs-3 text-primary"></i>
    <div class="flex-grow-1">
        <strong>AI Summary</strong>
        <div class="d-flex flex-wrap gap-2 mt-1 small">
            <span class="rp-badge rp-badge-ex"><i class="bi bi-arrow-up-circle"></i> Top: <?php echo h($top_subject_name); ?></span>
            <span class="rp-badge rp-badge-avg"><i class="bi bi-arrow-down-circle"></i> Lowest: <?php echo h($low_subject_name); ?></span>
            <span class="rp-badge rp-badge-fail"><i class="bi bi-exclamation-circle"></i> At Risk: <?php echo $at_risk; ?> students</span>
            <span class="rp-badge rp-badge-pass"><i class="bi bi-graph-up"></i> Avg: <?php echo $avg_score; ?>%</span>
        </div>
    </div>
</div>

<!-- ═══ FILTERS ═══ -->
<form method="get" class="toolbar-card mb-3 sp-fade-up">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold mb-1" for="rpSearch">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input id="rpSearch" name="search" value="<?php echo h($search); ?>" class="form-control" placeholder="Name, email, exam, subject..." aria-label="Search">
            </div>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1" for="rpExam">Exam</label>
            <select name="exam_id" id="rpExam" class="form-select">
                <option value="">All Exams</option>
                <?php if ($exams_list) { mysqli_data_seek($exams_list,0); while ($e = mysqli_fetch_assoc($exams_list)) { ?>
                <option value="<?php echo $e['id']; ?>" <?php if ($exam_id==$e['id']) echo 'selected'; ?>><?php echo h($e['exam_title']); ?></option>
                <?php } } ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1" for="rpSubject">Subject</label>
            <select name="subject_id" id="rpSubject" class="form-select">
                <option value="">All Subjects</option>
                <?php if ($subjects_list) { mysqli_data_seek($subjects_list,0); while ($s = mysqli_fetch_assoc($subjects_list)) { ?>
                <option value="<?php echo $s['id']; ?>" <?php if ($subject_id==$s['id']) echo 'selected'; ?>><?php echo h($s['subject_name']); ?></option>
                <?php } } ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small fw-semibold mb-1" for="rpStatus">Status</label>
            <select name="status" id="rpStatus" class="form-select">
                <option value="">All</option>
                <option value="Pass" <?php if($status=='Pass') echo 'selected'; ?>>Pass</option>
                <option value="Fail" <?php if($status=='Fail') echo 'selected'; ?>>Fail</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1" for="rpFrom">From Date</label>
            <input type="date" id="rpFrom" name="date_from" class="form-control" value="<?php echo h($date_from); ?>">
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small fw-semibold mb-1" for="rpTo">To Date</label>
            <input type="date" id="rpTo" name="date_to" class="form-control" value="<?php echo h($date_to); ?>">
        </div>
        <div class="col-6 col-md-1 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-outline-secondary" href="reports.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
    <div class="row g-2 mt-2">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small fw-semibold"><i class="bi bi-layers me-1"></i>Attempt view:</span>
                <div class="btn-group btn-group-sm attempt-toggle" role="group" aria-label="Attempt type">
                    <input type="radio" class="btn-check" name="attempt_type" id="at_latest" value="latest" <?php if($attempt_type=='latest') echo 'checked'; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="at_latest"><i class="bi bi-clock me-1"></i>Latest</label>
                    <input type="radio" class="btn-check" name="attempt_type" id="at_highest" value="highest" <?php if($attempt_type=='highest') echo 'checked'; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="at_highest"><i class="bi bi-trophy me-1"></i>Highest</label>
                    <input type="radio" class="btn-check" name="attempt_type" id="at_all" value="all" <?php if($attempt_type=='all') echo 'checked'; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="at_all"><i class="bi bi-list me-1"></i>All</label>
                </div>
                <small class="text-muted">Latest = most recent attempt &middot; Highest = best score &middot; All = every attempt</small>
            </div>
        </div>
    </div>
    <?php
    $rp_chips = array();
    if ($search !== "") { $rp_chips[] = array("Search: " . $search, "reports.php" . admin_qs(array("search" => $search, "exam_id" => $exam_id, "subject_id" => $subject_id, "status" => $status, "date_from" => $date_from, "date_to" => $date_to, "attempt_type" => $attempt_type), array("search" => "", "page" => 1))); }
    if ($exam_id > 0) {
        $rp_en = "Exam #" . $exam_id;
        if ($exams_list) { mysqli_data_seek($exams_list, 0); while ($ee = mysqli_fetch_assoc($exams_list)) { if ((int)$ee["id"] === $exam_id) { $rp_en = $ee["exam_title"]; break; } } }
        $rp_chips[] = array("Exam: " . $rp_en, "reports.php" . admin_qs(array("search" => $search, "exam_id" => $exam_id, "subject_id" => $subject_id, "status" => $status, "date_from" => $date_from, "date_to" => $date_to, "attempt_type" => $attempt_type), array("exam_id" => "", "page" => 1)));
    }
    if ($subject_id > 0) {
        $rp_sn = "Subject #" . $subject_id;
        if ($subjects_list) { mysqli_data_seek($subjects_list, 0); while ($se = mysqli_fetch_assoc($subjects_list)) { if ((int)$se["id"] === $subject_id) { $rp_sn = $se["subject_name"]; break; } } }
        $rp_chips[] = array("Subject: " . $rp_sn, "reports.php" . admin_qs(array("search" => $search, "exam_id" => $exam_id, "subject_id" => $subject_id, "status" => $status, "date_from" => $date_from, "date_to" => $date_to, "attempt_type" => $attempt_type), array("subject_id" => "", "page" => 1)));
    }
    if ($status !== "") { $rp_chips[] = array("Status: " . $status, "reports.php" . admin_qs(array("search" => $search, "exam_id" => $exam_id, "subject_id" => $subject_id, "status" => $status, "date_from" => $date_from, "date_to" => $date_to, "attempt_type" => $attempt_type), array("status" => "", "page" => 1))); }
    if ($date_from !== "" || $date_to !== "") { $rp_chips[] = array("Date: " . ($date_from !== "" ? $date_from : "…") . " to " . ($date_to !== "" ? $date_to : "…"), "reports.php" . admin_qs(array("search" => $search, "exam_id" => $exam_id, "subject_id" => $subject_id, "status" => $status, "date_from" => $date_from, "date_to" => $date_to, "attempt_type" => $attempt_type), array("date_from" => "", "date_to" => "", "page" => 1))); }
    admin_chips($rp_chips, "reports.php");
    ?>
</form>

<!-- ═══ CHARTS ═══ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12 col-md-6 sp-fade-up" data-delay="1">
        <div class="rp-chart-box"><h6 class="fw-bold mb-2 small">Pass vs Fail</h6><div class="chart-container chart-container-doughnut"><canvas id="chartPassFail" role="img" aria-label="Pass versus fail doughnut chart"></canvas></div><div class="chart-empty-state" id="chartPassFailEmpty">No Data Available</div></div>
    </div>
    <div class="col-12 col-md-6 sp-fade-up" data-delay="2">
        <div class="rp-chart-box"><h6 class="fw-bold mb-2 small">Avg Score</h6><div class="chart-container chart-container-sm"><canvas id="chartAvgScore" role="img" aria-label="Average score bar chart"></canvas></div><div class="chart-empty-state" id="chartAvgScoreEmpty">No Data Available</div></div>
    </div>
    <div class="col-12 col-md-6 sp-fade-up" data-delay="3">
        <div class="rp-chart-box"><h6 class="fw-bold mb-2 small">Subject Performance</h6><div class="chart-container chart-container-md"><canvas id="chartSubject" role="img" aria-label="Subject performance bar chart"></canvas></div><div class="chart-empty-state" id="chartSubjectEmpty">No Data Available</div></div>
    </div>
    <div class="col-12 col-md-6 sp-fade-up" data-delay="4">
        <div class="rp-chart-box"><h6 class="fw-bold mb-2 small">Performance Trend (Monthly Attempts)</h6><div class="chart-container chart-container-md"><canvas id="chartAttempts" role="img" aria-label="Monthly attempts line chart"></canvas></div><div class="chart-empty-state" id="chartAttemptsEmpty">No Data Available</div></div>
    </div>
</div>

<!-- ═══ MAIN TABLE ═══ -->
<div class="rp-table-card sp-fade-up">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 pt-3 pb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-people me-1"></i>Student Performance Summary</h6>
        <small class="text-muted"><?php echo $total_students_filtered; ?> students found</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle admin-table mb-0" id="reportsTable">
            <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th>Student</th>
                    <th>Attempts</th>
                    <th>Avg Score</th>
                    <th>Pass</th>
                    <th>Fail</th>
                    <th>Status</th>
                    <th style="width:80px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($studentsQ && mysqli_num_rows($studentsQ) > 0) {
                    $student_ids = [];
                    $student_rows = [];
                    while ($s = mysqli_fetch_assoc($studentsQ)) {
                        $student_ids[] = (int)$s['id'];
                        $student_rows[] = $s;
                    }
                    // Pre-fetch attempts for displayed students
                    $sid_list = implode(',', $student_ids);
                    $attempts_map = [];
                    $attQ = mysqli_query($conn, "SELECT r.*, e.exam_title, e.exam_mode, sub.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE r.student_id IN ($sid_list) ORDER BY r.student_id ASC, r.id ASC");
                    if ($attQ) { while ($a = mysqli_fetch_assoc($attQ)) { $sid = (int)$a['student_id']; if (!isset($attempts_map[$sid])) $attempts_map[$sid] = []; $attempts_map[$sid][] = $a; } }
                    foreach ($student_rows as $s) { $sid = (int)$s['id']; $attempts = $attempts_map[$sid] ?? []; $sc = round((float)$s['avg_pct'], 1); ?>
                <tr class="rp-student-row" data-student="<?php echo $sid; ?>" onclick="toggleStudent(<?php echo $sid; ?>)">
                    <td data-label="Expand"><i class="bi bi-chevron-right text-muted" id="icon_<?php echo $sid; ?>" style="transition:.2s"></i></td>
                    <td data-label="Student">
                        <div class="d-flex align-items-center gap-2">
                            <?php echo admin_reports_avatar($s); ?>
                            <div class="admin-cell-main"><strong><?php echo h($s['name']); ?></strong><small class="text-muted"><?php echo h($s['email']); ?></small></div>
                        </div>
                    </td>
                    <td data-label="Attempts"><span class="fw-bold"><?php echo (int)$s['attempt_count']; ?></span></td>
                    <td data-label="Avg Score"><span class="fw-bold"><?php echo $sc; ?>%</span></td>
                    <td data-label="Pass"><span class="text-success fw-bold"><?php echo (int)$s['pass_count']; ?></span></td>
                    <td data-label="Fail"><span class="text-danger fw-bold"><?php echo (int)$s['fail_count']; ?></span></td>
                    <td data-label="Status">
                        <?php if ($sc >= 75) { ?><span class="rp-badge rp-badge-ex"><i class="bi bi-star-fill"></i> Excellent</span>
                        <?php } elseif ($sc >= 50) { ?><span class="rp-badge rp-badge-pass"><i class="bi bi-check-circle"></i> Pass</span>
                        <?php } elseif ($sc >= 35) { ?><span class="rp-badge rp-badge-avg"><i class="bi bi-exclamation-circle"></i> Average</span>
                        <?php } else { ?><span class="rp-badge rp-badge-fail"><i class="bi bi-x-circle"></i> Fail</span><?php } ?>
                    </td>
                    <td data-label="Action">
                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();viewStudent(<?php echo $sid; ?>)" title="View Details" aria-label="View details"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>
                <tr class="rp-detail-row" id="detail_<?php echo $sid; ?>">
                    <td colspan="8">
                        <div class="rp-detail-inner">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($attempts as $aidx => $att) {
                                    $apct = round((float)$att['percentage'],1);
                                    $isAd = ($att['exam_mode'] ?? 'Standard') === 'Adaptive';
                                ?>
                                <div class="rp-attempt-item" style="flex:1;min-width:200px;max-width:300px;">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong style="font-size:.8rem"><?php echo h($att['exam_title']); ?></strong>
                                            <div class="small text-muted"><?php echo h($att['subject_name'] ?? ''); ?> <?php if($isAd){?><span class="badge bg-info text-dark" style="font-size:.6rem">Adaptive</span><?php } ?></div>
                                        </div>
                                        <span class="fw-bold" style="font-size:.9rem;color:<?php echo $apct>=50?'#10b981':'#ef4444'; ?>"><?php echo $apct; ?>%</span>
                                    </div>
                                    <div class="small text-muted d-flex gap-2 mt-1">
                                        <span>Marks: <?php echo (int)$att['marks_obtained']; ?>/<?php echo max((int)$att['total_questions'],1); ?></span>
                                        <span>Status: <?php echo $att['status']; ?></span>
                                        <span><?php echo h($att['submitted_at'] ?? ''); ?></span>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <?php if (count($attempts) == 0) { ?><div class="text-muted small py-2">No attempts found.</div><?php } ?>
                        </div>
                    </td>
                </tr>
                <?php } } else { ?>
                <tr><td colspan="8" class="text-center empty-state py-4">No results found matching your filters.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1) { ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
        <small class="text-muted">Showing <?php echo ($page-1)*$per_page+1; ?>-<?php echo min($page*$per_page, $total_students_filtered); ?> of <?php echo $total_students_filtered; ?></small>
        <div class="client-pagination">
            <?php for ($i=1; $i<=$total_pages; $i++) { ?>
            <a class="btn btn-sm <?php echo $i==$page?'btn-primary':'btn-outline-primary'; ?>" href="?<?php $q = $_GET; $q['page']=$i; echo http_build_query($q); ?>"><?php echo $i; ?></a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>

<!-- ═══ VIEW DETAILS MODAL ═══ -->
<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content professional-modal">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person me-2"></i>Student Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="viewModalBody">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading...</p></div>
    </div>
    <div class="modal-footer d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary btn-sm flex-shrink-0" data-bs-dismiss="modal">Close</button>
        <a class="rp-export-btn flex-fill text-center" id="viewDownloadPdf" href="#" style="text-decoration:none;justify-content:center;" onclick="if(this.getAttribute('href')==='#'){return false;}"><i class="bi bi-download" aria-hidden="true"></i>Download Student Report PDF</a>
    </div>
</div></div></div>

</div><!-- /col-lg-10 -->
</div><!-- /row -->
</div><!-- /container-fluid -->

<script>
function toggleStudent(id) {
    var row = document.getElementById('detail_'+id);
    var icon = document.getElementById('icon_'+id);
    if (!row) return;
    var show = !row.classList.contains('show');
    document.querySelectorAll('.rp-detail-row.show').forEach(function(r) { if (r.id !== 'detail_'+id) { r.classList.remove('show'); var ic = document.getElementById('icon_'+r.id.replace('detail_','')); if(ic) ic.style.transform='rotate(0deg)'; } });
    document.querySelectorAll('.rp-student-row.expanded').forEach(function(r) { if (r.dataset.student != id) r.classList.remove('expanded'); });
    row.classList.toggle('show', show);
    document.querySelector('.rp-student-row[data-student="'+id+'"]').classList.toggle('expanded', show);
    icon.style.transform = show ? 'rotate(90deg)' : 'rotate(0deg)';
}
function viewStudent(id) {
    var modal = new bootstrap.Modal(document.getElementById('viewModal'));
    document.getElementById('viewModalBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading...</p></div>';
    document.getElementById('viewDownloadPdf').href = 'reports_export.php?type=student_pdf&student_id='+id;
    modal.show();
    fetch('reports_ajax.php?student_id='+id)
        .then(function(r){return r.text();})
        .then(function(html){document.getElementById('viewModalBody').innerHTML=html;})
        .catch(function(){document.getElementById('viewModalBody').innerHTML='<div class="alert alert-danger">Failed to load student data.</div>';});
}
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ChartManager === 'undefined') { return; }
    function showNoData(canvasId, emptyId, values) {
        var canvas = document.getElementById(canvasId);
        var empty = document.getElementById(emptyId);
        var hasData = values.some(function(value) { return Number(value) > 0; });
        if (canvas) canvas.style.display = hasData ? '' : 'none';
        if (empty) empty.classList.toggle('show', !hasData);
        return hasData;
    }
    var passFailValues = [<?php echo $pass_fail_data; ?>];
    var avgValues = [<?php echo $chart_avg_score; ?>];
    var subjectValues = [<?php echo $subj_chart_values; ?>];
    var attemptValues = [<?php echo $attempt_chart_values; ?>];
    if (showNoData('chartPassFail', 'chartPassFailEmpty', passFailValues)) ChartManager.create('chartPassFail', {type:'doughnut',data:{labels:['Pass','Fail'],datasets:[{data:passFailValues,backgroundColor:['#10b981','#ef4444'],borderWidth:2,borderColor:'#fff'}]},options:{cutout:'65%',plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){var t=passFailValues[0]+passFailValues[1];var p=t>0?Math.round(ctx.parsed/t*100):0;return ' '+ctx.label+': '+ctx.parsed+' ('+p+'%)';}}}}}});
    if (showNoData('chartAvgScore', 'chartAvgScoreEmpty', avgValues)) ChartManager.create('chartAvgScore', {type:'bar',data:{labels:['Avg Score'],datasets:[{data:avgValues,backgroundColor:'#2454d6',borderRadius:6,maxBarThickness:72}]},options:{scales:{y:{beginAtZero:true,max:100,ticks:{callback:function(v){return v+'%';}}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return ' '+ctx.parsed.y+'%';}}}}}});
    if (showNoData('chartSubject', 'chartSubjectEmpty', subjectValues)) ChartManager.create('chartSubject', {type:'bar',data:{labels:[<?php echo $subj_chart_labels; ?>],datasets:[{data:subjectValues,backgroundColor:'#14b8a6',borderRadius:4}]},options:{indexAxis:'y',scales:{x:{beginAtZero:true,max:100,ticks:{callback:function(v){return v+'%';}}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return ' '+ctx.parsed.x+'%';}}}}}});
    if (showNoData('chartAttempts', 'chartAttemptsEmpty', attemptValues)) ChartManager.create('chartAttempts', {type:'line',data:{labels:[<?php echo $attempt_chart_labels; ?>],datasets:[{data:attemptValues,borderColor:'#8b5cf6',backgroundColor:'rgba(139,92,246,.12)',fill:true,tension:.35,pointRadius:3.5,pointBackgroundColor:'#8b5cf6',borderWidth:2.5}]},options:{scales:{y:{beginAtZero:true,ticks:{stepSize:1,precision:0}},x:{ticks:{maxRotation:45,minRotation:0,autoSkip:true,maxTicksLimit:8}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return ' '+ctx.parsed.y+' attempts';}}}}}});
});
</script>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

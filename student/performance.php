<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Performance";
include("../app/layouts/student_header.php");

$student_id = (int)$_SESSION["student_id"];

$overall = null;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS completed, AVG(percentage) AS avgp, AVG(marks_obtained) AS avg_marks,
    MAX(percentage) AS highest, MIN(percentage) AS lowest,
    SUM(correct_answers) AS correct_total, SUM(wrong_answers) AS wrong_total, SUM(total_questions) AS total_questions,
    SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) AS passed,
    SUM(CASE WHEN status='Fail' THEN 1 ELSE 0 END) AS failed
    FROM results WHERE student_id=?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $overall = $r ? mysqli_fetch_assoc($r) : null;
    mysqli_stmt_close($stmt);
}

$total_active = 0;
$ta = mysqli_query($conn, "SELECT COUNT(*) AS total FROM exams WHERE status='Active'");
if ($ta) { $total_active = (int)mysqli_fetch_assoc($ta)["total"]; }

$completed = (int)($overall["completed"] ?? 0);
$pending = max($total_active - $completed, 0);
$avgp = $overall && $overall["avgp"] !== null ? round((float)$overall["avgp"], 2) : 0;
$avg_marks = $overall && $overall["avg_marks"] !== null ? round((float)$overall["avg_marks"], 2) : 0;
$highest = $overall && $overall["highest"] !== null ? round((float)$overall["highest"], 2) : 0;
$lowest = $overall && $overall["lowest"] !== null ? round((float)$overall["lowest"], 2) : 0;

$correct_total = (int)($overall["correct_total"] ?? 0);
$wrong_total = (int)($overall["wrong_total"] ?? 0);
$total_q = (int)($overall["total_questions"] ?? 0);
$skipped = max($total_q - $correct_total - $wrong_total, 0);
$accuracy = $total_q > 0 ? round(($correct_total / $total_q) * 100, 2) : 0;

$grade = $avgp >= 90 ? "A+" : ($avgp >= 75 ? "A" : ($avgp >= 60 ? "B" : ($avgp >= 40 ? "C" : ($completed > 0 ? "F" : "N/A"))));
$overall_status = $completed > 0 ? ($avgp >= 40 ? "Pass" : "Fail") : "No Data";
$attempt_percentage = $total_active > 0 ? round(($completed / $total_active) * 100, 2) : 0;

$subject_stmt = mysqli_prepare($conn, "SELECT s.subject_name, COUNT(r.id) AS exams_taken, AVG(r.percentage) AS avgp
    FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id
    WHERE r.student_id=? GROUP BY s.id ORDER BY avgp DESC");
$subjects = array();
if ($subject_stmt) {
    mysqli_stmt_bind_param($subject_stmt, "i", $student_id);
    mysqli_stmt_execute($subject_stmt);
    $sr = mysqli_stmt_get_result($subject_stmt);
    if ($sr) {
        while ($row = mysqli_fetch_assoc($sr)) { $subjects[] = $row; }
    }
    mysqli_stmt_close($subject_stmt);
}

$diff_stmt = mysqli_prepare($conn, "SELECT difficulty, SUM(total) AS total, SUM(correct) AS correct FROM (
    SELECT q.difficulty, COUNT(sa.id) AS total, SUM(sa.is_correct) AS correct
    FROM student_answers sa LEFT JOIN questions q ON sa.question_id=q.id
    WHERE sa.student_id=? GROUP BY q.difficulty
    UNION ALL
    SELECT q.difficulty, COUNT(aa.id) AS total, SUM(aa.is_correct) AS correct
    FROM adaptive_answers aa
    LEFT JOIN adaptive_sessions aas ON aa.session_id=aas.id
    LEFT JOIN questions q ON aa.question_id=q.id
    WHERE aas.student_id=? GROUP BY q.difficulty
) AS combined GROUP BY difficulty ORDER BY FIELD(difficulty, 'Easy', 'Medium', 'Hard')");
$difficulty = array();
if ($diff_stmt) {
    mysqli_stmt_bind_param($diff_stmt, "ii", $student_id, $student_id);
    mysqli_stmt_execute($diff_stmt);
    $dr = mysqli_stmt_get_result($diff_stmt);
    if ($dr) {
        while ($row = mysqli_fetch_assoc($dr)) { $difficulty[] = $row; }
    }
    mysqli_stmt_close($diff_stmt);
}

$history_stmt = mysqli_prepare($conn, "SELECT e.exam_title, r.percentage, r.submitted_at
    FROM results r LEFT JOIN exams e ON r.exam_id=e.id
    WHERE r.student_id=? ORDER BY r.id ASC LIMIT 10");
$history = array();
if ($history_stmt) {
    mysqli_stmt_bind_param($history_stmt, "i", $student_id);
    mysqli_stmt_execute($history_stmt);
    $hr = mysqli_stmt_get_result($history_stmt);
    if ($hr) {
        while ($row = mysqli_fetch_assoc($hr)) { $history[] = $row; }
    }
    mysqli_stmt_close($history_stmt);
}

$subj_labels = ""; $subj_values = "";
foreach ($subjects as $s) {
    $subj_labels .= "'" . addslashes($s["subject_name"] ?? "Unknown") . "',";
    $subj_values .= round((float)$s["avgp"], 2) . ",";
}
$hist_labels = ""; $hist_values = "";
foreach ($history as $h) {
    $hist_labels .= "'" . addslashes($h["exam_title"] ?? "Exam") . "',";
    $hist_values .= round((float)$h["percentage"], 2) . ",";
}
// Trend insight caption (real numbers for the chart footer).
$trend_latest = $trend_best = $trend_delta = null;
if (count($history) >= 1) {
    $vals = array_map(function ($h) { return round((float)$h["percentage"], 1); }, $history);
    $trend_latest = end($vals);
    $trend_best = max($vals);
    $trend_delta = count($vals) >= 2 ? round($trend_latest - $vals[count($vals) - 2], 1) : 0;
}

$strong_areas = array();
$weak_areas = array();
foreach ($subjects as $s) {
    $sp = (float)$s["avgp"];
    if ($sp >= 70) { $strong_areas[] = $s["subject_name"] . " (" . number_format($sp, 0) . "%)"; }
    elseif ($sp < 40) { $weak_areas[] = $s["subject_name"] . " (" . number_format($sp, 0) . "%)"; }
}
foreach ($difficulty as $d) {
    $dt = (int)$d["total"];
    $dc = (int)$d["correct"];
    $acc = $dt > 0 ? round(($dc / $dt) * 100) : 0;
    if ($dt >= 3 && $acc >= 80) { $strong_areas[] = $d["difficulty"] . " questions (" . $acc . "%)"; }
    if ($dt >= 3 && $acc < 40) { $weak_areas[] = $d["difficulty"] . " questions (" . $acc . "%)"; }
}
$strong_areas = array_slice(array_values(array_unique($strong_areas)), 0, 6);
$weak_areas = array_slice(array_values(array_unique($weak_areas)), 0, 6);
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-graph-up-arrow"></i> Analytics</span>
                        <h1>Performance Analysis</h1>
                        <p>Track your scores, accuracy, subject strengths and improvement areas.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="sp-btn sp-btn-primary" onclick="if(window.StudentAI){StudentAI.ask('Analyze my current performance and explain my strengths, areas that need improvement, and what I should focus on next.');}"><i class="bi bi-robot"></i> Ask AI to Analyze</button>
                        <a href="result.php" class="sp-btn sp-btn-soft"><i class="bi bi-list-check"></i> Detailed Results</a>
                    </div>
                </div>

                <?php if ($completed > 0) { ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="1">
                        <div class="sp-stat <?php echo $overall_status === "Pass" ? "is-success" : "is-danger"; ?>">
                            <span class="sp-stat-icon"><i class="bi bi-percent"></i></span>
                            <div class="sp-stat-value"><?php echo $avgp; ?>%</div>
                            <div class="sp-stat-label">Overall Score</div>
                            <div class="sp-stat-foot">Grade <?php echo h($grade); ?> &middot; <?php echo h($overall_status); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="2">
                        <div class="sp-stat is-info">
                            <span class="sp-stat-icon"><i class="bi bi-bullseye"></i></span>
                            <div class="sp-stat-value"><?php echo $accuracy; ?>%</div>
                            <div class="sp-stat-label">Accuracy</div>
                            <div class="sp-stat-foot"><?php echo $correct_total; ?> of <?php echo $total_q; ?> correct</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="3">
                        <div class="sp-stat is-indigo">
                            <span class="sp-stat-icon"><i class="bi bi-journal-check"></i></span>
                            <div class="sp-stat-value"><?php echo $completed; ?></div>
                            <div class="sp-stat-label">Exams Attempted</div>
                            <div class="sp-stat-foot"><?php echo $attempt_percentage; ?>% of active exams</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="4">
                        <div class="sp-stat is-warning">
                            <span class="sp-stat-icon"><i class="bi bi-bar-chart"></i></span>
                            <div class="sp-stat-value"><?php echo number_format($avg_marks, 1); ?></div>
                            <div class="sp-stat-label">Average Marks</div>
                            <div class="sp-stat-foot">Best <?php echo number_format($highest, 1); ?>% &middot; Low <?php echo number_format($lowest, 1); ?>%</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-8 sp-fade-up">
                        <div class="sp-panel">
                            <div class="sp-panel-head">
                                <h3><i class="bi bi-graph-up me-2 text-primary"></i>Performance Trend</h3>
                                <span class="small text-muted">Last <?php echo count($history); ?> attempts</span>
                            </div>
                            <?php if (count($history) >= 2) { ?>
                                <div class="chart-container chart-container-md"><canvas id="studentTrendChart" role="img" aria-label="Performance trend line chart"></canvas></div>
                                <?php if ($trend_latest !== null) { ?>
                                <div class="d-flex flex-wrap gap-2 mt-2 small">
                                    <span class="sp-pill is-available"><i class="bi bi-flag"></i>Latest <?php echo $trend_latest; ?>%</span>
                                    <span class="sp-pill is-completed"><i class="bi bi-trophy"></i>Best <?php echo $trend_best; ?>%</span>
                                    <span class="sp-pill <?php echo $trend_delta >= 0 ? "is-completed" : "is-expired"; ?>"><i class="bi bi-activity"></i><?php echo ($trend_delta >= 0 ? "+" : "") . $trend_delta; ?>% vs previous</span>
                                </div>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="text-muted small mb-0">Complete at least two exams to see your performance trend over time.</p>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-xl-4 sp-fade-up" data-delay="2">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3><i class="bi bi-pie-chart me-2 text-primary"></i>Answer Split</h3></div>
                            <div class="chart-container chart-container-doughnut" style="position:relative;"><canvas id="studentAnswerChart" role="img" aria-label="Answer split doughnut chart"></canvas><div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events:none;"><div class="fw-bold fs-4"><?php echo $accuracy; ?>%</div><small class="text-muted">accuracy</small></div></div>
                            <div class="d-flex flex-wrap justify-content-center gap-2 mt-2 small">
                                <span class="sp-pill is-completed"><i class="bi bi-check-circle"></i><?php echo $correct_total; ?> correct</span>
                                <span class="sp-pill is-expired"><i class="bi bi-x-circle"></i><?php echo $wrong_total; ?> wrong</span>
                                <span class="sp-pill"><?php echo $skipped; ?> skipped</span>
                            </div></div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-7 sp-fade-up">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3><i class="bi bi-book me-2 text-primary"></i>Subject-wise Performance</h3></div>
                            <?php if (!empty($subjects)) { ?>
                                <?php foreach ($subjects as $s) { $sp = round((float)$s["avgp"], 1); $cls = $sp >= 70 ? "is-success" : ($sp >= 40 ? "is-warning" : "is-danger"); ?>
                                    <div class="sp-subject-row">
                                        <div class="sp-subject-top">
                                            <span class="sp-subject-name"><i class="bi bi-bookmark-star text-primary"></i><?php echo h($s["subject_name"] ?? "Unknown"); ?></span>
                                            <span class="sp-subject-score"><?php echo $sp; ?>%</span>
                                        </div>
                                        <div class="sp-track <?php echo $cls; ?>"><span data-sp-progress="<?php echo $sp; ?>"></span></div>
                                        <small class="text-muted"><?php echo (int)$s["exams_taken"]; ?> exam<?php echo (int)$s["exams_taken"] === 1 ? "" : "s"; ?> attempted</small>
                                    </div>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="text-muted small mb-0">No subject performance data yet.</p>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-xl-5 sp-fade-up" data-delay="2">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3><i class="bi bi-sliders me-2 text-primary"></i>Difficulty-wise Accuracy</h3></div>
                            <?php if (!empty($difficulty)) { ?>
                                <?php foreach ($difficulty as $d) { $dt = (int)$d["total"]; $dc = (int)$d["correct"]; $acc = $dt > 0 ? round(($dc / $dt) * 100) : 0; $cls = $acc >= 70 ? "is-success" : ($acc >= 40 ? "is-warning" : "is-danger"); ?>
                                    <div class="sp-subject-row">
                                        <div class="sp-subject-top">
                                            <span class="sp-subject-name"><i class="bi bi-bar-chart-steps text-primary"></i><?php echo h($d["difficulty"]); ?></span>
                                            <span class="sp-subject-score"><?php echo $acc; ?>%</span>
                                        </div>
                                        <div class="sp-track <?php echo $cls; ?>"><span data-sp-progress="<?php echo $acc; ?>"></span></div>
                                        <small class="text-muted"><?php echo $dc; ?> / <?php echo $dt; ?> correct</small>
                                    </div>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="text-muted small mb-0">No difficulty data recorded yet.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6 sp-fade-up">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3><i class="bi bi-hand-thumbs-up me-2 text-success"></i>Strong Areas</h3></div>
                            <?php if (!empty($strong_areas)) { ?>
                                <div class="sp-chip-list">
                                    <?php foreach ($strong_areas as $a) { ?><span class="sp-chip is-good"><i class="bi bi-check2-circle"></i><?php echo h($a); ?></span><?php } ?>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted small mb-0">Keep practicing — strong areas will appear as you score above 70% in a subject or difficulty level.</p>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-md-6 sp-fade-up" data-delay="2">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Needs Improvement</h3></div>
                            <?php if (!empty($weak_areas)) { ?>
                                <div class="sp-chip-list">
                                    <?php foreach ($weak_areas as $a) { ?><span class="sp-chip is-warn"><i class="bi bi-exclamation-circle"></i><?php echo h($a); ?></span><?php } ?>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted small mb-0">No weak areas detected. Areas below 40% will be highlighted here.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                    <div class="sp-empty">
                        <div class="sp-empty-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <h4>No Performance Data Yet</h4>
                        <p>Complete your first examination to unlock detailed performance analytics, subject breakdowns and progress trends.</p>
                        <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-play-circle"></i> Take an Exam</a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php if ($completed > 0) { ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof ChartManager === "undefined") { return; }
    ChartManager.create("studentTrendChart", {
        type: "line",
        data: {
            labels: [<?php echo $hist_labels; ?>],
            datasets: [{
                label: "Percentage",
                data: [<?php echo $hist_values; ?>],
                borderColor: "#4F46E5",
                borderWidth: 3,
                backgroundColor: function (ctx) {
                    var area = ctx.chart.chartArea;
                    if (!area) { return "rgba(79,70,229,.12)"; }
                    var g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                    g.addColorStop(0, "rgba(79,70,229,.34)");
                    g.addColorStop(1, "rgba(79,70,229,0)");
                    return g;
                },
                tension: .42,
                fill: true,
                pointBackgroundColor: "#4F46E5",
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8
            }]
        },
        options: {
            interaction: { mode: "index", intersect: false },
            animation: { duration: 1000, easing: "easeOutQuart" },
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + "%"; } } } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return " " + c.parsed.y + "%"; } } }
            }
        }
    });
    ChartManager.create("studentAnswerChart", {
        type: "doughnut",
        data: {
            labels: ["Correct", "Wrong", "Skipped"],
            datasets: [{ data: [<?php echo $correct_total; ?>, <?php echo $wrong_total; ?>, <?php echo $skipped; ?>], backgroundColor: ["#16A34A", "#DC2626", "#94A3B8"], borderColor: "#fff", borderWidth: 3, hoverOffset: 10 }]
        },
        options: { cutout: "68%", animation: { animateRotate: true, animateScale: true, duration: 1000, easing: "easeOutQuart" }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return " " + c.label + ": " + c.parsed; } } } } }
    });
});
</script>
<?php } ?>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
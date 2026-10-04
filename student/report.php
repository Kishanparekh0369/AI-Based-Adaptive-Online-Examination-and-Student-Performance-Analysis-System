<?php
require_once("../app/config/database.php");
$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "My Report";
include("../app/layouts/student_header.php");

$student_id = (int)$_SESSION["student_id"];

function rp_fetch($conn, $sql, $types = "", $params = array())
{
    $rows = array();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $rows; }
    if ($types !== "" && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) { $rows[] = $row; }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

$st_rows = rp_fetch($conn, "SELECT * FROM students WHERE id=? LIMIT 1", "i", array($student_id));
$st = $st_rows ? $st_rows[0] : array("name" => "Student", "email" => "", "course" => "", "semester" => "");

$all_results = rp_fetch($conn,
    "SELECT r.*, e.exam_title, e.exam_mode, e.total_marks AS exam_total, s.subject_name
     FROM results r
     LEFT JOIN exams e ON r.exam_id=e.id
     LEFT JOIN subjects s ON e.subject_id=s.id
     WHERE r.student_id=? ORDER BY r.id ASC", "i", array($student_id));

$total_exams = count($all_results);
$sum_pct = 0;
$pass_c = 0;
$fail_c = 0;
$best_pct = 0;
$best_exam = '';
$last_theta = null;
$last_skill = '';
foreach ($all_results as $r) {
    $p = round((float)$r['percentage'], 1);
    $sum_pct += $p;
    if ($r['status'] == 'Pass') { $pass_c++; } else { $fail_c++; }
    if ($p > $best_pct) {
        $best_pct = $p;
        $best_exam = $r['exam_title'];
    }
    if ($r['theta_score'] !== null) { $last_theta = round((float)$r['theta_score'], 2); }
    if (!empty($r['skill_category'])) { $last_skill = $r['skill_category']; }
}
$avg_pct = $total_exams > 0 ? round($sum_pct / $total_exams, 1) : 0;

$total_q = 0;
$total_correct = 0;
$total_wrong = 0;
$total_time = 0;
foreach ($all_results as $r) {
    $total_q += max((int)$r['total_questions'], 0);
    $total_correct += max((int)$r['correct_answers'], 0);
    $total_wrong += max((int)$r['wrong_answers'], 0);
    $total_time += max((int)$r['time_spent_seconds'], 0);
}
$accuracy = $total_q > 0 ? round(($total_correct / $total_q) * 100, 1) : 0;

$diff_data = array();
$std_rows = rp_fetch($conn,
    "SELECT q.difficulty, COUNT(sa.id) AS total, SUM(sa.is_correct) AS correct
     FROM student_answers sa LEFT JOIN questions q ON sa.question_id=q.id
     WHERE sa.student_id=? GROUP BY q.difficulty", "i", array($student_id));
foreach ($std_rows as $r) {
    $d = $r['difficulty'];
    if (!isset($diff_data[$d])) { $diff_data[$d] = array('total' => 0, 'correct' => 0); }
    $diff_data[$d]['total'] += (int)$r['total'];
    $diff_data[$d]['correct'] += (int)$r['correct'];
}
$adp_rows = rp_fetch($conn,
    "SELECT q.difficulty, COUNT(aa.id) AS total, SUM(aa.is_correct) AS correct
     FROM adaptive_answers aa LEFT JOIN questions q ON aa.question_id=q.id
     WHERE aa.session_id IN (SELECT session_id FROM results WHERE student_id=? AND session_id IS NOT NULL)
     GROUP BY q.difficulty", "i", array($student_id));
foreach ($adp_rows as $r) {
    $d = $r['difficulty'];
    if (!isset($diff_data[$d])) { $diff_data[$d] = array('total' => 0, 'correct' => 0); }
    $diff_data[$d]['total'] += (int)$r['total'];
    $diff_data[$d]['correct'] += (int)$r['correct'];
}

$ai_insight = '';
if ($total_exams > 0 && file_exists("../app/ai/PerformanceAnalyzer.php")) {
    require_once("../app/ai/PerformanceAnalyzer.php");
    if (class_exists("PerformanceAnalyzer")) {
        $analyzer = new PerformanceAnalyzer();
        $latest_row = end($all_results);
        $lastCategory = !empty($latest_row['skill_category']) ? $latest_row['skill_category'] : 'Beginner';
        $lastTheta2 = $last_theta ?? 0;
        $ai_insight = $analyzer->analyzeStudentPerformance($st['name'], 'Overall Performance', $avg_pct, $total_correct, $total_wrong, $total_q, $lastTheta2, $lastCategory, $total_time, 'All Subjects');
        $diffInsight = $analyzer->analyzeDifficultyBreakdown($student_id, $conn, $diff_data);
        if ($diffInsight) {
            $ai_insight .= "\n\nDifficulty Analysis:\n" . $diffInsight;
        }
    }
}

$trend_labels = '';
$trend_values = '';
foreach (array_reverse($all_results) as $r) {
    $trend_labels .= "'" . addslashes($r['exam_title']) . "',";
    $trend_values .= round((float)$r['percentage'], 1) . ",";
}
$subj_data = array();
foreach ($all_results as $r) {
    $sn = $r['subject_name'] ?: 'General';
    if (!isset($subj_data[$sn])) { $subj_data[$sn] = array('sum' => 0, 'c' => 0); }
    $subj_data[$sn]['sum'] += round((float)$r['percentage'], 1);
    $subj_data[$sn]['c']++;
}
$subj_labels = '';
$subj_values = '';
foreach ($subj_data as $name => $d) {
    $subj_labels .= "'" . addslashes($name) . "',";
    $subj_values .= round($d['sum'] / $d['c'], 1) . ",";
}
$diff_labels = '';
$diff_values = '';
foreach (array('Easy', 'Medium', 'Hard') as $d) {
    $dt = $diff_data[$d] ?? array('total' => 0, 'correct' => 0);
    $diff_labels .= "'$d',";
    $diff_values .= ($dt['total'] > 0 ? round(($dt['correct'] / $dt['total']) * 100, 1) : 0) . ",";
}

$rank = '-';
$rank_total = 0;
if ($total_exams > 0) {
    $latest_for_rank = end($all_results);
    $eid = (int)$latest_for_rank['exam_id'];
    $rank_rows = rp_fetch($conn, "SELECT COUNT(*)+1 AS r FROM results WHERE exam_id=? AND percentage > ?", "id", array($eid, (float)$latest_for_rank['percentage']));
    if ($rank_rows) { $rank = $rank_rows[0]['r']; }
    $tot_rows = rp_fetch($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id=?", "i", array($eid));
    if ($tot_rows) { $rank_total = (int)$tot_rows[0]['c']; }
}
$rank_nice = ($rank === '-' ? '-' : '#' . $rank) . ($rank_total > 0 ? ' of ' . $rank_total : '');

function rp_ability($theta) {
    if ($theta === null || $theta === "") { return null; }
    $score = (int)round((((float)$theta + 3) / 6) * 100);
    $score = max(0, min(100, $score));
    if ($score < 25) { $label = "Beginner"; }
    elseif ($score < 50) { $label = "Developing"; }
    elseif ($score < 75) { $label = "Proficient"; }
    else { $label = "Advanced"; }
    return array("score" => $score, "label" => $label);
}
$last_ability = rp_ability($last_theta);

function rp_skill($skill) {
    $s = strtolower(trim((string)$skill));
    if ($s === "advanced") { return array("label" => "Advanced", "icon" => "bi-graph-up-arrow"); }
    if ($s === "moderate") { return array("label" => "Moderate", "icon" => "bi-bar-chart"); }
    if ($s === "weak") { return array("label" => "Weak", "icon" => "bi-graph-down-arrow"); }
    if ($s === "") { return null; }
    return array("label" => ucfirst($s), "icon" => "bi-award");
}
$last_skill_meta = rp_skill($last_skill);

$initials = strtoupper(implode("", array_map(function ($w) { return substr($w, 0, 1); }, preg_split('/\s+/', trim($st["name"])))));
if (strlen($initials) > 2) { $initials = substr($initials, 0, 2); }
$photo_url = "";
if (!empty($st["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/profile/" . $st["profile_image"])) {
    $photo_url = "../assets/uploads/profile/" . $st["profile_image"];
}

function rp_fmt_time($s)
{
    $s = max((int)$s, 0);
    $h = floor($s / 3600);
    $m = floor(($s % 3600) / 60);
    $sec = $s % 60;
    if ($h > 0) { return $h . "h " . $m . "m"; }
    if ($m > 0) { return $m . "m " . $sec . "s"; }
    return $sec . "s";
}
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-file-earmark-bar-graph"></i> Report Card</span>
                        <h1>My Performance Report</h1>
                        <p>Complete exam history, analytics and downloadable report.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="sp-btn sp-btn-soft" href="result.php"><i class="bi bi-trophy"></i> Results</a>
                        <a class="sp-btn sp-btn-primary" href="report_export.php"><i class="bi bi-download"></i> Download PDF</a>
                    </div>
                </div>

                <?php if ($total_exams > 0) { ?>
                <!-- HERO -->
                <div class="sp-profile-hero sp-fade-up">
                    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
                        <?php if ($photo_url !== "") { ?>
                            <img src="<?php echo h($photo_url); ?>" class="sp-profile-avatar" alt="Profile photo">
                        <?php } else { ?>
                            <span class="sp-profile-initials"><?php echo h($initials); ?></span>
                        <?php } ?>
                        <div class="flex-grow-1">
                            <h2><?php echo h($st["name"]); ?></h2>
                            <p class="sp-profile-sub"><?php echo h($st["email"]); ?> &middot; <?php echo h($st["course"]); ?> &middot; <?php echo h($st["semester"]); ?></p>
                            <div class="sp-profile-pills">
                                <span class="sp-hero-pill" title="<?php echo $rank_total > 0 ? 'Position among ' . $rank_total . ' attempts on your latest exam' : 'Your rank on the latest exam'; ?>"><i class="bi bi-trophy"></i>Rank <?php echo h($rank_nice); ?></span>
                                <?php if ($last_ability) { ?><span class="sp-hero-pill" title="Estimated ability (theta = <?php echo h($last_theta); ?>)"><i class="bi bi-activity"></i>Ability <?php echo $last_ability['score']; ?>/100 &middot; <?php echo h($last_ability['label']); ?></span><?php } ?>
                                <?php if ($last_skill_meta) { ?><span class="sp-hero-pill" title="Skill category assigned by the evaluation engine"><i class="bi <?php echo $last_skill_meta['icon']; ?>"></i>Skill: <?php echo h($last_skill_meta['label']); ?></span><?php } ?>
                                <span class="sp-hero-pill"><i class="bi bi-bullseye"></i><?php echo $accuracy; ?>% accuracy</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAT CARDS -->
                <div class="row g-3 g-xl-4 mb-4">
                    <?php
                    $rp_cards = array(
                        array("Exams Taken", $total_exams, $pass_c . " passed", "bi-journal-check", "result.php", "dash-tone-blue"),
                        array("Average Score", $avg_pct, "Across all exams", "bi-graph-up-arrow", "performance.php", "dash-tone-violet", "%"),
                        array("Best Score", $best_pct, $best_exam !== "" ? $best_exam : "No exam yet", "bi-trophy", "result.php", "dash-tone-amber", "%"),
                        array("Questions Solved", $total_q, $total_correct . " correct", "bi-patch-question", "result.php", "dash-tone-green"),
                        array("Study Time", rp_fmt_time($total_time), "Total exam time", "bi-stopwatch", "result.php", "dash-tone-cyan")
                    );
                    $rp_delay = 0;
                    foreach ($rp_cards as $card) {
                        $rp_delay++;
                        $rp_suffix = isset($card[6]) ? $card[6] : "";
                    ?>
                    <div class="col-6 col-lg-4 col-xl sp-fade-up" data-delay="<?php echo min($rp_delay, 6); ?>">
                        <div class="dash-stat <?php echo $card[5]; ?>">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $card[3]; ?>"></i></span>
                            </div>
                            <div class="dash-stat-value" <?php if ($rp_suffix !== "") { echo "data-suffix='" . h($rp_suffix) . "'"; } ?> data-count="<?php echo is_numeric($card[1]) ? $card[1] : 0; ?>"><?php echo is_numeric($card[1]) ? "0" : h($card[1]); ?></div>
                            <p class="dash-stat-label"><?php echo h($card[0]); ?></p>
                            <p class="dash-stat-sub"><?php echo h($card[2]); ?></p>
                            <a href="<?php echo $card[4]; ?>" class="dash-stat-link" aria-label="Open <?php echo h($card[0]); ?>"><span>Open</span><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                    <?php } ?>
                </div>

                <!-- AI INSIGHT -->
                <?php if ($ai_insight !== "") { ?>
                <div class="sp-ai-panel mb-4 sp-fade-up">
                    <span class="sp-ai-badge mb-2"><i class="bi bi-stars"></i> AI Performance Analysis</span>
                    <p class="sp-ai-text mt-2 mb-0"><?php echo h($ai_insight); ?></p>
                </div>
                <?php } ?>

                <!-- CHARTS -->
                <div class="row g-3 g-xl-4 mb-4">
                    <div class="col-md-6 col-xl-3 sp-fade-up">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3 style="font-size:.92rem;"><i class="bi bi-graph-up me-1 text-primary"></i>Score Trend</h3></div>
                            <div class="chart-container chart-container-md"><canvas id="rpTrendChart" role="img" aria-label="Score trend line chart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 sp-fade-up" data-delay="1">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3 style="font-size:.92rem;"><i class="bi bi-pie-chart me-1 text-primary"></i>Pass vs Fail</h3></div>
                            <div class="chart-container chart-container-doughnut"><canvas id="rpPassFailChart" role="img" aria-label="Pass versus fail doughnut chart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 sp-fade-up" data-delay="2">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3 style="font-size:.92rem;"><i class="bi bi-bar-chart me-1 text-primary"></i>Subject Average</h3></div>
                            <div class="chart-container chart-container-md"><canvas id="rpSubjectChart" role="img" aria-label="Subject average bar chart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 sp-fade-up" data-delay="3">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3 style="font-size:.92rem;"><i class="bi bi-radar me-1 text-primary"></i>Difficulty Accuracy</h3></div>
                            <div class="chart-container chart-container-radar"><canvas id="rpDiffChart" role="img" aria-label="Difficulty accuracy radar chart"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- EXAM HISTORY -->
                <div class="sp-panel-head sp-fade-up">
                    <h3><i class="bi bi-clock-history me-1 text-primary"></i>Exam History</h3>
                    <span class="small text-muted"><?php echo $total_exams; ?> attempt<?php echo $total_exams === 1 ? "" : "s"; ?> &middot; tap a card for details</span>
                </div>
                <div class="row g-3">
                    <?php $idx = 0; foreach ($all_results as $row) {
                        $idx++;
                        $pct = round((float)$row['percentage'], 1);
                        $isAd = ($row['exam_mode'] ?? 'Standard') === 'Adaptive';
                        $marksTotal = max((int)$row['exam_total'], (int)$row['total_questions'], 1);
                        $skipped = max(0, (int)$row['total_questions'] - (int)$row['correct_answers'] - (int)$row['wrong_answers']);
                        $ad = array();
                        if (!empty($row['answers_detail'])) { $ad = json_decode($row['answers_detail'], true) ?: array(); }
                        if (empty($ad) && !empty($row['session_id'])) {
                            $fq = rp_fetch($conn, "SELECT aa.*, q.correct_answer, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.marks, q.difficulty FROM adaptive_answers aa LEFT JOIN questions q ON aa.question_id = q.id WHERE aa.session_id = ? ORDER BY aa.id", "i", array((int)$row['session_id']));
                            foreach ($fq as $fa) { $ad[] = $fa; }
                        }
                        $isPass = $row['status'] == 'Pass';
                    ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min($idx, 6); ?>">
                        <div class="sp-exam-card <?php echo $isPass ? "is-completed" : "is-blocked"; ?>">
                            <div class="sp-exam-head">
                                <div>
                                    <h3 class="sp-exam-title"><?php echo h($row['exam_title']); ?></h3>
                                    <span class="sp-exam-subject"><i class="bi bi-book"></i><?php echo h($row['subject_name'] ?? 'General'); ?><?php if ($isAd) { ?> &middot; <i class="bi bi-robot"></i>Adaptive<?php } ?></span>
                                </div>
                                <span class="sp-pill <?php echo $isPass ? "is-completed" : "is-expired"; ?>">
                                    <i class="bi bi-<?php echo $isPass ? "check2-circle" : "x-circle"; ?>"></i><?php echo h($row['status']); ?>
                                </span>
                            </div>
                            <div class="sp-exam-meta">
                                <div class="sp-meta-item"><i class="bi bi-award"></i><span><?php echo (int)$row['marks_obtained']; ?>/<?php echo $marksTotal; ?> marks</span></div>
                                <div class="sp-meta-item"><i class="bi bi-graph-up"></i><span><?php echo $pct; ?>%</span></div>
                                <div class="sp-meta-item"><i class="bi bi-check-circle"></i><span><?php echo (int)$row['correct_answers']; ?> correct</span></div>
                                <div class="sp-meta-item"><i class="bi bi-calendar3"></i><span><?php echo date("d M Y", strtotime($row['submitted_at'])); ?></span></div>
                                <?php $rowAb = rp_ability($row['theta_score']); if ($rowAb) { ?><div class="sp-meta-item"><i class="bi bi-activity"></i><span title="Estimated ability (theta = <?php echo h(round((float)$row['theta_score'], 2)); ?>)">Ability <?php echo $rowAb['score']; ?>/100</span></div><?php } ?>
                                <?php $rowSk = rp_skill($row['skill_category'] ?? ''); if ($rowSk) { ?><div class="sp-meta-item"><i class="bi bi-stars"></i><span>Skill: <?php echo h($rowSk['label']); ?></span></div><?php } ?>
                            </div>
                            <div class="sp-exam-foot">
                                <a class="sp-btn sp-btn-soft" href="result.php?exam_id=<?php echo (int)$row['exam_id']; ?>"><i class="bi bi-bar-chart"></i> Full Result</a>
                                <?php if (count($ad) > 0) { ?>
                                <button type="button" class="sp-btn sp-btn-ghost" data-rp-toggle="rpQ_<?php echo $idx; ?>" aria-expanded="false"><i class="bi bi-list-check"></i> Questions</button>
                                <?php } ?>
                            </div>
                            <?php if (count($ad) > 0) { ?>
                            <div id="rpQ_<?php echo $idx; ?>" style="display:none;" class="mt-3 pt-3" data-rp-panel>
                                <?php $qn = 1; foreach ($ad as $a) {
                                    $ic = (int)($a['is_correct'] ?? 0);
                                    $sa = $a['student_answer'] ?? '';
                                    $ca = $a['correct_answer'] ?? '';
                                    $opts = array('A' => $a['option_a'], 'B' => $a['option_b'], 'C' => $a['option_c'], 'D' => $a['option_d']);
                                ?>
                                <div class="p-2 mb-2 rounded-3 border" style="background:#F8FAFC;">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                        <strong class="small">Q<?php echo $qn++; ?>. <?php echo h($a['question']); ?></strong>
                                        <span class="sp-pill <?php echo $ic ? "is-completed" : "is-expired"; ?>" style="font-size:.62rem;"><?php echo $ic ? "Correct" : "Wrong"; ?></span>
                                    </div>
                                    <?php foreach ($opts as $k => $v) {
                                        $isS = $k === $sa; $isC = $k === $ca;
                                        $bg = $isC ? "#dcfce7" : (($isS && !$ic) ? "#fee2e2" : "transparent");
                                    ?>
                                    <div class="d-flex align-items-center gap-2 px-2 py-1 rounded small" style="background:<?php echo $bg; ?>;">
                                        <?php if ($isC) { ?><i class="bi bi-check-circle-fill text-success"></i><?php } elseif ($isS && !$ic) { ?><i class="bi bi-x-circle-fill text-danger"></i><?php } else { ?><span style="width:14px;"></span><?php } ?>
                                        <strong><?php echo $k; ?>.</strong><span><?php echo h($v); ?></span>
                                        <?php if ($isS) { ?><span class="badge bg-secondary ms-auto" style="font-size:.55rem;">You</span><?php } ?>
                                    </div>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <div class="sp-empty sp-fade-up">
                    <div class="sp-empty-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
                    <h4>No Report Yet</h4>
                    <p>Complete your first examination to generate a detailed performance report with analytics, charts and AI insights.</p>
                    <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-play-circle"></i> Take an Exam</a>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-rp-toggle]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var panel = document.getElementById(btn.getAttribute("data-rp-toggle"));
            if (!panel) { return; }
            var open = panel.style.display !== "none";
            panel.style.display = open ? "none" : "";
            btn.setAttribute("aria-expanded", open ? "false" : "true");
        });
    });

    if (typeof ChartManager === "undefined" || <?php echo $total_exams > 0 ? "false" : "true"; ?>) { return; }
    ChartManager.create("rpTrendChart", {
        type: "line",
        data: { labels: [<?php echo $trend_labels; ?>], datasets: [{ data: [<?php echo $trend_values; ?>], borderColor: "#4F46E5", backgroundColor: "rgba(79,70,229,.12)", fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: "#4F46E5" }] },
        options: { scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + "%"; } } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return " Score: " + ctx.parsed.y + "%"; } } } } }
    });
    ChartManager.create("rpPassFailChart", {
        type: "doughnut",
        data: { labels: ["Pass", "Fail"], datasets: [{ data: [<?php echo $pass_c; ?>, <?php echo $fail_c; ?>], backgroundColor: ["#16A34A", "#DC2626"] }] },
        options: { cutout: "65%" }
    });
    ChartManager.create("rpSubjectChart", {
        type: "bar",
        data: { labels: [<?php echo $subj_labels; ?>], datasets: [{ data: [<?php echo $subj_values; ?>], backgroundColor: "#0D9488", borderRadius: 5 }] },
        options: { indexAxis: "y", scales: { x: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + "%"; } } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return " Average: " + ctx.parsed.x + "%"; } } } } }
    });
    ChartManager.create("rpDiffChart", {
        type: "radar",
        data: { labels: [<?php echo $diff_labels; ?>], datasets: [{ data: [<?php echo $diff_values; ?>], backgroundColor: "rgba(99,102,241,.2)", borderColor: "#6366F1", pointBackgroundColor: "#6366F1" }] },
        options: { scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 25, callback: function (v) { return v + "%"; } } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return " Accuracy: " + ctx.parsed.r + "%"; } } } } }
    });
});
</script>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
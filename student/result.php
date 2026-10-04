<?php
require_once("../app/config/database.php");
$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "My Results";
$student_id = (int)$_SESSION["student_id"];

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

function sp_q($conn, $sql, $types, $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return null; }
    if ($types !== "" && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $res;
}

// Summary stats (overall, this student only)
$summaryQ = sp_q($conn,
    "SELECT COUNT(*) AS total_exams, AVG(percentage) AS avgp,
            SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) AS pass_count,
            SUM(correct_answers) AS correct_total,
            SUM(wrong_answers) AS wrong_total,
            SUM(total_questions - correct_answers - wrong_answers) AS skipped_total,
            SUM(time_spent_seconds) AS time_total
     FROM results WHERE student_id=?",
    "i", array($student_id));
$summary = ($summaryQ && $row = mysqli_fetch_assoc($summaryQ)) ? $row : array(
    "total_exams"=>0,"avgp"=>null,"pass_count"=>0,"correct_total"=>0,"wrong_total"=>0,"skipped_total"=>0,"time_total"=>0
);
$total_exams = max((int)$summary["total_exams"], 0);
$avgp = $summary["avgp"] ? round($summary["avgp"], 2) : 0;
$pass_percent = $total_exams > 0 ? round(($summary["pass_count"] / $total_exams) * 100, 2) : 0;
$avg_time = $total_exams > 0 ? round(($summary["time_total"] ?? 0) / $total_exams) : 0;

$baseResultSQL = "SELECT r.*, e.exam_title, e.exam_mode, e.pass_marks, e.total_marks AS exam_total_marks, s.subject_name
    FROM results r
    LEFT JOIN exams e ON r.exam_id=e.id
    LEFT JOIN subjects s ON e.subject_id=s.id
    WHERE r.student_id=?";
if ($exam_id) {
    $latestQ = sp_q($conn, $baseResultSQL . " AND r.exam_id=? ORDER BY r.id DESC LIMIT 1", "ii", array($student_id, $exam_id));
} else {
    $latestQ = sp_q($conn, $baseResultSQL . " ORDER BY r.id DESC LIMIT 1", "i", array($student_id));
}
$latest = ($latestQ && mysqli_num_rows($latestQ) > 0) ? mysqli_fetch_assoc($latestQ) : null;

$rank = '-';
$rankTotal = 0;
$attemptNum = '-';
if ($latest) {
    $eid = (int)$latest['exam_id'];
    $rankQ = sp_q($conn, "SELECT COUNT(*) + 1 AS r FROM results WHERE exam_id=? AND percentage > ?", "id", array($eid, (float)$latest['percentage']));
    if ($rankQ && $rr = mysqli_fetch_assoc($rankQ)) { $rank = $rr['r']; }
    $totQ = sp_q($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id=?", "i", array($eid));
    if ($totQ && $tr = mysqli_fetch_assoc($totQ)) { $rankTotal = (int)$tr['c']; }
    $allResQ = sp_q($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id=? AND student_id=?", "ii", array($eid, $student_id));
    if ($allResQ && $ar = mysqli_fetch_assoc($allResQ)) { $attemptNum = $ar['c']; }
}

$latest_id = $latest ? (int)$latest['id'] : 0;

// ─── Structured AI analysis (cached JSON; on-demand via ai_analysis_ajax.php) ───
require_once("../app/ai/PerformanceService.php");
require_once("../app/ai/analysis_view.php");
$ai_svc = new PerformanceService();
$ai_svc->ensureColumn($conn);
$ai_cached = $latest_id > 0 ? $ai_svc->loadCached($conn, $latest_id) : null;
// Metrics for difficulty cards: this attempt's stored breakdown (real per-attempt data).
$ai_metrics = array("difficulty" => array());
if (!empty($latest["difficulty_breakdown"])) {
    $ai_bd = json_decode($latest["difficulty_breakdown"], true);
    if (is_array($ai_bd)) {
        foreach ($ai_bd as $alv => $av) {
            $at = (int)($av["total"] ?? 0);
            if ($at <= 0) { continue; }
            $ac = (int)($av["correct"] ?? 0);
            $ai_metrics["difficulty"][$alv] = array("attempted" => $at, "correct" => $ac, "incorrect" => max($at - $ac, 0), "accuracy" => round(($ac / $at) * 100, 1));
        }
    }
}

$prevQ = sp_q($conn, $baseResultSQL . " AND r.id!=? ORDER BY r.id DESC", "ii", array($student_id, $latest_id));

$subjQ = sp_q($conn,
    "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r
     LEFT JOIN exams e ON r.exam_id=e.id
     LEFT JOIN subjects s ON e.subject_id=s.id
     WHERE r.student_id=? GROUP BY s.id ORDER BY s.subject_name", "i", array($student_id));

$diff_data = array();
$stdDiffQ = sp_q($conn,
    "SELECT q.difficulty, COUNT(sa.id) AS total, SUM(sa.is_correct) AS correct_total
     FROM student_answers sa LEFT JOIN questions q ON sa.question_id=q.id
     WHERE sa.student_id=? GROUP BY q.difficulty", "i", array($student_id));
if ($stdDiffQ) { while ($r = mysqli_fetch_assoc($stdDiffQ)) { $d = $r['difficulty']; if (!isset($diff_data[$d])) $diff_data[$d] = ['total'=>0,'correct'=>0]; $diff_data[$d]['total'] += (int)$r['total']; $diff_data[$d]['correct'] += (int)$r['correct_total']; } }
$adapDiffQ = sp_q($conn,
    "SELECT q.difficulty, COUNT(aa.id) AS total, SUM(aa.is_correct) AS correct_total
     FROM adaptive_answers aa
     LEFT JOIN questions q ON aa.question_id=q.id
     WHERE aa.session_id IN (SELECT session_id FROM results WHERE student_id=? AND session_id IS NOT NULL)
     GROUP BY q.difficulty", "i", array($student_id));
if ($adapDiffQ) { while ($r = mysqli_fetch_assoc($adapDiffQ)) { $d = $r['difficulty']; if (!isset($diff_data[$d])) $diff_data[$d] = ['total'=>0,'correct'=>0]; $diff_data[$d]['total'] += (int)$r['total']; $diff_data[$d]['correct'] += (int)$r['correct_total']; } }

$subj_labels = ''; $subj_values = '';
if ($subjQ) { while ($r = mysqli_fetch_assoc($subjQ)) { $subj_labels .= "'" . addslashes($r['subject_name']) . "',"; $subj_values .= round((float)$r['avgp'], 2) . ","; } }
$diff_labels = ''; $diff_values = '';
// Per-attempt difficulty accuracy (only levels present in THIS attempt — never 0/0 filler).
$attempt_diff = isset($ai_metrics["difficulty"]) ? $ai_metrics["difficulty"] : array();
if (empty($attempt_diff)) {
    foreach (array('Easy', 'Medium', 'Hard') as $d) {
        $dt = $diff_data[$d] ?? array('total'=>0, 'correct'=>0);
        if ((int)$dt['total'] <= 0) { continue; }
        $attempt_diff[$d] = array('attempted' => (int)$dt['total'], 'correct' => (int)$dt['correct'], 'accuracy' => round(((int)$dt['correct'] / (int)$dt['total']) * 100, 2));
    }
}
foreach ($attempt_diff as $d => $dt) {
    $diff_labels .= "'" . addslashes($d) . "',";
    $diff_values .= round((float)($dt['accuracy'] ?? 0), 2) . ",";
}

function fmt_sec($s) { $s = max((int)$s, 0); return str_pad(floor($s / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad($s % 60, 2, '0', STR_PAD_LEFT); }

function fmt_duration($s) {
    $s = max((int)$s, 0);
    if ($s < 60) { return $s . " sec"; }
    $m = floor($s / 60); $r = $s % 60;
    if ($m < 60) { return $r > 0 ? $m . "m " . $r . "s" : $m . " min"; }
    $h = floor($m / 60); $rm = $m % 60;
    return $rm > 0 ? $h . "h " . $rm . "m" : $h . " hr";
}

function fmt_datetime($dt) {
    if (empty($dt)) { return "-"; }
    $t = strtotime($dt);
    if ($t === false) { return $dt; }
    return date("d M Y, h:i A", $t);
}

function theta_ability($theta) {
    if ($theta === null || $theta === "") { return null; }
    $score = (int)round((((float)$theta + 3) / 6) * 100);
    $score = max(0, min(100, $score));
    if ($score < 25) { $label = "Beginner"; $cls = "is-expired"; }
    elseif ($score < 50) { $label = "Developing"; $cls = "is-upcoming"; }
    elseif ($score < 75) { $label = "Proficient"; $cls = "is-available"; }
    else { $label = "Advanced"; $cls = "is-completed"; }
    return array("score" => $score, "label" => $label, "cls" => $cls);
}

function skill_meta($skill) {
    $s = strtolower(trim((string)$skill));
    if ($s === "advanced") { return array("label" => "Advanced", "icon" => "bi-graph-up-arrow", "pill" => "is-completed"); }
    if ($s === "moderate") { return array("label" => "Moderate", "icon" => "bi-bar-chart", "pill" => "is-upcoming"); }
    if ($s === "weak") { return array("label" => "Weak", "icon" => "bi-graph-down-arrow", "pill" => "is-expired"); }
    if ($s === "") { return array("label" => "Not graded", "icon" => "bi-award", "pill" => "is-upcoming"); }
    return array("label" => ucfirst($s), "icon" => "bi-award", "pill" => "is-available");
}

$ad = array();
if ($latest) {
    if (!empty($latest['answers_detail'])) { $ad = json_decode($latest['answers_detail'], true); }
    if (empty($ad) && !empty($latest['session_id'])) {
        $fq = sp_q($conn,
            "SELECT aa.*, q.correct_answer, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.marks, q.difficulty
             FROM adaptive_answers aa LEFT JOIN questions q ON aa.question_id = q.id
             WHERE aa.session_id = ? ORDER BY aa.id", "i", array((int)$latest['session_id']));
        if ($fq) { while ($r = mysqli_fetch_assoc($fq)) { $ad[] = array(
            'question'=>$r['question'], 'option_a'=>$r['option_a'], 'option_b'=>$r['option_b'],
            'option_c'=>$r['option_c'], 'option_d'=>$r['option_d'],
            'student_answer'=>$r['student_answer'], 'correct_answer'=>$r['correct_answer'],
            'is_correct'=>(int)$r['is_correct'], 'difficulty'=>$r['difficulty'], 'marks'=>(int)$r['marks']
        ); } }
    }
}

$obtained = (int)($latest['marks_obtained'] ?? 0);
$totalQ = max((int)($latest['total_questions'] ?? 0), 0);
$correct = (int)($latest['correct_answers'] ?? 0);
$wrong = (int)($latest['wrong_answers'] ?? 0);
$skipped = max($totalQ - $correct - $wrong, 0);
$tmarks = max((int)($latest['exam_total_marks'] ?? 0), $totalQ, 1);
$pm = min((int)($latest['pass_marks'] ?? 0), $tmarks);
$pct = $tmarks > 0 ? round(($obtained / $tmarks) * 100, 2) : 0;
$pct = min($pct, 100);
$isPass = $obtained >= $pm;
$statusText = $isPass ? 'PASS' : 'FAIL';
$isAdaptive = ($latest['exam_mode'] ?? 'Standard') === 'Adaptive';
$latestTime = max((int)($latest['time_spent_seconds'] ?? 0), 0);
$avgPerQ = $totalQ > 0 ? round($latestTime / $totalQ) : 0;
$avgPerQ = min($avgPerQ, $latestTime);
$submittedAdaptive = isset($_GET['adaptive']) && $_GET['adaptive'] == '1';

// Premium hero display values (dynamic + human-friendly)
$ability = $isAdaptive ? theta_ability($latest['theta_score'] ?? null) : null;
$skillMeta = skill_meta($latest['skill_category'] ?? '');
$examTitleNice = trim((string)($latest['exam_title'] ?? 'Exam'));
$subjectNice = trim((string)($latest['subject_name'] ?? ''));
$showSubjectPill = ($subjectNice !== '' && strcasecmp($subjectNice, $examTitleNice) !== 0);
$submittedNice = fmt_datetime($latest['submitted_at'] ?? '');
$timeNice = fmt_duration($latestTime);
$rankNice = ($rank === '-' ? '-' : '#' . $rank) . ($rankTotal > 0 ? ' of ' . $rankTotal : '');

include("../app/layouts/student_header.php");
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-trophy"></i> Result Analytics</span>
                        <h1>My Results</h1>
                        <p>Detailed breakdown of your examination performance.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($exam_id) { ?><a href="result.php" class="sp-btn sp-btn-ghost"><i class="bi bi-arrow-left"></i> Back to Latest</a><?php } ?>
                        <button type="button" class="sp-btn sp-btn-primary" onclick="if(window.StudentAI){StudentAI.ask('Explain my latest result in simple terms and suggest what I can improve.');}"><i class="bi bi-robot"></i> Explain My Result</button>
                        <button class="sp-btn sp-btn-soft" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                    </div>
                </div>

                <?php if ($latest) { ?>
                <!-- HERO -->
                <div class="sp-profile-hero sp-result-hero <?php echo $isPass ? 'is-pass' : 'is-fail'; ?> sp-fade-up">
                    <span class="sp-result-glow" aria-hidden="true"></span>
                    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4 position-relative">
                        <div class="sp-score-ring" style="--p:<?php echo $pct; ?>;--c:<?php echo $isPass ? '#22C55E' : '#EF4444'; ?>;" role="img" aria-label="Score <?php echo number_format($pct, 0); ?> percent, <?php echo $statusText; ?>">
                            <div class="sp-score-ring-inner">
                                <strong><?php echo number_format($pct, 0); ?>%</strong>
                                <small style="color:<?php echo $isPass ? '#4ADE80' : '#F87171'; ?>;"><?php echo $statusText; ?></small>
                            </div>
                        </div>
                        <div class="flex-grow-1" style="min-width:200px;">
                            <div class="sp-result-kicker"><?php echo $isAdaptive ? 'Adaptive Assessment' : 'Standard Assessment'; ?><?php if ($subjectNice !== '') { echo ' &middot; ' . h($subjectNice); } ?></div>
                            <h2 class="mb-2"><?php echo h($examTitleNice); ?></h2>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <?php if ($showSubjectPill) { ?><span class="sp-hero-pill"><i class="bi bi-book"></i><?php echo h($subjectNice); ?></span><?php } ?>
                                <span class="sp-hero-pill"><i class="bi bi-<?php echo $isAdaptive ? 'robot' : 'gear'; ?>"></i><?php echo $isAdaptive ? 'Adaptive' : 'Standard'; ?> Mode</span>
                                <span class="sp-pill <?php echo $isPass ? 'is-completed' : 'is-expired'; ?>"><i class="bi bi-<?php echo $isPass ? 'check-circle-fill' : 'x-circle-fill'; ?> me-1"></i><?php echo $statusText; ?></span>
                                <span class="sp-pill <?php echo $skillMeta['pill']; ?>" title="Skill category assigned by the evaluation engine"><i class="bi <?php echo $skillMeta['icon']; ?> me-1"></i>Skill: <?php echo h($skillMeta['label']); ?></span>
                            </div>
                            <?php if ($ability) { ?>
                            <div class="sp-ability" title="Estimated ability (theta = <?php echo h(round((float)$latest['theta_score'], 2)); ?>)">
                                <div class="sp-ability-top"><span><i class="bi bi-cpu me-1"></i>Ability Score</span><strong><?php echo $ability['score']; ?>/100 &middot; <?php echo h($ability['label']); ?></strong></div>
                                <div class="sp-ability-bar"><span class="sp-ability-fill" style="width:<?php echo $ability['score']; ?>%;"></span></div>
                            </div>
                            <?php } ?>
                        </div>
                        <?php if ($ad && count($ad) > 0) { ?>
                        <button class="sp-btn sp-btn-soft" onclick="showAnswerReview()"><i class="bi bi-eye"></i> View Answers</button>
                        <?php } ?>
                    </div>
                    <div class="sp-result-meta position-relative">
                        <div class="sp-result-fact"><small><i class="bi bi-trophy me-1"></i>Rank</small><strong title="<?php echo $rankTotal > 0 ? 'Position among ' . $rankTotal . ' attempts on this exam' : 'Rank among attempts on this exam'; ?>"><?php echo h($rankNice); ?></strong></div>
                        <div class="sp-result-fact"><small><i class="bi bi-stopwatch me-1"></i>Time Taken</small><strong title="Total time spent: <?php echo fmt_sec($latestTime); ?>"><?php echo h($timeNice); ?></strong></div>
                        <div class="sp-result-fact"><small><i class="bi bi-calendar-check me-1"></i>Submitted On</small><strong><?php echo h($submittedNice); ?></strong></div>
                        <div class="sp-result-fact"><small><i class="bi bi-hash me-1"></i>Attempt</small><strong><?php echo $attemptNum === '-' ? '-' : '#' . $attemptNum; ?></strong></div>
                    </div>
                </div>

                <!-- STAT CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="1">
                        <div class="sp-stat is-success"><span class="sp-stat-icon"><i class="bi bi-check-circle"></i></span><div class="sp-stat-value"><?php echo $correct; ?></div><div class="sp-stat-label">Correct</div></div>
                    </div>
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="2">
                        <div class="sp-stat is-danger"><span class="sp-stat-icon"><i class="bi bi-x-circle"></i></span><div class="sp-stat-value"><?php echo $wrong; ?></div><div class="sp-stat-label">Wrong</div></div>
                    </div>
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="3">
                        <div class="sp-stat"><span class="sp-stat-icon"><i class="bi bi-dash-circle"></i></span><div class="sp-stat-value"><?php echo $skipped; ?></div><div class="sp-stat-label">Skipped</div></div>
                    </div>
                    <div class="col-6 col-xl-3 sp-fade-up" data-delay="4">
                        <div class="sp-stat is-info"><span class="sp-stat-icon"><i class="bi bi-stopwatch"></i></span><div class="sp-stat-value"><?php echo fmt_sec($avgPerQ); ?></div><div class="sp-stat-label">Avg / Question</div></div>
                    </div>
                </div>

                <!-- SCORE ROW -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3 sp-fade-up"><div class="sp-panel"><div class="small text-muted mb-1">Score</div><div class="fw-bold fs-4"><?php echo $obtained; ?> / <?php echo $tmarks; ?></div></div></div>
                    <div class="col-6 col-md-3 sp-fade-up" data-delay="1"><div class="sp-panel"><div class="small text-muted mb-1">Percentage</div><div class="fw-bold fs-4"><?php echo number_format($pct, 1); ?>%</div></div></div>
                    <div class="col-6 col-md-3 sp-fade-up" data-delay="2"><div class="sp-panel"><div class="small text-muted mb-1">Pass Marks</div><div class="fw-bold fs-4"><?php echo $pm; ?> / <?php echo $tmarks; ?></div></div></div>
                    <div class="col-6 col-md-3 sp-fade-up" data-delay="3"><div class="sp-panel"><div class="small text-muted mb-1">Total Questions</div><div class="fw-bold fs-4"><?php echo $totalQ; ?></div></div></div>
                </div>

                <!-- AI PERFORMANCE ANALYSIS (dynamic, per-attempt) -->
                <div class="sp-ai-panel mb-4 sp-fade-up">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-robot fs-2 text-primary d-none d-sm-block"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="sp-ai-badge"><i class="bi bi-stars"></i> AI Performance Analysis</span>
                                <small class="text-muted">Personalized analysis based on your actual examination performance.</small>
                                <button type="button" id="aiRegenBtn" class="sp-btn sp-btn-ghost ms-auto" style="min-height:36px;padding:6px 14px;font-size:.78rem;" title="Generate a fresh AI analysis for this result">
                                    <i class="bi bi-arrow-clockwise"></i> Regenerate Analysis
                                </button>
                            </div>
                            <div id="aiAnalysisBody">
                                <?php if ($ai_cached) { echo render_ai_analysis($ai_cached, $ai_metrics); } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHARTS -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4 sp-fade-up"><div class="sp-panel"><div class="sp-panel-head"><h3 style="font-size:.95rem;"><i class="bi bi-pie-chart me-1 text-primary"></i>Attempt Split</h3></div><div class="chart-container chart-container-doughnut"><canvas id="attemptChart" role="img" aria-label="Attempt split doughnut chart"></canvas></div></div></div>
                    <div class="col-md-4 sp-fade-up" data-delay="1"><div class="sp-panel"><div class="sp-panel-head"><h3 style="font-size:.95rem;"><i class="bi bi-bar-chart me-1 text-primary"></i>Subject Performance</h3></div><div class="chart-container chart-container-md"><canvas id="subjectChart" role="img" aria-label="Subject performance bar chart"></canvas></div></div></div>
                    <div class="col-md-4 sp-fade-up" data-delay="2"><div class="sp-panel"><div class="sp-panel-head"><h3 style="font-size:.95rem;"><i class="bi bi-bar-chart-line me-1 text-primary"></i>Difficulty Accuracy</h3></div><div class="chart-container chart-container-md"><canvas id="difficultyChart" role="img" aria-label="Difficulty accuracy bar chart"></canvas></div></div></div>
                </div>

                <!-- ANSWER REVIEW MODAL -->
                <?php if ($ad && count($ad) > 0) { ?>
                <div class="modal fade" id="answerReviewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content" style="border-radius:16px;">
                            <div class="modal-header border-0 pb-0"><h5 class="fw-bold"><i class="bi bi-list-check me-2"></i>Answer Review</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body pt-3">
                                <?php $qn = 1; foreach ($ad as $a) { $ic = (int)($a['is_correct'] ?? 0); $sa = $a['student_answer'] ?? ''; $ca = $a['correct_answer'] ?? ''; $opts = array('A'=>$a['option_a'], 'B'=>$a['option_b'], 'C'=>$a['option_c'], 'D'=>$a['option_d']); ?>
                                <div class="sp-exam-card mb-2" style="box-shadow:none;padding:14px 16px;">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'':'none'" style="cursor:pointer;">
                                        <div class="fw-bold me-2">Q<?php echo $qn++; ?>. <?php echo h($a['question']); ?></div>
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                            <span class="sp-pill <?php echo $a['difficulty']=='Easy'?'is-completed':($a['difficulty']=='Hard'?'is-expired':'is-upcoming'); ?>" style="font-size:.65rem;"><?php echo h($a['difficulty']); ?></span>
                                            <span class="sp-pill <?php echo $ic?'is-completed':'is-expired'; ?>" style="font-size:.65rem;"><?php echo $ic?'Correct':'Wrong'; ?></span>
                                            <i class="bi bi-chevron-down text-muted"></i>
                                        </div>
                                    </div>
                                    <div class="qb" style="display:none;">
                                        <?php foreach ($opts as $k => $v) { $isS = $k === $sa; $isC = $k === $ca; $cls = $isC ? 'cr' : ($isS && !$ic ? 'wr' : 'nt'); ?>
                                        <div class="d-flex align-items-center gap-2 p-1 rounded mb-1 small" style="background:<?php echo $cls==='cr'?'#dcfce7':($cls==='wr'?'#fee2e2':'#f8fafc'); ?>;">
                                            <?php if ($isC) { ?><i class="bi bi-check-circle-fill text-success"></i><?php } elseif ($isS && !$ic) { ?><i class="bi bi-x-circle-fill text-danger"></i><?php } else { ?><span style="width:16px"></span><?php } ?>
                                            <strong><?php echo $k; ?>.</strong> <span><?php echo h($v); ?></span>
                                            <?php if ($isS) { ?><span class="badge bg-secondary ms-auto" style="font-size:.58rem">Your answer</span><?php } ?>
                                            <?php if ($isC) { ?><span class="badge bg-success ms-1" style="font-size:.58rem">Correct</span><?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <div class="modal-footer border-0 pt-0"><button class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <?php } else { ?>
                <div class="sp-empty sp-fade-up">
                    <div class="sp-empty-icon"><i class="bi bi-trophy"></i></div>
                    <h4>No Results Yet</h4>
                    <p>Complete your first examination to see your scores, accuracy and detailed performance here.</p>
                    <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-play-circle"></i> Take an Exam</a>
                </div>
                <?php } ?>

                <!-- PREVIOUS ATTEMPTS -->
                <div class="mt-5">
                    <div class="sp-panel-head"><h3><i class="bi bi-clock-history me-1 text-primary"></i>Previous Attempts</h3></div>
                    <?php if ($prevQ && mysqli_num_rows($prevQ) > 0) { ?>
                    <div class="row g-3">
                        <?php while ($r = mysqli_fetch_assoc($prevQ)) {
                            $reid = (int)$r['exam_id'];
                            $ro = max((int)$r['marks_obtained'], 0);
                            $rt = max((int)$r['total_questions'], 0);
                            $rc = max((int)$r['correct_answers'], 0);
                            $rw = max((int)$r['wrong_answers'], 0);
                            $rs = max($rt - $rc - $rw, 0);
                            $rp = min((float)$r['percentage'], 100);
                            $rtm = max((int)$r['exam_total_marks'], $rt, 1);
                            $rpm = min((int)$r['pass_marks'], $rtm);
                            $rpass = $ro >= $rpm;
                            $rtime = max((int)$r['time_spent_seconds'], 0);
                            $isAd = ($r['exam_mode'] ?? 'Standard') === 'Adaptive';
                            $aQ = sp_q($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id=? AND student_id=? AND id<=?", "iii", array($reid, $student_id, (int)$r['id']));
                            $ran = 0; if ($aQ && $aR = mysqli_fetch_assoc($aQ)) { $ran = $aR['c']; }
                        ?>
                        <div class="col-md-6 sp-fade-up">
                            <a href="result.php?exam_id=<?php echo $reid; ?>" class="text-decoration-none text-reset d-block">
                                <div class="sp-exam-card">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <h3 class="sp-exam-title" style="font-size:.98rem;"><?php echo h($r['exam_title']); ?></h3>
                                            <small class="text-muted"><?php echo h($r['subject_name']); ?> &middot; Attempt #<?php echo $ran; ?> &middot; <?php echo h(fmt_datetime($r['submitted_at'])); ?></small>
                                        </div>
                                        <span class="sp-pill <?php echo $rpass ? 'is-completed' : 'is-expired'; ?>"><?php echo $rpass ? 'Pass' : 'Fail'; ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="sp-pill is-available"><i class="bi bi-<?php echo $isAd ? 'robot' : 'gear'; ?>"></i><?php echo $isAd ? 'Adaptive' : 'Standard'; ?></span>
                                        <span class="sp-pill is-upcoming"><?php echo number_format($rp, 1); ?>%</span>
                                        <span class="sp-pill"><?php echo $ro; ?>/<?php echo $rtm; ?> marks</span>
                                        <?php if ($isAd) { $prevAb = theta_ability($r['theta_score']); if ($prevAb) { ?><span class="sp-pill is-adaptive" title="Estimated ability (theta = <?php echo h(round((float)$r['theta_score'], 2)); ?>)">Ability <?php echo $prevAb['score']; ?>/100</span><?php } } ?>
                                    </div>
                                    <div class="row g-1 small text-center">
                                        <div class="col-3"><span class="text-muted d-block">Correct</span><span class="text-success fw-bold"><?php echo $rc; ?></span></div>
                                        <div class="col-3"><span class="text-muted d-block">Wrong</span><span class="text-danger fw-bold"><?php echo $rw; ?></span></div>
                                        <div class="col-3"><span class="text-muted d-block">Skipped</span><span class="text-secondary fw-bold"><?php echo $rs; ?></span></div>
                                        <div class="col-3"><span class="text-muted d-block">Time</span><span class="fw-bold"><?php echo fmt_sec($rtime); ?></span></div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } else { ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-clock-history me-2"></i>No previous attempts found.</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['submitted'])) { ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, "", "result.php<?php echo $exam_id ? '?exam_id=' . (int)$exam_id : ''; ?>");
    }
    Swal.fire({
        title: "<?php echo $submittedAdaptive ? 'Adaptive Exam Completed!' : 'Exam Completed'; ?>",
        text: "<?php echo $submittedAdaptive ? 'Your AI-powered adaptive exam is complete.' : 'Your exam was submitted successfully.'; ?>",
        icon: "success",
        confirmButtonText: "View Result",
        confirmButtonColor: "#2563EB"
    });
});
</script>
<?php } ?>
<script>
function showAnswerReview() { var m = new bootstrap.Modal(document.getElementById('answerReviewModal')); m.show(); }
/* Dynamic AI analysis: cached JSON renders instantly; otherwise skeleton → fetch once. */
(function () {
    var resultId = <?php echo (int)$latest_id; ?>;
    var hasCached = <?php echo $ai_cached ? "true" : "false"; ?>;
    var body = document.getElementById('aiAnalysisBody');
    var regenBtn = document.getElementById('aiRegenBtn');
    if (!body || !resultId) { return; }

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function badge(acc) { return acc >= 70 ? 'bg-success' : (acc >= 40 ? 'bg-warning text-dark' : 'bg-danger'); }

    function render(a, metrics) {
        if (!a || !a.overall_analysis) {
            body.innerHTML = '<div class="alert alert-warning mb-0">AI analysis is temporarily unavailable. Your scores and difficulty metrics above are calculated from your actual result.</div>';
            return;
        }
        var h = '<div class="ai-struct">';
        if (a._fallback) { h += '<div class="alert alert-info py-2 px-3 small mb-0"><i class="bi bi-info-circle me-1"></i>AI service is temporarily unavailable — showing analysis computed from your actual result data.</div>'; }
        h += '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-person-check me-1 text-primary"></i>Overall Performance</h6><p class="mb-0">' + esc(a.overall_analysis) + '</p></div>';
        function list(title, icon, items, ordered) {
            if (!items || !items.length) { return ''; }
            var t = '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi ' + icon + ' me-1"></i>' + title + '</h6>' + (ordered ? '<ol class="ai-list mb-0">' : '<ul class="ai-list mb-0">');
            items.forEach(function (x) { t += '<li>' + esc(x) + '</li>'; });
            return t + (ordered ? '</ol></div>' : '</ul></div>');
        }
        h += list('Strengths', 'bi-emoji-smile text-success', a.strengths, false);
        h += list('Areas for Improvement', 'bi-bullseye text-warning', a.areas_for_improvement, false);
        var diffNums = (metrics && metrics.difficulty) ? metrics.difficulty : {};
        var diffText = a.difficulty_analysis || {};
        var levels = Object.keys(diffNums).length ? Object.keys(diffNums) : Object.keys(diffText);
        if (levels.length) {
            h += '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-bar-chart-line me-1 text-primary"></i>Difficulty Analysis</h6><div class="row g-2">';
            levels.forEach(function (lv) {
                var n = diffNums[lv] || null;
                var cls = lv === 'Easy' ? 'is-completed' : (lv === 'Hard' ? 'is-expired' : 'is-upcoming');
                h += '<div class="col-12 col-md-4"><div class="ai-diff-card ' + cls + '"><div class="d-flex justify-content-between align-items-center mb-1"><strong>' + esc(lv) + '</strong>';
                if (n) { h += '<span class="badge ' + badge(n.accuracy) + '">' + n.accuracy + '%</span>'; }
                h += '</div>';
                if (n) { h += '<div class="small text-muted mb-1">' + n.correct + ' / ' + n.attempted + ' correct</div><div class="ai-bar"><span style="width:' + Math.max(0, Math.min(100, n.accuracy)) + '%;"></span></div>'; }
                if (diffText[lv]) { h += '<p class="small mb-0 mt-1">' + esc(diffText[lv]) + '</p>'; }
                h += '</div></div>';
            });
            h += '</div></div>';
        }
        if (a.topic_analysis && a.topic_analysis.length) {
            h += '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-book me-1 text-primary"></i>Topic Performance</h6><div class="row g-2">';
            a.topic_analysis.forEach(function (t) {
                h += '<div class="col-12 col-md-6"><div class="ai-topic"><div class="d-flex justify-content-between align-items-center"><strong>' + esc(t.topic) + '</strong><span class="badge ' + badge(t.accuracy) + '">' + t.accuracy + '%</span></div>';
                if (t.attempted != null) { h += '<div class="small text-muted">' + t.correct + ' / ' + t.attempted + ' correct</div>'; }
                if (t.analysis) { h += '<p class="small mb-0 mt-1">' + esc(t.analysis) + '</p>'; }
                h += '</div></div>';
            });
            h += '</div></div>';
        }
        h += list('Recommended Learning', 'bi-journal-check text-primary', a.recommendations, true);
        h += list('Next Steps', 'bi-rocket-takeoff text-primary', a.next_steps, false);
        h += '</div>';
        body.innerHTML = h;
    }

    function skeleton() {
        body.innerHTML = '<div class="d-flex align-items-center gap-2 mb-3 text-primary"><span class="ai-typing"><i></i><i></i><i></i></span><strong>AI is analyzing your performance...</strong></div>'
            + '<div class="ai-skel"><span style="height:16px;width:92%;"></span><span style="height:16px;width:78%;"></span><span style="height:16px;width:85%;"></span><span style="height:64px;"></span><span style="height:16px;width:60%;"></span></div>';
    }

    function load(force) {
        skeleton();
        if (regenBtn) { regenBtn.disabled = true; regenBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Analyzing...'; }
        var fd = new FormData();
        fd.append('result_id', resultId);
        if (force) { fd.append('force', '1'); }
        fetch('ai_analysis_ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.success && d.analysis) { render(d.analysis, d.metrics || null); }
                else { render(null); }
            })
            .catch(function () { render(null); })
            .finally(function () {
                if (regenBtn) { regenBtn.disabled = false; regenBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Regenerate Analysis'; }
            });
    }

    if (regenBtn) { regenBtn.addEventListener('click', function () { load(true); }); }
    if (!hasCached) { load(false); }
})();
document.addEventListener("DOMContentLoaded", function () {
    if (typeof ChartManager === "undefined") { return; }
    ChartManager.create("attemptChart", { type: "doughnut", data: { labels: ["Correct","Wrong","Skipped"], datasets: [{ data: [<?php echo (int)($summary['correct_total']??0); ?>,<?php echo (int)($summary['wrong_total']??0); ?>,<?php echo (int)($summary['skipped_total']??0); ?>], backgroundColor: ["#16A34A","#DC2626","#94A3B8"] }] }, options: { cutout: "62%", plugins: { legend: { display: false } } } });
    ChartManager.create("subjectChart", { type: "bar", data: { labels: [<?php echo $subj_labels; ?>], datasets: [{ label: "Avg %", data: [<?php echo $subj_values; ?>], backgroundColor: "#4F46E5", borderRadius: 6 }] }, options: { scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + "%"; } } } }, plugins: { legend: { display: false } } } });
    ChartManager.create("difficultyChart", { type: "bar", data: { labels: [<?php echo $diff_labels; ?>], datasets: [{ label: "Accuracy %", data: [<?php echo $diff_values; ?>], backgroundColor: "#0D9488", borderRadius: 6 }] }, options: { scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + "%"; } } } }, plugins: { legend: { display: false } } } });
});
</script>
<?php include("../app/layouts/student_footer.php"); mysqli_close($conn); ?>
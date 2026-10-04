<?php
require_once("../app/config/database.php");
$conn = conn(); require_admin();
$student_id = (int)($_GET['student_id'] ?? 0);
if (!$student_id) { echo '<div class="alert alert-danger">Invalid student ID.</div>'; exit; }

$student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id=$student_id"));
if (!$student) { echo '<div class="alert alert-danger">Student not found.</div>'; exit; }

$results = mysqli_query($conn, "SELECT r.*, e.exam_title, e.exam_mode, e.total_marks AS exam_total, sub.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE r.student_id=$student_id ORDER BY r.id DESC");
$total_att = 0; $pass_att = 0; $fail_att = 0; $best_pct = 0; $best_exam = '';
$all_marks = [];
while ($r = mysqli_fetch_assoc($results)) {
    $total_att++; $pct = round((float)$r['percentage'],2);
    if ($r['status'] == 'Pass') $pass_att++; else $fail_att++;
    if ($pct > $best_pct) { $best_pct = $pct; $best_exam = $r['exam_title']; }
    $all_marks[] = $r;
}
$avg_pct = $total_att > 0 ? round(array_sum(array_column($all_marks,'percentage')) / $total_att, 1) : 0;

// AI Insight (latest result)
$ai_insight = '';
$theta_score = null;
$skill_category = '';
if (!empty($all_marks[0]['ai_insight'])) $ai_insight = $all_marks[0]['ai_insight'];
if (!empty($all_marks[0]['theta_score'])) $theta_score = round((float)$all_marks[0]['theta_score'], 2);
if (!empty($all_marks[0]['skill_category'])) $skill_category = $all_marks[0]['skill_category'];
$answers_detail = [];
if (!empty($all_marks[0]['answers_detail'])) $answers_detail = json_decode($all_marks[0]['answers_detail'], true) ?: [];

// Performance trend (correct rate over attempts)
$trend_labels = ''; $trend_values = '';
foreach (array_reverse($all_marks) as $i => $r) {
    $trend_labels .= "'Attempt " . ($i+1) . "',";
    $trend_values .= round((float)$r['percentage'],1) . ",";
}
?>
<div class="row g-3">
    <!-- Student Info -->
    <div class="col-md-4">
        <div class="d-flex align-items-center gap-3 p-3" style="background:#f8fafc;border-radius:12px;">
            <?php
            $ax_img = trim((string)($student['profile_image'] ?? ""));
            if ($ax_img !== "" && file_exists(__DIR__ . "/../assets/uploads/profile/" . $ax_img)) {
                echo '<img src="../assets/uploads/profile/' . h($ax_img) . '" alt="Profile photo of ' . h($student['name']) . '" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;">';
            } else {
                echo '<span style="width:48px;height:48px;border-radius:50%;background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:700;flex-shrink:0;" aria-hidden="true">' . h(strtoupper(substr((string)$student['name'], 0, 1))) . '</span>';
            }
            ?>
            <div class="min-w-0">
                <strong><?php echo h($student['name']); ?></strong><br>
                <small class="text-muted"><?php echo h($student['email']); ?></small><br>
                <small class="text-muted"><?php echo h($student['course']); ?> - Sem <?php echo h($student['semester']); ?></small>
            </div>
        </div>
    </div>
    <!-- Stats -->
    <div class="col-md-8">
        <div class="row g-2">
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#dbeafe;border-radius:8px;"><strong class="d-block fs-5"><?php echo $total_att; ?></strong><small>Attempts</small></div></div>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#d1fae5;border-radius:8px;"><strong class="d-block fs-5"><?php echo $pass_att; ?></strong><small>Pass</small></div></div>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#fee2e2;border-radius:8px;"><strong class="d-block fs-5"><?php echo $fail_att; ?></strong><small>Fail</small></div></div>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#fef3c7;border-radius:8px;"><strong class="d-block fs-5"><?php echo $avg_pct; ?>%</strong><small>Avg</small></div></div>
            <?php if ($theta_score !== null) { ?>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#f3e8ff;border-radius:8px;"><strong class="d-block fs-5"><?php echo $theta_score; ?></strong><small>Theta</small></div></div>
            <?php } if ($skill_category) { ?>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#e0e7ff;border-radius:8px;"><strong class="d-block fs-5"><?php echo h($skill_category); ?></strong><small>Skill</small></div></div>
            <?php } ?>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#ecfdf5;border-radius:8px;"><strong class="d-block fs-5"><?php echo $best_pct; ?>%</strong><small>Best</small></div></div>
            <div class="col-3 col-md-3"><div class="p-2 text-center" style="background:#fff7ed;border-radius:8px;"><strong class="d-block fs-5" style="font-size:.85rem!important"><?php echo h($best_exam); ?></strong><small>Best Exam</small></div></div>
        </div>
    </div>
</div>

<!-- Performance Trend Chart -->
<?php if ($trend_labels) { ?>
<div class="mt-3">
    <h6 class="fw-bold small">Performance Trend</h6>
    <div class="chart-container chart-container-sm"><canvas id="trendChart" role="img" aria-label="Performance trend line chart"></canvas></div>
    <script>
    if (typeof ChartManager !== 'undefined') {
        ChartManager.create('trendChart', {
            type: 'line',
            data: { labels: [<?php echo $trend_labels; ?>], datasets: [{ data: [<?php echo $trend_values; ?>], borderColor: '#2454d6', backgroundColor: 'rgba(36,84,214,.1)', fill: true, tension: .3, pointRadius: 3 }] },
            options: { scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } } }, plugins: { legend: { display: false } } }
        });
    }
    </script>
</div>
<?php } ?>

<!-- AI Analysis -->
<?php if ($ai_insight) { ?>
<div class="mt-3 ai-insight-box p-3 rounded-3">
    <strong class="small"><i class="bi bi-robot me-1"></i>AI Analysis</strong>
    <p class="mb-0 small mt-1" style="white-space:pre-line"><?php echo h($ai_insight); ?></p>
</div>
<?php } ?>

<!-- All Attempts -->
<h6 class="fw-bold mt-3 mb-2">All Attempts</h6>
<div class="rp-modal-review">
<?php foreach ($all_marks as $idx => $r) {
    $pct = round((float)$r['percentage'],1);
    $isAd = ($r['exam_mode'] ?? 'Standard') === 'Adaptive';
    $ad = [];
    if (!empty($r['answers_detail'])) $ad = json_decode($r['answers_detail'], true) ?: [];
    if (empty($ad) && !empty($r['session_id'])) {
        $fq = mysqli_query($conn, "SELECT aa.*, q.correct_answer, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.marks, q.difficulty FROM adaptive_answers aa LEFT JOIN questions q ON aa.question_id = q.id WHERE aa.session_id = " . (int)$r['session_id'] . " ORDER BY aa.id");
        while ($fa = mysqli_fetch_assoc($fq)) $ad[] = $fa;
    }
    $marks_total = max((int)$r['exam_total'], (int)$r['total_questions'], 1);
?>
<div class="ar-card" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:.5rem;overflow:hidden;">
    <div class="d-flex justify-content-between align-items-center p-2 px-3" style="cursor:pointer;background:#f8fafc;" onclick="var n=this.nextElementSibling;n.style.display=n.style.display=='none'?'':'none'">
        <div class="d-flex align-items-center gap-2">
            <strong style="font-size:.85rem"><?php echo h($r['exam_title']); ?></strong>
            <?php if($isAd){?><span class="badge bg-info text-dark" style="font-size:.6rem">Adaptive</span><?php } ?>
            <span class="text-muted small">- <?php echo h($r['submitted_at']); ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold" style="color:<?php echo $pct>=50?'#10b981':'#ef4444'; ?>"><?php echo $pct; ?>%</span>
            <span class="rp-badge <?php echo $r['status']=='Pass'?'rp-badge-pass':'rp-badge-fail'; ?>"><?php echo $r['status']; ?></span>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
    </div>
    <div style="display:none;padding:.5rem 1rem 1rem;">
        <div class="row g-2 mb-2">
            <div class="col-4 col-md-2"><small class="text-muted d-block">Marks</small><strong><?php echo (int)$r['marks_obtained']; ?>/<?php echo $marks_total; ?></strong></div>
            <div class="col-4 col-md-2"><small class="text-muted d-block">Correct</small><strong class="text-success"><?php echo (int)$r['correct_answers']; ?></strong></div>
            <div class="col-4 col-md-2"><small class="text-muted d-block">Wrong</small><strong class="text-danger"><?php echo (int)$r['wrong_answers']; ?></strong></div>
            <div class="col-4 col-md-2"><small class="text-muted d-block">Skipped</small><strong><?php echo max(0, (int)$r['total_questions'] - (int)$r['correct_answers'] - (int)$r['wrong_answers']); ?></strong></div>
            <?php if ($r['theta_score'] !== null) { ?><div class="col-4 col-md-2"><small class="text-muted d-block">Theta</small><strong><?php echo round((float)$r['theta_score'],2); ?></strong></div><?php } ?>
            <div class="col-4 col-md-2"><small class="text-muted d-block">Attempt</small><strong>#<?php echo $idx+1; ?></strong></div>
        </div>
        <?php if (count($ad) > 0) { ?>
        <h6 class="small fw-bold mb-2">Question Review</h6>
        <?php $qn=1; foreach ($ad as $a) { $ic=(int)($a['is_correct']??0); $sa=$a['student_answer']??''; $ca=$a['correct_answer']??''; $opts=['A'=>$a['option_a'],'B'=>$a['option_b'],'C'=>$a['option_c'],'D'=>$a['option_d']]; ?>
        <div class="ar-card" style="border:1px solid #e2e8f0;border-radius:6px;margin-bottom:.3rem;">
            <div class="p-2 px-3" style="cursor:pointer;background:#fafbff;" onclick="var n=this.nextElementSibling;n.style.display=n.style.display=='none'?'':'none'">
                <div class="d-flex justify-content-between align-items-center">
                    <span><span class="fw-bold me-2">Q<?php echo $qn++; ?>.</span><?php echo h($a['question']); ?></span>
                    <span class="rp-badge <?php echo $ic?'rp-badge-pass':'rp-badge-fail'; ?>"><?php echo $ic?'Correct':'Wrong'; ?></span>
                </div>
            </div>
            <div style="display:none;padding:.3rem .7rem .5rem 1rem;">
                <?php foreach ($opts as $k=>$v) { $isS=$k===$sa; $isC=$k===$ca; $cls='ol'; if($isC) $cls.=' cr'; elseif($isS&&!$ic) $cls.=' wr'; else $cls.=' nt'; ?>
                <div class="<?php echo $cls; ?>"><?php if($isC){?><i class="bi bi-check-circle-fill text-success"></i><?php }elseif($isS&&!$ic){?><i class="bi bi-x-circle-fill text-danger"></i><?php }else{?><span style="width:16px"></span><?php }?> <strong><?php echo $k; ?>.</strong> <?php echo h($v); ?><?php if($isS){?><span class="badge bg-secondary ms-1" style="font-size:.55rem">Your answer</span><?php } ?><?php if($isC){?><span class="badge bg-success ms-1" style="font-size:.55rem">Correct</span><?php } ?></div>
                <?php } ?>
            </div>
        </div>
        <?php } } else { ?><div class="text-muted small">No question details available.</div><?php } ?>
    </div>
</div>
<?php } ?>
</div>
<script>if(typeof MathJax!=='undefined')try{MathJax.typesetPromise()}catch(e){}</script>

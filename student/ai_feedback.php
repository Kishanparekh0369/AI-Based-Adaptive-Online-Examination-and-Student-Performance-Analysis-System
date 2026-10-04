<?php
require_once("../app/config/database.php");
require_once("../app/config/ai.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "AI Feedback";
include("../app/layouts/student_header.php");

$studentId = (int)$_SESSION["student_id"];

/* Load this student's results (own data only) */
function sp_load_ai_results($conn, $studentId)
{
    $rows = array();
    $stmt = mysqli_prepare($conn, "SELECT r.*, e.exam_title, e.exam_mode, s.subject_name
        FROM results r
        LEFT JOIN exams e ON r.exam_id = e.id
        LEFT JOIN subjects s ON e.subject_id = s.id
        WHERE r.student_id = ?
        ORDER BY r.id DESC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $studentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) { $rows[] = $row; }
        }
        mysqli_stmt_close($stmt);
    }
    return $rows;
}

$results = sp_load_ai_results($conn, $studentId);

/* Structured analysis cache (per-attempt JSON). Generated on demand via
 * ai_analysis_ajax.php — never in a page-load loop, never static text. */
require_once("../app/ai/PerformanceService.php");
require_once("../app/ai/analysis_view.php");
$ai_svc = new PerformanceService();
$ai_svc->ensureColumn($conn);
$ai_cache = array();
if (!empty($results)) {
    $ids = array();
    foreach ($results as $row) { $ids[] = (int)$row["id"]; }
    $id_list = implode(",", $ids);
    $cq = mysqli_query($conn, "SELECT id, ai_analysis_json FROM results WHERE id IN ($id_list) AND student_id = $studentId");
    if ($cq) {
        while ($crow = mysqli_fetch_assoc($cq)) {
            if (!empty($crow["ai_analysis_json"])) {
                $dec = json_decode($crow["ai_analysis_json"], true);
                if (is_array($dec) && !empty($dec["overall_analysis"])) { $ai_cache[(int)$crow["id"]] = $dec; }
            }
        }
    }
}

/* Real, data-driven summary (computed, not fabricated) */
$insight_results = array_values($results); // every own attempt gets a card; analysis generates on demand
$ai_done_count = count($ai_cache);

$attempted = count($results);
$avg_pct = 0;
$best = 0;
$trend = null;
if ($attempted > 0) {
    $sum = 0;
    foreach ($results as $r) { $sum += (float)$r["percentage"]; }
    $avg_pct = round($sum / $attempted, 1);
    $best = round((float)max(array_map(function ($r) { return (float)$r["percentage"]; }, $results)), 1);

    if ($attempted >= 2) {
        $recent = (float)$results[0]["percentage"];
        $previous = (float)$results[1]["percentage"];
        $trend = round($recent - $previous, 1);
    }
}

$subject_scores = array();
foreach ($insight_results as $r) {
    $subj = $r["subject_name"] ?: "General";
    if (!isset($subject_scores[$subj])) { $subject_scores[$subj] = array("total" => 0, "count" => 0); }
    $subject_scores[$subj]["total"] += (float)$r["percentage"];
    $subject_scores[$subj]["count"]++;
}
$strong_subjects = array();
$weak_subjects = array();
foreach ($subject_scores as $name => $d) {
    $avg = $d["count"] > 0 ? $d["total"] / $d["count"] : 0;
    if ($avg >= 70) { $strong_subjects[] = $name; }
    elseif ($avg < 40) { $weak_subjects[] = $name; }
}
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-ai-badge"><i class="bi bi-robot"></i> AI Powered</span>
                        <h1 class="mt-2">AI Performance Feedback</h1>
                        <p>Personalized insights based on your examination performance.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="sp-btn sp-btn-primary" onclick="if(window.StudentAI){StudentAI.ask('Explain my latest AI performance feedback in simple words.');}"><i class="bi bi-robot"></i> Ask AI About This</button>
                        <a href="performance.php" class="sp-btn sp-btn-soft"><i class="bi bi-graph-up-arrow"></i> Performance</a>
                    </div>
                </div>

                <?php if ($attempted > 0) { ?>
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-lg-3 sp-fade-up" data-delay="1">
                            <div class="sp-stat is-indigo">
                                <span class="sp-stat-icon"><i class="bi bi-chat-square-text"></i></span>
                                <div class="sp-stat-value"><?php echo $ai_done_count; ?></div>
                                <div class="sp-stat-label">AI Insights</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3 sp-fade-up" data-delay="2">
                            <div class="sp-stat is-success">
                                <span class="sp-stat-icon"><i class="bi bi-percent"></i></span>
                                <div class="sp-stat-value"><?php echo $avg_pct; ?>%</div>
                                <div class="sp-stat-label">Average Score</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3 sp-fade-up" data-delay="3">
                            <div class="sp-stat is-warning">
                                <span class="sp-stat-icon"><i class="bi bi-trophy"></i></span>
                                <div class="sp-stat-value"><?php echo $best; ?>%</div>
                                <div class="sp-stat-label">Best Score</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3 sp-fade-up" data-delay="4">
                            <div class="sp-stat <?php echo $trend === null ? "" : ($trend >= 0 ? "is-success" : "is-danger"); ?>">
                                <span class="sp-stat-icon"><i class="bi bi-activity"></i></span>
                                <div class="sp-stat-value">
                                    <?php if ($trend === null) { echo "—"; } else { echo ($trend >= 0 ? "+" : "") . $trend . "%"; } ?>
                                </div>
                                <div class="sp-stat-label">Latest vs Previous</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($strong_subjects) || !empty($weak_subjects)) { ?>
                    <div class="row g-3 mb-4">
                        <?php if (!empty($strong_subjects)) { ?>
                        <div class="col-md-6 sp-fade-up">
                            <div class="sp-panel">
                                <div class="sp-panel-head"><h3><i class="bi bi-hand-thumbs-up me-2 text-success"></i>Strengths</h3></div>
                                <div class="sp-chip-list">
                                    <?php foreach ($strong_subjects as $s) { ?><span class="sp-chip is-good"><i class="bi bi-check2-circle"></i><?php echo h($s); ?></span><?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if (!empty($weak_subjects)) { ?>
                        <div class="col-md-6 sp-fade-up" data-delay="2">
                            <div class="sp-panel">
                                <div class="sp-panel-head"><h3><i class="bi bi-bullseye me-2 text-warning"></i>Areas for Improvement</h3></div>
                                <div class="sp-chip-list">
                                    <?php foreach ($weak_subjects as $s) { ?><span class="sp-chip is-warn"><i class="bi bi-exclamation-circle"></i><?php echo h($s); ?></span><?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <div class="row g-4" id="aiFeedbackCards">
                        <?php $i = 0; foreach ($insight_results as $row) { $i++; $rid = (int)$row["id"]; $cached = $ai_cache[$rid] ?? null; ?>
                            <div class="col-12 sp-fade-up" data-delay="<?php echo min($i, 6); ?>">
                                <div class="sp-ai-panel" data-ai-card="<?php echo $rid; ?>">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <h3 class="mb-1" style="font-size:1.05rem;font-weight:700;color:#0F172A;">
                                                <i class="bi bi-robot me-1 text-primary"></i><?php echo h($row["exam_title"] ?? "Exam"); ?>
                                            </h3>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <span class="sp-pill is-available"><i class="bi bi-book"></i><?php echo h($row["subject_name"] ?? "General"); ?></span>
                                                <span class="sp-pill <?php echo (float)$row["percentage"] >= 40 ? "is-completed" : "is-expired"; ?>"><?php echo number_format((float)$row["percentage"], 1); ?>%</span>
                                                <?php if (!empty($row["skill_category"])) { ?>
                                                    <span class="sp-pill is-adaptive"><i class="bi bi-award"></i><?php echo h($row["skill_category"]); ?></span>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo h($row["submitted_at"] ?? ""); ?></small>
                                    </div>

                                    <div data-ai-body="<?php echo $rid; ?>">
                                        <?php if ($cached) { echo render_ai_analysis($cached, $cached["_metrics"] ?? null); } ?>
                                    </div>

                                    <div class="mt-3 d-flex flex-wrap gap-2">
                                        <a href="result.php?exam_id=<?php echo (int)$row["exam_id"]; ?>" class="sp-btn sp-btn-soft"><i class="bi bi-bar-chart"></i> View Full Result</a>
                                        <button type="button" class="sp-btn sp-btn-ghost" data-ai-generate="<?php echo $rid; ?>"><i class="bi bi-stars"></i> <?php echo $cached ? "Refresh Analysis" : "Generate AI Analysis"; ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <script>
                    (function () {
                        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
                        function badge(acc) { return acc >= 70 ? 'bg-success' : (acc >= 40 ? 'bg-warning text-dark' : 'bg-danger'); }
                        function renderInto(body, a, metrics) {
                            if (!a || !a.overall_analysis) {
                                body.innerHTML = '<div class="alert alert-warning mb-0">AI analysis is temporarily unavailable. Open the full result for your calculated scores.</div>';
                                return;
                            }
                            var h = '<div class="ai-struct">';
                            h += '<div class="ai-sec"><p class="mb-0">' + esc(a.overall_analysis) + '</p></div>';
                            var diff = (metrics && metrics.difficulty) ? metrics.difficulty : {};
                            var levels = Object.keys(diff);
                            if (levels.length) {
                                h += '<div class="d-flex flex-wrap gap-2">';
                                levels.forEach(function (lv) {
                                    var n = diff[lv];
                                    h += '<span class="sp-pill">' + esc(lv) + ': ' + n.correct + '/' + n.attempted + ' (' + n.accuracy + '%)</span>';
                                });
                                h += '</div>';
                            }
                            if (a.areas_for_improvement && a.areas_for_improvement.length) {
                                h += '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-bullseye me-1 text-warning"></i>Key Focus</h6><p class="small mb-0">' + esc(a.areas_for_improvement[0]) + '</p></div>';
                            }
                            h += '</div>';
                            body.innerHTML = h;
                        }
                        function loadCard(rid, btn, auto) {
                            var body = document.querySelector('[data-ai-body="' + rid + '"]');
                            if (!body) { return; }
                            if (auto && body.innerHTML.trim() !== '') { return; }
                            body.innerHTML = '<div class="d-flex align-items-center gap-2 text-primary"><span class="ai-typing"><i></i><i></i><i></i></span><strong>AI is analyzing your performance...</strong></div><div class="ai-skel mt-2"><span style="height:14px;width:90%;"></span><span style="height:14px;width:70%;"></span></div>';
                            if (btn) { btn.disabled = true; }
                            var fd = new FormData();
                            fd.append('result_id', rid);
                            fetch('ai_analysis_ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                                .then(function (r) { return r.json(); })
                                .then(function (d) {
                                    if (d && d.success && d.analysis) { renderInto(body, d.analysis, d.metrics || null); if (btn) { btn.innerHTML = '<i class="bi bi-stars"></i> Refresh Analysis'; } }
                                    else { body.innerHTML = '<div class="alert alert-warning mb-0">AI analysis is temporarily unavailable.</div>'; }
                                })
                                .catch(function () { body.innerHTML = '<div class="alert alert-warning mb-0">AI analysis is temporarily unavailable.</div>'; })
                                .finally(function () { if (btn) { btn.disabled = false; } });
                        }
                        document.querySelectorAll('[data-ai-generate]').forEach(function (btn) {
                            btn.addEventListener('click', function () { loadCard(btn.getAttribute('data-ai-generate'), btn, false); });
                        });
                        var first = document.querySelector('[data-ai-generate]');
                        if (first) {
                            var firstBody = document.querySelector('[data-ai-body="' + first.getAttribute('data-ai-generate') + '"]');
                            if (firstBody && firstBody.innerHTML.trim() === '') { loadCard(first.getAttribute('data-ai-generate'), first, false); }
                        }
                    })();
                    </script>
                <?php } else { ?>
                    <div class="sp-empty">
                        <div class="sp-empty-icon"><i class="bi bi-robot"></i></div>
                        <h4>No AI Feedback Yet</h4>
                        <p>Complete an examination to receive personalized AI-powered performance insights, strengths analysis and study recommendations.</p>
                        <a href="exams.php" class="sp-btn sp-btn-primary"><i class="bi bi-play-circle"></i> Take an Exam</a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
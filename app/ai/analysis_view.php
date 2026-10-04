<?php
/* Shared renderer for structured AI performance analysis.
 * Input: analysis array (overall_analysis, strengths, areas_for_improvement,
 * difficulty_analysis, topic_analysis, recommendations, next_steps, _fallback?)
 * + optional $metrics (difficulty numbers). All output HTML-escaped.
 */
function render_ai_analysis($a, $metrics = null) {
    if (!is_array($a) || empty($a["overall_analysis"])) { return ""; }
    $e = function ($v) { return h((string)$v); };
    $html = '<div class="ai-struct">';
    if (!empty($a["_fallback"])) {
        $html .= '<div class="alert alert-info py-2 px-3 small mb-3"><i class="bi bi-info-circle me-1"></i>AI service is temporarily unavailable — showing analysis computed from your actual result data.</div>';
    }
    $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-person-check me-1 text-primary"></i>Overall Performance</h6><p class="mb-0">' . $e($a["overall_analysis"]) . '</p></div>';
    if (!empty($a["strengths"])) {
        $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-emoji-smile me-1 text-success"></i>Strengths</h6><ul class="ai-list mb-0">';
        foreach ($a["strengths"] as $s) { $html .= '<li>' . $e($s) . '</li>'; }
        $html .= '</ul></div>';
    }
    if (!empty($a["areas_for_improvement"])) {
        $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-bullseye me-1 text-warning"></i>Areas for Improvement</h6><ul class="ai-list mb-0">';
        foreach ($a["areas_for_improvement"] as $s) { $html .= '<li>' . $e($s) . '</li>'; }
        $html .= '</ul></div>';
    }
    // Difficulty cards — only levels with real attempt data.
    $diffNums = is_array($metrics) && isset($metrics["difficulty"]) ? $metrics["difficulty"] : null;
    $diffText = isset($a["difficulty_analysis"]) && is_array($a["difficulty_analysis"]) ? $a["difficulty_analysis"] : array();
    $levels = $diffNums ? array_keys($diffNums) : array_keys($diffText);
    if (!empty($levels)) {
        $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-bar-chart-line me-1 text-primary"></i>Difficulty Analysis</h6><div class="row g-2">';
        foreach ($levels as $level) {
            $num = $diffNums && isset($diffNums[$level]) ? $diffNums[$level] : null;
            $txt = isset($diffText[$level]) ? (string)$diffText[$level] : "";
            $cls = $level === "Easy" ? "is-completed" : ($level === "Hard" ? "is-expired" : "is-upcoming");
            $html .= '<div class="col-12 col-md-4"><div class="ai-diff-card ' . $cls . '"><div class="d-flex justify-content-between align-items-center mb-1"><strong>' . $e($level) . '</strong>';
            if ($num) {
                $html .= '<span class="badge ' . ($num["accuracy"] >= 70 ? "bg-success" : ($num["accuracy"] >= 40 ? "bg-warning text-dark" : "bg-danger")) . '">' . $num["accuracy"] . '%</span>';
            }
            $html .= '</div>';
            if ($num) {
                $html .= '<div class="small text-muted mb-1">' . (int)$num["correct"] . ' / ' . (int)$num["attempted"] . ' correct</div>';
                $html .= '<div class="ai-bar"><span style="width:' . max(0, min(100, (float)$num["accuracy"])) . '%;"></span></div>';
            }
            if ($txt !== "") { $html .= '<p class="small mb-0 mt-1">' . $e($txt) . '</p>'; }
            $html .= '</div></div>';
        }
        $html .= '</div></div>';
    }
    if (!empty($a["topic_analysis"])) {
        $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-book me-1 text-primary"></i>Topic Performance</h6><div class="row g-2">';
        foreach ($a["topic_analysis"] as $t) {
            if (!is_array($t)) { continue; }
            $acc = isset($t["accuracy"]) ? (float)$t["accuracy"] : 0;
            $html .= '<div class="col-12 col-md-6"><div class="ai-topic"><div class="d-flex justify-content-between align-items-center"><strong>' . $e($t["topic"] ?? "") . '</strong><span class="badge ' . ($acc >= 70 ? "bg-success" : ($acc >= 40 ? "bg-warning text-dark" : "bg-danger")) . '">' . $acc . '%</span></div>';
            if (isset($t["attempted"])) { $html .= '<div class="small text-muted">' . (int)($t["correct"] ?? 0) . ' / ' . (int)$t["attempted"] . ' correct</div>'; }
            if (!empty($t["analysis"])) { $html .= '<p class="small mb-0 mt-1">' . $e($t["analysis"]) . '</p>'; }
            $html .= '</div></div>';
        }
        $html .= '</div></div>';
    }
    if (!empty($a["recommendations"])) {
        $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-journal-check me-1 text-primary"></i>Recommended Learning</h6><ol class="ai-list mb-0">';
        foreach ($a["recommendations"] as $s) { $html .= '<li>' . $e($s) . '</li>'; }
        $html .= '</ol></div>';
    }
    if (!empty($a["next_steps"])) {
        $html .= '<div class="ai-sec"><h6 class="ai-sec-title"><i class="bi bi-rocket-takeoff me-1 text-primary"></i>Next Steps</h6><ul class="ai-list mb-0">';
        foreach ($a["next_steps"] as $s) { $html .= '<li>' . $e($s) . '</li>'; }
        $html .= '</ul></div>';
    }
    $html .= '</div>';
    return $html;
}

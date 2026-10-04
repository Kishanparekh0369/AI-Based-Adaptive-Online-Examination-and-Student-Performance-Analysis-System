<?php
/* On-demand AI performance analysis for the AUTHENTICATED student's OWN attempt.
 * - student_id comes ONLY from the session; result ownership verified in SQL.
 * - Cached JSON returned instantly; Groq called only when missing or forced.
 * - Never exposes API keys; never emits PHP errors to the client.
 */
require_once("../app/config/database.php");

header("Content-Type: application/json; charset=UTF-8");

$conn = conn();
require_student();

$studentId = (int)($_SESSION["student_id"] ?? 0);
$resultId = (int)($_POST["result_id"] ?? 0);
$force = !empty($_POST["force"]);

if ($studentId <= 0 || $resultId <= 0) {
    echo json_encode(array("success" => false, "message" => "Invalid request."));
    exit;
}

require_once("../app/ai/PerformanceService.php");
$svc = new PerformanceService();
$svc->ensureColumn($conn);

if (!$force) {
    $cached = $svc->loadCached($conn, $resultId);
    if ($cached) {
        echo json_encode(array("success" => true, "cached" => true, "analysis" => $cached, "ai_live" => empty($cached["_fallback"])));
        exit;
    }
}

$data = $svc->getAttemptData($conn, $studentId, $resultId);
if (!$data) {
    echo json_encode(array("success" => false, "message" => "Result not found."));
    exit;
}

$analysis = $svc->generateAnalysis($data);
$aiLive = true;
if (!$analysis) {
    // Layer 1 still delivers: real computed metrics, zero invented content.
    $analysis = $svc->fallbackAnalysis($data);
    $aiLive = false;
}

$analysis["_metrics"] = array(
    "obtained" => $data["result"]["obtained"],
    "total_marks" => $data["result"]["total_marks"],
    "percentage" => $data["result"]["percentage"],
    "status" => $data["result"]["status"],
    "difficulty" => $data["difficulty"],
    "topics" => $data["topics"],
    "has_topics" => $data["has_topics"],
);
$svc->saveAnalysis($conn, $studentId, $resultId, $analysis);

echo json_encode(array(
    "success" => true,
    "cached" => false,
    "analysis" => $analysis,
    "ai_live" => $aiLive,
    "metrics" => array(
        "obtained" => $data["result"]["obtained"],
        "total_marks" => $data["result"]["total_marks"],
        "percentage" => $data["result"]["percentage"],
        "status" => $data["result"]["status"],
        "difficulty" => $data["difficulty"],
        "topics" => $data["topics"],
        "has_topics" => $data["has_topics"],
    ),
));

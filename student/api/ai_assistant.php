<?php
/**
 * Student AI Assistant endpoint.
 * Identity comes ONLY from the PHP session. Client-supplied
 * student IDs are never accepted.
 */
require_once("../../app/config/database.php");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$conn = conn();

function sp_ai_reply($reply, $actions = array(), $ai_available = true, $extra = array())
{
    $out = array_merge(array(
        "success" => true,
        "reply" => $reply,
        "actions" => $actions,
        "ai_available" => $ai_available,
    ), $extra);
    echo json_encode($out);
    exit;
}

function sp_ai_error($message, $code = 200)
{
    http_response_code($code);
    echo json_encode(array("success" => false, "message" => $message, "ai_available" => false));
    exit;
}

// ---- Auth: session only ----
$studentId = isset($_SESSION["student_id"]) ? (int)$_SESSION["student_id"] : 0;
if ($studentId <= 0) {
    sp_ai_error("Please sign in to use the AI assistant.", 401);
}

// ---- Input (JSON or form) ----
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) { $input = $_POST; }

$action = isset($input["action"]) ? trim((string)$input["action"]) : "chat";

// ---- Session chat history (server-side memory) ----
if (!isset($_SESSION["sp_ai_history"]) || !is_array($_SESSION["sp_ai_history"])) {
    $_SESSION["sp_ai_history"] = array();
}

if ($action === "reset") {
    $_SESSION["sp_ai_history"] = array();
    sp_ai_reply("Conversation cleared. How can I help you?", array(), true, array("reset" => true));
}

if ($action === "status") {
    require_once("../../app/config/ai.php");
    $name = isset($_SESSION["student_name"]) ? trim((string)$_SESSION["student_name"]) : "Student";
    if ($name === "") { $name = "Student"; }
    $parts = preg_split('/\s+/', $name);
    $first = !empty($parts[0]) ? $parts[0] : "Student";
    sp_ai_reply("", array(), is_ai_enabled(), array("name" => $first, "full_name" => $name));
}

// ---- Current page hint (safe identifier only, never a raw URL) ----
$allowed_pages = array("dashboard", "exams", "performance", "ai_feedback", "result", "profile", "edit_profile", "change_password", "report", "feedback");
$current_page = strtolower(trim((string)($input["current_page"] ?? "")));
if (!in_array($current_page, $allowed_pages, true)) {
    $current_page = "";
}

$message = trim(strip_tags((string)($input["message"] ?? "")));
$message = preg_replace('/\s+/', ' ', $message);
if ($message === "") {
    sp_ai_error("Please type a message first.");
}
if (mb_strlen($message) > 1000) {
    sp_ai_error("Please keep your message under 1000 characters.");
}

// ---- Rate limit: 20 requests / 60 seconds per session ----
$now = time();
if (!isset($_SESSION["sp_ai_hits"]) || !is_array($_SESSION["sp_ai_hits"])) {
    $_SESSION["sp_ai_hits"] = array();
}
$_SESSION["sp_ai_hits"] = array_values(array_filter($_SESSION["sp_ai_hits"], function ($t) use ($now) {
    return ($now - (int)$t) < 60;
}));
if (count($_SESSION["sp_ai_hits"]) >= 20) {
    sp_ai_error("AI service is temporarily busy. Please try again shortly.", 429);
}
$_SESSION["sp_ai_hits"][] = $now;

// ---- Build authorized context ----
require_once("../../app/ai/StudentAssistant.php");
$ctx = StudentContext::forSession($conn);
if (!$ctx) {
    sp_ai_error("We could not load your student profile. Please re-login and try again.");
}

// ---- Fast deterministic path (exact numbers, no LLM needed) ----
$local = StudentAssistant::localAnswer($message, $ctx);
if ($local !== null) {
    list($reply, $actions) = $local;
    $actions = StudentAssistant::sanitizeActions($actions);
    $_SESSION["sp_ai_history"][] = array("role" => "user", "message" => mb_substr($message, 0, 300));
    $_SESSION["sp_ai_history"][] = array("role" => "assistant", "message" => mb_substr($reply, 0, 300));
    $_SESSION["sp_ai_history"] = array_slice($_SESSION["sp_ai_history"], -12);
    sp_ai_reply($reply, $actions, true);
}

// ---- Groq path ----
require_once("../../app/config/ai.php");
if (!is_ai_enabled()) {
    sp_ai_reply(
        "I can answer questions about your exams, results and performance from your records, but the advanced AI service is temporarily unavailable. Please try basic questions like \"What is my average score?\" or check back shortly.",
        array(),
        false
    );
}

try {
    $configFile = __DIR__ . "/../../app/config/ai_config.json";
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : array();
    $model = $config["model"] ?? "llama-3.3-70b-versatile";

    $history = array_slice($_SESSION["sp_ai_history"], -12);
    $reply = StudentAssistant::askGroq(
        get_groq(),
        $model,
        StudentAssistant::systemPrompt($ctx["profile"]["name"] ?? "Student", $current_page),
        StudentContext::toPromptText($ctx),
        $history,
        mb_substr($message, 0, 800)
    );

    $_SESSION["sp_ai_history"][] = array("role" => "user", "message" => mb_substr($message, 0, 300));
    $_SESSION["sp_ai_history"][] = array("role" => "assistant", "message" => mb_substr($reply, 0, 300));
    $_SESSION["sp_ai_history"] = array_slice($_SESSION["sp_ai_history"], -12);

    sp_ai_reply($reply, StudentAssistant::suggestActions($message), true);
} catch (Exception $e) {
    error_log("Student AI assistant error: " . $e->getMessage());
    sp_ai_reply(
        "Sorry, I couldn't process that request right now. Please try again.",
        array(),
        false
    );
}

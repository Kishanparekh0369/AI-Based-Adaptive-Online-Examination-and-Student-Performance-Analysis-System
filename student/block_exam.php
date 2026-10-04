<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();

header("Content-Type: application/json");

$student_id = (int)$_SESSION["student_id"];
$exam_id = (int)($_POST["exam_id"] ?? 0);
$session_id = isset($_POST["session_id"]) ? (int)$_POST["session_id"] : null;
$reason = $_POST["reason"] ?? "Navigation exit";

if ($exam_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid exam"]);
    exit;
}

$blocked = block_exam_attempt($student_id, $exam_id, $reason, $session_id);
unset($_SESSION["active_exam_" . $exam_id]);
echo json_encode(["success" => $blocked || exam_blocked($student_id, $exam_id)]);
mysqli_close($conn);
?>

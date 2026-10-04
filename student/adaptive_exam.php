<?php
require_once("../app/config/database.php");
$conn = conn();
require_student();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$studentId = (int)$_SESSION["student_id"];
if (!isset($_GET["id"])) { redirect_alert("Invalid exam", "exams.php"); exit; }

$examId = (int)$_GET["id"];
$examResult = mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id = s.id WHERE e.id = $examId AND e.status = 'Active' AND e.exam_mode = 'Adaptive'");
if (!$examResult || mysqli_num_rows($examResult) == 0) { redirect_alert("Exam not available", "exams.php"); exit; }
$exam = mysqli_fetch_assoc($examResult);
// Academic eligibility re-check (server-side; never trust the listing page)
$scope_check = student_exam_eligible($studentId, (int)$exam["id"]);
if (!$scope_check["ok"]) {
    redirect_alert($scope_check["reason"] !== "" ? $scope_check["reason"] : "This examination is not available for your academic program or semester.", "exams.php", "warning");
    exit;
}

$check = mysqli_query($conn, "SELECT id, percentage, status FROM results WHERE student_id = $studentId AND exam_id = $examId");
if ($check && mysqli_num_rows($check) > 0) {
    $row = mysqli_fetch_assoc($check);
    redirect_alert("Exam already attempted! Score: " . number_format($row['percentage'], 1) . "%", "result.php");
    exit;
}
if (exam_blocked($studentId, $examId)) {
    redirect_alert("This exam attempt has been blocked", "exams.php", "warning");
    exit;
}
$active_key = "active_exam_" . $examId;
if (!empty($_SESSION[$active_key])) {
    block_exam_attempt($studentId, $examId, "Page refresh or reopen");
    unset($_SESSION[$active_key]);
    redirect_alert("This exam attempt has been blocked", "exams.php", "warning");
    exit;
}
$_SESSION[$active_key] = 1;

$duration_seconds = max((int)$exam["duration"], 1) * 60;
$student_name = isset($_SESSION["student_name"]) ? $_SESSION["student_name"] : "Student";
$exam_shell_mode = "adaptive";
$exam_shell_action = "";
$exam_shell_questions = [];
$exam_shell_total = max((int)($exam["max_questions"] ?? 10), 1);
$exam_shell_exam_id = $examId;
$exam_shell_student_id = $studentId;
$exam_shell_student_name = $student_name;
$exam_shell_title_suffix = "Exam Portal";
require("../app/layouts/exam_shell.php");
?>
<script>
window.adaptiveExamData = {
    examId: <?php echo $examId; ?>,
    studentId: <?php echo $studentId; ?>,
    duration: <?php echo $duration_seconds; ?>,
    maxQuestions: <?php echo $exam_shell_total; ?>,
    examTitle: "<?php echo h($exam["exam_title"]); ?>",
    subjectName: "<?php echo h($exam["subject_name"]); ?>"
};
window.history.pushState(null, null, window.location.href);
window.addEventListener('popstate', function() { window.location.href = 'exams.php'; });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/exam-engine.js"></script>
</body>
</html>

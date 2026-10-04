<?php
require_once("../app/config/database.php");
require_once("../app/config/ai.php");

$conn = conn();require_student();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    redirect_alert("Invalid request", "exams.php");
}

$student_id = $_SESSION["student_id"];
$exam_id = clean_data($_POST["exam_id"]);
$answers = isset($_POST["answer"]) ? $_POST["answer"] : array();
$time_spent_seconds = isset($_POST["time_spent_seconds"]) ? (int)$_POST["time_spent_seconds"] : 0;

$blocked = exam_blocked($student_id, $exam_id);
if ($blocked) {
    redirect_alert("This exam attempt has been blocked", "exams.php", "warning");
}

$check = mysqli_query($conn, "SELECT * FROM results WHERE student_id='$student_id' AND exam_id='$exam_id'");
if (mysqli_num_rows($check) > 0) {
    redirect_alert("You have already submitted this exam", "result.php");
}

$exam_result = mysqli_query($conn, "SELECT * FROM exams WHERE id='$exam_id'");
if (mysqli_num_rows($exam_result) == 0) {
    redirect_alert("Exam not found", "exams.php");
}
$exam = mysqli_fetch_assoc($exam_result);
// Academic eligibility re-check before accepting answers (defense in depth)
$scope_check = student_exam_eligible($student_id, (int)$exam["id"]);
if (!$scope_check["ok"]) {
    redirect_alert($scope_check["reason"] !== "" ? $scope_check["reason"] : "This examination is not available for your academic program or semester.", "exams.php", "warning");
}

$questions = mysqli_query($conn, "SELECT q.* FROM exam_questions eq LEFT JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id='$exam_id'");
$total_questions = mysqli_num_rows($questions);
$correct_answers = 0;
$wrong_answers = 0;
$marks_obtained = 0;
$difficulty_breakdown = [];
$answers_detail = [];

while ($row = mysqli_fetch_assoc($questions)) {
    $question_id = $row["id"];
    $selected_answer = isset($answers[$question_id]) ? clean_data($answers[$question_id]) : "";
    $correct_answer = $row["correct_answer"];
    $is_correct = 0;

    if ($selected_answer == $correct_answer) {
        $is_correct = 1;
        $correct_answers++;
        $marks_obtained += $row["marks"];
    } else if ($selected_answer != "") {
        $wrong_answers++;
    }

    $difficulty = $row["difficulty"] ?: "Medium";
    if (!isset($difficulty_breakdown[$difficulty])) {
        $difficulty_breakdown[$difficulty] = ["total" => 0, "correct" => 0];
    }
    $difficulty_breakdown[$difficulty]["total"]++;
    if ($is_correct) {
        $difficulty_breakdown[$difficulty]["correct"]++;
    }

    $answers_detail[] = [
        "question" => $row["question"],
        "option_a" => $row["option_a"],
        "option_b" => $row["option_b"],
        "option_c" => $row["option_c"],
        "option_d" => $row["option_d"],
        "student_answer" => $selected_answer,
        "correct_answer" => $correct_answer,
        "is_correct" => $is_correct,
        "difficulty" => $difficulty,
        "marks" => (int)$row["marks"],
        "topic" => trim((string)($row["keywords"] ?? "")),
    ];

    mysqli_query($conn, "INSERT INTO student_answers (student_id,exam_id,question_id,selected_answer,correct_answer,is_correct) VALUES ('$student_id','$exam_id','$question_id','$selected_answer','$correct_answer','$is_correct')");
}

$percentage = 0;
if ($exam["total_marks"] > 0) {
    $percentage = ($marks_obtained / $exam["total_marks"]) * 100;
}
$status = $marks_obtained >= $exam["pass_marks"] ? "Pass" : "Fail";
$skipped = max($total_questions - $correct_answers - $wrong_answers, 0);
$category = $percentage >= 80 ? "Advanced" : ($percentage >= 40 ? "Moderate" : "Weak");
$theta_score = round(($percentage - 50) / 25, 2);
$ai_insight = "";

if (is_ai_enabled()) {
    require_once("../app/ai/PerformanceAnalyzer.php");
    $student_name = $_SESSION["student_name"] ?? "Student";
    $exam_title = $exam["exam_title"] ?? "Exam";
    $subject_name = "";
    $subject_result = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id='" . (int)$exam["subject_id"] . "'");
    if ($subject_result && $subject_row = mysqli_fetch_assoc($subject_result)) {
        $subject_name = $subject_row["subject_name"];
    }

    $analyzer = new PerformanceAnalyzer();
    // Topic roll-up from question keywords (first keyword = topic; none invented).
    $submit_topics = array();
    foreach ($answers_detail as $adq) {
        $kw = trim((string)($adq["topic"] ?? ""));
        if ($kw === "") { continue; }
        $parts = explode(",", $kw);
        $tn = trim($parts[0]);
        if ($tn === "") { continue; }
        $key = mb_strtolower($tn);
        if (!isset($submit_topics[$key])) { $submit_topics[$key] = array("topic" => $tn, "attempted" => 0, "correct" => 0); }
        $submit_topics[$key]["attempted"]++;
        if ((int)($adq["is_correct"] ?? 0) === 1) { $submit_topics[$key]["correct"]++; }
    }
    $ai_insight = $analyzer->analyzeStudentPerformance(
        $student_name,
        $exam_title,
        round($percentage, 2),
        $correct_answers,
        $wrong_answers,
        $total_questions,
        $theta_score,
        $category,
        $time_spent_seconds,
        $subject_name,
        array(
            "obtained" => $marks_obtained,
            "total_marks" => (int)$exam["total_marks"],
            "pass_marks" => (int)$exam["pass_marks"],
            "status" => $status,
            "skipped" => $skipped,
            "difficulty" => $difficulty_breakdown,
            "topics" => array_values($submit_topics),
            "has_topics" => !empty($submit_topics),
            "timing_note" => "Per-question timing unavailable for standard exams — do not invent time-management observations.",
        )
    );
    $diff_insight = $analyzer->analyzeDifficultyBreakdown((int)$student_id, $conn, $difficulty_breakdown);
    if ($diff_insight) {
        $ai_insight .= "\n\nDifficulty Analysis:\n" . $diff_insight;
    }
}

$ai_insight_sql = mysqli_real_escape_string($conn, $ai_insight);
$category_sql = mysqli_real_escape_string($conn, $category);
$diff_json = mysqli_real_escape_string($conn, json_encode($difficulty_breakdown));
$answers_json = mysqli_real_escape_string($conn, json_encode($answers_detail));

mysqli_query($conn, "INSERT INTO results (student_id,exam_id,total_questions,correct_answers,wrong_answers,marks_obtained,percentage,status,time_spent_seconds,ai_insight,skill_category,theta_score,difficulty_breakdown,answers_detail) VALUES ('$student_id','$exam_id','$total_questions','$correct_answers','$wrong_answers','$marks_obtained','$percentage','$status','$time_spent_seconds','$ai_insight_sql','$category_sql','$theta_score','$diff_json','$answers_json')");
unset($_SESSION["active_exam_" . (int)$exam_id]);
notify_user("student", $student_id, "Exam Completed", "Your exam has been submitted and the result is ready.", "result.php");
notify_user("admin", null, "Exam Completed", $_SESSION["student_name"] . " submitted an exam.", "results.php");
header("Location: result.php?submitted=1&exam_id=" . urlencode($exam_id));
exit;

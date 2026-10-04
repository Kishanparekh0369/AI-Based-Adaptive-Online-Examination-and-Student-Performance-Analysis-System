<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = mysqli_connect('localhost', 'root', '', 'ai_adaptive_exam');
if (!$conn) { die("DB failed: " . mysqli_connect_error()); }

echo "=== Exam ID 5 Check ===\n";
$r = mysqli_query($conn, "SELECT * FROM exams WHERE id = 5");
if (!$r) { echo "Query error: " . mysqli_error($conn) . "\n"; }
$exam = mysqli_fetch_assoc($r);
if ($exam) {
    echo "Title: {$exam['exam_title']}\n";
    echo "Mode: {$exam['exam_mode']}\n";
    echo "Status: {$exam['status']}\n";
    echo "Subject ID: {$exam['subject_id']}\n";
    echo "Duration: {$exam['duration']}\n";
    echo "Total Marks: {$exam['total_marks']}\n";
} else {
    echo "EXAM ID 5 NOT FOUND!\n";
    echo "Available exams:\n";
    $r2 = mysqli_query($conn, "SELECT id, exam_title, exam_mode, status FROM exams");
    while ($row = mysqli_fetch_assoc($r2)) {
        echo "  ID {$row['id']}: {$row['exam_title']} (Mode: {$row['exam_mode']}, Status: {$row['status']})\n";
    }
}

echo "\n=== Student ID 1 Check ===\n";
$r = mysqli_query($conn, "SELECT id, name, email, status FROM students WHERE id = 1");
$s = mysqli_fetch_assoc($r);
if ($s) {
    echo "Name: {$s['name']}\n";
    echo "Email: {$s['email']}\n";
    echo "Status: {$s['status']}\n";
} else {
    echo "STUDENT ID 1 NOT FOUND!\n";
}

echo "\n=== Test adaptive_ajax start action ===\n";
// Simulate what adaptive_ajax.php does
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/config/session.php';
require_once __DIR__ . '/app/helpers/validation.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/config/ai.php';
require_once __DIR__ . '/app/adaptive/AbilityEstimator.php';
require_once __DIR__ . '/app/adaptive/QuestionSelector.php';
require_once __DIR__ . '/app/adaptive/AdaptiveSession.php';

// Check if questions exist for subject
$subjectId = 1;
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions WHERE subject_id = $subjectId");
$row = mysqli_fetch_assoc($r);
echo "Questions for subject $subjectId: {$row['c']}\n";

$r = mysqli_query($conn, "SELECT difficulty, COUNT(*) AS c FROM questions WHERE subject_id = $subjectId GROUP BY difficulty");
while ($row = mysqli_fetch_assoc($r)) {
    echo "  {$row['difficulty']}: {$row['c']}\n";
}

// Test session creation
$_SESSION["student_id"] = 1;
$sessionMgr = new AdaptiveSession($conn);
$session = $sessionMgr->start(1, 1);
echo "\nSession created: ID={$session['id']}, theta={$session['theta_value']}, diff={$session['current_difficulty']}\n";

// Test getBankStatus
$sel = new QuestionSelector($conn);
$bankStatus = $sel->getBankStatus(1, [], 0);
echo "\nBank status: total={$bankStatus['total']}, needs_ai={$bankStatus['needs_ai']}, needed_diff={$bankStatus['needed_difficulty']}\n";

// Test shouldEnd with proper max
$endCheck = $sessionMgr->shouldEnd($session['id'], 20, $bankStatus['total']);
echo "shouldEnd: end=" . ($endCheck['end'] ? 'true' : 'false') . ", reason={$endCheck['reason']}\n";

// Try picking a question
$q = $sel->pickNext(1, 'Medium', []);
if ($q) {
    echo "\nPicked question: ID={$q['id']}, diff={$q['difficulty']}\n";
    echo "Q: " . substr($q['question'], 0, 80) . "...\n";
} else {
    echo "\nNO QUESTION PICKED!\n";
}

// Test AJAX URLs
echo "\n=== Verify AJAX URL ===\n";
$baseUrl = "http://localhost/AIproject/AIProject/student/adaptive_ajax.php";
echo "Expected AJAX URL: $baseUrl\n";
echo "File exists: " . (file_exists(__DIR__ . '/student/adaptive_ajax.php') ? 'YES' : 'NO') . "\n";

mysqli_close($conn);

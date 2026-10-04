<?php
require_once("../app/config/database.php");
require_once("../app/config/ai.php");

$conn = conn();
require_student();

header('Content-Type: application/json');

$studentId = (int)$_SESSION["student_id"];
$action = $_POST["action"] ?? '';
$examId = (int)($_POST["exam_id"] ?? 0);

if (!$examId || !$action) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if (exam_blocked($studentId, $examId)) {
    echo json_encode(['error' => 'This exam attempt has been blocked']);
    exit;
}

// Academic eligibility re-check (server-side; never trust the client)
$scope_check = student_exam_eligible($studentId, $examId);
if (!$scope_check["ok"]) {
    echo json_encode(['error' => $scope_check["reason"] !== "" ? $scope_check["reason"] : 'This examination is not available for your academic program or semester.']);
    exit;
}

if ($action !== 'finish' && exam_completed($studentId, $examId)) {
    echo json_encode(['error' => 'This exam has already been completed']);
    exit;
}

require_once("../app/adaptive/AbilityEstimator.php");
require_once("../app/adaptive/QuestionSelector.php");
require_once("../app/adaptive/AdaptiveSession.php");

$selector = new QuestionSelector($conn);
$estimator = new AbilityEstimator();
$sessionMgr = new AdaptiveSession($conn);

switch ($action) {

    case 'start':
        $session = $sessionMgr->start($studentId, $examId);
        $subjectId = $selector->getSubjectIdForExam($examId);
        $askedIds = $sessionMgr->getAskedQuestionIds($session['id']);
        $savedCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM exam_questions WHERE exam_id = $examId"));
        if ((int)($savedCountRow['total'] ?? 0) <= 0) {
            echo json_encode(['error' => 'No saved AI questions are assigned to this adaptive exam. Ask the admin to regenerate the exam questions.']);
            exit;
        }

        echo json_encode([
            'session_id' => (int)$session['id'],
            'theta' => (float)$session['theta_value'],
            'difficulty' => $session['current_difficulty'] ?: 'Medium',
            'asked_ids' => $askedIds,
            'subject_id' => $subjectId,
        ]);
        break;

    case 'get_question':
        $sessionId = (int)($_POST["session_id"] ?? 0);
        $theta = (float)($_POST["theta"] ?? 0);
        $difficulty = $_POST["difficulty"] ?? 'Medium';
        $askedIds = json_decode($_POST["asked_ids"] ?? '[]', true) ?: [];

        if ($sessionId) {
            $askedIds = $sessionMgr->getAskedQuestionIds($sessionId);
        }

        $subjectId = $selector->getSubjectIdForExam($examId);

        if (!$subjectId) {
            echo json_encode(['error' => 'Subject not found']);
            exit;
        }

        $bankStatus = $selector->getExamBankStatus($examId, $askedIds, $theta);

        // Get exam's max_questions setting
        $maxQrow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max_questions FROM exams WHERE id = $examId"));
        $maxQuestions = max(1, (int)($maxQrow['max_questions'] ?? 10));

        if ($sessionId) {
            $endCheck = $sessionMgr->shouldEnd($sessionId, $maxQuestions, $bankStatus['total'] + count($askedIds));
            if ($endCheck['end']) {
                echo json_encode(['exam_ended' => true, 'reason' => $endCheck['reason']]);
                exit;
            }
        }

        $question = null;
        $diffToPick = $bankStatus['needed_difficulty'];
        if ($diffToPick === 'ANY' || !in_array($diffToPick, ['Easy', 'Medium', 'Hard'])) {
            $diffToPick = 'Medium';
        }

        $q = $selector->pickNextForExam($examId, $diffToPick, $askedIds);
        if (!$q) {
            foreach (['Easy', 'Medium', 'Hard'] as $d) {
                $q = $selector->pickNextForExam($examId, $d, $askedIds);
                if ($q) break;
            }
        }
        if ($q) {
            $q['type'] = 'saved_ai';
            $question = $q;
        }

        if (!$question) {
            echo json_encode(['exam_ended' => true, 'reason' => 'Question bank exhausted']);
            exit;
        }

        // Don't send correct_answer to client
        $responseQuestion = [
            'id' => (int)$question['id'],
            'question' => $question['question'],
            'option_a' => $question['option_a'],
            'option_b' => $question['option_b'],
            'option_c' => $question['option_c'],
            'option_d' => $question['option_d'],
            'difficulty' => $question['difficulty'],
            'marks' => (int)($question['marks'] ?? 1),
            'language' => $question['language'] ?? 'English',
            'type' => $question['type'] ?? 'from_bank',
        ];

        echo json_encode($responseQuestion);
        break;

    case 'submit_answer':
        $sessionId = (int)($_POST["session_id"] ?? 0);
        $questionId = (int)($_POST["question_id"] ?? 0);
        $answer = $_POST["answer"] ?? '';
        $theta = (float)($_POST["theta"] ?? 0);
        $difficulty = $_POST["difficulty"] ?? 'Medium';
        $timeTaken = (int)($_POST["time_taken"] ?? 0);

        if (!$sessionId || !$questionId) {
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }

        // Get correct answer
        $qResult = mysqli_query($conn, "SELECT correct_answer, difficulty, marks FROM questions WHERE id = $questionId");
        $qData = mysqli_fetch_assoc($qResult);

        if (!$qData) {
            echo json_encode(['error' => 'Question not found']);
            exit;
        }

        $correctAnswer = $qData['correct_answer'];
        $wasCorrect = ($answer === $correctAnswer);

        // Update theta
        $estimator->loadState($theta, null);
        $newTheta = $estimator->update($qData['difficulty'], $wasCorrect);

        $sessionMgr->saveAnswer($sessionId, $questionId, $answer, $wasCorrect ? 1 : 0, $qData['difficulty'], $timeTaken);
        $askedIds = $sessionMgr->getAskedQuestionIds($sessionId);
        $subjectId = $selector->getSubjectIdForExam($examId);
        $bankStatus = $selector->getExamBankStatus($examId, $askedIds, $newTheta);
        $nextDifficulty = $estimator->transitionDifficulty($qData['difficulty'], $wasCorrect, $bankStatus['available']);
        $sessionMgr->update($sessionId, $newTheta, $nextDifficulty, $wasCorrect);

        // Check if exam should end after this answer
        $maxQrow2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT max_questions FROM exams WHERE id = $examId"));
        $maxQuestions2 = max(1, (int)($maxQrow2['max_questions'] ?? 10));
        $totalAvailable2 = $bankStatus['total'] + count($askedIds);
        $endCheck = $sessionMgr->shouldEnd($sessionId, $maxQuestions2, $totalAvailable2);

        $response = [
            'was_correct' => $wasCorrect,
            'theta' => $newTheta,
            'next_difficulty' => $nextDifficulty,
            'asked_ids' => $askedIds,
            'correct_answer' => $correctAnswer,
        ];

        if ($endCheck['end']) {
            $response['exam_ended'] = true;
            $response['end_reason'] = $endCheck['reason'];
        } else {
            // Get next question
            $bankStatus2 = $selector->getExamBankStatus($examId, $askedIds, $newTheta);
            $nextQ = null;

            $diffToPick = $nextDifficulty;
            if ($diffToPick === 'ANY' || !in_array($diffToPick, ['Easy', 'Medium', 'Hard'])) {
                $diffToPick = $bankStatus2['needed_difficulty'];
            }
            if ($diffToPick === 'ANY' || !in_array($diffToPick, ['Easy', 'Medium', 'Hard'])) {
                $diffToPick = 'Medium';
            }
            $nextQ = $selector->pickNextForExam($examId, $diffToPick, $askedIds);
            if (!$nextQ) {
                foreach (['Easy', 'Medium', 'Hard'] as $d) {
                    $nextQ = $selector->pickNextForExam($examId, $d, $askedIds);
                    if ($nextQ) {
                        break;
                    }
                }
            }
            if ($nextQ) $nextQ['type'] = 'saved_ai';

            if ($nextQ) {
                $response['next_question'] = [
                    'id' => (int)$nextQ['id'],
                    'question' => $nextQ['question'],
                    'option_a' => $nextQ['option_a'],
                    'option_b' => $nextQ['option_b'],
                    'option_c' => $nextQ['option_c'],
                    'option_d' => $nextQ['option_d'],
                    'difficulty' => $nextQ['difficulty'],
                    'marks' => (int)($nextQ['marks'] ?? 1),
                    'language' => $nextQ['language'] ?? 'English',
                    'type' => $nextQ['type'] ?? 'from_bank',
                ];
            } else {
                $response['exam_ended'] = true;
                $response['end_reason'] = 'No more questions';
            }
        }

        echo json_encode($response);
        break;

    case 'abandon':
        $sessionId = (int)($_POST["session_id"] ?? 0);
        if (!$sessionId) {
            echo json_encode(['error' => 'No session']);
            exit;
        }

        block_exam_attempt($studentId, $examId, "Navigation exit", $sessionId);
        unset($_SESSION["active_exam_" . $examId]);
        echo json_encode(['success' => true, 'blocked' => true]);
        break;

    case 'finish':
        $sessionId = (int)($_POST["session_id"] ?? 0);
        $theta = (float)($_POST["theta"] ?? 0);

        if (!$sessionId) {
            echo json_encode(['error' => 'Session not found']);
            exit;
        }

        if (exam_completed($studentId, $examId)) {
            unset($_SESSION["active_exam_" . $examId]);
            echo json_encode(['success' => true, 'already_completed' => true]);
            exit;
        }

        // Complete session
        $sessionMgr->complete($sessionId);

        // Get result data
        $result = $sessionMgr->getResult($sessionId, $examId, $studentId);

        // Save to results table
        $totalQuestions = (int)$result['total_questions'];
        $correctAnswers = (int)$result['correct_answers'];
        $wrongAnswers = (int)$result['wrong_answers'];
        $marksObtained = (int)$result['marks_obtained'];
        $percentage = (float)$result['percentage'];
        $status = $result['status'];
        $thetaScore = (float)$result['theta_score'];
        $skillCategory = $result['skill_category'];
        $timeSpent = (int)$result['time_spent_seconds'];

        // Difficulty breakdown for AI
        $diffResult = mysqli_query($conn, "SELECT q.difficulty,
            COUNT(aa.id) AS total, SUM(aa.is_correct) AS correct_count
            FROM adaptive_answers aa
            LEFT JOIN questions q ON aa.question_id = q.id
            WHERE aa.session_id = $sessionId
            GROUP BY q.difficulty");

        $correctByDifficulty = [];
        while ($row = mysqli_fetch_assoc($diffResult)) {
            $correctByDifficulty[$row['difficulty']] = [
                'total' => (int)$row['total'],
                'correct' => (int)$row['correct_count'],
            ];
        }

        // Generate AI insight
        $aiInsight = '';
        if (is_ai_enabled()) {
            require_once("../app/ai/PerformanceAnalyzer.php");
            $studentName = $_SESSION["student_name"] ?? 'Student';
            $examTitle = mysqli_fetch_assoc(mysqli_query($conn, "SELECT exam_title FROM exams WHERE id = $examId"))['exam_title'] ?? 'Exam';
            $subjectName = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id = s.id WHERE e.id = $examId"))['subject_name'] ?? '';

            $analyzer = new PerformanceAnalyzer();
            $adapSkipped = max($totalQuestions - $correctAnswers - $wrongAnswers, 0);
            $aiInsight = $analyzer->analyzeStudentPerformance(
                $studentName,
                $examTitle,
                $percentage,
                $correctAnswers,
                $wrongAnswers,
                $totalQuestions,
                $thetaScore,
                $skillCategory,
                $timeSpent,
                $subjectName,
                array(
                    "status" => $status,
                    "skipped" => $adapSkipped,
                    "difficulty" => $correctByDifficulty,
                    "has_topics" => false,
                    "timing_note" => "Per-question timing was recorded for this adaptive attempt and averaged into the total time.",
                )
            );

            $diffInsight = $analyzer->analyzeDifficultyBreakdown($studentId, $conn, $correctByDifficulty);
            if ($diffInsight) {
                $aiInsight .= "\n\n📊 Difficulty Analysis:\n" . $diffInsight;
            }
        }

        // Get detailed answers for review
        $answersDetail = [];
        $ansQ = mysqli_query($conn, "SELECT aa.*, q.correct_answer, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.marks, q.difficulty, q.keywords FROM adaptive_answers aa LEFT JOIN questions q ON aa.question_id = q.id WHERE aa.session_id = $sessionId ORDER BY aa.id");
        while ($row = mysqli_fetch_assoc($ansQ)) {
            $answersDetail[] = [
                'question' => $row['question'],
                'option_a' => $row['option_a'],
                'option_b' => $row['option_b'],
                'option_c' => $row['option_c'],
                'option_d' => $row['option_d'],
                'student_answer' => $row['student_answer'],
                'correct_answer' => $row['correct_answer'],
                'is_correct' => (int)$row['is_correct'],
                'difficulty' => $row['difficulty'],
                'marks' => (int)$row['marks'],
                'topic' => trim((string)($row['keywords'] ?? '')),
                'time_seconds' => max((int)($row['time_taken_seconds'] ?? 0), 0),
            ];
        }
        $answersJson = mysqli_real_escape_string($conn, json_encode($answersDetail));

        // Save result
        $checkResult = mysqli_query($conn, "SELECT id FROM results WHERE student_id = $studentId AND exam_id = $examId");
        if (mysqli_num_rows($checkResult) == 0) {
            $escapedInsight = mysqli_real_escape_string($conn, $aiInsight);
            $diffJson = mysqli_real_escape_string($conn, json_encode($correctByDifficulty));

            mysqli_query($conn, "INSERT INTO results
                (student_id, exam_id, session_id, total_questions, correct_answers, wrong_answers,
                 marks_obtained, percentage, status, time_spent_seconds, ai_insight,
                 skill_category, theta_score, difficulty_breakdown, answers_detail)
                VALUES ($studentId, $examId, $sessionId, $totalQuestions, $correctAnswers, $wrongAnswers,
                        $marksObtained, $percentage, '$status', $timeSpent, '$escapedInsight',
                        '$skillCategory', $thetaScore, '$diffJson', '$answersJson')");

            notify_user("student", $studentId, "Adaptive Exam Completed", "Your adaptive exam result is ready.", "result.php");
            notify_user("admin", null, "Adaptive Exam Completed", $_SESSION["student_name"] . " completed an adaptive exam.", "results.php");
        }

        unset($_SESSION["active_exam_" . $examId]);

        echo json_encode([
            'success' => true,
            'percentage' => $percentage,
            'status' => $status,
            'theta' => $thetaScore,
            'category' => $skillCategory,
        ]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}

mysqli_close($conn);

<?php
class AdaptiveSession {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function start($studentId, $examId) {
        $studentId = (int)$studentId;
        $examId = (int)$examId;

        $existing = mysqli_query($this->conn, "SELECT * FROM adaptive_sessions
            WHERE student_id = $studentId AND exam_id = $examId AND is_completed = 0");
        if ($row = mysqli_fetch_assoc($existing)) {
            return $row;
        }

        mysqli_query($this->conn, "INSERT INTO adaptive_sessions
            (student_id, exam_id, theta_value, current_difficulty, questions_asked, correct_count, wrong_count)
            VALUES ($studentId, $examId, 0.00, 'Medium', 0, 0, 0)");

        return mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM adaptive_sessions WHERE id = " . mysqli_insert_id($this->conn)));
    }

    public function update($sessionId, $theta, $difficulty, $wasCorrect) {
        $sessionId = (int)$sessionId;
        $theta = (float)$theta;
        $difficulty = mysqli_real_escape_string($this->conn, $difficulty);

        $incCorrect = $wasCorrect ? "correct_count = correct_count + 1" : "";
        $incWrong = !$wasCorrect ? "wrong_count = wrong_count + 1" : "";

        $setClauses = [
            "theta_value = $theta",
            "current_difficulty = '$difficulty'",
            "questions_asked = questions_asked + 1",
        ];
        if ($wasCorrect) $setClauses[] = "correct_count = correct_count + 1";
        else $setClauses[] = "wrong_count = wrong_count + 1";

        $sql = "UPDATE adaptive_sessions SET " . implode(", ", $setClauses) . " WHERE id = $sessionId";
        mysqli_query($this->conn, $sql);
    }

    public function saveAnswer($sessionId, $questionId, $selectedAnswer, $isCorrect, $difficulty, $timeTaken) {
        $sessionId = (int)$sessionId;
        $questionId = (int)$questionId;
        $selectedAnswer = mysqli_real_escape_string($this->conn, $selectedAnswer);
        $difficulty = mysqli_real_escape_string($this->conn, $difficulty);
        $timeTaken = (int)$timeTaken;

        mysqli_query($this->conn, "INSERT INTO adaptive_answers
            (session_id, question_id, student_answer, is_correct, time_taken_seconds, difficulty_at_time)
            VALUES ($sessionId, $questionId, '$selectedAnswer', $isCorrect, $timeTaken, '$difficulty')");
    }

    public function getAskedQuestionIds($sessionId) {
        $sessionId = (int)$sessionId;
        $result = mysqli_query($this->conn, "SELECT question_id FROM adaptive_answers WHERE session_id = $sessionId");
        $ids = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $ids[] = (int)$row['question_id'];
        }
        return $ids;
    }

    public function complete($sessionId) {
        $sessionId = (int)$sessionId;
        mysqli_query($this->conn, "UPDATE adaptive_sessions SET is_completed = 1 WHERE id = $sessionId");
    }

    public function shouldEnd($sessionId, $maxQuestions, $totalAvailable) {
        $sessionId = (int)$sessionId;
        $result = mysqli_query($this->conn, "SELECT * FROM adaptive_sessions WHERE id = $sessionId");
        $session = mysqli_fetch_assoc($result);

        if (!$session) return ['end' => true, 'reason' => 'Session not found'];

        $asked = (int)$session['questions_asked'];
        $theta = (float)$session['theta_value'];

        if ($asked >= $maxQuestions) {
            return ['end' => true, 'reason' => 'Max questions reached'];
        }

        if ($totalAvailable <= 0 && $asked > 0) {
            return ['end' => true, 'reason' => 'Question bank exhausted'];
        }
        if ($asked >= 50) {
            return ['end' => true, 'reason' => 'Max 50 questions reached'];
        }

        return ['end' => false, 'reason' => ''];
    }

    public function getResult($sessionId, $examId, $studentId) {
        $sessionId = (int)$sessionId;
        $examId = (int)$examId;
        $studentId = (int)$studentId;

        $session = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM adaptive_sessions WHERE id = $sessionId"));
        $exam = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM exams WHERE id = $examId"));

        $answersResult = mysqli_query($this->conn, "SELECT aa.*, q.marks, q.difficulty
            FROM adaptive_answers aa
            LEFT JOIN questions q ON aa.question_id = q.id
            WHERE aa.session_id = $sessionId");

        $totalQuestions = 0;
        $correctAnswers = 0;
        $wrongAnswers = 0;
        $marksObtained = 0;
        $timeSpent = 0;

        while ($row = mysqli_fetch_assoc($answersResult)) {
            $totalQuestions++;
            $timeSpent += (int)$row['time_taken_seconds'];
            if ((int)$row['is_correct'] === 1) {
                $correctAnswers++;
                $marksObtained += (int)$row['marks'];
            } else {
                $wrongAnswers++;
            }
        }

        $totalMarks = max((int)$exam['total_marks'], $totalQuestions);
        $percentage = $totalMarks > 0 ? round(($marksObtained / $totalMarks) * 100, 2) : 0;
        $passMarks = (int)($exam['pass_marks'] ?? 0);
        $status = $passMarks > 0
            ? ($marksObtained >= $passMarks ? 'Pass' : 'Fail')
            : ($percentage >= 40 ? 'Pass' : 'Fail');
        $theta = (float)$session['theta_value'];

        $estimator = new AbilityEstimator();
        $estimator->loadState($theta, null);
        $category = $estimator->getCategory();

        return [
            'session' => $session,
            'exam' => $exam,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'wrong_answers' => $wrongAnswers,
            'marks_obtained' => $marksObtained,
            'percentage' => $percentage,
            'status' => $status,
            'theta_score' => $theta,
            'skill_category' => $category,
            'time_spent_seconds' => $timeSpent,
        ];
    }
}

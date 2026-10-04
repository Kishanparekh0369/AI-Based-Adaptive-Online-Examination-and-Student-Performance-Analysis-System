<?php
class QuestionSelector {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getBankStatus($subjectId, $excludeIds, $theta) {
        $excludeStr = count($excludeIds) > 0
            ? "AND id NOT IN (" . implode(',', array_map('intval', $excludeIds)) . ")"
            : '';

        $result = mysqli_query($this->conn, "SELECT difficulty, COUNT(*) AS cnt FROM questions
            WHERE subject_id = " . (int)$subjectId . " $excludeStr GROUP BY difficulty");

        $counts = ['Easy' => 0, 'Medium' => 0, 'Hard' => 0, 'total' => 0];
        while ($row = mysqli_fetch_assoc($result)) {
            $d = $row['difficulty'];
            if (isset($counts[$d])) {
                $counts[$d] = (int)$row['cnt'];
                $counts['total'] += (int)$row['cnt'];
            }
        }

        $estimator = new AbilityEstimator();
        $estimator->loadState($theta, null);
        $neededDiff = $estimator->getNextDifficulty($counts);

        return [
            'available' => $counts,
            'total' => $counts['total'],
            'needs_ai' => $counts['total'] <= 0 || (isset($counts[$neededDiff]) && $counts[$neededDiff] <= 0 && $counts['total'] <= 2),
            'needed_difficulty' => $neededDiff,
            'subject_id' => $subjectId,
        ];
    }

    public function getExamBankStatus($examId, $excludeIds, $theta) {
        $excludeStr = count($excludeIds) > 0
            ? "AND q.id NOT IN (" . implode(',', array_map('intval', $excludeIds)) . ")"
            : '';

        $result = mysqli_query($this->conn, "SELECT q.difficulty, COUNT(*) AS cnt
            FROM exam_questions eq
            INNER JOIN questions q ON q.id = eq.question_id
            WHERE eq.exam_id = " . (int)$examId . " $excludeStr
            GROUP BY q.difficulty");

        $counts = ['Easy' => 0, 'Medium' => 0, 'Hard' => 0, 'total' => 0];
        while ($row = mysqli_fetch_assoc($result)) {
            $d = $row['difficulty'];
            if (isset($counts[$d])) {
                $counts[$d] = (int)$row['cnt'];
                $counts['total'] += (int)$row['cnt'];
            }
        }

        $estimator = new AbilityEstimator();
        $estimator->loadState($theta, null);
        $neededDiff = $estimator->getNextDifficulty($counts);

        return [
            'available' => $counts,
            'total' => $counts['total'],
            'needs_ai' => false,
            'needed_difficulty' => $neededDiff,
            'exam_id' => $examId,
        ];
    }

    public function pickNext($subjectId, $difficulty, $excludeIds = []) {
        $excludeStr = count($excludeIds) > 0
            ? "AND id NOT IN (" . implode(',', array_map('intval', $excludeIds)) . ")"
            : '';

        $difficultyClause = $difficulty === 'ANY'
            ? ""
            : "AND difficulty = '" . mysqli_real_escape_string($this->conn, $difficulty) . "'";

        $query = "SELECT id, question, option_a, option_b, option_c, option_d,
                         correct_answer, difficulty, marks, language
                  FROM questions
                  WHERE subject_id = " . (int)$subjectId . "
                    $difficultyClause
                    $excludeStr
                  ORDER BY RAND()
                  LIMIT 1";

        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_assoc($result);
    }

    public function pickNextForExam($examId, $difficulty, $excludeIds = []) {
        $excludeStr = count($excludeIds) > 0
            ? "AND q.id NOT IN (" . implode(',', array_map('intval', $excludeIds)) . ")"
            : '';

        $difficultyClause = $difficulty === 'ANY'
            ? ""
            : "AND q.difficulty = '" . mysqli_real_escape_string($this->conn, $difficulty) . "'";

        $query = "SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
                         q.correct_answer, q.difficulty, q.marks, q.language
                  FROM exam_questions eq
                  INNER JOIN questions q ON q.id = eq.question_id
                  WHERE eq.exam_id = " . (int)$examId . "
                    $difficultyClause
                    $excludeStr
                  ORDER BY eq.id ASC
                  LIMIT 1";

        $result = mysqli_query($this->conn, $query);
        return $result ? mysqli_fetch_assoc($result) : null;
    }

    public function getSubjectIdForExam($examId) {
        $result = mysqli_query($this->conn, "SELECT subject_id FROM exams WHERE id = " . (int)$examId);
        $row = mysqli_fetch_assoc($result);
        return $row ? (int)$row['subject_id'] : null;
    }
}

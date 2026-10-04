<?php
require_once __DIR__ . '/../config/ai.php';

class PerformanceAnalyzer {
    private $groq;
    private $model;

    public function __construct() {
        $this->groq = get_groq();
        $configFile = __DIR__ . '/../config/ai_config.json';
        $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
        $this->model = $config['model'] ?? 'llama-3.3-70b-versatile';
    }

    /* Richer analysis entry point. $extra may carry real attempt metrics:
     * obtained, total_marks, pass_marks, status, skipped, difficulty (array),
     * topics (array), has_topics (bool), timing_note (string). All optional
     * and backward compatible. Returns "" when AI is unavailable — callers
     * must show computed metrics instead of inventing text. */
    public function analyzeStudentPerformance($studentName, $examTitle, $percentage, $correct, $wrong, $totalQuestions, $theta, $category, $timeSpentSeconds, $subjectName, $extra = array()) {
        if (!$this->groq) {
            return "";
        }

        $obtained = isset($extra["obtained"]) ? (int)$extra["obtained"] : null;
        $totalMarks = isset($extra["total_marks"]) ? (int)$extra["total_marks"] : null;
        $passMarks = isset($extra["pass_marks"]) ? (int)$extra["pass_marks"] : null;
        $status = isset($extra["status"]) && $extra["status"] !== "" ? strtolower($extra["status"]) : ($percentage >= 40 ? 'passed' : 'failed');
        $skipped = isset($extra["skipped"]) ? max((int)$extra["skipped"], 0) : max($totalQuestions - $correct - $wrong, 0);
        $timeFormatted = gmdate("H:i:s", max((int)$timeSpentSeconds, 0));

        $scoreLine = "Score: $percentage% ($status)";
        if ($obtained !== null && $totalMarks !== null) {
            $scoreLine = "Score: $obtained/$totalMarks ($percentage%, $status)";
        }
        $contextLines = array(
            "Student: $studentName",
            "Exam: $examTitle",
            "Subject: " . ($subjectName !== "" ? $subjectName : "not specified"),
            $scoreLine,
        );
        if ($passMarks !== null) { $contextLines[] = "Passing Marks: $passMarks"; }
        $contextLines[] = "Category: $category";
        $contextLines[] = "Correct: $correct/$totalQuestions";
        $contextLines[] = "Wrong: $wrong";
        $contextLines[] = "Skipped: $skipped";
        $contextLines[] = "Time: $timeFormatted";
        $contextLines[] = "Ability Score (theta): $theta";
        if (!empty($extra["difficulty"]) && is_array($extra["difficulty"])) {
            $parts = array();
            foreach ($extra["difficulty"] as $level => $v) {
                $t = (int)($v["total"] ?? $v["attempted"] ?? 0);
                $c = (int)($v["correct"] ?? 0);
                if ($t > 0) { $parts[] = "$level $c/$t"; }
            }
            if (!empty($parts)) { $contextLines[] = "Difficulty (correct/attempted): " . implode(", ", $parts); }
        }
        if (!empty($extra["topics"]) && is_array($extra["topics"])) {
            $parts = array();
            foreach (array_slice($extra["topics"], 0, 8) as $t) {
                if (is_array($t) && !empty($t["topic"])) {
                    $parts[] = $t["topic"] . " " . ($t["correct"] ?? 0) . "/" . ($t["attempted"] ?? 0);
                }
            }
            if (!empty($parts)) { $contextLines[] = "Topics (correct/attempted): " . implode(", ", $parts); }
        } elseif (array_key_exists("has_topics", $extra) && !$extra["has_topics"]) {
            $contextLines[] = "Topics: unavailable — do not invent topic names.";
        }
        if (!empty($extra["timing_note"])) { $contextLines[] = $extra["timing_note"]; }
        if (!empty($extra["previous"])) { $contextLines[] = "Previous attempt: " . $extra["previous"]; }

        $prompt = "You are an educational performance analyst. Analyze ONLY the provided student performance data. "
            . "Do not invent scores, topics, difficulty levels, answers, timing, or weaknesses. "
            . "If information is unavailable, state that it is unavailable. "
            . "Give concise, evidence-based, personalized academic guidance using the actual metrics below. "
            . "Address the student by first name occasionally and naturally; do not start every response the same way. "
            . "Avoid generic motivational filler unless tied to a specific data-backed action.\n\n"
            . implode("\n", $contextLines) . "\n\nReturn ONLY valid JSON with these keys:\n"
            . "{\n"
            . "    \"strength_statement\": \"1 sentence about what the student did well, citing real numbers\",\n"
            . "    \"weakness_statement\": \"1 sentence about the evidence-backed area for improvement\",\n"
            . "    \"recommendation\": \"1 specific actionable recommendation\",\n"
            . "    \"encouragement\": \"1 short message tied to actual performance\"\n"
            . "}";

        try {
            $response = $this->groq->chat()->completions()->create([
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 600,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $analysis = json_decode($content, true);

            if (!$analysis || !isset($analysis['strength_statement'])) {
                return "";
            }

            return $analysis['strength_statement'] . "\n" .
                   $analysis['weakness_statement'] . "\n" .
                   $analysis['recommendation'] . "\n" .
                   "💪 " . $analysis['encouragement'];

        } catch (Exception $e) {
            error_log('AI performance analysis failed: ' . $e->getMessage());
            return "";
        }
    }

    public function analyzeDifficultyBreakdown($studentId, $conn, $overrideBreakdown = null) {
        if (!$this->groq) return '';

        if ($overrideBreakdown !== null) {
            $breakdown = [];
            foreach ($overrideBreakdown as $diff => $data) {
                $total = (int)$data['total'];
                $correct = (int)($data['correct'] ?? $data['correct_count'] ?? 0);
                $accuracy = $total > 0 ? round(($correct / $total) * 100) : 0;
                $breakdown[] = "$diff: $correct/$total ($accuracy%)";
            }
        } else {
            $result = mysqli_query($conn, "SELECT q.difficulty,
                COUNT(sa.id) AS total,
                SUM(sa.is_correct) AS correct_count
                FROM student_answers sa
                LEFT JOIN questions q ON sa.question_id = q.id
                WHERE sa.student_id = " . (int)$studentId . "
                GROUP BY q.difficulty");

            $breakdown = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $total = (int)$row['total'];
                $correct = (int)$row['correct_count'];
                $accuracy = $total > 0 ? round(($correct / $total) * 100) : 0;
                $breakdown[] = $row['difficulty'] . ": $correct/$total ($accuracy%)";
            }
        }

        if (empty($breakdown)) return '';

        $prompt = "Based on this student's performance by difficulty level:\n" . implode("\n", $breakdown) . "\nGive a 2-sentence analysis of their strengths and weaknesses. Be specific.";

        try {
            $response = $this->groq->chat()->completions()->create([
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens' => 600,
            ]);
            return trim((string)($response['choices'][0]['message']['content'] ?? ''));
        } catch (Exception $e) {
            error_log('AI difficulty analysis failed: ' . $e->getMessage());
            return '';
        }
    }

    /* Legacy static fallbacks removed. When AI is unavailable this returns an
     * empty string so callers display real computed metrics instead of
     * inventing motivational text. Kept for backward compatibility. */
    private function defaultInsight($percentage, $category) {
        return "";
    }
}

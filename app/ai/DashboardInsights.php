<?php
require_once __DIR__ . '/../config/ai.php';

class DashboardInsights {
    private $groq;
    private $model;

    public function __construct() {
        $this->groq = get_groq();
        $configFile = __DIR__ . '/../config/ai_config.json';
        $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
        $this->model = $config['model'] ?? 'llama-3.3-70b-versatile';
    }

    public function generateAdminInsights($conn) {
        if (!$this->groq) {
            return $this->defaultInsights($conn);
        }

        $stats = $this->getStats($conn);
        if (!$stats) return $this->defaultInsights($conn);

        $prompt = "You are an AI education analytics assistant. Analyze this exam system data and provide insights:

        - Total Students: {$stats['total_students']}
        - Total Exams: {$stats['total_exams']}
        - Total Results: {$stats['total_results']}
        - Average Score: {$stats['avg_score']}%
        - Pass Rate: {$stats['pass_rate']}%
        - Weak Students (below 40%): {$stats['weak_count']}
        - Moderate Students (40-70%): {$stats['moderate_count']}
        - Advanced Students (above 70%): {$stats['advanced_count']}

        Return ONLY valid JSON with these keys:
        {
            \"summary\": \"1 sentence overall summary\",
            \"risk_alert\": \"if weak students > 30% of total, give alert otherwise empty string\",
            \"recommendation\": \"1 actionable recommendation for the admin\",
            \"trend\": \"1 sentence about observed trends\"
        }";

        try {
            $response = $this->groq->chat()->completions()->create([
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 400,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $insights = json_decode($content, true);

            if ($insights && isset($insights['summary'])) {
                return $insights;
            }
        } catch (Exception $e) {
            // fall through to default
        }

        return $this->defaultInsights($conn);
    }

    private function getStats($conn) {
        $total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM students"))['c'] ?? 0;
        $total_exams = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams"))['c'] ?? 0;
        $total_results = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results"))['c'] ?? 0;

        $avgResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(percentage) AS avg FROM results"));
        $avg_score = round($avgResult['avg'] ?? 0, 1);

        $passResult = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE status='Pass'"));
        $totalRes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results"));
        $pass_rate = $totalRes['c'] > 0 ? round(($passResult['c'] / $totalRes['c']) * 100, 1) : 0;

        $weak = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE percentage < 40"));
        $moderate = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE percentage >= 40 AND percentage < 70"));
        $advanced = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE percentage >= 70"));

        return [
            'total_students' => $total_students,
            'total_exams' => $total_exams,
            'total_results' => $total_results,
            'avg_score' => $avg_score,
            'pass_rate' => $pass_rate,
            'weak_count' => $weak['c'] ?? 0,
            'moderate_count' => $moderate['c'] ?? 0,
            'advanced_count' => $advanced['c'] ?? 0,
        ];
    }

    private function defaultInsights($conn) {
        $stats = $this->getStats($conn);

        $summary = "System has " . $stats['total_students'] . " students, " . $stats['total_exams'] . " exams with " . $stats['total_results'] . " results completed. Average score: " . $stats['avg_score'] . "%.";

        $risk_alert = '';
        if ($stats['weak_count'] > $stats['total_results'] * 0.3) {
            $alertPercent = $stats['total_results'] > 0 ? round(($stats['weak_count'] / $stats['total_results']) * 100) : 0;
            $risk_alert = "ALERT: " . $stats['weak_count'] . " students (" . $alertPercent . "%) are scoring below 40%. Intervention recommended.";
        }

        $recommendation = 'Continue monitoring student performance and maintain question bank quality.';

        $trend = $stats['avg_score'] > 60
            ? 'Students are performing well overall.'
            : 'There is room for improvement in overall student performance.';

        return [
            'summary' => $summary,
            'risk_alert' => $risk_alert,
            'recommendation' => $recommendation,
            'trend' => $trend,
        ];
    }
}

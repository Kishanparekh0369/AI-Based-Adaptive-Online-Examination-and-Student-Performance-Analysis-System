<?php
require_once __DIR__ . '/../config/ai.php';

class SkillCategorizer {
    private $groq;
    private $model;

    public function __construct() {
        $this->groq = get_groq();
        $configFile = __DIR__ . '/../config/ai_config.json';
        $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
        $this->model = $config['model'] ?? 'llama-3.3-70b-versatile';
    }

    public function categorize($theta, $percentage, $correctByDifficulty) {
        $estimator = new AbilityEstimator();
        $estimator->loadState($theta, null);
        $category = $estimator->getCategory();

        if (!$this->groq) {
            return [
                'category' => $category,
                'description' => $this->getCategoryDescription($category, $percentage),
                'recommendation' => $this->getCategoryRecommendation($category),
            ];
        }

        $diffText = '';
        foreach ($correctByDifficulty as $diff => $data) {
            $diffText .= "$diff: {$data['correct']}/{$data['total']} correct, ";
        }

        $prompt = "Based on exam data:
        - Ability Score (theta): $theta
        - Overall Percentage: $percentage%
        - Difficulty Breakdown: $diffText

        Categorize this student as one of: Weak, Moderate, or Advanced.
        Return ONLY valid JSON with keys: category, description (1 sentence), recommendation (1 short actionable tip)";

        try {
            $response = $this->groq->chat()->completions()->create([
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.5,
                'max_tokens' => 200,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $result = json_decode($content, true);

            if ($result && isset($result['category'])) {
                return [
                    'category' => $result['category'],
                    'description' => $result['description'] ?? $this->getCategoryDescription($result['category'], $percentage),
                    'recommendation' => $result['recommendation'] ?? $this->getCategoryRecommendation($result['category']),
                ];
            }
        } catch (Exception $e) {
            // fall through
        }

        return [
            'category' => $category,
            'description' => $this->getCategoryDescription($category, $percentage),
            'recommendation' => $this->getCategoryRecommendation($category),
        ];
    }

    private function getCategoryDescription($category, $percentage) {
        $descriptions = [
            'Advanced' => "Strong performer with $percentage% score. Demonstrates excellent understanding of concepts.",
            'Moderate' => "Average performer with $percentage% score. Has good foundation but needs more practice.",
            'Weak' => "Needs improvement with $percentage% score. Should focus on fundamental concepts.",
        ];
        return $descriptions[$category] ?? $descriptions['Moderate'];
    }

    private function getCategoryRecommendation($category) {
        $recs = [
            'Advanced' => 'Challenge yourself with advanced topics and help peers.',
            'Moderate' => 'Focus on weak areas and practice more problems.',
            'Weak' => 'Review fundamentals and practice easy questions first.',
        ];
        return $recs[$category] ?? $recs['Moderate'];
    }
}

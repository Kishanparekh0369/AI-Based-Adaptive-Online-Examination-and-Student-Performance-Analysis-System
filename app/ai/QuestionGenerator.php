<?php
require_once __DIR__ . '/../config/ai.php';

class QuestionGenerator {
    private $groq;
    private $model;
    private $lastError = '';
    private $unavailableModels = [];
    private const MAX_UNIQUE_RETRIES = 3;

    public function __construct() {
        $this->groq = get_groq();
        $configFile = __DIR__ . '/../config/ai_config.json';
        $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
        $this->model = $config['model'] ?? 'openai/gpt-oss-20b';
    }

    public function generateMCQ($subject, $topic, $difficulty, $language, $marks = 1, $allowFallback = true, $existingQuestions = [], $currentQuestion = '', $currentOptions = [], $requestNonce = '') {
        $this->lastError = '';
        if (!$this->groq) {
            $this->lastError = 'AI service is not configured. Add a valid Groq API key in app/config/ai_config.json.';
            return $allowFallback ? $this->fallbackQuestion($subject, $difficulty) : null;
        }

        $subject = trim((string)$subject) ?: 'General';
        $difficulty = in_array($difficulty, ['Easy', 'Medium', 'Hard'], true) ? $difficulty : 'Medium';
        $marks = max(1, (int)$marks);
        $topicText = $topic ? " on topic '$topic'" : "";
        $langInstruction = $language === 'Hindi'
            ? "The question and ALL options must be in Hindi language (Devanagari script)."
            : ($language === 'Gujarati'
                ? "The question and ALL options must be in Gujarati language."
                : "The question and ALL options must be in English.");

        $existingQuestions = $this->questionTextList($existingQuestions);
        if (trim($currentQuestion) !== '') {
            $existingQuestions[] = trim($currentQuestion);
        }
        $existingQuestions = array_values(array_unique(array_filter($existingQuestions)));
        $currentOptionsText = $this->formatOptionsForPrompt($currentOptions);
        $existingText = empty($existingQuestions)
            ? "None"
            : "- " . implode("\n- ", array_slice($existingQuestions, 0, 30));
        $nonce = $requestNonce ?: bin2hex(random_bytes(8)) . '-' . microtime(true);
        $conceptGuide = $this->subjectConceptGuide($subject);

        for ($attempt = 1; $attempt <= self::MAX_UNIQUE_RETRIES; $attempt++) {
            $retryInstruction = $attempt > 1
                ? "\nPrevious attempt was rejected as duplicate/similar. Choose a completely different concept from $subject and use different answer/options."
                : "";
            $prompt = "You are an expert exam question generator.

Generate ONE high-quality MCQ specifically for the selected subject: $subject{$topicText}.

Subject control:
- The question must test a specific concept from $subject.
- Do not generate a generic programming question that could apply to another language or subject.
- Do not use generic templates such as: \"What is the primary function of X?\", \"What is X mainly used for?\", \"What is the purpose of X?\" unless the exact concept is unique and important.
- Prefer these subject-specific concept areas when relevant: $conceptGuide
- Keep the question concept, correct answer, and options different from the previous/existing questions where possible.

Requirements:
- Difficulty level: $difficulty
- $langInstruction
- Marks: $marks
- Provide exactly 4 options labeled A, B, C, D
- Only ONE option should be correct
- Wrong options must be plausible but clearly incorrect
- Return a fresh uncached question for request id: $nonce attempt $attempt

Previously generated questions that must NOT be repeated or paraphrased:
$existingText

Previous/current question being regenerated:
" . (trim($currentQuestion) !== '' ? trim($currentQuestion) : 'None') . "

Previous/current options:
$currentOptionsText
$retryInstruction

        Return ONLY valid JSON with EXACTLY these keys (correct_answer must be one letter: \"A\", \"B\", \"C\", or \"D\"):
        {
            \"question\": \"the question text\",
            \"option_a\": \"option A text\",
            \"option_b\": \"option B text\",
            \"option_c\": \"option C text\",
            \"option_d\": \"option D text\",
            \"correct_answer\": \"A\"
        }";

            foreach ($this->modelCandidates() as $model) {
                try {
                    $response = $this->groq->chat()->completions()->create([
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => min(1.0, 0.82 + ($attempt * 0.05)),
                        'max_tokens' => 650,
                    ]);

                    $content = $response['choices'][0]['message']['content'] ?? '{}';
                    $question = $this->normalizeAiResponse(json_decode($content, true), $difficulty, $language, $marks);

                    if (!$question) {
                        continue;
                    }

                    if ($this->isGenericQuestionTemplate($question['question'], $subject)) {
                        $this->lastError = 'Groq returned a generic question instead of a subject-specific concept.';
                        continue;
                    }

                    if ($this->isDuplicateQuestion($question, $existingQuestions, $currentOptions)) {
                        $this->lastError = 'Groq returned a duplicate or too-similar question.';
                        continue;
                    }

                    $this->model = $model;
                    return $question;
                } catch (Throwable $e) {
                    error_log('AI question generation failed: ' . $e->getMessage());
                    $this->lastError = 'AI service could not generate a question: ' . $e->getMessage();
                    if ($this->isModelUnavailableError($e->getMessage())) {
                        $this->unavailableModels[$model] = true;
                    }
                }
            }
        }

        $this->lastError = $this->lastError ?: "Groq could not generate a unique $subject question.";
        return $allowFallback ? $this->fallbackQuestion($subject, $difficulty, $marks, $existingQuestions) : null;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function generatePreviewMCQs($subject, $difficulties, $language = 'English', $marks = 1, $existingQuestions = []) {
        $this->lastError = '';
        $difficulties = array_values(array_filter((array)$difficulties));
        $count = count($difficulties);
        if ($count === 0) {
            return [];
        }
        if (!$this->groq) {
            $this->lastError = 'AI service is not configured. Add a valid Groq API key in app/config/ai_config.json.';
            return [];
        }

        $subject = trim((string)$subject) ?: 'General';
        $marks = max(1, (int)$marks);
        $existingQuestions = $this->questionTextList($existingQuestions);
        $conceptGuide = $this->subjectConceptGuide($subject);
        $langInstruction = $language === 'Hindi'
            ? "The question and ALL options must be in Hindi language (Devanagari script)."
            : ($language === 'Gujarati'
                ? "The question and ALL options must be in Gujarati language."
                : "The question and ALL options must be in English.");
        $difficultyLines = [];
        foreach ($difficulties as $index => $difficulty) {
            $difficulty = in_array($difficulty, ['Easy', 'Medium', 'Hard'], true) ? $difficulty : 'Medium';
            $difficultyLines[] = ($index + 1) . ". " . $difficulty;
        }
        $existingText = empty($existingQuestions)
            ? "None"
            : "- " . implode("\n- ", array_slice($existingQuestions, 0, 30));
        $nonce = bin2hex(random_bytes(8)) . '-' . microtime(true);

        $prompt = "You are an expert exam question generator.

Generate exactly $count high-quality MCQs specifically for the selected subject: $subject.

Subject control:
- Every question must test a specific concept from $subject.
- Do not generate generic programming questions that could apply to another language or subject.
- Do not use generic templates such as: \"What is the primary function of X?\", \"What is X mainly used for?\", \"What is the purpose of X?\".
- Use different concepts across the set. Prefer these areas when relevant: $conceptGuide
- Keep all questions different from each other and from the existing questions below.

Difficulty for each question:
" . implode("\n", $difficultyLines) . "

Requirements for every question:
- $langInstruction
- Marks: $marks
- Exactly 4 options: option_a, option_b, option_c, option_d
- Exactly ONE correct answer: A, B, C, or D
- Wrong options must be plausible but clearly incorrect
- Return fresh uncached questions for request id: $nonce

Previously generated questions that must NOT be repeated or paraphrased:
$existingText

Return ONLY valid JSON with this exact structure:
{
  \"questions\": [
    {
      \"question\": \"the question text\",
      \"option_a\": \"option A text\",
      \"option_b\": \"option B text\",
      \"option_c\": \"option C text\",
      \"option_d\": \"option D text\",
      \"correct_answer\": \"A\"
    }
  ]
}";

        foreach ($this->modelCandidates() as $model) {
            try {
                $response = $this->groq->chat()->completions()->create([
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.82,
                    'max_tokens' => min(3800, max(900, $count * 360)),
                ]);

                $content = $response['choices'][0]['message']['content'] ?? '{}';
                $decoded = json_decode($content, true);
                $items = $decoded['questions'] ?? [];
                if (!is_array($items)) {
                    $this->lastError = 'Groq returned invalid preview JSON.';
                    continue;
                }

                $accepted = [];
                foreach ($items as $index => $item) {
                    if (count($accepted) >= $count) {
                        break;
                    }
                    $difficulty = $difficulties[count($accepted)] ?? ($difficulties[$index] ?? 'Medium');
                    $question = $this->normalizeAiResponse($item, $difficulty, $language, $marks);
                    if (!$question) {
                        continue;
                    }
                    if ($this->isGenericQuestionTemplate($question['question'], $subject)) {
                        continue;
                    }
                    if ($this->isDuplicateQuestion($question, array_merge($existingQuestions, $accepted))) {
                        continue;
                    }
                    $accepted[] = $question;
                }

                if (count($accepted) === $count) {
                    $this->model = $model;
                    return $accepted;
                }

                $this->lastError = 'Groq returned fewer unique preview questions than requested.';
            } catch (Throwable $e) {
                error_log('AI preview batch generation failed: ' . $e->getMessage());
                $this->lastError = 'AI service could not generate preview questions: ' . $e->getMessage();
                if ($this->isModelUnavailableError($e->getMessage())) {
                    $this->unavailableModels[$model] = true;
                }
            }
        }

        return [];
    }

    private function modelCandidates() {
        $models = [
            $this->model,
            'openai/gpt-oss-20b',
            'openai/gpt-oss-120b',
            'groq/compound-mini',
        ];
        $models = array_values(array_unique(array_filter($models)));
        return array_values(array_filter($models, function($model) {
            if (in_array($model, ['llama-3.3-70b-versatile'], true)) {
                return false;
            }
            return empty($this->unavailableModels[$model]);
        }));
    }

    private function isModelUnavailableError($message) {
        $message = strtolower((string)$message);
        return strpos($message, 'model') !== false && (
            strpos($message, 'does not exist') !== false ||
            strpos($message, 'do not have access') !== false ||
            strpos($message, 'not found') !== false
        );
    }

    public function generateBatch($subject, $topic, $difficulty, $language, $count, $marks = 1) {
        $questions = [];
        $topics = $topic ? [$topic] : [
            'basic concepts',
            'core principles',
            'practical applications',
            'advanced topics',
            'common problems',
        ];

        for ($i = 0; $i < $count; $i++) {
            $t = $topics[$i % count($topics)];
            $q = $this->generateMCQ($subject, $t, $difficulty, $language, $marks);
            if ($q) $questions[] = $q;
        }

        return $questions;
    }

    public function generateAdaptiveExamQuestions($subjectId, $count, $language = 'English', $marks = 1) {
        global $conn;
        $this->lastError = '';
        $count = max(1, (int)$count);
        $subjectId = (int)$subjectId;
        $subResult = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id = $subjectId");
        $subRow = $subResult ? mysqli_fetch_assoc($subResult) : null;
        $subject = $subRow['subject_name'] ?? 'General';
        $distribution = $this->difficultyDistribution($count);
        $saved = [];
        $seen = [];

        foreach ($distribution as $difficulty) {
            $question = null;
            for ($attempt = 1; $attempt <= 4; $attempt++) {
                $candidate = $this->generateMCQ($subject, '', $difficulty, $language, $marks, false, $seen);
                if (!$candidate) {
                    continue;
                }
                $normalized = $this->normalizeQuestionText($candidate['question']);
                if ($normalized === '' || $this->isDuplicateQuestion($candidate, $seen) || $this->questionExistsForSubject($subjectId, $candidate['question'])) {
                    $seen[] = $candidate['question'];
                    continue;
                }
                $question = $candidate;
                break;
            }

            if (!$question) {
                $this->lastError = $this->lastError ?: "Groq could not generate a unique $difficulty question.";
                return [];
            }

            $id = $this->saveToDatabase($subjectId, $question);
            if (!$id) {
                $this->lastError = 'Database error while saving Groq question: ' . mysqli_error($conn);
                return [];
            }
            $question['id'] = $id;
            $saved[] = $question;
            $seen[] = $this->normalizeQuestionText($question['question']);
        }

        return $saved;
    }

    public function generateOnDemand($subjectId, $difficulty, $language) {
        global $conn;
        $subResult = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id = " . (int)$subjectId);
        $subject = $subResult ? mysqli_fetch_assoc($subResult)['subject_name'] : 'General';
        $topic = '';

        $q = $this->generateMCQ($subject, $topic, $difficulty, $language);

        if ($q && isset($q['question'])) {
            $q['id'] = $this->saveToDatabase($subjectId, $q);
            return $q;
        }

        return null;
    }

    public function saveToDatabase($subjectId, $q) {
        global $conn;
        $qText = mysqli_real_escape_string($conn, $q['question']);
        $lang = mysqli_real_escape_string($conn, $q['language'] ?? 'English');
        $a = mysqli_real_escape_string($conn, $q['option_a']);
        $b = mysqli_real_escape_string($conn, $q['option_b']);
        $c = mysqli_real_escape_string($conn, $q['option_c'] ?? '');
        $d = mysqli_real_escape_string($conn, $q['option_d'] ?? '');
        $ans = mysqli_real_escape_string($conn, $q['correct_answer']);
        $diff = mysqli_real_escape_string($conn, $q['difficulty'] ?? 'Medium');
        $marks = (int)($q['marks'] ?? 1);

        $ok = mysqli_query($conn, "INSERT INTO questions
            (subject_id, question, language, option_a, option_b, option_c, option_d,
             correct_answer, difficulty, marks, ai_generated)
            VALUES ($subjectId, '$qText', '$lang', '$a', '$b', '$c', '$d',
                    '$ans', '$diff', $marks, 1)");

        return $ok ? mysqli_insert_id($conn) : 0;
    }

    public static function calculateDifficultyDistribution($count) {
        $count = max(1, (int)$count);
        $base = intdiv($count, 3);
        $remainder = $count % 3;
        $easy = $base + ($remainder >= 1 ? 1 : 0);
        $medium = $base + ($remainder >= 2 ? 1 : 0);
        $hard = $base;
        $levels = [];
        for ($i = 0; $i < $easy; $i++) $levels[] = 'Easy';
        for ($i = 0; $i < $medium; $i++) $levels[] = 'Medium';
        for ($i = 0; $i < $hard; $i++) $levels[] = 'Hard';
        return $levels;
    }

    private function difficultyDistribution($count) {
        return self::calculateDifficultyDistribution($count);
    }

    public function normalizeQuestionText($text) {
        $text = strtolower((string)$text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\b(the|a|an|is|are|was|were|what|which|how|why|does|do|in|of|to|for|and|or)\b/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    public function isDuplicateQuestion($candidate, $existingQuestions = [], $currentOptions = []) {
        $candidateText = is_array($candidate) ? ($candidate['question'] ?? '') : (string)$candidate;
        $candidateNorm = $this->normalizeQuestionText($candidateText);
        if ($candidateNorm === '') {
            return true;
        }

        foreach ($this->questionTextList($existingQuestions) as $existingText) {
            $existingNorm = $this->normalizeQuestionText($existingText);
            if ($existingNorm === '') {
                continue;
            }
            if ($candidateNorm === $existingNorm) {
                return true;
            }
            similar_text($candidateNorm, $existingNorm, $percent);
            if ($percent >= 72) {
                return true;
            }
            if ($this->tokenOverlapRatio($candidateNorm, $existingNorm) >= 0.68) {
                return true;
            }
        }

        if (is_array($candidate) && !empty($currentOptions)) {
            $candidateOptions = $this->normalizedOptionSet($candidate);
            $oldOptions = $this->normalizedOptionSet($currentOptions);
            if (!empty($candidateOptions) && count(array_intersect($candidateOptions, $oldOptions)) >= 3) {
                return true;
            }
        }

        return false;
    }

    public function isGenericQuestionTemplate($questionText, $subject = '') {
        $questionText = strtolower(trim((string)$questionText));
        $subject = strtolower(trim((string)$subject));
        $subjectPattern = $subject !== '' ? preg_quote($subject, '/') : '[a-z0-9+#.\s]+';
        $patterns = [
            '/^what\s+is\s+the\s+(primary|main|major|basic)\s+(function|purpose|role|use)\s+of\s+' . $subjectPattern . '\??$/i',
            '/^what\s+is\s+' . $subjectPattern . '\s+(mainly|primarily)\s+used\s+for\??$/i',
            '/^why\s+is\s+' . $subjectPattern . '\s+used\??$/i',
            '/^which\s+statement\s+best\s+describes\s+' . $subjectPattern . '\??$/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $questionText)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeAiResponse($question, $difficulty, $language, $marks) {
        if (!$question || !is_array($question)) {
            $this->lastError = 'Groq returned invalid question JSON.';
            return null;
        }

        if (isset($question['options']) && is_array($question['options'])) {
            $question['option_a'] = $question['option_a'] ?? ($question['options']['A'] ?? $question['options']['a'] ?? '');
            $question['option_b'] = $question['option_b'] ?? ($question['options']['B'] ?? $question['options']['b'] ?? '');
            $question['option_c'] = $question['option_c'] ?? ($question['options']['C'] ?? $question['options']['c'] ?? '');
            $question['option_d'] = $question['option_d'] ?? ($question['options']['D'] ?? $question['options']['d'] ?? '');
        }

        foreach (['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'] as $key) {
            if (!isset($question[$key]) || trim((string)$question[$key]) === '') {
                $this->lastError = 'Groq returned incomplete question JSON.';
                return null;
            }
            $question[$key] = trim((string)$question[$key]);
        }

        $question['correct_answer'] = strtoupper($question['correct_answer']);
        if (!in_array($question['correct_answer'], ['A', 'B', 'C', 'D'], true)) {
            $this->lastError = 'Groq returned an invalid correct_answer value.';
            return null;
        }

        $question['difficulty'] = in_array($difficulty, ['Easy', 'Medium', 'Hard'], true) ? $difficulty : 'Medium';
        $question['language'] = $language ?: 'English';
        $question['marks'] = max(1, (int)$marks);
        return $question;
    }

    private function questionTextList($questions) {
        $list = [];
        foreach ((array)$questions as $q) {
            if (is_array($q)) {
                $text = $q['question'] ?? '';
            } else {
                $text = $q;
            }
            $text = trim((string)$text);
            if ($text !== '') {
                $list[] = $text;
            }
        }
        return $list;
    }

    private function tokenOverlapRatio($a, $b) {
        $aTokens = array_values(array_unique(array_filter(explode(' ', $a))));
        $bTokens = array_values(array_unique(array_filter(explode(' ', $b))));
        if (count($aTokens) === 0 || count($bTokens) === 0) {
            return 0;
        }
        $intersection = count(array_intersect($aTokens, $bTokens));
        return $intersection / max(1, min(count($aTokens), count($bTokens)));
    }

    private function normalizedOptionSet($source) {
        $options = [];
        if (isset($source['options']) && is_array($source['options'])) {
            $source = $source['options'];
        }
        foreach (['A', 'B', 'C', 'D', 'option_a', 'option_b', 'option_c', 'option_d'] as $key) {
            if (isset($source[$key]) && trim((string)$source[$key]) !== '') {
                $options[] = $this->normalizeQuestionText($source[$key]);
            }
        }
        return array_values(array_unique(array_filter($options)));
    }

    private function formatOptionsForPrompt($options) {
        $set = $this->optionMap($options);
        if (empty($set)) {
            return 'None';
        }
        $lines = [];
        foreach ($set as $letter => $value) {
            $lines[] = "$letter. $value";
        }
        return implode("\n", $lines);
    }

    private function optionMap($options) {
        if (!is_array($options)) {
            return [];
        }
        if (isset($options['options']) && is_array($options['options'])) {
            $options = $options['options'];
        }
        return array_filter([
            'A' => trim((string)($options['A'] ?? $options['option_a'] ?? '')),
            'B' => trim((string)($options['B'] ?? $options['option_b'] ?? '')),
            'C' => trim((string)($options['C'] ?? $options['option_c'] ?? '')),
            'D' => trim((string)($options['D'] ?? $options['option_d'] ?? '')),
        ]);
    }

    private function subjectConceptGuide($subject) {
        $key = strtolower(trim($subject));
        $guides = [
            'php' => 'variables, arrays, superglobals, forms, sessions, cookies, include vs require, MySQLi/PDO, OOP, traits, namespaces, exceptions, file handling',
            'c#' => 'classes, structs, interfaces, generics, LINQ, async/await, delegates, events, collections, namespaces, access modifiers, exception handling',
            'csharp' => 'classes, structs, interfaces, generics, LINQ, async/await, delegates, events, collections, namespaces, access modifiers, exception handling',
            'javascript' => 'let vs var, closures, promises, async/await, DOM events, event bubbling, JSON parsing, prototypes, modules, equality operators, arrays',
            'python' => 'lists, tuples, dictionaries, comprehensions, functions, decorators, exceptions, modules, classes, generators, context managers, file handling',
            'mysql' => 'primary keys, joins, GROUP BY, HAVING, indexes, normalization, transactions, constraints, UPDATE statements, aggregate functions, subqueries',
        ];
        return $guides[$key] ?? 'core syntax, data structures, control flow, functions, practical usage, error handling, best practices, subject-specific tools and concepts';
    }

    private function questionExistsForSubject($subjectId, $questionText) {
        global $conn;
        $subjectId = (int)$subjectId;
        $safe = mysqli_real_escape_string($conn, $questionText);
        $result = mysqli_query($conn, "SELECT id FROM questions WHERE subject_id = $subjectId AND LOWER(TRIM(question)) = LOWER(TRIM('$safe')) LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    }

    private function fallbackQuestion($subject, $difficulty, $marks = 1, $existingQuestions = []) {
        $subjectKey = strtolower(trim($subject));
        $fallbacks = [
            'php' => [
                ['question' => 'Which PHP superglobal is used to access form values submitted with the POST method?', 'option_a' => '$_GET', 'option_b' => '$_POST', 'option_c' => '$_SESSION', 'option_d' => '$_COOKIE', 'correct_answer' => 'B'],
                ['question' => 'What does session_start() do in PHP?', 'option_a' => 'Creates a database table', 'option_b' => 'Starts or resumes a server-side session', 'option_c' => 'Loads an external CSS file', 'option_d' => 'Deletes all cookies', 'correct_answer' => 'B'],
            ],
            'c#' => [
                ['question' => 'Which C# keyword is used to define a class?', 'option_a' => 'class', 'option_b' => 'structonly', 'option_c' => 'define', 'option_d' => 'object', 'correct_answer' => 'A'],
                ['question' => 'Which namespace contains the generic List<T> collection in C#?', 'option_a' => 'System.IO', 'option_b' => 'System.Net', 'option_c' => 'System.Collections.Generic', 'option_d' => 'System.Threading', 'correct_answer' => 'C'],
            ],
            'javascript' => [
                ['question' => 'What does JSON.parse() do in JavaScript?', 'option_a' => 'Converts an object to JSON text', 'option_b' => 'Converts JSON text into a JavaScript value', 'option_c' => 'Creates a new Promise', 'option_d' => 'Stops event bubbling', 'correct_answer' => 'B'],
                ['question' => 'Which JavaScript operator checks both value and type equality?', 'option_a' => '==', 'option_b' => '=', 'option_c' => '===', 'option_d' => '!=', 'correct_answer' => 'C'],
            ],
            'python' => [
                ['question' => 'Which Python keyword is used to define a function?', 'option_a' => 'func', 'option_b' => 'def', 'option_c' => 'function', 'option_d' => 'lambda_only', 'correct_answer' => 'B'],
                ['question' => 'Which Python data type stores key-value pairs?', 'option_a' => 'list', 'option_b' => 'tuple', 'option_c' => 'set', 'option_d' => 'dictionary', 'correct_answer' => 'D'],
            ],
            'mysql' => [
                ['question' => 'Which SQL clause filters grouped records in MySQL?', 'option_a' => 'WHERE', 'option_b' => 'ORDER BY', 'option_c' => 'HAVING', 'option_d' => 'LIMIT', 'correct_answer' => 'C'],
                ['question' => 'What does an INNER JOIN return in MySQL?', 'option_a' => 'Only unmatched rows from both tables', 'option_b' => 'Rows with matching values in both joined tables', 'option_c' => 'All rows from the left table', 'option_d' => 'A backup copy of both tables', 'correct_answer' => 'B'],
            ],
        ];
        $choices = $fallbacks[$subjectKey] ?? [
            ['question' => "Which item best represents a core concept in $subject?", 'option_a' => 'A subject-specific rule or technique', 'option_b' => 'An unrelated visual style', 'option_c' => 'A random file name', 'option_d' => 'A network cable type only', 'correct_answer' => 'A'],
        ];
        $q = $choices[0];
        foreach ($choices as $choice) {
            if (!$this->isDuplicateQuestion($choice, $existingQuestions)) {
                $q = $choice;
                break;
            }
        }
        $q['language'] = 'English';
        $q['difficulty'] = $difficulty;
        $q['marks'] = max(1, (int)$marks);
        return $q;
    }
}

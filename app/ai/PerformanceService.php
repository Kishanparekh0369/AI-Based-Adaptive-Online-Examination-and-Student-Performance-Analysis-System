<?php
/*
 * PerformanceService — REAL data-driven student performance analysis.
 *
 * Layer 1 (always correct): deterministic PHP/SQL metrics from the
 * authenticated student's own attempt (results, answers_detail,
 * student_answers / adaptive_answers, questions.keywords/difficulty).
 *
 * Layer 2 (interpretation): Groq AI turns those metrics into a structured,
 * personalized analysis. Never static text, never invented topics/scores.
 *
 * Security: every method takes $studentId from the PHP session (caller
 * responsibility) and re-verifies attempt ownership server-side.
 */
require_once __DIR__ . '/../config/ai.php';

class PerformanceService {
    private $groq;
    private $model;

    public function __construct() {
        $this->groq = get_groq();
        $configFile = __DIR__ . '/../config/ai_config.json';
        $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : array();
        $this->model = $config['model'] ?? 'openai/gpt-oss-20b';
    }

    public function isAvailable() {
        return (bool)$this->groq;
    }

    /* Add cache column once (idempotent, additive only). */
    public function ensureColumn($conn) {
        $c = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'ai_analysis_json'");
        if ($c && mysqli_num_rows($c) === 0) {
            mysqli_query($conn, "ALTER TABLE results ADD COLUMN ai_analysis_json LONGTEXT NULL");
        }
    }

    /* ── Load one OWNED attempt + all derived metrics ── */
    public function getAttemptData($conn, $studentId, $resultId) {
        $studentId = (int)$studentId;
        $resultId = (int)$resultId;
        if ($studentId <= 0 || $resultId <= 0) { return null; }

        $stmt = mysqli_prepare($conn, "SELECT r.*, e.exam_title, e.exam_mode, e.total_marks AS exam_total_marks,
                e.pass_marks, e.subject_id, s.subject_name, st.name AS student_name, st.course, st.semester
            FROM results r
            LEFT JOIN exams e ON r.exam_id = e.id
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN students st ON st.id = r.student_id
            WHERE r.id = ? AND r.student_id = ? LIMIT 1");
        if (!$stmt) { return null; }
        mysqli_stmt_bind_param($stmt, "ii", $resultId, $studentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) { return null; }

        $isAdaptive = (($row["exam_mode"] ?? "Standard") === "Adaptive");
        $totalQ = max((int)($row["total_questions"] ?? 0), 0);
        $correct = (int)($row["correct_answers"] ?? 0);
        $wrong = (int)($row["wrong_answers"] ?? 0);
        $skipped = max($totalQ - $correct - $wrong, 0);
        $obtained = (int)($row["marks_obtained"] ?? 0);
        $totalMarks = max((int)($row["exam_total_marks"] ?? 0), $totalQ, 1);
        $passMarks = min((int)($row["pass_marks"] ?? 0), $totalMarks);
        $pct = $totalMarks > 0 ? round(($obtained / $totalMarks) * 100, 2) : 0;
        $pct = min($pct, 100);
        $isPass = $obtained >= $passMarks;
        $timeTotal = max((int)($row["time_spent_seconds"] ?? 0), 0);

        // Per-question list: answers_detail JSON is the per-attempt source of truth.
        $questions = array();
        if (!empty($row["answers_detail"])) {
            $dec = json_decode($row["answers_detail"], true);
            if (is_array($dec)) { $questions = array_values($dec); }
        }

        // Enrich: keywords (topic) + per-question time where EXACTLY attributable.
        $subjectId = (int)($row["subject_id"] ?? 0);
        if ($isAdaptive && !empty($row["session_id"])) {
            $sessionId = (int)$row["session_id"];
            $tq = mysqli_query($conn, "SELECT aa.is_correct, aa.time_taken_seconds, q.keywords, q.question
                FROM adaptive_answers aa LEFT JOIN questions q ON q.id = aa.question_id
                WHERE aa.session_id = $sessionId ORDER BY aa.id");
            $enrich = array();
            if ($tq) { while ($t = mysqli_fetch_assoc($tq)) { $enrich[] = $t; } }
            foreach ($questions as $i => $qq) {
                if (isset($enrich[$i])) {
                    if (empty($qq["topic"]) && !empty($enrich[$i]["keywords"])) { $questions[$i]["topic"] = $enrich[$i]["keywords"]; }
                    $questions[$i]["time_seconds"] = max((int)($enrich[$i]["time_taken_seconds"] ?? 0), 0);
                }
            }
        } elseif ($subjectId > 0) {
            // Standard: match by exact question text within the exam subject (read-only heuristic).
            foreach ($questions as $i => $qq) {
                if (!empty($qq["topic"]) || empty($qq["question"])) { continue; }
                $qt = mysqli_real_escape_string($conn, (string)$qq["question"]);
                $kq = mysqli_query($conn, "SELECT keywords FROM questions WHERE subject_id = $subjectId AND question = '$qt' LIMIT 1");
                if ($kq && ($kr = mysqli_fetch_assoc($kq)) && !empty($kr["keywords"])) {
                    $questions[$i]["topic"] = $kr["keywords"];
                }
            }
        }

        // Difficulty summary — ONLY levels present in this attempt.
        $difficulty = array();
        foreach ($questions as $qq) {
            $d = trim((string)($qq["difficulty"] ?? ""));
            if ($d === "") { continue; }
            if (!isset($difficulty[$d])) { $difficulty[$d] = array("attempted" => 0, "correct" => 0, "incorrect" => 0); }
            $difficulty[$d]["attempted"]++;
            if ((int)($qq["is_correct"] ?? 0) === 1) { $difficulty[$d]["correct"]++; }
            else { $difficulty[$d]["incorrect"]++; }
        }
        // Fallback to stored breakdown when no question-level rows exist.
        if (empty($difficulty) && !empty($row["difficulty_breakdown"])) {
            $bd = json_decode($row["difficulty_breakdown"], true);
            if (is_array($bd)) {
                foreach ($bd as $d => $dt) {
                    $t = (int)($dt["total"] ?? 0);
                    if ($t <= 0) { continue; }
                    $c = (int)($dt["correct"] ?? 0);
                    $difficulty[$d] = array("attempted" => $t, "correct" => $c, "incorrect" => max($t - $c, 0));
                }
            }
        }
        foreach ($difficulty as $d => $v) {
            $difficulty[$d]["accuracy"] = $v["attempted"] > 0 ? round(($v["correct"] / $v["attempted"]) * 100, 1) : 0;
        }

        // Topic summary from keywords (first keyword = topic). No keywords → no topics, no invention.
        $topics = array();
        foreach ($questions as $qq) {
            $kw = trim((string)($qq["topic"] ?? ($qq["keywords"] ?? "")));
            if ($kw === "") { continue; }
            $parts = explode(",", $kw);
            $topic = trim($parts[0]);
            if ($topic === "") { continue; }
            $key = mb_strtolower($topic);
            if (!isset($topics[$key])) { $topics[$key] = array("topic" => $topic, "attempted" => 0, "correct" => 0); }
            $topics[$key]["attempted"]++;
            if ((int)($qq["is_correct"] ?? 0) === 1) { $topics[$key]["correct"]++; }
        }
        foreach ($topics as $k => $v) {
            $topics[$k]["accuracy"] = $v["attempted"] > 0 ? round(($v["correct"] / $v["attempted"]) * 100, 1) : 0;
        }
        uasort($topics, function ($a, $b) { return $b["attempted"] <=> $a["attempted"]; });

        // Timing summary.
        $timing = array("total_seconds" => $timeTotal, "per_question_available" => false);
        $times = array();
        foreach ($questions as $qq) {
            if (isset($qq["time_seconds"]) && (int)$qq["time_seconds"] > 0) { $times[] = (int)$qq["time_seconds"]; }
        }
        if (!empty($times)) {
            $timing["per_question_available"] = true;
            $timing["avg_seconds"] = round(array_sum($times) / count($times), 1);
            $byDiff = array();
            foreach ($questions as $qq) {
                if (!isset($qq["time_seconds"]) || (int)$qq["time_seconds"] <= 0) { continue; }
                $d = trim((string)($qq["difficulty"] ?? "Unknown"));
                if (!isset($byDiff[$d])) { $byDiff[$d] = array(); }
                $byDiff[$d][] = (int)$qq["time_seconds"];
            }
            foreach ($byDiff as $d => $arr) { $timing["avg_by_difficulty"][$d] = round(array_sum($arr) / count($arr), 1); }
        } elseif ($totalQ > 0) {
            $timing["avg_seconds"] = round($timeTotal / $totalQ, 1);
        }

        // Previous attempt on the SAME exam (real trend only).
        $previous = null;
        $pq = mysqli_prepare($conn, "SELECT id, percentage, marks_obtained, submitted_at FROM results
            WHERE student_id = ? AND exam_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
        if ($pq) {
            $examId = (int)$row["exam_id"];
            mysqli_stmt_bind_param($pq, "iii", $studentId, $examId, $resultId);
            mysqli_stmt_execute($pq);
            $pr = mysqli_stmt_get_result($pq);
            if ($pr && ($prow = mysqli_fetch_assoc($pr))) {
                $previous = array(
                    "percentage" => round((float)$prow["percentage"], 2),
                    "marks_obtained" => (int)$prow["marks_obtained"],
                    "submitted_at" => $prow["submitted_at"] ?? "",
                    "delta_pp" => round($pct - (float)$prow["percentage"], 2),
                );
            }
            mysqli_stmt_close($pq);
        }

        return array(
            "student" => array(
                "name" => $row["student_name"] ?? "Student",
                "course" => $row["course"] ?? "",
                "semester" => $row["semester"] ?? "",
            ),
            "exam" => array(
                "id" => (int)$row["exam_id"],
                "title" => $row["exam_title"] ?? "Exam",
                "subject" => $row["subject_name"] ?? "",
                "mode" => $isAdaptive ? "Adaptive" : "Standard",
            ),
            "result" => array(
                "id" => $resultId,
                "obtained" => $obtained,
                "total_marks" => $totalMarks,
                "percentage" => $pct,
                "pass_marks" => $passMarks,
                "status" => $isPass ? "Pass" : "Fail",
                "correct" => $correct,
                "incorrect" => $wrong,
                "skipped" => $skipped,
                "total_questions" => $totalQ,
                "submitted_at" => $row["submitted_at"] ?? "",
            ),
            "questions" => $questions,
            "question_count" => count($questions),
            "difficulty" => $difficulty,
            "topics" => array_values($topics),
            "has_topics" => !empty($topics),
            "timing" => $timing,
            "previous" => $previous,
        );
    }

    /* ── Structured text context for the model ── */
    public function buildContext($d) {
        $s = $d["student"];
        $e = $d["exam"];
        $r = $d["result"];
        $lines = array();
        $lines[] = "Student:";
        $lines[] = "Name: " . $s["name"];
        if ($s["course"] !== "") { $lines[] = "Course: " . $s["course"]; }
        if ($s["semester"] !== "") { $lines[] = "Semester: " . $s["semester"]; }
        $lines[] = "";
        $lines[] = "Exam:";
        $lines[] = "Title: " . $e["title"];
        if ($e["subject"] !== "") { $lines[] = "Subject: " . $e["subject"]; }
        $lines[] = "Mode: " . $e["mode"];
        $lines[] = "";
        $lines[] = "Result:";
        $lines[] = "Obtained: " . $r["obtained"] . " / " . $r["total_marks"];
        $lines[] = "Percentage: " . $r["percentage"] . "%";
        $lines[] = "Passing Marks: " . $r["pass_marks"];
        $lines[] = "Status: " . $r["status"];
        $lines[] = "Correct: " . $r["correct"] . ", Incorrect: " . $r["incorrect"] . ", Skipped: " . $r["skipped"] . " (of " . $r["total_questions"] . " questions)";
        $lines[] = "";
        $lines[] = "Difficulty Summary (attempted / correct / accuracy):";
        if (!empty($d["difficulty"])) {
            foreach ($d["difficulty"] as $level => $v) {
                $lines[] = "- $level: " . $v["attempted"] . " attempted, " . $v["correct"] . " correct, " . $v["accuracy"] . "% accuracy";
            }
        } else {
            $lines[] = "- Difficulty data unavailable for this attempt.";
        }
        $lines[] = "";
        $lines[] = "Topic Summary:";
        if (!empty($d["topics"])) {
            foreach ($d["topics"] as $t) {
                $lines[] = "- " . $t["topic"] . ": " . $t["attempted"] . " questions, " . $t["correct"] . " correct, " . $t["accuracy"] . "% accuracy";
            }
        } else {
            $lines[] = "- Topic information unavailable. Do NOT invent topics.";
        }
        $lines[] = "";
        $lines[] = "Timing Summary:";
        $tm = $d["timing"];
        $lines[] = "- Total time: " . $tm["total_seconds"] . " seconds";
        if (!empty($tm["per_question_available"])) {
            $lines[] = "- Average per question: " . $tm["avg_seconds"] . " seconds";
            if (!empty($tm["avg_by_difficulty"])) {
                foreach ($tm["avg_by_difficulty"] as $level => $avg) {
                    $lines[] = "- Avg on $level questions: $avg seconds";
                }
            }
        } else {
            $lines[] = "- Per-question timing unavailable. Do NOT invent time-management observations.";
            if (isset($tm["avg_seconds"])) { $lines[] = "- Overall average per question: " . $tm["avg_seconds"] . " seconds"; }
        }
        $lines[] = "";
        if (!empty($d["previous"])) {
            $p = $d["previous"];
            $lines[] = "Previous attempt on this exam: " . $p["percentage"] . "% (" . $p["marks_obtained"] . " marks). Change: " . ($p["delta_pp"] >= 0 ? "+" : "") . $p["delta_pp"] . " percentage points.";
        } else {
            $lines[] = "Previous attempt on this exam: none. Do NOT mention any trend.";
        }
        if (!empty($d["questions"])) {
            $lines[] = "";
            $lines[] = "Question-level performance (up to 30 shown):";
            $i = 0;
            foreach ($d["questions"] as $qq) {
                $i++;
                if ($i > 30) { break; }
                $qline = "Q$i: difficulty=" . ($qq["difficulty"] ?? "Unknown")
                    . ", result=" . (((int)($qq["is_correct"] ?? 0) === 1) ? "correct" : ((string)($qq["student_answer"] ?? "") !== "" ? "incorrect" : "skipped"))
                    . ", marks=" . (int)($qq["marks"] ?? 0);
                if (!empty($qq["topic"])) { $qline .= ", topic=" . mb_substr((string)$qq["topic"], 0, 60); }
                $lines[] = $qline;
            }
        }
        return implode("\n", $lines);
    }

    private function systemPrompt() {
        return "You are an educational performance analyst. Analyze ONLY the provided student performance data. "
            . "Do not invent scores, topics, difficulty levels, answers, timing, weak areas, previous results, or learning history. "
            . "Do not assume information that is not provided. If information is unavailable, explicitly state that it is unavailable. "
            . "Provide concise, evidence-based, personalized academic guidance using the student's actual metrics. "
            . "Address the student by first name occasionally, naturally. Do not start every response the same way. "
            . "Avoid generic motivational filler (e.g. 'keep practicing', 'never give up') unless tied to a specific data-backed action. "
            . "Return ONLY valid JSON matching the requested schema.";
    }

    /* ── Groq interpretation → structured array (or null) ── */
    public function generateAnalysis($d) {
        if (!$this->groq) { return null; }
        $presentLevels = array_keys($d["difficulty"]);
        $schemaHint = '"overall_analysis": "2-3 sentence personalized summary with actual score", '
            . '"strengths": ["evidence-based strength", ...], '
            . '"areas_for_improvement": ["evidence-based weakness", ...], '
            . '"difficulty_analysis": {"<each level present>: "1 sentence"}, '
            . '"topic_analysis": [{"topic": "...", "accuracy": 0, "analysis": "..."}] (empty array if no topic data), '
            . '"recommendations": ["specific action", ...], '
            . '"next_steps": ["immediate next step"]';
        $prompt = $this->systemPrompt() . "\n\nAnalyze this exam attempt. Difficulty levels present in this attempt: "
            . (empty($presentLevels) ? "none" : implode(", ", $presentLevels))
            . ". Only include difficulty_analysis keys for these levels.\n\n"
            . $this->buildContext($d)
            . "\n\nReturn ONLY valid JSON: {" . $schemaHint . "}";
        try {
            $response = $this->groq->chat()->completions()->create(array(
                "model" => $this->model,
                "messages" => array(array("role" => "user", "content" => $prompt)),
                "response_format" => array("type" => "json_object"),
                "temperature" => 0.4,
                "max_tokens" => 1400,
            ));
            $content = $response["choices"][0]["message"]["content"] ?? "";
            $parsed = $this->parseAnalysis($content, $d);
            return $parsed;
        } catch (Exception $e) {
            error_log("PerformanceService AI failed: " . $e->getMessage());
            return null;
        }
    }

    /* Robust parse: strip fences, decode, validate shape, clamp to real levels. */
    public function parseAnalysis($content, $d) {
        if (!is_string($content) || trim($content) === "") { return null; }
        $t = trim($content);
        $t = preg_replace('/^```(?:json)?\s*/i', '', $t);
        $t = preg_replace('/\s*```$/', '', $t);
        $a = json_decode($t, true);
        if (!is_array($a)) {
            $s = strpos($t, "{");
            $e = strrpos($t, "}");
            if ($s === false || $e === false || $e <= $s) { return null; }
            $a = json_decode(substr($t, $s, $e - $s + 1), true);
            if (!is_array($a)) { return null; }
        }
        $str = function ($v) { return trim((string)$v); };
        $strList = function ($v) use ($str) {
            $out = array();
            if (is_array($v)) { foreach ($v as $x) { $x = is_array($x) ? "" : $str($x); if ($x !== "") { $out[] = $x; } } }
            elseif ($v !== null && $str($v) !== "") { $out[] = $str($v); }
            return array_slice($out, 0, 8);
        };
        $out = array();
        $out["overall_analysis"] = $str($a["overall_analysis"] ?? "");
        $out["strengths"] = $strList($a["strengths"] ?? array());
        $out["areas_for_improvement"] = $strList($a["areas_for_improvement"] ?? array());
        $out["difficulty_analysis"] = array();
        if (isset($a["difficulty_analysis"]) && is_array($a["difficulty_analysis"])) {
            foreach ($d["difficulty"] as $level => $v) {
                foreach ($a["difficulty_analysis"] as $k => $val) {
                    if (strcasecmp(trim((string)$k), $level) === 0 && $str($val) !== "") {
                        $out["difficulty_analysis"][$level] = $str($val);
                        break;
                    }
                }
            }
        }
        $out["topic_analysis"] = array();
        if (!empty($d["has_topics"]) && isset($a["topic_analysis"]) && is_array($a["topic_analysis"])) {
            $known = array();
            foreach ($d["topics"] as $t) { $known[mb_strtolower($t["topic"])] = $t; }
            foreach ($a["topic_analysis"] as $ta) {
                if (!is_array($ta)) { continue; }
                $tn = $str($ta["topic"] ?? "");
                $key = mb_strtolower($tn);
                if ($tn === "" || !isset($known[$key])) { continue; } // reject invented topics
                $out["topic_analysis"][] = array(
                    "topic" => $known[$key]["topic"],
                    "accuracy" => $known[$key]["accuracy"],
                    "attempted" => $known[$key]["attempted"],
                    "correct" => $known[$key]["correct"],
                    "analysis" => $str($ta["analysis"] ?? ""),
                );
                if (count($out["topic_analysis"]) >= 8) { break; }
            }
        }
        $out["recommendations"] = $strList($a["recommendations"] ?? array());
        $out["next_steps"] = $strList($a["next_steps"] ?? array());
        if ($out["overall_analysis"] === "" && empty($out["strengths"]) && empty($out["areas_for_improvement"])) {
            return null;
        }
        return $out;
    }

    /* Deterministic, data-driven fallback — real numbers, zero generic filler. */
    public function fallbackAnalysis($d) {
        $r = $d["result"];
        $name = explode(" ", trim($d["student"]["name"]))[0];
        $overall = $name . ", you scored " . $r["obtained"] . "/" . $r["total_marks"]
            . " (" . $r["percentage"] . "%) in " . $d["exam"]["title"] . " — Status: " . $r["status"]
            . ". You answered " . $r["correct"] . " correctly, " . $r["incorrect"] . " incorrectly"
            . ($r["skipped"] > 0 ? (" and skipped " . $r["skipped"]) : " with no skips") . ".";
        $strengths = array();
        $areas = array();
        foreach ($d["difficulty"] as $level => $v) {
            if ($v["accuracy"] >= 70 && $v["attempted"] > 0) {
                $strengths[] = $level . " questions: " . $v["correct"] . "/" . $v["attempted"] . " correct (" . $v["accuracy"] . "% accuracy).";
            } elseif ($v["attempted"] > 0) {
                $areas[] = $level . " questions: only " . $v["correct"] . "/" . $v["attempted"] . " correct (" . $v["accuracy"] . "% accuracy) — needs work.";
            }
        }
        $topics = array();
        foreach ($d["topics"] as $t) {
            $topics[] = array(
                "topic" => $t["topic"],
                "accuracy" => $t["accuracy"],
                "attempted" => $t["attempted"],
                "correct" => $t["correct"],
                "analysis" => $t["accuracy"] >= 70 ? "Strong area." : ($t["accuracy"] >= 40 ? "Moderate — review key concepts." : "Priority improvement area."),
            );
        }
        $recs = array();
        if ($r["skipped"] > 0) { $recs[] = "Attempt all questions next time — " . $r["skipped"] . " skipped question(s) cost marks."; }
        $weakLevels = array();
        foreach ($d["difficulty"] as $level => $v) { if ($v["attempted"] > 0 && $v["accuracy"] < 60) { $weakLevels[] = $level; } }
        if (!empty($weakLevels)) { $recs[] = "Practice more " . implode(" and ", $weakLevels) . "-level questions for " . ($d["exam"]["subject"] !== "" ? $d["exam"]["subject"] : "this subject") . "."; }
        if ($r["status"] === "Fail") { $recs[] = "You needed " . $r["pass_marks"] . " marks to pass — a focused revision of the weak areas above can close this gap."; }
        if (empty($recs)) { $recs[] = "Maintain this level by attempting timed mixed-difficulty practice sets."; }
        $next = array($r["status"] === "Pass" ? "Move to the next topic or a higher-difficulty assessment." : "Revise the weak areas above, then re-attempt this assessment.");
        return array(
            "overall_analysis" => $overall,
            "strengths" => $strengths,
            "areas_for_improvement" => $areas,
            "difficulty_analysis" => array(),
            "topic_analysis" => $topics,
            "recommendations" => $recs,
            "next_steps" => $next,
            "_fallback" => true,
        );
    }

    /* ── Cache helpers ── */
    public function loadCached($conn, $resultId) {
        $resultId = (int)$resultId;
        $q = mysqli_query($conn, "SELECT ai_analysis_json FROM results WHERE id = $resultId LIMIT 1");
        if ($q && ($row = mysqli_fetch_assoc($q)) && !empty($row["ai_analysis_json"])) {
            $a = json_decode($row["ai_analysis_json"], true);
            if (is_array($a) && !empty($a["overall_analysis"])) { return $a; }
        }
        return null;
    }

    public function saveAnalysis($conn, $studentId, $resultId, $analysis) {
        $this->ensureColumn($conn);
        $resultId = (int)$resultId;
        $studentId = (int)$studentId;
        $json = mysqli_real_escape_string($conn, json_encode($analysis, JSON_UNESCAPED_UNICODE));
        mysqli_query($conn, "UPDATE results SET ai_analysis_json = '$json' WHERE id = $resultId AND student_id = $studentId LIMIT 1");
        return mysqli_affected_rows($conn) >= 0;
    }
}

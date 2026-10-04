<?php
/**
 * StudentContext — builds the authorized academic context for the
 * currently authenticated student. Student ID ALWAYS comes from
 * the PHP session; never from client input.
 *
 * Only non-sensitive academic/profile fields are collected.
 * NEVER password hashes, tokens, or credentials.
 */

class StudentContext
{
    public static function forSession($conn)
    {
        $studentId = isset($_SESSION["student_id"]) ? (int)$_SESSION["student_id"] : 0;
        if ($studentId <= 0) {
            return null;
        }
        return self::build($conn, $studentId);
    }

    public static function build($conn, $studentId)
    {
        $studentId = (int)$studentId;
        if ($studentId <= 0 || !$conn) {
            return null;
        }

        $ctx = array(
            "student_id" => $studentId,
            "profile" => array(),
            "performance" => array(),
            "recent_results" => array(),
            "subject_performance" => array(),
            "available_exams" => array(),
            "upcoming_exams" => array(),
            "completed_exams" => array(),
            "ai_feedback" => array(),
        );

        // ---- Profile (non-sensitive fields only) ----
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, course, semester, status, created_at, last_login FROM students WHERE id=? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $ctx["profile"] = array(
                    "name" => (string)($row["name"] ?? ""),
                    "email" => (string)($row["email"] ?? ""),
                    "phone" => (string)($row["phone"] ?? ""),
                    "course" => (string)($row["course"] ?? ""),
                    "semester" => (string)($row["semester"] ?? ""),
                    "status" => (string)($row["status"] ?? ""),
                    "member_since" => !empty($row["created_at"]) ? date("d M Y", strtotime($row["created_at"])) : "",
                );
            }
            mysqli_stmt_close($stmt);
        }
        if (empty($ctx["profile"])) {
            return null;
        }

        // ---- Aggregates (computed server-side) ----
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total, AVG(percentage) AS avgp,
            SUM(correct_answers) AS correct_total, SUM(wrong_answers) AS wrong_total,
            SUM(total_questions) AS q_total, SUM(time_spent_seconds) AS t_total,
            SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) AS passed,
            MAX(percentage) AS best, MIN(percentage) AS worst
            FROM results WHERE student_id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $total = (int)($row["total"] ?? 0);
                $qTotal = (int)($row["q_total"] ?? 0);
                $correct = (int)($row["correct_total"] ?? 0);
                $ctx["performance"] = array(
                    "exams_completed" => $total,
                    "average_score" => $total > 0 && $row["avgp"] !== null ? round((float)$row["avgp"], 1) : null,
                    "accuracy" => $qTotal > 0 ? round(($correct / $qTotal) * 100, 1) : null,
                    "questions_answered" => $qTotal,
                    "correct_answers" => $correct,
                    "wrong_answers" => (int)($row["wrong_total"] ?? 0),
                    "exams_passed" => (int)($row["passed"] ?? 0),
                    "best_score" => $total > 0 ? round((float)$row["best"], 1) : null,
                    "lowest_score" => $total > 0 ? round((float)$row["worst"], 1) : null,
                );
            }
            mysqli_stmt_close($stmt);
        }

        // ---- Recent results (latest 5) ----
        $stmt = mysqli_prepare($conn, "SELECT r.exam_id, r.total_questions, r.correct_answers, r.wrong_answers,
            r.marks_obtained, r.percentage, r.status, r.skill_category, r.submitted_at,
            e.exam_title, s.subject_name
            FROM results r
            LEFT JOIN exams e ON r.exam_id=e.id
            LEFT JOIN subjects s ON e.subject_id=s.id
            WHERE r.student_id=? ORDER BY r.id DESC LIMIT 5");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $total = max((int)$row["total_questions"], 0);
                    $skipped = max(0, $total - (int)$row["correct_answers"] - (int)$row["wrong_answers"]);
                    $ctx["recent_results"][] = array(
                        "exam" => (string)($row["exam_title"] ?? "Exam"),
                        "subject" => (string)($row["subject_name"] ?? "General"),
                        "percentage" => round((float)$row["percentage"], 1),
                        "correct" => (int)$row["correct_answers"],
                        "wrong" => (int)$row["wrong_answers"],
                        "skipped" => $skipped,
                        "total_questions" => $total,
                        "status" => (string)($row["status"] ?? ""),
                        "skill" => (string)($row["skill_category"] ?? ""),
                        "date" => !empty($row["submitted_at"]) ? date("d M Y", strtotime($row["submitted_at"])) : "",
                    );
                }
            }
            mysqli_stmt_close($stmt);
        }

        // ---- Subject-wise performance ----
        $stmt = mysqli_prepare($conn, "SELECT s.subject_name, COUNT(r.id) AS taken, AVG(r.percentage) AS avgp
            FROM results r
            LEFT JOIN exams e ON r.exam_id=e.id
            LEFT JOIN subjects s ON e.subject_id=s.id
            WHERE r.student_id=? GROUP BY s.id ORDER BY avgp DESC");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $ctx["subject_performance"][] = array(
                        "subject" => (string)($row["subject_name"] ?? "General"),
                        "exams_taken" => (int)$row["taken"],
                        "average" => round((float)$row["avgp"], 1),
                    );
                }
            }
            mysqli_stmt_close($stmt);
        }

        // ---- Exams: available / upcoming / completed ----
        $today = date("Y-m-d");
        $course = (string)($ctx["profile"]["course"] ?? "");
        $semester = (string)($ctx["profile"]["semester"] ?? "");
        $stmt = mysqli_prepare($conn, "SELECT e.id, e.exam_title, e.exam_date, e.duration, e.total_marks, e.exam_mode,
            s.subject_name,
            (SELECT r.id FROM results r WHERE r.exam_id=e.id AND r.student_id=? LIMIT 1) AS result_id,
            (SELECT r.percentage FROM results r WHERE r.exam_id=e.id AND r.student_id=? ORDER BY r.id DESC LIMIT 1) AS result_pct,
            (SELECT b.id FROM exam_attempt_blocks b WHERE b.exam_id=e.id AND b.student_id=? LIMIT 1) AS blocked_id
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id=s.id
            WHERE e.status='Active' AND e.course=? AND e.semester=? ORDER BY e.exam_date ASC");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiiss", $studentId, $studentId, $studentId, $course, $semester);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $item = array(
                        "title" => (string)$row["exam_title"],
                        "subject" => (string)($row["subject_name"] ?? "General"),
                        "date" => !empty($row["exam_date"]) ? date("d M Y", strtotime($row["exam_date"])) : "Not set",
                        "duration_minutes" => (int)$row["duration"],
                        "total_marks" => (int)$row["total_marks"],
                        "mode" => (string)($row["exam_mode"] ?? "Standard"),
                    );
                    if ($row["result_id"] !== null) {
                        $item["score"] = round((float)$row["result_pct"], 1);
                        $ctx["completed_exams"][] = $item;
                    } elseif ($row["blocked_id"] !== null) {
                        continue;
                    } elseif (!empty($row["exam_date"]) && $row["exam_date"] > $today) {
                        $ctx["upcoming_exams"][] = $item;
                    } else {
                        $ctx["available_exams"][] = $item;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        // ---- Latest AI feedback entries (text only) ----
        $stmt = mysqli_prepare($conn, "SELECT r.ai_insight, r.skill_category, e.exam_title
            FROM results r LEFT JOIN exams e ON r.exam_id=e.id
            WHERE r.student_id=? AND r.ai_insight IS NOT NULL AND TRIM(r.ai_insight) <> ''
            ORDER BY r.id DESC LIMIT 2");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $text = trim((string)$row["ai_insight"]);
                    if ($text !== "") {
                        $ctx["ai_feedback"][] = array(
                            "exam" => (string)($row["exam_title"] ?? "Exam"),
                            "skill" => (string)($row["skill_category"] ?? ""),
                            "insight" => mb_substr($text, 0, 1200),
                        );
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        return $ctx;
    }

    public static function toPromptText($ctx)
    {
        if (!$ctx) { return "No student data available."; }
        $p = $ctx["profile"];
        $perf = $ctx["performance"];

        $out = "Student Profile:\n";
        $out .= "Name: " . $p["name"] . "\n";
        $out .= "Course: " . $p["course"] . "\n";
        $out .= "Semester: " . $p["semester"] . "\n";
        $out .= "Account Status: " . $p["status"] . "\n\n";

        $out .= "Performance Summary:\n";
        if (!empty($perf) && ($perf["exams_completed"] ?? 0) > 0) {
            $out .= "Exams Completed: " . $perf["exams_completed"] . "\n";
            $out .= "Average Score: " . ($perf["average_score"] === null ? "N/A" : $perf["average_score"] . "%") . "\n";
            $out .= "Accuracy: " . ($perf["accuracy"] === null ? "N/A" : $perf["accuracy"] . "%") . "\n";
            $out .= "Correct Answers: " . $perf["correct_answers"] . " / " . $perf["questions_answered"] . "\n";
            $out .= "Wrong Answers: " . $perf["wrong_answers"] . "\n";
            $out .= "Exams Passed: " . $perf["exams_passed"] . "\n";
            $out .= "Best Score: " . ($perf["best_score"] === null ? "N/A" : $perf["best_score"] . "%") . "\n";
            $out .= "Lowest Score: " . ($perf["lowest_score"] === null ? "N/A" : $perf["lowest_score"] . "%") . "\n";
        } else {
            $out .= "No examinations completed yet.\n";
        }
        $out .= "\n";

        $out .= "Recent Results (latest first):\n";
        if (!empty($ctx["recent_results"])) {
            foreach ($ctx["recent_results"] as $r) {
                $out .= "- Exam: " . $r["exam"] . " (" . $r["subject"] . "), Score: " . $r["percentage"] . "%, "
                    . "Correct: " . $r["correct"] . ", Wrong: " . $r["wrong"] . ", Skipped: " . $r["skipped"] . ", "
                    . "Status: " . $r["status"] . ", Date: " . $r["date"] . "\n";
            }
        } else {
            $out .= "None yet.\n";
        }
        $out .= "\n";

        $out .= "Subject Performance:\n";
        if (!empty($ctx["subject_performance"])) {
            foreach ($ctx["subject_performance"] as $s) {
                $out .= "- " . $s["subject"] . ": " . $s["average"] . "% (" . $s["exams_taken"] . " exams)\n";
            }
        } else {
            $out .= "None yet.\n";
        }
        $out .= "\n";

        $out .= "Available Exams:\n";
        if (!empty($ctx["available_exams"])) {
            foreach ($ctx["available_exams"] as $e) {
                $out .= "- " . $e["title"] . " (" . $e["subject"] . "), Date: " . $e["date"] . ", Duration: " . $e["duration_minutes"] . " min, Marks: " . $e["total_marks"] . ", Mode: " . $e["mode"] . "\n";
            }
        } else {
            $out .= "None currently available.\n";
        }
        $out .= "\n";

        $out .= "Upcoming Exams:\n";
        if (!empty($ctx["upcoming_exams"])) {
            foreach ($ctx["upcoming_exams"] as $e) {
                $out .= "- " . $e["title"] . " (" . $e["subject"] . "), Date: " . $e["date"] . ", Duration: " . $e["duration_minutes"] . " min\n";
            }
        } else {
            $out .= "None scheduled.\n";
        }
        $out .= "\n";

        $out .= "Latest AI Feedback:\n";
        if (!empty($ctx["ai_feedback"])) {
            foreach ($ctx["ai_feedback"] as $f) {
                $out .= "- Exam: " . $f["exam"] . ($f["skill"] !== "" ? " (Skill: " . $f["skill"] . ")" : "") . "\n  " . $f["insight"] . "\n";
            }
        } else {
            $out .= "No AI feedback recorded yet.\n";
        }

        return $out;
    }
}

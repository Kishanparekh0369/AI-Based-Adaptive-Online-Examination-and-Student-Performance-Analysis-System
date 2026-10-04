<?php
/* ══════════════════════════════════════════════════════════════════
   Central Database Reset Service — AI Exam System (development).
   ------------------------------------------------------------------
   Clears transactional/test data while PRESERVING schema, master
   data (subjects, questions unless opted in) and the admin account.
   - Child tables are cleared before parents (dependency order).
   - TRUNCATE is used for fully-cleared tables (also resets
     AUTO_INCREMENT); conditional DELETEs elsewhere.
   - FOREIGN_KEY_CHECKS is disabled during the run and ALWAYS
     restored (shutdown guard + post verification).
   - Every destructive step is counted, logged and verified.
   Never include this file's execution from a GET request — the
   admin page only runs it on a CSRF-verified POST with typed
   confirmation, admin session and APP_ENV=development.
   ══════════════════════════════════════════════════════════════════ */

function db_reset_modes()
{
    return array(
        "full" => "Full Development Reset",
        "student" => "Student Data Reset",
        "exam" => "Exam Data Reset",
    );
}

/**
 * Ordered execution plan. Each op: ["op" => truncate|delete, "table", "where"?].
 * Order is child → parent (safe even with FK checks enabled).
 */
function db_reset_plan($mode, $include_questions = false)
{
    $plan = array();
    if ($mode === "student") {
        $plan = array(
            array("op" => "truncate", "table" => "adaptive_answers"),
            array("op" => "truncate", "table" => "student_answers"),
            array("op" => "truncate", "table" => "exam_attempt_blocks"),
            array("op" => "truncate", "table" => "adaptive_sessions"),
            array("op" => "truncate", "table" => "results"),
            array("op" => "truncate", "table" => "feedback"),
            array("op" => "truncate", "table" => "attendance"),
            array("op" => "truncate", "table" => "face_verifications"),
            array("op" => "truncate", "table" => "announcement_recipients"),
            array("op" => "delete", "table" => "notifications", "where" => "user_type='student'"),
            array("op" => "delete", "table" => "password_resets", "where" => "user_role='student'"),
            array("op" => "truncate", "table" => "students"),
        );
    } elseif ($mode === "exam") {
        $plan = array(
            array("op" => "truncate", "table" => "adaptive_answers"),
            array("op" => "truncate", "table" => "student_answers"),
            array("op" => "truncate", "table" => "exam_attempt_blocks"),
            array("op" => "truncate", "table" => "adaptive_sessions"),
            array("op" => "truncate", "table" => "results"),
            array("op" => "truncate", "table" => "exam_questions"),
            array("op" => "truncate", "table" => "exams"),
        );
    } else {
        $mode = "full";
        $plan = array(
            array("op" => "truncate", "table" => "adaptive_answers"),
            array("op" => "truncate", "table" => "student_answers"),
            array("op" => "truncate", "table" => "exam_attempt_blocks"),
            array("op" => "truncate", "table" => "adaptive_sessions"),
            array("op" => "truncate", "table" => "results"),
            array("op" => "truncate", "table" => "feedback"),
            array("op" => "truncate", "table" => "attendance"),
            array("op" => "truncate", "table" => "face_verifications"),
            array("op" => "truncate", "table" => "exam_questions"),
            array("op" => "truncate", "table" => "faculty_subjects"),
            array("op" => "truncate", "table" => "announcement_recipients"),
            array("op" => "truncate", "table" => "notifications"),
            array("op" => "truncate", "table" => "password_resets"),
            array("op" => "truncate", "table" => "contact_messages"),
            array("op" => "truncate", "table" => "announcements"),
            array("op" => "truncate", "table" => "exams"),
            array("op" => "truncate", "table" => "students"),
            array("op" => "truncate", "table" => "faculty"),
        );
        if ($include_questions) {
            // Questions have no dependents left at this point except the
            // already-cleared answer tables — safe to append last.
            $plan[] = array("op" => "truncate", "table" => "questions");
        }
    }
    return $plan;
}

/** Tables intentionally NEVER touched by any mode. */
function db_reset_preserved_tables()
{
    return array("admin", "subjects");
}

function db_reset_count($conn, $table, $where = "")
{
    $sql = "SELECT COUNT(*) AS c FROM `$table`" . ($where !== "" ? " WHERE $where" : "");
    $r = mysqli_query($conn, $sql);
    if (!$r) { return -1; }
    $row = mysqli_fetch_assoc($r);
    return (int)($row["c"] ?? 0);
}

/** Live per-table counts for the preview screen. */
function db_reset_preview($conn, $mode, $include_questions = false)
{
    $out = array();
    foreach (db_reset_plan($mode, $include_questions) as $op) {
        $t = $op["table"];
        $w = isset($op["where"]) ? $op["where"] : "";
        if (!isset($out[$t])) {
            $out[$t] = db_reset_count($conn, $t, $w);
        }
    }
    return $out;
}

function db_reset_backup_dir()
{
    return rtrim((string)APP_ROOT, "/\\") . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "backups";
}

/** Full SQL dump via mysqldump (PHP fallback if exec is unavailable). */
function db_reset_backup($conn)
{
    $dir = db_reset_backup_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . DIRECTORY_SEPARATOR . ".htaccess";
    if (!file_exists($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    $stamp = date("Ymd_His");
    $file = $dir . DIRECTORY_SEPARATOR . "ai_adaptive_exam_" . $stamp . ".sql";
    $db = "ai_adaptive_exam";

    $mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";
    if (!file_exists($mysqldump)) {
        $mysqldump = "mysqldump";
    }
    $cmd = '"' . $mysqldump . '" --host=localhost --user=root --single-transaction --skip-lock-tables '
        . escapeshellarg($db) . ' 2>&1';
    $out = array();
    $code = 1;
    if (function_exists("exec")) {
        @exec($cmd, $out, $code);
    }
    if ($code === 0 && !empty($out)) {
        @file_put_contents($file, implode("\n", $out));
        return array("ok" => true, "file" => $file, "bytes" => filesize($file));
    }
    // PHP fallback dump (structure + data).
    $dump = "-- AI Exam System backup " . date("Y-m-d H:i:s") . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = array();
    $tq = mysqli_query($conn, "SHOW TABLES");
    if ($tq) { while ($tr = mysqli_fetch_row($tq)) { $tables[] = $tr[0]; } }
    foreach ($tables as $t) {
        $cr = mysqli_query($conn, "SHOW CREATE TABLE `$t`");
        if ($cr && ($crow = mysqli_fetch_assoc($cr))) {
            $dump .= "DROP TABLE IF EXISTS `$t`;\n" . $crow["Create Table"] . ";\n\n";
        }
        $dr = mysqli_query($conn, "SELECT * FROM `$t`");
        if ($dr) {
            while ($row = mysqli_fetch_assoc($dr)) {
                $vals = array();
                foreach ($row as $v) {
                    $vals[] = $v === null ? "NULL" : ("'" . mysqli_real_escape_string($conn, (string)$v) . "'");
                }
                $dump .= "INSERT INTO `$t` VALUES (" . implode(",", $vals) . ");\n";
            }
            $dump .= "\n";
        }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    @file_put_contents($file, $dump);
    if (file_exists($file) && filesize($file) > 0) {
        return array("ok" => true, "file" => $file, "bytes" => filesize($file));
    }
    return array("ok" => false, "file" => "", "bytes" => 0);
}

function db_reset_ensure_fk_checks($conn, $value)
{
    @mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=" . ($value ? "1" : "0"));
}

function db_reset_fk_checks($conn)
{
    $r = mysqli_query($conn, "SELECT @@FOREIGN_KEY_CHECKS AS v");
    if (!$r) { return -1; }
    $row = mysqli_fetch_assoc($r);
    return (int)($row["v"] ?? -1);
}

function db_reset_auto_increment($conn, $table)
{
    $r = mysqli_query($conn, "SELECT AUTO_INCREMENT AS ai FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$r) { return null; }
    $row = mysqli_fetch_assoc($r);
    return $row && $row["ai"] !== null ? (int)$row["ai"] : null;
}

/** Remove user-uploaded files (profile/faculty/attendance/verification). Files only. */
function db_reset_cleanup_uploads($dirs = array("profile", "faculty", "attendance", "verification"))
{
    $base = rtrim((string)APP_ROOT, "/\\") . DIRECTORY_SEPARATOR . "assets"
        . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
    $removed = 0;
    foreach ($dirs as $d) {
        $dir = $base . $d;
        if (!is_dir($dir)) { continue; }
        foreach (scandir($dir) as $f) {
            if ($f === "." || $f === ".." || $f === ".htaccess") { continue; }
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($p) && @unlink($p)) { $removed++; }
        }
    }
    return $removed;
}

/** Invalidate other users' file sessions; the executing admin stays signed in. */
function db_reset_clear_other_sessions()
{
    $dir = rtrim((string)APP_ROOT, "/\\") . DIRECTORY_SEPARATOR . "assets"
        . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "sessions";
    if (!is_dir($dir)) { return 0; }
    $mine = function_exists("session_id") ? (string)session_id() : "";
    $removed = 0;
    foreach (scandir($dir) as $f) {
        if ($f === "." || $f === ".." || $f === ".htaccess") { continue; }
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($p)) { continue; }
        if ($mine !== "" && (strpos($f, $mine) !== false || $f === "sess_" . $mine)) { continue; }
        if (@unlink($p)) { $removed++; }
    }
    return $removed;
}

function db_reset_log($admin_id, $mode, $details)
{
    $ref = function_exists("app_error_reference") ? app_error_reference() : ("RESET-" . date("Ymd") . "-" . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6)));
    $line = "[" . date("Y-m-d H:i:s") . "] [$ref] [RESET] admin_id=" . (int)$admin_id
        . " mode=$mode " . str_replace(array("\r", "\n"), " ", (string)$details) . PHP_EOL;
    $file = rtrim((string)APP_ROOT, "/\\") . DIRECTORY_SEPARATOR . "app"
        . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . date("Y-m-d") . ".log";
    $dir = dirname($file);
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    return $ref;
}

/**
 * Execute the reset. Returns report array (never throws on SQL issues —
 * per-step failures are recorded, FK checks always restored).
 */
function db_reset_execute($conn, $admin_id, $mode, $options = array())
{
    $modes = db_reset_modes();
    if (!isset($modes[$mode])) { $mode = "full"; }
    $include_questions = $mode === "full" && !empty($options["include_questions"]);
    $plan = db_reset_plan($mode, $include_questions);
    $report = array(
        "mode" => $mode,
        "tables" => array(),
        "total_deleted" => 0,
        "errors" => array(),
        "fk_restored" => false,
        "uploads_removed" => 0,
        "sessions_cleared" => 0,
    );

    // Snapshot counts first (also serves the "records deleted" report).
    foreach ($plan as $op) {
        $t = $op["table"];
        if (!isset($report["tables"][$t])) {
            $report["tables"][$t] = array("before" => db_reset_count($conn, $t, isset($op["where"]) ? $op["where"] : ""), "deleted" => 0, "after" => null, "ok" => false);
        }
    }

    // Guarantee FK checks come back on even on fatal failure.
    $GLOBALS["__DB_RESET_FK_GUARD"] = true;
    register_shutdown_function(function () {
        if (!empty($GLOBALS["__DB_RESET_FK_GUARD"]) && isset($GLOBALS["__DB_RESET_CONN"])) {
            @mysqli_query($GLOBALS["__DB_RESET_CONN"], "SET FOREIGN_KEY_CHECKS=1");
        }
    });
    $GLOBALS["__DB_RESET_CONN"] = $conn;
    db_reset_ensure_fk_checks($conn, 0);

    foreach ($plan as $op) {
        $t = $op["table"];
        if ($op["op"] === "delete") {
            $ok = mysqli_query($conn, "DELETE FROM `$t` WHERE " . $op["where"]);
            if ($ok) {
                $report["tables"][$t]["deleted"] += mysqli_affected_rows($conn);
                $report["tables"][$t]["ok"] = true;
            } else {
                $report["tables"][$t]["ok"] = false;
                $report["errors"][] = "DELETE failed on $t";
            }
            continue;
        }
        if (mysqli_query($conn, "TRUNCATE TABLE `$t`")) {
            $report["tables"][$t]["ok"] = true;
        } else {
            $report["tables"][$t]["ok"] = false;
            $report["errors"][] = "TRUNCATE failed on $t";
        }
    }

    // AUTO_INCREMENT reset for DELETE-cleared tables that ended up empty.
    foreach ($plan as $op) {
        if ($op["op"] !== "delete") { continue; }
        $t = $op["table"];
        if (db_reset_count($conn, $t) === 0) {
            @mysqli_query($conn, "ALTER TABLE `$t` AUTO_INCREMENT = 1");
        }
    }

    if (!empty($options["cleanup_uploads"])) {
        $report["uploads_removed"] = db_reset_cleanup_uploads();
    }
    $report["sessions_cleared"] = db_reset_clear_other_sessions();

    db_reset_ensure_fk_checks($conn, 1);
    $report["fk_restored"] = (db_reset_fk_checks($conn) === 1);
    $GLOBALS["__DB_RESET_FK_GUARD"] = false;

    foreach ($report["tables"] as $t => $info) {
        $after = db_reset_count($conn, $t);
        $report["tables"][$t]["after"] = $after;
        if ($info["deleted"] === 0 && isset($info["before"])) {
            // TRUNCATE path: deleted = before - after.
            $report["tables"][$t]["deleted"] = max(0, (int)$info["before"] - (int)$after);
        }
        $report["total_deleted"] += (int)$report["tables"][$t]["deleted"];
    }
    return $report;
}

/** Post-reset verification: counts, AUTO_INCREMENT, FK checks, orphans. */
function db_reset_verify($conn, $mode, $include_questions = false)
{
    $out = array("checks" => array(), "ok" => true);
    $plan = db_reset_plan($mode, $include_questions);
    foreach ($plan as $op) {
        $t = $op["table"];
        if (isset($out["checks"][$t])) { continue; }
        $after = db_reset_count($conn, $t);
        // Conditional deletes (student-mode notifications/resets) may legitimately retain rows.
        $must_be_empty = ($op["op"] === "truncate");
        $pass = !$must_be_empty || $after === 0;
        $ai = db_reset_auto_increment($conn, $t);
        if ($must_be_empty && $after === 0 && $ai !== null && $ai !== 1) {
            $pass = false;
        }
        $out["checks"][$t] = array("rows" => $after, "ai" => $ai, "pass" => $pass);
        if (!$pass) { $out["ok"] = false; }
    }
    $fk = db_reset_fk_checks($conn);
    $out["fk_checks"] = $fk;
    if ($fk !== 1) { $out["ok"] = false; }
    // Orphan spot-checks (must all be zero after a clean reset).
    $orphans = array(
        "results→students" => "SELECT COUNT(*) AS c FROM results r LEFT JOIN students s ON s.id=r.student_id WHERE s.id IS NULL",
        "results→exams" => "SELECT COUNT(*) AS c FROM results r LEFT JOIN exams e ON e.id=r.exam_id WHERE e.id IS NULL",
        "answers→questions" => "SELECT COUNT(*) AS c FROM student_answers sa LEFT JOIN questions q ON q.id=sa.question_id WHERE q.id IS NULL",
        "adaptive→sessions" => "SELECT COUNT(*) AS c FROM adaptive_answers aa LEFT JOIN adaptive_sessions s ON s.id=aa.session_id WHERE s.id IS NULL",
        "exam_questions→exams" => "SELECT COUNT(*) AS c FROM exam_questions eq LEFT JOIN exams e ON e.id=eq.exam_id WHERE e.id IS NULL",
        "exam_questions→questions" => "SELECT COUNT(*) AS c FROM exam_questions eq LEFT JOIN questions q ON q.id=eq.question_id WHERE q.id IS NULL",
        "sessions→students" => "SELECT COUNT(*) AS c FROM adaptive_sessions s LEFT JOIN students st ON st.id=s.student_id WHERE st.id IS NULL",
    );
    $out["orphans"] = array();
    foreach ($orphans as $label => $sql) {
        $r = mysqli_query($conn, $sql);
        $row = $r ? mysqli_fetch_assoc($r) : array("c" => -1);
        $c = (int)($row["c"] ?? -1);
        $out["orphans"][$label] = $c;
        if ($c !== 0) { $out["ok"] = false; }
    }
    return $out;
}

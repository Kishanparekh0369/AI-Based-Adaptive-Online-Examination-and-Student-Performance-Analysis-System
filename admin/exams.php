<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Create Exam";

$edit_id = "";
$exam_title = "";
$subject_id = "";
$exam_date = date("Y-m-d");
$duration = 20;
$pass_marks = 0;
$status = "Active";
$exam_mode = "Standard";
$max_questions = 10;
$selected_questions = array();
$form_error = "";
$ai_preview_questions = array();
$ai_preview_active = false;

function ai_exam_difficulty_for_index($index) {
    $levels = array("Easy", "Medium", "Hard");
    return $levels[$index % count($levels)];
}

function normalize_ai_preview_questions($raw) {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return array();
    }

    $questions = array();
    foreach ($decoded as $q) {
        if (!is_array($q)) {
            continue;
        }
        $answer = strtoupper(trim($q["correct_answer"] ?? ""));
        if (!in_array($answer, array("A", "B", "C", "D"))) {
            $answer = "A";
        }
        $marks = max(1, (int)($q["marks"] ?? 1));
        $questions[] = array(
            "question" => trim($q["question"] ?? ""),
            "option_a" => trim($q["option_a"] ?? ""),
            "option_b" => trim($q["option_b"] ?? ""),
            "option_c" => trim($q["option_c"] ?? ""),
            "option_d" => trim($q["option_d"] ?? ""),
            "correct_answer" => $answer,
            "difficulty" => trim($q["difficulty"] ?? "Medium"),
            "language" => trim($q["language"] ?? "English"),
            "marks" => $marks
        );
    }

    return $questions;
}

function get_subject_name_by_id($conn, $subject_id) {
    $subject_id = (int)$subject_id;
    $result = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id='$subject_id' LIMIT 1");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row["subject_name"];
    }
    return "General";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajax_action"]) && $_POST["ajax_action"] == "regenerate_ai_question") {
    require_once("../app/ai/QuestionGenerator.php");
    header("Content-Type: application/json");

    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $difficulty = clean_data($_POST["difficulty"] ?? "Medium");
    $marks = max(1, (int)($_POST["marks"] ?? 1));
    $existing = normalize_ai_preview_questions($_POST["existing_questions"] ?? "[]");
    $current_question = clean_data($_POST["current_question"] ?? "");
    $current_options = json_decode($_POST["current_options"] ?? "[]", true);
    if (!is_array($current_options)) {
        $current_options = array();
    }
    $question_id = clean_data($_POST["question_id"] ?? "");
    $request_nonce = clean_data($_POST["generation_nonce"] ?? uniqid("regen_", true));
    $subject_name = get_subject_name_by_id($conn, $subject_id);
    $generator = new QuestionGenerator();
    $question = $generator->generateMCQ($subject_name, "", $difficulty, "English", $marks, false, $existing, $current_question, $current_options, $request_nonce . "_" . $question_id);

    if (!$question) {
        echo json_encode(array("success" => false, "message" => $generator->getLastError() ?: "Could not regenerate question."));
        exit;
    }

    echo json_encode(array("success" => true, "question" => $question));
    exit;
}

if (isset($_GET["ajax_action"]) && $_GET["ajax_action"] == "load_subject_questions") {
    header("Content-Type: text/html; charset=UTF-8");
    $ajax_subject_id = (int)($_GET["subject_id"] ?? 0);
    $context = clean_data($_GET["context"] ?? "standard");
    $selected_ids = array();
    if (!empty($_GET["selected_ids"])) {
        foreach (explode(",", $_GET["selected_ids"]) as $selected_id) {
            $selected_id = (int)$selected_id;
            if ($selected_id > 0) {
                $selected_ids[] = $selected_id;
            }
        }
    }

    $qres = mysqli_query($conn, "SELECT q.*, s.subject_name FROM questions q LEFT JOIN subjects s ON q.subject_id=s.id WHERE q.subject_id='$ajax_subject_id' ORDER BY q.id ASC");
    if ($qres && mysqli_num_rows($qres) > 0) {
        while ($q = mysqli_fetch_assoc($qres)) { ?>
            <?php if ($context == "edit_modal") { ?>
            <label class="edit-question-card <?php if (in_array((int)$q["id"], $selected_ids)) { echo "is-selected"; } ?>">
                <input class="edit-question-checkbox" type="checkbox" name="questions[]" value="<?php echo (int)$q["id"]; ?>" <?php if (in_array((int)$q["id"], $selected_ids)) { echo "checked"; } ?>>
                <span class="edit-question-check"><i class="bi bi-check2"></i></span>
                <span class="edit-question-content">
                    <span class="edit-question-badges">
                        <span class="premium-badge premium-badge-primary"><?php echo h($q["subject_name"] ?? "Subject"); ?></span>
                        <span class="premium-badge premium-badge-muted"><?php echo h($q["difficulty"]); ?></span>
                        <?php if (!empty($q["ai_generated"])) { ?><span class="premium-badge premium-badge-ai"><i class="bi bi-robot"></i>AI Generated</span><?php } else { ?><span class="premium-badge premium-badge-manual"><i class="bi bi-person-check"></i>Manual</span><?php } ?>
                    </span>
                    <span class="edit-question-text"><?php echo h($q["question"]); ?></span>
                </span>
            </label>
            <?php } else { ?>
            <label class="standard-question-card <?php if (in_array((int)$q["id"], $selected_ids)) { echo "is-selected"; } ?>">
                <input class="standard-question-checkbox" type="checkbox" value="<?php echo (int)$q["id"]; ?>" <?php if (in_array((int)$q["id"], $selected_ids)) { echo "checked"; } ?>>
                <span class="standard-question-check"><i class="bi bi-check2"></i></span>
                <span class="standard-question-body">
                    <span class="standard-question-text"><?php echo h($q["question"]); ?></span>
                    <span class="standard-question-meta">
                        <span class="premium-badge premium-badge-muted"><?php echo h($q["difficulty"]); ?></span>
                        <span class="premium-badge premium-badge-primary"><?php echo (int)($q["marks"] ?? 1); ?> Marks</span>
                        <?php if (!empty($q["ai_generated"])) { ?><span class="premium-badge premium-badge-ai"><i class="bi bi-robot"></i>AI Generated</span><?php } else { ?><span class="premium-badge premium-badge-manual"><i class="bi bi-person-check"></i>Manual</span><?php } ?>
                    </span>
                </span>
            </label>
            <?php } ?>
        <?php }
    } else { ?>
        <div class="empty-state text-center">No questions available in this subject.</div>
    <?php }
    exit;
}

if (isset($_GET["delete"])) {
    $id = (int)clean_data($_GET["delete"]);
    if (mysqli_query($conn, "DELETE FROM exams WHERE id=$id")) {
        flash_script("Exam deleted successfully", "exams.php", "success");
    } else {
        flash_script("Delete failed: " . mysqli_error($conn), "exams.php", "error");
    }
    header("Location: exams.php");
    exit;
}

if (isset($_GET["edit"])) {
    $edit_id = clean_data($_GET["edit"]);
    $result = mysqli_query($conn, "SELECT * FROM exams WHERE id='$edit_id'");
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $exam_title = $row["exam_title"];
        $subject_id = $row["subject_id"];
        $exam_date = $row["exam_date"];
        $duration = $row["duration"];
        $pass_marks = $row["pass_marks"];
        $status = $row["status"];
        $exam_mode = $row["exam_mode"] ?? "Standard";
        $max_questions = (int)($row["max_questions"] ?? 10);
    }
    $qres = mysqli_query($conn, "SELECT question_id FROM exam_questions WHERE exam_id='$edit_id'");
    while ($qrow = mysqli_fetch_assoc($qres)) {
        $selected_questions[] = $qrow["question_id"];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_action = clean_data($_POST["form_action"] ?? "");
    $should_save_exam = true;
    $edit_id = clean_data($_POST["edit_id"]);
    $exam_title = clean_data($_POST["exam_title"]);
    $subject_id = clean_data($_POST["subject_id"]);
    if ($edit_id != "") {
        $stored_exam = mysqli_query($conn, "SELECT subject_id FROM exams WHERE id='$edit_id' LIMIT 1");
        if ($stored_exam && $stored_row = mysqli_fetch_assoc($stored_exam)) {
            $subject_id = $stored_row["subject_id"];
        }
    }
    $exam_date = clean_data($_POST["exam_date"]);
    $duration = clean_data($_POST["duration"]);
    $pass_marks = clean_data($_POST["pass_marks"]);
    $status = clean_data($_POST["status"]);
    $questions = isset($_POST["questions"]) ? $_POST["questions"] : array();
    $selected_questions = $questions;

    $exam_mode = clean_data($_POST["exam_mode"]);
    $max_questions = (int)($_POST["max_questions"] ?? 10);
    if ($max_questions < 1) $max_questions = 10;
    if ($edit_id != "") {
        $max_questions = count($questions);
    }
    $ai_preview_questions = normalize_ai_preview_questions($_POST["ai_questions_json"] ?? "[]");
    $ai_preview_active = $exam_mode == 'Adaptive' && in_array($form_action, array("generate_ai_preview", "create_exam_confirmed"));

    if ($exam_title == "" || $subject_id == "" || $exam_date == "" || $duration == "" || $pass_marks == "") {
        $form_error = "All exam fields are required";
    }
    if (!$form_error && strtotime($exam_date) < strtotime(date("Y-m-d"))) {
        $form_error = "Exam date cannot be in the past";
    }
    if (!$form_error && count($questions) == 0 && $exam_mode != 'Adaptive') {
        $form_error = "Select at least one question or switch to Adaptive mode";
    }
    if (!$form_error && $exam_mode == 'Adaptive' && $edit_id != "" && count($questions) == 0) {
        $form_error = "Select at least one question for this adaptive exam";
    }

    $total_marks = 0;
    foreach ($questions as $qid) {
        $qid = clean_data($qid);
        $mres = mysqli_query($conn, "SELECT marks FROM questions WHERE id='$qid'");
        if ($mrow = mysqli_fetch_assoc($mres)) {
            $total_marks += $mrow["marks"];
        }
    }
    if (!$form_error && $pass_marks > $total_marks && $exam_mode != 'Adaptive') {
        $form_error = "Pass marks cannot be greater than total marks";
    }

    $check = mysqli_query($conn, "SELECT * FROM exams WHERE exam_title='$exam_title' AND id!='$edit_id'");
    if (!$form_error && $check && mysqli_num_rows($check) > 0) {
        $form_error = "Exam title already exists";
    }

    if (!$form_error && $exam_mode == 'Adaptive' && $edit_id == "" && $form_action == "generate_ai_preview") {
        require_once("../app/ai/QuestionGenerator.php");
        $generator = new QuestionGenerator();
        $subject_name = get_subject_name_by_id($conn, $subject_id);
        $difficulties = QuestionGenerator::calculateDifficultyDistribution($max_questions);
        $generated = $generator->generatePreviewMCQs($subject_name, $difficulties, "English", 1);

        if (count($generated) < $max_questions) {
            $seen = $generated;
            for ($i = count($generated); $i < $max_questions; $i++) {
                $question = $generator->generateMCQ($subject_name, "", $difficulties[$i], "English", 1, false, $seen, "", array(), "preview_" . time() . "_" . $i);
                if (!$question) {
                    $form_error = $generator->getLastError() ?: "AI could not generate question " . ($i + 1) . ".";
                    break;
                }
                $generated[] = $question;
                $seen[] = $question;
            }
        }

        if (!$form_error) {
            $ai_preview_questions = $generated;
            $ai_preview_active = true;
            $total_marks = count($ai_preview_questions);
        }
    }

    if (!$form_error && $exam_mode == 'Adaptive' && $edit_id == "" && $form_action != "create_exam_confirmed") {
        $should_save_exam = false;
    } elseif (!$form_error) {
        if ($exam_mode == 'Adaptive' && $edit_id == "") {
            if (count($ai_preview_questions) == 0) {
                $form_error = "Generate and review AI questions before creating the exam.";
            }
            if (!$form_error && count($ai_preview_questions) != $max_questions) {
                $form_error = "Total questions must match the reviewed AI question list.";
            }
            $prior_preview_questions = array();
            $preview_validator = null;
            foreach ($ai_preview_questions as $question) {
                if (!$form_error && ($question["question"] == "" || $question["option_a"] == "" || $question["option_b"] == "" || $question["option_c"] == "" || $question["option_d"] == "")) {
                    $form_error = "All AI preview question and option fields are required.";
                }
                if (!$form_error) {
                    require_once("../app/ai/QuestionGenerator.php");
                    $preview_validator = $preview_validator ?: new QuestionGenerator();
                    if ($preview_validator->isDuplicateQuestion($question, $prior_preview_questions)) {
                        $form_error = "AI preview contains duplicate or too-similar questions. Please regenerate the repeated question.";
                    }
                }
                $prior_preview_questions[] = $question;
            }
            if (!$form_error) {
                require_once("../app/ai/QuestionGenerator.php");
                $generator = new QuestionGenerator();
                $questions = array();
                foreach ($ai_preview_questions as $question) {
                    $qid = $generator->saveToDatabase((int)$subject_id, $question);
                    if (!$qid) {
                        $form_error = "Database error while saving reviewed AI question: " . mysqli_error($conn);
                        break;
                    }
                    $questions[] = $qid;
                }
                $selected_questions = $questions;
                $total_marks = 0;
                foreach ($ai_preview_questions as $question) {
                    $total_marks += (int)$question["marks"];
                }
                if ((int)$pass_marks > $total_marks) {
                    $pass_marks = $total_marks;
                }
            }
        } elseif ($exam_mode == 'Adaptive') {
            require_once("../app/ai/QuestionGenerator.php");
            $generator = new QuestionGenerator();
            $total_marks = 0;
            foreach ($questions as $qid) {
                $qid = clean_data($qid);
                $mres = mysqli_query($conn, "SELECT marks FROM questions WHERE id='$qid'");
                if ($mrow = mysqli_fetch_assoc($mres)) {
                    $total_marks += $mrow["marks"];
                }
            }
        }
    }

    if (!$form_error && $should_save_exam) {
        if ($edit_id != "") {
            $saved = mysqli_query($conn, "UPDATE exams SET exam_title='$exam_title', subject_id='$subject_id', exam_date='$exam_date', duration='$duration', total_marks='$total_marks', pass_marks='$pass_marks', status='$status', exam_mode='$exam_mode', max_questions=$max_questions WHERE id='$edit_id'");
            if ($saved) {
                mysqli_query($conn, "DELETE FROM exam_questions WHERE exam_id='$edit_id'");
                $exam_id = $edit_id;
                $message = "Exam updated successfully";
            } else {
                $form_error = "Update failed: " . mysqli_error($conn);
            }
        } else {
            $saved = mysqli_query($conn, "INSERT INTO exams (exam_title,subject_id,exam_date,duration,total_marks,pass_marks,status,exam_mode,max_questions) VALUES ('$exam_title','$subject_id','$exam_date','$duration','$total_marks','$pass_marks','$status','$exam_mode',$max_questions)");
            if ($saved) {
                $exam_id = mysqli_insert_id($conn);
                notify_user("student", null, "New Exam Created", $exam_title . " is available for students.", "exams.php");
                notify_user("admin", null, "New Exam Created", $exam_title . " was created successfully.", "exams.php");
                $message = "Exam created successfully";
            } else {
                $form_error = "Insert failed: " . mysqli_error($conn);
            }
        }
    }

    if (!$form_error && $should_save_exam) {
        $linked = 0;
        foreach ($questions as $qid) {
            $qid = clean_data($qid);
            if (mysqli_query($conn, "INSERT IGNORE INTO exam_questions (exam_id,question_id) VALUES ('$exam_id','$qid')")) {
                $linked += mysqli_affected_rows($conn);
            }
        }
        if ($exam_mode == 'Adaptive' && $linked != $max_questions) {
            $form_error = "Only $linked of $max_questions generated questions were attached to the exam.";
        }
    }

    if (!$form_error && $should_save_exam) {
        flash_script($message, "exams.php", "success");
        header("Location: exams.php");
        exit;
    }

    if ($form_error) {
        flash_script($form_error, "", "error");
    }
}

include("../app/layouts/admin_header.php");
$subjects = mysqli_query($conn, "SELECT * FROM subjects WHERE status='Active' ORDER BY subject_name");
$subject_cards = mysqli_query($conn, "SELECT s.*, COUNT(q.id) AS question_count FROM subjects s LEFT JOIN questions q ON q.subject_id=s.id WHERE s.status='Active' GROUP BY s.id ORDER BY s.id ASC");
$search = isset($_GET["search"]) ? clean_data($_GET["search"]) : "";
$exams_result = mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE e.exam_title LIKE '%$search%' OR s.subject_name LIKE '%$search%' ORDER BY e.id ASC");
$exams_data = [];
if ($exams_result) {
    while ($e_row = mysqli_fetch_assoc($exams_result)) {
        $eq_ids = [];
        $eq_res = mysqli_query($conn, "SELECT question_id FROM exam_questions WHERE exam_id='" . $e_row["id"] . "'");
        while ($eq = mysqli_fetch_assoc($eq_res)) {
            $eq_ids[] = $eq["question_id"];
        }
        $e_row["edit_questions"] = $eq_ids;
        $e_row["question_count"] = count($eq_ids);
        $exams_data[] = $e_row;
    }
}

// ---- Exam list filters (validated; display list only — builder JS keeps full $exams_data) ----
$ex_subject = isset($_GET["subject"]) ? (int)$_GET["subject"] : 0;
$ex_mode = isset($_GET["mode"]) ? clean_data($_GET["mode"]) : "";
if (!in_array($ex_mode, array("", "Standard", "Adaptive"), true)) { $ex_mode = ""; }
$ex_status = isset($_GET["exam_status"]) ? clean_data($_GET["exam_status"]) : "";
if (!in_array($ex_status, array("", "Active", "Inactive"), true)) { $ex_status = ""; }
$ex_page = max(1, (int)($_GET["page"] ?? 1));
$ex_per = 10;

$ex_conds = array(); $ex_types = ""; $ex_params = array();
if ($search !== "") {
    $ex_like = "%" . $search . "%";
    $ex_conds[] = "(e.exam_title LIKE ? OR s.subject_name LIKE ?)";
    $ex_types .= "ss"; array_push($ex_params, $ex_like, $ex_like);
}
if ($ex_subject > 0) { $ex_conds[] = "e.subject_id = ?"; $ex_types .= "i"; $ex_params[] = $ex_subject; }
if ($ex_mode !== "") { $ex_conds[] = "e.exam_mode = ?"; $ex_types .= "s"; $ex_params[] = $ex_mode; }
if ($ex_status !== "") { $ex_conds[] = "e.status = ?"; $ex_types .= "s"; $ex_params[] = $ex_status; }
$ex_where = $ex_conds ? ("WHERE " . implode(" AND ", $ex_conds)) : "";

function admin_ex_all($conn, $sql, $types, $params)
{
    $out = array();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $out; }
    if ($types !== "" && !empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    mysqli_stmt_close($stmt);
    return $out;
}

$ex_count_rows = admin_ex_all($conn, "SELECT COUNT(DISTINCT e.id) AS c FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id $ex_where", $ex_types, $ex_params);
$ex_total = $ex_count_rows ? (int)$ex_count_rows[0]["c"] : 0;
$ex_pages = max(1, (int)ceil($ex_total / $ex_per));
if ($ex_page > $ex_pages) { $ex_page = $ex_pages; }
$ex_offset = ($ex_page - 1) * $ex_per;
$exams_list = admin_ex_all($conn, "SELECT e.*, s.subject_name, COUNT(DISTINCT eq.question_id) AS question_count, COUNT(r.id) AS attempt_count FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id LEFT JOIN exam_questions eq ON eq.exam_id=e.id LEFT JOIN results r ON r.exam_id=e.id $ex_where GROUP BY e.id, s.subject_name ORDER BY e.id DESC LIMIT $ex_per OFFSET $ex_offset", $ex_types, $ex_params);

// ---- Global exam stats (real counts) ----
$ex_stat = admin_ex_all($conn, "SELECT COUNT(*) AS t, SUM(status='Active') AS a, SUM(exam_mode='Adaptive') AS ad, SUM(exam_mode='Standard' OR exam_mode IS NULL OR exam_mode='') AS st FROM exams", "", array());
$ex_stat_total = $ex_stat ? (int)$ex_stat[0]["t"] : 0;
$ex_stat_active = $ex_stat ? (int)($ex_stat[0]["a"] ?? 0) : 0;
$ex_stat_adaptive = $ex_stat ? (int)($ex_stat[0]["ad"] ?? 0) : 0;
$ex_stat_standard = $ex_stat ? (int)($ex_stat[0]["st"] ?? 0) : 0;
$ex_att = admin_ex_all($conn, "SELECT COUNT(*) AS c FROM results", "", array());
$ex_stat_attempts = $ex_att ? (int)$ex_att[0]["c"] : 0;

$ex_subject_opts = array();
$ex_subq = mysqli_query($conn, "SELECT id, subject_name FROM subjects WHERE status='Active' ORDER BY subject_name ASC");
if ($ex_subq) { while ($esor = mysqli_fetch_assoc($ex_subq)) { $ex_subject_opts[] = $esor; } }

$ex_params_arr = array("search" => $search, "subject" => $ex_subject > 0 ? $ex_subject : "", "mode" => $ex_mode, "exam_status" => $ex_status);
$ex_chips = array();
if ($search !== "") { $ex_chips[] = array("Search: " . $search, "exams.php" . admin_qs($ex_params_arr, array("search" => "", "epage" => 1))); }
if ($ex_subject > 0) {
    $ex_sn = "Subject #" . $ex_subject;
    foreach ($ex_subject_opts as $eso) { if ((int)$eso["id"] === $ex_subject) { $ex_sn = $eso["subject_name"]; break; } }
    $ex_chips[] = array("Subject: " . $ex_sn, "exams.php" . admin_qs($ex_params_arr, array("subject" => "", "epage" => 1)));
}
if ($ex_mode !== "") { $ex_chips[] = array("Mode: " . $ex_mode, "exams.php" . admin_qs($ex_params_arr, array("mode" => "", "epage" => 1))); }
if ($ex_status !== "") { $ex_chips[] = array("Status: " . $ex_status, "exams.php" . admin_qs($ex_params_arr, array("exam_status" => "", "epage" => 1))); }
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item active">Exams</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-calendar-check"></i> Exam Management</span>
                    <h1><?php echo $edit_id != "" ? "Edit Exam" : "Create Exam"; ?></h1>
                    <p>Build standard or AI-powered adaptive examinations.</p>
                </div>
                <?php if ($edit_id != "") { ?><a class="btn btn-secondary" href="exams.php">Create New</a><?php } ?>
            </div>

            <form method="post" class="form-card p-4 mb-4" id="examFormBuilder">
                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <input type="hidden" name="form_action" id="formAction" value="">
                <input type="hidden" name="ai_questions_json" id="aiQuestionsJson" value="<?php echo h(json_encode($ai_preview_questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>">
                <div class="row g-3">
                    <div class="col-md-4 form-floating"><input id="examTitle" name="exam_title" value="<?php echo $exam_title; ?>" class="form-control" placeholder="Exam Title" required><label for="examTitle">Exam Title</label></div>
                    <div class="col-md-4 form-floating"><select id="examSubject" name="subject_id" class="form-select" required><option value="">Select Subject</option><?php while ($sub = mysqli_fetch_assoc($subjects)) { ?><option value="<?php echo $sub["id"]; ?>" <?php if ($subject_id == $sub["id"]) { echo "selected"; } ?>><?php echo $sub["subject_name"]; ?></option><?php } ?></select><label for="examSubject">Subject</label></div>
                    <div class="col-md-4 form-floating"><input id="examDate" type="date" name="exam_date" value="<?php echo $exam_date; ?>" class="form-control" required><label for="examDate">Exam Date</label></div>
                    <div class="col-md-4 form-floating"><input id="examDuration" type="number" name="duration" value="<?php echo $duration; ?>" class="form-control" min="1" required><label for="examDuration">Duration Minutes</label></div>
                    <div class="col-md-4 form-floating"><input id="passMarks" type="number" name="pass_marks" value="<?php echo $pass_marks; ?>" class="form-control" min="1" required><label for="passMarks">Pass Marks</label></div>
                    <div class="col-md-4 form-floating"><select id="examStatus" name="status" class="form-select"><option <?php if ($status == "Active") { echo "selected"; } ?>>Active</option><option <?php if ($status == "Inactive") { echo "selected"; } ?>>Inactive</option></select><label for="examStatus">Status</label></div>
                    <div class="col-md-4 form-floating">
                        <select id="examMode" name="exam_mode" class="form-select">
                            <option value="Standard" <?php if ($exam_mode == 'Standard') echo 'selected'; ?>>Standard (Manual Questions)</option>
                            <option value="Adaptive" <?php if ($exam_mode == 'Adaptive') echo 'selected'; ?>>Adaptive (AI-Powered)</option>
                        </select>
                        <label for="examMode">Exam Mode</label>
                        <small class="text-muted">Adaptive mode uses AI to select questions based on student performance</small>
                    </div>
                    <div class="col-md-4 form-floating" id="maxQuestionsWrap">
                        <input id="maxQuestions" type="number" name="max_questions" value="<?php echo (int)$max_questions; ?>" class="form-control" min="1" max="100">
                        <label for="maxQuestions">Total Number of Questions</label>
                    </div>
                </div>

                <div id="aiPreviewSection" class="mt-4" style="<?php echo ($ai_preview_active && count($ai_preview_questions) > 0) ? "" : "display:none;"; ?>">
                    <hr>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Question Preview</h5>
                            <p class="text-muted small mb-0">Review, edit, delete, or regenerate AI-generated MCQs before creating the exam.</p>
                        </div>
                        <span class="badge bg-info text-dark"><i class="bi bi-robot me-1"></i>AI Generated</span>
                    </div>
                    <div id="aiQuestionPreviewList" class="ai-preview-list"></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <small class="text-muted" id="aiPreviewCount"></small>
                        <button type="button" class="btn btn-gradient no-loader" id="confirmCreateExamBtn"><i class="bi bi-check2-circle me-1"></i>Create Exam</button>
                    </div>
                </div>

                <div id="standardModeOptions">
                <hr>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">Select Questions by Subject</h5>
                    <span class="badge bg-primary">Use subject cards</span>
                </div>
                <div class="standard-exam-guide mb-3">
                    <div class="standard-exam-guide-icon"><i class="bi bi-book"></i></div>
                    <div>
                        <strong>Select a Subject Card below to load available questions.</strong>
                        <p class="mb-0">After selecting a subject, choose the questions you want to include in this exam. Questions will appear automatically after selecting a subject.</p>
                    </div>
                    <i class="bi bi-arrow-down-circle standard-exam-guide-arrow"></i>
                </div>
                <div class="row g-3" id="subjectCardGrid">
                    <?php while ($card = mysqli_fetch_assoc($subject_cards)) { ?>
                        <div class="col-md-6 col-xl-4 subject-card-col" data-subject-id="<?php echo (int)$card["id"]; ?>">
                            <button type="button" class="subject-card admin-exam-subject-card text-start w-100 p-3 js-load-subject-questions" data-subject-id="<?php echo (int)$card["id"]; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="admin-card-icon me-3"><i class="bi bi-ui-checks-grid"></i></span>
                                    <div><h5 class="mb-1"><?php echo $card["subject_name"]; ?></h5><small class="text-muted"><?php echo $card["subject_code"]; ?></small></div>
                                    <span class="badge bg-success"><?php echo $card["question_count"]; ?> Questions</span>
                                </div>
                                <p class="small text-muted mb-0 mt-3">Select questions from this subject.</p>
                            </button>
                        </div>
                    <?php } ?>
                </div>
                <div id="standardSelectedQuestions">
                    <?php foreach ($selected_questions as $selected_qid) { ?>
                        <input type="hidden" name="questions[]" value="<?php echo (int)$selected_qid; ?>">
                    <?php } ?>
                </div>
                <div class="subject-question-panel mt-4" id="subjectQuestionPanel" style="display:none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="fw-bold mb-0" id="subjectQuestionPanelTitle">Available Questions</h6>
                        <span class="badge bg-primary" id="subjectQuestionPanelCount">0 selected</span>
                    </div>
                    <div id="subjectQuestionList"></div>
                </div>
                </div> <!-- end standardModeOptions -->
                <button class="btn btn-gradient mt-4" type="submit" id="mainExamSubmit"><?php echo $edit_id != "" ? "Update Exam" : ($exam_mode == "Adaptive" ? "Generate Questions" : "Create Exam"); ?></button>
            </form>

            <script>
            var aiPreviewQuestions = <?php echo json_encode($ai_preview_questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            var editExamData = <?php echo json_encode($exams_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            function toggleAdaptiveFields(selectEl, wrapId) {
                var isAd = selectEl.value === 'Adaptive';
                document.getElementById('standardModeOptions').style.display = isAd ? 'none' : 'block';
                var wrap = document.getElementById(wrapId);
                if (wrap) wrap.style.display = isAd ? 'block' : 'none';
                var preview = document.getElementById('aiPreviewSection');
                var submit = document.getElementById('mainExamSubmit');
                if (preview && !isAd) preview.style.display = 'none';
                if (submit) submit.innerHTML = <?php echo $edit_id != "" ? "'Update Exam'" : "isAd ? 'Generate Questions' : 'Create Exam'"; ?>;
            }
            document.getElementById('examMode').addEventListener('change', function() {
                toggleAdaptiveFields(this, 'maxQuestionsWrap');
            });
            // Set initial state
            toggleAdaptiveFields(document.getElementById('examMode'), 'maxQuestionsWrap');

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : value;
                return div.innerHTML;
            }

            function syncAiPreviewFromDom() {
                var cards = document.querySelectorAll('.ai-preview-question');
                aiPreviewQuestions = Array.prototype.map.call(cards, function(card) {
                    return {
                        question: card.querySelector('[data-ai-field="question"]').value,
                        option_a: card.querySelector('[data-ai-field="option_a"]').value,
                        option_b: card.querySelector('[data-ai-field="option_b"]').value,
                        option_c: card.querySelector('[data-ai-field="option_c"]').value,
                        option_d: card.querySelector('[data-ai-field="option_d"]').value,
                        correct_answer: card.querySelector('[data-ai-field="correct_answer"]').value,
                        difficulty: card.querySelector('[data-ai-field="difficulty"]').value,
                        language: 'English',
                        marks: parseInt(card.querySelector('[data-ai-field="marks"]').value || '1', 10)
                    };
                });
                document.getElementById('aiQuestionsJson').value = JSON.stringify(aiPreviewQuestions);
                document.getElementById('maxQuestions').value = aiPreviewQuestions.length || document.getElementById('maxQuestions').value;
                document.getElementById('aiPreviewCount').textContent = 'Total Questions: ' + aiPreviewQuestions.length;
            }

            function renderAiPreview() {
                var list = document.getElementById('aiQuestionPreviewList');
                if (!list) return;
                list.innerHTML = '';
                aiPreviewQuestions.forEach(function(q, index) {
                    var card = document.createElement('div');
                    var correct = q.correct_answer || 'A';
                    card.className = 'ai-preview-question mb-3';
                    card.innerHTML = '<div class="ai-preview-card-header">' +
                        '<div class="ai-preview-title-wrap"><span class="ai-preview-number">Question ' + (index + 1) + '</span><span class="premium-badge premium-badge-ai"><i class="bi bi-robot"></i>AI Generated</span></div>' +
                        '<div class="ai-preview-header-badges"><span class="premium-badge premium-badge-muted ai-difficulty-badge">' + escapeHtml(q.difficulty || 'Medium') + '</span><span class="premium-badge premium-badge-primary ai-marks-badge">' + escapeHtml(q.marks || 1) + ' Marks</span><button type="button" class="ai-collapse-question" aria-label="Collapse question preview"><i class="bi bi-x-lg"></i></button></div>' +
                        '</div>' +
                        '<div class="ai-preview-card-body">' +
                        '<div class="ai-question-highlight"><label class="form-label small fw-semibold">Question</label><textarea class="form-control" rows="2" data-ai-field="question">' + escapeHtml(q.question) + '</textarea></div>' +
                        '<div class="ai-option-grid">' +
                        '<label class="ai-option-card" data-option-letter="A"><span class="ai-option-letter">A</span><textarea class="form-control" rows="2" data-ai-field="option_a">' + escapeHtml(q.option_a) + '</textarea><span class="ai-correct-pill"><i class="bi bi-check-circle-fill"></i>Correct</span></label>' +
                        '<label class="ai-option-card" data-option-letter="B"><span class="ai-option-letter">B</span><textarea class="form-control" rows="2" data-ai-field="option_b">' + escapeHtml(q.option_b) + '</textarea><span class="ai-correct-pill"><i class="bi bi-check-circle-fill"></i>Correct</span></label>' +
                        '<label class="ai-option-card" data-option-letter="C"><span class="ai-option-letter">C</span><textarea class="form-control" rows="2" data-ai-field="option_c">' + escapeHtml(q.option_c) + '</textarea><span class="ai-correct-pill"><i class="bi bi-check-circle-fill"></i>Correct</span></label>' +
                        '<label class="ai-option-card" data-option-letter="D"><span class="ai-option-letter">D</span><textarea class="form-control" rows="2" data-ai-field="option_d">' + escapeHtml(q.option_d) + '</textarea><span class="ai-correct-pill"><i class="bi bi-check-circle-fill"></i>Correct</span></label>' +
                        '</div>' +
                        '<div class="ai-info-row">' +
                        '<label class="ai-info-field"><span>Difficulty</span><select class="form-select" data-ai-field="difficulty"><option>Easy</option><option>Medium</option><option>Hard</option></select></label>' +
                        '<label class="ai-info-field"><span>Marks</span><input class="form-control" type="number" min="1" data-ai-field="marks" value="' + escapeHtml(q.marks || 1) + '"></label>' +
                        '<label class="ai-info-field"><span>Correct Answer</span><select class="form-select" data-ai-field="correct_answer"><option>A</option><option>B</option><option>C</option><option>D</option></select></label>' +
                        '</div>' +
                        '<div class="ai-action-toolbar">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary ai-edit-question"><i class="bi bi-pencil me-1"></i>Edit</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger ai-delete-question"><i class="bi bi-trash me-1"></i>Delete</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary ai-regenerate-question"><i class="bi bi-arrow-repeat me-1"></i>Regenerate</button>' +
                        '</div></div>';
                    list.appendChild(card);
                    card.querySelector('[data-ai-field="correct_answer"]').value = correct;
                    card.querySelector('[data-ai-field="difficulty"]').value = q.difficulty || 'Medium';
                    updateAiCorrectOption(card);
                });
                document.getElementById('aiPreviewSection').style.display = aiPreviewQuestions.length ? 'block' : 'none';
                syncAiPreviewFromDom();
            }

            function updateAiCorrectOption(card) {
                var answer = card.querySelector('[data-ai-field="correct_answer"]').value;
                card.querySelectorAll('.ai-option-card').forEach(function(option) {
                    option.classList.toggle('is-correct', option.getAttribute('data-option-letter') === answer);
                });
            }

            function setAiGeneratingLoading(isLoading) {
                var list = document.getElementById('aiQuestionPreviewList');
                var section = document.getElementById('aiPreviewSection');
                var submit = document.getElementById('mainExamSubmit');
                if (section) section.style.display = 'block';
                if (submit) {
                    submit.disabled = isLoading;
                    submit.innerHTML = isLoading ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating AI Questions...' : 'Generate Questions';
                }
                if (isLoading && list) {
                    list.innerHTML = '<div class="ai-generation-loader"><div class="spinner-border text-primary mb-3" role="status"></div><div class="fw-bold">Generating AI Questions...</div></div>';
                }
            }

            function showExamToast(message, icon) {
                if (typeof showAppToast === 'function') {
                    showAppToast(message, icon || 'success');
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2200,
                        timerProgressBar: true,
                        width: 'auto'
                    }).fire({ icon: icon || 'success', title: message });
                }
            }

            function extractAiQuestionsFromHtml(html) {
                var match = html.match(/var aiPreviewQuestions = ([\s\S]*?);\s*var editExamData/);
                if (!match) return null;
                return JSON.parse(match[1]);
            }

            function generateAiPreviewWithoutReload(form) {
                setAiGeneratingLoading(true);
                var formData = new FormData(form);
                formData.set('form_action', 'generate_ai_preview');
                fetch('exams.php', { method: 'POST', body: formData })
                    .then(function(response) {
                        if (!response.ok) throw new Error('Could not generate AI questions.');
                        return response.text();
                    })
                    .then(function(html) {
                        var questions = extractAiQuestionsFromHtml(html);
                        if (!questions || !questions.length) throw new Error('AI preview was not returned. Please check the required fields and try again.');
                        aiPreviewQuestions = questions;
                        renderAiPreview();
                        setAiGeneratingLoading(false);
                        document.getElementById('formAction').value = '';
                        showExamToast('Questions generated successfully');
                        document.getElementById('aiPreviewSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    })
                    .catch(function(error) {
                        setAiGeneratingLoading(false);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Generation failed', error.message, 'error');
                        } else {
                            alert(error.message);
                        }
                    });
            }

            renderAiPreview();

            var selectedQuestionIds = new Set(<?php echo json_encode(array_values(array_map("intval", $selected_questions))); ?>);
            var loadedSubjectId = null;
            var loadingSubjectId = null;
            var subjectQuestionCache = {};
            var subjectLoadController = null;

            function renderSelectedQuestionInputs() {
                var holder = document.getElementById('standardSelectedQuestions');
                if (!holder) return;
                holder.innerHTML = '';
                selectedQuestionIds.forEach(function(qid) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'questions[]';
                    input.value = qid;
                    holder.appendChild(input);
                });
                var count = document.getElementById('subjectQuestionPanelCount');
                if (count) count.textContent = selectedQuestionIds.size + ' selected';
            }

            function selectedQuestionQuery() {
                return Array.from(selectedQuestionIds).join(',');
            }

            function setActiveSubjectCard(card) {
                document.querySelectorAll('.js-load-subject-questions').forEach(function(btn) {
                    btn.classList.toggle('active', btn === card);
                });
            }

            function filterSubjectCards() {
                var selectedSubject = document.getElementById('examSubject').value;
                document.querySelectorAll('.subject-card-col').forEach(function(col) {
                    var isVisible = !selectedSubject || col.getAttribute('data-subject-id') === selectedSubject;
                    col.style.display = isVisible ? '' : 'none';
                });

                if (loadedSubjectId && selectedSubject && loadedSubjectId !== selectedSubject) {
                    loadedSubjectId = null;
                    document.getElementById('subjectQuestionPanel').style.display = 'none';
                    document.getElementById('subjectQuestionList').innerHTML = '';
                    setActiveSubjectCard(null);
                }
            }

            var currentExamSubjectFilter = document.getElementById('examSubject').value;
            document.getElementById('examSubject').addEventListener('change', function() {
                if (this.value !== currentExamSubjectFilter) {
                    selectedQuestionIds.clear();
                    renderSelectedQuestionInputs();
                    loadedSubjectId = null;
                    loadingSubjectId = null;
                    document.getElementById('subjectQuestionPanel').style.display = 'none';
                    document.getElementById('subjectQuestionList').innerHTML = '';
                    setActiveSubjectCard(null);
                    currentExamSubjectFilter = this.value;
                }
                filterSubjectCards();
            });
            filterSubjectCards();

            function showSubjectLoading(title) {
                document.getElementById('subjectQuestionPanel').style.display = 'block';
                document.getElementById('subjectQuestionPanelTitle').textContent = title || 'Loading Questions';
                document.getElementById('subjectQuestionList').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary mb-2" role="status"></div><p class="text-muted mb-0">Loading questions...</p></div>';
            }

            function finishSubjectRender(subjectId, html, title) {
                loadedSubjectId = subjectId;
                loadingSubjectId = null;
                subjectQuestionCache[subjectId] = html;
                document.getElementById('subjectQuestionPanel').style.display = 'block';
                document.getElementById('subjectQuestionPanelTitle').textContent = title;
                document.getElementById('subjectQuestionList').innerHTML = html;
                document.querySelectorAll('#subjectQuestionList .standard-question-checkbox').forEach(function(input) {
                    input.checked = selectedQuestionIds.has(parseInt(input.value, 10));
                    var questionCard = input.closest('.standard-question-card');
                    if (questionCard) questionCard.classList.toggle('is-selected', input.checked);
                });
                renderSelectedQuestionInputs();
                document.getElementById('subjectQuestionPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function editSelectedQuestionQuery(exam) {
                return (exam && exam.edit_questions ? exam.edit_questions : []).map(function(qid) { return String(qid); }).join(',');
            }

            function updateEditQuestionCount() {
                var count = document.getElementById('editQuestionPanelCount');
                var selected = document.querySelectorAll('#editQuestionList .edit-question-checkbox:checked').length;
                var maxField = document.getElementById('editMaxQ');
                var label = selected + (selected === 1 ? ' Question' : ' Questions');
                if (count) count.textContent = label;
                if (maxField) maxField.value = selected;
            }

            function setEditQuestionCardsState() {
                document.querySelectorAll('#editQuestionList .edit-question-checkbox').forEach(function(input) {
                    var card = input.closest('.edit-question-card');
                    if (card) card.classList.toggle('is-selected', input.checked);
                });
                updateEditQuestionCount();
            }

            function loadEditSubjectQuestions(subjectId, selectedIds) {
                var list = document.getElementById('editQuestionList');
                var saveBtn = document.getElementById('editExamSaveBtn');
                if (!list) return;
                if (saveBtn) saveBtn.disabled = true;
                if (!subjectId) {
                    list.innerHTML = '<div class="empty-state text-center">Select a subject to load questions.</div>';
                    updateEditQuestionCount();
                    if (saveBtn) saveBtn.disabled = false;
                    return;
                }
                list.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary mb-2" role="status"></div><p class="text-muted mb-0">Loading subject questions...</p></div>';
                fetch('exams.php?ajax_action=load_subject_questions&context=edit_modal&subject_id=' + encodeURIComponent(subjectId) + '&selected_ids=' + encodeURIComponent(selectedIds || ''))
                    .then(function(response) {
                        if (!response.ok) throw new Error('Could not load questions.');
                        return response.text();
                    })
                    .then(function(html) {
                        list.innerHTML = html;
                        setEditQuestionCardsState();
                        if (saveBtn) saveBtn.disabled = false;
                    })
                    .catch(function() {
                        list.innerHTML = '<div class="alert alert-danger mb-0">Could not load questions. Please try again.</div>';
                        updateEditQuestionCount();
                        if (saveBtn) saveBtn.disabled = false;
                    });
            }

            document.getElementById('standardModeOptions').addEventListener('click', function(event) {
                var card = event.target.closest('.js-load-subject-questions');
                if (!card) return;
                event.preventDefault();
                event.stopPropagation();

                var subjectId = card.getAttribute('data-subject-id');
                var selectedSubject = document.getElementById('examSubject').value;
                if (selectedSubject && subjectId !== selectedSubject) return;
                var title = card.querySelector('h5') ? card.querySelector('h5').textContent + ' Questions' : 'Available Questions';
                setActiveSubjectCard(card);

                if (loadedSubjectId === subjectId && subjectQuestionCache[subjectId]) {
                    finishSubjectRender(subjectId, subjectQuestionCache[subjectId], title);
                    return;
                }
                if (loadingSubjectId === subjectId) {
                    return;
                }

                loadingSubjectId = subjectId;
                showSubjectLoading(title);

                if (subjectLoadController) {
                    subjectLoadController.abort();
                }
                subjectLoadController = new AbortController();

                fetch('exams.php?ajax_action=load_subject_questions&subject_id=' + encodeURIComponent(subjectId) + '&selected_ids=' + encodeURIComponent(selectedQuestionQuery()), {
                    signal: subjectLoadController.signal
                })
                    .then(function(response) {
                        if (!response.ok) throw new Error('Could not load questions.');
                        return response.text();
                    })
                    .then(function(html) {
                        finishSubjectRender(subjectId, html, title);
                    })
                    .catch(function(error) {
                        if (error.name === 'AbortError') return;
                        loadingSubjectId = null;
                        document.getElementById('subjectQuestionList').innerHTML = '<div class="alert alert-danger mb-0">Could not load questions. Please try again.</div>';
                    });
            });

            document.getElementById('subjectQuestionList').addEventListener('change', function(event) {
                if (!event.target.classList.contains('standard-question-checkbox')) return;
                var qid = parseInt(event.target.value, 10);
                if (event.target.checked) {
                    selectedQuestionIds.add(qid);
                } else {
                    selectedQuestionIds.delete(qid);
                }
                var questionCard = event.target.closest('.standard-question-card');
                if (questionCard) questionCard.classList.toggle('is-selected', event.target.checked);
                renderSelectedQuestionInputs();
            });

            document.getElementById('examFormBuilder').addEventListener('submit', function(event) {
                var mode = document.getElementById('examMode').value;
                renderSelectedQuestionInputs();
                if (mode === 'Adaptive' && !document.querySelector('input[name="edit_id"]').value) {
                    syncAiPreviewFromDom();
                    if (document.getElementById('formAction').value !== 'create_exam_confirmed') {
                        document.getElementById('formAction').value = 'generate_ai_preview';
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        generateAiPreviewWithoutReload(this);
                    }
                }
            });

            document.getElementById('aiQuestionPreviewList').addEventListener('input', syncAiPreviewFromDom);
            document.getElementById('aiQuestionPreviewList').addEventListener('change', function(event) {
                var card = event.target.closest('.ai-preview-question');
                if (card && event.target.matches('[data-ai-field="correct_answer"]')) {
                    updateAiCorrectOption(card);
                }
                syncAiPreviewFromDom();
            });
            document.getElementById('aiQuestionPreviewList').addEventListener('click', function(event) {
                var card = event.target.closest('.ai-preview-question');
                if (!card) return;
                if (event.target.closest('.ai-collapse-question')) {
                    card.classList.toggle('is-collapsed');
                    return;
                }
                if (event.target.closest('.ai-delete-question')) {
                    syncAiPreviewFromDom();
                    Swal.fire({
                        title: 'Delete question?',
                        text: 'This AI-generated question will be removed from the preview.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Delete',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#dc2626'
                    }).then(function(result) {
                        if (!result.isConfirmed) return;
                        aiPreviewQuestions.splice(Array.prototype.indexOf.call(document.querySelectorAll('.ai-preview-question'), card), 1);
                        renderAiPreview();
                        showExamToast('Question deleted');
                    });
                }
                if (event.target.closest('.ai-edit-question')) {
                    card.querySelector('[data-ai-field="question"]').focus();
                }
                if (event.target.closest('.ai-regenerate-question')) {
                    syncAiPreviewFromDom();
                    var index = Array.prototype.indexOf.call(document.querySelectorAll('.ai-preview-question'), card);
                    var currentQuestion = aiPreviewQuestions[index] || {};
                    var button = event.target.closest('.ai-regenerate-question');
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Regenerating';
                    var body = new URLSearchParams({
                        ajax_action: 'regenerate_ai_question',
                        subject_id: document.getElementById('examSubject').value,
                        difficulty: currentQuestion.difficulty || 'Medium',
                        marks: currentQuestion.marks || 1,
                        current_question: currentQuestion.question || '',
                        current_options: JSON.stringify({
                            option_a: currentQuestion.option_a || '',
                            option_b: currentQuestion.option_b || '',
                            option_c: currentQuestion.option_c || '',
                            option_d: currentQuestion.option_d || ''
                        }),
                        question_id: currentQuestion.id || index,
                        generation_nonce: Date.now() + '_' + Math.random().toString(36).slice(2),
                        existing_questions: JSON.stringify(aiPreviewQuestions)
                    });
                    fetch('exams.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Regeneration failed');
                            aiPreviewQuestions[index] = data.question;
                            renderAiPreview();
                            showExamToast('Question regenerated successfully.');
                        })
                        .catch(function(error) { Swal.fire('Regeneration failed', error.message, 'error'); })
                        .finally(function() {
                            button.disabled = false;
                            button.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Regenerate';
                        });
                }
            });

            document.getElementById('confirmCreateExamBtn').addEventListener('click', function() {
                syncAiPreviewFromDom();
                Swal.fire({
                    title: 'You are about to create this exam.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Create Exam',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        document.getElementById('formAction').value = 'create_exam_confirmed';
                        document.getElementById('examFormBuilder').submit();
                    }
                });
            });

            document.addEventListener('click', function(event) {
                var button = event.target.closest('.js-open-edit-exam');
                if (!button) return;
                event.preventDefault();
                event.stopPropagation();
                var examId = button.getAttribute('data-exam-id');
                var exam = editExamData.find(function(item) { return String(item.id) === String(examId); });
                var modal = document.getElementById('editExamModal');
                if (!exam || !modal) return;

                document.getElementById('editExamId').value = exam.id || '';
                document.getElementById('editExamTitle').value = exam.exam_title || '';
                document.getElementById('editExamSubject').value = exam.subject_id || '';
                document.getElementById('editExamDate').value = exam.exam_date || '';
                document.getElementById('editExamDuration').value = exam.duration || '';
                document.getElementById('editPassMarks').value = exam.pass_marks || '';
                document.getElementById('editExamStatus').value = exam.status || 'Active';
                document.getElementById('editExamMode').value = exam.exam_mode || 'Standard';
                document.getElementById('editMaxQ').value = (exam.edit_questions || []).length || exam.max_questions || 10;
                document.getElementById('editExamSubjectHidden').value = exam.subject_id || '';

                loadEditSubjectQuestions(exam.subject_id || '', editSelectedQuestionQuery(exam));

                var maxWrap = document.getElementById('editMaxQ').closest('.form-floating');
                if (maxWrap) maxWrap.style.display = 'block';

                if (!modal) return;
                bootstrap.Modal.getOrCreateInstance(modal).show();
            });

            document.addEventListener('change', function(event) {
                if (event.target.id !== 'editExamMode') return;
                var wrap = document.getElementById('editMaxQ');
                if (wrap) wrap.closest('.form-floating').style.display = 'block';
            });

            document.getElementById('editQuestionList').addEventListener('click', function(event) {
                var card = event.target.closest('.edit-question-card');
                if (!card) return;
                var input = card.querySelector('.edit-question-checkbox');
                if (!input || input.disabled) return;
                event.preventDefault();
                event.stopPropagation();
                input.checked = !input.checked;
                setEditQuestionCardsState();
            }, true);

            document.getElementById('editQuestionList').addEventListener('change', function(event) {
                if (!event.target.classList.contains('edit-question-checkbox')) return;
                setEditQuestionCardsState();
            });
            </script>

            <div class="sp-page-head sp-fade-up mt-5">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-calendar-check"></i> Exam Management</span>
                    <h1>All Exams</h1>
                    <p>Browse, filter, inspect and manage every examination in the system.</p>
                </div>
            </div>

            <?php
            admin_stat_cards(array(
                array("Total Exams", $ex_stat_total, "bi-calendar-check-fill", "blue", "All examinations"),
                array("Active Exams", $ex_stat_active, "bi-check-circle-fill", "green", "Open for students"),
                array("Adaptive Exams", $ex_stat_adaptive, "bi-robot", "violet", "AI-powered"),
                array("Standard Exams", $ex_stat_standard, "bi-gear-fill", "cyan", "Manual questions"),
                array("Total Attempts", $ex_stat_attempts, "bi-award-fill", "amber", "Submitted results")
            ));
            ?>

            <form method="get" class="toolbar-card mb-4 sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="exSearch">Search Exams</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input id="exSearch" name="search" value="<?php echo h($search); ?>" class="form-control" placeholder="Exam title or subject...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="exSubject">Subject</label>
                        <select id="exSubject" name="subject" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($ex_subject_opts as $eso) { ?><option value="<?php echo (int)$eso["id"]; ?>" <?php if ($ex_subject === (int)$eso["id"]) echo "selected"; ?>><?php echo h($eso["subject_name"]); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="exMode">Mode</label>
                        <select id="exMode" name="mode" class="form-select">
                            <option value="">All Modes</option>
                            <option value="Standard" <?php if ($ex_mode === "Standard") echo "selected"; ?>>Standard</option>
                            <option value="Adaptive" <?php if ($ex_mode === "Adaptive") echo "selected"; ?>>Adaptive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="exStatus">Status</label>
                        <select id="exStatus" name="exam_status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Active" <?php if ($ex_status === "Active") echo "selected"; ?>>Active</option>
                            <option value="Inactive" <?php if ($ex_status === "Inactive") echo "selected"; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                        <a class="btn btn-outline-secondary" href="exams.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <?php admin_chips($ex_chips, "exams.php"); ?>
            </form>
            <div class="table-card admin-modern-table admin-exam-table p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle admin-table mb-0">
                    <thead><tr><th>ID</th><th>Exam</th><th>Subject</th><th>Date</th><th>Duration</th><th>Questions</th><th>Attempts</th><th>Total</th><th>Pass</th><th>Mode</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                        <?php if (!empty($exams_list)) { foreach ($exams_list as $row) {
                            $ex_id = (int)$row["id"];
                            $ex_ad = (($row["exam_mode"] ?? "Standard") === "Adaptive");
                        ?>
                            <tr class="admin-exam-row">
                                <td data-label="ID"><?php echo $ex_id; ?></td>
                                <td data-label="Exam"><strong><?php echo h($row["exam_title"]); ?></strong></td>
                                <td data-label="Subject"><span class="badge bg-primary-soft text-primary"><?php echo h($row["subject_name"]); ?></span></td>
                                <td data-label="Date"><small class="text-muted"><?php echo h($row["exam_date"]); ?></small></td>
                                <td data-label="Duration"><?php echo h($row["duration"]); ?> min</td>
                                <td data-label="Questions"><span class="badge bg-primary"><?php echo (int)$row["question_count"]; ?></span></td>
                                <td data-label="Attempts"><span class="badge bg-info text-dark"><?php echo (int)$row["attempt_count"]; ?></span></td>
                                <td data-label="Total"><?php echo h($row["total_marks"]); ?></td>
                                <td data-label="Pass"><?php echo h($row["pass_marks"]); ?></td>
                                <td data-label="Mode"><?php if ($ex_ad) { ?><span class="sp-pill is-adaptive"><i class="bi bi-robot"></i>Adaptive</span><?php } else { ?><span class="sp-pill"><i class="bi bi-gear"></i>Standard</span><?php } ?></td>
                                <td data-label="Status"><?php echo admin_badge($row["status"]); ?></td>
                                <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewExamModal<?php echo $ex_id; ?>" title="View details" aria-label="View details"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-warning js-open-edit-exam" type="button" data-exam-id="<?php echo $ex_id; ?>" title="Edit exam" aria-label="Edit exam"><i class="bi bi-pencil"></i></button>
                                    <a class="btn btn-sm btn-danger js-confirm-delete" href="exams.php?delete=<?php echo $ex_id; ?>" title="Delete exam" aria-label="Delete exam"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } } else {
                            admin_empty_row(12, "bi-calendar-check", "No exams found.", "Try changing your filters or create a new exam above.");
                        } ?>
                    </tbody>
                </table>
                </div>
                <?php admin_pagination($ex_total, $ex_page, $ex_per, $ex_params_arr, "exams.php"); ?>
            </div>

            <?php foreach ($exams_list as $row) {
                $ex_id = (int)$row["id"];
            ?>
                <div class="modal fade" id="viewExamModal<?php echo $ex_id; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content professional-modal">
                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-calendar-check me-2 text-primary"></i>Exam Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                            <div class="modal-body">
                                <h5 class="mb-1"><?php echo h($row["exam_title"]); ?></h5>
                                <p class="text-muted small mb-3"><?php echo h($row["subject_name"]); ?> &middot; <?php echo admin_badge($row["status"]); ?></p>
                                <div class="admin-detail-grid">
                                    <div class="admin-detail-item"><small>Exam Date</small><strong><?php echo h($row["exam_date"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Duration</small><strong><?php echo h($row["duration"]); ?> min</strong></div>
                                    <div class="admin-detail-item"><small>Questions Linked</small><strong><?php echo (int)$row["question_count"]; ?></strong></div>
                                    <div class="admin-detail-item"><small>Student Attempts</small><strong><?php echo (int)$row["attempt_count"]; ?></strong></div>
                                    <div class="admin-detail-item"><small>Total Marks</small><strong><?php echo h($row["total_marks"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Pass Marks</small><strong><?php echo h($row["pass_marks"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Mode</small><strong><?php echo h($row["exam_mode"] ?? "Standard"); ?></strong></div>
                                    <div class="admin-detail-item"><small>Max Questions</small><strong><?php echo h((string)($row["max_questions"] ?? "-")); ?></strong></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a class="btn btn-outline-primary btn-sm" href="results.php?search=<?php echo urlencode($row["exam_title"]); ?>"><i class="bi bi-award me-1"></i>View Results</a>
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
            <div class="modal fade" id="editExamModal" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <form method="post" class="modal-content professional-modal">
                        <div class="modal-header"><h5 class="modal-title">Edit Exam</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body scrollable-modal-body">
                            <input type="hidden" name="edit_id" id="editExamId" value="">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4 form-floating"><input id="editExamTitle" name="exam_title" class="form-control" placeholder="Exam Title" required><label for="editExamTitle">Exam Title</label></div>
                                <input type="hidden" name="subject_id" id="editExamSubjectHidden" value="">
                                <div class="col-md-4">
                                    <div class="form-floating"><select id="editExamSubject" class="form-select" required disabled><option value="">Select Subject</option><?php $modal_subjects = mysqli_query($conn, "SELECT * FROM subjects WHERE status='Active' ORDER BY subject_name"); while ($sub = mysqli_fetch_assoc($modal_subjects)) { ?><option value="<?php echo (int)$sub["id"]; ?>"><?php echo h($sub["subject_name"]); ?></option><?php } ?></select><label for="editExamSubject">Subject</label></div>
                                    <small class="edit-subject-lock-note"><i class="bi bi-lock-fill me-1"></i>Subject cannot be changed after exam creation.</small>
                                </div>
                                <div class="col-md-4 form-floating"><input id="editExamDate" type="date" name="exam_date" class="form-control" required><label for="editExamDate">Exam Date</label></div>
                                <div class="col-md-4 form-floating"><input id="editExamDuration" type="number" name="duration" class="form-control" min="1" required><label for="editExamDuration">Duration Minutes</label></div>
                                <div class="col-md-4 form-floating"><input id="editPassMarks" type="number" name="pass_marks" class="form-control" min="1" required><label for="editPassMarks">Pass Marks</label></div>
                                <div class="col-md-4 form-floating"><select id="editExamStatus" name="status" class="form-select"><option>Active</option><option>Inactive</option></select><label for="editExamStatus">Status</label></div>
                                <div class="col-md-4 form-floating">
                                    <select id="editExamMode" name="exam_mode" class="form-select">
                                        <option value="Standard">Standard</option>
                                        <option value="Adaptive">Adaptive</option>
                                    </select>
                                    <label for="editExamMode">Exam Mode</label>
                                </div>
                                <div class="col-md-4 form-floating">
                                    <input id="editMaxQ" type="number" name="max_questions" class="form-control" min="0" max="100" readonly>
                                    <label for="editMaxQ">Total Questions</label>
                                </div>
                            </div>
                            <div class="edit-question-panel">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1">Selected Questions</h6>
                                        <p class="text-muted small mb-0">Only questions from the selected exam subject are shown.</p>
                                    </div>
                                    <span class="badge bg-primary" id="editQuestionPanelCount">0 selected</span>
                                </div>
                                <div class="edit-question-grid" id="editQuestionList">
                                    <div class="text-center py-4"><div class="spinner-border text-primary mb-2" role="status"></div><p class="text-muted mb-0">Select an exam to load questions.</p></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" type="submit" id="editExamSaveBtn">Save Update</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

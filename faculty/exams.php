<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Create Exam";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

$edit_id = "";
$exam_title = "";
$subject_id = "";
$exam_course = "";
$exam_semester = "";
$exam_date = date("Y-m-d");
$duration = 20;
$pass_marks = 0;
$marks_per_question = 1;
$status = "Active";
$exam_mode = "Standard";
$max_questions = 10;
$selected_questions = array();
$form_error = "";
$ai_preview_questions = array();
$ai_preview_active = false;
$edit_attempt_count = 0;
$edit_scope_locked = false;

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

// Handle AJAX batch AI question generation (Adaptive Preview)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajax_action"]) && $_POST["ajax_action"] == "generate_adaptive_preview") {
    require_once("../app/ai/QuestionGenerator.php");
    header("Content-Type: application/json");

    $target_subject_id = (int)($_POST["subject_id"] ?? 0);
    if ($target_subject_id <= 0 || !faculty_has_subject($faculty_id, $target_subject_id)) {
        echo json_encode(array("success" => false, "message" => "Please select a valid assigned subject."));
        exit;
    }

    $total_questions = (int)($_POST["total_questions"] ?? 9);
    if ($total_questions < 1) {
        $total_questions = 9;
    }
    if ($total_questions > 100) {
        $total_questions = 100;
    }

    $generator = new QuestionGenerator();
    $subject_name = get_subject_name_by_id($conn, $target_subject_id);
    $difficulties = QuestionGenerator::calculateDifficultyDistribution($total_questions);

    $generated = $generator->generatePreviewMCQs($subject_name, $difficulties, "English", 1);

    if (count($generated) < $total_questions) {
        $seen = $generated;
        for ($i = count($generated); $i < $total_questions; $i++) {
            $diff = $difficulties[$i] ?? "Medium";
            $question = $generator->generateMCQ($subject_name, "", $diff, "English", 1, false, $seen, "", array(), "preview_" . time() . "_" . $i);
            if (!$question) {
                break;
            }
            $generated[] = $question;
            $seen[] = $question;
        }
    }

    if (empty($generated)) {
        echo json_encode(array(
            "success" => false,
            "message" => $generator->getLastError() ?: "AI could not generate questions for this subject."
        ));
        exit;
    }

    $counts = array("Easy" => 0, "Medium" => 0, "Hard" => 0);
    foreach ($generated as $gq) {
        $d = $gq["difficulty"] ?? "Medium";
        if (isset($counts[$d])) {
            $counts[$d]++;
        }
    }

    echo json_encode(array(
        "success" => true,
        "questions" => $generated,
        "total" => count($generated),
        "distribution" => $counts,
        "subject_name" => $subject_name
    ));
    exit;
}

// Handle AJAX AI question regeneration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajax_action"]) && $_POST["ajax_action"] == "regenerate_ai_question") {
    require_once("../app/ai/QuestionGenerator.php");
    header("Content-Type: application/json");

    $target_subject_id = (int)($_POST["subject_id"] ?? 0);
    if (!faculty_has_subject($faculty_id, $target_subject_id)) {
        echo json_encode(array("success" => false, "message" => "Unauthorized subject access."));
        exit;
    }

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
    $subject_name = get_subject_name_by_id($conn, $target_subject_id);
    $generator = new QuestionGenerator();
    $question = $generator->generateMCQ($subject_name, "", $difficulty, "English", $marks, false, $existing, $current_question, $current_options, $request_nonce . "_" . $question_id);

    if (!$question) {
        echo json_encode(array("success" => false, "message" => $generator->getLastError() ?: "Could not regenerate question."));
        exit;
    }

    echo json_encode(array("success" => true, "question" => $question));
    exit;
}

// Handle AJAX load subject questions
if (isset($_GET["ajax_action"]) && $_GET["ajax_action"] == "load_subject_questions") {
    header("Content-Type: text/html; charset=UTF-8");
    $ajax_subject_id = (int)($_GET["subject_id"] ?? 0);
    if (!faculty_has_subject($faculty_id, $ajax_subject_id)) {
        echo '<div class="empty-state text-danger text-center">Unauthorized subject access.</div>';
        exit;
    }

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
            <label class="standard-question-card <?php if (in_array((int)$q["id"], $selected_ids)) { echo "is-selected"; } ?>" data-difficulty="<?php echo h($q["difficulty"]); ?>" data-marks="<?php echo (int)($q["marks"] ?? 1); ?>" data-searchtext="<?php echo h(strtolower($q["question"] . " " . $q["option_a"] . " " . $q["option_b"] . " " . $q["option_c"] . " " . $q["option_d"])); ?>">
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
        <div class="empty-state text-center">No questions available in this subject. <a href="questions.php?subject_id=<?php echo $ajax_subject_id; ?>">Add questions here</a></div>
    <?php }
    exit;
}

// AJAX: subjects for academic scope (Course + Semester -> assigned subjects)
if (isset($_GET["ajax_action"]) && $_GET["ajax_action"] == "subjects_for_scope") {
    header("Content-Type: application/json; charset=UTF-8");
    $scope_course = trim($_GET["course"] ?? "");
    $scope_semester = trim($_GET["semester"] ?? "");
    if (($scope_course !== "" && !academic_valid_course($scope_course)) || ($scope_semester !== "" && !academic_valid_semester($scope_semester))) {
        echo json_encode(array("success" => false, "message" => "Invalid academic scope."));
        exit;
    }
    $list = faculty_subjects_for_scope($faculty_id, $scope_course, $scope_semester);
    echo json_encode(array("success" => true, "subjects" => $list));
    exit;
}

// AJAX: eligible student count for a course+semester scope
if (isset($_GET["ajax_action"]) && $_GET["ajax_action"] == "eligible_count") {
    header("Content-Type: application/json; charset=UTF-8");
    $scope_course = trim($_GET["course"] ?? "");
    $scope_semester = trim($_GET["semester"] ?? "");
    if ($scope_course === "" || $scope_semester === "" || !academic_valid_course($scope_course) || !academic_valid_semester($scope_semester)) {
        echo json_encode(array("success" => false, "message" => "Select a valid course and semester."));
        exit;
    }
    echo json_encode(array("success" => true, "count" => exam_eligible_student_count($scope_course, $scope_semester)));
    exit;
}

// Handle Delete (blocked when student attempts exist — academic records must stay intact)
if (isset($_GET["delete"])) {
    $id = (int)clean_data($_GET["delete"]);
    $check_exam = mysqli_query($conn, "SELECT subject_id FROM exams WHERE id='$id' LIMIT 1");
    if ($check_exam && $row = mysqli_fetch_assoc($check_exam)) {
        if (!faculty_has_subject($faculty_id, (int)$row["subject_id"])) {
            redirect_alert("Access Denied: You cannot delete exams for unauthorized subjects.", "exam_library.php", "error");
        }
        $att_check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id='$id'");
        $att_row = $att_check ? mysqli_fetch_assoc($att_check) : array("c" => 0);
        $att_count = (int)($att_row["c"] ?? 0);
        if ($att_count > 0) {
            redirect_alert("Cannot delete this exam: " . $att_count . " student attempt" . ($att_count === 1 ? "" : "s") . " exist. Set the exam to Inactive instead to preserve academic records.", "exam_library.php", "error");
        }
        mysqli_query($conn, "DELETE FROM exam_questions WHERE exam_id='$id'");
        mysqli_query($conn, "DELETE FROM exams WHERE id='$id'");
        redirect_alert("Exam deleted successfully", "exam_library.php", "success");
    } else {
        redirect_alert("Exam not found", "exam_library.php", "error");
    }
}

// Handle Clone (duplicate exam + linked questions as Inactive draft)
if (isset($_GET["clone"])) {
    $id = (int)clean_data($_GET["clone"]);
    $check_exam = mysqli_query($conn, "SELECT * FROM exams WHERE id='$id' LIMIT 1");
    if ($check_exam && $row = mysqli_fetch_assoc($check_exam)) {
        if (!faculty_has_subject($faculty_id, (int)$row["subject_id"])) {
            redirect_alert("Access Denied: You cannot clone exams for unauthorized subjects.", "exam_library.php", "error");
        }
        $new_title = trim((string)$row["exam_title"]) . " (Copy)";
        $t_safe = mysqli_real_escape_string($conn, $row["exam_title"]);
        $dup = mysqli_query($conn, "SELECT id FROM exams WHERE exam_title='$t_safe (Copy)' AND subject_id='" . (int)$row["subject_id"] . "' LIMIT 1");
        if ($dup && mysqli_num_rows($dup) > 0) {
            $new_title .= " " . date("Y-m-d");
        }
        $new_title_safe = mysqli_real_escape_string($conn, $new_title);
        $new_date = date("Y-m-d", max(strtotime($row["exam_date"]), strtotime(date("Y-m-d"))));
        $new_ins = mysqli_query($conn, "INSERT INTO exams (exam_title,subject_id,faculty_id,course,semester,exam_date,duration,total_marks,pass_marks,status,exam_mode,max_questions) VALUES ('$new_title_safe','" . (int)$row["subject_id"] . "','$faculty_id','" . mysqli_real_escape_string($conn, $row["course"] ?? "") . "','" . mysqli_real_escape_string($conn, $row["semester"] ?? "") . "','$new_date','" . (int)$row["duration"] . "','" . (int)$row["total_marks"] . "','" . (int)$row["pass_marks"] . "','Inactive','" . mysqli_real_escape_string($conn, $row["exam_mode"] ?? "Standard") . "','" . (int)($row["max_questions"] ?? 10) . "')");
        if ($new_ins) {
            $new_id = (int)mysqli_insert_id($conn);
            $qres = mysqli_query($conn, "SELECT question_id FROM exam_questions WHERE exam_id='$id'");
            if ($qres) {
                while ($qrow = mysqli_fetch_assoc($qres)) {
                    $qid = (int)$qrow["question_id"];
                    mysqli_query($conn, "INSERT IGNORE INTO exam_questions (exam_id,question_id) VALUES ('$new_id','$qid')");
                }
            }
            redirect_alert("Exam duplicated as draft. Review and publish it when ready.", "exam_library.php", "success");
        } else {
            redirect_alert("Could not duplicate the exam. Please try again.", "exam_library.php", "error");
        }
    } else {
        redirect_alert("Exam not found", "exam_library.php", "error");
    }
}

// Handle Status quick-toggle (Active <-> Inactive)
if (isset($_GET["toggle_status"])) {
    $id = (int)clean_data($_GET["toggle_status"]);
    $check_exam = mysqli_query($conn, "SELECT subject_id, status FROM exams WHERE id='$id' LIMIT 1");
    if ($check_exam && $row = mysqli_fetch_assoc($check_exam)) {
        if (!faculty_has_subject($faculty_id, (int)$row["subject_id"])) {
            redirect_alert("Access Denied: You cannot modify exams for unauthorized subjects.", "exam_library.php", "error");
        }
        $new_status = ($row["status"] === "Active") ? "Inactive" : "Active";
        mysqli_query($conn, "UPDATE exams SET status='$new_status' WHERE id='$id'");
        redirect_alert("Exam status changed to " . $new_status . ".", "exam_library.php", "success");
    } else {
        redirect_alert("Exam not found", "exam_library.php", "error");
    }
}

// Handle Edit Load
if (isset($_GET["edit"])) {
    $edit_id = (int)clean_data($_GET["edit"]);
    $result = mysqli_query($conn, "SELECT * FROM exams WHERE id='$edit_id' LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (!faculty_has_subject($faculty_id, (int)$row["subject_id"])) {
            redirect_alert("Access Denied: You cannot edit exams for unauthorized subjects.", "exams.php", "error");
        }
        $exam_title = $row["exam_title"];
        $subject_id = $row["subject_id"];
        $exam_course = trim($row["course"] ?? "");
        $exam_semester = trim($row["semester"] ?? "");
        $exam_date = $row["exam_date"];
        $duration = $row["duration"];
        $pass_marks = $row["pass_marks"];
        $status = $row["status"];
        $exam_mode = $row["exam_mode"] ?? "Standard";
        $max_questions = (int)($row["max_questions"] ?? 10);

        // Scope lock: exams with attempts keep course/semester/subject frozen
        $att_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id='$edit_id'");
        $edit_attempt_count = $att_res ? (int)(mysqli_fetch_assoc($att_res)["c"] ?? 0) : 0;
        $edit_scope_locked = $edit_attempt_count > 0;

        $qres = mysqli_query($conn, "SELECT question_id FROM exam_questions WHERE exam_id='$edit_id'");
        while ($qrow = mysqli_fetch_assoc($qres)) {
            $selected_questions[] = $qrow["question_id"];
        }
        // Prefill Marks Per Question from existing exam questions (uniform value, else default 1)
        if (!empty($selected_questions)) {
            $mpq_ids = implode(",", array_map("intval", $selected_questions));
            $mpq_res = mysqli_query($conn, "SELECT DISTINCT marks FROM questions WHERE id IN ($mpq_ids)");
            $mpq_vals = array();
            if ($mpq_res) {
                while ($mpq_row = mysqli_fetch_assoc($mpq_res)) {
                    $mpq_vals[] = (int)$mpq_row["marks"];
                }
            }
            if (count($mpq_vals) === 1 && $mpq_vals[0] >= 1) {
                $marks_per_question = $mpq_vals[0];
            }
        }
    }
}

// Handle POST Save / Generate Preview
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_action = clean_data($_POST["form_action"] ?? "");
    $should_save_exam = true;
    $edit_id = clean_data($_POST["edit_id"] ?? "");
    $exam_title = clean_data($_POST["exam_title"] ?? "");
    $subject_id = (int)clean_data($_POST["subject_id"] ?? 0);
    $exam_course = trim($_POST["course"] ?? "");
    $exam_semester = trim($_POST["semester"] ?? "");

    if ($edit_id != "") {
        $stored_exam = mysqli_query($conn, "SELECT subject_id, course, semester FROM exams WHERE id='$edit_id' LIMIT 1");
        if ($stored_exam && $stored_row = mysqli_fetch_assoc($stored_exam)) {
            $subject_id = (int)$stored_row["subject_id"];
            // Scope lock: an exam with attempts keeps its academic scope frozen
            $lock_check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE exam_id='$edit_id'");
            $lock_count = $lock_check ? (int)(mysqli_fetch_assoc($lock_check)["c"] ?? 0) : 0;
            if ($lock_count > 0) {
                $edit_attempt_count = $lock_count;
                $edit_scope_locked = true;
                $exam_course = trim($stored_row["course"] ?? "");
                $exam_semester = trim($stored_row["semester"] ?? "");
            }
        }
    }

    // Strict Backend Subject Authorization
    if (!faculty_has_subject($faculty_id, $subject_id)) {
        redirect_alert("Access Denied: You are not authorized to create or manage exams for this subject.", "exams.php", "error");
    }

    // Academic scope validation (never trust frontend values)
    if ($exam_course === "" || !academic_valid_course($exam_course)) {
        $form_error = "Please select a course/program.";
    }
    if (!$form_error && ($exam_semester === "" || !academic_valid_semester($exam_semester))) {
        $form_error = "Please select a semester.";
    }
    if (!$form_error && !subject_accepts_scope($subject_id, $exam_course, $exam_semester)) {
        $form_error = "Invalid academic scope or unauthorized subject.";
    }

    $exam_date = clean_data($_POST["exam_date"] ?? "");
    $duration = clean_data($_POST["duration"] ?? "");
    $pass_marks = clean_data($_POST["pass_marks"] ?? "");
    $marks_per_question = max(1, (int)($_POST["marks_per_question"] ?? 1));
    $status = clean_data($_POST["status"] ?? "Active");
    $questions = isset($_POST["questions"]) && is_array($_POST["questions"]) ? $_POST["questions"] : array();
    $selected_questions = $questions;

    $exam_mode = clean_data($_POST["exam_mode"] ?? "Standard");
    $max_questions = (int)($_POST["max_questions"] ?? 10);
    if ($max_questions < 1) $max_questions = 10;
    if ($edit_id != "") {
        $max_questions = count($questions);
    }
    $ai_preview_questions = normalize_ai_preview_questions($_POST["ai_questions_json"] ?? "[]");
    $ai_preview_active = $exam_mode == 'Adaptive' && in_array($form_action, array("generate_ai_preview", "create_exam_confirmed"));

    if ($exam_title == "" || $subject_id <= 0 || $exam_course == "" || $exam_semester == "" || $exam_date == "" || $duration == "" || $pass_marks == "") {
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
    // Apply Marks Per Question to the selected bank questions (scoped to this exam's subject),
    // so saved Total Marks always equals Total Questions x Marks Per Question.
    if (!$form_error && $marks_per_question >= 1 && count($questions) > 0 && ($exam_mode != 'Adaptive' || $edit_id != "")) {
        $mpq_ids = array();
        foreach ($questions as $apply_qid) {
            $apply_qid = (int)$apply_qid;
            if ($apply_qid > 0) {
                $mpq_ids[] = $apply_qid;
            }
        }
        if (!empty($mpq_ids)) {
            $mpq_list = implode(",", $mpq_ids);
            mysqli_query($conn, "UPDATE questions SET marks='$marks_per_question' WHERE id IN ($mpq_list) AND subject_id='$subject_id'");
        }
    }
    foreach ($questions as $qid) {
        $qid = (int)$qid;
        $mres = mysqli_query($conn, "SELECT marks FROM questions WHERE id='$qid'");
        if ($mrow = mysqli_fetch_assoc($mres)) {
            $total_marks += (int)$mrow["marks"];
        }
    }
    if (!$form_error && $pass_marks > $total_marks && $exam_mode != 'Adaptive') {
        $form_error = "Pass marks cannot be greater than total marks ($total_marks)";
    }

    $check = mysqli_query($conn, "SELECT * FROM exams WHERE exam_title='" . mysqli_real_escape_string($conn, $exam_title) . "' AND id!='$edit_id'");
    if (!$form_error && $check && mysqli_num_rows($check) > 0) {
        $form_error = "Exam title already exists";
    }

    // AI Preview generation
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
                    $question["marks"] = $marks_per_question;
                    $qid = $generator->saveToDatabase((int)$subject_id, $question);
                    if (!$qid) {
                        $form_error = "Database error while saving reviewed AI question: " . mysqli_error($conn);
                        break;
                    }
                    // Tag question with faculty_id
                    mysqli_query($conn, "UPDATE questions SET faculty_id=$faculty_id WHERE id=$qid");
                    $questions[] = $qid;
                }
                $selected_questions = $questions;
                $total_marks = count($ai_preview_questions) * $marks_per_question;
                if ((int)$pass_marks > $total_marks) {
                    $pass_marks = $total_marks;
                }
            }
        } elseif ($exam_mode == 'Adaptive') {
            $total_marks = 0;
            foreach ($questions as $qid) {
                $qid = (int)$qid;
                $mres = mysqli_query($conn, "SELECT marks FROM questions WHERE id='$qid'");
                if ($mrow = mysqli_fetch_assoc($mres)) {
                    $total_marks += (int)$mrow["marks"];
                }
            }
        }
    }

    if (!$form_error && $should_save_exam) {
        $exam_title_s = mysqli_real_escape_string($conn, $exam_title);
        $exam_date_s = mysqli_real_escape_string($conn, $exam_date);
        $status_s = mysqli_real_escape_string($conn, $status);
        $mode_s = mysqli_real_escape_string($conn, $exam_mode);
        $course_s = mysqli_real_escape_string($conn, $exam_course);
        $semester_s = mysqli_real_escape_string($conn, $exam_semester);

        if ($edit_id != "") {
            $saved = mysqli_query($conn, "UPDATE exams SET exam_title='$exam_title_s', subject_id='$subject_id', faculty_id='$faculty_id', course='$course_s', semester='$semester_s', exam_date='$exam_date_s', duration='$duration', total_marks='$total_marks', pass_marks='$pass_marks', status='$status_s', exam_mode='$mode_s', max_questions=$max_questions WHERE id='$edit_id'");
            if ($saved) {
                mysqli_query($conn, "DELETE FROM exam_questions WHERE exam_id='$edit_id'");
                $exam_id = $edit_id;
                $message = "Exam updated successfully for " . $exam_course . " • " . $exam_semester . ".";
            } else {
                $form_error = "Update failed: " . mysqli_error($conn);
            }
        } else {
            $saved = mysqli_query($conn, "INSERT INTO exams (exam_title,subject_id,faculty_id,course,semester,exam_date,duration,total_marks,pass_marks,status,exam_mode,max_questions) VALUES ('$exam_title_s','$subject_id','$faculty_id','$course_s','$semester_s','$exam_date_s','$duration','$total_marks','$pass_marks','$status_s','$mode_s',$max_questions)");
            if ($saved) {
                $exam_id = mysqli_insert_id($conn);
                $eligible_now = exam_eligible_student_count($exam_course, $exam_semester);
                foreach (announcement_scope_students($exam_course, $exam_semester) as $eligible_student_id) {
                    notify_user("student", $eligible_student_id, "New Exam Created", $exam_title . " is available for your examination.", "exams.php");
                }
                notify_user("student", null, "New Exam Created", $exam_title . " (" . $exam_course . " • " . $exam_semester . ") is available for your examination.", "exams.php");
                notify_user("faculty", $faculty_id, "Exam Created", "You created exam: " . $exam_title . " for " . $exam_course . " • " . $exam_semester, "exams.php");
                $message = $exam_title . " has been created for " . $exam_course . " • " . $exam_semester . ". " . $eligible_now . " student" . ($eligible_now === 1 ? " is" : "s are") . " eligible for this examination.";
            } else {
                $form_error = "Insert failed: " . mysqli_error($conn);
            }
        }
    }

    if (!$form_error && $should_save_exam) {
        $linked = 0;
        foreach ($questions as $qid) {
            $qid = (int)$qid;
            if (mysqli_query($conn, "INSERT IGNORE INTO exam_questions (exam_id,question_id) VALUES ('$exam_id','$qid')")) {
                $linked += mysqli_affected_rows($conn);
            }
        }
        redirect_alert($message, "exam_library.php", "success");
    }

    if ($form_error) {
        flash_script($form_error, "", "error");
    }
}

include("../app/layouts/faculty_header.php");

// Fetch assigned subjects only (as reusable list for form + filters)
$subjects = mysqli_query($conn, "SELECT s.* FROM subjects s INNER JOIN faculty_subjects fs ON s.id=fs.subject_id WHERE fs.faculty_id=$faculty_id AND s.status='Active' ORDER BY s.subject_name ASC");
$exam_subject_list = array();
if ($subjects) {
    while ($sl_row = mysqli_fetch_assoc($subjects)) {
        $exam_subject_list[] = $sl_row;
    }
}

$subject_cards = mysqli_query($conn, "SELECT s.*, COUNT(q.id) AS question_count
    FROM subjects s
    INNER JOIN faculty_subjects fs ON s.id=fs.subject_id
    LEFT JOIN questions q ON q.subject_id=s.id
    WHERE fs.faculty_id=$faculty_id AND s.status='Active'
    GROUP BY s.id ORDER BY s.id ASC");

// Scope-wide counts for the hero + library teaser (unfiltered)
$scope_counts = array("total" => 0, "active" => 0, "adaptive" => 0, "standard" => 0);
$scope_res = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(status='Active') AS active, SUM(exam_mode='Adaptive') AS adaptive, SUM(exam_mode='Standard' OR exam_mode IS NULL OR exam_mode='') AS standard FROM exams WHERE subject_id IN ($subject_ids_csv)");
if ($scope_res && $scope_row = mysqli_fetch_assoc($scope_res)) {
    $scope_counts["total"] = (int)$scope_row["total"];
    $scope_counts["active"] = (int)($scope_row["active"] ?? 0);
    $scope_counts["adaptive"] = (int)($scope_row["adaptive"] ?? 0);
    $scope_counts["standard"] = (int)($scope_row["standard"] ?? 0);
}
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Exams</li>
                </ol>
            </nav>
            <?php
            $fp_hero_total = (int)($scope_counts["total"] ?? 0);
            $fp_hero_std = (int)($scope_counts["standard"] ?? 0);
            $fp_hero_adp = (int)($scope_counts["adaptive"] ?? 0);
            $fp_hero_std_pct = $fp_hero_total > 0 ? round(($fp_hero_std / $fp_hero_total) * 100) : 0;
            $fp_hero_adp_pct = $fp_hero_total > 0 ? round(($fp_hero_adp / $fp_hero_total) * 100) : 0;
            $fp_is_editing = $edit_id !== "" && !empty($edit_id);
            ?>
            <section class="dash-hero mb-4" aria-label="Exam creation">
                <span class="dash-orb dash-orb-1" aria-hidden="true"></span>
                <span class="dash-orb dash-orb-2" aria-hidden="true"></span>
                <div class="dash-hero-inner">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <div class="sp-fade-up">
                                <span class="dash-eyebrow"><span class="live-dot" aria-hidden="true"></span>Exam Builder &middot; <?php echo count($assigned_subject_ids); ?> subjects in scope</span>
                            </div>
                            <h1 class="dash-title sp-fade-up" data-delay="1"><?php echo $fp_is_editing ? "Edit <span class=\"grad\">Exam</span>" : "Create <span class=\"grad\">Exam</span>"; ?></h1>
                            <p class="dash-sub sp-fade-up" data-delay="2">Create Standard or AI Adaptive exams for your assigned subjects — configure, generate questions, and publish.</p>
                            <div class="dash-idchips sp-fade-up" data-delay="2">
                                <span class="dash-idchip"><i class="bi bi-collection"></i><?php echo $fp_hero_total; ?> Total Exams</span>
                                <span class="dash-idchip"><i class="bi bi-gear"></i><?php echo $fp_hero_std; ?> Standard</span>
                                <span class="dash-idchip"><i class="bi bi-robot"></i><?php echo $fp_hero_adp; ?> Adaptive</span>
                            </div>
                            <div class="dash-ctas sp-fade-up" data-delay="3">
                                <?php if ($fp_is_editing) { ?><a class="dash-cta-primary" href="exams.php"><i class="bi bi-plus-circle-fill"></i>Create New Exam</a><?php } ?>
                                <a class="dash-cta-ghost" href="questions.php"><i class="bi bi-patch-question"></i>Question Bank</a>
                                <a class="dash-cta-ghost" href="exam_library.php"><i class="bi bi-list-check"></i>View Library</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="dash-profile-card sp-fade-up" data-delay="2">
                                <span class="dash-avatar-wrap">
                                    <span class="dash-avatar-fallback"><i class="bi bi-calendar-check"></i></span>
                                    <span class="dash-online-dot" style="background:#22C55E;" aria-hidden="true"></span>
                                </span>
                                <div class="dash-profile-meta">
                                    <strong>Exam Modes</strong>
                                    <small>Standard vs AI Adaptive split</small>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between small mb-1"><span class="text-white-50">Standard</span><strong><?php echo $fp_hero_std_pct; ?>%</strong></div>
                                        <div class="sp-track mb-2" style="background:rgba(255,255,255,.12);"><span data-sp-progress="<?php echo $fp_hero_std_pct; ?>" style="width:0;"></span></div>
                                        <div class="d-flex justify-content-between small mb-1"><span class="text-white-50">Adaptive</span><strong><?php echo $fp_hero_adp_pct; ?>%</strong></div>
                                        <div class="sp-track is-success" style="background:rgba(255,255,255,.12);"><span data-sp-progress="<?php echo $fp_hero_adp_pct; ?>" style="width:0;"></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (count($assigned_subject_ids) == 0) { ?>
                <div class="dashboard-panel text-center py-5">
                    <i class="bi bi-exclamation-triangle fs-1 text-warning d-block mb-3"></i>
                    <h4>No Subjects Assigned</h4>
                    <p class="text-muted">You cannot create exams because you have not been assigned any subjects yet. Please contact the Admin.</p>
                </div>
            <?php } else { ?>

            <!-- Exam Form Builder -->
            <form method="post" class="form-card p-4 mb-4 sp-fade-up fp-builder" id="examFormBuilder">
                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <input type="hidden" name="form_action" id="formAction" value="">
                <input type="hidden" name="ai_questions_json" id="aiQuestionsJson" value="<?php echo h(json_encode($ai_preview_questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>">

                <?php if ($fp_is_editing) {
                    $fp_edit_sub_name = "General";
                    foreach ($exam_subject_list as $fp_sl) {
                        if ((int)$fp_sl["id"] === (int)$subject_id) { $fp_edit_sub_name = $fp_sl["subject_name"]; break; }
                    }
                ?>
                <div class="fp-edit-banner" role="status">
                    <span class="fp-edit-icon" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <strong class="d-block text-truncate">Editing: <?php echo h($exam_title !== "" ? $exam_title : ("Exam #" . (int)$edit_id)); ?></strong>
                        <small>
                            <i class="bi bi-book me-1"></i><?php echo h($fp_edit_sub_name); ?>
                            <span class="mx-1">·</span><i class="bi bi-cpu me-1"></i><?php echo h($exam_mode); ?>
                            <span class="mx-1">·</span><i class="bi bi-list-ol me-1"></i><?php echo count($selected_questions); ?> questions linked
                        </small>
                    </div>
                    <a href="exams.php" class="sp-btn sp-btn-ghost flex-shrink-0"><i class="bi bi-plus-circle"></i><span class="d-none d-sm-inline">New Exam</span></a>
                </div>
                <?php } ?>
                <div class="fp-step-head">
                    <span class="fp-step-num" aria-hidden="true">1</span>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-0">Exam Configuration</h5>
                        <small class="text-muted">Basic details, academic scope, schedule and delivery mode</small>
                    </div>
                    <span class="sp-pill <?php echo $exam_mode === 'Adaptive' ? 'is-adaptive' : 'is-available'; ?>" id="fpModePill"><i class="bi bi-<?php echo $exam_mode === 'Adaptive' ? 'robot' : 'gear'; ?>"></i><span id="fpModePillText"><?php echo h($exam_mode); ?></span></span>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="fp-label" for="examTitle">Exam Title <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-type text-muted"></i></span>
                            <input id="examTitle" name="exam_title" value="<?php echo h($exam_title); ?>" class="form-control" placeholder="e.g. Mid-Term PHP Exam" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="examCourse">Course / Program <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-mortarboard text-muted"></i></span>
                            <select id="examCourse" name="course" class="form-select" required <?php echo $edit_scope_locked ? "disabled" : ""; ?>>
                                <option value="">Select Course</option>
                                <?php foreach (academic_course_options() as $c_opt) { ?>
                                    <option value="<?php echo h($c_opt); ?>" <?php if ($exam_course === $c_opt) { echo "selected"; } ?>><?php echo h($c_opt); ?></option>
                                <?php } ?>
                            </select>
                            <?php if ($edit_scope_locked) { ?><input type="hidden" name="course" value="<?php echo h($exam_course); ?>"><?php } ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="examSemester">Semester / Year <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-layers text-muted"></i></span>
                            <select id="examSemester" name="semester" class="form-select" required <?php echo $edit_scope_locked ? "disabled" : ""; ?>>
                                <option value="">Select Semester</option>
                                <?php foreach (academic_semester_options() as $s_opt) { ?>
                                    <option value="<?php echo h($s_opt); ?>" <?php if ($exam_semester === $s_opt) { echo "selected"; } ?>><?php echo h($s_opt); ?></option>
                                <?php } ?>
                            </select>
                            <?php if ($edit_scope_locked) { ?><input type="hidden" name="semester" value="<?php echo h($exam_semester); ?>"><?php } ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="examSubject">Assigned Subject <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-book text-muted"></i></span>
                            <select id="examSubject" name="subject_id" class="form-select" required <?php echo ($fp_is_editing || $edit_scope_locked) ? "disabled" : ""; ?>>
                                <option value="">Select Course + Semester first</option>
                                <?php foreach ($exam_subject_list as $sub) { ?>
                                    <option value="<?php echo (int)$sub["id"]; ?>" <?php if ((int)$subject_id === (int)$sub["id"]) { echo "selected"; } ?>>
                                        <?php echo h($sub["subject_name"]); ?> (<?php echo h($sub["subject_code"]); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <?php if ($fp_is_editing || $edit_scope_locked) { ?><input type="hidden" name="subject_id" value="<?php echo (int)$subject_id; ?>"><?php } ?>
                        </div>
                        <?php if ($edit_scope_locked) { ?>
                            <small class="fp-hint text-warning"><i class="bi bi-lock me-1"></i>Academic scope is locked — this exam already has <?php echo (int)$edit_attempt_count; ?> student attempt(s). Changing it may affect student eligibility.</small>
                        <?php } elseif ($fp_is_editing) { ?>
                            <small class="fp-hint"><i class="bi bi-lock me-1"></i>Subject is locked after creation.</small>
                        <?php } else { ?>
                            <small class="fp-hint" id="fpSubjectHint">Select a course and semester to load eligible subjects.</small>
                        <?php } ?>
                    </div>
                </div>
                <div class="fp-scope-box mt-3" id="fpScopeBox">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fp-scope-title"><i class="bi bi-mortarboard-fill me-1"></i>Exam Scope</span>
                        <span class="badge bg-primary-soft text-primary" id="fpScopeCourse"><?php echo $exam_course !== "" ? h($exam_course) : "—"; ?></span>
                        <span class="badge bg-primary-soft text-primary" id="fpScopeSemester"><?php echo $exam_semester !== "" ? h($exam_semester) : "—"; ?></span>
                        <span class="badge bg-light text-dark" id="fpScopeSubject"><?php echo (int)$subject_id > 0 ? h(get_subject_name_by_id($conn, (int)$subject_id)) : "—"; ?></span>
                        <span class="ms-auto sp-pill is-available" id="fpEligiblePill"><i class="bi bi-people"></i><span id="fpEligibleCount">—</span>&nbsp;eligible</span>
                    </div>
                    <small class="text-muted d-block mt-2">Students matching this course and semester will be eligible for this examination.</small>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <label class="fp-label" for="examDate">Exam Date <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3 text-muted"></i></span>
                            <input id="examDate" type="date" name="exam_date" value="<?php echo $exam_date; ?>" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="examDuration">Duration <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-stopwatch text-muted"></i></span>
                            <input id="examDuration" type="number" name="duration" value="<?php echo $duration; ?>" class="form-control" min="1" placeholder="Minutes" required>
                            <span class="input-group-text text-muted small">mins</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fp-label" for="examStatus">Status</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-toggle-on text-muted"></i></span>
                            <select id="examStatus" name="status" class="form-select">
                                <option value="Active" <?php if ($status == "Active") echo "selected"; ?>>Active (Published)</option>
                                <option value="Inactive" <?php if ($status == "Inactive") echo "selected"; ?>>Inactive (Draft)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fp-label" for="examMode">Exam Mode</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-cpu text-muted"></i></span>
                            <select id="examMode" name="exam_mode" class="form-select">
                                <option value="Standard" <?php if ($exam_mode == 'Standard') echo 'selected'; ?>>Standard (Manual Selection)</option>
                                <option value="Adaptive" <?php if ($exam_mode == 'Adaptive') echo 'selected'; ?>>Adaptive (AI Powered CAT)</option>
                            </select>
                        </div>
                        <small class="fp-hint" id="fpModeHint"><?php echo $exam_mode === 'Adaptive' ? 'AI selects questions dynamically based on student ability.' : 'You hand-pick questions from the bank below.'; ?></small>
                    </div>
                    <div class="col-md-6" id="maxQuestionsWrap">
                        <label class="fp-label" for="maxQuestions">Total Questions <span class="text-muted fw-normal">(Adaptive Mode)</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-list-ol text-muted"></i></span>
                            <input id="maxQuestions" type="number" name="max_questions" value="<?php echo (int)$max_questions; ?>" class="form-control" min="1" max="100">
                            <span class="input-group-text text-muted small">max 100</span>
                        </div>
                    </div>
                </div>

                <!-- Marks Configuration -->
                <div class="fp-scope-box mt-3" id="marksConfigBox">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="fp-scope-title"><i class="bi bi-calculator-fill me-1"></i>Marks Configuration</span>
                        <span class="sp-pill is-available" id="marksFormulaPill"><i class="bi bi-lightning-charge"></i><span id="marksFormulaText">0 × 1 = 0 marks</span></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <label class="fp-label" for="marksTotalQ">Total Questions <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-list-ol text-muted"></i></span>
                                <input id="marksTotalQ" type="number" class="form-control" value="0" readonly aria-readonly="true" tabindex="-1">
                                <span class="input-group-text text-muted small">auto</span>
                            </div>
                            <small class="fp-hint" id="marksTotalQHint">Counted from selected questions.</small>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="fp-label" for="marksPerQ">Marks Per Question <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-award text-muted"></i></span>
                                <input id="marksPerQ" type="number" name="marks_per_question" value="<?php echo (int)$marks_per_question; ?>" class="form-control" min="1" max="100" required>
                                <span class="input-group-text text-muted small">marks</span>
                            </div>
                            <small class="fp-hint">Applied to selected questions on save.</small>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="fp-label" for="marksTotal">Total Marks</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-trophy text-muted"></i></span>
                                <input id="marksTotal" type="number" class="form-control fw-bold" value="0" readonly aria-readonly="true" tabindex="-1">
                                <span class="input-group-text text-muted small">auto</span>
                            </div>
                            <small class="fp-hint" id="marksTotalHint">Total Questions × Marks Per Question.</small>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="fp-label" for="passMarks">Passing Marks <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-flag text-muted"></i></span>
                                <input id="passMarks" type="number" name="pass_marks" value="<?php echo $pass_marks; ?>" class="form-control" min="1" placeholder="e.g. 16" required>
                                <span class="input-group-text text-muted small">marks</span>
                            </div>
                            <small class="fp-hint" id="passMarksHint">Must not exceed Total Marks.</small>
                        </div>
                    </div>
                    <div class="alert alert-warning d-flex align-items-start gap-2 mt-3 mb-0 py-2 px-3" id="marksMixedNotice" style="display:none;" role="alert">
                        <i class="bi bi-exclamation-triangle mt-1"></i>
                        <small id="marksMixedText">Some selected questions carry different marks — saving will update them to the Marks Per Question value above.</small>
                    </div>
                </div>

                <!-- AI Preview Section -->
                <div id="aiPreviewSection" class="mt-4" style="<?php echo ($ai_preview_active && count($ai_preview_questions) > 0) ? "" : "display:none;"; ?>">
                    <hr class="my-4">
                    <div class="fp-step-head mb-3">
                        <span class="fp-step-num fp-step-ai" aria-hidden="true"><i class="bi bi-robot"></i></span>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-0">AI Question Preview</h5>
                            <small class="text-muted">Review or regenerate AI-generated MCQs before finalizing the exam.</small>
                        </div>
                        <span class="sp-pill is-adaptive"><i class="bi bi-stars"></i>AI Generated</span>
                    </div>
                    <div class="d-flex justify-content-end mb-2">
                        <button type="button" class="sp-btn sp-btn-ghost" id="btnOpenModalFromSection">
                            <i class="bi bi-arrows-fullscreen"></i>Open Preview Modal
                        </button>
                    </div>
                    <div id="aiQuestionPreviewList" class="ai-preview-list"></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <small class="text-muted" id="aiPreviewCount"></small>
                        <button type="button" class="sp-btn sp-btn-primary no-loader" id="confirmCreateExamBtn">
                            <i class="bi bi-check2-circle"></i>Confirm &amp; Create Exam
                        </button>
                    </div>
                </div>

                <!-- Standard Question Selection Section -->
                <div id="standardModeOptions">
                    <hr class="my-4">
                    <div class="fp-step-head mb-3">
                        <span class="fp-step-num" aria-hidden="true">2</span>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-0">Select Questions from Question Bank</h5>
                            <small class="text-muted">Pick a subject, then tick the questions to include.</small>
                        </div>
                        <span class="sp-pill is-available"><i class="bi bi-collection"></i>Assigned Subjects</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <?php 
                        if ($subject_cards) {
                            while ($scard = mysqli_fetch_assoc($subject_cards)) { ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="subject-card js-select-subject-card p-3 h-100 <?php echo $subject_id == $scard["id"] ? "is-active" : ""; ?>" data-subject-id="<?php echo $scard["id"]; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="admin-card-icon me-3"><i class="bi bi-journal-text"></i></span>
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo h($scard["subject_name"]); ?></h6>
                                                <small class="text-muted"><?php echo h($scard["subject_code"]); ?></small>
                                            </div>
                                            <span class="badge bg-primary"><?php echo (int)$scard["question_count"]; ?> Qs</span>
                                        </div>
                                    </div>
                                </div>
                            <?php }
                        } ?>
                    </div>

                    <div class="fp-q-toolbar" id="questionToolbar" style="display:none;">
                        <div class="fp-q-search">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control" id="fpQSearch" placeholder="Search questions..." aria-label="Search questions">
                            </div>
                        </div>
                        <select class="form-select" id="fpQDifficulty" aria-label="Filter by difficulty">
                            <option value="">All Difficulties</option>
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
                        </select>
                        <button type="button" class="sp-btn sp-btn-soft" id="fpQSelectAll"><i class="bi bi-check2-all"></i>Select All</button>
                        <button type="button" class="sp-btn sp-btn-ghost" id="fpQClear"><i class="bi bi-x-circle"></i>Clear</button>
                        <span class="fp-q-count ms-auto"><i class="bi bi-check2-circle"></i><span id="fpQCount">0 selected</span></span>
                    </div>
                    <div id="selectedSubjectQuestions" class="mb-3">
                        <div class="sp-empty">
                            <div class="sp-empty-icon"><i class="bi bi-arrow-up-circle"></i></div>
                            <h4>No Subject Selected</h4>
                            <p>Select a subject above to load and pick questions.</p>
                        </div>
                    </div>
                </div>

                <div class="fp-submit-bar" id="defaultSubmitActions">
                    <button class="sp-btn sp-btn-primary" id="saveExamBtn" type="submit">
                        <i class="bi bi-check2-circle"></i><?php echo $edit_id != "" ? "Update Exam" : "Save Exam"; ?>
                    </button>
                    <button class="sp-btn sp-btn-soft" id="btnGenerateAdaptive" type="button" style="<?php echo ($exam_mode == 'Adaptive' && $edit_id == '') ? '' : 'display:none;'; ?>">
                        <i class="bi bi-robot"></i>Generate Questions
                    </button>
                    <button class="sp-btn sp-btn-ghost" id="btnReviewPreview" type="button" style="<?php echo (count($ai_preview_questions) > 0 && $exam_mode == 'Adaptive') ? '' : 'display:none;'; ?>">
                        <i class="bi bi-eye"></i>Preview Questions (<span id="confirmedQuestionCount"><?php echo count($ai_preview_questions); ?></span>)
                    </button>
                    <a href="exams.php" class="sp-btn sp-btn-ghost ms-lg-auto">Cancel</a>
                </div>
            </form>

            <!-- Preview Questions Modal (AI Generated) -->
            <div class="modal fade" id="previewQuestionsModal" tabindex="-1" aria-labelledby="previewQuestionsModalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content professional-modal">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title fw-bold" id="previewQuestionsModalLabel">
                                    <i class="bi bi-robot me-2 text-primary"></i>Preview Questions
                                </h5>
                                <small class="text-muted" id="modalSubjectSummary">Review AI-generated questions before attaching to exam.</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4 bg-light">
                            <!-- Summary bar -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 bg-white rounded-3 border mb-3">
                                <div class="d-flex align-items-center gap-2 flex-wrap" id="modalDistributionBadges">
                                    <span class="badge bg-secondary" id="modalTotalBadge">0 Questions</span>
                                    <span class="badge bg-success" id="modalEasyBadge">0 Easy</span>
                                    <span class="badge bg-warning text-dark" id="modalMediumBadge">0 Medium</span>
                                    <span class="badge bg-danger" id="modalHardBadge">0 Hard</span>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-check2-all text-success me-1"></i>Equal difficulty distribution applied
                                </div>
                            </div>

                            <!-- Questions List -->
                            <div id="modalQuestionsList" class="d-flex flex-column gap-3">
                                <!-- Populated dynamically via JS -->
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <button type="button" class="btn btn-outline-primary" id="btnModalGenerateAgain">
                                    <i class="bi bi-arrow-repeat me-1"></i>Generate Again
                                </button>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btnModalKeep">
                                    <i class="bi bi-bookmark-check me-1"></i>Keep / Accept Questions
                                </button>
                                <button type="button" class="btn btn-success" id="btnModalConfirmUse">
                                    <i class="bi bi-check2-circle me-1"></i>Confirm / Use Questions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php } ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var examMode = document.getElementById("examMode");
    var maxQuestionsWrap = document.getElementById("maxQuestionsWrap");
    var standardModeOptions = document.getElementById("standardModeOptions");
    var aiPreviewSection = document.getElementById("aiPreviewSection");
    var examSubject = document.getElementById("examSubject");
    var maxQuestions = document.getElementById("maxQuestions");
    var saveExamBtn = document.getElementById("saveExamBtn");
    var btnGenerateAdaptive = document.getElementById("btnGenerateAdaptive");
    var btnReviewPreview = document.getElementById("btnReviewPreview");
    var confirmedQuestionCount = document.getElementById("confirmedQuestionCount");
    var questionsContainer = document.getElementById("selectedSubjectQuestions");
    var formAction = document.getElementById("formAction");
    var aiQuestionsJson = document.getElementById("aiQuestionsJson");
    var examFormBuilder = document.getElementById("examFormBuilder");
    var confirmCreateExamBtn = document.getElementById("confirmCreateExamBtn");
    var btnOpenModalFromSection = document.getElementById("btnOpenModalFromSection");
    var btnModalGenerateAgain = document.getElementById("btnModalGenerateAgain");
    var btnModalConfirmUse = document.getElementById("btnModalConfirmUse");
    var previewModalEl = document.getElementById("previewQuestionsModal");
    var previewModal = previewModalEl ? bootstrap.Modal.getOrCreateInstance(previewModalEl) : null;

    var selectedIds = <?php echo json_encode(array_map('intval', $selected_questions)); ?>;
    var aiPreviewQuestions = <?php echo json_encode($ai_preview_questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function escapeHtml(value) {
        var div = document.createElement("div");
        div.textContent = value == null ? "" : value;
        return div.innerHTML;
    }

    function updateModeVisibility() {
        if (!examMode) return;
        var mode = examMode.value;
        var modePill = document.getElementById("fpModePill");
        var modePillText = document.getElementById("fpModePillText");
        var modeHint = document.getElementById("fpModeHint");
        if (modePillText) { modePillText.textContent = mode; }
        if (modePill) {
            modePill.className = "sp-pill " + (mode === "Adaptive" ? "is-adaptive" : "is-available");
            var modeIcon = modePill.querySelector("i");
            if (modeIcon) { modeIcon.className = "bi bi-" + (mode === "Adaptive" ? "robot" : "gear"); }
        }
        if (modeHint) {
            modeHint.textContent = mode === "Adaptive"
                ? "AI selects questions dynamically based on student ability."
                : "You hand-pick questions from the bank below.";
        }
        if (mode === "Adaptive") {
            if (maxQuestionsWrap) maxQuestionsWrap.style.display = "block";
            if (standardModeOptions) standardModeOptions.style.display = "none";
            if (btnGenerateAdaptive) btnGenerateAdaptive.style.display = "inline-block";
            if (btnReviewPreview) {
                btnReviewPreview.style.display = (aiPreviewQuestions && aiPreviewQuestions.length > 0) ? "inline-block" : "none";
            }
            if (aiPreviewSection) {
                aiPreviewSection.style.display = (aiPreviewQuestions && aiPreviewQuestions.length > 0) ? "block" : "none";
            }
        } else {
            if (maxQuestionsWrap) maxQuestionsWrap.style.display = "none";
            if (standardModeOptions) standardModeOptions.style.display = "block";
            if (btnGenerateAdaptive) btnGenerateAdaptive.style.display = "none";
            if (btnReviewPreview) btnReviewPreview.style.display = "none";
            if (aiPreviewSection) aiPreviewSection.style.display = "none";
        }
        if (typeof updateMarksPanel === "function") updateMarksPanel();
    }

    if (examMode) {
        examMode.addEventListener("change", updateModeVisibility);
        updateModeVisibility();
    }

    // ---- Marks Configuration live panel ----
    var marksTotalQ = document.getElementById("marksTotalQ");
    var marksPerQ = document.getElementById("marksPerQ");
    var marksTotal = document.getElementById("marksTotal");
    var passMarks = document.getElementById("passMarks");
    var marksFormulaText = document.getElementById("marksFormulaText");
    var marksTotalQHint = document.getElementById("marksTotalQHint");
    var marksTotalHint = document.getElementById("marksTotalHint");
    var passMarksHint = document.getElementById("passMarksHint");
    var marksMixedNotice = document.getElementById("marksMixedNotice");
    var marksMixedText = document.getElementById("marksMixedText");

    function currentMpq() {
        var v = marksPerQ ? parseInt(marksPerQ.value, 10) : 1;
        if (isNaN(v) || v < 1) v = 1;
        return v;
    }

    function countMixedMarks(mpq) {
        var mixed = 0;
        if (typeof fpQuestionCards === "function") {
            fpQuestionCards().forEach(function(card) {
                var input = card.querySelector(".standard-question-checkbox");
                if (input && input.checked) {
                    var m = parseInt(card.getAttribute("data-marks") || String(mpq), 10);
                    if (!isNaN(m) && m !== mpq) mixed++;
                }
            });
        }
        return mixed;
    }

    function updateMarksPanel() {
        if (!marksTotalQ || !marksTotal || !marksPerQ) return;
        var mode = examMode ? examMode.value : "Standard";
        var mpq = currentMpq();
        var q = 0;
        if (mode === "Adaptive") {
            q = maxQuestions ? (parseInt(maxQuestions.value, 10) || 0) : 0;
            if (q < 0) q = 0;
            if (marksTotalQHint) marksTotalQHint.textContent = "Maximum questions set for Adaptive mode.";
            if (marksTotalHint) marksTotalHint.textContent = "Maximum marks = Max Questions × Marks Per Question.";
        } else {
            q = selectedIds.length;
            if (marksTotalQHint) marksTotalQHint.textContent = "Counted from selected questions below.";
            if (marksTotalHint) marksTotalHint.textContent = "Total Questions × Marks Per Question.";
        }
        var total = q * mpq;
        marksTotalQ.value = q;
        marksTotal.value = total;
        if (marksFormulaText) marksFormulaText.textContent = q + " × " + mpq + " = " + total + " marks";
        var mixed = (mode === "Adaptive") ? 0 : countMixedMarks(mpq);
        if (marksMixedNotice) {
            if (mixed > 0) {
                if (marksMixedText) marksMixedText.textContent = mixed + " selected question(s) currently carr" + (mixed === 1 ? "ies" : "y") + " different marks in the bank — saving will update " + (mixed === 1 ? "it" : "them") + " to " + mpq + " mark(s) each (Total " + total + " marks).";
                marksMixedNotice.style.display = "flex";
            } else {
                marksMixedNotice.style.display = "none";
            }
        }
        if (passMarks && passMarksHint) {
            var pass = parseInt(passMarks.value, 10);
            if (!isNaN(pass) && total > 0 && pass > total) {
                passMarksHint.textContent = "Exceeds Total Marks (" + total + ") — please lower it.";
                passMarksHint.classList.add("text-danger");
            } else {
                passMarksHint.classList.remove("text-danger");
                passMarksHint.textContent = (!isNaN(pass) && total > 0)
                    ? Math.round((pass / total) * 100) + "% of Total Marks."
                    : "Must not exceed Total Marks.";
            }
        }
    }

    if (maxQuestions) maxQuestions.addEventListener("input", updateMarksPanel);
    if (marksPerQ) marksPerQ.addEventListener("input", updateMarksPanel);
    if (passMarks) passMarks.addEventListener("input", updateMarksPanel);
    updateMarksPanel();

    function syncAiQuestionsHidden() {
        if (aiQuestionsJson) {
            aiQuestionsJson.value = JSON.stringify(aiPreviewQuestions);
        }
        if (confirmedQuestionCount) {
            confirmedQuestionCount.textContent = aiPreviewQuestions.length;
        }
        var previewCountEl = document.getElementById("aiPreviewCount");
        if (previewCountEl) {
            previewCountEl.textContent = "Total Questions: " + aiPreviewQuestions.length;
        }
        if (btnReviewPreview) {
            btnReviewPreview.style.display = (aiPreviewQuestions.length > 0 && examMode && examMode.value === "Adaptive") ? "inline-block" : "none";
        }
        if (aiPreviewSection) {
            aiPreviewSection.style.display = (aiPreviewQuestions.length > 0 && examMode && examMode.value === "Adaptive") ? "block" : "none";
        }
    }

    function renderModalPreview() {
        var list = document.getElementById("modalQuestionsList");
        if (!list) return;
        list.innerHTML = "";

        var easyCount = 0;
        var mediumCount = 0;
        var hardCount = 0;

        aiPreviewQuestions.forEach(function (q, index) {
            var diff = q.difficulty || "Medium";
            if (diff === "Easy") easyCount++;
            else if (diff === "Hard") hardCount++;
            else mediumCount++;

            var diffBadge = diff === "Easy" ? "bg-success" : (diff === "Medium" ? "bg-warning text-dark" : "bg-danger");
            var ans = (q.correct_answer || "A").toUpperCase();

            var card = document.createElement("div");
            card.className = "card shadow-sm border rounded-3 preview-q-card mb-3";
            card.setAttribute("data-index", index);
            card.id = "modalQCard_" + index;

            card.innerHTML = 
                '<div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">' +
                    '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                        '<span class="badge bg-dark fs-7">Question ' + (index + 1) + '</span>' +
                        '<span class="badge ' + diffBadge + ' fs-7">Difficulty: ' + escapeHtml(diff) + '</span>' +
                        '<span class="badge bg-primary-soft text-primary fs-7">' + (parseInt(q.marks) || 1) + ' Marks</span>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-primary js-regen-single-q" data-index="' + index + '">' +
                        '<i class="bi bi-arrow-repeat me-1"></i>Regenerate Question #' + (index + 1) +
                    '</button>' +
                '</div>' +
                '<div class="card-body p-4 bg-white">' +
                    '<div class="mb-3">' +
                        '<h6 class="fw-bold text-dark mb-0 fs-6">' + escapeHtml(q.question) + '</h6>' +
                    '</div>' +
                    '<div class="row g-2 mb-3">' +
                        '<div class="col-md-6">' +
                            '<div class="p-2 border rounded-2 ' + (ans === "A" ? "border-success bg-success-subtle fw-semibold" : "bg-light") + '">' +
                                '<span class="badge ' + (ans === "A" ? "bg-success" : "bg-secondary") + ' me-2">A</span> ' + escapeHtml(q.option_a) +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<div class="p-2 border rounded-2 ' + (ans === "B" ? "border-success bg-success-subtle fw-semibold" : "bg-light") + '">' +
                                '<span class="badge ' + (ans === "B" ? "bg-success" : "bg-secondary") + ' me-2">B</span> ' + escapeHtml(q.option_b) +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<div class="p-2 border rounded-2 ' + (ans === "C" ? "border-success bg-success-subtle fw-semibold" : "bg-light") + '">' +
                                '<span class="badge ' + (ans === "C" ? "bg-success" : "bg-secondary") + ' me-2">C</span> ' + escapeHtml(q.option_c) +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<div class="p-2 border rounded-2 ' + (ans === "D" ? "border-success bg-success-subtle fw-semibold" : "bg-light") + '">' +
                                '<span class="badge ' + (ans === "D" ? "bg-success" : "bg-secondary") + ' me-2">D</span> ' + escapeHtml(q.option_d) +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<span class="badge bg-success py-2 px-3"><i class="bi bi-check-circle-fill me-1"></i>Correct Answer: ' + escapeHtml(ans) + '</span>' +
                    '</div>' +
                '</div>';

            list.appendChild(card);
        });

        var totalBadge = document.getElementById("modalTotalBadge");
        var easyBadge = document.getElementById("modalEasyBadge");
        var medBadge = document.getElementById("modalMediumBadge");
        var hardBadge = document.getElementById("modalHardBadge");
        if (totalBadge) totalBadge.textContent = aiPreviewQuestions.length + " Questions";
        if (easyBadge) easyBadge.textContent = easyCount + " Easy";
        if (medBadge) medBadge.textContent = mediumCount + " Medium";
        if (hardBadge) hardBadge.textContent = hardCount + " Hard";

        syncAiQuestionsHidden();
        renderInPagePreview();
    }

    function renderInPagePreview() {
        var list = document.getElementById("aiQuestionPreviewList");
        if (!list) return;
        list.innerHTML = "";

        aiPreviewQuestions.forEach(function (q, index) {
            var diff = q.difficulty || "Medium";
            var diffBadge = diff === "Easy" ? "bg-success" : (diff === "Medium" ? "bg-warning text-dark" : "bg-danger");
            var ans = (q.correct_answer || "A").toUpperCase();

            var card = document.createElement("div");
            card.className = "card shadow-sm border rounded-3 mb-2";
            card.innerHTML = 
                '<div class="card-body p-3">' +
                    '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<span class="badge bg-dark">Q' + (index + 1) + '</span>' +
                            '<span class="badge ' + diffBadge + '">' + escapeHtml(diff) + '</span>' +
                            '<span class="badge bg-primary-soft text-primary">' + (parseInt(q.marks) || 1) + ' Mark</span>' +
                        '</div>' +
                        '<button type="button" class="btn btn-sm btn-outline-primary js-regen-single-q" data-index="' + index + '">' +
                            '<i class="bi bi-arrow-repeat me-1"></i>Regenerate' +
                        '</button>' +
                    '</div>' +
                    '<div class="fw-semibold text-dark mb-2">' + escapeHtml(q.question) + '</div>' +
                    '<div class="small text-muted mb-2">' +
                        '<strong>A:</strong> ' + escapeHtml(q.option_a) + ' | ' +
                        '<strong>B:</strong> ' + escapeHtml(q.option_b) + ' | ' +
                        '<strong>C:</strong> ' + escapeHtml(q.option_c) + ' | ' +
                        '<strong>D:</strong> ' + escapeHtml(q.option_d) +
                    '</div>' +
                    '<div><span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Correct: ' + escapeHtml(ans) + '</span></div>' +
                '</div>';

            list.appendChild(card);
        });
    }

    function showToast(message, icon) {
        if (typeof Swal !== "undefined") {
            Swal.mixin({
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            }).fire({ icon: icon || "success", title: message });
        } else {
            alert(message);
        }
    }

    function generateAdaptiveQuestions() {
        var subId = examSubject ? examSubject.value : "";
        if (!subId) {
            if (typeof Swal !== "undefined") {
                Swal.fire("Subject Required", "Please select an assigned subject first.", "warning");
            } else {
                alert("Please select an assigned subject first.");
            }
            if (examSubject) examSubject.focus();
            return;
        }

        var count = maxQuestions ? parseInt(maxQuestions.value, 10) : 9;
        if (isNaN(count) || count < 1) {
            count = 9;
            if (maxQuestions) maxQuestions.value = 9;
        }

        if (btnGenerateAdaptive) {
            btnGenerateAdaptive.disabled = true;
            btnGenerateAdaptive.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating ' + count + ' Questions...';
        }
        if (btnModalGenerateAgain) {
            btnModalGenerateAgain.disabled = true;
            btnModalGenerateAgain.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
        }

        var body = new URLSearchParams({
            ajax_action: "generate_adaptive_preview",
            subject_id: subId,
            total_questions: count
        });

        fetch("exams.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                throw new Error(data.message || "Failed to generate questions.");
            }
            aiPreviewQuestions = data.questions || [];
            var previewMpq = (typeof currentMpq === "function") ? currentMpq() : 1;
            aiPreviewQuestions.forEach(function(pq) { pq.marks = previewMpq; });
            renderModalPreview();

            var modalSubjectSummary = document.getElementById("modalSubjectSummary");
            if (modalSubjectSummary) {
                modalSubjectSummary.textContent = "Subject: " + (data.subject_name || "Assigned Subject") + " | " + aiPreviewQuestions.length + " questions generated";
            }

            if (previewModal) {
                previewModal.show();
            }
            showToast("Successfully generated " + aiPreviewQuestions.length + " questions!");
        })
        .catch(function(err) {
            if (typeof Swal !== "undefined") {
                Swal.fire("Generation Failed", err.message, "error");
            } else {
                alert(err.message);
            }
        })
        .finally(function() {
            if (btnGenerateAdaptive) {
                btnGenerateAdaptive.disabled = false;
                btnGenerateAdaptive.innerHTML = '<i class="bi bi-robot me-1"></i>Generate Questions';
            }
            if (btnModalGenerateAgain) {
                btnModalGenerateAgain.disabled = false;
                btnModalGenerateAgain.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Generate Again';
            }
        });
    }

    // Trigger Generation
    if (btnGenerateAdaptive) {
        btnGenerateAdaptive.addEventListener("click", generateAdaptiveQuestions);
    }
    if (btnModalGenerateAgain) {
        btnModalGenerateAgain.addEventListener("click", function() {
            generateAdaptiveQuestions();
        });
    }

    // Open Modal from preview buttons
    if (btnReviewPreview) {
        btnReviewPreview.addEventListener("click", function() {
            if (aiPreviewQuestions.length === 0) {
                generateAdaptiveQuestions();
                return;
            }
            renderModalPreview();
            if (previewModal) previewModal.show();
        });
    }
    if (btnOpenModalFromSection) {
        btnOpenModalFromSection.addEventListener("click", function() {
            renderModalPreview();
            if (previewModal) previewModal.show();
        });
    }

    // Single Question Regeneration Click (Delegated for both modal & in-page list)
    document.addEventListener("click", function(event) {
        var btn = event.target.closest(".js-regen-single-q");
        if (!btn) return;
        event.preventDefault();
        event.stopPropagation();

        var index = parseInt(btn.getAttribute("data-index"), 10);
        if (isNaN(index) || !aiPreviewQuestions[index]) return;

        var currentQ = aiPreviewQuestions[index];
        var subId = examSubject ? examSubject.value : "";
        if (!subId) {
            alert("Subject is required.");
            return;
        }

        btn.disabled = true;
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Regenerating...';

        var body = new URLSearchParams({
            ajax_action: "regenerate_ai_question",
            subject_id: subId,
            difficulty: currentQ.difficulty || "Medium",
            marks: currentQ.marks || 1,
            current_question: currentQ.question || "",
            current_options: JSON.stringify({
                option_a: currentQ.option_a || "",
                option_b: currentQ.option_b || "",
                option_c: currentQ.option_c || "",
                option_d: currentQ.option_d || ""
            }),
            question_id: index,
            generation_nonce: Date.now() + "_" + Math.random().toString(36).slice(2),
            existing_questions: JSON.stringify(aiPreviewQuestions)
        });

        fetch("exams.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                throw new Error(data.message || "Could not regenerate question.");
            }
            // Replace ONLY this question (keep panel Marks Per Question for consistency)!
            aiPreviewQuestions[index] = data.question;
            aiPreviewQuestions[index].marks = (typeof currentMpq === "function") ? currentMpq() : (parseInt(currentQ.marks) || 1);
            renderModalPreview();
            showToast("Question #" + (index + 1) + " regenerated (" + data.question.difficulty + ").");
        })
        .catch(function(err) {
            if (typeof Swal !== "undefined") {
                Swal.fire("Regeneration Failed", err.message, "error");
            } else {
                alert(err.message);
            }
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });

    // Confirm / Use Questions
    function handleConfirmQuestions() {
        if (!aiPreviewQuestions || aiPreviewQuestions.length === 0) {
            alert("No questions to confirm. Please generate questions first.");
            return;
        }
        syncAiQuestionsHidden();
        if (previewModal) {
            previewModal.hide();
        }

        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: "Questions Confirmed!",
                text: aiPreviewQuestions.length + " questions are ready. Would you like to create the exam now?",
                icon: "success",
                showCancelButton: true,
                confirmButtonText: "Create Exam Now",
                cancelButtonText: "Review Exam Form",
                confirmButtonColor: "#2563eb"
            }).then(function(result) {
                if (result.isConfirmed) {
                    if (formAction) formAction.value = "create_exam_confirmed";
                    if (examFormBuilder) examFormBuilder.submit();
                }
            });
        }
    }

    if (btnModalConfirmUse) {
        btnModalConfirmUse.addEventListener("click", handleConfirmQuestions);
    }
    if (confirmCreateExamBtn) {
        confirmCreateExamBtn.addEventListener("click", handleConfirmQuestions);
    }

    // Intercept form submit for Adaptive mode
    if (examFormBuilder) {
        var marksApplyConfirmed = false;
        examFormBuilder.addEventListener("submit", function(event) {
            var mode = examMode ? examMode.value : "Standard";
            if (typeof updateMarksPanel === "function") updateMarksPanel();
            // Client-side guard: Passing Marks must not exceed live Total Marks.
            var liveTotal = marksTotal ? (parseInt(marksTotal.value, 10) || 0) : 0;
            var livePass = passMarks ? (parseInt(passMarks.value, 10) || 0) : 0;
            var isAdaptiveCreate = (mode === "Adaptive" && !document.querySelector("input[name='edit_id']").value);
            if (!isAdaptiveCreate && liveTotal > 0 && livePass > liveTotal) {
                event.preventDefault();
                if (typeof Swal !== "undefined") {
                    Swal.fire("Invalid Passing Marks", "Passing marks (" + livePass + ") cannot exceed Total Marks (" + liveTotal + ").", "error");
                } else {
                    alert("Passing marks cannot exceed Total Marks (" + liveTotal + ").");
                }
                if (passMarks) passMarks.focus();
                return false;
            }
            // Confirm bulk marks update when selected bank questions carry different marks.
            var touchesBank = (mode !== "Adaptive") || document.querySelector("input[name='edit_id']").value;
            if (touchesBank && typeof countMixedMarks === "function" && typeof currentMpq === "function") {
                var mpqNow = currentMpq();
                var mixedNow = (mode === "Adaptive") ? 0 : countMixedMarks(mpqNow);
                if (mixedNow > 0 && !marksApplyConfirmed) {
                    var msg = "Saving will set " + mixedNow + " selected question(s) to " + mpqNow + " mark(s) each in the question bank (Total " + liveTotal + " marks). Continue?";
                    if (typeof Swal !== "undefined") {
                        event.preventDefault();
                        Swal.fire({ title: "Apply Marks Per Question?", text: msg, icon: "question", showCancelButton: true, confirmButtonText: "Yes, save exam", cancelButtonText: "Review" }).then(function(res) {
                            if (res.isConfirmed) {
                                marksApplyConfirmed = true;
                                examFormBuilder.submit();
                            }
                        });
                        return false;
                    } else if (!confirm(msg)) {
                        event.preventDefault();
                        return false;
                    }
                    marksApplyConfirmed = true;
                }
            }
            if (mode === "Adaptive" && !document.querySelector("input[name='edit_id']").value) {
                if (!aiPreviewQuestions || aiPreviewQuestions.length === 0) {
                    event.preventDefault();
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            title: "Questions Required",
                            text: "Please click 'Generate Questions' to create and review AI questions before saving.",
                            icon: "info",
                            confirmButtonText: "Generate Questions"
                        }).then(function(res) {
                            if (res.isConfirmed) {
                                generateAdaptiveQuestions();
                            }
                        });
                    } else {
                        alert("Please click 'Generate Questions' first.");
                    }
                    return false;
                }
                syncAiQuestionsHidden();
                if (formAction && formAction.value !== "create_exam_confirmed") {
                    formAction.value = "create_exam_confirmed";
                }
            }
        });
    }

    // Render initial preview if loaded from server
    if (aiPreviewQuestions && aiPreviewQuestions.length > 0) {
        renderModalPreview();
    }

    // Load subject questions on card click (Standard mode)
    document.querySelectorAll(".js-select-subject-card").forEach(function(card) {
        card.addEventListener("click", function() {
            var subId = this.getAttribute("data-subject-id");
            if (examSubject) examSubject.value = subId;
            document.querySelectorAll(".js-select-subject-card").forEach(c => c.classList.remove("is-active"));
            this.classList.add("is-active");
            loadQuestionsForSubject(subId);
        });
    });

    if (examSubject) {
        examSubject.addEventListener("change", function() {
            var subId = this.value;
            document.querySelectorAll(".js-select-subject-card").forEach(function(c) {
                if (c.getAttribute("data-subject-id") === subId) c.classList.add("is-active");
                else c.classList.remove("is-active");
            });
            if (subId) loadQuestionsForSubject(subId);
        });
    }

    var qToolbar = document.getElementById("questionToolbar");
    var qSearch = document.getElementById("fpQSearch");
    var qDifficulty = document.getElementById("fpQDifficulty");
    var qSelectAll = document.getElementById("fpQSelectAll");
    var qClear = document.getElementById("fpQClear");
    var qCount = document.getElementById("fpQCount");

    function fpQuestionCards() {
        return questionsContainer ? Array.prototype.slice.call(questionsContainer.querySelectorAll(".standard-question-card")) : [];
    }

    function updateQCount() {
        if (!qCount) return;
        var totalMarks = 0;
        fpQuestionCards().forEach(function(card) {
            var input = card.querySelector(".standard-question-checkbox");
            if (input && input.checked) {
                totalMarks += parseInt(card.getAttribute("data-marks") || "1", 10) || 0;
            }
        });
        qCount.textContent = selectedIds.length + " selected · " + totalMarks + " marks";
        if (typeof updateMarksPanel === "function") updateMarksPanel();
    }

    function applyQFilter() {
        var term = qSearch ? qSearch.value.toLowerCase().trim() : "";
        var diff = qDifficulty ? qDifficulty.value : "";
        var visible = 0;
        fpQuestionCards().forEach(function(card) {
            var ok = true;
            if (diff && card.getAttribute("data-difficulty") !== diff) ok = false;
            if (ok && term && (card.getAttribute("data-searchtext") || "").indexOf(term) < 0) ok = false;
            card.style.display = ok ? "" : "none";
            if (ok) visible++;
        });
        var emptyMsg = questionsContainer ? questionsContainer.querySelector(".fp-q-empty") : null;
        if (emptyMsg) emptyMsg.remove();
        if (visible === 0 && fpQuestionCards().length > 0 && questionsContainer) {
            var d = document.createElement("div");
            d.className = "fp-q-empty";
            d.innerHTML = '<i class="bi bi-search me-1"></i>No questions match the current filter.';
            questionsContainer.appendChild(d);
        }
    }

    function setCardChecked(card, checked) {
        var input = card.querySelector(".standard-question-checkbox");
        if (!input) return;
        var val = parseInt(input.value, 10);
        input.checked = checked;
        if (checked) {
            card.classList.add("is-selected");
            if (!selectedIds.includes(val)) selectedIds.push(val);
        } else {
            card.classList.remove("is-selected");
            selectedIds = selectedIds.filter(id => id !== val);
        }
    }

    if (qSearch) qSearch.addEventListener("input", applyQFilter);
    if (qDifficulty) qDifficulty.addEventListener("change", applyQFilter);
    if (qSelectAll) qSelectAll.addEventListener("click", function() {
        fpQuestionCards().forEach(function(card) {
            if (card.style.display !== "none") setCardChecked(card, true);
        });
        syncSelectedHiddenInputs();
        updateQCount();
    });
    if (qClear) qClear.addEventListener("click", function() {
        fpQuestionCards().forEach(function(card) { setCardChecked(card, false); });
        syncSelectedHiddenInputs();
        updateQCount();
    });

    function loadQuestionsForSubject(subId) {
        if (!questionsContainer) return;
        if (qToolbar) qToolbar.style.display = "none";
        questionsContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm me-2"></div>Loading questions...</div>';
        fetch("exams.php?ajax_action=load_subject_questions&subject_id=" + encodeURIComponent(subId) + "&selected_ids=" + encodeURIComponent(selectedIds.join(',')))
            .then(res => res.text())
            .then(html => {
                questionsContainer.innerHTML = html;
                bindQuestionCheckboxes();
                if (qToolbar && fpQuestionCards().length > 0) qToolbar.style.display = "flex";
                if (qSearch) qSearch.value = "";
                if (qDifficulty) qDifficulty.value = "";
                applyQFilter();
                updateQCount();
            })
            .catch(err => {
                questionsContainer.innerHTML = '<div class="text-danger text-center py-2">Error loading questions.</div>';
                if (qToolbar) qToolbar.style.display = "none";
            });
    }

    function bindQuestionCheckboxes() {
        document.querySelectorAll(".standard-question-checkbox").forEach(function(chk) {
            chk.addEventListener("change", function() {
                var card = this.closest(".standard-question-card");
                var val = parseInt(this.value);
                if (this.checked) {
                    if (card) card.classList.add("is-selected");
                    if (!selectedIds.includes(val)) selectedIds.push(val);
                } else {
                    if (card) card.classList.remove("is-selected");
                    selectedIds = selectedIds.filter(id => id !== val);
                }
                syncSelectedHiddenInputs();
                updateQCount();
            });
        });
        updateQCount();
    }

    function syncSelectedHiddenInputs() {
        var existingInputs = document.querySelectorAll("input[name='questions[]']");
        existingInputs.forEach(i => i.remove());
        var form = document.getElementById("examFormBuilder");
        if (!form) return;
        selectedIds.forEach(id => {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "questions[]";
            input.value = id;
            form.appendChild(input);
        });
    }

    // Load initial subject questions if subject selected
    if (examSubject && examSubject.value) {
        loadQuestionsForSubject(examSubject.value);
    }

    /* ─── Academic Scope: Course + Semester -> Subjects + eligible count ─── */
    var examCourse = document.getElementById("examCourse");
    var examSemester = document.getElementById("examSemester");
    var scopeBox = document.getElementById("fpScopeBox");
    var scopeCourse = document.getElementById("fpScopeCourse");
    var scopeSemester = document.getElementById("fpScopeSemester");
    var scopeSubject = document.getElementById("fpScopeSubject");
    var eligibleCount = document.getElementById("fpEligibleCount");
    var subjectHint = document.getElementById("fpSubjectHint");
    var scopeLocked = <?php echo $edit_scope_locked ? "true" : "false"; ?>;

    function refreshScopeBox() {
        if (!scopeBox) return;
        var c = examCourse ? examCourse.value : "";
        var s = examSemester ? examSemester.value : "";
        if (scopeCourse) scopeCourse.textContent = c || "—";
        if (scopeSemester) scopeSemester.textContent = s || "—";
        if (scopeSubject && examSubject) {
            var opt = examSubject.options[examSubject.selectedIndex];
            scopeSubject.textContent = (examSubject.value && opt) ? opt.text.replace(/\s*\(.*?\)\s*$/, "") : "—";
        }
    }

    function refreshEligibleCount() {
        if (!eligibleCount || !examCourse || !examSemester) return;
        var c = examCourse.value, s = examSemester.value;
        if (!c || !s) { eligibleCount.textContent = "—"; return; }
        eligibleCount.textContent = "…";
        fetch("exams.php?ajax_action=eligible_count&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s))
            .then(r => r.json())
            .then(d => { eligibleCount.textContent = d.success ? d.count : "—"; })
            .catch(() => { eligibleCount.textContent = "—"; });
    }

    function reloadScopeSubjects(preserveSelected) {
        if (!examSubject || !examCourse || !examSemester || scopeLocked) { refreshScopeBox(); refreshEligibleCount(); return; }
        var c = examCourse.value, s = examSemester.value;
        var keepId = preserveSelected ? examSubject.value : "";
        refreshScopeBox(); refreshEligibleCount();
        if (!c || !s) {
            if (subjectHint) subjectHint.textContent = "Select a course and semester to load eligible subjects.";
            return;
        }
        examSubject.innerHTML = '<option value="">Loading subjects...</option>';
        if (subjectHint) subjectHint.textContent = "Loading subjects...";
        fetch("exams.php?ajax_action=subjects_for_scope&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s))
            .then(r => r.json())
            .then(d => {
                examSubject.innerHTML = '<option value="">Select Assigned Subject</option>';
                var restored = false;
                if (d.success && d.subjects && d.subjects.length) {
                    d.subjects.forEach(sub => {
                        var o = document.createElement("option");
                        o.value = sub.id;
                        o.textContent = sub.subject_name + " (" + (sub.subject_code || "") + ")";
                        if (keepId && String(sub.id) === String(keepId)) { o.selected = true; restored = true; }
                        examSubject.appendChild(o);
                    });
                    if (subjectHint) subjectHint.textContent = d.subjects.length + " assigned subject(s) available for this scope.";
                } else {
                    if (subjectHint) subjectHint.textContent = "No assigned subjects found for this academic scope.";
                }
                if (keepId && !restored) {
                    examSubject.value = "";
                    selectedIds = [];
                    if (typeof syncSelectedHiddenInputs === "function") syncSelectedHiddenInputs();
                    if (questionsContainer) questionsContainer.innerHTML = '<div class="empty-state text-center p-4 bg-light rounded-3">Select a subject above to load and pick questions.</div>';
                } else if (restored && examSubject.value) {
                    loadQuestionsForSubject(examSubject.value);
                }
                refreshScopeBox();
            })
            .catch(() => {
                examSubject.innerHTML = '<option value="">Select Assigned Subject</option>';
                if (subjectHint) subjectHint.textContent = "Could not load subjects. Please try again.";
            });
    }

    if (examCourse) examCourse.addEventListener("change", function() { reloadScopeSubjects(false); });
    if (examSemester) examSemester.addEventListener("change", function() { reloadScopeSubjects(false); });
    if (examSubject) examSubject.addEventListener("change", function() { refreshScopeBox(); });
    // Init: if course+semester already chosen (validation re-render), filter subjects at once
    if (!scopeLocked && examCourse && examSemester && examCourse.value && examSemester.value) {
        reloadScopeSubjects(true);
    } else {
        refreshScopeBox();
        refreshEligibleCount();
    }
});
</script>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>

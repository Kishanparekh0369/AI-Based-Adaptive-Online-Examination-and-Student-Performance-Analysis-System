<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Question Bank";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

// AJAX: regenerate a single bank question with Groq AI (preview only, nothing saved yet)
if (isset($_GET["ajax_action"]) && $_GET["ajax_action"] === "regenerate_question") {
    header("Content-Type: application/json; charset=UTF-8");
    $regen_qid = (int)($_GET["qid"] ?? 0);
    $regen_diff = clean_data($_GET["difficulty"] ?? "");
    if (!in_array($regen_diff, array("Easy", "Medium", "Hard"), true)) { $regen_diff = ""; }
    $regen_row = $regen_qid > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT q.*, s.subject_name FROM questions q LEFT JOIN subjects s ON s.id=q.subject_id WHERE q.id=$regen_qid LIMIT 1")) : null;
    if (!$regen_row || !faculty_has_subject($faculty_id, (int)$regen_row["subject_id"])) {
        echo json_encode(array("success" => false, "message" => "Access denied or question not found."));
        exit;
    }
    if ($regen_diff === "") {
        $regen_diff = in_array($regen_row["difficulty"], array("Easy", "Medium", "Hard"), true) ? $regen_row["difficulty"] : "Medium";
    }
    require_once("../app/ai/QuestionGenerator.php");
    $regen_generator = new QuestionGenerator();
    $regen_new = $regen_generator->generateMCQ(
        $regen_row["subject_name"] ?? "General",
        "",
        $regen_diff,
        $regen_row["language"] ?? "English",
        max(1, (int)$regen_row["marks"]),
        false,
        array(),
        $regen_row["question"],
        array("A" => $regen_row["option_a"], "B" => $regen_row["option_b"], "C" => $regen_row["option_c"], "D" => $regen_row["option_d"]),
        "regen_q" . $regen_qid . "_" . time()
    );
    if (!$regen_new) {
        echo json_encode(array("success" => false, "message" => $regen_generator->getLastError() ?: "AI could not regenerate this question right now. Please try again."));
        exit;
    }
    echo json_encode(array("success" => true, "question" => array(
        "question" => $regen_new["question"],
        "option_a" => $regen_new["option_a"],
        "option_b" => $regen_new["option_b"],
        "option_c" => $regen_new["option_c"],
        "option_d" => $regen_new["option_d"],
        "correct_answer" => $regen_new["correct_answer"],
        "difficulty" => $regen_new["difficulty"],
    )));
    exit;
}

// Handle Delete Question
if (isset($_GET["delete"])) {
    $id = (int)clean_data($_GET["delete"]);
    $check = mysqli_query($conn, "SELECT subject_id FROM questions WHERE id=$id LIMIT 1");
    if ($check && $row = mysqli_fetch_assoc($check)) {
        if (!faculty_has_subject($faculty_id, (int)$row["subject_id"])) {
            redirect_alert("Access Denied: You cannot delete questions from unauthorized subjects.", "questions.php", "error");
        }
        mysqli_query($conn, "DELETE FROM exam_questions WHERE question_id=$id");
        mysqli_query($conn, "DELETE FROM questions WHERE id=$id");
        redirect_alert("Question deleted successfully", "questions.php", "success");
    } else {
        redirect_alert("Question not found", "questions.php", "error");
    }
}

// Handle Bulk Delete Questions (selected checkboxes)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["bulk_delete"])) {
    $ids = isset($_POST["qid"]) && is_array($_POST["qid"]) ? $_POST["qid"] : array();
    $ids = array_values(array_unique(array_map("intval", $ids)));
    if (empty($ids)) {
        redirect_alert("Please select at least one question to delete", "questions.php", "error");
    }
    $deleted = 0;
    foreach ($ids as $qid) {
        if ($qid <= 0) { continue; }
        $chk = mysqli_query($conn, "SELECT subject_id FROM questions WHERE id=$qid LIMIT 1");
        if ($chk && ($chk_row = mysqli_fetch_assoc($chk)) && faculty_has_subject($faculty_id, (int)$chk_row["subject_id"])) {
            mysqli_query($conn, "DELETE FROM exam_questions WHERE question_id=$qid");
            if (mysqli_query($conn, "DELETE FROM questions WHERE id=$qid LIMIT 1")) { $deleted++; }
        }
    }
    if ($deleted > 0) {
        redirect_alert($deleted . " question" . ($deleted === 1 ? "" : "s") . " deleted successfully", "questions.php", "success");
    }
    redirect_alert("No questions were deleted. Access denied for the selected items.", "questions.php", "error");
}

// Handle Add / Edit Question Form POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $edit_id = (int)clean_data($_POST["edit_id"] ?? 0);
    $subject_id = (int)clean_data($_POST["subject_id"] ?? 0);
    $question = clean_data($_POST["question"] ?? "");
    $language = clean_data($_POST["language"] ?? "English");
    $option_a = clean_data($_POST["option_a"] ?? "");
    $option_b = clean_data($_POST["option_b"] ?? "");
    $option_c = clean_data($_POST["option_c"] ?? "");
    $option_d = clean_data($_POST["option_d"] ?? "");
    $correct_answer = clean_data($_POST["correct_answer"] ?? "A");
    $difficulty = clean_data($_POST["difficulty"] ?? "Medium");
    $marks = (int)clean_data($_POST["marks"] ?? 1);
    $keywords = clean_data($_POST["keywords"] ?? "");

    if (!faculty_has_subject($faculty_id, $subject_id)) {
        redirect_alert("Access Denied: You cannot manage questions for unauthorized subjects.", "questions.php", "error");
    }

    if ($question == "" || $option_a == "" || $option_b == "" || $option_c == "" || $option_d == "" || $marks <= 0) {
        redirect_alert("All question and option fields are required", "questions.php", "error");
    }

    $q_safe = mysqli_real_escape_string($conn, $question);
    $l_safe = mysqli_real_escape_string($conn, $language);
    $oa_safe = mysqli_real_escape_string($conn, $option_a);
    $ob_safe = mysqli_real_escape_string($conn, $option_b);
    $oc_safe = mysqli_real_escape_string($conn, $option_c);
    $od_safe = mysqli_real_escape_string($conn, $option_d);
    $ca_safe = mysqli_real_escape_string($conn, $correct_answer);
    $diff_safe = mysqli_real_escape_string($conn, $difficulty);
    $kw_safe = mysqli_real_escape_string($conn, $keywords);

    if ($edit_id > 0) {
        $check_existing = mysqli_query($conn, "SELECT subject_id FROM questions WHERE id=$edit_id LIMIT 1");
        if ($check_existing && $ex_row = mysqli_fetch_assoc($check_existing)) {
            if (!faculty_has_subject($faculty_id, (int)$ex_row["subject_id"])) {
                redirect_alert("Access Denied: You cannot modify questions of unauthorized subjects.", "questions.php", "error");
            }
        }
        $update_sql = "UPDATE questions SET subject_id=$subject_id, faculty_id=$faculty_id, question='$q_safe', language='$l_safe', option_a='$oa_safe', option_b='$ob_safe', option_c='$oc_safe', option_d='$od_safe', correct_answer='$ca_safe', difficulty='$diff_safe', marks=$marks, keywords='$kw_safe'" . (isset($_POST["ai_regenerated"]) && $_POST["ai_regenerated"] == "1" ? ", ai_generated=1" : "") . " WHERE id=$edit_id";
        if (mysqli_query($conn, $update_sql)) {
            redirect_alert("Question updated successfully", "questions.php?subject_id=" . $subject_id, "success");
        } else {
            redirect_alert("Update failed: " . mysqli_error($conn), "questions.php", "error");
        }
    } else {
        $insert_sql = "INSERT INTO questions (subject_id, faculty_id, question, language, option_a, option_b, option_c, option_d, correct_answer, difficulty, marks, keywords) VALUES ($subject_id, $faculty_id, '$q_safe', '$l_safe', '$oa_safe', '$ob_safe', '$oc_safe', '$od_safe', '$ca_safe', '$diff_safe', $marks, '$kw_safe')";
        if (mysqli_query($conn, $insert_sql)) {
            redirect_alert("Question inserted successfully", "questions.php?subject_id=" . $subject_id, "success");
        } else {
            redirect_alert("Insert failed: " . mysqli_error($conn), "questions.php", "error");
        }
    }
}

// Handle AI Generate directly into bank
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ai_generate_bank"])) {
    $gen_subject = (int)($_POST["gen_subject_id"] ?? 0);
    $gen_count = min(max((int)($_POST["gen_count"] ?? 5), 1), 20);
    $gen_diff = clean_data($_POST["gen_difficulty"] ?? "Mixed");
    if (!in_array($gen_diff, array("Mixed", "Easy", "Medium", "Hard"), true)) {
        $gen_diff = "Mixed";
    }
    if (!faculty_has_subject($faculty_id, $gen_subject)) {
        redirect_alert("Access Denied: You cannot generate questions for unauthorized subjects.", "questions.php", "error");
    }
    require_once("../app/ai/QuestionGenerator.php");
    $generator = new QuestionGenerator();
    if ($gen_diff === "Mixed") {
        $diffs = QuestionGenerator::calculateDifficultyDistribution($gen_count);
    } else {
        $diffs = array_fill(0, $gen_count, $gen_diff);
    }
    $gen_sub_name = "General";
    $gen_sub_res = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id=$gen_subject LIMIT 1");
    if ($gen_sub_res && $gen_sub_row = mysqli_fetch_assoc($gen_sub_res)) {
        $gen_sub_name = $gen_sub_row["subject_name"];
    }
    $generated = $generator->generatePreviewMCQs($gen_sub_name, $diffs, "English", 1);
    $saved = 0;
    foreach ($generated as $gq) {
        if (!is_array($gq) || trim($gq["question"] ?? "") === "") {
            continue;
        }
        $ans = strtoupper(trim($gq["correct_answer"] ?? "A"));
        if (!in_array($ans, array("A", "B", "C", "D"))) {
            $gq["correct_answer"] = "A";
        }
        if ($generator->saveToDatabase($gen_subject, $gq)) {
            $saved++;
        }
    }
    if ($saved > 0) {
        redirect_alert($saved . " AI question" . ($saved === 1 ? "" : "s") . " added to the bank.", "questions.php?subject_id=" . $gen_subject, "success");
    } else {
        redirect_alert($generator->getLastError() ?: "AI could not generate questions right now. Please try again.", "questions.php?subject_id=" . $gen_subject, "error");
    }
}

include("../app/layouts/faculty_header.php");

// Fetch assigned subjects list
$subjects = mysqli_query($conn, "SELECT s.* FROM subjects s INNER JOIN faculty_subjects fs ON s.id=fs.subject_id WHERE fs.faculty_id=$faculty_id AND s.status='Active' ORDER BY s.subject_name ASC");
$subjects_list = [];
if ($subjects) {
    while ($sub_row = mysqli_fetch_assoc($subjects)) {
        $subjects_list[] = $sub_row;
    }
}

$search = isset($_GET["search"]) ? clean_data($_GET["search"]) : "";
$filter_subject = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;
$filter_language = isset($_GET["language"]) ? clean_data($_GET["language"]) : "";
$filter_difficulty = isset($_GET["difficulty"]) ? clean_data($_GET["difficulty"]) : "";
$filter_marks = isset($_GET["marks"]) ? clean_data($_GET["marks"]) : "";
$filter_source = isset($_GET["source"]) ? clean_data($_GET["source"]) : "";
if (!in_array($filter_source, array("", "manual", "ai"), true)) { $filter_source = ""; }
$filter_answer = isset($_GET["answer"]) ? strtoupper(trim(clean_data($_GET["answer"]))) : "";
if (!in_array($filter_answer, array("", "A", "B", "C", "D"), true)) { $filter_answer = ""; }
$sort_options = array(
    "newest" => "q.id DESC",
    "oldest" => "q.id ASC",
    "marks_desc" => "q.marks DESC, q.id DESC",
    "marks_asc" => "q.marks ASC, q.id DESC",
    "az" => "q.question ASC"
);
$sort_labels = array(
    "newest" => "Newest first",
    "oldest" => "Oldest first",
    "marks_desc" => "Marks: high to low",
    "marks_asc" => "Marks: low to high",
    "az" => "Question A to Z"
);
$sort = isset($_GET["sort"]) ? clean_data($_GET["sort"]) : "newest";
if (!isset($sort_options[$sort])) { $sort = "newest"; }
$per_page_options = array(10, 25, 50, 100);
$per_page = isset($_GET["per_page"]) ? (int)$_GET["per_page"] : 10;
if (!in_array($per_page, $per_page_options, true)) { $per_page = 10; }
$page = isset($_GET["page"]) ? max((int)$_GET["page"], 1) : 1;
$offset = ($page - 1) * $per_page;

$where = " q.subject_id IN ($subject_ids_csv) ";
if ($search !== "") {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (q.question LIKE '%$search_safe%' OR s.subject_name LIKE '%$search_safe%' OR q.difficulty LIKE '%$search_safe%' OR q.keywords LIKE '%$search_safe%') ";
}
if ($filter_subject > 0 && faculty_has_subject($faculty_id, $filter_subject)) {
    $where .= " AND q.subject_id=$filter_subject ";
}
if ($filter_language != "") {
    $where .= " AND q.language='" . mysqli_real_escape_string($conn, $filter_language) . "' ";
}
if ($filter_difficulty != "") {
    $where .= " AND q.difficulty='" . mysqli_real_escape_string($conn, $filter_difficulty) . "' ";
}
if ($filter_marks != "") {
    $where .= " AND q.marks=" . (int)$filter_marks . " ";
}
if ($filter_source === "ai") {
    $where .= " AND q.ai_generated=1 ";
} elseif ($filter_source === "manual") {
    $where .= " AND (q.ai_generated IS NULL OR q.ai_generated=0) ";
}
if ($filter_answer != "") {
    $where .= " AND q.correct_answer='" . mysqli_real_escape_string($conn, $filter_answer) . "' ";
}
$order_by = $sort_options[$sort];

$total_question_rows = 0;
$count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM questions q LEFT JOIN subjects s ON q.subject_id=s.id WHERE $where");
if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
    $total_question_rows = (int)$count_row["total"];
}
$total_pages = max(ceil($total_question_rows / $per_page), 1);

$questions_result = mysqli_query($conn, "SELECT q.*, s.subject_name FROM questions q LEFT JOIN subjects s ON q.subject_id=s.id WHERE $where ORDER BY $order_by LIMIT $per_page OFFSET $offset");
$questions_data = [];
if ($questions_result) {
    while ($q_row = mysqli_fetch_assoc($questions_result)) {
        $questions_data[] = $q_row;
    }
}

$subject_cards = mysqli_query($conn, "SELECT s.*, COUNT(q.id) AS question_count, COUNT(DISTINCT q.language) AS language_count, COUNT(DISTINCT q.difficulty) AS difficulty_count
    FROM subjects s
    INNER JOIN faculty_subjects fs ON s.id=fs.subject_id
    LEFT JOIN questions q ON q.subject_id=s.id
    WHERE fs.faculty_id=$faculty_id AND s.status='Active'
    GROUP BY s.id ORDER BY s.id ASC");

// Header stat row (scope-wide, same tables)
$fp_qstats = array("total" => 0, "easy" => 0, "medium" => 0, "hard" => 0, "ai" => 0);
$fp_qstat_res = mysqli_query($conn, "SELECT COUNT(*) AS total,
    SUM(difficulty='Easy') AS easy, SUM(difficulty='Medium') AS medium, SUM(difficulty='Hard') AS hard,
    SUM(ai_generated=1) AS ai FROM questions WHERE subject_id IN ($subject_ids_csv)");
if ($fp_qstat_res && $fp_qstat_row = mysqli_fetch_assoc($fp_qstat_res)) {
    $fp_qstats["total"] = (int)$fp_qstat_row["total"];
    $fp_qstats["easy"] = (int)($fp_qstat_row["easy"] ?? 0);
    $fp_qstats["medium"] = (int)($fp_qstat_row["medium"] ?? 0);
    $fp_qstats["hard"] = (int)($fp_qstat_row["hard"] ?? 0);
    $fp_qstats["ai"] = (int)($fp_qstat_row["ai"] ?? 0);
}

function fp_qs($overrides = array())
{
    $base = array(
        "search" => isset($_GET["search"]) ? (string)$_GET["search"] : "",
        "subject_id" => isset($_GET["subject_id"]) ? (string)$_GET["subject_id"] : "",
        "language" => isset($_GET["language"]) ? (string)$_GET["language"] : "",
        "difficulty" => isset($_GET["difficulty"]) ? (string)$_GET["difficulty"] : "",
        "marks" => isset($_GET["marks"]) ? (string)$_GET["marks"] : "",
        "source" => isset($_GET["source"]) ? (string)$_GET["source"] : "",
        "answer" => isset($_GET["answer"]) ? (string)$_GET["answer"] : "",
        "sort" => isset($_GET["sort"]) ? (string)$_GET["sort"] : "",
        "per_page" => isset($_GET["per_page"]) ? (string)$_GET["per_page"] : ""
    );
    foreach ($overrides as $k => $v) { $base[$k] = (string)$v; }
    $out = array();
    foreach ($base as $k => $v) { if ($v !== "") { $out[$k] = $v; } }
    return http_build_query($out);
}

$fp_has_filters = ($search !== "" || $filter_subject > 0 || $filter_language !== "" || $filter_difficulty !== "" || $filter_marks !== "" || $filter_source !== "" || $filter_answer !== "");
$fp_show_from = $total_question_rows > 0 ? ($offset + 1) : 0;
$fp_show_to = min($offset + $per_page, $total_question_rows);
$fp_active_filters = array();
if ($search !== "") { $fp_active_filters[] = array("label" => "Search: " . $search, "qs" => fp_qs(array("search" => ""))); }
if ($filter_subject > 0) {
    $fp_sub_name = "Subject";
    foreach ($subjects_list as $sl) { if ((int)$sl["id"] === $filter_subject) { $fp_sub_name = $sl["subject_name"]; break; } }
    $fp_active_filters[] = array("label" => "Subject: " . $fp_sub_name, "qs" => fp_qs(array("subject_id" => "")));
}
if ($filter_language !== "") { $fp_active_filters[] = array("label" => "Language: " . $filter_language, "qs" => fp_qs(array("language" => ""))); }
if ($filter_difficulty !== "") { $fp_active_filters[] = array("label" => "Difficulty: " . $filter_difficulty, "qs" => fp_qs(array("difficulty" => ""))); }
if ($filter_marks !== "") { $fp_active_filters[] = array("label" => "Marks: " . $filter_marks, "qs" => fp_qs(array("marks" => ""))); }
if ($filter_source !== "") { $fp_active_filters[] = array("label" => "Source: " . ($filter_source === "ai" ? "AI Generated" : "Manual"), "qs" => fp_qs(array("source" => ""))); }
if ($filter_answer !== "") { $fp_active_filters[] = array("label" => "Answer: " . $filter_answer, "qs" => fp_qs(array("answer" => ""))); }
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Question Bank</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-patch-question"></i> Question Bank</span>
                    <h1>Manage MCQs</h1>
                    <p>Create, filter and manage multiple-choice questions for your assigned subjects.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="sp-btn sp-btn-ghost" data-fp-refresh><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                    <button type="button" class="sp-btn sp-btn-soft" data-bs-toggle="modal" data-bs-target="#aiGenerateModal">
                        <i class="bi bi-stars"></i> Generate with AI
                    </button>
                    <button class="sp-btn sp-btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                        <i class="bi bi-plus-circle"></i> Add Manual Question
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <?php
                $fp_stat_links = array(
                    "questions.php",
                    "questions.php?difficulty=Easy",
                    "questions.php?difficulty=Medium",
                    "questions.php?difficulty=Hard",
                    "questions.php?source=ai"
                );
                ?>
                <div class="col-6 col-lg sp-fade-up" data-delay="1">
                    <a href="<?php echo $fp_stat_links[0]; ?>" class="text-decoration-none d-block h-100" title="Show all questions">
                    <div class="sp-stat h-100">
                        <span class="sp-stat-icon"><i class="bi bi-collection"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_qstats["total"]; ?>">0</div>
                        <div class="sp-stat-label">Total Questions</div>
                    </div>
                    </a>
                </div>
                <div class="col-6 col-lg sp-fade-up" data-delay="2">
                    <a href="<?php echo $fp_stat_links[1]; ?>" class="text-decoration-none d-block h-100" title="Show only Easy questions">
                    <div class="sp-stat is-success h-100">
                        <span class="sp-stat-icon"><i class="bi bi-emoji-smile"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_qstats["easy"]; ?>">0</div>
                        <div class="sp-stat-label">Easy</div>
                    </div>
                    </a>
                </div>
                <div class="col-6 col-lg sp-fade-up" data-delay="3">
                    <a href="<?php echo $fp_stat_links[2]; ?>" class="text-decoration-none d-block h-100" title="Show only Medium questions">
                    <div class="sp-stat is-warning h-100">
                        <span class="sp-stat-icon"><i class="bi bi-emoji-neutral"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_qstats["medium"]; ?>">0</div>
                        <div class="sp-stat-label">Medium</div>
                    </div>
                    </a>
                </div>
                <div class="col-6 col-lg sp-fade-up" data-delay="4">
                    <a href="<?php echo $fp_stat_links[3]; ?>" class="text-decoration-none d-block h-100" title="Show only Hard questions">
                    <div class="sp-stat is-danger h-100">
                        <span class="sp-stat-icon"><i class="bi bi-emoji-frown"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_qstats["hard"]; ?>">0</div>
                        <div class="sp-stat-label">Hard</div>
                    </div>
                    </a>
                </div>
                <div class="col-6 col-lg sp-fade-up" data-delay="5">
                    <a href="<?php echo $fp_stat_links[4]; ?>" class="text-decoration-none d-block h-100" title="Show only AI generated questions">
                    <div class="sp-stat is-indigo h-100">
                        <span class="sp-stat-icon"><i class="bi bi-robot"></i></span>
                        <div class="sp-stat-value" data-count="<?php echo $fp_qstats["ai"]; ?>">0</div>
                        <div class="sp-stat-label">AI Generated</div>
                    </div>
                    </a>
                </div>
            </div>

            <!-- Subject Cards Filter -->
            <div class="row g-3 mb-4">
                <?php if ($subject_cards && mysqli_num_rows($subject_cards) > 0) {
                    $fp_ci = 0;
                    while ($card = mysqli_fetch_assoc($subject_cards)) {
                        $fp_ci++;
                        $is_active = ($filter_subject == (int)$card["id"]);
                ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min(($fp_ci % 3) + 1, 6); ?>">
                        <a class="subject-card d-block p-3 h-100 <?php echo $is_active ? 'border border-2 border-primary shadow-sm' : ''; ?>" href="questions.php?subject_id=<?php echo (int)$card["id"]; ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="admin-card-icon me-2 flex-shrink-0"><i class="bi bi-journal-text"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="mb-1 fw-bold text-truncate"><?php echo h($card["subject_name"]); ?></h6>
                                    <small class="text-muted"><?php echo h($card["subject_code"]); ?></small>
                                </div>
                                <span class="sp-pill is-available flex-shrink-0"><i class="bi bi-patch-question"></i><?php echo (int)$card["question_count"]; ?></span>
                            </div>
                            <div class="difficulty-summary mt-2 d-flex flex-wrap gap-1">
                                <span class="badge bg-info text-dark"><?php echo (int)$card["language_count"]; ?> Languages</span>
                                <span class="badge bg-secondary"><?php echo (int)$card["difficulty_count"]; ?> Levels</span>
                                <?php if ($is_active) { ?><span class="badge bg-primary">Filtered</span><?php } ?>
                            </div>
                        </a>
                    </div>
                <?php }} ?>
            </div>

            <!-- Search & Filters Toolbar -->
            <form method="get" class="sp-filters sp-fade-up" id="fpQuestionFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="fpQSearch">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" id="fpQSearch" name="search" value="<?php echo h($search); ?>" placeholder="Question, keywords or subject">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="fpQSubject">Subject</label>
                        <select name="subject_id" id="fpQSubject" class="form-select">
                            <option value="">All Assigned Subjects</option>
                            <?php foreach ($subjects_list as $sub) { ?>
                                <option value="<?php echo (int)$sub["id"]; ?>" <?php if ($filter_subject == (int)$sub["id"]) echo "selected"; ?>>
                                    <?php echo h($sub["subject_name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpQSource">Source</label>
                        <select name="source" id="fpQSource" class="form-select">
                            <option value="">All Sources</option>
                            <option value="manual" <?php if ($filter_source === "manual") echo "selected"; ?>>Manual</option>
                            <option value="ai" <?php if ($filter_source === "ai") echo "selected"; ?>>AI Generated</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpQAnswer">Answer</label>
                        <select name="answer" id="fpQAnswer" class="form-select">
                            <option value="">Any Key</option>
                            <?php foreach (array("A", "B", "C", "D") as $a_opt) { ?>
                                <option value="<?php echo $a_opt; ?>" <?php if ($filter_answer === $a_opt) echo "selected"; ?>>Option <?php echo $a_opt; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpQLang">Language</label>
                        <select name="language" id="fpQLang" class="form-select">
                            <option value="">All</option>
                            <?php foreach (array("English", "Hindi", "Gujarati") as $l_opt) { ?>
                                <option value="<?php echo $l_opt; ?>" <?php if ($filter_language == $l_opt) echo "selected"; ?>><?php echo $l_opt; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpQDiff">Difficulty</label>
                        <select name="difficulty" id="fpQDiff" class="form-select">
                            <option value="">All</option>
                            <option value="Easy" <?php if ($filter_difficulty == "Easy") echo "selected"; ?>>Easy</option>
                            <option value="Medium" <?php if ($filter_difficulty == "Medium") echo "selected"; ?>>Medium</option>
                            <option value="Hard" <?php if ($filter_difficulty == "Hard") echo "selected"; ?>>Hard</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpQMarks">Marks</label>
                        <input type="number" class="form-control" id="fpQMarks" name="marks" value="<?php echo h($filter_marks); ?>" min="1" placeholder="Any">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fpQSort">Sort By</label>
                        <select name="sort" id="fpQSort" class="form-select">
                            <?php foreach ($sort_labels as $s_key => $s_label) { ?>
                                <option value="<?php echo $s_key; ?>" <?php if ($sort === $s_key) echo "selected"; ?>><?php echo h($s_label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small fw-semibold mb-1" for="fpQPerPage">Rows</label>
                        <select name="per_page" id="fpQPerPage" class="form-select">
                            <?php foreach ($per_page_options as $pp_opt) { ?>
                                <option value="<?php echo $pp_opt; ?>" <?php if ($per_page === $pp_opt) echo "selected"; ?>><?php echo $pp_opt; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1 d-flex gap-2">
                        <button type="submit" class="sp-btn sp-btn-primary w-100" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
                        <button type="button" class="sp-btn sp-btn-ghost" data-fp-refresh aria-label="Refresh list"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>
                </div>
                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="sp-filter-count">Showing <?php echo $fp_show_from; ?>&ndash;<?php echo $fp_show_to; ?> of <?php echo $total_question_rows; ?> questions</span>
                    <?php if ($fp_has_filters) { ?>
                        <a href="questions.php" class="fp-clear-link"><i class="bi bi-x-circle me-1"></i>Clear all</a>
                    <?php } ?>
                </div>
                <?php if (!empty($fp_active_filters)) { ?>
                    <div class="fp-active-pills">
                        <?php foreach ($fp_active_filters as $af) { ?>
                            <a class="fp-active-pill" href="questions.php?<?php echo h($af["qs"]); ?>" title="Remove this filter"><?php echo h($af["label"]); ?><i class="bi bi-x"></i></a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </form>

            <!-- Questions Table -->
            <div class="table-card p-3 sp-fade-up" id="fpQuestionCard">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-patch-question me-2 text-primary"></i>Questions List (<?php echo $total_question_rows; ?>)</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group" style="max-width: 230px;">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input class="form-control" data-table-search="#facultyQuestionsTable" placeholder="Filter current page..." aria-label="Filter current page">
                        </div>
                        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" id="fpDensityBtn" title="Toggle compact view" aria-label="Toggle compact view" aria-pressed="false"><i class="bi bi-list"></i><span class="d-none d-sm-inline"> Compact</span></button>
                    </div>
                </div>

                <form method="post" id="fpBulkForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <div class="fp-bulkbar d-none" id="fpBulkBar" role="status" aria-live="polite">
                        <strong><span id="fpBulkCount">0</span> selected</strong>
                        <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" id="fpBulkDeleteBtn"><i class="bi bi-trash"></i> Delete selected</button>
                        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" id="fpBulkClearBtn">Clear</button>
                    </div>

                <div class="table-responsive">
                <table class="table table-hover align-middle sp-table" id="facultyQuestionsTable">
                    <thead>
                        <tr>
                            <th style="width:38px;"><input type="checkbox" class="form-check-input" id="fpSelectAll" aria-label="Select all questions on this page"></th>
                            <th style="width:56px;">Sr.</th>
                            <th>Subject</th>
                            <th>Question</th>
                            <th>Answer</th>
                            <th>Difficulty</th>
                            <th>Marks</th>
                            <th>Source</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($questions_data) > 0) {
                            $fp_sr = $offset;
                            foreach ($questions_data as $row) {
                                $fp_sr++;
                                $fp_diff = $row["difficulty"] ?? "Medium";
                                $fp_diff_cls = $fp_diff == "Easy" ? "is-completed" : ($fp_diff == "Hard" ? "is-expired" : "is-upcoming");
                                $fp_qid = (int)$row["id"];
                            ?>
                                <tr data-qrow="<?php echo $fp_qid; ?>">
                                    <td data-label="Select"><input type="checkbox" class="form-check-input fp-row-check" name="qid[]" value="<?php echo $fp_qid; ?>" aria-label="Select question <?php echo $fp_qid; ?>"></td>
                                    <td data-label="Sr." title="Question ID: <?php echo $fp_qid; ?>"><strong><?php echo $fp_sr; ?></strong></td>
                                    <td data-label="Subject"><span class="badge bg-primary-soft text-primary"><?php echo h($row["subject_name"]); ?></span></td>
                                    <td data-label="Question" style="max-width: 320px;">
                                        <div class="text-truncate" title="<?php echo h($row["question"]); ?>">
                                            <?php echo h($row["question"]); ?>
                                        </div>
                                        <small class="text-muted">Answer: <strong class="text-success"><?php echo h($row["correct_answer"]); ?></strong></small>
                                    </td>
                                    <td data-label="Answer"><span class="badge bg-success"><?php echo h($row["correct_answer"]); ?></span></td>
                                    <td data-label="Difficulty">
                                        <span class="sp-pill <?php echo $fp_diff_cls; ?>"><?php echo h($fp_diff); ?></span>
                                    </td>
                                    <td data-label="Marks"><?php echo (int)$row["marks"]; ?></td>
                                    <td data-label="Source">
                                        <?php if (!empty($row["ai_generated"])) { ?>
                                            <span class="sp-pill is-adaptive"><i class="bi bi-robot"></i>AI</span>
                                        <?php } else { ?>
                                            <span class="sp-pill"><i class="bi bi-person"></i>Manual</span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end text-nowrap" data-label="Actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary fp-expand-btn" data-fp-expand="<?php echo $fp_qid; ?>" title="View full question" aria-label="View full question <?php echo $fp_qid; ?>" aria-expanded="false">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editQuestion<?php echo $fp_qid; ?>" title="Edit Question" aria-label="Edit question">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" data-regen-question="<?php echo $fp_qid; ?>" data-regen-difficulty="<?php echo h($fp_diff); ?>" title="Regenerate with AI" aria-label="Regenerate question with AI">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <a class="btn btn-sm btn-danger js-confirm-delete" href="questions.php?delete=<?php echo $fp_qid; ?>" title="Delete Question" aria-label="Delete question">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr class="fp-qdetail" data-qdetail="<?php echo $fp_qid; ?>" hidden>
                                    <td colspan="9">
                                        <div class="fp-qdetail-box">
                                            <p class="fp-qdetail-q"><strong>Q<?php echo $fp_sr; ?>.</strong> <?php echo h($row["question"]); ?></p>
                                            <div class="fp-qdetail-opts">
                                                <?php foreach (array("A" => $row["option_a"], "B" => $row["option_b"], "C" => $row["option_c"], "D" => $row["option_d"]) as $ok => $ov) {
                                                    $is_c = ($ok === $row["correct_answer"]); ?>
                                                    <span class="fp-qdetail-opt <?php echo $is_c ? "is-correct" : ""; ?>"><strong><?php echo $ok; ?>.</strong> <?php echo h($ov); ?><?php if ($is_c) { ?> <i class="bi bi-check-circle-fill"></i><?php } ?></span>
                                                <?php } ?>
                                            </div>
                                            <div class="fp-qdetail-meta">
                                                <span><i class="bi bi-translate"></i><?php echo h($row["language"]); ?></span>
                                                <span><i class="bi bi-award"></i><?php echo (int)$row["marks"]; ?> marks</span>
                                                <?php if (!empty($row["keywords"])) { ?><span><i class="bi bi-tags"></i><?php echo h($row["keywords"]); ?></span><?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="9">
                                    <div class="sp-empty">
                                        <div class="sp-empty-icon"><i class="bi bi-patch-question"></i></div>
                                        <h4>No Questions Found</h4>
                                        <p>No questions match the selected criteria. Adjust the filters or add a new question.</p>
                                        <?php if ($fp_has_filters) { ?><a href="questions.php" class="sp-btn sp-btn-ghost sp-btn-sm"><i class="bi bi-x-circle"></i> Clear all filters</a><?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
                </form>

                <?php
                $fp_page_qs = fp_qs(array("page" => ""));
                $fp_link = function ($p) use ($fp_page_qs) {
                    $sep = $fp_page_qs !== "" ? "&" : "";
                    return "questions.php?" . $fp_page_qs . $sep . "page=" . $p;
                };
                ?>
                <?php if ($total_pages > 1) { ?>
                    <nav aria-label="Question pages" class="mt-3">
                        <ul class="pagination justify-content-center flex-wrap gap-1 mb-0">
                            <li class="page-item <?php echo $page <= 1 ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h($fp_link(max(1, $page - 1))); ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                                <li class="page-item <?php echo $i == $page ? "active" : ""; ?>">
                                    <a class="page-link" href="<?php echo h($fp_link($i)); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php } ?>
                            <li class="page-item <?php echo $page >= $total_pages ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h($fp_link(min($total_pages, $page + 1))); ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php } ?>
            </div>

            <!-- Edit Modals for loaded rows -->
            <?php foreach ($questions_data as $row) { include("question_modal.php"); } ?>
        </div>
    </div>
</div>

<!-- AI Generate Modal -->
<div class="modal fade" id="aiGenerateModal" tabindex="-1" aria-labelledby="aiGenerateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content professional-modal" data-fp-loading="Generating...">
            <input type="hidden" name="ai_generate_bank" value="1">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="aiGenerateModalLabel"><i class="bi bi-stars me-2 text-primary"></i>Generate Questions with AI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fp-label" for="aiGenSubject">Subject <span class="text-danger">*</span></label>
                    <select name="gen_subject_id" id="aiGenSubject" class="form-select" required>
                        <?php foreach ($subjects_list as $gsub) { ?>
                            <option value="<?php echo (int)$gsub["id"]; ?>" <?php echo $filter_subject === (int)$gsub["id"] ? "selected" : ""; ?>><?php echo h($gsub["subject_name"]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="fp-label" for="aiGenCount">How many? <span class="text-danger">*</span></label>
                        <input type="number" name="gen_count" id="aiGenCount" class="form-control" value="5" min="1" max="20" required>
                    </div>
                    <div class="col-6">
                        <label class="fp-label" for="aiGenDiff">Difficulty mix</label>
                        <select name="gen_difficulty" id="aiGenDiff" class="form-select">
                            <option value="Mixed">Mixed (balanced)</option>
                            <option value="Easy">All Easy</option>
                            <option value="Medium">All Medium</option>
                            <option value="Hard">All Hard</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info small mt-3 mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>Generated questions are saved straight into your bank marked with an <strong>AI</strong> badge. Generation may take up to a minute.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-primary"><i class="bi bi-stars"></i> Generate &amp; Save</button>
            </div>
        </form>
    </div>
</div>

<!-- AI Regenerate Modal (per-question, Groq powered) -->
<div class="modal fade" id="aiRegenModal" tabindex="-1" aria-labelledby="aiRegenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content professional-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="aiRegenModalLabel"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Regenerate with AI <span class="badge bg-primary-soft text-primary ms-1" id="aiRegenQidBadge">#</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="fp-label">Current Question</label>
                <div class="alert alert-light border small mb-3" id="aiRegenCurrent">—</div>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-6">
                        <label class="fp-label" for="aiRegenDifficulty">Difficulty</label>
                        <select id="aiRegenDifficulty" class="form-select">
                            <option>Easy</option>
                            <option selected>Medium</option>
                            <option>Hard</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <button type="button" class="sp-btn sp-btn-primary w-100" id="aiRegenGenerateBtn"><i class="bi bi-stars"></i> Generate New Version</button>
                    </div>
                </div>
                <small class="text-muted d-block mb-2"><i class="bi bi-robot me-1"></i>Groq AI creates a fresh question on the same subject — the current version stays untouched until you apply.</small>
                <div id="aiRegenPreview" style="display:none;">
                    <label class="fp-label">AI Suggestion <span class="sp-pill is-adaptive ms-1"><i class="bi bi-robot"></i>AI</span></label>
                    <div class="fp-qdetail-box">
                        <p class="fp-qdetail-q" id="aiRegenQ"></p>
                        <div class="fp-qdetail-opts" id="aiRegenOpts"></div>
                    </div>
                </div>
                <div class="alert alert-danger small mb-0" id="aiRegenError" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="sp-btn sp-btn-primary" id="aiRegenUseBtn" disabled><i class="bi bi-check2-circle"></i> Use This Version</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content professional-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="">
                <?php 
                $modal_row = array("id" => "add", "subject_id" => $filter_subject > 0 ? $filter_subject : "", "question" => "", "language" => "English", "option_a" => "", "option_b" => "", "option_c" => "", "option_d" => "", "correct_answer" => "A", "difficulty" => "Easy", "marks" => 1); 
                include("question_form_fields.php"); 
                ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-gradient" type="submit">Add Question</button>
            </div>
        </form>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    var regenQid = 0;
    var regenData = null;
    var regenModalEl = document.getElementById("aiRegenModal");
    var regenModal = regenModalEl ? bootstrap.Modal.getOrCreateInstance(regenModalEl) : null;

    function regenEscape(s) {
        var d = document.createElement("div");
        d.textContent = s == null ? "" : s;
        return d.innerHTML;
    }

    function setField(id, val) {
        var el = document.getElementById(id);
        if (el) { el.value = val; }
    }

    document.querySelectorAll("[data-regen-question]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            regenQid = parseInt(btn.getAttribute("data-regen-question"), 10) || 0;
            regenData = null;
            var diff = btn.getAttribute("data-regen-difficulty") || "Medium";
            var diffSel = document.getElementById("aiRegenDifficulty");
            if (diffSel) { diffSel.value = ["Easy", "Medium", "Hard"].indexOf(diff) !== -1 ? diff : "Medium"; }
            var badge = document.getElementById("aiRegenQidBadge");
            if (badge) { badge.textContent = "#" + regenQid; }
            var cur = document.getElementById("aiRegenCurrent");
            var curInput = document.getElementById("questionText" + regenQid);
            if (cur) { cur.textContent = curInput && curInput.value ? curInput.value : "—"; }
            var pv = document.getElementById("aiRegenPreview");
            if (pv) { pv.style.display = "none"; }
            var err = document.getElementById("aiRegenError");
            if (err) { err.style.display = "none"; }
            var useBtn = document.getElementById("aiRegenUseBtn");
            if (useBtn) { useBtn.disabled = true; }
            if (regenModal) { regenModal.show(); }
        });
    });

    var genBtn = document.getElementById("aiRegenGenerateBtn");
    if (genBtn) {
        genBtn.addEventListener("click", function () {
            if (!regenQid) { return; }
            var diffSel = document.getElementById("aiRegenDifficulty");
            var diff = diffSel ? diffSel.value : "Medium";
            var err = document.getElementById("aiRegenError");
            var pv = document.getElementById("aiRegenPreview");
            if (err) { err.style.display = "none"; }
            if (pv) { pv.style.display = "none"; }
            genBtn.disabled = true;
            var orig = genBtn.innerHTML;
            genBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
            fetch("questions.php?ajax_action=regenerate_question&qid=" + regenQid + "&difficulty=" + encodeURIComponent(diff))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) { throw new Error((data && data.message) || "AI could not regenerate right now."); }
                    regenData = data.question;
                    var qEl = document.getElementById("aiRegenQ");
                    if (qEl) { qEl.textContent = regenData.question; }
                    var optsEl = document.getElementById("aiRegenOpts");
                    if (optsEl) {
                        optsEl.innerHTML = "";
                        ["A", "B", "C", "D"].forEach(function (letter) {
                            var span = document.createElement("span");
                            span.className = "fp-qdetail-opt" + (regenData.correct_answer === letter ? " is-correct" : "");
                            span.innerHTML = "<strong>" + letter + ".</strong> " + regenEscape(regenData["option_" + letter.toLowerCase()]) + (regenData.correct_answer === letter ? ' <i class="bi bi-check-circle-fill"></i>' : "");
                            optsEl.appendChild(span);
                        });
                    }
                    if (pv) { pv.style.display = "block"; }
                    var useBtn = document.getElementById("aiRegenUseBtn");
                    if (useBtn) { useBtn.disabled = false; }
                })
                .catch(function (e) {
                    if (err) { err.textContent = e.message; err.style.display = "block"; }
                })
                .finally(function () {
                    genBtn.disabled = false;
                    genBtn.innerHTML = orig;
                });
        });
    }

    var useBtn = document.getElementById("aiRegenUseBtn");
    if (useBtn) {
        useBtn.addEventListener("click", function () {
            if (!regenQid || !regenData) { return; }
            setField("questionText" + regenQid, regenData.question);
            setField("optionA" + regenQid, regenData.option_a);
            setField("optionB" + regenQid, regenData.option_b);
            setField("optionC" + regenQid, regenData.option_c);
            setField("optionD" + regenQid, regenData.option_d);
            setField("correctAnswer" + regenQid, regenData.correct_answer);
            setField("difficulty" + regenQid, regenData.difficulty);
            setField("aiRegenerated" + regenQid, "1");
            if (regenModal) { regenModal.hide(); }
            var editEl = document.getElementById("editQuestion" + regenQid);
            if (editEl) { bootstrap.Modal.getOrCreateInstance(editEl).show(); }
        });
    }
});
</script>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>

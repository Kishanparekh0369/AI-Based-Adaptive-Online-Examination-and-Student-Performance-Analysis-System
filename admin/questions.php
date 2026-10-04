<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Manage Questions";

if (isset($_GET["delete"])) {
    $id = (int)clean_data($_GET["delete"]);
    mysqli_query($conn, "DELETE FROM exam_questions WHERE question_id=$id");
    mysqli_query($conn, "DELETE FROM questions WHERE id=$id");
    redirect_alert("Question deleted successfully", "questions.php");
}

// AJAX: regenerate a single question with AI (returns JSON, no HTML)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajax_action"]) && $_POST["ajax_action"] === "regenerate_question") {
    header("Content-Type: application/json");
    $qid = (int)($_POST["qid"] ?? 0);
    $qrow = null;
    if ($qid > 0) {
        $qq = mysqli_query($conn, "SELECT q.*, s.subject_name FROM questions q LEFT JOIN subjects s ON s.id=q.subject_id WHERE q.id=$qid LIMIT 1");
        if ($qq) { $qrow = mysqli_fetch_assoc($qq); }
    }
    if (!$qrow) {
        echo json_encode(array("success" => false, "message" => "Question not found."));
        exit;
    }
    require_once("../app/ai/QuestionGenerator.php");
    $generator = new QuestionGenerator();
    $subject_name = trim((string)($qrow["subject_name"] ?? "")) !== "" ? $qrow["subject_name"] : "General";
    $new_q = $generator->generateMCQ(
        $subject_name,
        "",
        in_array($qrow["difficulty"], array("Easy", "Medium", "Hard"), true) ? $qrow["difficulty"] : "Medium",
        in_array($qrow["language"], array("English", "Hindi", "Gujarati"), true) ? $qrow["language"] : "English",
        max(1, (int)$qrow["marks"]),
        false,
        array(),
        (string)$qrow["question"],
        array((string)$qrow["option_a"], (string)$qrow["option_b"], (string)$qrow["option_c"], (string)$qrow["option_d"]),
        "admin_regen_" . $qid . "_" . uniqid()
    );
    if (!$new_q || trim((string)($new_q["question"] ?? "")) === "") {
        echo json_encode(array("success" => false, "message" => $generator->getLastError() ?: "AI could not regenerate this question right now."));
        exit;
    }
    $ans = strtoupper(trim((string)($new_q["correct_answer"] ?? "")));
    if (!in_array($ans, array("A", "B", "C", "D"), true)) { $ans = "A"; }
    echo json_encode(array("success" => true, "qid" => $qid, "question" => (string)$new_q["question"],
        "option_a" => (string)($new_q["option_a"] ?? ""), "option_b" => (string)($new_q["option_b"] ?? ""),
        "option_c" => (string)($new_q["option_c"] ?? ""), "option_d" => (string)($new_q["option_d"] ?? ""),
        "correct_answer" => $ans));
    exit;
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
        mysqli_query($conn, "DELETE FROM exam_questions WHERE question_id=$qid");
        if (mysqli_query($conn, "DELETE FROM questions WHERE id=$qid LIMIT 1")) { $deleted++; }
    }
    if ($deleted > 0) {
        redirect_alert($deleted . " question" . ($deleted === 1 ? "" : "s") . " deleted successfully", "questions.php", "success");
    }
    redirect_alert("No questions were deleted.", "questions.php", "error");
}

// Handle AI regenerate SAVE (replace question text/options, keep same ID)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["regenerate_save"])) {
    $qid = (int)($_POST["qid"] ?? 0);
    $nq = clean_data($_POST["question"] ?? "");
    $oa = clean_data($_POST["option_a"] ?? "");
    $ob = clean_data($_POST["option_b"] ?? "");
    $oc = clean_data($_POST["option_c"] ?? "");
    $od = clean_data($_POST["option_d"] ?? "");
    $ca = strtoupper(trim(clean_data($_POST["correct_answer"] ?? "")));
    $chk = $qid > 0 ? mysqli_query($conn, "SELECT subject_id FROM questions WHERE id=$qid LIMIT 1") : false;
    $chk_row = $chk ? mysqli_fetch_assoc($chk) : null;
    if (!$chk_row) {
        redirect_alert("Question not found", "questions.php", "error");
    }
    if ($nq === "" || $oa === "" || $ob === "" || $oc === "" || $od === "" || !in_array($ca, array("A", "B", "C", "D"), true)) {
        redirect_alert("AI returned an incomplete question. Please try regenerating again.", "questions.php", "error");
    }
    $nq_safe = mysqli_real_escape_string($conn, $nq);
    $oa_safe = mysqli_real_escape_string($conn, $oa);
    $ob_safe = mysqli_real_escape_string($conn, $ob);
    $oc_safe = mysqli_real_escape_string($conn, $oc);
    $od_safe = mysqli_real_escape_string($conn, $od);
    if (mysqli_query($conn, "UPDATE questions SET question='$nq_safe', option_a='$oa_safe', option_b='$ob_safe', option_c='$oc_safe', option_d='$od_safe', correct_answer='$ca', ai_generated=1 WHERE id=$qid LIMIT 1")) {
        redirect_alert("Question #$qid regenerated successfully with AI", "questions.php?subject_id=" . (int)$chk_row["subject_id"], "success");
    }
    redirect_alert("Could not save the regenerated question. Please try again.", "questions.php", "error");
}

// Handle AI Generate directly into bank (IDs collected + shown)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ai_generate_bank"])) {
    $gen_subject = (int)($_POST["gen_subject_id"] ?? 0);
    $gen_count = min(max((int)($_POST["gen_count"] ?? 5), 1), 20);
    $gen_diff = clean_data($_POST["gen_difficulty"] ?? "Mixed");
    if (!in_array($gen_diff, array("Mixed", "Easy", "Medium", "Hard"), true)) {
        $gen_diff = "Mixed";
    }
    $gen_lang = clean_data($_POST["gen_language"] ?? "English");
    if (!in_array($gen_lang, array("English", "Hindi", "Gujarati"), true)) {
        $gen_lang = "English";
    }
    $gen_sub_check = $gen_subject > 0 ? mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id=$gen_subject LIMIT 1") : false;
    $gen_sub_row = $gen_sub_check ? mysqli_fetch_assoc($gen_sub_check) : null;
    if (!$gen_sub_row) {
        redirect_alert("Please select a valid subject for AI generation", "questions.php", "error");
    }
    require_once("../app/ai/QuestionGenerator.php");
    $generator = new QuestionGenerator();
    if ($gen_diff === "Mixed") {
        $diffs = QuestionGenerator::calculateDifficultyDistribution($gen_count);
    } else {
        $diffs = array_fill(0, $gen_count, $gen_diff);
    }
    $generated = $generator->generatePreviewMCQs($gen_sub_row["subject_name"], $diffs, $gen_lang, 1);
    $new_ids = array();
    foreach ($generated as $gq) {
        if (!is_array($gq) || trim($gq["question"] ?? "") === "") {
            continue;
        }
        $ans = strtoupper(trim($gq["correct_answer"] ?? "A"));
        if (!in_array($ans, array("A", "B", "C", "D"))) {
            $gq["correct_answer"] = "A";
        }
        $new_id = $generator->saveToDatabase($gen_subject, $gq);
        if ($new_id > 0) { $new_ids[] = $new_id; }
    }
    if (!empty($new_ids)) {
        $id_list = "#" . implode(", #", $new_ids);
        redirect_alert(count($new_ids) . " AI question" . (count($new_ids) === 1 ? "" : "s") . " added to the bank (IDs: " . $id_list . ")", "questions.php?subject_id=" . $gen_subject . "&source=ai&sort=newest", "success");
    }
    redirect_alert($generator->getLastError() ?: "AI could not generate questions right now. Please try again.", "questions.php?subject_id=" . $gen_subject, "error");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $edit_id = clean_data($_POST["edit_id"] ?? '');
    $subject_id = clean_data($_POST["subject_id"] ?? '');
    $question = clean_data($_POST["question"] ?? '');
    $language = clean_data($_POST["language"] ?? '');
    $option_a = clean_data($_POST["option_a"] ?? '');
    $option_b = clean_data($_POST["option_b"] ?? '');
    $option_c = clean_data($_POST["option_c"] ?? '');
    $option_d = clean_data($_POST["option_d"] ?? '');
    $correct_answer = clean_data($_POST["correct_answer"] ?? '');
    $difficulty = clean_data($_POST["difficulty"] ?? '');
    $marks = clean_data($_POST["marks"] ?? '');
    $keywords = clean_data($_POST["keywords"] ?? '');

    if ($subject_id == "" || $question == "" || $language == "" || $option_a == "" || $option_b == "" || $option_c == "" || $option_d == "" || $marks == "") {
        redirect_alert("All question fields are required", "questions.php");
    }
    if ($marks <= 0) {
        redirect_alert("Marks must be greater than zero", "questions.php");
    }

    $check = mysqli_query($conn, "SELECT * FROM questions WHERE subject_id='$subject_id' AND language='$language' AND question='$question' AND id!='$edit_id'");
    if (mysqli_num_rows($check) > 0) {
        redirect_alert("This question already exists for the same subject and language", "questions.php");
    }

    if ($edit_id != "") {
        mysqli_query($conn, "UPDATE questions SET subject_id='$subject_id', question='$question', language='$language', option_a='$option_a', option_b='$option_b', option_c='$option_c', option_d='$option_d', correct_answer='$correct_answer', difficulty='$difficulty', marks='$marks', keywords='$keywords' WHERE id='$edit_id'");
        redirect_alert("Question updated successfully", "questions.php");
    } else {
        mysqli_query($conn, "INSERT INTO questions (subject_id,question,language,option_a,option_b,option_c,option_d,correct_answer,difficulty,marks,keywords) VALUES ('$subject_id','$question','$language','$option_a','$option_b','$option_c','$option_d','$correct_answer','$difficulty','$marks','$keywords')");
        notify_user("admin", null, "Question Added", "A new question was added to the question bank.", "questions.php");
        redirect_alert("Question inserted successfully", "questions.php");
    }
}

include("../app/layouts/admin_header.php");
$subjects = mysqli_query($conn, "SELECT * FROM subjects ORDER BY subject_name");
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

$where_parts = array();
if ($search !== "") {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(q.question LIKE '%$search_safe%' OR s.subject_name LIKE '%$search_safe%' OR q.difficulty LIKE '%$search_safe%' OR q.keywords LIKE '%$search_safe%')";
}
if ($filter_subject > 0) {
    $where_parts[] = "q.subject_id=$filter_subject";
}
if ($filter_language != "") {
    $where_parts[] = "q.language='" . mysqli_real_escape_string($conn, $filter_language) . "'";
}
if ($filter_difficulty != "") {
    $where_parts[] = "q.difficulty='" . mysqli_real_escape_string($conn, $filter_difficulty) . "'";
}
if ($filter_marks != "") {
    $where_parts[] = "q.marks=" . (int)$filter_marks;
}
if ($filter_source === "ai") {
    $where_parts[] = "q.ai_generated=1";
} elseif ($filter_source === "manual") {
    $where_parts[] = "(q.ai_generated IS NULL OR q.ai_generated=0)";
}
if ($filter_answer != "") {
    $where_parts[] = "q.correct_answer='" . mysqli_real_escape_string($conn, $filter_answer) . "'";
}
$where = $where_parts ? implode(" AND ", $where_parts) : "1=1";
$order_by = $sort_options[$sort];

$total_question_rows = 0;
$count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM questions q LEFT JOIN subjects s ON q.subject_id=s.id WHERE $where");
if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
    $total_question_rows = (int)$count_row["total"];
}
$total_pages = max(ceil($total_question_rows / $per_page), 1);
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }
$questions_result = mysqli_query($conn, "SELECT q.*, s.subject_name FROM questions q LEFT JOIN subjects s ON q.subject_id=s.id WHERE $where ORDER BY $order_by LIMIT $per_page OFFSET $offset");
$questions_data = [];
if ($questions_result) {
    while ($q_row = mysqli_fetch_assoc($questions_result)) {
        $questions_data[] = $q_row;
    }
}
$subject_cards = mysqli_query($conn, "SELECT s.*, COUNT(q.id) AS question_count, COUNT(DISTINCT q.language) AS language_count, COUNT(DISTINCT q.difficulty) AS difficulty_count FROM subjects s LEFT JOIN questions q ON q.subject_id=s.id GROUP BY s.id ORDER BY s.id ASC");

// Header stat row (system-wide)
$aq_stats = array("total" => 0, "easy" => 0, "medium" => 0, "hard" => 0, "ai" => 0);
$aq_stat_res = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(difficulty='Easy') AS easy, SUM(difficulty='Medium') AS medium, SUM(difficulty='Hard') AS hard, SUM(ai_generated=1) AS ai FROM questions");
if ($aq_stat_res && $aq_stat_row = mysqli_fetch_assoc($aq_stat_res)) {
    $aq_stats["total"] = (int)$aq_stat_row["total"];
    $aq_stats["easy"] = (int)($aq_stat_row["easy"] ?? 0);
    $aq_stats["medium"] = (int)($aq_stat_row["medium"] ?? 0);
    $aq_stats["hard"] = (int)($aq_stat_row["hard"] ?? 0);
    $aq_stats["ai"] = (int)($aq_stat_row["ai"] ?? 0);
}

function aq_qs($overrides = array())
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

$aq_has_filters = ($search !== "" || $filter_subject > 0 || $filter_language !== "" || $filter_difficulty !== "" || $filter_marks !== "" || $filter_source !== "" || $filter_answer !== "");
$aq_show_from = $total_question_rows > 0 ? ($offset + 1) : 0;
$aq_show_to = min($offset + $per_page, $total_question_rows);
$aq_active_filters = array();
if ($search !== "") { $aq_active_filters[] = array("label" => "Search: " . $search, "qs" => aq_qs(array("search" => ""))); }
if ($filter_subject > 0) {
    $aq_sub_name = "Subject #" . $filter_subject;
    foreach ($subjects_list as $sl) { if ((int)$sl["id"] === $filter_subject) { $aq_sub_name = $sl["subject_name"]; break; } }
    $aq_active_filters[] = array("label" => "Subject: " . $aq_sub_name, "qs" => aq_qs(array("subject_id" => "")));
}
if ($filter_language !== "") { $aq_active_filters[] = array("label" => "Language: " . $filter_language, "qs" => aq_qs(array("language" => ""))); }
if ($filter_difficulty !== "") { $aq_active_filters[] = array("label" => "Difficulty: " . $filter_difficulty, "qs" => aq_qs(array("difficulty" => ""))); }
if ($filter_marks !== "") { $aq_active_filters[] = array("label" => "Marks: " . $filter_marks, "qs" => aq_qs(array("marks" => ""))); }
if ($filter_source !== "") { $aq_active_filters[] = array("label" => "Source: " . ($filter_source === "ai" ? "AI Generated" : "Manual"), "qs" => aq_qs(array("source" => ""))); }
if ($filter_answer !== "") { $aq_active_filters[] = array("label" => "Answer: " . $filter_answer, "qs" => aq_qs(array("answer" => ""))); }
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item active">Questions</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-patch-question"></i> Exam Management</span>
                    <h1>Manage Questions</h1>
                    <p>Curate the system-wide question bank across subjects and difficulty levels.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="sp-btn sp-btn-ai" data-bs-toggle="modal" data-bs-target="#aiGenerateModal"><i class="bi bi-stars"></i> Generate with AI</button>
                    <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addQuestionModal"><i class="bi bi-plus-circle me-1"></i>Add Question</button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <?php
                $aq_stat_links = array(
                    "questions.php",
                    "questions.php?difficulty=Easy",
                    "questions.php?difficulty=Medium",
                    "questions.php?difficulty=Hard",
                    "questions.php?source=ai"
                );
                $aq_stat_defs = array(
                    array($aq_stats["total"], "Total Questions", "bi-collection", "", $aq_stat_links[0], "Show all questions"),
                    array($aq_stats["easy"], "Easy", "bi-emoji-smile", "is-success", $aq_stat_links[1], "Show only Easy questions"),
                    array($aq_stats["medium"], "Medium", "bi-emoji-neutral", "is-warning", $aq_stat_links[2], "Show only Medium questions"),
                    array($aq_stats["hard"], "Hard", "bi-emoji-frown", "is-danger", $aq_stat_links[3], "Show only Hard questions"),
                    array($aq_stats["ai"], "AI Generated", "bi-robot", "is-indigo", $aq_stat_links[4], "Show only AI generated questions")
                );
                $aq_si = 0;
                foreach ($aq_stat_defs as $sd) { $aq_si++; ?>
                    <div class="col-6 col-lg sp-fade-up" data-delay="<?php echo $aq_si; ?>">
                        <a href="<?php echo $sd[4]; ?>" class="text-decoration-none d-block h-100" title="<?php echo h($sd[5]); ?>">
                            <div class="sp-stat <?php echo $sd[3]; ?> h-100">
                                <span class="sp-stat-icon"><i class="bi <?php echo $sd[2]; ?>"></i></span>
                                <div class="sp-stat-value" data-count="<?php echo $sd[0]; ?>">0</div>
                                <div class="sp-stat-label"><?php echo $sd[1]; ?></div>
                            </div>
                        </a>
                    </div>
                <?php } ?>
            </div>

            <div class="row g-3 mb-4">
                <?php if ($subject_cards && mysqli_num_rows($subject_cards) > 0) {
                    $aq_ci = 0;
                    while ($card = mysqli_fetch_assoc($subject_cards)) {
                        $aq_ci++;
                        $aq_active = ($filter_subject === (int)$card["id"]);
                ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min(($aq_ci % 3) + 1, 6); ?>">
                        <a class="subject-card admin-subject-card d-block p-3 h-100 <?php echo $aq_active ? 'border border-2 border-primary shadow-sm' : ''; ?>" href="questions.php?subject_id=<?php echo (int)$card["id"]; ?>">
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
                                <?php if ($aq_active) { ?><span class="badge bg-primary">Filtered</span><?php } ?>
                            </div>
                        </a>
                    </div>
                <?php }} ?>
            </div>

            <!-- Search & Filters Toolbar -->
            <form method="get" class="sp-filters sp-fade-up" id="aqQuestionFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="aqQSearch">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" id="aqQSearch" name="search" value="<?php echo h($search); ?>" placeholder="Question, keywords or subject">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="aqQSubject">Subject</label>
                        <select name="subject_id" id="aqQSubject" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects_list as $sub) { ?>
                                <option value="<?php echo (int)$sub["id"]; ?>" <?php if ($filter_subject === (int)$sub["id"]) echo "selected"; ?>><?php echo h($sub["subject_name"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="aqQSource">Source</label>
                        <select name="source" id="aqQSource" class="form-select">
                            <option value="">All Sources</option>
                            <option value="manual" <?php if ($filter_source === "manual") echo "selected"; ?>>Manual</option>
                            <option value="ai" <?php if ($filter_source === "ai") echo "selected"; ?>>AI Generated</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="aqQAnswer">Answer</label>
                        <select name="answer" id="aqQAnswer" class="form-select">
                            <option value="">Any Key</option>
                            <?php foreach (array("A", "B", "C", "D") as $a_opt) { ?>
                                <option value="<?php echo $a_opt; ?>" <?php if ($filter_answer === $a_opt) echo "selected"; ?>>Option <?php echo $a_opt; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="aqQLang">Language</label>
                        <select name="language" id="aqQLang" class="form-select">
                            <option value="">All</option>
                            <?php foreach (array("English", "Hindi", "Gujarati") as $l_opt) { ?>
                                <option value="<?php echo $l_opt; ?>" <?php if ($filter_language == $l_opt) echo "selected"; ?>><?php echo $l_opt; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="aqQDiff">Difficulty</label>
                        <select name="difficulty" id="aqQDiff" class="form-select">
                            <option value="">All</option>
                            <option value="Easy" <?php if ($filter_difficulty == "Easy") echo "selected"; ?>>Easy</option>
                            <option value="Medium" <?php if ($filter_difficulty == "Medium") echo "selected"; ?>>Medium</option>
                            <option value="Hard" <?php if ($filter_difficulty == "Hard") echo "selected"; ?>>Hard</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="aqQMarks">Marks</label>
                        <input type="number" class="form-control" id="aqQMarks" name="marks" value="<?php echo h($filter_marks); ?>" min="1" placeholder="Any">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="aqQSort">Sort By</label>
                        <select name="sort" id="aqQSort" class="form-select">
                            <?php foreach ($sort_labels as $s_key => $s_label) { ?>
                                <option value="<?php echo $s_key; ?>" <?php if ($sort === $s_key) echo "selected"; ?>><?php echo h($s_label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small fw-semibold mb-1" for="aqQPerPage">Rows</label>
                        <select name="per_page" id="aqQPerPage" class="form-select">
                            <?php foreach ($per_page_options as $pp_opt) { ?>
                                <option value="<?php echo $pp_opt; ?>" <?php if ($per_page === $pp_opt) echo "selected"; ?>><?php echo $pp_opt; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1 d-flex gap-2">
                        <button type="submit" class="sp-btn sp-btn-primary w-100" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
                        <a href="questions.php" class="sp-btn sp-btn-ghost" aria-label="Clear all filters"><i class="bi bi-x-circle"></i></a>
                    </div>
                </div>
                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="sp-filter-count">Showing <?php echo $total_question_rows > 0 ? ($offset + 1) : 0; ?>&ndash;<?php echo min($offset + $per_page, $total_question_rows); ?> of <?php echo $total_question_rows; ?> questions</span>
                    <?php if ($aq_has_filters) { ?>
                        <a href="questions.php" class="fp-clear-link"><i class="bi bi-x-circle me-1"></i>Clear all</a>
                    <?php } ?>
                </div>
                <?php if (!empty($aq_active_filters)) { ?>
                    <div class="fp-active-pills">
                        <?php foreach ($aq_active_filters as $af) { ?>
                            <a class="fp-active-pill" href="questions.php?<?php echo h($af["qs"]); ?>" title="Remove this filter"><?php echo h($af["label"]); ?><i class="bi bi-x"></i></a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </form>

            <div class="table-card admin-modern-table p-3 sp-fade-up" id="aqQuestionCard">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-patch-question me-2 text-primary"></i>Questions List (<?php echo $total_question_rows; ?>)</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group" style="max-width: 230px;">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input class="form-control dashboard-search" data-table-search="#questionsTable" placeholder="Filter current page..." aria-label="Filter current page">
                        </div>
                        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" id="aqDensityBtn" title="Toggle compact view" aria-label="Toggle compact view" aria-pressed="false"><i class="bi bi-list"></i><span class="d-none d-sm-inline"> Compact</span></button>
                    </div>
                </div>

                <form method="post" id="aqBulkForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <div class="fp-bulkbar d-none" id="aqBulkBar" role="status" aria-live="polite">
                        <strong><span id="aqBulkCount">0</span> selected</strong>
                        <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" id="aqBulkDeleteBtn"><i class="bi bi-trash"></i> Delete selected</button>
                        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" id="aqBulkClearBtn">Clear</button>
                    </div>

                <div class="table-responsive">
                <table class="table table-hover align-middle sp-table" id="questionsTable">
                    <thead><tr><th style="width:38px;"><input type="checkbox" class="form-check-input" id="aqSelectAll" aria-label="Select all questions on this page"></th><th style="width:56px;">Sr.</th><th>Subject</th><th>Language</th><th>Question</th><th>Answer</th><th>Difficulty</th><th>Marks</th><th>Source</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($questions_data) > 0) { $aq_sr = $offset; foreach ($questions_data as $row) {
                            $aq_sr++;
                            $aq_diff = $row["difficulty"] ?? "Medium";
                            $aq_diff_cls = $aq_diff == "Easy" ? "is-completed" : ($aq_diff == "Hard" ? "is-expired" : "is-upcoming");
                            $aq_qid = (int)$row["id"];
                        ?>
                            <tr data-qrow="<?php echo $aq_qid; ?>">
                                <td data-label="Select"><input type="checkbox" class="form-check-input aq-row-check" name="qid[]" value="<?php echo $aq_qid; ?>" aria-label="Select question <?php echo $aq_qid; ?>"></td>
                                <td data-label="Sr." title="Question ID: <?php echo $aq_qid; ?>"><strong><?php echo $aq_sr; ?></strong></td>
                                <td data-label="Subject"><span class="badge bg-primary-soft text-primary"><?php echo h($row["subject_name"]); ?></span></td>
                                <td data-label="Language"><span class="badge bg-info text-dark"><?php echo h($row["language"]); ?></span></td>
                                <td data-label="Question" style="max-width: 300px;">
                                    <div class="text-truncate" title="<?php echo h($row["question"]); ?>"><?php echo h($row["question"]); ?></div>
                                    <small class="text-muted">Answer: <strong class="text-success"><?php echo h($row["correct_answer"]); ?></strong></small>
                                </td>
                                <td data-label="Answer"><span class="badge bg-success"><?php echo h($row["correct_answer"]); ?></span></td>
                                <td data-label="Difficulty"><span class="sp-pill <?php echo $aq_diff_cls; ?>"><?php echo h($aq_diff); ?></span></td>
                                <td data-label="Marks"><?php echo (int)$row["marks"]; ?></td>
                                <td data-label="Source"><?php if (!empty($row["ai_generated"])) { ?><span class="sp-pill is-adaptive"><i class="bi bi-robot"></i>AI</span><?php } else { ?><span class="sp-pill"><i class="bi bi-person"></i>Manual</span><?php } ?></td>
                                <td class="text-end text-nowrap" data-label="Actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary aq-expand-btn" data-aq-expand="<?php echo $aq_qid; ?>" title="View full question" aria-label="View full question <?php echo $aq_qid; ?>" aria-expanded="false"><i class="bi bi-chevron-down"></i></button>
                                        <button type="button" class="btn btn-sm aq-regen-btn" data-aq-regen="<?php echo $aq_qid; ?>" data-question="<?php echo h($row["question"]); ?>" data-option-a="<?php echo h($row["option_a"]); ?>" data-option-b="<?php echo h($row["option_b"]); ?>" data-option-c="<?php echo h($row["option_c"]); ?>" data-option-d="<?php echo h($row["option_d"]); ?>" data-answer="<?php echo h($row["correct_answer"]); ?>" data-difficulty="<?php echo h($aq_diff); ?>" title="Regenerate with AI" aria-label="Regenerate question <?php echo $aq_qid; ?> with AI"><i class="bi bi-robot"></i></button>
                                    <button type="button" class="btn btn-sm btn-warning js-edit-question" data-target-modal="editQuestion<?php echo $aq_qid; ?>" title="Edit" aria-label="Edit question"><i class="bi bi-pencil"></i></button>
                                    <a class="btn btn-sm btn-danger js-confirm-delete" href="questions.php?delete=<?php echo $aq_qid; ?>" title="Delete" aria-label="Delete question"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <tr class="fp-qdetail" data-qdetail="<?php echo $aq_qid; ?>" hidden>
                                <td colspan="10">
                                    <div class="fp-qdetail-box">
                                        <p class="fp-qdetail-q"><strong>Q<?php echo $aq_sr; ?>.</strong> <?php echo h($row["question"]); ?></p>
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
                        <?php }} else { ?>
                            <tr><td colspan="10">
                                <div class="sp-empty">
                                    <div class="sp-empty-icon"><i class="bi bi-patch-question"></i></div>
                                    <h4>No Questions Found</h4>
                                    <p>No questions match the selected criteria. Adjust the filters or add a new question.</p>
                                    <?php if ($aq_has_filters) { ?><a href="questions.php" class="sp-btn sp-btn-ghost sp-btn-sm"><i class="bi bi-x-circle"></i> Clear all filters</a><?php } ?>
                                </div>
                            </td></tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
                </form>

                <?php
                $aq_page_qs = aq_qs(array("page" => ""));
                $aq_link = function ($p) use ($aq_page_qs) {
                    $sep = $aq_page_qs !== "" ? "&" : "";
                    return "questions.php?" . $aq_page_qs . $sep . "page=" . $p;
                };
                ?>
                <?php if ($total_pages > 1) { ?>
                    <nav aria-label="Question pages" class="mt-3">
                        <ul class="pagination justify-content-center flex-wrap gap-1 mb-0">
                            <li class="page-item <?php echo $page <= 1 ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h($aq_link(max(1, $page - 1))); ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                                <li class="page-item <?php echo $i == $page ? "active" : ""; ?>">
                                    <a class="page-link" href="<?php echo h($aq_link($i)); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php } ?>
                            <li class="page-item <?php echo $page >= $total_pages ? "disabled" : ""; ?>">
                                <a class="page-link" <?php echo $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ""; ?> href="<?php echo h($aq_link(min($total_pages, $page + 1))); ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php } ?>
            </div>
            <?php foreach ($questions_data as $row) { include("question_modal.php"); } ?>
        </div>
    </div>
</div>

<div class="modal fade" id="aiGenerateModal" tabindex="-1" aria-labelledby="aiGenerateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content professional-modal" id="aqAiGenerateForm">
            <input type="hidden" name="ai_generate_bank" value="1">
            <div class="modal-header aq-ai-head">
                <h5 class="modal-title fw-bold" id="aiGenerateModalLabel"><i class="bi bi-stars me-2"></i>Generate Questions with AI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fp-label" for="aiGenSubject">Subject <span class="text-danger">*</span></label>
                    <select name="gen_subject_id" id="aiGenSubject" class="form-select" required>
                        <option value="">Select subject</option>
                        <?php foreach ($subjects_list as $gsub) { ?>
                            <option value="<?php echo (int)$gsub["id"]; ?>" <?php if ($filter_subject === (int)$gsub["id"]) echo "selected"; ?>><?php echo h($gsub["subject_name"]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-4">
                        <label class="fp-label" for="aiGenCount">How many? <span class="text-danger">*</span></label>
                        <input type="number" name="gen_count" id="aiGenCount" class="form-control" value="5" min="1" max="20" required>
                    </div>
                    <div class="col-4">
                        <label class="fp-label" for="aiGenDiff">Difficulty</label>
                        <select name="gen_difficulty" id="aiGenDiff" class="form-select">
                            <option value="Mixed">Mixed</option>
                            <option value="Easy">All Easy</option>
                            <option value="Medium">All Medium</option>
                            <option value="Hard">All Hard</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="fp-label" for="aiGenLang">Language</label>
                        <select name="gen_language" id="aiGenLang" class="form-select">
                            <option value="English">English</option>
                            <option value="Hindi">Hindi</option>
                            <option value="Gujarati">Gujarati</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info small mt-3 mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>New questions are saved with an <strong>AI</strong> badge and their <strong>IDs are shown</strong> after generation. Generation may take up to a minute.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-ai" id="aqAiGenerateBtn"><i class="bi bi-stars"></i> Generate &amp; Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="aqRegenModal" tabindex="-1" aria-labelledby="aqRegenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content professional-modal">
            <div class="modal-header aq-ai-head">
                <h5 class="modal-title fw-bold" id="aqRegenModalLabel"><i class="bi bi-robot me-2"></i>Regenerate Question <span id="aqRegenIdLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="aqRegenLoading" class="text-center py-4">
                    <div class="aq-robot-pulse"><i class="bi bi-robot"></i></div>
                    <p class="mt-3 mb-1 fw-bold">AI is rewriting this question…</p>
                    <p class="text-muted small mb-0">Keeping the same difficulty, language and marks.</p>
                </div>
                <div id="aqRegenDiff" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="small fw-bold text-uppercase text-muted mb-2">Current version</h6>
                            <div class="aq-old-box">
                                <p class="fw-bold mb-2" id="aqOldQ"></p>
                                <div id="aqOldOpts"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-stars me-1"></i>AI regenerated</h6>
                            <div class="aq-new-box">
                                <p class="fw-bold mb-2" id="aqNewQ"></p>
                                <div id="aqNewOpts"></div>
                            </div>
                        </div>
                    </div>
                    <form method="post" id="aqRegenSaveForm" class="mt-3">
                        <input type="hidden" name="regenerate_save" value="1">
                        <input type="hidden" name="qid" id="aqSaveQid" value="">
                        <input type="hidden" name="question" id="aqSaveQ" value="">
                        <input type="hidden" name="option_a" id="aqSaveA" value="">
                        <input type="hidden" name="option_b" id="aqSaveB" value="">
                        <input type="hidden" name="option_c" id="aqSaveC" value="">
                        <input type="hidden" name="option_d" id="aqSaveD" value="">
                        <input type="hidden" name="correct_answer" id="aqSaveAns" value="">
                    </form>
                </div>
                <div id="aqRegenError" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer" id="aqRegenFooter" style="display:none;">
                <button type="button" class="sp-btn sp-btn-ghost" id="aqRegenAgainBtn"><i class="bi bi-arrow-clockwise"></i> Regenerate Again</button>
                <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="sp-btn sp-btn-ai" id="aqRegenSaveBtn"><i class="bi bi-check-lg"></i> Save as Question <span id="aqRegenSaveId"></span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content professional-modal">
            <div class="modal-header"><h5 class="modal-title">Add Question</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="">
                <?php $modal_row = array("id" => "add", "subject_id" => "", "question" => "", "language" => "English", "option_a" => "", "option_b" => "", "option_c" => "", "option_d" => "", "correct_answer" => "A", "difficulty" => "Easy", "marks" => 1); include("question_form_fields.php"); ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-gradient" type="submit">Add Question</button></div>
        </form>
    </div>
</div>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

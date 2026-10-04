<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Announcements & Notices";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";
$types = announcement_types();

// ─── AJAX: scope subjects + eligible students + exam meta ───
if (isset($_GET["ajax_action"])) {
    header("Content-Type: application/json; charset=UTF-8");
    $aa = $_GET["ajax_action"];
    if ($aa === "scope_subjects") {
        $c = trim($_GET["course"] ?? "");
        $s = trim($_GET["semester"] ?? "");
        echo json_encode(array("success" => true, "subjects" => faculty_subjects_for_scope($faculty_id, $c, $s)));
        exit;
    }
    if ($aa === "scope_exams") {
        $c = trim($_GET["course"] ?? "");
        $s = trim($_GET["semester"] ?? "");
        $sid = (int)($_GET["subject_id"] ?? 0);
        $sql = "SELECT id, exam_title, exam_date, duration, exam_mode FROM exams WHERE subject_id IN ($subject_ids_csv) AND status='Active'";
        if ($c !== "") { $sql .= " AND course='" . mysqli_real_escape_string($conn, $c) . "'"; }
        if ($s !== "") { $sql .= " AND semester='" . mysqli_real_escape_string($conn, $s) . "'"; }
        if ($sid > 0) { $sql .= " AND subject_id=$sid"; }
        $sql .= " ORDER BY exam_date DESC, id DESC LIMIT 100";
        $rows = array();
        $q = mysqli_query($conn, $sql);
        if ($q) { while ($r = mysqli_fetch_assoc($q)) { $rows[] = $r; } }
        echo json_encode(array("success" => true, "exams" => $rows));
        exit;
    }
    if ($aa === "audience_count") {
        $c = trim($_GET["course"] ?? "");
        $s = trim($_GET["semester"] ?? "");
        $aud = $_GET["audience"] ?? "all";
        $sid = (int)($_GET["subject_id"] ?? 0);
        if ($c === "" || $s === "" || !academic_valid_course($c) || !academic_valid_semester($s)) {
            echo json_encode(array("success" => false, "message" => "Select a valid course and semester."));
            exit;
        }
        $ids = announcement_scope_students($c, $s, $sid, in_array($aud, array("all", "subject", "specific"), true) ? $aud : "all", array());
        echo json_encode(array("success" => true, "count" => count($ids), "audience" => $aud));
        exit;
    }
    if ($aa === "scope_students") {
        $c = trim($_GET["course"] ?? "");
        $s = trim($_GET["semester"] ?? "");
        $rows = array();
        if ($c !== "" && $s !== "") {
            $cc = mysqli_real_escape_string($conn, $c);
            $ss = mysqli_real_escape_string($conn, $s);
            $q = mysqli_query($conn, "SELECT id, name, email FROM students WHERE course='$cc' AND semester='$ss' AND status='Active' ORDER BY name ASC LIMIT 300");
            if ($q) { while ($r = mysqli_fetch_assoc($q)) { $rows[] = $r; } }
        }
        echo json_encode(array("success" => true, "students" => $rows));
        exit;
    }
    if ($aa === "exam_meta") {
        $eid = (int)($_GET["exam_id"] ?? 0);
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE e.id=$eid LIMIT 1"));
        if (!$row || !in_array((int)$row["subject_id"], $assigned_subject_ids)) {
            echo json_encode(array("success" => false, "message" => "Unauthorized exam."));
            exit;
        }
        echo json_encode(array("success" => true, "exam" => array(
            "id" => (int)$row["id"], "title" => $row["exam_title"], "course" => $row["course"] ?? "",
            "semester" => $row["semester"] ?? "", "subject_id" => (int)$row["subject_id"],
            "subject" => $row["subject_name"] ?? "", "date" => $row["exam_date"],
            "duration" => (int)$row["duration"], "mode" => $row["exam_mode"] ?? "Standard",
        )));
        exit;
    }
    echo json_encode(array("success" => false, "message" => "Unknown action."));
    exit;
}

// ─── Actions on own announcements ───
if (isset($_GET["publish"])) {
    $aid = (int)$_GET["publish"];
    $a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM announcements WHERE id=$aid AND faculty_id=$faculty_id LIMIT 1"));
    if (!$a) { redirect_alert("Announcement not found.", "announcements.php", "error"); }
    mysqli_query($conn, "UPDATE announcements SET status='Published', publish_at=NOW() WHERE id=$aid");
    $n = announcement_publish($aid);
    redirect_alert("Announcement published successfully. Delivered to $n eligible student" . ($n === 1 ? "." : "s."), "announcements.php", "success");
}
if (isset($_GET["archive"])) {
    $aid = (int)$_GET["archive"];
    $a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM announcements WHERE id=$aid AND faculty_id=$faculty_id LIMIT 1"));
    if (!$a) { redirect_alert("Announcement not found.", "announcements.php", "error"); }
    mysqli_query($conn, "UPDATE announcements SET status='Archived' WHERE id=$aid");
    redirect_alert("Announcement archived.", "announcements.php", "success");
}
if (isset($_GET["pin"])) {
    $aid = (int)$_GET["pin"];
    $a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, is_pinned FROM announcements WHERE id=$aid AND faculty_id=$faculty_id LIMIT 1"));
    if (!$a) { redirect_alert("Announcement not found.", "announcements.php", "error"); }
    $np = ((int)$a["is_pinned"] === 1) ? 0 : 1;
    mysqli_query($conn, "UPDATE announcements SET is_pinned=$np WHERE id=$aid");
    redirect_alert($np ? "Announcement pinned to the top." : "Announcement unpinned.", "announcements.php", "success");
}
if (isset($_GET["delete"])) {
    $aid = (int)$_GET["delete"];
    $a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, status FROM announcements WHERE id=$aid AND faculty_id=$faculty_id LIMIT 1"));
    if (!$a) { redirect_alert("Announcement not found.", "announcements.php", "error"); }
    if (($a["status"] ?? "") !== "Draft") {
        redirect_alert("Only draft announcements can be deleted. Archive it instead to preserve records.", "announcements.php", "error");
    }
    mysqli_query($conn, "DELETE FROM announcement_recipients WHERE announcement_id=$aid");
    mysqli_query($conn, "DELETE FROM announcements WHERE id=$aid");
    redirect_alert("Draft deleted.", "announcements.php", "success");
}

// ─── Create announcement ───
$form_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form_action"] ?? "") === "create_announcement") {
    $type = trim($_POST["type"] ?? "general");
    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $semester = trim($_POST["semester"] ?? "");
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $audience = $_POST["audience"] ?? "all";
    $exam_id = (int)($_POST["exam_id"] ?? 0);
    $priority = $_POST["priority"] ?? "normal";
    $is_pinned = !empty($_POST["is_pinned"]) ? 1 : 0;
    $publish_mode = $_POST["publish_mode"] ?? "now";
    $schedule_at = trim($_POST["schedule_at"] ?? "");
    $expiry_at = trim($_POST["expiry_at"] ?? "");
    $specific_raw = $_POST["specific_students"] ?? array();

    if (!announcement_valid_type($type)) { $form_error = "Please select a valid announcement type."; }
    if (!$form_error && ($title === "" || mb_strlen($title) < 5)) { $form_error = "Title must be at least 5 characters."; }
    if (!$form_error && ($message === "" || mb_strlen($message) < 10)) { $form_error = "Message must be at least 10 characters."; }
    if (!$form_error && ($course === "" || !academic_valid_course($course))) { $form_error = "Please select a course/program."; }
    if (!$form_error && ($semester === "" || !academic_valid_semester($semester))) { $form_error = "Please select a semester."; }
    if (!$form_error && !in_array($audience, array("all", "subject", "specific"), true)) { $form_error = "Please select a valid target audience."; }
    if (!$form_error && !array_key_exists($priority, announcement_priorities())) { $form_error = "Please select a valid priority."; }

    // Subject must be assigned + scope-compatible (when set)
    if (!$form_error && $subject_id > 0) {
        if (!faculty_has_subject($faculty_id, $subject_id)) { $form_error = "Invalid academic scope or unauthorized subject."; }
        elseif (!subject_accepts_scope($subject_id, $course, $semester)) { $form_error = "This subject is not assigned to the selected academic scope."; }
    }
    if (!$form_error && $audience === "subject" && $subject_id <= 0) { $form_error = "Select a subject for subject-student targeting."; }

    // Exam must belong to assigned subjects and match the chosen scope
    $exam_scope = null;
    if (!$form_error && $exam_id > 0) {
        $exam_scope = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM exams WHERE id=$exam_id LIMIT 1"));
        if (!$exam_scope || !in_array((int)$exam_scope["subject_id"], $assigned_subject_ids)) { $form_error = "Invalid academic scope or unauthorized subject."; }
        elseif (trim($exam_scope["course"] ?? "") !== $course || trim($exam_scope["semester"] ?? "") !== $semester) { $form_error = "The selected exam does not belong to the chosen course and semester."; }
        elseif ($subject_id > 0 && (int)$exam_scope["subject_id"] !== $subject_id) { $form_error = "The selected exam does not belong to the chosen subject."; }
        if (!$form_error && $subject_id <= 0) { $subject_id = (int)$exam_scope["subject_id"]; }
    }

    // Specific students → validate inside scope
    $specific_ids = array();
    if (!$form_error && $audience === "specific") {
        foreach ((array)$specific_raw as $sid) { $sid = (int)$sid; if ($sid > 0) { $specific_ids[] = $sid; } }
        $specific_ids = array_values(array_unique($specific_ids));
        if (empty($specific_ids)) { $form_error = "Select at least one student for specific targeting."; }
        else {
            $specific_ids = announcement_scope_students($course, $semester, 0, "specific", $specific_ids);
            if (empty($specific_ids)) { $form_error = "No eligible students found for this course and semester."; }
        }
    }

    // Publish mode
    $status = "Draft";
    $publish_sql = "NULL";
    if (!$form_error) {
        if ($publish_mode === "now") {
            $status = "Published";
            $publish_sql = "NOW()";
        } elseif ($publish_mode === "schedule") {
            $ts = strtotime($schedule_at);
            if (!$ts || $ts <= time() + 60) { $form_error = "Schedule must be a future date and time."; }
            else {
                $status = "Scheduled";
                $publish_sql = "'" . date("Y-m-d H:i:s", $ts) . "'";
            }
        }
    }
    $expiry_sql = "NULL";
    if (!$form_error && $expiry_at !== "") {
        $ets = strtotime($expiry_at);
        if (!$ets) { $form_error = "Expiry date and time are invalid."; }
        else { $expiry_sql = "'" . date("Y-m-d H:i:s", $ets) . "'"; }
    }

    if (!$form_error) {
        $title_s = mysqli_real_escape_string($conn, $title);
        $msg_s = mysqli_real_escape_string($conn, $message);
        $course_s = mysqli_real_escape_string($conn, $course);
        $sem_s = mysqli_real_escape_string($conn, $semester);
        $sub_sql = $subject_id > 0 ? $subject_id : "NULL";
        $exam_sql = $exam_id > 0 ? $exam_id : "NULL";
        $aud_s = mysqli_real_escape_string($conn, $audience);
        $pri_s = mysqli_real_escape_string($conn, $priority);
        $aud_ids_sql = ($audience === "specific" && !empty($specific_ids)) ? "'" . mysqli_real_escape_string($conn, json_encode(array_values($specific_ids))) . "'" : "NULL";
        $ok = mysqli_query($conn, "INSERT INTO announcements (faculty_id,type,title,message,course,semester,subject_id,exam_id,audience,audience_ids,priority,is_pinned,status,publish_at,expires_at) VALUES ($faculty_id,'" . mysqli_real_escape_string($conn, $type) . "','$title_s','$msg_s','$course_s','$sem_s',$sub_sql,$exam_sql,'$aud_s',$aud_ids_sql,'$pri_s',$is_pinned,'$status',$publish_sql,$expiry_sql)");
        if ($ok) {
            $new_id = (int)mysqli_insert_id($conn);
            if ($status === "Published") {
                $n = announcement_publish($new_id);
                redirect_alert("Announcement published successfully. Delivered to $n eligible student" . ($n === 1 ? "." : "s."), "announcements.php", "success");
            } else {
                redirect_alert($status === "Scheduled" ? "Announcement scheduled successfully." : "Draft saved successfully.", "announcements.php", "success");
            }
        } else {
            $form_error = "Could not save the announcement. Please try again.";
        }
    }
    if ($form_error) { flash_script($form_error, "", "error"); }
}

// ─── Stats ───
$st_total = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcements WHERE faculty_id=$faculty_id"))["c"] ?? 0);
$st_pub = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcements WHERE faculty_id=$faculty_id AND status='Published'"))["c"] ?? 0);
$st_sched = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcements WHERE faculty_id=$faculty_id AND status='Scheduled'"))["c"] ?? 0);
$st_draft = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcements WHERE faculty_id=$faculty_id AND status='Draft'"))["c"] ?? 0);

// ─── List filters ───
$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_type = isset($_GET["type"]) ? trim($_GET["type"]) : "";
if ($f_type !== "" && !announcement_valid_type($f_type)) { $f_type = ""; }
$f_course = isset($_GET["course"]) ? trim($_GET["course"]) : "";
if ($f_course !== "" && !academic_valid_course($f_course)) { $f_course = ""; }
$f_semester = isset($_GET["semester"]) ? trim($_GET["semester"]) : "";
if ($f_semester !== "" && !academic_valid_semester($f_semester)) { $f_semester = ""; }
$f_status = isset($_GET["status"]) ? trim($_GET["status"]) : "";
if (!in_array($f_status, array("", "Draft", "Scheduled", "Published", "Archived"), true)) { $f_status = ""; }
$page = max(1, (int)($_GET["page"] ?? 1));
$per_page = 9;
$offset = ($page - 1) * $per_page;

$where = " WHERE a.faculty_id=$faculty_id ";
if ($f_search !== "") { $s = mysqli_real_escape_string($conn, $f_search); $where .= " AND (a.title LIKE '%$s%' OR a.message LIKE '%$s%') "; }
if ($f_type !== "") { $where .= " AND a.type='" . mysqli_real_escape_string($conn, $f_type) . "' "; }
if ($f_course !== "") { $where .= " AND a.course='" . mysqli_real_escape_string($conn, $f_course) . "' "; }
if ($f_semester !== "") { $where .= " AND a.semester='" . mysqli_real_escape_string($conn, $f_semester) . "' "; }
if ($f_status !== "") { $where .= " AND a.status='" . mysqli_real_escape_string($conn, $f_status) . "' "; }

$total_rows = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcements a $where"))["c"] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$list = mysqli_query($conn, "SELECT a.*, s.subject_name,
    (SELECT COUNT(*) FROM announcement_recipients ar WHERE ar.announcement_id=a.id) AS targeted,
    (SELECT COUNT(*) FROM announcement_recipients ar WHERE ar.announcement_id=a.id AND ar.read_at IS NOT NULL) AS read_count
    FROM announcements a LEFT JOIN subjects s ON s.id=a.subject_id
    $where ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT $per_page OFFSET $offset");

function fp_ann_page_url($params) {
    $parts = array();
    foreach ($params as $k => $v) {
        if ($v === "" || $v === 0 || $v === "0") { continue; }
        $parts[] = urlencode($k) . "=" . urlencode((string)$v);
    }
    return "announcements.php" . ($parts ? "?" . implode("&", $parts) : "");
}
$ann_base = array("search" => $f_search, "type" => $f_type, "course" => $f_course, "semester" => $f_semester, "status" => $f_status);

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Announcements</li>
                </ol>
            </nav>

            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-megaphone-fill"></i> Communication</span>
                    <h1>Announcements &amp; Notices</h1>
                    <p>Create, schedule and manage academic announcements for your students.</p>
                </div>
                <button type="button" class="sp-btn sp-btn-primary" data-bs-toggle="modal" data-bs-target="#annCreateModal"><i class="bi bi-plus-circle"></i> Create Announcement</button>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <?php $acards = array(
                    array("Total", $st_total, "bi-collection", ""),
                    array("Published", $st_pub, "bi-check2-circle", "is-success"),
                    array("Scheduled", $st_sched, "bi-clock", "is-warning"),
                    array("Drafts", $st_draft, "bi-pencil-square", "is-info"),
                ); $ai = 0; foreach ($acards as $ac) { $ai++; ?>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="<?php echo $ai; ?>">
                        <div class="sp-stat <?php echo $ac[3]; ?>">
                            <span class="sp-stat-icon"><i class="bi <?php echo $ac[2]; ?>"></i></span>
                            <div class="sp-stat-value" data-count="<?php echo (int)$ac[1]; ?>">0</div>
                            <div class="sp-stat-label"><?php echo h($ac[0]); ?></div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Filters -->
            <form method="get" class="sp-filters sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="annSearch">Search</label>
                        <input type="text" class="form-control" id="annSearch" name="search" value="<?php echo h($f_search); ?>" placeholder="Title or message...">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="annType">Type</label>
                        <select class="form-select" id="annType" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($types as $tk => $tv) { ?>
                                <option value="<?php echo h($tk); ?>" <?php echo $f_type === $tk ? "selected" : ""; ?>><?php echo h($tv["label"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fltAnnCourse">Course</label>
                        <select class="form-select" id="fltAnnCourse" name="course">
                            <option value="">All Courses</option>
                            <?php foreach (academic_course_options() as $c_opt) { ?>
                                <option value="<?php echo h($c_opt); ?>" <?php echo $f_course === $c_opt ? "selected" : ""; ?>><?php echo h($c_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fltAnnSemester">Semester</label>
                        <select class="form-select" id="fltAnnSemester" name="semester">
                            <option value="">All Semesters</option>
                            <?php foreach (academic_semester_options() as $s_opt) { ?>
                                <option value="<?php echo h($s_opt); ?>" <?php echo $f_semester === $s_opt ? "selected" : ""; ?>><?php echo h($s_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="annStatus">Status</label>
                        <select class="form-select" id="annStatus" name="status">
                            <option value="">All Status</option>
                            <?php foreach (array("Draft", "Scheduled", "Published", "Archived") as $st) { ?>
                                <option value="<?php echo h($st); ?>" <?php echo $f_status === $st ? "selected" : ""; ?>><?php echo h($st); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex gap-2">
                        <button type="submit" class="sp-btn sp-btn-primary w-100" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
                    </div>
                </div>
                <div class="mt-2">
                    <?php if ($f_search !== "" || $f_type !== "" || $f_course !== "" || $f_semester !== "" || $f_status !== "") { ?>
                        <a href="announcements.php" class="small text-decoration-none"><i class="bi bi-x-circle me-1"></i>Clear all filters</a>
                    <?php } else { ?>
                        <span class="sp-filter-count"><?php echo $total_rows; ?> announcement<?php echo $total_rows === 1 ? "" : "s"; ?></span>
                    <?php } ?>
                </div>
            </form>

            <!-- Cards -->
            <div class="row g-3" id="annGrid">
                <?php if ($list && mysqli_num_rows($list) > 0) {
                    while ($a = mysqli_fetch_assoc($list)) {
                        $t = $types[$a["type"]] ?? $types["general"];
                        $targeted = (int)($a["targeted"] ?? 0);
                        $read = (int)($a["read_count"] ?? 0);
                        $rate = $targeted > 0 ? round(($read / $targeted) * 100, 1) : 0;
                        $status = $a["status"];
                        $expired = !empty($a["expires_at"]) && strtotime($a["expires_at"]) < time();
                ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up">
                        <div class="sp-perf-card h-100 d-flex flex-column">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <span class="sp-stat-icon" style="width:40px;height:40px;font-size:1.05rem;"><i class="bi <?php echo h($t["icon"]); ?>"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <h5 class="fw-bold mb-0 text-truncate"><?php echo h($a["title"]); ?></h5>
                                    <small class="text-muted"><?php echo h($t["label"]); ?> &middot; <?php echo date("M j, Y", strtotime($a["created_at"])); ?></small>
                                </div>
                                <?php if ((int)$a["is_pinned"] === 1) { ?><span class="sp-pill is-upcoming" title="Pinned"><i class="bi bi-pin-fill"></i></span><?php } ?>
                            </div>
                            <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo h(mb_substr($a["message"], 0, 140)); ?></p>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <span class="badge bg-primary-soft text-primary"><?php echo h($a["course"]); ?> &middot; <?php echo h($a["semester"]); ?></span>
                                <?php if (!empty($a["subject_name"])) { ?><span class="badge bg-light text-dark"><?php echo h($a["subject_name"]); ?></span><?php } ?>
                                <span class="badge bg-<?php echo $status === "Published" ? "success" : ($status === "Scheduled" ? "warning text-dark" : ($status === "Archived" ? "secondary" : "info")); ?>"><?php echo h($status); ?></span>
                                <?php if ($expired && $status === "Published") { ?><span class="badge bg-secondary">Expired</span><?php } ?>
                                <?php if (($a["priority"] ?? "normal") !== "normal") { ?><span class="badge bg-<?php echo $a["priority"] === "urgent" ? "danger" : "warning text-dark"; ?>"><?php echo h(ucfirst($a["priority"])); ?></span><?php } ?>
                            </div>
                            <?php if ($status === "Published") { ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Read rate</span><strong><?php echo $rate; ?>% (<?php echo $read; ?>/<?php echo $targeted; ?>)</strong></div>
                                    <div class="sp-track"><span style="width:<?php echo $rate; ?>%;"></span></div>
                                </div>
                            <?php } else { ?>
                                <div class="mb-2 small text-muted"><i class="bi bi-people me-1"></i><span class="ann-target-preview" data-course="<?php echo h($a["course"]); ?>" data-semester="<?php echo h($a["semester"]); ?>">…</span> students in scope</div>
                            <?php } ?>
                            <div class="d-flex flex-wrap gap-1 mt-auto pt-2 border-top">
                                <a class="sp-btn sp-btn-soft" style="min-height:38px;padding:6px 14px;font-size:.8rem;" href="announcement_view.php?id=<?php echo (int)$a["id"]; ?>"><i class="bi bi-eye"></i> View</a>
                                <?php if ($status === "Draft" || $status === "Scheduled") { ?>
                                    <a class="sp-btn sp-btn-primary js-confirm-publish" style="min-height:38px;padding:6px 14px;font-size:.8rem;" href="announcements.php?publish=<?php echo (int)$a["id"]; ?>" data-count-preview data-course="<?php echo h($a["course"]); ?>" data-semester="<?php echo h($a["semester"]); ?>"><i class="bi bi-send"></i> Publish</a>
                                <?php } ?>
                                <a class="sp-btn sp-btn-ghost" style="min-height:38px;padding:6px 12px;font-size:.8rem;" href="announcements.php?pin=<?php echo (int)$a["id"]; ?>" title="<?php echo (int)$a["is_pinned"] === 1 ? "Unpin" : "Pin"; ?>"><i class="bi bi-pin<?php echo (int)$a["is_pinned"] === 1 ? "-fill" : ""; ?>"></i></a>
                                <?php if ($status === "Published") { ?>
                                    <a class="sp-btn sp-btn-ghost" style="min-height:38px;padding:6px 12px;font-size:.8rem;" href="announcements.php?archive=<?php echo (int)$a["id"]; ?>" title="Archive"><i class="bi bi-archive"></i></a>
                                <?php } elseif ($status === "Draft") { ?>
                                    <a class="sp-btn sp-btn-ghost js-confirm-delete" style="min-height:38px;padding:6px 12px;font-size:.8rem;color:#DC2626;" href="announcements.php?delete=<?php echo (int)$a["id"]; ?>" title="Delete draft"><i class="bi bi-trash"></i></a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php }
                } else { ?>
                    <div class="col-12">
                        <div class="sp-empty">
                            <div class="sp-empty-icon"><i class="bi bi-megaphone"></i></div>
                            <h4>No Announcements Yet</h4>
                            <p>Create your first announcement to keep students informed.</p>
                            <button type="button" class="sp-btn sp-btn-primary" data-bs-toggle="modal" data-bs-target="#annCreateModal"><i class="bi bi-plus-circle"></i> Create Announcement</button>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <?php if ($total_pages > 1) { ?>
                <nav class="sp-pagination mt-4" aria-label="Announcements pagination">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
                        if ($page > 1) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo fp_ann_page_url(array_merge($ann_base, array("page" => $page - 1))); ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>
                        <?php }
                        for ($i = $start; $i <= $end; $i++) { ?>
                            <li class="page-item <?php echo $i == $page ? "active" : ""; ?>"><a class="page-link" href="<?php echo fp_ann_page_url(array_merge($ann_base, array("page" => $i))); ?>"><?php echo $i; ?></a></li>
                        <?php }
                        if ($page < $total_pages) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo fp_ann_page_url(array_merge($ann_base, array("page" => $page + 1))); ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>
                        <?php } ?>
                    </ul>
                </nav>
            <?php } ?>

        </div>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="annCreateModal" tabindex="-1" aria-labelledby="annCreateLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content professional-modal" id="mAnnCreateForm">
            <input type="hidden" name="form_action" value="create_announcement">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="annCreateLabel"><i class="bi bi-megaphone me-2 text-primary"></i>Create Announcement</h5>
                    <small class="text-muted">Target the right students — course + semester are required.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="fp-label" for="mAnnType">Announcement Type <span class="text-danger">*</span></label>
                        <select id="mAnnType" name="type" class="form-select" required>
                            <?php foreach ($types as $tk => $tv) { ?>
                                <option value="<?php echo h($tk); ?>"><?php echo h($tv["label"]); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="fp-label" for="mAnnTitle">Title <span class="text-danger">*</span></label>
                        <input id="mAnnTitle" name="title" class="form-control" placeholder="e.g. DBMS Quiz Tomorrow" required minlength="5" maxlength="150">
                    </div>
                    <div class="col-12">
                        <label class="fp-label" for="mAnnMessage">Message <span class="text-danger">*</span></label>
                        <textarea id="mAnnMessage" name="message" class="form-control" rows="3" placeholder="Write announcement details..." required minlength="10"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="mAnnCourse">Course / Program <span class="text-danger">*</span></label>
                        <select id="mAnnCourse" name="course" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach (academic_course_options() as $c_opt) { ?>
                                <option value="<?php echo h($c_opt); ?>"><?php echo h($c_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="mAnnSemester">Semester / Year <span class="text-danger">*</span></label>
                        <select id="mAnnSemester" name="semester" class="form-select" required>
                            <option value="">Select Semester</option>
                            <?php foreach (academic_semester_options() as $s_opt) { ?>
                                <option value="<?php echo h($s_opt); ?>"><?php echo h($s_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="fp-label" for="mAnnSubject">Subject <span class="text-muted fw-normal">(optional)</span></label>
                        <select id="mAnnSubject" name="subject_id" class="form-select">
                            <option value="0">All Subjects in Scope</option>
                        </select>
                        <small class="fp-hint" id="mAnnSubjectHint">Select course + semester first.</small>
                    </div>
                    <div class="col-12">
                        <label class="fp-label d-block">Target Audience</label>
                        <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="Target audience">
                            <label class="sp-chip is-neutral mb-0" style="cursor:pointer;"><input type="radio" name="audience" value="all" checked class="me-1"> All Students in Course &amp; Semester</label>
                            <label class="sp-chip is-neutral mb-0" style="cursor:pointer;"><input type="radio" name="audience" value="subject" class="me-1"> Selected Subject Students</label>
                            <label class="sp-chip is-neutral mb-0" style="cursor:pointer;"><input type="radio" name="audience" value="specific" class="me-1"> Specific Students</label>
                        </div>
                        <div id="mAnnSpecificWrap" class="mt-2" style="display:none;">
                            <div id="mAnnSpecificList" class="d-flex flex-column gap-1 p-2 border rounded-3 bg-light" style="max-height:180px;overflow-y:auto;">
                                <small class="text-muted">Select course + semester to load students.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" id="mAnnExamWrap">
                        <label class="fp-label" for="mAnnExam">Link Examination <span class="text-muted fw-normal">(for Quiz/Exam type)</span></label>
                        <select id="mAnnExam" name="exam_id" class="form-select">
                            <option value="0">No linked exam</option>
                        </select>
                        <small class="fp-hint">Exam scope auto-fills course, semester and subject.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="fp-label" for="mAnnPriority">Priority</label>
                        <select id="mAnnPriority" name="priority" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="fp-label d-block">Visibility</label>
                        <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="Publish mode">
                            <label class="sp-chip is-neutral mb-0" style="cursor:pointer;"><input type="radio" name="publish_mode" value="now" checked class="me-1"> Publish Now</label>
                            <label class="sp-chip is-neutral mb-0" style="cursor:pointer;"><input type="radio" name="publish_mode" value="schedule" class="me-1"> Schedule for Later</label>
                            <label class="sp-chip is-neutral mb-0" style="cursor:pointer;"><input type="radio" name="publish_mode" value="draft" class="me-1"> Save Draft</label>
                        </div>
                        <div id="mAnnScheduleWrap" class="mt-2" style="display:none;">
                            <input type="datetime-local" name="schedule_at" class="form-control" aria-label="Schedule date and time">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fp-label" for="mAnnExpiry">Expiry <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="datetime-local" id="mAnnExpiry" name="expiry_at" class="form-control">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="mAnnPinned">
                            <label class="form-check-label small fw-semibold" for="mAnnPinned"><i class="bi bi-pin me-1"></i>Pin this announcement</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="fp-scope-box" id="annPreviewBox">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fp-scope-title"><i class="bi bi-eye me-1"></i>Target</span>
                                <span class="badge bg-primary-soft text-primary" id="mAnnPrevScope">—</span>
                                <span class="ms-auto sp-pill is-available"><i class="bi bi-people"></i><span id="mAnnPrevCount">—</span>&nbsp;students</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex flex-wrap gap-2">
                <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-primary ms-auto" id="mAnnSubmitBtn"><i class="bi bi-send"></i> Publish Now</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var course = document.getElementById("mAnnCourse");
    var semester = document.getElementById("mAnnSemester");
    var subject = document.getElementById("mAnnSubject");
    var exam = document.getElementById("mAnnExam");
    var prevScope = document.getElementById("mAnnPrevScope");
    var prevCount = document.getElementById("mAnnPrevCount");
    var submitBtn = document.getElementById("mAnnSubmitBtn");
    var form = document.getElementById("mAnnCreateForm");

    function audience() {
        var r = form.querySelector('input[name="audience"]:checked');
        return r ? r.value : "all";
    }
    function publishMode() {
        var r = form.querySelector('input[name="publish_mode"]:checked');
        return r ? r.value : "now";
    }

    function refreshPreview() {
        var c = course ? course.value : "", s = semester ? semester.value : "";
        var subj = subject && subject.value !== "0" ? subject.options[subject.selectedIndex].text : "";
        prevScope.textContent = (c || "—") + " • " + (s || "—") + (subj ? " • " + subj : "");
        var mode = publishMode();
        submitBtn.innerHTML = mode === "schedule"
            ? '<i class="bi bi-clock"></i> Schedule Announcement'
            : (mode === "draft" ? '<i class="bi bi-pencil-square"></i> Save Draft' : '<i class="bi bi-send"></i> Publish Now');
        document.getElementById("mAnnScheduleWrap").style.display = mode === "schedule" ? "block" : "none";
        var specWrap = document.getElementById("mAnnSpecificWrap");
        specWrap.style.display = audience() === "specific" ? "block" : "none";
        if (!c || !s) { prevCount.textContent = "—"; return; }
        prevCount.textContent = "…";
        fetch("announcements.php?ajax_action=audience_count&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s) + "&audience=" + encodeURIComponent(audience()) + "&subject_id=" + encodeURIComponent(subject ? subject.value : "0"))
            .then(r => r.json()).then(d => { prevCount.textContent = d.success ? d.count : "—"; })
            .catch(() => { prevCount.textContent = "—"; });
    }

    function reloadSubjects() {
        var c = course.value, s = semester.value;
        subject.innerHTML = '<option value="0">Loading...</option>';
        reloadExams(); refreshPreview(); loadSpecific();
        if (!c && !s) { subject.innerHTML = '<option value="0">All Subjects in Scope</option>'; return; }
        fetch("announcements.php?ajax_action=scope_subjects&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s))
            .then(r => r.json()).then(d => {
                subject.innerHTML = '<option value="0">All Subjects in Scope</option>';
                (d.subjects || []).forEach(sub => {
                    var o = document.createElement("option");
                    o.value = sub.id; o.textContent = sub.subject_name + " (" + (sub.subject_code || "") + ")";
                    subject.appendChild(o);
                });
                refreshPreview();
            });
    }

    function reloadExams() {
        var c = course.value, s = semester.value, sid = subject.value;
        exam.innerHTML = '<option value="0">Loading...</option>';
        fetch("announcements.php?ajax_action=scope_exams&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s) + "&subject_id=" + encodeURIComponent(sid))
            .then(r => r.json()).then(d => {
                exam.innerHTML = '<option value="0">No linked exam</option>';
                (d.exams || []).forEach(ex => {
                    var o = document.createElement("option");
                    o.value = ex.id; o.textContent = ex.exam_title + " (" + ex.exam_date + ")";
                    exam.appendChild(o);
                });
            });
    }

    function loadSpecific() {
        var list = document.getElementById("mAnnSpecificList");
        var c = course.value, s = semester.value;
        if (audience() !== "specific" || !c || !s) return;
        list.innerHTML = '<small class="text-muted">Loading students...</small>';
        fetch("announcements.php?ajax_action=scope_students&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s))
            .then(r => r.json()).then(d => {
                list.innerHTML = "";
                (d.students || []).forEach(st => {
                    var lab = document.createElement("label");
                    lab.className = "d-flex align-items-center gap-2 small bg-white border rounded-2 px-2 py-1";
                    lab.innerHTML = '<input type="checkbox" name="specific_students[]" value="' + st.id + '"> <span><strong>' + st.name.replace(/</g, "&lt;") + '</strong> <span class="text-muted">' + st.email.replace(/</g, "&lt;") + '</span></span>';
                    list.appendChild(lab);
                });
                if (!(d.students || []).length) list.innerHTML = '<small class="text-muted">No students in this scope.</small>';
            });
    }

    if (course) course.addEventListener("change", function() { reloadSubjects(); });
    if (semester) semester.addEventListener("change", function() { reloadSubjects(); });
    if (subject) subject.addEventListener("change", function() { reloadExams(); refreshPreview(); });
    form.querySelectorAll('input[name="audience"]').forEach(r => r.addEventListener("change", function() { loadSpecific(); refreshPreview(); }));
    form.querySelectorAll('input[name="publish_mode"]').forEach(r => r.addEventListener("change", refreshPreview));
    if (exam) exam.addEventListener("change", function() {
        if (!exam.value || exam.value === "0") return;
        fetch("announcements.php?ajax_action=exam_meta&exam_id=" + encodeURIComponent(exam.value))
            .then(r => r.json()).then(d => {
                if (!d.success) return;
                var ex = d.exam;
                course.value = ex.course || ""; semester.value = ex.semester || "";
                reloadSubjects();
                setTimeout(function() { if (ex.subject_id) { subject.value = String(ex.subject_id); } refreshPreview(); }, 400);
            });
    });

    // Publish confirmation with live count
    form.addEventListener("submit", function(e) {
        var mode = publishMode();
        if (mode === "draft") return; // plain save
        e.preventDefault();
        var c = course.value, s = semester.value;
        fetch("announcements.php?ajax_action=audience_count&course=" + encodeURIComponent(c) + "&semester=" + encodeURIComponent(s) + "&audience=" + encodeURIComponent(audience()) + "&subject_id=" + encodeURIComponent(subject.value))
            .then(r => r.json()).then(d => {
                var n = d.success ? d.count : "?";
                var msg = mode === "schedule" ? "This announcement will be delivered to " + n + " eligible students at the scheduled time." : "This announcement will be sent to " + n + " eligible students.";
                if (typeof Swal !== "undefined") {
                    Swal.fire({ title: mode === "schedule" ? "Schedule Announcement?" : "Publish Announcement?", text: msg, icon: "question", showCancelButton: true, confirmButtonText: mode === "schedule" ? "Schedule" : "Publish", cancelButtonText: "Cancel", confirmButtonColor: "#2563EB" })
                        .then(r => { if (r.isConfirmed) form.submit(); });
                } else if (confirm(msg)) { form.submit(); }
            });
    });

    // Card publish buttons: confirm with count
    document.querySelectorAll(".js-confirm-publish").forEach(function(link) {
        link.addEventListener("click", function(e) {
            e.preventDefault();
            var href = link.getAttribute("href");
            var n = "?";
            fetch("announcements.php?ajax_action=audience_count&course=" + encodeURIComponent(link.dataset.course || "") + "&semester=" + encodeURIComponent(link.dataset.semester || "") + "&audience=all&subject_id=0")
                .then(r => r.json()).then(d => {
                    n = d.success ? d.count : "?";
                    var msg = "This announcement will be sent to " + n + " eligible students.";
                    if (typeof Swal !== "undefined") {
                        Swal.fire({ title: "Publish Announcement?", text: msg, icon: "question", showCancelButton: true, confirmButtonText: "Publish", cancelButtonText: "Cancel", confirmButtonColor: "#2563EB" })
                            .then(r => { if (r.isConfirmed) window.location.href = href; });
                    } else if (confirm(msg)) { window.location.href = href; }
                });
        });
    });

    // Draft target preview counts
    document.querySelectorAll(".ann-target-preview").forEach(function(el) {
        fetch("announcements.php?ajax_action=audience_count&course=" + encodeURIComponent(el.dataset.course || "") + "&semester=" + encodeURIComponent(el.dataset.semester || "") + "&audience=all&subject_id=0")
            .then(r => r.json()).then(d => { el.textContent = d.success ? d.count : "—"; });
    });
});
</script>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>
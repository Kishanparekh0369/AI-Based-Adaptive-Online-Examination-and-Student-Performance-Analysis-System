<?php
require_once("../app/config/database.php");

$conn = conn();
require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Manage Faculty";

// Handle Delete
if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    if ($id > 0) {
        $faculty_check = mysqli_query($conn, "SELECT profile_image FROM faculty WHERE id=$id");
        if ($faculty_check && $f_row = mysqli_fetch_assoc($faculty_check)) {
            if (!empty($f_row["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/faculty/" . $f_row["profile_image"])) {
                @unlink(__DIR__ . "/../assets/uploads/faculty/" . $f_row["profile_image"]);
            }
        }
        mysqli_query($conn, "DELETE FROM faculty_subjects WHERE faculty_id=$id");
        mysqli_query($conn, "DELETE FROM faculty WHERE id=$id");
        redirect_alert("Faculty deleted successfully", "faculty.php", "success");
    }
}

if (isset($_GET["toggle"])) {
    $id = (int)$_GET["toggle"];
    $stmt = mysqli_prepare($conn, "SELECT status FROM faculty WHERE id=? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $trow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($trow) {
            $new_status = (($trow["status"] ?? "") === "Active") ? "Inactive" : "Active";
            $ustmt = mysqli_prepare($conn, "UPDATE faculty SET status=? WHERE id=? LIMIT 1");
            if ($ustmt) {
                mysqli_stmt_bind_param($ustmt, "si", $new_status, $id);
                mysqli_stmt_execute($ustmt);
                mysqli_stmt_close($ustmt);
            }
            redirect_alert("Faculty account " . strtolower($new_status), "faculty.php", "success");
        }
    }
    redirect_alert("Faculty not found", "faculty.php", "error");
}

// Handle Add / Edit
$edit_id = "";
$name = "";
$email = "";
$phone = "";
$status = "Active";
$assigned_subjects = array();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $edit_id = (int)clean_data($_POST["edit_id"] ?? 0);
    $name = clean_data($_POST["name"] ?? "");
    $email = clean_data($_POST["email"] ?? "");
    $phone = clean_data($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $status = clean_data($_POST["status"] ?? "Active");
    $subjects = isset($_POST["subjects"]) && is_array($_POST["subjects"]) ? $_POST["subjects"] : array();

    if ($name == "" || $email == "") {
        redirect_alert("Faculty Name and Email are required", "faculty.php", "error");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_alert("Please enter a valid email address", "faculty.php", "error");
    }

    $email_safe = mysqli_real_escape_string($conn, $email);
    $check_email = mysqli_query($conn, "SELECT id FROM faculty WHERE email='$email_safe'" . ($edit_id > 0 ? " AND id!=$edit_id" : ""));
    if ($check_email && mysqli_num_rows($check_email) > 0) {
        redirect_alert("A faculty account with this email already exists", "faculty.php", "error");
    }

    $uploaded_image = upload_faculty_profile_image("profile_image", "faculty.php");

    if ($edit_id > 0) {
        // Edit existing faculty
        $sql_parts = array(
            "name='" . mysqli_real_escape_string($conn, $name) . "'",
            "email='" . $email_safe . "'",
            "phone='" . mysqli_real_escape_string($conn, $phone) . "'",
            "status='" . mysqli_real_escape_string($conn, $status) . "'"
        );
        if ($password !== "") {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql_parts[] = "password='" . mysqli_real_escape_string($conn, $hashed) . "'";
        }
        if ($uploaded_image != "") {
            $old_q = mysqli_query($conn, "SELECT profile_image FROM faculty WHERE id=$edit_id");
            if ($old_q && $old_row = mysqli_fetch_assoc($old_q)) {
                if (!empty($old_row["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/faculty/" . $old_row["profile_image"])) {
                    @unlink(__DIR__ . "/../assets/uploads/faculty/" . $old_row["profile_image"]);
                }
            }
            $sql_parts[] = "profile_image='" . mysqli_real_escape_string($conn, $uploaded_image) . "'";
        }

        $update_sql = "UPDATE faculty SET " . implode(", ", $sql_parts) . " WHERE id=$edit_id";
        if (mysqli_query($conn, $update_sql)) {
            // Update subjects
            mysqli_query($conn, "DELETE FROM faculty_subjects WHERE faculty_id=$edit_id");
            foreach ($subjects as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) {
                    mysqli_query($conn, "INSERT INTO faculty_subjects (faculty_id, subject_id) VALUES ($edit_id, $sid)");
                }
            }
            redirect_alert("Faculty updated successfully", "faculty.php", "success");
        } else {
            redirect_alert("Update failed: " . mysqli_error($conn), "faculty.php", "error");
        }
    } else {
        // Add new faculty
        if ($password == "") {
            redirect_alert("Password is required for new faculty account", "faculty.php", "error");
        }
        if (strlen($password) < 6) {
            redirect_alert("Password must be at least 6 characters long", "faculty.php", "error");
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $name_safe = mysqli_real_escape_string($conn, $name);
        $phone_safe = mysqli_real_escape_string($conn, $phone);
        $status_safe = mysqli_real_escape_string($conn, $status);
        $img_safe = $uploaded_image != "" ? "'" . mysqli_real_escape_string($conn, $uploaded_image) . "'" : "NULL";

        $insert_sql = "INSERT INTO faculty (name, email, phone, password, profile_image, status) VALUES ('$name_safe', '$email_safe', '$phone_safe', '$hashed', $img_safe, '$status_safe')";
        if (mysqli_query($conn, $insert_sql)) {
            $new_faculty_id = mysqli_insert_id($conn);
            foreach ($subjects as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) {
                    mysqli_query($conn, "INSERT INTO faculty_subjects (faculty_id, subject_id) VALUES ($new_faculty_id, $sid)");
                }
            }
            notify_user("faculty", $new_faculty_id, "Welcome", "Your faculty account was created by Admin.", "dashboard.php");
            redirect_alert("Faculty added successfully", "faculty.php", "success");
        } else {
            redirect_alert("Insert failed: " . mysqli_error($conn), "faculty.php", "error");
        }
    }
}

include("../app/layouts/admin_header.php");

// Fetch subjects for assignment options
$all_subjects = array();
$sub_query = mysqli_query($conn, "SELECT id, subject_name, subject_code, course, semester FROM subjects WHERE status='Active' ORDER BY subject_name ASC");
if ($sub_query) {
    while ($s = mysqli_fetch_assoc($sub_query)) {
        $all_subjects[] = $s;
    }
}

// ---- Filters (validated; academic scope via real relationships) ----
$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_subject = isset($_GET["subject"]) ? (int)$_GET["subject"] : 0;
$f_status = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "";
if (!in_array($f_status, array("", "Active", "Inactive"), true)) { $f_status = ""; }
$f_course = isset($_GET["course"]) ? trim((string)$_GET["course"]) : "";
if ($f_course !== "" && !academic_valid_course($f_course)) { $f_course = ""; }
$f_semester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
if ($f_semester !== "" && !academic_valid_semester($f_semester)) { $f_semester = ""; }
$f_page = max(1, (int)($_GET["page"] ?? 1));
$f_per = 10;

$conds = array(); $types = ""; $params = array();
if ($f_search !== "") {
    $like = "%" . $f_search . "%";
    if (ctype_digit($f_search)) {
        $conds[] = "(f.name LIKE ? OR f.email LIKE ? OR f.phone LIKE ? OR s.subject_name LIKE ? OR f.id = ?)";
        $types .= "ssssi"; array_push($params, $like, $like, $like, $like, (int)$f_search);
    } else {
        $conds[] = "(f.name LIKE ? OR f.email LIKE ? OR f.phone LIKE ? OR s.subject_name LIKE ?)";
        $types .= "ssss"; array_push($params, $like, $like, $like, $like);
    }
}
if ($f_subject > 0) {
    $conds[] = "EXISTS (SELECT 1 FROM faculty_subjects fs2 WHERE fs2.faculty_id=f.id AND fs2.subject_id=?)";
    $types .= "i"; $params[] = $f_subject;
}
if ($f_status !== "") { $conds[] = "f.status = ?"; $types .= "s"; $params[] = $f_status; }
if ($f_course !== "") {
    $conds[] = "EXISTS (SELECT 1 FROM faculty_subjects fs3 INNER JOIN subjects s3 ON s3.id=fs3.subject_id WHERE fs3.faculty_id=f.id AND (s3.course IS NULL OR s3.course='' OR s3.course=?))";
    $types .= "s"; $params[] = $f_course;
}
if ($f_semester !== "") {
    $conds[] = "EXISTS (SELECT 1 FROM faculty_subjects fs4 INNER JOIN subjects s4 ON s4.id=fs4.subject_id WHERE fs4.faculty_id=f.id AND (s4.semester IS NULL OR s4.semester='' OR s4.semester=?))";
    $types .= "s"; $params[] = $f_semester;
}
$where_sql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

function admin_fetch_all($conn, $sql, $types = "", $params = array())
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
function admin_fetch_one($conn, $sql, $types = "", $params = array())
{
    $rows = admin_fetch_all($conn, $sql, $types, $params);
    return $rows ? $rows[0] : null;
}

$count_row = admin_fetch_one($conn, "SELECT COUNT(DISTINCT f.id) AS c FROM faculty f LEFT JOIN faculty_subjects fs ON f.id=fs.faculty_id LEFT JOIN subjects s ON fs.subject_id=s.id $where_sql", $types, $params);
$total_rows = $count_row ? (int)$count_row["c"] : 0;
$total_pages = max(1, (int)ceil($total_rows / $f_per));
if ($f_page > $total_pages) { $f_page = $total_pages; }
$offset = ($f_page - 1) * $f_per;

$faculty_list = admin_fetch_all($conn, "SELECT f.*,
    GROUP_CONCAT(DISTINCT CONCAT(s.subject_name, '||', IFNULL(s.course, ''), '||', IFNULL(s.semester, '')) ORDER BY s.subject_name ASC SEPARATOR ';;') AS assigned_subjects,
    GROUP_CONCAT(DISTINCT s.id ORDER BY s.id ASC SEPARATOR ',') AS assigned_subject_ids,
    COUNT(DISTINCT fs.subject_id) AS subject_count
    FROM faculty f
    LEFT JOIN faculty_subjects fs ON f.id=fs.faculty_id
    LEFT JOIN subjects s ON fs.subject_id=s.id
    $where_sql
    GROUP BY f.id ORDER BY f.id DESC LIMIT $f_per OFFSET $offset", $types, $params);

// ---- Global summary stats (real counts) ----
$st_total = admin_fetch_one($conn, "SELECT COUNT(*) AS c FROM faculty", "", array());
$stat_total = $st_total ? (int)$st_total["c"] : 0;
$st_active = admin_fetch_one($conn, "SELECT COUNT(*) AS c FROM faculty WHERE status='Active'", "", array());
$stat_active = $st_active ? (int)$st_active["c"] : 0;
$st_assigned = admin_fetch_one($conn, "SELECT COUNT(DISTINCT faculty_id) AS c FROM faculty_subjects", "", array());
$stat_assigned = $st_assigned ? (int)$st_assigned["c"] : 0;
$stat_inactive = max(0, $stat_total - $stat_active);
$stat_unassigned = max(0, $stat_total - $stat_assigned);

$f_params = array("search" => $f_search, "subject" => $f_subject > 0 ? $f_subject : "", "status" => $f_status, "course" => $f_course, "semester" => $f_semester);
$f_chips = array();
if ($f_search !== "") { $f_chips[] = array("Search: " . $f_search, "faculty.php" . admin_qs($f_params, array("search" => "", "page" => 1))); }
if ($f_subject > 0) {
    $fs_name = "Subject #" . $f_subject;
    foreach ($all_subjects as $as) { if ((int)$as["id"] === $f_subject) { $fs_name = $as["subject_name"]; break; } }
    $f_chips[] = array("Subject: " . $fs_name, "faculty.php" . admin_qs($f_params, array("subject" => "", "page" => 1)));
}
if ($f_course !== "") { $f_chips[] = array("Course: " . $f_course, "faculty.php" . admin_qs($f_params, array("course" => "", "semester" => "", "page" => 1))); }
if ($f_semester !== "") { $f_chips[] = array("Semester: " . $f_semester, "faculty.php" . admin_qs($f_params, array("semester" => "", "page" => 1))); }
if ($f_status !== "") { $f_chips[] = array("Status: " . $f_status, "faculty.php" . admin_qs($f_params, array("status" => "", "page" => 1))); }
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item active">Faculty Management</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-person-badge"></i> User Management</span>
                    <h1>Manage Faculty</h1>
                    <p>Create faculty accounts, assign subjects, and configure permissions.</p>
                </div>
                <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                    <i class="bi bi-person-plus-fill me-1"></i>Add New Faculty
                </button>
            </div>

            <?php
            admin_stat_cards(array(
                array("Total Faculty", $stat_total, "bi-person-badge-fill", "blue", "All accounts"),
                array("Active Faculty", $stat_active, "bi-person-check-fill", "green", "Can sign in"),
                array("Inactive Faculty", $stat_inactive, "bi-person-x-fill", "rose", "Access blocked"),
                array("Assigned Faculty", $stat_assigned, "bi-journal-check", "violet", "Has subjects"),
                array("Unassigned Faculty", $stat_unassigned, "bi-journal-x", "amber", "No subjects yet")
            ));
            ?>

            <!-- Search + Filters Toolbar -->
            <form method="get" class="toolbar-card mb-4 sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fSearch">Search Faculty</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input id="fSearch" name="search" value="<?php echo h($f_search); ?>" class="form-control" placeholder="Name, ID, email or subject...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fSubject">Subject</label>
                        <select id="fSubject" name="subject" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($all_subjects as $as) { ?><option value="<?php echo (int)$as["id"]; ?>" <?php if ($f_subject === (int)$as["id"]) echo "selected"; ?>><?php echo h($as["subject_name"]); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fCourse">Course</label>
                        <select id="fCourse" name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach (academic_course_options() as $co) { ?><option value="<?php echo h($co); ?>" <?php if ($f_course === $co) echo "selected"; ?>><?php echo h($co); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fSemester">Semester</label>
                        <select id="fSemester" name="semester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach (academic_semester_options() as $so) { ?><option value="<?php echo h($so); ?>" <?php if ($f_semester === $so) echo "selected"; ?>><?php echo h($so); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small fw-semibold mb-1" for="fStatus">Status</label>
                        <select id="fStatus" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="Active" <?php if ($f_status === "Active") echo "selected"; ?>>Active</option>
                            <option value="Inactive" <?php if ($f_status === "Inactive") echo "selected"; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                        <a class="btn btn-outline-secondary" href="faculty.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <?php admin_chips($f_chips, "faculty.php"); ?>
            </form>

            <!-- Faculty Table -->
            <div class="table-card p-3 sp-fade-up">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Faculty List (<?php echo $total_rows; ?>)</h5>
                    <input class="form-control dashboard-search" style="max-width: 250px;" data-table-search="#facultyTable" placeholder="Filter rows...">
                </div>

                <div class="table-responsive">
                <table class="table table-hover align-middle admin-table mb-0" id="facultyTable">
                    <thead>
                        <tr>
                            <th>Faculty</th>
                            <th>Email &amp; Contact</th>
                            <th>Assignments</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($faculty_list) > 0) {
                            foreach ($faculty_list as $f) {
                                $avatar_html = faculty_profile_avatar($f, "student-row-avatar");
                                $assign_uid = "assign" . (int)$f["id"];
                        ?>
                            <tr>
                                <td data-label="Faculty">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php echo $avatar_html; ?>
                                        <div class="admin-cell-main">
                                            <strong><?php echo h($f["name"]); ?></strong>
                                            <small class="text-muted">ID: #<?php echo (int)$f["id"]; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Email & Contact">
                                    <div><i class="bi bi-envelope me-1 text-muted"></i><?php echo h($f["email"]); ?></div>
                                    <?php if (!empty($f["phone"])) { ?>
                                        <small class="text-muted"><i class="bi bi-telephone me-1"></i><?php echo h($f["phone"]); ?></small>
                                    <?php } ?>
                                </td>
                                <td data-label="Assignments">
                                    <?php
                                    $assign_items = array();
                                    foreach ((!empty($f["assigned_subjects"]) ? explode(";;", $f["assigned_subjects"]) : array()) as $ar) {
                                        $parts = explode("||", $ar);
                                        $an = trim($parts[0] ?? "");
                                        if ($an === "") { continue; }
                                        $ac = trim($parts[1] ?? "");
                                        $as = trim($parts[2] ?? "");
                                        $assign_items[] = $an . (($ac !== "" || $as !== "") ? (" • " . trim($ac . " " . $as)) : "");
                                    }
                                    ?>
                                    <?php if (count($assign_items) > 0) { ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php $ai = 0; $extra_open = false; foreach ($assign_items as $item) { $ai++;
                                                if ($ai === 4) { $extra_open = true; echo '<span id="extra' . $assign_uid . '" class="d-none">'; }
                                            ?>
                                                <span class="admin-assign-chip"><?php echo h($item); ?></span>
                                            <?php } ?>
                                            <?php if ($extra_open) { echo '</span>'; ?>
                                                <button type="button" class="admin-assign-more" data-admin-expand="extra<?php echo $assign_uid; ?>" data-more="+<?php echo count($assign_items) - 3; ?> more" data-less="Show less">+<?php echo count($assign_items) - 3; ?> more</button>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary-soft text-secondary">No Subjects Assigned</span>
                                    <?php } ?>
                                </td>
                                <td data-label="Status"><?php echo admin_badge($f["status"]); ?></td>
                                <td data-label="Last Login">
                                    <small class="text-muted">
                                        <?php echo !empty($f["last_login"]) ? h(date("M j, Y g:i A", strtotime($f["last_login"]))) : "Never"; ?>
                                    </small>
                                </td>
                                <td class="text-end text-nowrap" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewFacultyModal<?php echo (int)$f["id"]; ?>" title="View details" aria-label="View details"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editFacultyModal<?php echo (int)$f["id"]; ?>" title="Edit Faculty" aria-label="Edit faculty"><i class="bi bi-pencil"></i></button>
                                    <a class="btn btn-sm <?php echo ($f["status"] ?? "") === "Active" ? "btn-secondary" : "btn-success"; ?>" href="faculty.php?toggle=<?php echo (int)$f["id"]; ?>" data-admin-confirm="Are you sure?|This will <?php echo ($f["status"] ?? "") === "Active" ? "deactivate" : "activate"; ?> this faculty account.|Confirm" title="<?php echo ($f["status"] ?? "") === "Active" ? "Deactivate" : "Activate"; ?>" aria-label="Toggle status"><i class="bi bi-<?php echo ($f["status"] ?? "") === "Active" ? "pause-circle" : "play-circle"; ?>"></i></a>
                                    <a class="btn btn-sm btn-danger js-confirm-delete" href="faculty.php?delete=<?php echo (int)$f["id"]; ?>" title="Delete Faculty" aria-label="Delete faculty"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php }
                        } else { ?>
                            <tr>
                                <td colspan="6">
                                    <div class="sp-empty">
                                        <div class="sp-empty-icon"><i class="bi bi-person-x"></i></div>
                                        <h4>No Faculty Found</h4>
                                        <p>Try changing your filters or add a new faculty account.</p>
                                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addFacultyModal"><i class="bi bi-plus-lg me-1"></i>Add New Faculty</button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
                <?php admin_pagination($total_rows, $f_page, $f_per, $f_params, "faculty.php"); ?>
            </div>

            <!-- View + Edit Faculty Modals (current page rows only) -->
            <?php if (count($faculty_list) > 0) {
                foreach ($faculty_list as $f) {
                    $assigned_ids = !empty($f["assigned_subject_ids"]) ? explode(",", $f["assigned_subject_ids"]) : array();
                    $view_items = array();
                    foreach ((!empty($f["assigned_subjects"]) ? explode(";;", $f["assigned_subjects"]) : array()) as $var) {
                        $vp = explode("||", $var);
                        $vn = trim($vp[0] ?? "");
                        if ($vn === "") { continue; }
                        $vc = trim($vp[1] ?? "");
                        $vs = trim($vp[2] ?? "");
                        $view_items[] = $vn . (($vc !== "" || $vs !== "") ? (" • " . trim($vc . " " . $vs)) : "");
                    }
            ?>
                <div class="modal fade" id="viewFacultyModal<?php echo (int)$f["id"]; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content professional-modal">
                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Faculty Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                            <div class="modal-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <?php echo faculty_profile_avatar($f, "student-row-avatar"); ?>
                                    <div class="min-w-0">
                                        <h5 class="mb-0"><?php echo h($f["name"]); ?></h5>
                                        <small class="text-muted">Faculty ID #<?php echo (int)$f["id"]; ?></small>
                                    </div>
                                    <span class="ms-auto"><?php echo admin_badge($f["status"]); ?></span>
                                </div>
                                <div class="admin-detail-grid">
                                    <div class="admin-detail-item"><small>Email</small><strong><?php echo h($f["email"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Phone</small><strong><?php echo h($f["phone"] ?: "-"); ?></strong></div>
                                    <div class="admin-detail-item"><small>Last Login</small><strong><?php echo !empty($f["last_login"]) ? h(date("d M Y, h:i A", strtotime($f["last_login"]))) : "Never"; ?></strong></div>
                                    <div class="admin-detail-item"><small>Joined</small><strong><?php echo !empty($f["created_at"]) ? h(date("d M Y", strtotime($f["created_at"]))) : "-"; ?></strong></div>
                                </div>
                                <h6 class="small fw-bold text-uppercase text-muted mt-3 mb-2">Assigned Subjects (<?php echo count($view_items); ?>)</h6>
                                <?php if (!empty($view_items)) { ?>
                                    <div class="d-flex flex-wrap gap-1"><?php foreach ($view_items as $vi) { ?><span class="admin-assign-chip"><?php echo h($vi); ?></span><?php } ?></div>
                                <?php } else { ?><p class="text-muted small mb-0">No subjects assigned.</p><?php } ?>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="editFacultyModal<?php echo $f["id"]; ?>" tabindex="-1" aria-labelledby="editFacultyModalLabel<?php echo $f["id"]; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <form method="post" enctype="multipart/form-data" class="modal-content professional-modal">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editFacultyModalLabel<?php echo $f["id"]; ?>"><i class="bi bi-pencil-square me-2"></i>Edit Faculty: <?php echo h($f["name"]); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="edit_id" value="<?php echo $f["id"]; ?>">
                                <div class="row g-3">
                                    <div class="col-md-6 form-floating">
                                        <input type="text" class="form-control" id="fName<?php echo $f["id"]; ?>" name="name" value="<?php echo h($f["name"]); ?>" placeholder="Full Name" required>
                                        <label for="fName<?php echo $f["id"]; ?>">Full Name *</label>
                                    </div>
                                    <div class="col-md-6 form-floating">
                                        <input type="email" class="form-control" id="fEmail<?php echo $f["id"]; ?>" name="email" value="<?php echo h($f["email"]); ?>" placeholder="Email Address" required>
                                        <label for="fEmail<?php echo $f["id"]; ?>">Email Address *</label>
                                    </div>
                                    <div class="col-md-6 form-floating">
                                        <input type="text" class="form-control" id="fPhone<?php echo $f["id"]; ?>" name="phone" value="<?php echo h($f["phone"] ?? ""); ?>" placeholder="Contact Number">
                                        <label for="fPhone<?php echo $f["id"]; ?>">Contact Number</label>
                                    </div>
                                    <div class="col-md-6 form-floating">
                                        <input type="password" class="form-control" id="fPass<?php echo $f["id"]; ?>" name="password" placeholder="New Password (Leave blank to keep current)" minlength="6">
                                        <label for="fPass<?php echo $f["id"]; ?>">New Password (Optional)</label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Account Status</label>
                                        <select class="form-select" name="status">
                                            <option value="Active" <?php echo $f["status"] == "Active" ? "selected" : ""; ?>>Active</option>
                                            <option value="Inactive" <?php echo $f["status"] == "Inactive" ? "selected" : ""; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Profile Photo (Optional, PNG/JPEG under 2MB)</label>
                                        <input type="file" name="profile_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold"><i class="bi bi-book me-1"></i>Assigned Subjects</label>
                                        <p class="text-muted small mb-2">Check the subjects this faculty member is authorized to manage:</p>
                                        <div class="row g-2 p-3 bg-light rounded-3 border" style="max-height: 200px; overflow-y: auto;">
                                            <?php if (count($all_subjects) > 0) {
                                                foreach ($all_subjects as $sub) {
                                                    $is_checked = in_array((string)$sub["id"], $assigned_ids) ? "checked" : "";
                                            ?>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $sub["id"]; ?>" id="sub_edit_<?php echo $f["id"]; ?>_<?php echo $sub["id"]; ?>" <?php echo $is_checked; ?>>
                                                        <label class="form-check-label" for="sub_edit_<?php echo $f["id"]; ?>_<?php echo $sub["id"]; ?>">
                                                            <strong><?php echo h($sub["subject_name"]); ?></strong> <small class="text-muted">(<?php echo h($sub["subject_code"]); ?>)</small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php }
                                            } else { ?>
                                                <div class="col-12 text-muted">No active subjects available. Please create subjects first.</div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-gradient">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php }
            } ?>
        </div>
    </div>
</div>

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" enctype="multipart/form-data" class="modal-content professional-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="">
                <div class="row g-3">
                    <div class="col-md-6 form-floating">
                        <input type="text" class="form-control" id="addFName" name="name" placeholder="Full Name" required>
                        <label for="addFName">Full Name *</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input type="email" class="form-control" id="addFEmail" name="email" placeholder="Email Address" required>
                        <label for="addFEmail">Email Address *</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input type="text" class="form-control" id="addFPhone" name="phone" placeholder="Contact Number">
                        <label for="addFPhone">Contact Number</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input type="password" class="form-control" id="addFPass" name="password" placeholder="Password (Min 6 chars)" minlength="6" required>
                        <label for="addFPass">Password *</label>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Account Status</label>
                        <select class="form-select" name="status">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Profile Photo (Optional)</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold"><i class="bi bi-book me-1"></i>Assigned Subjects</label>
                        <p class="text-muted small mb-2">Select one or more subjects to assign to this faculty member:</p>
                        <div class="row g-2 p-3 bg-light rounded-3 border" style="max-height: 200px; overflow-y: auto;">
                            <?php if (count($all_subjects) > 0) {
                                foreach ($all_subjects as $sub) {
                            ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $sub["id"]; ?>" id="sub_add_<?php echo $sub["id"]; ?>">
                                        <label class="form-check-label" for="sub_add_<?php echo $sub["id"]; ?>">
                                            <strong><?php echo h($sub["subject_name"]); ?></strong> <small class="text-muted">(<?php echo h($sub["subject_code"]); ?>)</small>
                                        </label>
                                    </div>
                                </div>
                            <?php }
                            } else { ?>
                                <div class="col-12 text-muted">No active subjects found. Please create subjects first.</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-gradient">Create Faculty</button>
            </div>
        </form>
    </div>
</div>

<?php
include("../app/layouts/admin_footer.php");
mysqli_close($conn);
?>

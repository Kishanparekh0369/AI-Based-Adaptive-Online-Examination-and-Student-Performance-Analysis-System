<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Manage Students";

if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    mysqli_query($conn, "DELETE FROM students WHERE id=$id");
    redirect_alert("Student deleted successfully", "students.php");
}

if (isset($_GET["toggle"])) {
    $id = (int)$_GET["toggle"];
    $stmt = mysqli_prepare($conn, "SELECT status FROM students WHERE id=? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $trow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($trow) {
            $new_status = (($trow["status"] ?? "") === "Active") ? "Inactive" : "Active";
            $ustmt = mysqli_prepare($conn, "UPDATE students SET status=? WHERE id=? LIMIT 1");
            if ($ustmt) {
                mysqli_stmt_bind_param($ustmt, "si", $new_status, $id);
                mysqli_stmt_execute($ustmt);
                mysqli_stmt_close($ustmt);
            }
            redirect_alert("Student account " . strtolower($new_status), "students.php", "success");
        }
    }
    redirect_alert("Student not found", "students.php", "error");
}

$edit_id = "";
$name = "";
$email = "";
$phone = "";
$course = "";
$semester = "";
$status = "Active";
$course_options = array("BCA", "MCA", "B.Sc IT", "M.Sc IT", "BCS IT", "Computer Engineering", "Information Technology", "Computer Science", "AI & Data Science", "Cyber Security", "Other");
$semester_options = array("Semester 1", "Semester 2", "Semester 3", "Semester 4", "Semester 5", "Semester 6", "Semester 7", "Semester 8");

function render_student_options($options, $selected = "") {
    foreach ($options as $option) {
        $is_selected = $option == $selected ? " selected" : "";
        echo '<option value="' . h($option) . '"' . $is_selected . '>' . h($option) . '</option>';
    }
}

function student_profile_avatar($student) {
    $name = $student["name"] ?? "S";
    $image = $student["profile_image"] ?? "";
    if ($image != "" && file_exists("../assets/uploads/profile/" . $image)) {
        return '<img src="../assets/uploads/profile/' . h($image) . '" class="student-row-avatar" style="object-fit:cover" alt="Profile">';
    }
    return '<span class="student-row-avatar">' . h(strtoupper(substr($name, 0, 1))) . '</span>';
}

function upload_student_profile_image($field_name) {
    if (empty($_FILES[$field_name]) || $_FILES[$field_name]["error"] === UPLOAD_ERR_NO_FILE) {
        return "";
    }
    if ($_FILES[$field_name]["error"] !== UPLOAD_ERR_OK) {
        redirect_alert("Profile image upload failed", "students.php");
    }
    $allowed = array("image/jpeg", "image/png");
    if (!in_array($_FILES[$field_name]["type"], $allowed)) {
        redirect_alert("Only JPEG and PNG files are allowed", "students.php");
    }
    if ($_FILES[$field_name]["size"] > 2 * 1024 * 1024) {
        redirect_alert("File size must be under 2MB", "students.php");
    }
    $ext = pathinfo($_FILES[$field_name]["name"], PATHINFO_EXTENSION);
    $filename = "profile_admin_" . time() . "_" . rand(100, 999) . "." . $ext;
    $filepath = "../assets/uploads/profile/" . $filename;
    if (!move_uploaded_file($_FILES[$field_name]["tmp_name"], $filepath)) {
        redirect_alert("Profile image upload failed", "students.php");
    }
    return $filename;
}

if (isset($_GET["edit"])) {
    $edit_id = $_GET["edit"];
    $result = mysqli_query($conn, "SELECT * FROM students WHERE id='$edit_id'");
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $name = $row["name"];
        $email = $row["email"];
        $phone = $row["phone"];
        $course = $row["course"];
        $semester = $row["semester"];
        $status = $row["status"];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $edit_id = clean_data($_POST["edit_id"]);
    $name = clean_data($_POST["name"]);
    $email = clean_data($_POST["email"]);
    $phone = clean_data($_POST["phone"]);
    $password = clean_data($_POST["password"]);
    $course = clean_data($_POST["course"]);
    $semester = clean_data($_POST["semester"]);
    $status = clean_data($_POST["status"]);

    if ($name == "" || $email == "" || $phone == "" || $course == "" || $semester == "") {
        redirect_alert("Required fields are missing", "students.php");
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_alert("Enter valid email", "students.php");
    } else if (!preg_match("/^[0-9]{10}$/", $phone)) {
        redirect_alert("Enter valid 10 digit phone number", "students.php");
    } else {
        $check_sql = "SELECT * FROM students WHERE email='$email' AND id!='$edit_id'";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            redirect_alert("Email already exists", "students.php");
        } else if ($edit_id != "") {
            $profile_image = upload_student_profile_image("profile_photo");
            $profile_update = "";
            if ($profile_image != "") {
                $profile_image_safe = mysqli_real_escape_string($conn, $profile_image);
                $profile_update = ", profile_image='$profile_image_safe', profile_image_updated_at=NOW()";
            }
            $sql = "UPDATE students SET name='$name', email='$email', phone='$phone', course='$course', semester='$semester', status='$status' $profile_update WHERE id='$edit_id'";
            mysqli_query($conn, $sql);
            if ($password != "") {
                mysqli_query($conn, "UPDATE students SET password='$password' WHERE id='$edit_id'");
            }
            notify_user("student", $edit_id, "Profile Updated", "Your student profile was updated by admin.", "account_settings.php");
            redirect_alert("Student updated successfully", "students.php");
        } else {
            if (strlen($password) < 6) {
                redirect_alert("Password must be at least 6 characters", "students.php");
            }
            $profile_image = upload_student_profile_image("profile_photo");
            if ($profile_image != "") {
                $profile_image_safe = mysqli_real_escape_string($conn, $profile_image);
                $sql = "INSERT INTO students (name,email,phone,password,course,semester,status,profile_image,profile_image_updated_at) VALUES ('$name','$email','$phone','$password','$course','$semester','$status','$profile_image_safe',NOW())";
            } else {
                $sql = "INSERT INTO students (name,email,phone,password,course,semester,status) VALUES ('$name','$email','$phone','$password','$course','$semester','$status')";
            }
            mysqli_query($conn, $sql);
            $new_student_id = mysqli_insert_id($conn);
            notify_user("admin", null, "New Student Added", $name . " was added by admin.", "students.php");
            notify_user("student", $new_student_id, "Welcome", "Your student account is ready.", "dashboard.php");
            redirect_alert("Student inserted successfully", "students.php");
        }
    }
}

include("../app/layouts/admin_header.php");

// ---- Filters (validated against real distinct values) ----
$course_list = array();
$cr = mysqli_query($conn, "SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course<>'' ORDER BY course ASC");
if ($cr) { while ($crow = mysqli_fetch_assoc($cr)) { $course_list[] = $crow["course"]; } }
$semester_list = array();
$sr = mysqli_query($conn, "SELECT DISTINCT semester FROM students WHERE semester IS NOT NULL AND semester<>'' ORDER BY semester ASC");
if ($sr) { while ($srow = mysqli_fetch_assoc($sr)) { $semester_list[] = $srow["semester"]; } }

$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_course = isset($_GET["course"]) ? trim((string)$_GET["course"]) : "";
if ($f_course !== "" && !in_array($f_course, $course_list, true)) { $f_course = ""; }
$f_semester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
if ($f_semester !== "" && !in_array($f_semester, $semester_list, true)) { $f_semester = ""; }
$f_status = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "";
if (!in_array($f_status, array("", "Active", "Inactive"), true)) { $f_status = ""; }
$f_page = max(1, (int)($_GET["page"] ?? 1));
$f_per = 10;

$conds = array(); $types = ""; $params = array();
if ($f_search !== "") {
    $like = "%" . $f_search . "%";
    if (ctype_digit($f_search)) {
        $conds[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR id = ?)";
        $types .= "sssi"; array_push($params, $like, $like, $like, (int)$f_search);
    } else {
        $conds[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $types .= "sss"; array_push($params, $like, $like, $like);
    }
}
if ($f_course !== "") { $conds[] = "course = ?"; $types .= "s"; $params[] = $f_course; }
if ($f_semester !== "") { $conds[] = "semester = ?"; $types .= "s"; $params[] = $f_semester; }
if ($f_status !== "") { $conds[] = "status = ?"; $types .= "s"; $params[] = $f_status; }
$where_sql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

function admin_run_list($conn, $sql, $types, $params, $single = false)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $single ? null : array(); }
    if ($types !== "" && !empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = $single ? null : array();
    if ($res) {
        if ($single) { $out = mysqli_fetch_assoc($res); }
        else { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    }
    mysqli_stmt_close($stmt);
    return $out;
}

$count_row = admin_run_list($conn, "SELECT COUNT(*) AS c FROM students $where_sql", $types, $params, true);
$total_rows = $count_row ? (int)$count_row["c"] : 0;
$total_pages = max(1, (int)ceil($total_rows / $f_per));
if ($f_page > $total_pages) { $f_page = $total_pages; }
$offset = ($f_page - 1) * $f_per;
$students_data = admin_run_list($conn, "SELECT * FROM students $where_sql ORDER BY id ASC LIMIT $f_per OFFSET $offset", $types, $params);

// ---- Global summary stats (real counts) ----
$stat_total = (int)(admin_run_list($conn, "SELECT COUNT(*) AS c FROM students", "", array(), true)["c"] ?? 0);
$stat_active = (int)(admin_run_list($conn, "SELECT COUNT(*) AS c FROM students WHERE status='Active'", "", array(), true)["c"] ?? 0);
$stat_inactive = max(0, $stat_total - $stat_active);
$stat_courses = (int)(admin_run_list($conn, "SELECT COUNT(DISTINCT course) AS c FROM students WHERE course IS NOT NULL AND course<>''", "", array(), true)["c"] ?? 0);
$stat_semesters = (int)(admin_run_list($conn, "SELECT COUNT(DISTINCT semester) AS c FROM students WHERE semester IS NOT NULL AND semester<>''", "", array(), true)["c"] ?? 0);

$f_params = array("search" => $f_search, "course" => $f_course, "semester" => $f_semester, "status" => $f_status);
$f_chips = array();
if ($f_search !== "") { $f_chips[] = array("Search: " . $f_search, "students.php" . admin_qs($f_params, array("search" => "", "page" => 1))); }
if ($f_course !== "") { $f_chips[] = array("Course: " . $f_course, "students.php" . admin_qs($f_params, array("course" => "", "page" => 1))); }
if ($f_semester !== "") { $f_chips[] = array("Semester: " . $f_semester, "students.php" . admin_qs($f_params, array("semester" => "", "page" => 1))); }
if ($f_status !== "") { $f_chips[] = array("Status: " . $f_status, "students.php" . admin_qs($f_params, array("status" => "", "page" => 1))); }
$modals = "";
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4 admin-students-page">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item active">Student Management</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-people"></i> User Management</span>
                    <h1>Manage Students</h1>
                    <p>View, create, and manage all registered student accounts.</p>
                </div>
                <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-plus-lg me-1"></i>Add Student</button>
            </div>

            <?php
            admin_stat_cards(array(
                array("Total Students", $stat_total, "bi-people-fill", "blue", "Registered accounts"),
                array("Active Students", $stat_active, "bi-person-check-fill", "green", "Can sign in"),
                array("Inactive Students", $stat_inactive, "bi-person-x-fill", "rose", "Access blocked"),
                array("Total Courses", $stat_courses, "bi-journal-bookmark-fill", "violet", "Distinct programs"),
                array("Total Semesters", $stat_semesters, "bi-layers-fill", "amber", "Distinct terms")
            ));
            ?>

            <!-- Search + Filters -->
            <form method="get" class="toolbar-card admin-student-search mb-4 sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1" for="fSearch">Search Student</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="fSearch" name="search" value="<?php echo h($f_search); ?>" class="form-control" placeholder="Name, ID or email...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fCourse">Course</label>
                        <select id="fCourse" name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($course_list as $co) { ?><option value="<?php echo h($co); ?>" <?php if ($f_course === $co) echo "selected"; ?>><?php echo h($co); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fSemester">Semester</label>
                        <select id="fSemester" name="semester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach ($semester_list as $so) { ?><option value="<?php echo h($so); ?>" <?php if ($f_semester === $so) echo "selected"; ?>><?php echo h($so); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fStatus">Status</label>
                        <select id="fStatus" name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Active" <?php if ($f_status === "Active") echo "selected"; ?>>Active</option>
                            <option value="Inactive" <?php if ($f_status === "Inactive") echo "selected"; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                        <a class="btn btn-outline-secondary" href="students.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <?php admin_chips($f_chips, "students.php"); ?>
            </form>

            <!-- Students Table -->
            <div class="table-card admin-modern-table admin-students-table p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students_data)) { foreach ($students_data as $row) { ?>
                            <tr>
                                <td data-label="Student"><div class="d-flex align-items-center gap-2"><?php echo student_profile_avatar($row); ?><div class="admin-cell-main"><strong><?php echo h($row["name"]); ?></strong></div></div></td>
                                <td data-label="Student ID"><span class="text-muted">#<?php echo (int)$row["id"]; ?></span></td>
                                <td data-label="Email"><span class="text-break"><?php echo h($row["email"]); ?></span></td>
                                <td data-label="Mobile"><?php echo h($row["phone"]); ?></td>
                                <td data-label="Course"><?php echo h($row["course"]); ?></td>
                                <td data-label="Semester"><?php echo h($row["semester"]); ?></td>
                                <td data-label="Status"><?php echo admin_badge($row["status"]); ?></td>
                                <td data-label="Registered"><small class="text-muted"><?php echo !empty($row["created_at"]) ? h(date("d M Y", strtotime($row["created_at"]))) : "-"; ?></small></td>
                                <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewStudentModal<?php echo (int)$row["id"]; ?>" title="View details" aria-label="View details"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editStudentModal<?php echo (int)$row["id"]; ?>" title="Edit" aria-label="Edit student"><i class="bi bi-pencil"></i></button>
                                    <a class="btn btn-sm <?php echo ($row["status"] ?? "") === "Active" ? "btn-secondary" : "btn-success"; ?>" href="students.php?toggle=<?php echo (int)$row["id"]; ?>" data-admin-confirm="Are you sure?|This will <?php echo ($row["status"] ?? "") === "Active" ? "deactivate" : "activate"; ?> this student account.|Confirm" title="<?php echo ($row["status"] ?? "") === "Active" ? "Deactivate" : "Activate"; ?>" aria-label="<?php echo ($row["status"] ?? "") === "Active" ? "Deactivate account" : "Activate account"; ?>"><i class="bi bi-<?php echo ($row["status"] ?? "") === "Active" ? "pause-circle" : "play-circle"; ?>"></i></a>
                                    <a class="btn btn-sm btn-danger js-confirm-delete" href="students.php?delete=<?php echo (int)$row["id"]; ?>" title="Delete" aria-label="Delete student"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } } else {
                            admin_empty_row(9, "bi-people", "No students found.", "Try changing your filters or add a new student.", '<a class="btn btn-sm btn-gradient mt-2" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-plus-lg me-1"></i>Add Student</a>');
                        } ?>
                    </tbody>
                </table>
                </div>
                <?php admin_pagination($total_rows, $f_page, $f_per, $f_params, "students.php"); ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-gradient text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="">
                <div class="row g-3">
                    <div class="col-md-4 form-floating">
                        <input id="addName" type="text" name="name" class="form-control" placeholder="Full Name" required>
                        <label for="addName">Full Name</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input id="addEmail" type="email" name="email" class="form-control" placeholder="Email Address" required>
                        <label for="addEmail">Email Address</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input id="addPhone" type="text" name="phone" class="form-control" placeholder="Phone Number" required maxlength="10">
                        <label for="addPhone">Phone Number</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input id="addPassword" type="password" name="password" class="form-control" placeholder="Password" required minlength="6">
                        <label for="addPassword">Password</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select id="addCourse" name="course" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php render_student_options($course_options); ?>
                        </select>
                        <label for="addCourse">Course</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select id="addSemester" name="semester" class="form-select" required>
                            <option value="">Select Semester</option>
                            <?php render_student_options($semester_options); ?>
                        </select>
                        <label for="addSemester">Semester</label>
                    </div>
                    <div class="col-md-4">
                        <label for="addProfilePhoto" class="form-label small fw-medium">Profile Image</label>
                        <input id="addProfilePhoto" type="file" name="profile_photo" accept="image/jpeg,image/png" class="form-control">
                    </div>
                    <div class="col-md-4 form-floating">
                        <select id="addStatus" name="status" class="form-select">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                        <label for="addStatus">Status</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-gradient" type="submit"><i class="bi bi-check-lg me-1"></i>Add Student</button>
            </div>
        </form>
    </div>
</div>

<!-- View + Edit Student Modals (current page rows only) -->
<?php
foreach ($students_data as $row) {
    $uid = (int)$row["id"];
?>
    <div class="modal fade" id="viewStudentModal<?php echo $uid; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content professional-modal">
                <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-person-circle me-2 text-primary"></i>Student Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php echo student_profile_avatar($row); ?>
                        <div class="min-w-0">
                            <h5 class="mb-0"><?php echo h($row["name"]); ?></h5>
                            <small class="text-muted">Student ID #<?php echo $uid; ?></small>
                        </div>
                        <span class="ms-auto"><?php echo admin_badge($row["status"]); ?></span>
                    </div>
                    <div class="admin-detail-grid">
                        <div class="admin-detail-item"><small>Email</small><strong><?php echo h($row["email"]); ?></strong></div>
                        <div class="admin-detail-item"><small>Mobile</small><strong><?php echo h($row["phone"]); ?></strong></div>
                        <div class="admin-detail-item"><small>Course</small><strong><?php echo h($row["course"]); ?></strong></div>
                        <div class="admin-detail-item"><small>Semester</small><strong><?php echo h($row["semester"]); ?></strong></div>
                        <div class="admin-detail-item"><small>Registered</small><strong><?php echo !empty($row["created_at"]) ? h(date("d M Y, h:i A", strtotime($row["created_at"]))) : "-"; ?></strong></div>
                        <div class="admin-detail-item"><small>Last Login</small><strong><?php echo !empty($row["last_login"]) ? h(date("d M Y, h:i A", strtotime($row["last_login"]))) : "Never"; ?></strong></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editStudentModal<?php echo $uid; ?>" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <form method="post" class="modal-content" enctype="multipart/form-data">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Student #<?php echo $uid; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_id" value="<?php echo $uid; ?>">
                    <div class="row g-3">
                        <div class="col-md-4 form-floating">
                            <input id="editName<?php echo $uid; ?>" type="text" name="name" value="<?php echo $row["name"]; ?>" class="form-control" placeholder="Full Name" required>
                            <label for="editName<?php echo $uid; ?>">Full Name</label>
                        </div>
                        <div class="col-md-4 form-floating">
                            <input id="editEmail<?php echo $uid; ?>" type="email" name="email" value="<?php echo $row["email"]; ?>" class="form-control" placeholder="Email Address" required>
                            <label for="editEmail<?php echo $uid; ?>">Email Address</label>
                        </div>
                        <div class="col-md-4 form-floating">
                            <input id="editPhone<?php echo $uid; ?>" type="text" name="phone" value="<?php echo $row["phone"]; ?>" class="form-control" placeholder="Phone Number" required maxlength="10">
                            <label for="editPhone<?php echo $uid; ?>">Phone Number</label>
                        </div>
                        <div class="col-md-4 form-floating">
                            <input id="editPassword<?php echo $uid; ?>" type="password" name="password" class="form-control" placeholder="Leave blank to keep current" minlength="6">
                            <label for="editPassword<?php echo $uid; ?>">New Password (optional)</label>
                        </div>
                        <div class="col-md-4 form-floating">
                            <select id="editCourse<?php echo $uid; ?>" name="course" class="form-select" required>
                                <option value="">Select Course</option>
                                <?php render_student_options($course_options, $row["course"]); ?>
                            </select>
                            <label for="editCourse<?php echo $uid; ?>">Course</label>
                        </div>
                        <div class="col-md-4 form-floating">
                            <select id="editSemester<?php echo $uid; ?>" name="semester" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php render_student_options($semester_options, $row["semester"]); ?>
                            </select>
                            <label for="editSemester<?php echo $uid; ?>">Semester</label>
                        </div>
                        <div class="col-md-4">
                            <label for="editProfilePhoto<?php echo $uid; ?>" class="form-label small fw-medium">Profile Image</label>
                            <div class="d-flex align-items-center gap-2">
                                <?php echo student_profile_avatar($row); ?>
                                <input id="editProfilePhoto<?php echo $uid; ?>" type="file" name="profile_photo" accept="image/jpeg,image/png" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 form-floating">
                            <select id="editStatus<?php echo $uid; ?>" name="status" class="form-select">
                                <option value="Active" <?php echo $row["status"] == "Active" ? "selected" : ""; ?>>Active</option>
                                <option value="Inactive" <?php echo $row["status"] == "Inactive" ? "selected" : ""; ?>>Inactive</option>
                            </select>
                            <label for="editStatus<?php echo $uid; ?>">Status</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-gradient" type="submit"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php include("../app/layouts/admin_footer.php");
mysqli_close($conn); ?>

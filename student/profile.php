<?php
require_once("../app/config/database.php");
$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "My Profile";

$student_id = (int)$_SESSION["student_id"];
$student = null;
$stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, course, semester, status, profile_image, profile_image_updated_at, last_login, face_verified_at, created_at FROM students WHERE id=? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { $student = mysqli_fetch_assoc($res); }
    mysqli_stmt_close($stmt);
}

$initials = $student ? strtoupper(implode("", array_map(function ($w) { return substr($w, 0, 1); }, preg_split('/\s+/', trim($student["name"]))))) : "S";
if (strlen($initials) > 2) { $initials = substr($initials, 0, 2); }
$photo_url = "";
if ($student && !empty($student["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/profile/" . $student["profile_image"])) {
    $photo_url = "../assets/uploads/profile/" . $student["profile_image"];
}

function sp_val($v, $label = "")
{
    $v = trim((string)$v);
    if ($v === "") { return '<span class="sp-info-value is-empty">Not provided</span>'; }
    return '<span class="sp-info-value">' . h($v) . '</span>';
}

include("../app/layouts/student_header.php");
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-person-badge"></i> My Account</span>
                        <h1>My Profile</h1>
                        <p>Your personal, academic and account information.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="edit_profile.php" class="sp-btn sp-btn-primary"><i class="bi bi-pencil-square"></i> Edit Profile</a>
                        <a href="change_password.php" class="sp-btn sp-btn-soft"><i class="bi bi-shield-lock"></i> Change Password</a>
                    </div>
                </div>

                <?php if ($student) { ?>
                <!-- PROFILE HERO -->
                <div class="sp-profile-hero sp-fade-up">
                    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
                        <?php if ($photo_url !== "") { ?>
                            <img src="<?php echo h($photo_url); ?>" class="sp-profile-avatar" alt="Profile photo">
                        <?php } else { ?>
                            <span class="sp-profile-initials"><?php echo h($initials); ?></span>
                        <?php } ?>
                        <div class="flex-grow-1">
                            <h2><?php echo h($student["name"]); ?></h2>
                            <p class="sp-profile-sub"><?php echo h($student["course"]); ?> &middot; <?php echo h($student["semester"]); ?></p>
                            <div class="sp-profile-pills">
                                <span class="sp-hero-pill"><i class="bi bi-person-badge"></i>Student ID: #<?php echo (int)$student["id"]; ?></span>
                                <span class="sp-hero-pill"><i class="bi bi-envelope"></i><?php echo h($student["email"]); ?></span>
                                <span class="sp-pill <?php echo strtolower($student["status"]) === "active" ? "is-completed" : "is-expired"; ?>"><i class="bi bi-circle-fill"></i><?php echo h($student["status"]); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PERSONAL INFORMATION -->
                <div class="sp-panel mb-4 sp-fade-up" data-delay="1">
                    <div class="sp-panel-head">
                        <h3><i class="bi bi-person-circle me-2 text-primary"></i>Personal Information</h3>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-person"></i>Full Name</div><?php echo sp_val($student["name"]); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-envelope"></i>Email Address</div><?php echo sp_val($student["email"]); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-telephone"></i>Mobile Number</div><?php echo sp_val($student["phone"]); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-hash"></i>Student ID</div><span class="sp-info-value">#<?php echo (int)$student["id"]; ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- ACADEMIC INFORMATION -->
                <div class="sp-panel mb-4 sp-fade-up" data-delay="2">
                    <div class="sp-panel-head">
                        <h3><i class="bi bi-book me-2 text-primary"></i>Academic Information</h3>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-mortarboard"></i>Course / Program</div><?php echo sp_val($student["course"]); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-layers"></i>Semester / Year</div><?php echo sp_val($student["semester"]); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ACCOUNT INFORMATION -->
                <div class="sp-panel sp-fade-up" data-delay="3">
                    <div class="sp-panel-head">
                        <h3><i class="bi bi-shield-check me-2 text-primary"></i>Account Information</h3>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-circle-fill"></i>Account Status</div>
                                <span class="sp-info-value"><span class="sp-pill <?php echo strtolower($student["status"]) === "active" ? "is-completed" : "is-expired"; ?>"><?php echo h($student["status"]); ?></span></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-calendar-plus"></i>Registered On</div><?php echo sp_val($student["created_at"] ? date("d M Y", strtotime($student["created_at"])) : ""); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-clock-history"></i>Last Login</div><?php echo sp_val($student["last_login"] ? date("d M Y, h:i A", strtotime($student["last_login"])) : ""); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="sp-info-item"><div class="sp-info-label"><i class="bi bi-fingerprint"></i>Face Verified</div><?php echo sp_val($student["face_verified_at"] ? date("d M Y", strtotime($student["face_verified_at"])) : ""); ?></div>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-person-x"></i></div>
                    <h4>Profile Not Available</h4>
                    <p>We could not load your profile information. Please contact the administrator.</p>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
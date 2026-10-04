<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "My Profile";

$faculty_id = (int)$_SESSION["faculty_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify_request()) {
        redirect_alert("Security validation failed. Please try again.", "profile.php", "error");
    }
    $name = clean_data($_POST["name"] ?? "");
    $phone = clean_data($_POST["phone"] ?? "");

    if ($name == "") {
        redirect_alert("Name cannot be empty.", "profile.php", "error");
    }
    if (strlen($name) < 3 || !preg_match("/^[A-Za-z][A-Za-z\s.'-]{2,60}$/", $name)) {
        redirect_alert("Please enter a valid full name (letters only, min 3 characters).", "profile.php", "error");
    }
    if ($phone !== "" && !preg_match("/^[0-9+\-\s]{4,15}$/", $phone)) {
        redirect_alert("Please enter a valid contact number.", "profile.php", "error");
    }

    $uploaded_img = upload_faculty_profile_image("profile_image", "profile.php");
    $name_s = mysqli_real_escape_string($conn, $name);
    $phone_s = mysqli_real_escape_string($conn, $phone);

    $sql_parts = array("name='$name_s'", "phone='$phone_s'");
    if ($uploaded_img != "") {
        // remove old image if any
        $old_img_res = mysqli_query($conn, "SELECT profile_image FROM faculty WHERE id=$faculty_id");
        if ($old_img_res && $old_img_row = mysqli_fetch_assoc($old_img_res)) {
            if (!empty($old_img_row['profile_image']) && file_exists(__DIR__ . "/../assets/uploads/faculty/" . $old_img_row['profile_image'])) {
                @unlink(__DIR__ . "/../assets/uploads/faculty/" . $old_img_row['profile_image']);
            }
        }
        $sql_parts[] = "profile_image='" . mysqli_real_escape_string($conn, $uploaded_img) . "'";
    }

    if (mysqli_query($conn, "UPDATE faculty SET " . implode(", ", $sql_parts) . " WHERE id=$faculty_id")) {
        $_SESSION["faculty_name"] = $name;
        redirect_alert("Profile updated successfully!", "profile.php", "success");
    } else {
        redirect_alert("We could not update your profile right now. Please try again later.", "profile.php", "error");
    }
}

// Fetch fresh faculty data
$faculty = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM faculty WHERE id=$faculty_id"));
$assigned_subjects = get_faculty_assigned_subjects($faculty_id);

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-person-badge"></i> My Account</span>
                    <h1>My Profile</h1>
                    <p>View your account details and keep your profile information up to date.</p>
                </div>
                <a href="change_password.php" class="sp-btn sp-btn-soft"><i class="bi bi-shield-lock"></i> Change Password</a>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 sp-fade-up">
                    <!-- Profile Card -->
                    <div class="sp-panel text-center h-100">
                        <div class="mb-3 d-flex justify-content-center">
                            <?php if (!empty($faculty["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/faculty/" . $faculty["profile_image"])) { ?>
                                <img src="../assets/uploads/faculty/<?php echo h($faculty["profile_image"]); ?>" class="fp-avatar-ring" alt="Profile photo">
                            <?php } else { ?>
                                <span class="fp-avatar-fallback" aria-hidden="true"><?php echo strtoupper(substr($faculty["name"], 0, 1)); ?></span>
                            <?php } ?>
                        </div>

                        <h4 class="fw-bold mb-1"><?php echo h($faculty["name"]); ?></h4>
                        <span class="sp-pill is-available mb-2"><i class="bi bi-person-workspace"></i>Faculty Member</span>
                        <p class="text-muted small mb-3"><i class="bi bi-envelope me-1"></i><?php echo h($faculty["email"]); ?></p>

                        <hr>

                        <div class="text-start">
                            <h6 class="fw-bold mb-2"><i class="bi bi-book me-2 text-primary"></i>Assigned Subjects (<?php echo count($assigned_subjects); ?>)</h6>
                            <?php if (count($assigned_subjects) > 0) { ?>
                                <div class="sp-chip-list mb-3">
                                    <?php foreach ($assigned_subjects as $sub) { ?>
                                        <span class="sp-chip is-neutral"><i class="bi bi-bookmark"></i><?php echo h($sub["subject_name"]); ?></span>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted small">No subjects assigned.</p>
                            <?php } ?>

                            <div class="small text-muted d-grid gap-1">
                                <div><i class="bi bi-check-circle me-1 text-success"></i>Status: <strong><?php echo h($faculty["status"]); ?></strong></div>
                                <div><i class="bi bi-clock-history me-1"></i>Last login: <?php echo !empty($faculty["last_login"]) ? date("M j, Y g:i A", strtotime($faculty["last_login"])) : "Never"; ?></div>
                                <div><i class="bi bi-calendar-event me-1"></i>Joined: <?php echo date("M j, Y", strtotime($faculty["created_at"])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 sp-fade-up" data-delay="1">
                    <!-- Edit Profile Form -->
                    <div class="sp-panel h-100">
                        <div class="sp-panel-head">
                            <h3><i class="bi bi-person-gear me-2 text-primary"></i>Edit Profile Information</h3>
                        </div>
                        <form method="post" enctype="multipart/form-data" data-fp-loading="Saving Changes...">
                            <?php echo csrf_field(); ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="fp-label" for="facultyName">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                                        <input type="text" class="form-control" id="facultyName" name="name" value="<?php echo h($faculty["name"]); ?>" placeholder="Enter your full name" required autocomplete="name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="fp-label" for="facultyEmail">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" id="facultyEmail" value="<?php echo h($faculty["email"]); ?>" disabled readonly title="Managed by Admin">
                                    </div>
                                    <small class="fp-hint"><i class="bi bi-lock me-1"></i>Managed by Admin — contact admin to change.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="fp-label" for="facultyPhone">Contact Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone text-muted"></i></span>
                                        <input type="tel" class="form-control" id="facultyPhone" name="phone" value="<?php echo h($faculty["phone"] ?? ""); ?>" placeholder="e.g. 9876543210" autocomplete="tel">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="fp-label" for="facultyStatus">Account Status</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-check-circle text-muted"></i></span>
                                        <input type="text" class="form-control" id="facultyStatus" value="<?php echo h($faculty["status"]); ?>" disabled readonly>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="fp-label" for="fpFacultyPhoto">Profile Photo <span class="text-muted fw-normal">(optional)</span></label>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <img id="fpFacultyPhotoImg" class="rounded-circle border" style="width:56px;height:56px;object-fit:cover;<?php echo (!empty($faculty["profile_image"]) ? "" : "display:none;"); ?>" src="<?php echo !empty($faculty["profile_image"]) ? "../assets/uploads/faculty/" . h($faculty["profile_image"]) : ""; ?>" alt="">
                                        <input type="file" id="fpFacultyPhoto" name="profile_image" class="form-control" style="max-width:320px;" accept="image/jpeg,image/png,image/webp" data-fp-photo-preview="fpFacultyPhotoImg">
                                    </div>
                                    <small class="fp-hint">JPEG, PNG or WebP — max 2MB. Choosing a file shows a live preview.</small>
                                </div>
                            </div>

                            <div class="fp-submit-bar">
                                <button type="submit" class="sp-btn sp-btn-primary">
                                    <i class="bi bi-check-lg"></i>Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>

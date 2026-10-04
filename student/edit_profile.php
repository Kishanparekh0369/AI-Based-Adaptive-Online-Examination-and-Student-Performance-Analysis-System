<?php
require_once("../app/config/database.php");
$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Edit Profile";

$student_id = (int)$_SESSION["student_id"];
$student = null;
$stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, course, semester, status, profile_image FROM students WHERE id=? LIMIT 1");
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

include("../app/layouts/student_header.php");
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-pencil-square"></i> My Account</span>
                        <h1>Edit Profile</h1>
                        <p>Update your personal information and profile photo.</p>
                    </div>
                    <a href="profile.php" class="sp-btn sp-btn-ghost"><i class="bi bi-arrow-left"></i> Back to Profile</a>
                </div>

                <?php if ($student) { ?>
                <div class="row g-4">
                    <div class="col-lg-4 sp-fade-up">
                        <div class="sp-panel text-center">
                            <div class="mx-auto mb-3" style="max-width:160px;">
                                <div id="spAvatarDrop" class="sp-avatar-drop" role="button" tabindex="0" aria-label="Change profile photo" title="Click or drop an image here">
                                    <div id="spEditAvatarPreview">
                                        <?php if ($photo_url !== "") { ?>
                                            <img src="<?php echo h($photo_url); ?>" class="sp-profile-avatar mx-auto" alt="Profile photo" style="width:110px;height:110px;border-radius:24px;">
                                        <?php } else { ?>
                                            <span class="sp-profile-initials mx-auto" style="width:110px;height:110px;font-size:2.2rem;"><?php echo h($initials); ?></span>
                                        <?php } ?>
                                    </div>
                                    <span class="sp-avatar-overlay"><i class="bi bi-camera-fill"></i><small>Change Photo</small></span>
                                </div>
                            </div>
                            <h5 class="fw-bold mb-1"><?php echo h($student["name"]); ?></h5>
                            <p class="text-muted small mb-3"><?php echo h($student["course"]); ?> &middot; <?php echo h($student["semester"]); ?></p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap mb-2">
                                <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" id="spUploadPhotoBtn"><i class="bi bi-cloud-upload"></i> Upload Photo</button>
                                <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm d-none" id="spResetPhotoBtn"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                            </div>
                            <p class="sp-file-info small mb-1 d-none" id="spPhotoFileInfo"><i class="bi bi-file-earmark-image me-1"></i><span></span></p>
                            <p class="sp-field-error" id="spEditPhotoErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                            <p class="text-muted small mt-2 mb-0">JPEG or PNG, max 2MB — you can also drag &amp; drop an image onto the photo</p>
                        </div>
                    </div>

                    <div class="col-lg-8 sp-fade-up" data-delay="1">
                        <div class="sp-panel">
                            <form id="spEditProfileForm" enctype="multipart/form-data" novalidate data-ajax="true">
                                <input type="file" id="spEditPhoto" name="profile_photo" accept="image/jpeg,image/png" class="d-none">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1" for="spEditName">Full Name <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                                            <input type="text" class="form-control" id="spEditName" name="name" value="<?php echo h($student["name"]); ?>" required autocomplete="name" aria-describedby="spEditNameErr">
                                        </div>
                                        <p class="sp-field-error" id="spEditNameErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1" for="spEditPhone">Mobile Number <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                                            <input type="tel" class="form-control" id="spEditPhone" name="phone" value="<?php echo h($student["phone"]); ?>" maxlength="10" inputmode="numeric" required autocomplete="tel" aria-describedby="spEditPhoneErr">
                                        </div>
                                        <p class="sp-field-error" id="spEditPhoneErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                    </div>
                                </div>

                                <hr class="my-4">
                                <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-lock me-1"></i>Read-Only Information</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="bg-light rounded-3 px-3 py-2 border"><small class="text-muted text-uppercase d-block" style="font-size:.65rem;letter-spacing:.5px">Email</small><span class="fw-medium small"><i class="bi bi-envelope text-primary me-1"></i><?php echo h($student["email"]); ?></span></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light rounded-3 px-3 py-2 border"><small class="text-muted text-uppercase d-block" style="font-size:.65rem;letter-spacing:.5px">Course</small><span class="fw-medium small"><i class="bi bi-book text-warning me-1"></i><?php echo h($student["course"]); ?></span></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light rounded-3 px-3 py-2 border"><small class="text-muted text-uppercase d-block" style="font-size:.65rem;letter-spacing:.5px">Semester</small><span class="fw-medium small"><i class="bi bi-layers text-info me-1"></i><?php echo h($student["semester"]); ?></span></div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="sp-btn sp-btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
                                    <a href="profile.php" class="sp-btn sp-btn-ghost ms-2">Cancel</a>
                                </div>
                            </form>
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
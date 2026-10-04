<?php
require_once("../app/config/database.php");
$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Change Password";

$student_id = (int)$_SESSION["student_id"];
$student = null;
$stmt = mysqli_prepare($conn, "SELECT name, email FROM students WHERE id=? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { $student = mysqli_fetch_assoc($res); }
    mysqli_stmt_close($stmt);
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
                        <span class="sp-eyebrow"><i class="bi bi-shield-lock"></i> Security</span>
                        <h1>Change Password</h1>
                        <p>Update your password to keep your account secure.</p>
                    </div>
                    <a href="profile.php" class="sp-btn sp-btn-ghost"><i class="bi bi-arrow-left"></i> Back to Profile</a>
                </div>

                <div class="row g-4">
                    <div class="col-lg-4 sp-fade-up">
                        <div class="sp-panel">
                            <div class="sp-panel-head"><h3><i class="bi bi-shield-check me-2 text-success"></i>Password Tips</h3></div>
                            <ul class="mb-0 small text-muted" style="line-height:1.9;">
                                <li><i class="bi bi-check2 text-success me-1"></i>At least 8 characters</li>
                                <li><i class="bi bi-check2 text-success me-1"></i>Mix of uppercase &amp; lowercase</li>
                                <li><i class="bi bi-check2 text-success me-1"></i>Include numbers &amp; symbols</li>
                                <li><i class="bi bi-exclamation-triangle text-warning me-1"></i>Don't reuse your current password</li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-8 sp-fade-up" data-delay="1">
                        <div class="sp-panel">
                            <form id="spChangePasswordForm" novalidate data-ajax="true">
                                <input type="text" name="username" class="visually-hidden" value="<?php echo h($student["email"] ?? ""); ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold mb-1" for="spCurrentPw">Current Password <span class="text-danger">*</span></label>
                                    <div class="input-group sp-pw-wrap">
                                        <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                                        <input type="password" class="form-control" id="spCurrentPw" name="current_password" autocomplete="current-password" required aria-describedby="spCurrentPwErr">
                                        <button type="button" class="btn btn-outline-secondary" data-sp-pw-toggle aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <p class="sp-field-error" id="spCurrentPwErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold mb-1" for="spNewPw">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group sp-pw-wrap">
                                        <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                                        <input type="password" class="form-control" id="spNewPw" name="new_password" autocomplete="new-password" required minlength="8" aria-describedby="spNewPwErr">
                                        <button type="button" class="btn btn-outline-secondary" data-sp-pw-toggle aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="sp-strength" data-sp-strength-for="spNewPw" aria-live="polite">
                                        <div class="sp-strength-track"><div class="sp-strength-bar"></div></div>
                                        <div class="sp-strength-meta"><span>Password Strength</span><span class="sp-strength-label">Weak</span></div>
                                    </div>
                                    <p class="sp-field-error" id="spNewPwErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold mb-1" for="spConfirmPw">Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="input-group sp-pw-wrap">
                                        <span class="input-group-text bg-white"><i class="bi bi-check-circle text-muted"></i></span>
                                        <input type="password" class="form-control" id="spConfirmPw" name="confirm_password" autocomplete="new-password" required minlength="8" aria-describedby="spConfirmPwErr">
                                        <button type="button" class="btn btn-outline-secondary" data-sp-pw-toggle aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <p class="sp-field-error" id="spConfirmPwErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                </div>

                                <button type="submit" class="sp-btn sp-btn-primary"><i class="bi bi-arrow-repeat"></i> Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
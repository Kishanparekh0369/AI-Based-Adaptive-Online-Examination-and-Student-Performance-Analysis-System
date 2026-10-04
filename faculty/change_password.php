<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Change Password";

$faculty_id = (int)$_SESSION["faculty_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = $_POST["old_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($old_password == "" || $new_password == "" || $confirm_password == "") {
        redirect_alert("All password fields are required.", "change_password.php", "error");
    } else if (strlen($new_password) < 8) {
        redirect_alert("New password must be at least 8 characters long.", "change_password.php", "error");
    } else if ($new_password !== $confirm_password) {
        redirect_alert("New password and confirmation do not match.", "change_password.php", "error");
    } else {
        $stmt = mysqli_prepare($conn, "SELECT password FROM faculty WHERE id=? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $faculty_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
        } else {
            $row = null;
        }
        if ($row) {
            if (faculty_login_password_matches($old_password, $row["password"])) {
                if (faculty_login_password_matches($new_password, $row["password"])) {
                    redirect_alert("New password must be different from your current password.", "change_password.php", "error");
                }
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $upd = mysqli_prepare($conn, "UPDATE faculty SET password=? WHERE id=?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, "si", $new_hash, $faculty_id);
                    $ok = mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                } else {
                    $ok = false;
                }
                if (!empty($ok)) {
                    redirect_alert("Your password has been updated securely.", "profile.php", "success");
                } else {
                    redirect_alert("We could not update your password right now. Please try again later.", "change_password.php", "error");
                }
            } else {
                redirect_alert("Your current password is incorrect.", "change_password.php", "error");
            }
        } else {
            redirect_alert("We could not verify your account. Please try again later.", "change_password.php", "error");
        }
    }
}

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item"><a href="profile.php">Profile</a></li>
                    <li class="breadcrumb-item active">Change Password</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-shield-lock"></i> Security</span>
                    <h1>Change Password</h1>
                    <p>Update your password to keep your faculty account secure.</p>
                </div>
                <a href="profile.php" class="sp-btn sp-btn-ghost"><i class="bi bi-arrow-left"></i> Back to Profile</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 sp-fade-up">
                    <div class="sp-panel h-100">
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
                    <div class="sp-panel h-100">
                        <div class="sp-panel-head"><h3><i class="bi bi-key me-2 text-primary"></i>Change Account Password</h3></div>
                        <form method="post" data-fp-loading="Updating Password...">
                            <div class="mb-3">
                                <label class="fp-label" for="fpCurrentPw">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group fp-pw-wrap">
                                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                                    <input type="password" class="form-control" id="fpCurrentPw" name="old_password" placeholder="Enter your current password" autocomplete="current-password" required>
                                    <button type="button" class="fp-pw-toggle" aria-controls="fpCurrentPw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fp-label" for="fpNewPw">New Password <span class="text-danger">*</span></label>
                                <div class="input-group fp-pw-wrap">
                                    <span class="input-group-text"><i class="bi bi-key text-muted"></i></span>
                                    <input type="password" class="form-control" id="fpNewPw" name="new_password" placeholder="Min 8 characters" autocomplete="new-password" required minlength="8">
                                    <button type="button" class="fp-pw-toggle" aria-controls="fpNewPw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                </div>
                                <div class="fp-strength" data-fp-strength-for="fpNewPw" aria-live="polite">
                                    <div class="fp-strength-track"><div class="fp-strength-bar"></div></div>
                                    <div class="fp-strength-meta"><span>Password Strength</span><span class="fp-strength-label">Weak</span></div>
                                </div>
                                <ul class="fp-checklist" data-fp-checklist-for="fpNewPw" aria-live="polite">
                                    <li data-check="length"><i class="bi bi-check-circle-fill"></i>At least 8 characters</li>
                                    <li data-check="cases"><i class="bi bi-check-circle-fill"></i>Uppercase &amp; lowercase letters</li>
                                    <li data-check="number"><i class="bi bi-check-circle-fill"></i>At least one number</li>
                                    <li data-check="special"><i class="bi bi-check-circle-fill"></i>At least one special character</li>
                                </ul>
                            </div>
                            <div class="mb-4">
                                <label class="fp-label" for="fpConfirmPw">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group fp-pw-wrap">
                                    <span class="input-group-text"><i class="bi bi-check-circle text-muted"></i></span>
                                    <input type="password" class="form-control" id="fpConfirmPw" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password" required minlength="8" data-fp-match="fpNewPw" data-fp-match-error="fpConfirmPwErr">
                                    <button type="button" class="fp-pw-toggle" aria-controls="fpConfirmPw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                </div>
                                <p class="fp-field-error" id="fpConfirmPwErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                            </div>

                            <div class="fp-submit-bar">
                                <button type="submit" class="sp-btn sp-btn-primary"><i class="bi bi-arrow-repeat"></i>Update Password</button>
                                <a href="profile.php" class="sp-btn sp-btn-ghost">Cancel</a>
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

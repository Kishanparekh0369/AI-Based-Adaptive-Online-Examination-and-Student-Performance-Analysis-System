<?php
require_once("../app/config/database.php");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Reset Password — AI Exam System";

$token = isset($_GET["token"]) ? trim((string)$_GET["token"]) : "";
$form_errors = array();
$server_alert = null;
$valid_token = false;
$reset_data = null;

if ($token === "") {
    $server_alert = array(
        "type" => "danger",
        "message" => "Invalid or missing reset token."
    );
} else {
    // Validate token
    $stmt = mysqli_prepare($conn,
        "SELECT pr.*, 
         CASE pr.user_role 
           WHEN 'admin' THEN (SELECT name FROM admin WHERE id=pr.user_id)
           WHEN 'faculty' THEN (SELECT name FROM faculty WHERE id=pr.user_id)
           WHEN 'students' THEN (SELECT name FROM students WHERE id=pr.user_id)
         END as user_name
         FROM password_resets pr
         WHERE pr.token=? AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reset_data = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }

    if ($reset_data) {
        $valid_token = true;
    } else {
        $server_alert = array(
            "type" => "danger",
            "message" => "This reset token is invalid, expired, or has already been used."
        );
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $valid_token) {
    if (!csrf_verify_request()) {
        $server_alert = array(
            "type" => "danger",
            "message" => "Security validation failed. Please try again."
        );
    } else {
        $password = isset($_POST["password"]) ? (string)$_POST["password"] : "";
        $confirm = isset($_POST["confirm_password"]) ? (string)$_POST["confirm_password"] : "";

        if ($password === "" || $confirm === "") {
            if ($password === "") { $form_errors["password"] = "Please enter a new password."; }
            if ($confirm === "") { $form_errors["confirm_password"] = "Please confirm your new password."; }
        } else if (strlen($password) < 8) {
            $form_errors["password"] = "Password must be at least 8 characters.";
        } else if ($confirm !== $password) {
            $form_errors["confirm_password"] = "Passwords do not match.";
        }

        if (!$form_errors) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $table_map = array("admin" => "admin", "faculty" => "faculty", "students" => "students");
            $table = $table_map[$reset_data["user_role"]] ?? "students";
            $id_col = $table === "admin" ? "id" : ($table === "faculty" ? "id" : "id");

            $stmt = mysqli_prepare($conn, "UPDATE {$table} SET password=? WHERE {$id_col}=? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $password_hash, (int)$reset_data["user_id"]);
                $updated = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($updated) {
                    // Mark token as used
                    mysqli_query($conn, "UPDATE password_resets SET used=1 WHERE token='".mysqli_real_escape_string($conn, $token)."'");

                    // Log the activity
                    $role = $reset_data["user_role"];
                    if ($role === "admin") {
                        notify_user("admin", $reset_data["user_id"], "Password Reset", "Password was reset via token.", "dashboard.php");
                    } else if ($role === "faculty") {
                        notify_user("faculty", $reset_data["user_id"], "Password Reset", "Password was reset via token.", "dashboard.php");
                    } else {
                        notify_user("student", $reset_data["user_id"], "Password Reset", "Password was reset via token.", "dashboard.php");
                    }

                    $server_alert = array(
                        "type" => "success",
                        "message" => "Your password has been reset successfully. You can now sign in with your new password."
                    );
                    $valid_token = false; // Hide form after success
                } else {
                    $server_alert = array(
                        "type" => "danger",
                        "message" => "Failed to update password. Please try again."
                    );
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo h($asset_path); ?>images/favicon.svg?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo h($asset_path); ?>images/favicon-32x32.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo h($asset_path); ?>images/favicon-16x16.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo h($asset_path); ?>images/apple-touch-icon.png?v=1">
    <meta name="theme-color" content="#2563EB">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo h($asset_path); ?>css/style.css" rel="stylesheet">
    <link href="<?php echo h($asset_path); ?>css/auth.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="auth-page">
    <main class="auth-shell">
        <div class="auth-grid">

            <!-- LEFT: Branding -->
            <aside class="auth-brand" aria-label="System overview">
                <span class="auth-logo" aria-hidden="true"><i class="bi bi-lock-fill"></i></span>
                <span class="auth-kicker">Set New Password</span>
                <h1>Create Your <span class="accent">New Password</span></h1>
                <p class="brand-lead">Enter a strong password below. Your password must be at least 8 characters.</p>
                <ul class="auth-features">
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Min 8 characters</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Strength indicator</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Secure bcrypt hashing</span></li>
                </ul>
                <div class="auth-brand-foot">
                    <span class="dot" aria-hidden="true"></span>
                    <span>Token valid for 1 hour · Single use only</span>
                </div>
            </aside>

            <!-- RIGHT: Reset card -->
            <section class="auth-card" aria-labelledby="resetHeading">
                <header class="auth-card-head">
                    <span class="auth-card-mark" aria-hidden="true"><i class="bi bi-key-fill"></i></span>
                    <h2 id="resetHeading">Set New Password</h2>
                    <p><?php echo $valid_token ? "Enter your new password and confirm it" : "This token is invalid or expired"; ?></p>
                </header>

                <?php if ($server_alert) { ?>
                    <div class="auth-alert auth-alert-<?php echo h($server_alert["type"]); ?>" role="alert">
                        <i class="bi bi-<?php echo $server_alert["type"] === "danger" ? "exclamation-triangle-fill" : ($server_alert["type"] === "success" ? "check-circle-fill" : "info-circle-fill"); ?>" aria-hidden="true"></i>
                        <span><?php echo h($server_alert["message"]); ?></span>
                    </div>
                <?php } ?>

                <?php if ($valid_token) { ?>
                <form method="post" id="authResetForm" novalidate autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">

                    <!-- New Password -->
                    <div class="auth-field <?php echo isset($form_errors["password"]) ? "is-invalid" : ""; ?>">
                        <label for="resetPassword">New Password</label>
                        <div class="auth-input-wrap">
                            <input type="password" id="resetPassword" name="password" data-validate="password" data-mode="new"
                                   class="pw-input" placeholder="Min 8 characters"
                                   autocomplete="new-password" required minlength="8"
                                   aria-describedby="resetPasswordError">
                            <i class="bi bi-lock lead-icon" aria-hidden="true"></i>
                            <button type="button" class="pw-toggle" id="resetPwToggle"
                                    aria-controls="resetPassword" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="pw-strength" data-strength-for="resetPassword" aria-live="polite">
                            <div class="pw-strength-track"><div class="pw-strength-bar"></div></div>
                            <div class="pw-strength-meta">
                                <span>Password Strength</span>
                                <span class="pw-strength-label">Weak</span>
                            </div>
                        </div>
                        <p class="auth-field-error" id="resetPasswordError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["password"]) ? h($form_errors["password"]) : ""; ?></span>
                        </p>
                    </div>

                    <!-- Confirm Password -->
                    <div class="auth-field <?php echo isset($form_errors["confirm_password"]) ? "is-invalid" : ""; ?>">
                        <label for="resetConfirmPassword">Confirm Password</label>
                        <div class="auth-input-wrap">
                            <input type="password" id="resetConfirmPassword" name="confirm_password"
                                   data-match="resetPassword"
                                   class="pw-input" placeholder="Re-enter new password"
                                   autocomplete="new-password" required minlength="8"
                                   aria-describedby="resetConfirmError">
                            <i class="bi bi-lock-fill lead-icon" aria-hidden="true"></i>
                            <button type="button" class="pw-toggle" id="resetConfirmPwToggle"
                                    aria-controls="resetConfirmPassword" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="auth-field-error" id="resetConfirmError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["confirm_password"]) ? h($form_errors["confirm_password"]) : ""; ?></span>
                        </p>
                    </div>

                    <button type="submit" class="auth-btn mt-2">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                        <span>Update Password</span>
                    </button>
                </form>

                <div class="auth-divider">Already know your password?</div>
                <a class="auth-btn-outline" href="<?php echo h($base_path); ?>public/login.php">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    <span>Go to Login</span>
                </a>

                <?php } else if (!$server_alert) { ?>
                <div class="auth-divider">Invalid token</div>
                <a class="auth-btn-outline" href="<?php echo h($base_path); ?>public/forgot_password.php">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    <span>Request New Token</span>
                </a>
                <?php } ?>

                <p class="auth-switch mt-3 mb-0">
                    <a href="<?php echo h($base_path); ?>index.php"><i class="bi bi-arrow-left me-1"></i>Back to Home</a>
                </p>

                <footer class="auth-card-foot">
                    AI-Based Adaptive Online Examination<br>
                    &copy; <?php echo date("Y"); ?> All Rights Reserved
                </footer>
            </section>
        </div>
    </main>

    <footer class="auth-page-footer">
        Secure password reset &middot; Token-based &middot; bcrypt hashing
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo h($asset_path); ?>js/auth.js?v=<?php echo filemtime(__DIR__ . "/../assets/js/auth.js"); ?>"></script>
<?php render_flash_alert(); ?>
</body>
</html>
<?php mysqli_close($conn); ?>
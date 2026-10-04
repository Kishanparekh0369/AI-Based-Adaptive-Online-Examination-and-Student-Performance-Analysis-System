<?php
require_once("../app/config/database.php");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Forgot Password — AI Exam System";

$form_values = array("email" => "");
$form_errors = array();
$server_alert = null;
$step = 1; // 1 = email, 2 = show token
$reset_token = null;

function generate_reset_token()
{
    return bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify_request()) {
        $server_alert = array(
            "type" => "danger",
            "message" => "Security validation failed. Please try again."
        );
    } else {
        $email = isset($_POST["email"]) ? trim((string)$_POST["email"]) : "";
        $form_values["email"] = $email;

        if ($email === "") {
            $form_errors["email"] = "Please enter your email address.";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors["email"] = "Please enter a valid email address.";
        } else {
            // Check if email exists in any role
            $user_role = "";
            $user_id = 0;
            $user_name = "";

            foreach (array("admin" => "admin", "faculty" => "faculty", "students" => "students") as $table => $role) {
                $stmt = mysqli_prepare($conn, "SELECT id, name, email FROM {$table} WHERE email=? LIMIT 1");
                if (!$stmt) continue;
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);

                if ($row) {
                    $user_role = $role;
                    $user_id = (int)$row["id"];
                    $user_name = $row["name"];
                    break;
                }
            }

            if ($user_id === 0) {
                // Don't reveal if email exists - generic message
                $server_alert = array(
                    "type" => "info",
                    "message" => "If this email is registered, a password reset token will be generated."
                );
            } else {
                // Generate reset token
                $reset_token = generate_reset_token();
                $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry

                $stmt = mysqli_prepare($conn,
                    "INSERT INTO password_resets (user_id, user_role, token, expires_at) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE token=VALUES(token), expires_at=VALUES(expires_at), used=0, created_at=NOW()"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "isss", $user_id, $user_role, $reset_token, $expiry);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }

                $step = 2;
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
                <span class="auth-logo" aria-hidden="true"><i class="bi bi-key-fill"></i></span>
                <span class="auth-kicker">Password Recovery</span>
                <h1>Reset Your <span class="accent">Password</span> Securely</h1>
                <p class="brand-lead">Enter your registered email address. If the email exists in our system, a secure reset token will be generated for you.</p>
                <ul class="auth-features">
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Secure token-based reset</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Token expires in 1 hour</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Works for all roles</span></li>
                </ul>
                <div class="auth-brand-foot">
                    <span class="dot" aria-hidden="true"></span>
                    <span>Token displayed on screen (demo mode — no SMTP)</span>
                </div>
            </aside>

            <!-- RIGHT: Reset card -->
            <section class="auth-card" aria-labelledby="resetHeading">
                <header class="auth-card-head">
                    <span class="auth-card-mark" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
                    <h2 id="resetHeading"><?php echo $step === 1 ? "Forgot Password" : "Your Reset Token"; ?></h2>
                    <p><?php echo $step === 1 ? "Enter your email to receive a reset token" : "Save this token and use it to set a new password"; ?></p>
                </header>

                <?php if ($server_alert) { ?>
                    <div class="auth-alert auth-alert-<?php echo h($server_alert["type"]); ?>" role="alert">
                        <i class="bi bi-<?php echo $server_alert["type"] === "danger" ? "exclamation-triangle-fill" : "info-circle-fill"; ?>" aria-hidden="true"></i>
                        <span><?php echo h($server_alert["message"]); ?></span>
                    </div>
                <?php } ?>

                <?php if ($step === 1) { ?>
                <!-- Step 1: Email input -->
                <form method="post" id="authForgotForm" novalidate autocomplete="on">
                    <?php echo csrf_field(); ?>

                    <div class="auth-field <?php echo isset($form_errors["email"]) ? "is-invalid" : ""; ?>" data-field="email">
                        <label for="forgotEmail">Email Address</label>
                        <div class="auth-input-wrap">
                            <input type="email" id="forgotEmail" name="email" data-validate="email"
                                   value="<?php echo h($form_values["email"]); ?>"
                                   placeholder="name@example.com"
                                   autocomplete="email" required
                                   aria-describedby="forgotEmailError">
                            <i class="bi bi-envelope lead-icon" aria-hidden="true"></i>
                            <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                            <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                        </div>
                        <p class="auth-field-error" id="forgotEmailError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["email"]) ? h($form_errors["email"]) : ""; ?></span>
                        </p>
                    </div>

                    <button type="submit" class="auth-btn">
                        <i class="bi bi-key me-1" aria-hidden="true"></i>
                        <span>Generate Reset Token</span>
                    </button>
                </form>

                <div class="auth-divider">Remember your password?</div>
                <a class="auth-btn-outline" href="<?php echo h($base_path); ?>public/login.php">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    <span>Back to Login</span>
                </a>

                <?php } else { ?>
                <!-- Step 2: Show token -->
                <div class="token-display" style="text-align:center; padding: 8px 0 4px;">
                    <div class="alert alert-info mb-4" style="border-radius: 12px; border: 1px solid #BAE6FD; background: #EFF6FF; color: #1E40AF;">
                        <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>
                        <strong>Demo Mode:</strong> No SMTP configured. Your reset token is displayed below.
                    </div>

                    <div style="background: #0F172A; border-radius: 12px; padding: 24px; margin: 16px 0; text-align: center; border: 1px solid rgba(37,99,235,0.2);">
                        <div style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #38BDF8; margin-bottom: 10px;">Your Reset Token</div>
                        <code id="resetTokenValue" style="font-family: 'SF Mono', 'Monaco', 'Consolas', monospace; font-size: 1.1rem; letter-spacing: 0.5px; color: #60A5FA; background: transparent; padding: 0; word-break: break-all;"><?php echo h($reset_token); ?></code>
                    </div>

                    <div class="d-flex gap-2 justify-content-center flex-wrap mb-3">
                        <button type="button" class="btn btn-outline-primary" onclick="copyToken()" style="min-width: 140px;">
                            <i class="bi bi-clipboard me-1"></i> Copy Token
                        </button>
                        <a class="btn btn-primary" href="<?php echo h($base_path); ?>public/reset_password.php?token=<?php echo h($reset_token); ?>">
                            <i class="bi bi-arrow-right me-1"></i> Use This Token
                        </a>
                    </div>

                    <div style="font-size: 0.82rem; color: var(--auth-muted); line-height: 1.6; max-width: 380px; margin: 0 auto;">
                        <strong>Important:</strong> This token expires in <strong>1 hour</strong> and can only be used once. Keep it secure — anyone with this token can reset the password for this account.
                    </div>

                    <div class="auth-divider" style="margin-top: 24px;">Or request a new token</div>
                    <a class="auth-btn-outline" href="<?php echo h($base_path); ?>public/forgot_password.php">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        <span>Request Another Token</span>
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
        Secure password recovery &middot; Token-based &middot; Expires in 1 hour
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyToken() {
    var token = document.getElementById('resetTokenValue').textContent.trim();
    if (!token) return;
    navigator.clipboard.writeText(token).then(function() {
        if (typeof Swal !== "undefined") {
            Swal.fire({
                toast: true,
                position: "top-end",
                icon: "success",
                title: "Token copied to clipboard!",
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }
    });
}
</script>
<script src="<?php echo h($asset_path); ?>js/auth.js?v=<?php echo filemtime(__DIR__ . "/../assets/js/auth.js"); ?>"></script>
<?php render_flash_alert(); ?>
</body>
</html>
<?php mysqli_close($conn); ?>
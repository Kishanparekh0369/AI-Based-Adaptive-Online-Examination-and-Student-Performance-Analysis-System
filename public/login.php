<?php
require_once("../app/config/database.php");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Sign In — AI Exam System";

if (current_logged_role() !== "") {
    header("Location: " . dashboard_url_for_role(current_logged_role()));
    exit;
}

$form_values = array("role" => "", "email" => "");
$form_errors = array();
$server_alert = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify_request()) {
        $server_alert = array(
            "type" => "danger",
            "message" => "Security validation failed. Please try again."
        );
    } else {
        $role = isset($_POST["role"]) ? trim((string)$_POST["role"]) : "";
        $email = isset($_POST["email"]) ? trim((string)$_POST["email"]) : "";
        $password = isset($_POST["password"]) ? (string)$_POST["password"] : "";
        $remember = !empty($_POST["remember"]);

        $form_values["role"] = $role;
        $form_values["email"] = $email;

        if ($role === "" || $email === "" || $password === "") {
            if ($role === "") { $form_errors["role"] = "Please select how you want to log in."; }
            if ($email === "") { $form_errors["email"] = "Please enter your email address."; }
            if ($password === "") { $form_errors["password"] = "Password is required."; }
        } else if (!in_array($role, array("admin", "faculty", "student"), true)) {
            $form_errors["role"] = "Please select a valid login role.";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors["email"] = "Please enter a valid email address.";
        } else if (strlen($password) < 6) {
            $form_errors["password"] = "Password must be at least 6 characters.";
        } else {
            $generic_error = "Invalid login credentials. Please check your details and try again.";
            $role_mismatch = "The selected role does not match this account.";
            $inactive_msg = "Your account is currently inactive. Please contact the administrator.";
            $authenticated = false;

            if ($role === "admin") {
                $stmt = mysqli_prepare($conn, "SELECT id, name, email, password FROM admin WHERE email=? LIMIT 1");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = $result ? mysqli_fetch_assoc($result) : null;
                    mysqli_stmt_close($stmt);

                    if ($row && admin_login_password_matches($password, $row["password"])) {
                        $authenticated = true;
                        secure_session_regenerate();
                        $_SESSION["admin_id"] = (int)$row["id"];
                        $_SESSION["admin_name"] = $row["name"];
                        $_SESSION["admin_email"] = $row["email"];
                        mark_login_success("admin", $row["name"]);
                        if ($remember) {
                            setcookie("aiexam_remember_email", $email, time() + 30 * 86400, "/");
                        }
                        redirect_alert("Admin login successful", "../admin/dashboard.php");
                    } else if ($row) {
                        $form_errors["password"] = $generic_error;
                    } else if (email_exists_in_any_role($conn, $email)) {
                        $form_errors["role"] = $role_mismatch;
                    } else {
                        $form_errors["password"] = $generic_error;
                    }
                }
            } else if ($role === "faculty") {
                $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, status FROM faculty WHERE email=? LIMIT 1");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = $result ? mysqli_fetch_assoc($result) : null;
                    mysqli_stmt_close($stmt);

                    if ($row) {
                        $status = isset($row["status"]) ? $row["status"] : "Active";
                        if ($status !== "Active") {
                            $form_errors["email"] = $inactive_msg;
                        } else if (faculty_login_password_matches($password, $row["password"])) {
                            $authenticated = true;
                            secure_session_regenerate();
                            $_SESSION["faculty_id"] = (int)$row["id"];
                            $_SESSION["faculty_name"] = $row["name"];
                            $_SESSION["faculty_email"] = $row["email"];
                            mysqli_query($conn, "UPDATE faculty SET last_login=NOW() WHERE id=" . (int)$row["id"]);
                            mark_login_success("faculty", $row["name"]);
                            if ($remember) {
                                setcookie("aiexam_remember_email", $email, time() + 30 * 86400, "/");
                            }
                            redirect_alert("Faculty login successful", "../faculty/dashboard.php");
                        } else {
                            $form_errors["password"] = $generic_error;
                        }
                    } else if (email_exists_in_any_role($conn, $email)) {
                        $form_errors["role"] = $role_mismatch;
                    } else {
                        $form_errors["password"] = $generic_error;
                    }
                }
            } else {
                $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, status FROM students WHERE email=? LIMIT 1");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = $result ? mysqli_fetch_assoc($result) : null;
                    mysqli_stmt_close($stmt);

                    if ($row) {
                        $status = isset($row["status"]) ? $row["status"] : "Active";
                        if ($status !== "Active") {
                            $form_errors["email"] = $inactive_msg;
                        } else if (student_login_password_matches($password, $row["password"])) {
                            $authenticated = true;
                            secure_session_regenerate();
                            $_SESSION["student_id"] = (int)$row["id"];
                            $_SESSION["student_name"] = $row["name"];
                            mysqli_query($conn, "UPDATE students SET last_login=NOW() WHERE id=" . (int)$row["id"]);
                            mark_login_success("student", $row["name"]);
                            if ($remember) {
                                setcookie("aiexam_remember_email", $email, time() + 30 * 86400, "/");
                            }
                            redirect_alert("Student login successful", "../student/dashboard.php");
                        } else {
                            $form_errors["password"] = $generic_error;
                        }
                    } else if (email_exists_in_any_role($conn, $email)) {
                        $form_errors["role"] = $role_mismatch;
                    } else {
                        $form_errors["password"] = $generic_error;
                    }
                }
            }

            if (!$authenticated && !$form_errors) {
                $form_errors["password"] = $generic_error;
            }
        }
    }
}

function email_exists_in_any_role($conn, $email)
{
    $found = false;
    foreach (array("admin", "faculty", "students") as $table) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM {$table} WHERE email=? LIMIT 1");
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            $found = true;
        }
        mysqli_stmt_close($stmt);
        if ($found) {
            break;
        }
    }
    return $found;
}

// Prefer cookie prefill when no POST value
if ($_SERVER["REQUEST_METHOD"] !== "POST" && $form_values["email"] === "" && !empty($_COOKIE["aiexam_remember_email"])) {
    $form_values["email"] = (string)$_COOKIE["aiexam_remember_email"];
}

$selected_role = $form_values["role"];

$show_registered = false;
if (isset($_GET["registered"])) {
    $show_registered = true;
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
                <span class="auth-logo" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="auth-kicker">AI Examination Platform</span>
                <h1>AI-Based Adaptive <span class="accent">Online Examination</span> &amp; Student Performance Analysis</h1>
                <p class="brand-lead">A secure, intelligent assessment platform for universities — adaptive exams, instant results, and AI-powered performance insights.</p>
                <ul class="auth-features">
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Adaptive Online Examinations</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>AI-Based Performance Analysis</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Intelligent Student Insights</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Role-Based Secure Dashboards</span></li>
                </ul>
                <div class="auth-brand-foot">
                    <span class="dot" aria-hidden="true"></span>
                    <span>Trusted by Admins, Faculty &amp; Students</span>
                </div>
            </aside>

            <!-- RIGHT: Login card -->
            <section class="auth-card" aria-labelledby="loginHeading">
                <header class="auth-card-head">
                    <span class="auth-card-mark" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
                    <h2 id="loginHeading">Welcome Back</h2>
                    <p>Sign in to continue to your dashboard</p>
                </header>

                <?php if ($server_alert) { ?>
                    <div class="auth-alert auth-alert-<?php echo h($server_alert["type"]); ?>" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span><?php echo h($server_alert["message"]); ?></span>
                    </div>
                <?php } ?>

                <form method="post" id="authLoginForm" novalidate autocomplete="on">
                    <?php echo csrf_field(); ?>

                    <!-- Login As -->
                    <div class="auth-field" data-field="role">
                        <div class="auth-section-label" id="loginAsLabel">
                            <span>Login As</span>
                            <span class="text-uppercase" style="font-size:0.68rem;color:#94A3B8;letter-spacing:0.04em;">Select one</span>
                        </div>
                        <div class="role-cards" role="radiogroup" aria-labelledby="loginAsLabel">

                            <label class="role-card">
                                <input type="radio" name="role" value="admin"
                                       <?php echo $selected_role === "admin" ? "checked" : ""; ?>>
                                <span class="role-card-body">
                                    <span class="role-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                                    <span class="role-meta">
                                        <span class="role-name">Admin</span>
                                        <span class="role-desc">System Admin</span>
                                    </span>
                                </span>
                            </label>

                            <label class="role-card">
                                <input type="radio" name="role" value="faculty"
                                       <?php echo $selected_role === "faculty" ? "checked" : ""; ?>>
                                <span class="role-card-body">
                                    <span class="role-icon" aria-hidden="true"><i class="bi bi-person-workspace"></i></span>
                                    <span class="role-meta">
                                        <span class="role-name">Faculty</span>
                                        <span class="role-desc">Teaching Staff</span>
                                    </span>
                                </span>
                            </label>

                            <label class="role-card">
                                <input type="radio" name="role" value="student"
                                       <?php echo $selected_role === "student" ? "checked" : ""; ?>>
                                <span class="role-card-body">
                                    <span class="role-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></span>
                                    <span class="role-meta">
                                        <span class="role-name">Student</span>
                                        <span class="role-desc">Exam Candidate</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p class="auth-field-error" id="roleError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["role"]) ? h($form_errors["role"]) : "Please select how you want to log in."; ?></span>
                        </p>
                        <?php if (isset($form_errors["role"])) { ?>
                            <script>window.__AUTH_ROLE_ERROR__ = <?php echo json_encode($form_errors["role"]); ?>;</script>
                        <?php } ?>
                    </div>

                    <!-- Email -->
                    <div class="auth-field <?php echo isset($form_errors["email"]) ? "is-invalid" : ""; ?>" data-field="email">
                        <label for="loginEmail">Email Address</label>
                        <div class="auth-input-wrap">
                            <input type="email" id="loginEmail" name="email" data-validate="email"
                                   value="<?php echo h($form_values["email"]); ?>"
                                   placeholder="name@example.com"
                                   autocomplete="username" required
                                   aria-describedby="loginEmailError">
                            <i class="bi bi-envelope lead-icon" aria-hidden="true"></i>
                            <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                            <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                        </div>
                        <p class="auth-field-error" id="loginEmailError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["email"]) ? h($form_errors["email"]) : ""; ?></span>
                        </p>
                    </div>

                    <!-- Password -->
                    <div class="auth-field <?php echo isset($form_errors["password"]) ? "is-invalid" : ""; ?>" data-field="password">
                        <label for="loginPassword">Password</label>
                        <div class="auth-input-wrap">
                            <input type="password" id="loginPassword" name="password" data-validate="password"
                                   class="pw-input" placeholder="Enter your password"
                                   autocomplete="current-password" required minlength="6"
                                   aria-describedby="loginPasswordError">
                            <i class="bi bi-lock lead-icon" aria-hidden="true"></i>
                            <button type="button" class="pw-toggle" id="loginPwToggle"
                                    aria-controls="loginPassword" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="auth-field-error" id="loginPasswordError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["password"]) ? h($form_errors["password"]) : ""; ?></span>
                        </p>
                    </div>

                    <div class="auth-meta">
                        <label class="auth-check" for="rememberMe">
                            <input type="checkbox" id="rememberMe" name="remember" value="1"
                                <?php echo !empty($_COOKIE["aiexam_remember_email"]) ? "checked" : ""; ?>>
                            <span>Remember me</span>
                        </label>
                        <a class="auth-link" href="<?php echo h($base_path); ?>public/forgot_password.php">
                            <i class="bi bi-key me-1" aria-hidden="true"></i>Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="auth-btn">
                        <span>Sign In</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="auth-divider">New here?</div>
                <a class="auth-btn-outline" href="<?php echo h($base_path); ?>student/register.php">
                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                    <span>Create Student Account</span>
                </a>

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
        Secure role-based authentication &middot; Admin &middot; Faculty &middot; Student
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($show_registered) { ?>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof Swal === "undefined") { return; }
    Swal.fire({
        icon: "success",
        title: "Account Created!",
        text: "Your student account has been created successfully. Select the Student role to sign in.",
        confirmButtonText: "Go to Login",
        confirmButtonColor: "#2563EB"
    });
});
<?php } ?>
<?php if (isset($form_errors["role"])) { ?>
document.addEventListener("DOMContentLoaded", function () {
    var wrap = document.querySelector('[data-field="role"]');
    if (wrap) wrap.classList.add("is-invalid");
});
<?php } ?>
</script>
<script src="<?php echo h($asset_path); ?>js/auth.js?v=<?php echo filemtime(__DIR__ . "/../assets/js/auth.js"); ?>"></script>
<?php render_flash_alert(); ?>
</body>
</html>
<?php mysqli_close($conn); ?>

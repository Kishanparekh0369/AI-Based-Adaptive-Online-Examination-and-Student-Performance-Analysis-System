<?php
require_once("../app/config/database.php");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Create Student Account — AI Exam System";

if (current_logged_role() === "student") {
    header("Location: dashboard.php");
    exit;
}

$course_options = array("BCA", "MCA", "B.Sc IT", "M.Sc IT", "BCS IT", "Computer Engineering", "Information Technology", "Computer Science", "AI & Data Science", "Cyber Security", "Other");
$semester_options = array("Semester 1", "Semester 2", "Semester 3", "Semester 4", "Semester 5", "Semester 6", "Semester 7", "Semester 8");

$form_values = array(
    "name" => "",
    "email" => "",
    "phone" => "",
    "course" => "",
    "semester" => ""
);
$form_errors = array();
$server_alert = null;
$registered = false;

function render_student_select_options($options, $selected = "")
{
    foreach ($options as $option) {
        echo '<option value="' . h($option) . '"' . ($selected === $option ? " selected" : "") . ">" . h($option) . "</option>";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify_request()) {
        $server_alert = array(
            "type" => "danger",
            "message" => "Security validation failed. Please try again."
        );
    } else {
        $name = isset($_POST["name"]) ? trim((string)$_POST["name"]) : "";
        $email = isset($_POST["email"]) ? trim((string)$_POST["email"]) : "";
        $phone = isset($_POST["phone"]) ? trim((string)$_POST["phone"]) : "";
        $password = isset($_POST["password"]) ? (string)$_POST["password"] : "";
        $confirm = isset($_POST["confirm_password"]) ? (string)$_POST["confirm_password"] : "";
        $course = isset($_POST["course"]) ? trim((string)$_POST["course"]) : "";
        $semester = isset($_POST["semester"]) ? trim((string)$_POST["semester"]) : "";

        $form_values = array(
            "name" => $name,
            "email" => $email,
            "phone" => $phone,
            "course" => $course,
            "semester" => $semester
        );

        if ($name === "" || $email === "" || $phone === "" || $password === "" || $course === "" || $semester === "") {
            if ($name === "") { $form_errors["name"] = "Please enter your full name."; }
            if ($email === "") { $form_errors["email"] = "Please enter your email address."; }
            if ($phone === "") { $form_errors["phone"] = "Please enter your phone number."; }
            if ($password === "") { $form_errors["password"] = "Please enter a password."; }
            if ($course === "") { $form_errors["course"] = "Please select a course."; }
            if ($semester === "") { $form_errors["semester"] = "Please select a semester."; }
        }

        if (!isset($form_errors["name"]) && (strlen($name) < 3 || !preg_match("/^[A-Za-z][A-Za-z\s.'-]{2,60}$/", $name))) {
            $form_errors["name"] = "Please enter a valid full name (letters only, min 3 characters).";
        }

        if (!isset($form_errors["email"]) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors["email"] = "Please enter a valid email address.";
        }

        if (!isset($form_errors["phone"]) && !preg_match("/^[0-9]{10}$/", $phone)) {
            $form_errors["phone"] = "Please enter a valid 10 digit phone number.";
        }

        if (!isset($form_errors["password"])) {
            if (strlen($password) < 8) {
                $form_errors["password"] = "Password must be at least 8 characters.";
            }
        }

        if ($confirm === "") {
            $form_errors["confirm_password"] = "Please confirm your password.";
        } else if ($confirm !== $password) {
            $form_errors["confirm_password"] = "Passwords do not match.";
        }

        if (!$form_errors) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE email=? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $exists = $result && mysqli_num_rows($result) > 0;
                mysqli_stmt_close($stmt);
                if ($exists) {
                    $form_errors["email"] = "This email address is already registered.";
                }
            }
        }

        $profileImage = "";
        if (!$form_errors && !empty($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK) {
                $form_errors["profile_photo"] = "Profile photo upload failed. Please try again.";
            } else {
                $allowed = array("image/jpeg", "image/png");
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES["profile_photo"]["tmp_name"]);
                if (!in_array($mime, $allowed, true)) {
                    $form_errors["profile_photo"] = "Only JPEG and PNG files are allowed.";
                } else if ($_FILES["profile_photo"]["size"] > 2 * 1024 * 1024) {
                    $form_errors["profile_photo"] = "File size must be under 2MB.";
                } else {
                    $uploadDir = __DIR__ . "/../assets/uploads/profile/";
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }
                    $ext = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
                    if (!in_array($ext, array("jpg", "jpeg", "png"), true)) {
                        $ext = $mime === "image/png" ? "png" : "jpg";
                    }
                    $filename = "profile_" . time() . "_" . rand(100, 999) . "." . $ext;
                    $filepath = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $filepath)) {
                        $profileImage = $filename;
                    } else {
                        $form_errors["profile_photo"] = "Profile photo upload failed. Please try again.";
                    }
                }
            }
        }

        if (!$form_errors) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            if ($profileImage !== "") {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO students (name,email,phone,password,course,semester,profile_image,profile_image_updated_at) VALUES (?,?,?,?,?,?,?,NOW())"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssssss", $name, $email, $phone, $password_hash, $course, $semester, $profileImage);
                    $inserted = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                } else {
                    $inserted = false;
                }
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO students (name,email,phone,password,course,semester) VALUES (?,?,?,?,?,?)"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $phone, $password_hash, $course, $semester);
                    $inserted = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                } else {
                    $inserted = false;
                }
            }

            if ($inserted) {
                $registered = true;
            } else {
                $server_alert = array(
                    "type" => "danger",
                    "message" => "We could not create your account right now. Please try again later."
                );
            }
        }
    }
}

if ($registered) {
    header("Location: ../public/login.php?registered=1");
    exit;
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
            <aside class="auth-brand" aria-label="Registration benefits">
                <span class="auth-logo" aria-hidden="true"><i class="bi bi-person-plus-fill"></i></span>
                <span class="auth-kicker">Student Registration</span>
                <h1>Start Your <span class="accent">Adaptive Exam</span> Journey Today</h1>
                <p class="brand-lead">Create your student account to access timed examinations, instant results, and personalized AI performance analysis.</p>
                <ul class="auth-features">
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Quick &amp; free student registration</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Adaptive online examinations</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Instant results with AI insights</span></li>
                    <li><i class="bi bi-check-lg" aria-hidden="true"></i><span>Personal performance analytics</span></li>
                </ul>
                <div class="auth-brand-foot">
                    <span class="dot" aria-hidden="true"></span>
                    <span>Already have an account? Sign in from the login page</span>
                </div>
            </aside>

            <!-- RIGHT: Registration card -->
            <section class="auth-card" aria-labelledby="registerHeading">
                <header class="auth-card-head">
                    <span class="auth-card-mark" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
                    <h2 id="registerHeading">Create Your Student Account</h2>
                    <p>Register to access adaptive examinations and personalized performance analysis.</p>
                </header>

                <?php if ($server_alert) { ?>
                    <div class="auth-alert auth-alert-<?php echo h($server_alert["type"]); ?>" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span><?php echo h($server_alert["message"]); ?></span>
                    </div>
                <?php } ?>

                <form method="post" id="authRegisterForm" enctype="multipart/form-data" novalidate autocomplete="on">
                    <?php echo csrf_field(); ?>

                    <div class="auth-form-row">
                        <div class="auth-field <?php echo isset($form_errors["name"]) ? "is-invalid" : ""; ?>">
                            <label for="regName">Full Name</label>
                            <div class="auth-input-wrap">
                                <input type="text" id="regName" name="name" data-validate="name"
                                       value="<?php echo h($form_values["name"]); ?>"
                                       placeholder="Enter your full name"
                                       autocomplete="name" required
                                       aria-describedby="regNameError">
                                <i class="bi bi-person lead-icon" aria-hidden="true"></i>
                                <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                            </div>
                            <p class="auth-field-error" id="regNameError">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span><?php echo isset($form_errors["name"]) ? h($form_errors["name"]) : ""; ?></span>
                            </p>
                        </div>

                        <div class="auth-field <?php echo isset($form_errors["email"]) ? "is-invalid" : ""; ?>">
                            <label for="regEmail">Email Address</label>
                            <div class="auth-input-wrap">
                                <input type="email" id="regEmail" name="email" data-validate="email"
                                       value="<?php echo h($form_values["email"]); ?>"
                                       placeholder="you@example.com"
                                       autocomplete="email" required
                                       aria-describedby="regEmailError">
                                <i class="bi bi-envelope lead-icon" aria-hidden="true"></i>
                                <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                            </div>
                            <p class="auth-field-error" id="regEmailError">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span><?php echo isset($form_errors["email"]) ? h($form_errors["email"]) : ""; ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="auth-form-row">
                        <div class="auth-field <?php echo isset($form_errors["phone"]) ? "is-invalid" : ""; ?>">
                            <label for="regPhone">Mobile Number</label>
                            <div class="auth-input-wrap">
                                <input type="tel" id="regPhone" name="phone" data-validate="phone"
                                       value="<?php echo h($form_values["phone"]); ?>"
                                       placeholder="10-digit mobile number"
                                       autocomplete="tel" maxlength="10" required
                                       inputmode="numeric" pattern="[0-9]{10}"
                                       aria-describedby="regPhoneError">
                                <i class="bi bi-telephone lead-icon" aria-hidden="true"></i>
                                <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                            </div>
                            <p class="auth-field-error" id="regPhoneError">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span><?php echo isset($form_errors["phone"]) ? h($form_errors["phone"]) : ""; ?></span>
                            </p>
                        </div>

                        <div class="auth-field <?php echo isset($form_errors["password"]) ? "is-invalid" : ""; ?>">
                            <label for="regPassword">Password</label>
                            <div class="auth-input-wrap">
                                <input type="password" id="regPassword" name="password" data-validate="password" data-mode="new"
                                       class="pw-input" placeholder="Min 8 characters"
                                       autocomplete="new-password" required minlength="8"
                                       aria-describedby="regPasswordError">
                                <i class="bi bi-lock lead-icon" aria-hidden="true"></i>
                                <button type="button" class="pw-toggle" id="regPwToggle"
                                        aria-controls="regPassword" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="pw-strength" data-strength-for="regPassword" aria-live="polite">
                                <div class="pw-strength-track"><div class="pw-strength-bar"></div></div>
                                <div class="pw-strength-meta">
                                    <span>Password Strength</span>
                                    <span class="pw-strength-label">Weak</span>
                                </div>
                            </div>
                            <p class="auth-field-error" id="regPasswordError">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span><?php echo isset($form_errors["password"]) ? h($form_errors["password"]) : ""; ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="auth-form-row">
                        <div class="auth-field <?php echo isset($form_errors["confirm_password"]) ? "is-invalid" : ""; ?>">
                            <label for="regConfirmPassword">Confirm Password</label>
                            <div class="auth-input-wrap">
                                <input type="password" id="regConfirmPassword" name="confirm_password"
                                       data-match="regPassword"
                                       class="pw-input" placeholder="Re-enter password"
                                       autocomplete="new-password" required minlength="8"
                                       aria-describedby="regConfirmError">
                                <i class="bi bi-lock-fill lead-icon" aria-hidden="true"></i>
                                <button type="button" class="pw-toggle" id="regConfirmPwToggle"
                                        aria-controls="regConfirmPassword" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p class="auth-field-error" id="regConfirmError">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span><?php echo isset($form_errors["confirm_password"]) ? h($form_errors["confirm_password"]) : ""; ?></span>
                            </p>
                        </div>

                        <div class="auth-field <?php echo isset($form_errors["course"]) ? "is-invalid" : ""; ?>">
                            <label for="regCourse">Course / Program</label>
                            <div class="auth-input-wrap">
                                <select id="regCourse" name="course" data-validate="select" data-label="a course" required
                                        aria-describedby="regCourseError">
                                    <option value="">Select Course</option>
                                    <?php render_student_select_options($course_options, $form_values["course"]); ?>
                                </select>
                                <i class="bi bi-book lead-icon" aria-hidden="true"></i>
                                <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                            </div>
                            <p class="auth-field-error" id="regCourseError">
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span><?php echo isset($form_errors["course"]) ? h($form_errors["course"]) : ""; ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="auth-field <?php echo isset($form_errors["semester"]) ? "is-invalid" : ""; ?>">
                        <label for="regSemester">Semester / Year</label>
                        <div class="auth-input-wrap">
                            <select id="regSemester" name="semester" data-validate="select" data-label="a semester" required
                                    aria-describedby="regSemesterError">
                                <option value="">Select Semester</option>
                                <?php render_student_select_options($semester_options, $form_values["semester"]); ?>
                            </select>
                            <i class="bi bi-layers lead-icon" aria-hidden="true"></i>
                            <span class="auth-field-status valid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
                            <span class="auth-field-status invalid" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                        </div>
                        <p class="auth-field-error" id="regSemesterError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["semester"]) ? h($form_errors["semester"]) : ""; ?></span>
                        </p>
                    </div>

                    <div class="auth-field <?php echo isset($form_errors["profile_photo"]) ? "is-invalid" : ""; ?>">
                        <label for="regPhoto">Profile Photo <span style="font-weight:500;color:#94A3B8;">(optional)</span></label>
                        <div class="auth-photo-row">
                            <img id="regPhotoPreview" class="auth-photo-preview" src="" alt="">
                            <span class="auth-photo-placeholder" aria-hidden="true"><i class="bi bi-person"></i></span>
                            <div class="auth-photo-copy">
                                <strong>Upload photo</strong>
                                <span>JPEG or PNG, max 2MB</span>
                            </div>
                            <input type="file" id="regPhoto" name="profile_photo"
                                   accept="image/jpeg,image/png"
                                   data-photo-preview="regPhotoPreview">
                        </div>
                        <p class="auth-field-error" id="regPhotoError">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <span><?php echo isset($form_errors["profile_photo"]) ? h($form_errors["profile_photo"]) : ""; ?></span>
                        </p>
                    </div>

                    <button type="submit" class="auth-btn mt-2">
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        <span>Create Account</span>
                    </button>
                </form>

                <div class="auth-divider">Already registered?</div>
                <a class="auth-btn-outline" href="login.php">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    <span>Go to Login</span>
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
        Secure student registration &middot; Your data is protected
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo h($asset_path); ?>js/auth.js?v=<?php echo filemtime(__DIR__ . "/../assets/js/auth.js"); ?>"></script>
<?php render_flash_alert(); ?>
</body>
</html>
<?php mysqli_close($conn); ?>

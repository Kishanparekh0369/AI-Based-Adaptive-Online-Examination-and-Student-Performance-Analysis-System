<?php
require_once("../app/config/database.php");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Signed Out — AI Exam System";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Visiting while still signed in makes no sense — send them home to their panel.
if (current_logged_role() !== "") {
    header("Location: " . dashboard_url_for_role(current_logged_role()));
    exit;
}

// Display name only (first name), captured BEFORE session destruction by the
// role logout scripts. Escaped on output; never trusted blindly.
$raw_name = isset($_GET["name"]) ? trim((string)$_GET["name"]) : "";
$first_name = "";
if ($raw_name !== "") {
    $parts = preg_split('/\s+/', $raw_name);
    $first_name = isset($parts[0]) ? trim((string)$parts[0]) : "";
}
if ($first_name === "" || mb_strlen($first_name) > 40) {
    $first_name = "";
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
        <div class="auth-grid" style="grid-template-columns:1fr;max-width:560px;">
            <section class="auth-card logout-card" aria-labelledby="logoutHeading">
                <div class="logout-check" aria-hidden="true">
                    <i class="bi bi-check-lg"></i>
                </div>
                <?php if ($first_name !== "") { ?>
                    <h2 id="logoutHeading">Goodbye, <?php echo h($first_name); ?>!</h2>
                <?php } else { ?>
                    <h2 id="logoutHeading">Signed Out Successfully</h2>
                <?php } ?>
                <p class="logout-sub">
                    Your session has been securely ended.<br>
                    Thank you for using the Student Portal AI Exam System.
                </p>
                <a class="auth-btn" href="login.php">
                    <span>Sign In Again</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
                <a class="auth-btn-outline" href="<?php echo h($base_path); ?>index.php">
                    <i class="bi bi-house" aria-hidden="true"></i>
                    <span>Back to Home</span>
                </a>
                <div class="logout-meta">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Protected pages require sign-in</span>
                </div>
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
<?php unset($_SESSION["flash_alert"]); ?>
</body>
</html>
<?php mysqli_close($conn); ?>

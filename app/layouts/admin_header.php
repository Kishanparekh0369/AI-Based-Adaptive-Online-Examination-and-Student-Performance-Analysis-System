<?php
require_once __DIR__ . "/../config/database.php";

$conn = conn();
$page_title = isset($page_title) ? $page_title : "Admin Panel";
$admin_notifications = notification_data("admin");
if (isset($_GET["mark_notifications"])) {
    mark_notifications_read("admin");
    if (isset($_SERVER["HTTP_X_REQUESTED_WITH"])) {
        echo "OK";
        exit;
    }
}

// Prevent protected admin content from being served from browser cache.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Admin identity (session) — same pattern as student/faculty portals.
$ap_admin_name = isset($_SESSION["admin_name"]) ? (string)$_SESSION["admin_name"] : "Administrator";
$ap_admin_email = isset($_SESSION["admin_email"]) ? (string)$_SESSION["admin_email"] : "";
if (trim($ap_admin_name) === "") { $ap_admin_name = "Administrator"; }
$ap_admin_initial = strtoupper(substr($ap_admin_name, 0, 1));
$ap_notif_n = isset($admin_notifications["count"]) ? (int)$admin_notifications["count"] : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?> &middot; Admin Portal</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>images/favicon.svg?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>images/favicon-32x32.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>images/favicon-16x16.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>images/apple-touch-icon.png?v=1">
    <meta name="theme-color" content="#2563EB">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>css/style.css" rel="stylesheet">
    <link href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>css/student.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/student.css'); ?>" rel="stylesheet">
    <link href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>css/admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admin.css'); ?>" rel="stylesheet">
    <link href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>css/charts.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/charts.css'); ?>" rel="stylesheet">
    <script>
    (function () {
        try {
            var t = localStorage.getItem("sp-theme");
            document.documentElement.setAttribute("data-sp-theme", t === "light" ? "light" : "dark");
        } catch (e) {
            document.documentElement.setAttribute("data-sp-theme", "dark");
        }
    })();
    </script>
</head>

<body class="panel-body admin-panel">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <header class="sp-topbar ap-topbar" aria-label="Top navigation">
        <button class="btn sp-icon-btn d-lg-none sidebar-open-btn" type="button" aria-label="Open menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="sp-topbar-title">
            <h1><?php echo h($page_title); ?></h1>
            <small><span id="welcomeMessage">Welcome Back</span> &middot; <span class="live-date"></span> &middot; <span class="live-clock"></span></small>
        </div>
        <div class="sp-topbar-actions">
            <button type="button" class="btn sp-icon-btn d-none d-sm-inline-flex" id="spThemeToggleTop" title="Switch theme" aria-label="Switch dark / light theme">
                <i class="bi bi-sun-fill" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn sp-icon-btn position-relative notification-toggle" title="Notifications"
                    data-bs-toggle="modal" data-bs-target="#apNotifModal"
                    data-notification-read-url="?mark_notifications=1" aria-label="Notifications">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <?php if ($ap_notif_n > 0) { ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $ap_notif_n; ?></span><?php } ?>
            </button>
            <button type="button" class="btn sp-icon-btn sp-logout-top" onclick="confirmAdminLogout(); return false;" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            </button>
            <div class="dropdown">
                <button type="button" class="btn sp-topbar-profile dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Admin account menu" title="<?php echo h($ap_admin_name); ?>">
                    <span class="sp-avatar-initials" aria-hidden="true"><?php echo h($ap_admin_initial); ?></span>
                    <span class="d-none d-md-inline"><?php echo h($ap_admin_name); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow ap-profile-menu">
                    <li class="ap-profile-head">
                        <strong><?php echo h($ap_admin_name); ?></strong>
                        <?php if ($ap_admin_email !== "") { ?><small><?php echo h($ap_admin_email); ?></small><?php } ?>
                        <span class="ap-role">Administrator</span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmAdminLogout(); return false;"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

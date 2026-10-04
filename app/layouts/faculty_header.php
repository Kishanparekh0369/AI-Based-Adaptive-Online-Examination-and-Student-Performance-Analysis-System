<?php
require_once __DIR__ . "/../config/database.php";

$conn = conn();
$page_title = isset($page_title) ? $page_title : "Faculty Portal";
$faculty_id = isset($_SESSION["faculty_id"]) ? (int)$_SESSION["faculty_id"] : 0;
$faculty_notifications = notification_data("faculty", $faculty_id > 0 ? $faculty_id : null);
if (isset($_GET["mark_notifications"])) {
    mark_notifications_read("faculty", $faculty_id > 0 ? $faculty_id : null);
    if (isset($_SERVER["HTTP_X_REQUESTED_WITH"])) {
        echo "OK";
        exit;
    }
}

// Fetch faculty identity (name + photo) for the topbar — same pattern as student portal.
$fp_top_name = isset($_SESSION["faculty_name"]) ? $_SESSION["faculty_name"] : "Faculty";
$fp_top_photo = "";
if ($faculty_id > 0 && isset($conn)) {
    $fp_top_stmt = mysqli_prepare($conn, "SELECT name, profile_image FROM faculty WHERE id=? LIMIT 1");
    if ($fp_top_stmt) {
        mysqli_stmt_bind_param($fp_top_stmt, "i", $faculty_id);
        mysqli_stmt_execute($fp_top_stmt);
        $fp_top_res = mysqli_stmt_get_result($fp_top_stmt);
        if ($fp_top_res && $fp_top_row = mysqli_fetch_assoc($fp_top_res)) {
            if (!empty($fp_top_row["name"])) { $fp_top_name = $fp_top_row["name"]; }
            if (!empty($fp_top_row["profile_image"]) && file_exists(__DIR__ . "/../../assets/uploads/faculty/" . $fp_top_row["profile_image"])) {
                $fp_top_photo = "../assets/uploads/faculty/" . $fp_top_row["profile_image"];
            }
        }
        mysqli_stmt_close($fp_top_stmt);
    }
}
$fp_top_initial = strtoupper(substr($fp_top_name, 0, 1));
$fp_notif_n = isset($faculty_notifications["count"]) ? (int)$faculty_notifications["count"] : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?> &middot; Faculty Portal</title>
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
    <link href="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>css/faculty.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/faculty.css'); ?>" rel="stylesheet">
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

<body class="panel-body faculty-panel">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <header class="sp-topbar fp-topbar" aria-label="Top navigation">
        <button class="btn sp-icon-btn d-lg-none sidebar-open-btn" type="button" aria-label="Open menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="sp-topbar-title">
            <h1><?php echo h($page_title); ?></h1>
            <small><span class="live-date"></span> &middot; <span class="live-clock"></span></small>
        </div>
        <div class="sp-topbar-actions">
            <button type="button" class="btn sp-icon-btn d-none d-sm-inline-flex" id="spThemeToggleTop" title="Switch theme" aria-label="Switch dark / light theme">
                <i class="bi bi-sun-fill" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn sp-icon-btn position-relative" title="Notifications"
                    data-bs-toggle="modal" data-bs-target="#fpNotifModal" aria-label="Notifications">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <?php if ($fp_notif_n > 0) { ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $fp_notif_n; ?></span><?php } ?>
            </button>
            <button type="button" class="btn sp-icon-btn sp-logout-top" onclick="confirmFacultyLogout(); return false;" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            </button>
            <a href="profile.php" class="sp-topbar-profile" aria-label="View my profile" title="<?php echo h($fp_top_name); ?>">
                <?php if ($fp_top_photo !== "") { ?>
                    <img src="<?php echo h($fp_top_photo); ?>" alt="Profile photo of <?php echo h($fp_top_name); ?>">
                <?php } else { ?>
                    <span class="sp-avatar-initials" aria-hidden="true"><?php echo h($fp_top_initial); ?></span>
                <?php } ?>
                <span class="d-none d-md-inline"><?php echo h($fp_top_name); ?></span>
            </a>
        </div>
    </header>

<?php
$sp_current = basename($_SERVER["PHP_SELF"] ?? "dashboard.php");

$sp_is = function ($file) use ($sp_current) {
    return $sp_current === $file ? "active" : "";
};
?>
<div class="sidebar panel-sidebar student-sidebar p-3" id="studentSidebar" aria-label="Student navigation">
    <div class="sp-brandrow mb-3">
        <span class="sp-brand-badge" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
        <div class="sp-brand-copy">
            <strong>Student Portal</strong>
            <small>AI Exam System</small>
        </div>
        <button class="btn btn-sm sp-icon-btn d-lg-none sidebar-close-btn" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="sp-nav">
        <div class="menu-group">
            <h6>Overview</h6>
            <a href="dashboard.php" class="<?php echo $sp_is("dashboard.php"); ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
        </div>

        <div class="menu-group">
            <h6>Examinations</h6>
            <a href="exams.php" class="<?php echo $sp_is("exams.php"); ?>"><i class="bi bi-journal-check"></i><span>Exams</span></a>
            <a href="performance.php" class="<?php echo $sp_is("performance.php"); ?>"><i class="bi bi-graph-up-arrow"></i><span>Performance</span></a>
            <a href="ai_feedback.php" class="<?php echo $sp_is("ai_feedback.php"); ?>"><i class="bi bi-robot"></i><span>AI Feedback</span></a>
            <a href="result.php" class="<?php echo $sp_is("result.php"); ?>"><i class="bi bi-trophy"></i><span>Results</span></a>
            <a href="report.php" class="<?php echo $sp_is("report.php"); ?>"><i class="bi bi-file-earmark-text"></i><span>Report Card</span></a>
        </div>

        <div class="menu-group">
            <h6>Academic</h6>
            <a href="announcements.php" class="<?php echo $sp_is("announcements.php"); ?>"><i class="bi bi-megaphone"></i><span>Announcements</span><?php $sp_ann_u = isset($_SESSION["student_id"]) ? student_unread_announcements((int)$_SESSION["student_id"]) : 0; if ($sp_ann_u > 0) { ?><span class="badge bg-danger ms-auto"><?php echo $sp_ann_u; ?></span><?php } ?></a>
            <a href="notifications.php" class="<?php echo $sp_is("notifications.php"); ?>"><i class="bi bi-bell"></i><span>Notifications</span><?php $sp_not_u = isset($sp_notif_n) ? (int)$sp_notif_n : 0; if ($sp_not_u > 0) { ?><span class="badge bg-danger ms-auto"><?php echo $sp_not_u; ?></span><?php } ?></a>
        </div>

        <div class="menu-group">
            <h6>My Account</h6>
            <a href="profile.php" class="<?php echo $sp_is("profile.php"); ?>"><i class="bi bi-person-badge"></i><span>View Profile</span></a>
            <a href="edit_profile.php" class="<?php echo $sp_is("edit_profile.php"); ?>"><i class="bi bi-pencil-square"></i><span>Edit Profile</span></a>
            <a href="change_password.php" class="<?php echo $sp_is("change_password.php"); ?>"><i class="bi bi-shield-lock"></i><span>Change Password</span></a>
        </div>

        <div class="menu-group">
            <h6>Support</h6>
            <a href="feedback.php" class="<?php echo $sp_is("feedback.php"); ?>"><i class="bi bi-chat-dots"></i><span>Feedback</span></a>
        </div>
    </nav>

    <div class="sp-sidebar-foot">
        <button type="button" class="sp-logout-btn" onclick="spConfirmLogout(); return false;">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span>
        </button>
    </div>
</div>
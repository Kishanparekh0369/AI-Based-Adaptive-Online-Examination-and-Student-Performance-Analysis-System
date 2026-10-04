<?php
$ap_current = basename($_SERVER["PHP_SELF"] ?? "dashboard.php");

$ap_is = function ($file) use ($ap_current) {
    return $ap_current === $file ? "active" : "";
};
?>
<div class="sidebar panel-sidebar student-sidebar admin-sidebar p-3" id="adminSidebar" aria-label="Admin navigation">
    <div class="sp-brandrow mb-3">
        <span class="sp-brand-badge ap-brand-badge" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
        <div class="sp-brand-copy">
            <strong>Admin Portal</strong>
            <small>AI Exam System</small>
        </div>
        <button class="btn btn-sm sp-icon-btn d-lg-none sidebar-close-btn" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="sp-nav">
        <div class="menu-group">
            <h6>Overview</h6>
            <a href="dashboard.php" class="<?php echo $ap_is("dashboard.php"); ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
        </div>

        <div class="menu-group">
            <h6>User Management</h6>
            <a href="faculty.php" class="<?php echo $ap_is("faculty.php"); ?>"><i class="bi bi-person-badge"></i><span>Manage Faculty</span></a>
            <a href="students.php" class="<?php echo $ap_is("students.php"); ?>"><i class="bi bi-people"></i><span>Manage Students</span></a>
        </div>

        <div class="menu-group">
            <h6>Academic &amp; Exams</h6>
            <a href="subjects.php" class="<?php echo $ap_is("subjects.php"); ?>"><i class="bi bi-book"></i><span>Manage Subjects</span></a>
            <a href="exams.php" class="<?php echo $ap_is("exams.php"); ?>"><i class="bi bi-calendar-check"></i><span>Manage Exams</span></a>
            <a href="questions.php" class="<?php echo $ap_is("questions.php"); ?>"><i class="bi bi-patch-question"></i><span>Question Bank</span></a>
        </div>

        <div class="menu-group">
            <h6>Analytics &amp; Results</h6>
            <a href="reports.php" class="<?php echo $ap_is("reports.php"); ?>"><i class="bi bi-file-earmark-text"></i><span>Overall Reports</span></a>
            <a href="results.php" class="<?php echo $ap_is("results.php"); ?>"><i class="bi bi-award"></i><span>System Results</span></a>
            <a href="performance.php" class="<?php echo $ap_is("performance.php"); ?>"><i class="bi bi-graph-up"></i><span>Overall Performance</span></a>
            <a href="ai_dashboard.php" class="<?php echo $ap_is("ai_dashboard.php"); ?>"><i class="bi bi-robot"></i><span>AI Dashboard</span></a>
        </div>

        <div class="menu-group">
            <h6>Communication</h6>
            <a href="feedback.php" class="<?php echo $ap_is("feedback.php"); ?>"><i class="bi bi-chat-dots"></i><span>Feedback</span></a>
            <a href="contact_messages.php" class="<?php echo $ap_is("contact_messages.php"); ?>"><i class="bi bi-envelope-paper"></i><span>Contact Messages</span></a>
        </div>


        <div class="menu-group">
            <h6>Account</h6>
            <a href="change_password.php" class="<?php echo $ap_is("change_password.php"); ?>"><i class="bi bi-key"></i><span>Change Password</span></a>
        </div>
    </nav>

    <div class="sp-sidebar-foot">
        <button type="button" class="sp-logout-btn" onclick="confirmAdminLogout(); return false;">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span>
        </button>
    </div>
</div>

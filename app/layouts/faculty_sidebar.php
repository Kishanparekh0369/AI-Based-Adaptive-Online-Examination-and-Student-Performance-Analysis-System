<?php
$fp_current = basename($_SERVER["PHP_SELF"] ?? "dashboard.php");

$fp_is = function ($file) use ($fp_current) {
    return $fp_current === $file ? "active" : "";
};
?>
<div class="sidebar panel-sidebar student-sidebar faculty-sidebar p-3" id="facultySidebar" aria-label="Faculty navigation">
    <div class="sp-brandrow mb-3">
        <span class="sp-brand-badge fp-brand-badge" aria-hidden="true"><i class="bi bi-person-workspace"></i></span>
        <div class="sp-brand-copy">
            <strong>Faculty Portal</strong>
            <small>AI Exam System</small>
        </div>
        <button class="btn btn-sm sp-icon-btn d-lg-none sidebar-close-btn" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="sp-nav">
        <div class="menu-group">
            <h6>Overview</h6>
            <a href="dashboard.php" class="<?php echo $fp_is("dashboard.php"); ?>"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
            <a href="subjects.php" class="<?php echo $fp_is("subjects.php"); ?>"><i class="bi bi-book"></i><span>My Subjects</span></a>
            <a href="students.php" class="<?php echo $fp_is("students.php"); ?>"><i class="bi bi-people"></i><span>Students</span></a>
        </div>

        <div class="menu-group">
            <h6>Academic &amp; Exams</h6>
            <a href="exams.php" class="<?php echo $fp_is("exams.php"); ?>"><i class="bi bi-plus-circle"></i><span>Create Exam</span></a>
            <a href="exam_library.php" class="<?php echo $fp_is("exam_library.php"); ?>"><i class="bi bi-collection"></i><span>Exam Library</span></a>
            <a href="questions.php" class="<?php echo $fp_is("questions.php"); ?>"><i class="bi bi-patch-question"></i><span>Question Bank &amp; AI</span></a>
        </div>

        <div class="menu-group">
            <h6>Communication</h6>
            <a href="announcements.php" class="<?php echo $fp_is("announcements.php"); ?>"><i class="bi bi-megaphone"></i><span>Announcements</span><?php $fp_pend = isset($_SESSION["faculty_id"]) ? faculty_pending_announcements((int)$_SESSION["faculty_id"]) : 0; if ($fp_pend > 0) { ?><span class="badge bg-warning text-dark ms-auto"><?php echo $fp_pend; ?></span><?php } ?></a>
        </div>

        <div class="menu-group">
            <h6>Analytics &amp; Results</h6>
            <a href="performance.php" class="<?php echo $fp_is("performance.php"); ?>"><i class="bi bi-graph-up"></i><span>Student Performance</span></a>
            <a href="results.php" class="<?php echo $fp_is("results.php"); ?>"><i class="bi bi-award"></i><span>Subject Results</span></a>
            <a href="reports.php" class="<?php echo $fp_is("reports.php"); ?>"><i class="bi bi-file-earmark-text"></i><span>Reports</span></a>
        </div>

        <div class="menu-group">
            <h6>Account</h6>
            <a href="profile.php" class="<?php echo $fp_is("profile.php"); ?>"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
            <a href="change_password.php" class="<?php echo $fp_is("change_password.php"); ?>"><i class="bi bi-key"></i><span>Change Password</span></a>
        </div>
    </nav>

    <div class="sp-sidebar-foot">
        <button type="button" class="sp-logout-btn" onclick="confirmFacultyLogout(); return false;">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span>
        </button>
    </div>
</div>

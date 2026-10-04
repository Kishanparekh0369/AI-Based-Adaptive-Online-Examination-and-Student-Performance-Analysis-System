<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Announcement Details";

$faculty_id = (int)$_SESSION["faculty_id"];
$aid = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($aid <= 0) {
    redirect_alert("Invalid announcement.", "announcements.php", "error");
}

$a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT a.*, s.subject_name, s.subject_code, e.exam_title, e.exam_date, e.duration, e.exam_mode
    FROM announcements a
    LEFT JOIN subjects s ON s.id=a.subject_id
    LEFT JOIN exams e ON e.id=a.exam_id
    WHERE a.id=$aid AND a.faculty_id=$faculty_id LIMIT 1"));
if (!$a) {
    redirect_alert("Access Denied: You cannot view this announcement.", "announcements.php", "error");
}

$types = announcement_types();
$t = $types[$a["type"]] ?? $types["general"];

$recipients = array();
$q = mysqli_query($conn, "SELECT ar.*, st.name, st.email, st.course, st.semester FROM announcement_recipients ar LEFT JOIN students st ON st.id=ar.student_id WHERE ar.announcement_id=$aid ORDER BY ar.read_at IS NULL, ar.delivered_at DESC");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $recipients[] = $r; } }
$targeted = count($recipients);
$read = 0;
foreach ($recipients as $rc) { if (!empty($rc["read_at"])) { $read++; } }
$unread = $targeted - $read;
$rate = $targeted > 0 ? round(($read / $targeted) * 100, 1) : 0;

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item"><a href="announcements.php">Announcements</a></li>
                    <li class="breadcrumb-item active">Details</li>
                </ol>
            </nav>

            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi <?php echo h($t["icon"]); ?>"></i> <?php echo h($t["label"]); ?></span>
                    <h1><?php echo h($a["title"]); ?></h1>
                    <p>
                        <span class="badge bg-<?php echo $a["status"] === "Published" ? "success" : ($a["status"] === "Scheduled" ? "warning text-dark" : ($a["status"] === "Archived" ? "secondary" : "info")); ?>"><?php echo h($a["status"]); ?></span>
                        <?php if ((int)$a["is_pinned"] === 1) { ?><span class="badge bg-warning text-dark"><i class="bi bi-pin-fill"></i> Pinned</span><?php } ?>
                        <?php if (($a["priority"] ?? "normal") !== "normal") { ?><span class="badge bg-<?php echo $a["priority"] === "urgent" ? "danger" : "warning text-dark"; ?>"><?php echo h(ucfirst($a["priority"])); ?></span><?php } ?>
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($a["status"] === "Draft" || $a["status"] === "Scheduled") { ?>
                        <a class="sp-btn sp-btn-primary" href="announcements.php?publish=<?php echo $aid; ?>"><i class="bi bi-send"></i> Publish Now</a>
                    <?php } ?>
                    <a class="sp-btn sp-btn-ghost" href="announcements.php"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-7 sp-fade-up">
                    <div class="sp-panel h-100">
                        <div class="sp-panel-head"><h3><i class="bi bi-chat-text me-2 text-primary"></i>Message</h3></div>
                        <p style="white-space:pre-line;line-height:1.75;"><?php echo h($a["message"]); ?></p>
                        <hr>
                        <div class="row g-2">
                            <div class="col-6 col-md-4"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-mortarboard"></i>Course</span><span class="sp-info-value"><?php echo h($a["course"]); ?></span></div></div>
                            <div class="col-6 col-md-4"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-layers"></i>Semester</span><span class="sp-info-value"><?php echo h($a["semester"]); ?></span></div></div>
                            <div class="col-6 col-md-4"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-book"></i>Subject</span><span class="sp-info-value"><?php echo !empty($a["subject_name"]) ? h($a["subject_name"]) : "All"; ?></span></div></div>
                            <div class="col-6 col-md-4"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-people"></i>Audience</span><span class="sp-info-value"><?php echo h(ucfirst($a["audience"] ?? "all")); ?></span></div></div>
                            <div class="col-6 col-md-4"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-calendar"></i>Published</span><span class="sp-info-value"><?php echo !empty($a["publish_at"]) ? date("d M Y, g:i A", strtotime($a["publish_at"])) : "—"; ?></span></div></div>
                            <div class="col-6 col-md-4"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-clock"></i>Expires</span><span class="sp-info-value"><?php echo !empty($a["expires_at"]) ? date("d M Y, g:i A", strtotime($a["expires_at"])) : "No expiry"; ?></span></div></div>
                        </div>
                        <?php if (!empty($a["exam_id"])) { ?>
                            <hr>
                            <h6 class="fw-bold mb-2"><i class="bi bi-journal-check me-1 text-primary"></i>Linked Examination</h6>
                            <div class="d-flex flex-wrap align-items-center gap-2 p-3 bg-light rounded-3">
                                <div class="flex-grow-1">
                                    <strong><?php echo h($a["exam_title"] ?? "Exam"); ?></strong><br>
                                    <small class="text-muted"><?php echo !empty($a["exam_date"]) ? date("d M Y", strtotime($a["exam_date"])) : ""; ?> &middot; <?php echo (int)($a["duration"] ?? 0); ?> mins &middot; <?php echo h($a["exam_mode"] ?? "Standard"); ?></small>
                                </div>
                                <a class="sp-btn sp-btn-soft" href="exam_view.php?id=<?php echo (int)$a["exam_id"]; ?>"><i class="bi bi-eye"></i> View Exam</a>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="col-xl-5 sp-fade-up" data-delay="1">
                    <div class="sp-panel h-100">
                        <div class="sp-panel-head"><h3><i class="bi bi-bar-chart me-2 text-success"></i>Delivery Analytics</h3></div>
                        <div class="row g-2 mb-3">
                            <div class="col-4"><div class="sp-info-item text-center"><span class="sp-info-label">Targeted</span><span class="sp-info-value fs-4"><?php echo $targeted; ?></span></div></div>
                            <div class="col-4"><div class="sp-info-item text-center"><span class="sp-info-label">Read</span><span class="sp-info-value fs-4 text-success"><?php echo $read; ?></span></div></div>
                            <div class="col-4"><div class="sp-info-item text-center"><span class="sp-info-label">Unread</span><span class="sp-info-value fs-4 text-danger"><?php echo $unread; ?></span></div></div>
                        </div>
                        <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Read rate</span><strong><?php echo $rate; ?>%</strong></div>
                        <div class="sp-track mb-3"><span style="width:<?php echo $rate; ?>%;"></span></div>
                        <h6 class="fw-bold mb-2">Recipients</h6>
                        <?php if (count($recipients) > 0) { ?>
                            <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
                            <table class="table table-sm table-hover align-middle sp-table mb-0">
                                <thead><tr><th>Student</th><th>Status</th><th>Read At</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recipients as $rc) { ?>
                                        <tr>
                                            <td><strong><?php echo h($rc["name"] ?? "Student"); ?></strong><br><small class="text-muted"><?php echo h($rc["email"] ?? ""); ?></small></td>
                                            <td><?php echo !empty($rc["read_at"]) ? '<span class="sp-pill is-completed">Read</span>' : '<span class="sp-pill is-upcoming">Unread</span>'; ?></td>
                                            <td><small class="text-muted"><?php echo !empty($rc["read_at"]) ? date("M j, g:i A", strtotime($rc["read_at"])) : "—"; ?></small></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted small mb-0">No recipients yet — publish the announcement to deliver it.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>
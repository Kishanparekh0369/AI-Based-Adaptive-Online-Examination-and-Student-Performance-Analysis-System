<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Announcements";
include("../app/layouts/student_header.php");

$student_id = (int)$_SESSION["student_id"];
$types = announcement_types();

// Mark single announcement as read (ownership verified via recipient row)
if (isset($_GET["view"])) {
    $vid = (int)$_GET["view"];
    $own = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ar.id FROM announcement_recipients ar INNER JOIN announcements a ON a.id=ar.announcement_id WHERE ar.announcement_id=$vid AND ar.student_id=$student_id AND a.status='Published' LIMIT 1"));
    if ($own) {
        mysqli_query($conn, "UPDATE announcement_recipients SET read_at=NOW() WHERE announcement_id=$vid AND student_id=$student_id AND read_at IS NULL");
        mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE user_type='student' AND user_id=$student_id AND link='announcements.php?view=$vid' AND is_read=0");
    }
}

// Mark all as read
if (isset($_GET["mark_all"])) {
    mysqli_query($conn, "UPDATE announcement_recipients ar INNER JOIN announcements a ON a.id=ar.announcement_id SET ar.read_at=NOW() WHERE ar.student_id=$student_id AND ar.read_at IS NULL AND a.status='Published'");
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE user_type='student' AND user_id=$student_id AND link LIKE 'announcements.php?view=%' AND is_read=0");
    redirect_alert("All announcements marked as read.", "announcements.php", "success");
}

$filter_type = isset($_GET["type"]) ? trim($_GET["type"]) : "";
if ($filter_type !== "" && !announcement_valid_type($filter_type)) { $filter_type = ""; }
$search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";

$where = " WHERE ar.student_id=$student_id AND a.status='Published' AND (a.publish_at IS NULL OR a.publish_at <= NOW()) AND (a.expires_at IS NULL OR a.expires_at > NOW()) ";
if ($filter_type !== "") { $where .= " AND a.type='" . mysqli_real_escape_string($conn, $filter_type) . "' "; }
if ($search !== "") { $s = mysqli_real_escape_string($conn, $search); $where .= " AND (a.title LIKE '%$s%' OR a.message LIKE '%$s%') "; }

$unread_total = student_unread_announcements($student_id);

$feed = mysqli_query($conn, "SELECT a.*, ar.read_at, s.subject_name, f.name AS faculty_name,
    e.exam_title, e.exam_date, e.duration, e.exam_mode
    FROM announcement_recipients ar
    INNER JOIN announcements a ON a.id=ar.announcement_id
    LEFT JOIN subjects s ON s.id=a.subject_id
    LEFT JOIN faculty f ON f.id=a.faculty_id
    LEFT JOIN exams e ON e.id=a.exam_id
    $where
    ORDER BY a.is_pinned DESC,
        CASE a.priority WHEN 'urgent' THEN 0 WHEN 'important' THEN 1 ELSE 2 END,
        a.publish_at DESC, a.created_at DESC");
$items = array();
if ($feed) { while ($r = mysqli_fetch_assoc($feed)) { $items[] = $r; } }

// Detail view (same scope guard)
$detail = null;
if (isset($_GET["view"])) {
    $vid = (int)$_GET["view"];
    foreach ($items as $it) {
        if ((int)$it["id"] === $vid) { $detail = $it; break; }
    }
    if (!$detail) {
        $chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT a.id FROM announcement_recipients ar INNER JOIN announcements a ON a.id=ar.announcement_id WHERE ar.announcement_id=$vid AND ar.student_id=$student_id LIMIT 1"));
        if (!$chk) { redirect_alert("This announcement is not available for your account.", "announcements.php", "warning"); }
    }
}
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-megaphone-fill"></i> Academic Updates</span>
                        <h1>Announcements <?php if ($unread_total > 0) { ?><span class="badge bg-danger ms-1" style="font-size:.8rem;vertical-align:middle;"><?php echo $unread_total; ?> new</span><?php } ?></h1>
                        <p>Stay updated with quizzes, examinations and important academic notices.</p>
                    </div>
                    <?php if ($unread_total > 0) { ?>
                        <a href="announcements.php?mark_all=1" class="sp-btn sp-btn-soft"><i class="bi bi-check2-all"></i> Mark All Read</a>
                    <?php } ?>
                </div>

                <form method="get" class="sp-filters sp-fade-up">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1" for="stAnnSearch">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control" id="stAnnSearch" name="search" value="<?php echo h($search); ?>" placeholder="Search announcements...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1" for="stAnnType">Type</label>
                            <select class="form-select" id="stAnnType" name="type">
                                <option value="">All Types</option>
                                <?php foreach ($types as $tk => $tv) { ?>
                                    <option value="<?php echo h($tk); ?>" <?php echo $filter_type === $tk ? "selected" : ""; ?>><?php echo h($tv["label"]); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="sp-btn sp-btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                    </div>
                    <?php if ($search !== "" || $filter_type !== "") { ?>
                        <div class="mt-2"><a href="announcements.php" class="small text-decoration-none"><i class="bi bi-x-circle me-1"></i>Clear filters</a></div>
                    <?php } ?>
                </form>

                <?php if ($detail) {
                    $dt = $types[$detail["type"]] ?? $types["general"];
                ?>
                    <!-- Detail view -->
                    <div class="sp-panel sp-fade-up mb-4">
                        <a href="announcements.php" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to all</a>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-2">
                            <span class="sp-pill is-available"><i class="bi <?php echo h($dt["icon"]); ?>"></i><?php echo h($dt["label"]); ?></span>
                            <?php if ((int)$detail["is_pinned"] === 1) { ?><span class="sp-pill is-upcoming"><i class="bi bi-pin-fill"></i>Pinned</span><?php } ?>
                            <?php if (($detail["priority"] ?? "normal") !== "normal") { ?><span class="sp-pill <?php echo $detail["priority"] === "urgent" ? "is-expired" : "is-upcoming"; ?>"><?php echo h(ucfirst($detail["priority"])); ?></span><?php } ?>
                            <span class="text-muted small ms-auto"><i class="bi bi-clock me-1"></i><?php echo date("d M Y, g:i A", strtotime($detail["publish_at"] ?? $detail["created_at"])); ?></span>
                        </div>
                        <h3 class="fw-bold"><?php echo h($detail["title"]); ?></h3>
                        <p class="text-muted small mb-2">By <?php echo h($detail["faculty_name"] ?? "Faculty"); ?> &middot; <?php echo h($detail["course"]); ?> &middot; <?php echo h($detail["semester"]); ?><?php echo !empty($detail["subject_name"]) ? " &middot; " . h($detail["subject_name"]) : ""; ?></p>
                        <p style="white-space:pre-line;line-height:1.75;"><?php echo h($detail["message"]); ?></p>
                        <?php if (!empty($detail["exam_id"])) { ?>
                            <div class="d-flex flex-wrap align-items-center gap-2 p-3 rounded-3 border bg-light mt-3">
                                <div class="flex-grow-1">
                                    <strong><?php echo h($detail["exam_title"] ?? "Linked Exam"); ?></strong><br>
                                    <small class="text-muted"><?php echo !empty($detail["exam_date"]) ? date("d M Y", strtotime($detail["exam_date"])) : ""; ?> &middot; <?php echo (int)($detail["duration"] ?? 0); ?> mins &middot; <?php echo h($detail["exam_mode"] ?? "Standard"); ?></small>
                                </div>
                                <a class="sp-btn sp-btn-primary" href="<?php echo (($detail["exam_mode"] ?? "Standard") === "Adaptive" ? "adaptive_exam.php" : "start_exam.php") . "?id=" . (int)$detail["exam_id"]; ?>"><i class="bi bi-play-circle"></i> View Exam</a>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <!-- Feed -->
                <div class="row g-3" id="stAnnGrid">
                    <?php if (count($items) > 0) { $i = 0; foreach ($items as $it) {
                        $i++;
                        $dt = $types[$it["type"]] ?? $types["general"];
                        $is_unread = empty($it["read_at"]);
                    ?>
                        <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min(($i % 6) + 1, 6); ?>">
                            <div class="sp-exam-card h-100 <?php echo $is_unread ? "sp-unread-card" : ""; ?>">
                                <div class="sp-exam-head">
                                    <span class="sp-pill is-available"><i class="bi <?php echo h($dt["icon"]); ?>"></i><?php echo h($dt["label"]); ?></span>
                                    <span class="d-flex gap-1">
                                        <?php if ((int)$it["is_pinned"] === 1) { ?><span class="sp-pill is-upcoming" title="Pinned"><i class="bi bi-pin-fill"></i></span><?php } ?>
                                        <?php if ($is_unread) { ?><span class="sp-unread-dot" title="Unread" aria-label="Unread"></span><?php } ?>
                                    </span>
                                </div>
                                <h3 class="sp-exam-title"><?php echo h($it["title"]); ?></h3>
                                <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo h(mb_substr($it["message"], 0, 130)); ?></p>
                                <div class="sp-exam-meta" style="grid-template-columns:1fr 1fr;">
                                    <div class="sp-meta-item"><i class="bi bi-book"></i><span><?php echo h($it["subject_name"] ?? "General"); ?></span></div>
                                    <div class="sp-meta-item"><i class="bi bi-mortarboard"></i><span><?php echo h($it["course"]); ?> &middot; <?php echo h($it["semester"]); ?></span></div>
                                    <div class="sp-meta-item"><i class="bi bi-calendar3"></i><span><?php echo date("d M Y", strtotime($it["publish_at"] ?? $it["created_at"])); ?></span></div>
                                    <div class="sp-meta-item"><i class="bi bi-person"></i><span><?php echo h($it["faculty_name"] ?? "Faculty"); ?></span></div>
                                </div>
                                <div class="sp-exam-foot">
                                    <span class="small text-muted"><?php echo $is_unread ? "New" : "Read"; ?></span>
                                    <a class="sp-btn <?php echo $is_unread ? "sp-btn-primary" : "sp-btn-soft"; ?>" href="announcements.php?view=<?php echo (int)$it["id"]; ?>">View Details <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php } } else { ?>
                        <div class="col-12">
                            <div class="sp-empty">
                                <div class="sp-empty-icon"><i class="bi bi-megaphone"></i></div>
                                <h4>No New Announcements</h4>
                                <p>You're all caught up. New academic announcements will appear here.</p>
                            </div>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>
</div>
<style>
.sp-unread-card { border-color: rgba(37, 99, 235, 0.35) !important; box-shadow: 0 8px 24px -10px rgba(37, 99, 235, 0.35); }
.sp-unread-dot { width: 10px; height: 10px; border-radius: 50%; background: #2563EB; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18); display: inline-block; animation: spPulse 2s ease infinite; }
@keyframes spPulse { 0%,100% { box-shadow: 0 0 0 4px rgba(37,99,235,.2);} 50% { box-shadow: 0 0 0 7px rgba(37,99,235,.06);} }
@media (prefers-reduced-motion: reduce) { .sp-unread-dot { animation: none; } }
</style>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>
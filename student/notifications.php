<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Notifications";
include("../app/layouts/student_header.php");

$student_id = (int)$_SESSION["student_id"];

if (isset($_GET["mark_all"])) {
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE user_type='student' AND user_id=$student_id AND is_read=0");
    mysqli_query($conn, "UPDATE announcement_recipients ar INNER JOIN announcements a ON a.id=ar.announcement_id SET ar.read_at=NOW() WHERE ar.student_id=$student_id AND ar.read_at IS NULL");
    redirect_alert("All notifications marked as read.", "notifications.php", "success");
}
if (isset($_GET["read"])) {
    $nid = (int)$_GET["read"];
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE id=$nid AND user_type='student' AND user_id=$student_id");
    header("Location: notifications.php");
    exit;
}

$tab = isset($_GET["tab"]) ? trim($_GET["tab"]) : "all";
if (!in_array($tab, array("all", "unread", "read"), true)) { $tab = "all"; }

$where = " WHERE user_type='student' AND user_id=$student_id ";
if ($tab === "unread") { $where .= " AND is_read=0 "; }
if ($tab === "read") { $where .= " AND is_read=1 "; }

$notes = mysqli_query($conn, "SELECT * FROM notifications $where ORDER BY id DESC LIMIT 100");
$items = array();
if ($notes) { while ($r = mysqli_fetch_assoc($notes)) { $items[] = $r; } }
$unread_n = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_type='student' AND user_id=$student_id AND is_read=0"))["c"] ?? 0);
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-bell-fill"></i> Inbox</span>
                        <h1>Notifications <?php if ($unread_n > 0) { ?><span class="badge bg-danger ms-1" style="font-size:.8rem;vertical-align:middle;"><?php echo $unread_n; ?></span><?php } ?></h1>
                        <p>Announcements, exam updates and system messages for your account.</p>
                    </div>
                    <?php if ($unread_n > 0) { ?>
                        <a href="notifications.php?mark_all=1" class="sp-btn sp-btn-soft"><i class="bi bi-check2-all"></i> Mark All as Read</a>
                    <?php } ?>
                </div>

                <div class="sp-filters sp-fade-up">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="notifications.php?tab=all" class="sp-btn <?php echo $tab === "all" ? "sp-btn-primary" : "sp-btn-ghost"; ?>"><i class="bi bi-inbox"></i> All</a>
                        <a href="notifications.php?tab=unread" class="sp-btn <?php echo $tab === "unread" ? "sp-btn-primary" : "sp-btn-ghost"; ?>"><i class="bi bi-envelope"></i> Unread<?php if ($unread_n > 0) { ?> (<?php echo $unread_n; ?>)<?php } ?></a>
                        <a href="notifications.php?tab=read" class="sp-btn <?php echo $tab === "read" ? "sp-btn-primary" : "sp-btn-ghost"; ?>"><i class="bi bi-envelope-open"></i> Read</a>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <?php if (count($items) > 0) { foreach ($items as $n) {
                        $is_unread = ((int)$n["is_read"] === 0);
                        $link = trim($n["link"] ?? "#");
                        if ($link === "" || $link === "#") { $link = "notifications.php?read=" . (int)$n["id"]; }
                    ?>
                        <a href="<?php echo h($link); ?>" class="sp-panel sp-fade-up text-decoration-none d-flex align-items-start gap-3 <?php echo $is_unread ? "sp-unread-card" : ""; ?>" style="padding:16px 18px;color:inherit;">
                            <span class="sp-stat-icon" style="width:42px;height:42px;font-size:1.1rem;flex-shrink:0;"><i class="bi <?php echo $is_unread ? "bi-bell-fill" : "bi-bell"; ?>"></i></span>
                            <span class="flex-grow-1 min-w-0">
                                <strong class="d-block"><?php echo h($n["title"]); ?><?php if ($is_unread) { ?> <span class="sp-unread-dot ms-1" style="vertical-align:middle;" aria-label="Unread"></span><?php } ?></strong>
                                <small class="text-muted d-block" style="line-height:1.6;"><?php echo h(mb_substr($n["message"], 0, 220)); ?></small>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date("d M Y, g:i A", strtotime($n["created_at"])); ?></small>
                            </span>
                            <?php if ($is_unread) { ?>
                                <span class="sp-pill is-available flex-shrink-0">New</span>
                            <?php } ?>
                        </a>
                    <?php } } else { ?>
                        <div class="sp-empty">
                            <div class="sp-empty-icon"><i class="bi bi-bell-slash"></i></div>
                            <h4><?php echo $tab === "unread" ? "No Unread Notifications" : "No Notifications"; ?></h4>
                            <p><?php echo $tab === "unread" ? "You're all caught up." : "System messages and announcement alerts will appear here."; ?></p>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>
</div>
<style>
.sp-unread-card { border-color: rgba(37, 99, 235, 0.35) !important; }
.sp-unread-dot { width: 9px; height: 9px; border-radius: 50%; background: #2563EB; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18); display: inline-block; }
</style>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>

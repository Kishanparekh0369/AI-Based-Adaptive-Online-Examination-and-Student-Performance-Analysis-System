<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Contact Messages";

if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    mysqli_query($conn, "DELETE FROM contact_messages WHERE id=$id");
    redirect_alert("Contact message deleted successfully", "contact_messages.php");
}

if (isset($_GET["read"])) {
    $id = (int)clean_data($_GET["read"]);
    mysqli_query($conn, "UPDATE contact_messages SET status='Read' WHERE id=$id");
    redirect_alert("Contact message marked as read", "contact_messages.php");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)clean_data($_POST["message_id"]);
    $reply = clean_data($_POST["reply"]);
    if ($reply == "") {
        redirect_alert("Reply message is required", "contact_messages.php");
    }
    $reply_safe = mysqli_real_escape_string($conn, $reply);
    mysqli_query($conn, "UPDATE contact_messages SET reply='$reply_safe', status='Replied' WHERE id=$id");
    redirect_alert("Reply saved successfully", "contact_messages.php");
}

include("../app/layouts/admin_header.php");

// ---- Filters (validated server-side) ----
$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_status = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "";
if (!in_array($f_status, array("", "Unread", "Read", "Replied"), true)) { $f_status = ""; }
$f_page = max(1, (int)($_GET["page"] ?? 1));
$f_per = 10;

$conds = array(); $types = ""; $params = array();
if ($f_search !== "") {
    $like = "%" . $f_search . "%";
    $conds[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $types .= "ssss"; array_push($params, $like, $like, $like, $like);
}
if ($f_status !== "") { $conds[] = "status = ?"; $types .= "s"; $params[] = $f_status; }
$where_sql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

function admin_cm_all($conn, $sql, $types, $params)
{
    $out = array();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $out; }
    if ($types !== "" && !empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    mysqli_stmt_close($stmt);
    return $out;
}

$count_rows = admin_cm_all($conn, "SELECT COUNT(*) AS c FROM contact_messages $where_sql", $types, $params);
$total_rows = $count_rows ? (int)$count_rows[0]["c"] : 0;
$total_pages = max(1, (int)ceil($total_rows / $f_per));
if ($f_page > $total_pages) { $f_page = $total_pages; }
$offset = ($f_page - 1) * $f_per;
$rows = admin_cm_all($conn, "SELECT * FROM contact_messages $where_sql ORDER BY id DESC LIMIT $f_per OFFSET $offset", $types, $params);

// ---- Summary stats (real counts) ----
$stat_total = (int)(admin_cm_all($conn, "SELECT COUNT(*) AS c FROM contact_messages", "", array())[0]["c"] ?? 0);
$stat_unread = (int)(admin_cm_all($conn, "SELECT COUNT(*) AS c FROM contact_messages WHERE status='Unread'", "", array())[0]["c"] ?? 0);
$stat_read = (int)(admin_cm_all($conn, "SELECT COUNT(*) AS c FROM contact_messages WHERE status='Read'", "", array())[0]["c"] ?? 0);
$stat_replied = (int)(admin_cm_all($conn, "SELECT COUNT(*) AS c FROM contact_messages WHERE status='Replied'", "", array())[0]["c"] ?? 0);

$f_params = array("search" => $f_search, "status" => $f_status);
$f_chips = array();
if ($f_search !== "") { $f_chips[] = array("Search: " . $f_search, "contact_messages.php" . admin_qs($f_params, array("search" => "", "page" => 1))); }
if ($f_status !== "") { $f_chips[] = array("Status: " . $f_status, "contact_messages.php" . admin_qs($f_params, array("status" => "", "page" => 1))); }
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <?php admin_page_head("Contact Messages", "bi-envelope-paper", "Communication", "Contact Messages", "Read, reply to, and manage incoming contact messages."); ?>

            <?php
            admin_stat_cards(array(
                array("Total Messages", $stat_total, "bi-envelope-paper-fill", "blue", "All submissions"),
                array("Unread", $stat_unread, "bi-envelope-exclamation-fill", "rose", "Needs attention"),
                array("Read", $stat_read, "bi-envelope-open-fill", "cyan", "Reviewed"),
                array("Replied", $stat_replied, "bi-reply-all-fill", "green", "Answered")
            ));
            ?>

            <!-- Search + Filters -->
            <form method="get" class="toolbar-card mb-4 sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1" for="fSearch">Search Messages</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="fSearch" name="search" value="<?php echo h($f_search); ?>" class="form-control" placeholder="Name, email, subject or message...">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fStatus">Status</label>
                        <select id="fStatus" name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Unread" <?php if ($f_status === "Unread") echo "selected"; ?>>Unread</option>
                            <option value="Read" <?php if ($f_status === "Read") echo "selected"; ?>>Read</option>
                            <option value="Replied" <?php if ($f_status === "Replied") echo "selected"; ?>>Replied</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                        <a class="btn btn-outline-secondary" href="contact_messages.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <?php admin_chips($f_chips, "contact_messages.php"); ?>
            </form>

            <!-- Messages Table -->
            <div class="table-card p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)) { foreach ($rows as $msg) {
                            $mid = (int)$msg["id"];
                            $full = (string)($msg["message"] ?? "");
                            $short = mb_strlen($full) > 80 ? mb_substr($full, 0, 80) . "…" : $full;
                        ?>
                            <tr class="<?php echo ($msg["status"] ?? "") === "Unread" ? "fw-semibold" : ""; ?>">
                                <td data-label="Sender"><div class="admin-cell-main"><strong><?php echo h($msg["name"]); ?></strong><small class="text-muted"><?php echo h($msg["email"]); ?></small></div></td>
                                <td data-label="Subject"><?php echo h($msg["subject"]); ?></td>
                                <td data-label="Message"><span title="<?php echo h($full); ?>"><?php echo h($short); ?></span></td>
                                <td data-label="Date"><small class="text-muted"><?php echo !empty($msg["created_at"]) ? h(date("d M Y, h:i A", strtotime($msg["created_at"]))) : "-"; ?></small></td>
                                <td data-label="Status"><?php echo admin_badge($msg["status"]); ?></td>
                                <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $mid; ?>" title="View and reply" aria-label="View and reply"><i class="bi bi-reply"></i></button>
                                    <?php if (($msg["status"] ?? "") === "Unread") { ?>
                                    <a class="btn btn-sm btn-info" href="contact_messages.php?read=<?php echo $mid; ?>" title="Mark as read" aria-label="Mark as read"><i class="bi bi-envelope-open"></i></a>
                                    <?php } ?>
                                    <a class="btn btn-sm btn-danger js-confirm-delete" href="contact_messages.php?delete=<?php echo $mid; ?>" title="Delete" aria-label="Delete message"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } } else {
                            admin_empty_row(6, "bi-envelope-paper", "No contact messages found.", "Try changing your filters. New visitor messages will appear here.");
                        } ?>
                    </tbody>
                </table>
                </div>
                <?php admin_pagination($total_rows, $f_page, $f_per, $f_params, "contact_messages.php"); ?>
            </div>

            <!-- View + Reply Modals -->
            <?php foreach ($rows as $msg) {
                $mid = (int)$msg["id"];
            ?>
                <div class="modal fade" id="replyModal<?php echo $mid; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <form method="post" class="modal-content professional-modal">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold"><i class="bi bi-reply me-2 text-primary"></i>Reply to <?php echo h($msg["name"]); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="message_id" value="<?php echo $mid; ?>">
                                <div class="admin-detail-grid mb-3">
                                    <div class="admin-detail-item"><small>From</small><strong><?php echo h($msg["name"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Email</small><strong><?php echo h($msg["email"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Subject</small><strong><?php echo h($msg["subject"]); ?></strong></div>
                                    <div class="admin-detail-item"><small>Received</small><strong><?php echo !empty($msg["created_at"]) ? h(date("d M Y, h:i A", strtotime($msg["created_at"]))) : "-"; ?></strong></div>
                                    <div class="admin-detail-item" style="grid-column:1/-1;"><small>Message</small><strong style="font-weight:500;line-height:1.6;"><?php echo h((string)($msg["message"] ?? "")); ?></strong></div>
                                    <?php if (!empty($msg["reply"])) { ?>
                                    <div class="admin-detail-item" style="grid-column:1/-1;"><small>Saved Reply</small><strong style="font-weight:500;line-height:1.6;"><?php echo h((string)$msg["reply"]); ?></strong></div>
                                    <?php } ?>
                                </div>
                                <div class="form-floating">
                                    <textarea class="form-control" name="reply" id="replyBox<?php echo $mid; ?>" placeholder="Write your reply" style="min-height:110px;" required><?php echo h((string)($msg["reply"] ?? "")); ?></textarea>
                                    <label for="replyBox<?php echo $mid; ?>">Write your reply</label>
                                </div>
                                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Saving stores the reply and marks this message as Replied.</small>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button class="btn btn-gradient" type="submit"><i class="bi bi-send me-1"></i>Save Reply</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

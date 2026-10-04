<?php if (isset($faculty_notifications) && $faculty_notifications) { ?>
<div class="modal fade" id="fpNotifModal" tabindex="-1" aria-hidden="true" aria-labelledby="fpNotifModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content sp-notif-modal">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="fpNotifModalLabel"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications<?php if ($faculty_notifications["count"] > 0) { ?> <span class="badge bg-primary ms-1" data-notif-badge><?php echo $faculty_notifications["count"]; ?></span><?php } ?></h6>
                <button type="button" class="sp-btn sp-btn-ghost ms-auto me-2" style="min-height:32px;padding:4px 12px;font-size:.75rem;" data-notif-clear title="Mark all as read"><i class="bi bi-check2-all"></i> Mark all read</button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($faculty_notifications["items"] && mysqli_num_rows($faculty_notifications["items"]) > 0) { while ($note = mysqli_fetch_assoc($faculty_notifications["items"])) { ?>
                    <a class="notification-item <?php echo $note["is_read"] ? "" : "unread"; ?>" href="<?php echo $note["link"]; ?>">
                        <span class="notification-dot" aria-hidden="true"></span>
                        <span class="notification-copy">
                            <strong><?php echo htmlspecialchars($note["title"]); ?></strong>
                            <small><?php echo htmlspecialchars($note["message"]); ?></small>
                            <span class="notification-time"><?php echo date("M j, g:i A", strtotime($note["created_at"])); ?></span>
                        </span>
                    </a>
                <?php }} else { ?>
                    <div class="notification-empty"><i class="bi bi-bell-slash d-block mb-2" style="font-size:1.6rem;"></i>No notifications yet.</div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<script>
/* Bell notifications: auto mark-read on open + live badge (no manual refresh). */
document.addEventListener("DOMContentLoaded", function () {
    var modal = document.getElementById("fpNotifModal");
    if (!modal) { return; }
    var done = false;
    function clearNotifs() {
        fetch(location.pathname + "?mark_notifications=1", { headers: { "X-Requested-With": "fetch" }, credentials: "same-origin" })
            .then(function () {
                document.querySelectorAll("[data-notif-badge]").forEach(function (b) { b.remove(); });
                document.querySelectorAll("#fpNotifModal .notification-item.unread").forEach(function (n) { n.classList.remove("unread"); });
                var bell = document.querySelector('[data-bs-target="#fpNotifModal"] .badge');
                if (bell) { bell.remove(); }
            });
    }
    modal.addEventListener("shown.bs.modal", function () { if (!done) { done = true; clearNotifs(); } });
    modal.querySelectorAll("[data-notif-clear]").forEach(function (b) { b.addEventListener("click", function () { done = true; clearNotifs(); }); });
});
</script>
<button class="scroll-top-btn" id="scrollTopBtn" type="button"><i class="bi bi-arrow-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>js/charts.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/charts.js'); ?>"></script>
<script src="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>js/script.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/script.js'); ?>"></script>
<script src="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>js/student.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/student.js'); ?>"></script>
<script src="<?php echo isset($asset_path) ? $asset_path : '../assets/'; ?>js/faculty.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/faculty.js'); ?>"></script>
<?php render_flash_alert(); ?>
<?php render_login_welcome(); ?>
</body>
</html>

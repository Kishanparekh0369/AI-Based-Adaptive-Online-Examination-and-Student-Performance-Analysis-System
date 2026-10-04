<?php
// Student AI Assistant widget — included once via student_footer.php.
// Identity is resolved server-side from the session; never from the client.
// Reuses $sp_top_name / $spai photo vars from student_header.php when available.
$spai_full_name = "Student";
if (isset($sp_top_name) && trim((string)$sp_top_name) !== "") {
    $spai_full_name = trim((string)$sp_top_name);
} elseif (isset($_SESSION["student_name"])) {
    $spai_full_name = trim((string)$_SESSION["student_name"]);
}
$spai_first = "Student";
$spai_parts = preg_split('/\s+/', $spai_full_name);
if (!empty($spai_parts[0])) { $spai_first = $spai_parts[0]; }

$spai_photo = "";
if (isset($sp_top_photo) && $sp_top_photo !== "") {
    $spai_photo = (string)$sp_top_photo;
} elseif (isset($_SESSION["student_id"]) && isset($conn)) {
    $spai_stmt = mysqli_prepare($conn, "SELECT profile_image FROM students WHERE id=? LIMIT 1");
    if ($spai_stmt) {
        $spai_sid = (int)$_SESSION["student_id"];
        mysqli_stmt_bind_param($spai_stmt, "i", $spai_sid);
        mysqli_stmt_execute($spai_stmt);
        $spai_res = mysqli_stmt_get_result($spai_stmt);
        if ($spai_res && $spai_row = mysqli_fetch_assoc($spai_res)) {
            if (!empty($spai_row["profile_image"]) && file_exists(__DIR__ . "/../../assets/uploads/profile/" . $spai_row["profile_image"])) {
                $spai_photo = "../assets/uploads/profile/" . $spai_row["profile_image"];
            }
        }
        mysqli_stmt_close($spai_stmt);
    }
}
$spai_initials = "S";
if ($spai_full_name !== "") {
    $spai_bits = preg_split('/\s+/', $spai_full_name);
    $spai_initials = strtoupper(mb_substr($spai_bits[0], 0, 1) . (isset($spai_bits[1]) ? mb_substr($spai_bits[1], 0, 1) : ""));
}
?>
<button type="button" class="spai-fab" id="spaiFab" aria-label="Open Student AI Assistant" aria-expanded="false" title="Ask Student AI">
    <i class="bi bi-robot" aria-hidden="true"></i>
    <span class="spai-tip" aria-hidden="true">Ask Student AI</span>
</button>

<section class="spai-panel" id="spaiPanel" aria-hidden="true" aria-label="Student AI Assistant chat"
    data-student-name="<?php echo h($spai_first); ?>"
    data-student-fullname="<?php echo h($spai_full_name); ?>"
    data-student-photo="<?php echo h($spai_photo); ?>"
    data-student-initials="<?php echo h($spai_initials); ?>">
    <header class="spai-head">
        <span class="spai-head-avatar" aria-hidden="true"><i class="bi bi-robot"></i></span>
        <div class="spai-head-copy">
            <strong>Student AI Assistant</strong>
            <small class="spai-head-sub" title="<?php echo h($spai_full_name); ?>">Personalized for <?php echo h($spai_full_name); ?></small>
            <span class="spai-status" id="spaiStatus"><span class="dot" aria-hidden="true"></span>AI Online</span>
        </div>
        <button type="button" class="spai-head-btn" id="spaiNew" title="New chat" aria-label="Start new chat"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
        <button type="button" class="spai-head-btn" id="spaiMin" title="Minimize" aria-label="Minimize Student AI Assistant"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
        <button type="button" class="spai-head-btn" id="spaiClose" title="Close" aria-label="Close Student AI Assistant"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </header>

    <div class="spai-body" id="spaiBody" role="log" aria-live="polite" aria-label="Conversation"></div>

    <div class="spai-chips" id="spaiChips" aria-label="Quick actions"></div>

    <div class="spai-inputbar">
        <textarea id="spaiInput" rows="1" maxlength="1000" placeholder="Ask about your exams, results or performance..." aria-label="Type your message"></textarea>
        <button type="button" class="spai-send" id="spaiSend" aria-label="Send message"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
    </div>
</section>

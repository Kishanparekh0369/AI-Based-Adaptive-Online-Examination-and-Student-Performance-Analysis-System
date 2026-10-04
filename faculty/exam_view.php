<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Exam Details";

$faculty_id = (int)$_SESSION["faculty_id"];
$exam_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($exam_id <= 0) {
    redirect_alert("Invalid exam selected.", "exams.php", "error");
}

$stmt = mysqli_prepare($conn, "SELECT e.*, s.subject_name, s.subject_code FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE e.id=? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $exam_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exam = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} else {
    $exam = null;
}

if (!$exam || !faculty_has_subject($faculty_id, (int)$exam["subject_id"])) {
    redirect_alert("Access Denied: You cannot view exams for unauthorized subjects.", "exams.php", "error");
}

$is_adaptive = ($exam["exam_mode"] ?? "Standard") === "Adaptive";
$is_active = ($exam["status"] ?? "") === "Active";

// Linked questions (standard mode detail)
$linked = array();
$qres = mysqli_query($conn, "SELECT q.* FROM exam_questions eq INNER JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=$exam_id ORDER BY eq.id ASC");
if ($qres) {
    while ($qr = mysqli_fetch_assoc($qres)) { $linked[] = $qr; }
}

// Attempt statistics
$stats = array("attempts" => 0, "avg" => 0, "best" => 0, "passed" => 0);
$sres = mysqli_query($conn, "SELECT COUNT(*) AS c, AVG(percentage) AS a, MAX(percentage) AS b, SUM(status='Pass') AS p FROM results WHERE exam_id=$exam_id");
if ($sres && $srow = mysqli_fetch_assoc($sres)) {
    $stats["attempts"] = (int)$srow["c"];
    $stats["avg"] = round((float)($srow["a"] ?? 0), 1);
    $stats["best"] = round((float)($srow["b"] ?? 0), 1);
    $stats["passed"] = (int)($srow["p"] ?? 0);
}

// Recent attempts (latest 10)
$recent = array();
$rres = mysqli_query($conn, "SELECT r.percentage, r.status, r.submitted_at, st.name AS student_name, st.course, st.semester FROM results r LEFT JOIN students st ON st.id=r.student_id WHERE r.exam_id=$exam_id ORDER BY r.id DESC LIMIT 10");
if ($rres) {
    while ($rr = mysqli_fetch_assoc($rres)) { $recent[] = $rr; }
}

// Academic scope summary
$ev_course = trim($exam["course"] ?? "");
$ev_semester = trim($exam["semester"] ?? "");
$ev_scope_ok = ($ev_course !== "" && $ev_semester !== "");
$ev_eligible = $ev_scope_ok ? exam_eligible_student_count($ev_course, $ev_semester) : 0;
$ev_pass_rate = $stats["attempts"] > 0 ? round(($stats["passed"] / $stats["attempts"]) * 100, 1) : 0;

include("../app/layouts/faculty_header.php");
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb dashboard-breadcrumb">
                        <li class="breadcrumb-item">Faculty</li>
                        <li class="breadcrumb-item"><a href="exams.php">Exams</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </nav>

                <!-- HERO -->
                <div class="sp-profile-hero sp-fade-up">
                    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
                        <span class="sp-profile-initials" aria-hidden="true"><i class="bi bi-journal-check"></i></span>
                        <div class="flex-grow-1">
                            <h2><?php echo h($exam["exam_title"]); ?></h2>
                            <p class="sp-profile-sub"><?php echo h($exam["subject_name"] ?? "General"); ?><?php echo !empty($exam["subject_code"]) ? " (" . h($exam["subject_code"]) . ")" : ""; ?> &middot; <?php echo $is_adaptive ? "AI Adaptive" : "Standard"; ?></p>
                            <div class="sp-profile-pills">
                                <span class="sp-hero-pill"><i class="bi bi-calendar3"></i><?php echo date("d M Y", strtotime($exam["exam_date"])); ?></span>
                                <span class="sp-hero-pill"><i class="bi bi-stopwatch"></i><?php echo (int)$exam["duration"]; ?> mins</span>
                                <span class="sp-hero-pill"><i class="bi bi-award"></i><?php echo (int)$exam["pass_marks"]; ?> / <?php echo (int)$exam["total_marks"]; ?> to pass</span>
                                <span class="sp-pill <?php echo $is_active ? "is-completed" : "is-expired"; ?>"><?php echo h($exam["status"]); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="sp-btn sp-btn-primary" href="exams.php?edit=<?php echo $exam_id; ?>"><i class="bi bi-pencil"></i> Edit Exam</a>
                        <a class="sp-btn sp-btn-soft" href="exams.php?clone=<?php echo $exam_id; ?>" data-fp-clone-link><i class="bi bi-copy"></i> Duplicate</a>
                        <a class="sp-btn sp-btn-ghost" href="results.php"><i class="bi bi-trophy"></i> All Results</a>
                    </div>
                </div>

                <!-- ACADEMIC SCOPE -->
                <div class="sp-panel mb-4 sp-fade-up">
                    <div class="sp-panel-head">
                        <h3><i class="bi bi-mortarboard me-2 text-primary"></i>Academic Scope</h3>
                        <?php if ($ev_scope_ok) { ?>
                            <span class="sp-pill is-available"><i class="bi bi-check2-circle"></i>Configured</span>
                        <?php } else { ?>
                            <span class="sp-pill is-expired"><i class="bi bi-exclamation-triangle"></i>Not configured</span>
                        <?php } ?>
                    </div>
                    <?php if ($ev_scope_ok) { ?>
                        <div class="row g-2">
                            <div class="col-6 col-md-2"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-mortarboard"></i>Course</span><span class="sp-info-value"><?php echo h($ev_course); ?></span></div></div>
                            <div class="col-6 col-md-2"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-layers"></i>Semester</span><span class="sp-info-value"><?php echo h($ev_semester); ?></span></div></div>
                            <div class="col-6 col-md-2"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-book"></i>Subject</span><span class="sp-info-value"><?php echo h($exam["subject_name"] ?? "General"); ?></span></div></div>
                            <div class="col-6 col-md-2"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-people"></i>Eligible</span><span class="sp-info-value"><?php echo (int)$ev_eligible; ?> students</span></div></div>
                            <div class="col-6 col-md-2"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-check2-circle"></i>Attempts</span><span class="sp-info-value"><?php echo (int)$stats["attempts"]; ?></span></div></div>
                            <div class="col-6 col-md-2"><div class="sp-info-item"><span class="sp-info-label"><i class="bi bi-graph-up"></i>Pass Rate</span><span class="sp-info-value"><?php echo $ev_pass_rate; ?>%</span></div></div>
                        </div>
                    <?php } else { ?>
                        <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Academic Scope Not Configured — this exam is hidden from students. Edit the exam and set its Course + Semester to publish it to the right class.</p>
                    <?php } ?>
                </div>

                <!-- STATS -->
                <?php
                $fp_q_total = $is_adaptive ? (int)($exam["max_questions"] ?? 0) : count($linked);
                $fp_has_attempts = $stats["attempts"] > 0;
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="1">
                        <div class="sp-stat">
                            <span class="sp-stat-icon"><i class="bi bi-patch-question"></i></span>
                            <div class="sp-stat-value"><?php echo $fp_q_total > 0 ? $fp_q_total : "—"; ?></div>
                            <div class="sp-stat-label"><?php echo $is_adaptive ? "Max Questions" : "Linked Questions"; ?></div>
                            <div class="sp-stat-foot"><?php echo $is_adaptive ? "Adaptive CAT engine" : "Fixed question set"; ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="2">
                        <div class="sp-stat is-info">
                            <span class="sp-stat-icon"><i class="bi bi-people"></i></span>
                            <div class="sp-stat-value" data-count="<?php echo $stats["attempts"]; ?>">0</div>
                            <div class="sp-stat-label">Student Attempts</div>
                            <div class="sp-stat-foot"><?php echo $fp_has_attempts ? "Across all students" : "No attempts yet"; ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="3">
                        <div class="sp-stat is-success">
                            <span class="sp-stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
                            <div class="sp-stat-value"><?php echo $fp_has_attempts ? $stats["avg"] . "%" : "—"; ?></div>
                            <div class="sp-stat-label">Average Score</div>
                            <div class="sp-stat-foot"><?php echo $fp_has_attempts ? "Mean of attempts" : "Awaiting first attempt"; ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 sp-fade-up" data-delay="4">
                        <div class="sp-stat is-warning">
                            <span class="sp-stat-icon"><i class="bi bi-trophy"></i></span>
                            <div class="sp-stat-value"><?php echo $fp_has_attempts ? $stats["best"] . "%" : "—"; ?></div>
                            <div class="sp-stat-label">Best Score</div>
                            <div class="sp-stat-foot"><?php echo $fp_has_attempts ? $stats["passed"] . " passed" : "No results yet"; ?></div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- QUESTIONS -->
                    <div class="col-xl-7 sp-fade-up">
                        <div class="sp-panel h-100">
                            <div class="sp-panel-head">
                                <h3><i class="bi bi-list-check me-2 text-primary"></i><?php echo $is_adaptive ? "Adaptive Engine" : "Linked Questions (" . count($linked) . ")"; ?></h3>
                                <?php if (!$is_adaptive) { ?><a href="questions.php?subject_id=<?php echo (int)$exam["subject_id"]; ?>" class="sp-btn sp-btn-ghost"><i class="bi bi-plus-circle"></i> Bank</a><?php } ?>
                            </div>
                            <?php if ($is_adaptive) { ?>
                                <p class="text-muted small mb-0"><i class="bi bi-robot me-1 text-primary"></i>This exam adapts difficulty in real time (CAT engine, up to <?php echo (int)($exam["max_questions"] ?? 10); ?> questions). No fixed question list — review overall difficulty mix in the Question Bank.</p>
                                <div class="mt-3">
                                    <a class="sp-btn sp-btn-soft" href="questions.php?subject_id=<?php echo (int)$exam["subject_id"]; ?>"><i class="bi bi-patch-question"></i> Open Subject Bank</a>
                                </div>
                            <?php } elseif (count($linked) > 0) { ?>
                                <div class="d-flex flex-column gap-2" style="max-height:420px;overflow-y:auto;">
                                    <?php $qi = 0; foreach ($linked as $lq) { $qi++; ?>
                                        <div class="p-2 rounded-3 border bg-light">
                                            <div class="d-flex justify-content-between gap-2">
                                                <strong class="small">Q<?php echo $qi; ?>. <?php echo h(mb_substr($lq["question"], 0, 110)); ?><?php echo mb_strlen($lq["question"]) > 110 ? "..." : ""; ?></strong>
                                                <span class="sp-pill <?php echo ($lq["difficulty"] ?? "") === "Easy" ? "is-completed" : ((($lq["difficulty"] ?? "") === "Hard") ? "is-expired" : "is-upcoming"); ?>" style="font-size:.62rem;"><?php echo h($lq["difficulty"] ?? "Medium"); ?></span>
                                            </div>
                                            <small class="text-muted">Answer: <strong class="text-success"><?php echo h($lq["correct_answer"]); ?></strong> &middot; <?php echo (int)$lq["marks"]; ?> mark<?php echo (int)$lq["marks"] === 1 ? "" : "s"; ?></small>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="sp-empty">
                                    <div class="sp-empty-icon"><i class="bi bi-patch-question"></i></div>
                                    <h4>No Questions Linked</h4>
                                    <p>Edit this exam and select questions from the bank.</p>
                                    <a href="exams.php?edit=<?php echo $exam_id; ?>" class="sp-btn sp-btn-primary"><i class="bi bi-pencil"></i> Edit Exam</a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- RECENT ATTEMPTS -->
                    <div class="col-xl-5 sp-fade-up" data-delay="1">
                        <div class="sp-panel h-100">
                            <div class="sp-panel-head">
                                <h3><i class="bi bi-clock-history me-2 text-primary"></i>Recent Attempts</h3>
                                <a href="results.php" class="small text-decoration-none">All results</a>
                            </div>
                            <?php if (count($recent) > 0) { ?>
                                <div class="table-responsive">
                                <table class="table table-hover align-middle sp-table mb-0">
                                    <thead><tr><th>Student</th><th>Score</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($recent as $ra) { ?>
                                            <tr>
                                                <td data-label="Student"><?php echo h($ra["student_name"] ?? "Student"); ?><br><small class="text-muted"><?php echo h(trim(($ra["course"] ?? "") . " • " . ($ra["semester"] ?? ""), " •")); ?> &middot; <?php echo date("d M Y", strtotime($ra["submitted_at"])); ?></small></td>
                                                <td data-label="Score"><strong><?php echo number_format((float)$ra["percentage"], 1); ?>%</strong></td>
                                                <td data-label="Status"><span class="sp-pill <?php echo $ra["status"] === "Pass" ? "is-completed" : "is-expired"; ?>"><?php echo h($ra["status"]); ?></span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php } else { ?>
                                <div class="sp-empty">
                                    <div class="sp-empty-icon"><i class="bi bi-inbox"></i></div>
                                    <h4>No Attempts Yet</h4>
                                    <p>No student has attempted this exam so far.</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-fp-clone-link]").forEach(function (link) {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            var href = link.getAttribute("href");
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    title: "Duplicate this exam?",
                    text: "A copy will be created as an Inactive draft with the same questions.",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Duplicate",
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#4F46E5",
                    reverseButtons: true,
                    focusCancel: true
                }).then(function (r) { if (r && r.isConfirmed) { window.location.href = href; } });
            } else if (window.confirm("Duplicate this exam as a draft?")) {
                window.location.href = href;
            }
        });
    });
});
</script>
<?php include("../app/layouts/faculty_footer.php");
mysqli_close($conn); ?>

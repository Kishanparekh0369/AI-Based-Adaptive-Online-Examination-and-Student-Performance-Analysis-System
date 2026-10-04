<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Student Performance";
include("../app/layouts/admin_header.php");

function admin_student_profile_avatar($profile_image, $class_name) {
    $profile_image = trim((string)$profile_image);
    if ($profile_image !== "" && file_exists("../assets/uploads/profile/" . $profile_image)) {
        return '<img src="../assets/uploads/profile/' . h($profile_image) . '" class="' . h($class_name) . '" style="object-fit:cover" alt="Student profile photo">';
    }
    return '<span class="' . h($class_name) . '"><i class="bi bi-person-fill"></i></span>';
}

$search = isset($_GET["search"]) ? clean_data($_GET["search"]) : "";
$summary_result = mysqli_query($conn, "SELECT COUNT(*) AS total_results, AVG(percentage) AS avgp, MAX(percentage) AS highest, MIN(percentage) AS lowest, SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) AS pass_count, SUM(CASE WHEN status='Fail' THEN 1 ELSE 0 END) AS fail_count, SUM(correct_answers) AS correct_total, SUM(wrong_answers) AS wrong_total FROM results");
$summary = mysqli_fetch_assoc($summary_result);
$students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM students"));
$exams = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM exams"));
$result = mysqli_query($conn, "SELECT LOWER(TRIM(st.name)) AS name_key, TRIM(st.name) AS display_name, SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(st.profile_image, '') ORDER BY st.id SEPARATOR '||'), '||', 1) AS profile_image, GROUP_CONCAT(DISTINCT st.email SEPARATOR ', ') AS emails, GROUP_CONCAT(DISTINCT st.course SEPARATOR ', ') AS courses, GROUP_CONCAT(DISTINCT s.subject_name SEPARATOR ', ') AS subjects, COUNT(DISTINCT st.id) AS account_count, COUNT(r.id) AS exams_taken, AVG(r.percentage) AS avg_percentage, SUM(r.correct_answers) AS correct_total, SUM(r.wrong_answers) AS wrong_total, MAX(r.percentage) AS highest_score, MIN(r.percentage) AS lowest_score FROM students st LEFT JOIN results r ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY name_key, display_name ORDER BY display_name");
$subject = mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id LIMIT 8");
$subject_labels = ""; $subject_values = "";
while ($row = mysqli_fetch_assoc($subject)) { $subject_labels .= "'" . $row["subject_name"] . "',"; $subject_values .= round($row["avgp"], 2) . ","; }
if ($subject_labels == "") { $subject_labels = "'No Data'"; $subject_values = "0"; }
$pass_count = $summary["pass_count"] ?: 0;
$fail_count = $summary["fail_count"] ?: 0;
?>
<style>
.perf-card { background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:1.25rem; transition:.2s; cursor:pointer; }
.perf-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.07); }
.perf-card .grade-badge { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }
.perf-card .mini-stat { background:#f8fafc; border-radius:8px; padding:.4rem .6rem; text-align:center; }
.perf-card .mini-stat small { font-size:.62rem; color:#64748b; display:block; }
.perf-card .mini-stat strong { font-size:.85rem; }
.perf-avatar { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; flex-shrink:0; overflow:hidden; object-fit:cover; }
img.perf-avatar { display:block; }
.mult-acct { font-size:.6rem; padding:1px 6px; border-radius:10px; background:#fef3c7; color:#d97706; }
@media(max-width:576px) { .perf-card { padding:1rem; } }
</style>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb dashboard-breadcrumb"><li class="breadcrumb-item">Admin</li><li class="breadcrumb-item active">Performance</li></ol></nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-graph-up"></i> Analytics &amp; Results</span>
                    <h1>Student Performance Analysis</h1>
                    <p>System-wide score trends, pass splits, and per-student drill-downs.</p>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <?php $stats = array(
                    array("Overall Percentage", number_format($summary["avgp"] == "" ? 0 : $summary["avgp"], 2) . "%", "bi-percent", "blue", "Average across all attempts"),
                    array("Highest Score", number_format($summary["highest"] == "" ? 0 : $summary["highest"], 2) . "%", "bi-trophy", "amber", "Best single attempt"),
                    array("Lowest Score", number_format($summary["lowest"] == "" ? 0 : $summary["lowest"], 2) . "%", "bi-graph-down", "secondary", "Needs attention"),
                    array("Total Students", $students["total"], "bi-people", "green", "Registered learners"),
                    array("Total Exams", $exams["total"], "bi-calendar-check", "cyan", "Created exams"),
                    array("Completed Exams", $summary["total_results"] ?: 0, "bi-check2-circle", "rose", "Submitted attempts")
                );
                $st_delay = 0;
                foreach ($stats as $stat) { $st_delay++; ?>
                    <div class="col-6 col-xl-4 sp-fade-up" data-delay="<?php echo min($st_delay, 6); ?>"><div class="dash-stat dash-tone-<?php echo $stat[3]; ?> h-100"><div class="dash-stat-top"><span class="dash-stat-icon"><i class="bi <?php echo $stat[2]; ?>"></i></span><span class="dash-stat-trend">Live</span></div><div class="dash-stat-value"><?php echo $stat[1]; ?></div><p class="dash-stat-label"><?php echo $stat[0]; ?></p><p class="dash-stat-sub"><?php echo $stat[4]; ?></p></div></div>
                <?php } ?>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-xl-7"><div class="dashboard-panel"><h5>Subject-wise Average</h5><div class="chart-container chart-container-md"><canvas id="adminSubjectPerformance" role="img" aria-label="Subject-wise average bar chart"></canvas></div></div></div>
                <div class="col-xl-5"><div class="dashboard-panel"><h5>Pass / Fail Split</h5><div class="chart-container chart-container-doughnut"><canvas id="adminPassFail" role="img" aria-label="Pass fail split doughnut chart"></canvas></div></div></div>
            </div>
            <form class="toolbar-card mb-3" id="performanceSearchForm"><div class="row g-2"><div class="col-md-10"><input id="performanceLiveSearch" value="<?php echo h($search); ?>" class="form-control" placeholder="Search by name, email, subject or course"></div><div class="col-md-2"><button type="button" class="btn btn-primary w-100" id="performanceClearSearch"><i class="bi bi-x-circle me-1"></i>Clear</button></div></div></form>
            <div class="row g-3" id="performanceCards">
                <?php if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $avg = round($row["avg_percentage"] == "" ? 0 : $row["avg_percentage"], 2);
                        $grade = $avg >= 90 ? "A+" : ($avg >= 75 ? "A" : ($avg >= 60 ? "B" : ($avg >= 40 ? "C" : "F")));
                        $gradeColor = $grade == "F" ? "#dc2626" : ($grade == "C" ? "#d97706" : "#059669");
                        $highest = $row["highest_score"] == "" ? 0 : $row["highest_score"];
                        $lowest = $row["lowest_score"] == "" ? 0 : $row["lowest_score"];
                        $emails = h($row["emails"]);
                        $searchText = strtolower(trim(($row["display_name"] ?? "") . " " . ($row["emails"] ?? "") . " " . ($row["courses"] ?? "") . " " . ($row["subjects"] ?? "")));
                        $accountCount = (int)$row["account_count"];
                        $nameKey = preg_replace('/[^a-z0-9]/', '', $row["name_key"]);
                        $modalId = "perfModal_" . $nameKey;
                        $catColors = [0=>"#4f46e5",1=>"#059669",2=>"#d97706",3=>"#dc2626",4=>"#0891b2"];
                    ?>
                    <div class="col-xl-6 performance-result-card" data-search="<?php echo h($searchText); ?>">
                        <div class="perf-card" onclick="new bootstrap.Modal(document.getElementById('<?php echo $modalId; ?>')).show()">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <?php echo admin_student_profile_avatar($row["profile_image"] ?? "", "perf-avatar"); ?>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h5 class="fw-bold mb-0"><?php echo h($row["display_name"]); ?></h5>
                                        <?php if ($accountCount > 1): ?><span class="mult-acct"><i class="bi bi-layers me-1"></i><?php echo $accountCount; ?> accounts</span><?php endif; ?>
                                        <span class="grade-badge" style="background:<?php echo $gradeColor; ?>15;color:<?php echo $gradeColor; ?>;margin-left:auto"><?php echo $grade; ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo $emails; ?></small>
                                </div>
                            </div>
                            <div class="row g-1 mb-2">
                                <div class="col-3"><div class="mini-stat"><small>Average</small><strong><?php echo number_format($avg,1); ?>%</strong></div></div>
                                <div class="col-3"><div class="mini-stat"><small>Exams</small><strong><?php echo h($row["exams_taken"]); ?></strong></div></div>
                                <div class="col-3"><div class="mini-stat"><small>Highest</small><strong><?php echo number_format($highest,1); ?>%</strong></div></div>
                                <div class="col-3"><div class="mini-stat"><small>Lowest</small><strong><?php echo number_format($lowest,1); ?>%</strong></div></div>
                            </div>
                            <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?php echo min($avg,100); ?>%;background:<?php echo $gradeColor; ?>"></div></div>
                        </div>
                    </div>
                    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content professional-modal" data-client-scope>
                                <div class="modal-header"><div><h5 class="modal-title"><?php echo h($row["display_name"]); ?> — Performance Details</h5><small class="text-muted"><?php echo $emails; ?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body scrollable-modal-body">
                                    <?php
                                    $detailStudents = mysqli_query($conn, "SELECT id, email FROM students WHERE LOWER(TRIM(name))='" . mysqli_real_escape_string($conn, $row["name_key"]) . "'");
                                    $detailIds = []; $detailEmails = [];
                                    while ($ds = mysqli_fetch_assoc($detailStudents)) { $detailIds[] = (int)$ds['id']; $detailEmails[] = $ds['email']; }
                                    $idList = implode(',', $detailIds);
                                    ?>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4"><div class="dashboard-panel text-center p-3"><h4 class="text-primary"><?php echo number_format($avg,1); ?>%</h4><small class="text-muted">Average</small></div></div>
                                        <div class="col-md-4"><div class="dashboard-panel text-center p-3"><h4 class="text-success"><?php echo h($row["exams_taken"]); ?></h4><small class="text-muted">Total Exams</small></div></div>
                                        <div class="col-md-4"><div class="dashboard-panel text-center p-3"><h4 class="text-warning"><?php echo number_format($highest,1); ?>%</h4><small class="text-muted">Highest</small></div></div>
                                    </div>
                                    <?php if (count($detailEmails) > 1): ?>
                                    <div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle me-1"></i>This student has <?php echo count($detailEmails); ?> linked accounts: <?php echo implode(', ', array_map('h', $detailEmails)); ?></div>
                                    <?php endif; ?>
                                    <div class="modal-filter-bar"><div class="row g-2"><div class="col-md-5"><input class="form-control" data-client-filter="text" placeholder="Search exam or subject"></div><div class="col-md-3"><input type="date" class="form-control" data-client-filter="date"></div></div></div>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle" data-client-table data-page-size="6">
                                            <thead><tr><th>Exam</th><th>Subject</th><th>Marks</th><th>Correct</th><th>Wrong</th><th>Percentage</th><th>Status</th><th>Date</th></tr></thead>
                                            <tbody><?php
                                            $history = mysqli_query($conn, "SELECT r.*, e.exam_title, s.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id WHERE r.student_id IN ($idList) ORDER BY r.id ASC");
                                            if ($history && mysqli_num_rows($history) > 0) { while ($item = mysqli_fetch_assoc($history)) { ?>
                                                <tr data-date="<?php echo h(substr($item["submitted_at"],0,10)); ?>">
                                                    <td><?php echo h($item["exam_title"]); ?></td><td><?php echo h($item["subject_name"]); ?></td><td><?php echo h($item["marks_obtained"]); ?></td><td><?php echo h($item["correct_answers"]); ?></td><td><?php echo h($item["wrong_answers"]); ?></td><td><?php echo number_format($item["percentage"],2); ?>%</td><td><span class="badge bg-<?php echo $item["status"]=="Pass"?"success":"danger"; ?>"><?php echo h($item["status"]); ?></span></td><td><?php echo h($item["submitted_at"]); ?></td>
                                                </tr>
                                            <?php }} else { ?><tr><td colspan="8" class="text-center empty-state">No exam history available.</td></tr><?php } ?></tbody>
                                        </table>
                                    </div>
                                    <div class="client-pagination" data-client-pagination></div>
                                    <h5 class="mt-4">Subject Wise Analysis</h5>
                                    <?php
                                    $subjH = mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id WHERE r.student_id IN ($idList) GROUP BY s.id ORDER BY s.subject_name");
                                    ?>
                                    <div class="row g-2"><?php if ($subjH && mysqli_num_rows($subjH) > 0) { $ci=0; while ($sr = mysqli_fetch_assoc($subjH)) { ?><div class="col-md-4"><div class="toolbar-card p-3" style="border-left:3px solid <?php echo $catColors[$ci % 5]; ?>"><strong><?php echo h($sr["subject_name"]); ?></strong><div class="fw-bold mt-1"><?php echo number_format($sr["avgp"],1); ?>%</div></div></div><?php $ci++; }} else { ?><div class="col-12 empty-state text-center">No subject analysis yet.</div><?php } ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php }} else { ?><div class="col-12"><div class="empty-state text-center">No performance records found.</div></div><?php } ?>
                <div class="col-12 d-none" id="performanceNoResults"><div class="empty-state text-center">No matching performance records found.</div></div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded",function(){
    if(typeof ChartManager!=="undefined") {
        ChartManager.create("adminSubjectPerformance",{type:"bar",data:{labels:[<?php echo $subject_labels; ?>],datasets:[{label:"Average %",data:[<?php echo $subject_values; ?>],backgroundColor:"#2454d6",borderRadius:5}]},options:{scales:{y:{beginAtZero:true,max:100,ticks:{callback:function(v){return v+"%";}}}},plugins:{legend:{display:false}}}});
        ChartManager.create("adminPassFail",{type:"doughnut",data:{labels:["Pass","Fail"],datasets:[{data:[<?php echo $pass_count; ?>,<?php echo $fail_count; ?>],backgroundColor:["#198754","#dc3545"]}]},options:{cutout:"65%"}});
    }
    var searchInput = document.getElementById("performanceLiveSearch");
    var clearBtn = document.getElementById("performanceClearSearch");
    var cards = Array.prototype.slice.call(document.querySelectorAll(".performance-result-card"));
    var empty = document.getElementById("performanceNoResults");
    function filterCards() {
        var term = (searchInput.value || "").toLowerCase().trim();
        var visible = 0;
        cards.forEach(function(card) {
            var match = !term || (card.getAttribute("data-search") || "").indexOf(term) >= 0;
            card.classList.toggle("d-none", !match);
            if (match) visible++;
        });
        if (empty) empty.classList.toggle("d-none", visible !== 0);
    }
    if (searchInput) {
        searchInput.addEventListener("input", filterCards);
        filterCards();
    }
    if (clearBtn) {
        clearBtn.addEventListener("click", function() {
            searchInput.value = "";
            filterCards();
            searchInput.focus();
        });
    }
    var form = document.getElementById("performanceSearchForm");
    if (form) {
        form.addEventListener("submit", function(event) {
            event.preventDefault();
            filterCards();
        });
    }
});
</script>
<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

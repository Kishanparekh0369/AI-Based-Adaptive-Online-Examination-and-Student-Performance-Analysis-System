<?php
require_once("../app/config/database.php");

$conn = conn();require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Student Dashboard";
include("../app/layouts/student_header.php");

$student_id = (int)$_SESSION["student_id"];
$available = 0;
$completed = 0;
$avg = 0;
$feedback = 0;
$results_count = 0;
$avg_marks = 0;

function dash_fetch($conn, $sql, $types = "", $params = array())
{
    $rows = array();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $rows; }
    if ($types !== "" && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) { $rows[] = $row; }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

$student_rows = dash_fetch($conn, "SELECT * FROM students WHERE id=? LIMIT 1", "i", array($student_id));
$student = $student_rows ? $student_rows[0] : array("id" => $student_id, "name" => "Student", "course" => "", "semester" => "", "profile_image" => "");

$student_course = trim((string)($student["course"] ?? ""));
$student_semester = trim((string)($student["semester"] ?? ""));
$active_rows = $student_course !== "" && $student_semester !== ""
    ? dash_fetch($conn, "SELECT COUNT(*) AS total FROM exams WHERE status='Active' AND course=? AND semester=?", "ss", array($student_course, $student_semester))
    : array(array("total" => 0));
if ($active_rows) { $available = (int)$active_rows[0]["total"]; }

$result_rows = dash_fetch($conn, "SELECT COUNT(*) AS total, AVG(percentage) AS avgp, AVG(marks_obtained) AS avg_marks, SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) AS passed FROM results WHERE student_id=?", "i", array($student_id));
if ($result_rows) {
    $completed = (int)$result_rows[0]["total"];
    $results_count = (int)$result_rows[0]["total"];
    $avg = $result_rows[0]["avgp"] === null ? 0 : (float)$result_rows[0]["avgp"];
    $avg_marks = $result_rows[0]["avg_marks"] === null ? 0 : (float)$result_rows[0]["avg_marks"];
    $passed_count = (int)($result_rows[0]["passed"] ?? 0);
} else {
    $passed_count = 0;
}
$feedback_rows = dash_fetch($conn, "SELECT COUNT(*) AS total, AVG(rating) AS avgr FROM feedback WHERE student_id=?", "i", array($student_id));
if ($feedback_rows) {
    $feedback = (int)$feedback_rows[0]["total"];
    $feedback_avg = $feedback_rows[0]["avgr"] === null ? 0 : round((float)$feedback_rows[0]["avgr"], 1);
} else {
    $feedback_avg = 0;
}

$pass_rate = $completed > 0 ? round(($passed_count / $completed) * 100) : 0;
$grade = $completed > 0 ? ($avg >= 90 ? "A+" : ($avg >= 75 ? "A" : ($avg >= 60 ? "B" : ($avg >= 40 ? "C" : "F")))) : "—";

$last_exam_rows = dash_fetch($conn, "SELECT e.exam_title, r.percentage, r.submitted_at FROM results r LEFT JOIN exams e ON r.exam_id=e.id WHERE r.student_id=? ORDER BY r.id DESC LIMIT 1", "i", array($student_id));
$last_exam = $last_exam_rows ? $last_exam_rows[0] : null;

$hour_now = (int)date("G");
$greeting = $hour_now < 12 ? "Good morning" : ($hour_now < 17 ? "Good afternoon" : "Good evening");
$first_name = trim((string)$student["name"]);
$first_name = $first_name !== "" ? explode(" ", preg_replace('/\s+/', ' ', $first_name))[0] : "Student";
$first_name = ucwords(strtolower($first_name));

$activities = array();
$activity_exams = dash_fetch($conn, "SELECT e.exam_title, r.percentage, r.submitted_at FROM results r LEFT JOIN exams e ON r.exam_id=e.id WHERE r.student_id=? ORDER BY r.id DESC LIMIT 10", "i", array($student_id));
foreach ($activity_exams as $row) {
    $activities[] = array("type" => "Exam", "detail" => $row["exam_title"] . " submitted with " . number_format((float)$row["percentage"], 2) . "%", "date" => $row["submitted_at"], "status" => "Completed", "icon" => "bi-calendar-check text-primary");
}
$activity_feedback = dash_fetch($conn, "SELECT rating, created_at FROM feedback WHERE student_id=? ORDER BY id DESC LIMIT 10", "i", array($student_id));
foreach ($activity_feedback as $row) {
    $activities[] = array("type" => "Feedback", "detail" => "Feedback rating submitted: " . $row["rating"] . "/5", "date" => $row["created_at"], "status" => "Sent", "icon" => "bi-chat-dots text-secondary");
}
$activities[] = array("type" => "Profile", "detail" => "Profile section is ready for review and updates.", "date" => date("Y-m-d"), "status" => "Active", "icon" => "bi-person-check text-primary");
usort($activities, function($a, $b) { return strtotime($b["date"]) - strtotime($a["date"]); });
$latest_activities = array_slice($activities, 0, 10);
$subject_labels = "";
$subject_marks = "";
$history_labels = "";
$history_values = "";
$subject_result = dash_fetch($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id WHERE r.student_id=? GROUP BY s.id LIMIT 6", "i", array($student_id));
foreach ($subject_result as $row) {
    $subject_labels .= "'" . addslashes($row["subject_name"]) . "',";
    $subject_marks .= round((float)$row["avgp"]) . ",";
}
if ($subject_labels == "") {
    $subject_labels = "'No Data'";
    $subject_marks = "0";
}
$history_result = dash_fetch($conn, "SELECT e.exam_title, r.percentage FROM results r LEFT JOIN exams e ON r.exam_id=e.id WHERE r.student_id=? ORDER BY r.id DESC LIMIT 6", "i", array($student_id));
foreach ($history_result as $row) {
    $history_labels .= "'" . addslashes($row["exam_title"]) . "',";
    $history_values .= round((float)$row["percentage"]) . ",";
}
if ($history_labels == "") {
    $history_labels = "'No Exam'";
    $history_values = "0";
}

$exam_progress = $available > 0 ? min(round(($completed / $available) * 100), 100) : 0;
$avg_progress = round($avg);
$marks_progress = min(round($avg_marks * 10), 100);

$att_total = 0;
$att_present = 0;
$attendance_pct = null;
$att_res = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present FROM attendance WHERE student_id='$student_id'");
if ($att_res && $att_row = mysqli_fetch_assoc($att_res)) {
    $att_total = (int)$att_row["total"];
    $att_present = (int)($att_row["present"] ?? 0);
    if ($att_total > 0) {
        $attendance_pct = round(($att_present / $att_total) * 100);
    }
}
$attendance_display = $attendance_pct !== null ? $attendance_pct . "%" : "No data";
$attendance_width = $attendance_pct !== null ? $attendance_pct : 0;
$attendance_note = $attendance_pct !== null ? ($attendance_pct >= 75 ? "Healthy" : "Below target") : "No records yet";

$student_avatar_html = '<span class="sp-avatar-initials">' . strtoupper(substr($student["name"], 0, 1)) . '</span>';
$student_photo_path = __DIR__ . "/../assets/uploads/profile/" . ($student["profile_image"] ?? "");
if (!empty($student["profile_image"]) && file_exists($student_photo_path)) {
    $student_avatar_html = '<img src="../assets/uploads/profile/' . h($student["profile_image"]) . '" class="sp-avatar" alt="Profile photo">';
}
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Student</li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </nav>

            <section class="dash-hero mb-4" aria-label="Welcome">
                <span class="dash-orb dash-orb-1" aria-hidden="true"></span>
                <span class="dash-orb dash-orb-2" aria-hidden="true"></span>
                <div class="dash-hero-inner">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <div class="sp-fade-up">
                                <span class="dash-eyebrow"><span class="live-dot" aria-hidden="true"></span>Student Portal &middot; <span class="live-date"></span></span>
                            </div>
                            <h1 class="dash-title sp-fade-up" data-delay="1"><?php echo h($greeting); ?>, <span class="grad"><?php echo h($first_name); ?></span></h1>
                            <p class="dash-sub sp-fade-up" data-delay="2">Ready for your next assessment? Track exams, results and AI-powered insights in one place.</p>
                            <div class="dash-idchips sp-fade-up" data-delay="2">
                                <span class="dash-idchip"><i class="bi bi-person-badge"></i>Student ID: #<?php echo (int)$student["id"]; ?></span>
                                <span class="dash-idchip"><i class="bi bi-mortarboard"></i><?php echo h($student["course"]); ?></span>
                                <span class="dash-idchip"><i class="bi bi-layers"></i><?php echo h($student["semester"]); ?></span>
                                <span class="dash-idchip"><i class="bi bi-clock"></i><span class="live-clock"></span></span>
                            </div>
                            <div class="dash-ctas sp-fade-up" data-delay="3">
                                <a class="dash-cta-primary" href="exams.php"><i class="bi bi-play-circle-fill"></i>Start Exam</a>
                                <a class="dash-cta-ghost" href="result.php"><i class="bi bi-trophy"></i>Results</a>
                                <a class="dash-cta-ghost" href="performance.php"><i class="bi bi-graph-up-arrow"></i>Performance</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="dash-profile-card sp-fade-up" data-delay="2">
                                <span class="dash-avatar-wrap">
                                    <?php if (!empty($student["profile_image"]) && file_exists(__DIR__ . "/../assets/uploads/profile/" . $student["profile_image"])) { ?>
                                        <img src="../assets/uploads/profile/<?php echo h($student["profile_image"]); ?>" alt="Profile photo">
                                    <?php } else { ?>
                                        <span class="dash-avatar-fallback"><?php echo h(strtoupper(substr($student["name"] ?? "S", 0, 1))); ?></span>
                                    <?php } ?>
                                    <span class="dash-online-dot" title="Online" aria-hidden="true"></span>
                                </span>
                                <div class="dash-profile-meta">
                                    <strong><?php echo h($student["name"]); ?></strong>
                                    <small><?php echo h($student["course"]); ?> &middot; <?php echo h($student["semester"]); ?></small>
                                    <div class="dash-mini-stats">
                                        <div><b><?php echo $completed; ?></b><span>Done</span></div>
                                        <div><b><?php echo round($avg); ?>%</b><span>Avg</span></div>
                                        <div><b><?php echo h($grade); ?></b><span>Grade</span></div>
                                    </div>
                                </div>
                                <a class="dash-cta-ghost" style="padding:10px 13px;" href="profile.php" title="View Profile" aria-label="View Profile"><i class="bi bi-arrow-up-right"></i></a>
                            </div>
                            <?php if ($last_exam) { ?>
                            <div class="dash-profile-card mt-2 sp-fade-up" data-delay="3" style="padding:12px 18px;">
                                <i class="bi bi-clock-history" style="color:#93C5FD;font-size:1.2rem;"></i>
                                <div class="dash-profile-meta">
                                    <small style="text-transform:uppercase;letter-spacing:.06em;">Last attempt</small>
                                    <strong style="font-size:.85rem;"><?php echo h($last_exam["exam_title"]); ?> &middot; <?php echo number_format((float)$last_exam["percentage"], 0); ?>%</strong>
                                </div>
                                <a class="dash-cta-ghost" style="padding:8px 11px;" href="result.php" title="View Result" aria-label="View Result"><i class="bi bi-arrow-right"></i></a>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-3 g-xl-4 mb-4">
                <?php
                $dash_cards = array(
                    array("Available Exams", $available, "Ready to attempt now", "bi-calendar-event-fill", "exams.php", "dash-tone-blue", $available > 0 ? $available . " open" : "None open"),
                    array("Completed Exams", $completed, "Exams you submitted", "bi-check-circle-fill", "result.php", "dash-tone-green", $pass_rate . "% pass rate"),
                    array("Overall Average", round($avg), "Across all attempts", "bi-graph-up-arrow", "performance.php", "dash-tone-violet", "Grade " . $grade, "%"),
                    array("Results", $results_count, "Generated scorecards", "bi-award-fill", "result.php", "dash-tone-amber", $passed_count . " passed"),
                    array("Feedback Sent", $feedback, "Ratings you shared", "bi-chat-dots-fill", "feedback.php", "dash-tone-cyan", $feedback > 0 ? $feedback_avg . "/5 avg rating" : "None yet")
                );
                $dc_delay = 0;
                foreach ($dash_cards as $card) {
                    $dc_delay++;
                    $dc_suffix = isset($card[7]) ? $card[7] : "";
                ?>
                    <div class="col-6 col-lg-4 col-xl sp-fade-up" data-delay="<?php echo min($dc_delay, 6); ?>">
                        <div class="dash-stat <?php echo $card[5]; ?>">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $card[3]; ?>"></i></span>
                                <span class="dash-stat-trend"><?php echo h($card[6]); ?></span>
                            </div>
                            <div class="dash-stat-value" data-count="<?php echo $card[1]; ?>" <?php if ($dc_suffix !== "") { echo "data-suffix='" . h($dc_suffix) . "'"; } ?>>0</div>
                            <p class="dash-stat-label"><?php echo h($card[0]); ?></p>
                            <p class="dash-stat-sub"><?php echo h($card[2]); ?></p>
                            <a href="<?php echo $card[4]; ?>" class="dash-stat-link" aria-label="Open <?php echo h($card[0]); ?>"><span>Open</span><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-8 sp-fade-up">
                    <div class="dashboard-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Quick Actions</h5>
                            <span class="badge bg-primary">Student Tools</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><a class="quick-action" href="exams.php"><i class="bi bi-play-circle"></i><span>Start Exam</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="result.php"><i class="bi bi-award"></i><span>View Result</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="performance.php"><i class="bi bi-file-earmark-bar-graph"></i><span>Performance Report</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="profile.php"><i class="bi bi-person-badge"></i><span>View Profile</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="edit_profile.php"><i class="bi bi-pencil-square"></i><span>Edit Profile</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="report.php"><i class="bi bi-file-earmark-text"></i><span>Report Card</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="ai_feedback.php"><i class="bi bi-robot"></i><span>AI Feedback</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="change_password.php"><i class="bi bi-shield-lock"></i><span>Change Password</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="feedback.php"><i class="bi bi-chat-dots"></i><span>Feedback</span></a></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 sp-fade-up" data-delay="1">
                    <div class="dashboard-panel h-100">
                        <h5 class="mb-3">Performance Overview</h5>
                        <div class="progress-line"><span>Overall Performance</span><strong><?php echo $avg_progress; ?>%</strong></div>
                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: <?php echo $avg_progress; ?>%"></div>
                        </div>
                        <div class="progress-line"><span>Completed Exams</span><strong><?php echo $exam_progress; ?>%</strong></div>
                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: <?php echo $exam_progress; ?>%"></div>
                        </div>
                        <div class="progress-line"><span>Average Marks</span><strong><?php echo $marks_progress; ?>%</strong></div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-warning" style="width: <?php echo $marks_progress; ?>%"></div>
                        </div>
                        <div class="progress-line"><span>Attendance</span><strong><?php echo $attendance_display; ?></strong></div>
                        <div class="progress">
                            <div class="progress-bar bg-info" style="width: <?php echo (int)$attendance_width; ?>%"></div>
                        </div>
                        <small class="text-muted d-block mt-1"><?php echo h($attendance_note); ?></small>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-6 sp-fade-up">
                    <div class="dashboard-panel">
                        <h5>Subject-wise Marks</h5><div class="chart-container chart-container-md"><canvas id="studentSubjectChart" role="img" aria-label="Subject-wise marks bar chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6 sp-fade-up" data-delay="1">
                    <div class="dashboard-panel">
                        <h5>Overall Performance</h5><div class="chart-container chart-container-doughnut"><canvas id="studentOverallChart" role="img" aria-label="Overall performance doughnut chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6 sp-fade-up">
                    <div class="dashboard-panel">
                        <h5>Exam History</h5><div class="chart-container chart-container-md"><canvas id="studentHistoryChart" role="img" aria-label="Exam history line chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6 sp-fade-up" data-delay="1">
                    <div class="dashboard-panel">
                        <h5>Average Score</h5><div class="chart-container chart-container-md"><canvas id="studentAverageChart" role="img" aria-label="Average score bar chart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="dashboard-panel sp-fade-up">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">Recent Activities (10 Latest)</h5>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#studentAllActivityModal"><i class="bi bi-list-ul me-1"></i>View All Activity</button>
                </div>
                <div class="table-responsive">
                    <table class="table dashboard-table">
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Details</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latest_activities as $activity) { ?><tr>
                                <td><i class="bi <?php echo h($activity["icon"]); ?> me-2"></i><?php echo h($activity["type"]); ?></td>
                                <td><?php echo h($activity["detail"]); ?><br><small class="text-muted"><?php echo h($activity["date"]); ?></small></td>
                                <td><span class="badge bg-primary"><?php echo h($activity["status"]); ?></span></td>
                            </tr><?php } ?>
                            <?php if (count($latest_activities) == 0) { ?><tr>
                                    <td colspan="3" class="text-center empty-state">No exam or feedback activity yet. Start an exam to build your history.</td>
                                </tr><?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="studentAllActivityModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content professional-modal" data-client-scope>
            <div class="modal-header"><h5 class="modal-title">All Activity</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body scrollable-modal-body">
                <div class="modal-filter-bar">
                    <div class="row g-2">
                        <div class="col-md-4"><input class="form-control" data-client-filter="text" placeholder="Search activity"></div>
                        <div class="col-md-3"><input type="date" class="form-control" data-client-filter="date"></div>
                        <div class="col-md-3"><select class="form-select" data-client-filter="type"><option value="">All Activity</option><option>Exam</option><option>Feedback</option><option>Profile</option></select></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" data-client-table data-page-size="8">
                        <thead><tr><th>Type</th><th>Details</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody><?php foreach ($activities as $activity) { ?><tr data-type="<?php echo h($activity["type"]); ?>" data-date="<?php echo h(substr($activity["date"], 0, 10)); ?>">
                            <td><i class="bi <?php echo h($activity["icon"]); ?> me-2"></i><?php echo h($activity["type"]); ?></td>
                            <td><?php echo h($activity["detail"]); ?></td>
                            <td><?php echo h($activity["date"]); ?></td>
                            <td><span class="badge bg-primary"><?php echo h($activity["status"]); ?></span></td>
                        </tr><?php } ?></tbody>
                    </table>
                </div>
                <div class="client-pagination" data-client-pagination></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof ChartManager === "undefined") {
            return;
        }
        ChartManager.create("studentSubjectChart", {
            type: "bar",
            data: {
                labels: [<?php echo $subject_labels; ?>],
                datasets: [{
                    label: "Marks %",
                    data: [<?php echo $subject_marks; ?>],
                    backgroundColor: "#4f46e5"
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function (v) { return v + "%"; } }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
        ChartManager.create("studentOverallChart", {
            type: "doughnut",
            data: {
                labels: ["Performance", "Remaining"],
                datasets: [{
                    data: [<?php echo $avg_progress; ?>, <?php echo 100 - $avg_progress; ?>],
                    backgroundColor: ["#4f46e5", "#e9ecef"]
                }]
            },
            options: { cutout: "65%" }
        });
        ChartManager.create("studentHistoryChart", {
            type: "line",
            data: {
                labels: [<?php echo $history_labels; ?>],
                datasets: [{
                    label: "Exam History",
                    data: [<?php echo $history_values; ?>],
                    borderColor: "#2454d6",
                    backgroundColor: "rgba(36,84,214,.12)",
                    tension: .35,
                    fill: true
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function (v) { return v + "%"; } }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
        ChartManager.create("studentAverageChart", {
            type: "bar",
            data: {
                labels: ["Average Score", "Completed Exams", "Feedback"],
                datasets: [{
                    label: "Student Summary",
                    data: [<?php echo round($avg); ?>, <?php echo $completed; ?>, <?php echo $feedback; ?>],
                    backgroundColor: ["#4f46e5", "#2454d6", "#64748b"]
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    });
</script>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>

<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Admin Dashboard";
include("../app/layouts/admin_header.php");

$counts = array("students" => 0, "faculty" => 0, "subjects" => 0, "questions" => 0, "exams" => 0, "results" => 0, "feedback" => 0);
foreach ($counts as $table => $value) {
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM $table");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $counts[$table] = $row["total"];
    }
}

$recent_students = mysqli_query($conn, "SELECT name, created_at FROM students ORDER BY id DESC LIMIT 2");
$recent_faculty = mysqli_query($conn, "SELECT name, email, created_at FROM faculty ORDER BY id DESC LIMIT 2");
$recent_exams = mysqli_query($conn, "SELECT exam_title, exam_date FROM exams ORDER BY id DESC LIMIT 2");
$recent_results = mysqli_query($conn, "SELECT r.percentage, st.name FROM results r LEFT JOIN students st ON r.student_id=st.id ORDER BY r.id DESC LIMIT 2");
$recent_feedback = mysqli_query($conn, "SELECT f.rating, st.name FROM feedback f LEFT JOIN students st ON f.student_id=st.id ORDER BY f.id DESC LIMIT 2");

$activities = array();
function add_admin_activity(&$activities, $type, $detail, $date, $status, $icon, $badge = "primary")
{
    if ($date == "") {
        $date = date("Y-m-d H:i:s");
    }
    $activities[] = array("type" => $type, "detail" => $detail, "date" => $date, "status" => $status, "icon" => $icon, "badge" => $badge);
}
$activity_faculty = mysqli_query($conn, "SELECT name, created_at FROM faculty ORDER BY id DESC LIMIT 6");
if ($activity_faculty) {
    while ($row = mysqli_fetch_assoc($activity_faculty)) {
        add_admin_activity($activities, "Faculty Added", $row["name"] . " joined as Faculty", $row["created_at"], "Faculty", "bi-person-badge text-info", "info");
    }
}
$activity_students = mysqli_query($conn, "SELECT name, created_at FROM students ORDER BY id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($activity_students)) {
    add_admin_activity($activities, "Student Registration", $row["name"] . " joined the system", $row["created_at"], "New", "bi-person-plus text-primary", "success");
}
$activity_questions = mysqli_query($conn, "SELECT question, id FROM questions ORDER BY id DESC LIMIT 6");
while ($row = mysqli_fetch_assoc($activity_questions)) {
    add_admin_activity($activities, "Question Added", "Question #" . $row["id"] . " added to question bank", date("Y-m-d H:i:s"), "Added", "bi-question-circle text-warning", "warning");
}
$activity_exams = mysqli_query($conn, "SELECT exam_title, exam_date FROM exams ORDER BY id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($activity_exams)) {
    add_admin_activity($activities, "Exam Scheduled", $row["exam_title"] . " scheduled", $row["exam_date"], "Scheduled", "bi-calendar-check text-success", "info");
}
$activity_results = mysqli_query($conn, "SELECT r.percentage, r.submitted_at, st.name FROM results r LEFT JOIN students st ON r.student_id=st.id ORDER BY r.id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($activity_results)) {
    add_admin_activity($activities, "Result Generated", ($row["name"] ?: "Student") . " scored " . number_format($row["percentage"], 2) . "%", $row["submitted_at"], "Generated", "bi-award text-danger", "danger");
}
$activity_feedback = mysqli_query($conn, "SELECT f.rating, f.created_at, st.name FROM feedback f LEFT JOIN students st ON f.student_id=st.id ORDER BY f.id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($activity_feedback)) {
    add_admin_activity($activities, "Feedback Submitted", ($row["name"] ?: "Student") . " rated " . $row["rating"] . "/5", $row["created_at"], "Review", "bi-chat-dots text-secondary", "secondary");
}
$activity_notifications = mysqli_query($conn, "SELECT title, message, created_at FROM notifications WHERE user_type='admin' AND (title LIKE '%Updated%' OR title LIKE '%PDF%' OR title LIKE '%AI%' OR title LIKE '%Started%' OR title LIKE '%Submitted%') ORDER BY id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($activity_notifications)) {
    $title = $row["title"];
    $icon = stripos($title, "AI") !== false ? "bi-robot text-info" : (stripos($title, "PDF") !== false ? "bi-file-earmark-pdf text-danger" : "bi-bell text-primary");
    add_admin_activity($activities, $title, $row["message"], $row["created_at"], "Logged", $icon, "primary");
}
usort($activities, function ($a, $b) {
    return strtotime($b["date"]) - strtotime($a["date"]);
});
$latest_activities = array_slice($activities, 0, 10);

$subject_labels = "";
$subject_values = "";
$subject_result = mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id LIMIT 6");
while ($row = mysqli_fetch_assoc($subject_result)) {
    $subject_labels .= "'" . $row["subject_name"] . "',";
    $subject_values .= round($row["avgp"]) . ",";
}
if ($subject_labels == "") {
    $subject_labels = "'No Data'";
    $subject_values = "0";
}

$faculty_progress = min($counts["faculty"] * 15, 100);
$student_progress = min($counts["students"] * 10, 100);
$question_progress = min($counts["questions"] * 8, 100);
$exam_progress = min($counts["exams"] * 20, 100);
$result_progress = min($counts["results"] * 15, 100);
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
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
                                <span class="dash-eyebrow"><span class="live-dot" aria-hidden="true"></span>Admin Portal &middot; <span class="live-date"></span></span>
                            </div>
                            <h1 class="dash-title sp-fade-up" data-delay="1">Welcome back, <span class="grad"><?php echo h($_SESSION["admin_name"]); ?></span></h1>
                            <p class="dash-sub sp-fade-up" data-delay="2">Overall System Administrator: manage faculty accounts, students, subjects, and view global performance reports.</p>
                            <div class="dash-idchips sp-fade-up" data-delay="2">
                                <span class="dash-idchip"><i class="bi bi-person-badge"></i><?php echo $counts["faculty"]; ?> Faculty</span>
                                <span class="dash-idchip"><i class="bi bi-people"></i><?php echo $counts["students"]; ?> Students</span>
                                <span class="dash-idchip"><i class="bi bi-journal-bookmark"></i><?php echo $counts["subjects"]; ?> Subjects</span>
                                <span class="dash-idchip"><i class="bi bi-clock"></i><span class="live-clock"></span></span>
                            </div>
                            <div class="dash-ctas sp-fade-up" data-delay="3">
                                <a class="dash-cta-primary" href="faculty.php"><i class="bi bi-person-badge-fill"></i>Manage Faculty</a>
                                <a class="dash-cta-ghost" href="students.php"><i class="bi bi-person-plus"></i>Manage Students</a>
                                <a class="dash-cta-ghost" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i>System Reports</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="dash-profile-card sp-fade-up" data-delay="2">
                                <span class="dash-avatar-wrap">
                                    <span class="dash-avatar-fallback"><?php echo h(strtoupper(substr((string)$_SESSION["admin_name"], 0, 1))); ?></span>
                                    <span class="dash-online-dot" title="Online" aria-hidden="true"></span>
                                </span>
                                <div class="dash-profile-meta">
                                    <strong><?php echo h($_SESSION["admin_name"]); ?></strong>
                                    <small>Administrator &middot; Online</small>
                                    <div class="dash-mini-stats">
                                        <div><b><?php echo $counts["exams"]; ?></b><span>Exams</span></div>
                                        <div><b><?php echo $counts["results"]; ?></b><span>Results</span></div>
                                        <div><b><?php echo $counts["questions"]; ?></b><span>Questions</span></div>
                                    </div>
                                </div>
                                <a class="dash-cta-ghost" style="padding:10px 13px;" href="change_password.php" title="Account Settings" aria-label="Account Settings"><i class="bi bi-arrow-up-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-3 g-xl-4 mb-4">
                <?php
                $cards = array(
                    array("Total Faculty", $counts["faculty"], "Registered faculty", "bi-person-badge-fill", "faculty.php", "cyan"),
                    array("Total Students", $counts["students"], "Registered students", "bi-people-fill", "students.php", "blue"),
                    array("Total Subjects", $counts["subjects"], "Available subjects", "bi-book-fill", "subjects.php", "green"),
                    array("Total Exams", $counts["exams"], "Created exams", "bi-calendar-check-fill", "exams.php", "amber"),
                    array("Total Results", $counts["results"], "Completed attempts", "bi-award-fill", "results.php", "rose"),
                    array("Total Feedback", $counts["feedback"], "Student feedback", "bi-chat-dots-fill", "feedback.php", "violet")
                );
                $dc_delay = 0;
                foreach ($cards as $card) {
                    $dc_delay++;
                ?>
                    <div class="col-6 col-lg-4 col-xl sp-fade-up" data-delay="<?php echo min($dc_delay, 6); ?>">
                        <div class="dash-stat dash-tone-<?php echo $card[5]; ?> h-100">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $card[3]; ?>"></i></span>
                                <span class="dash-stat-trend">System</span>
                            </div>
                            <div class="dash-stat-value" data-count="<?php echo $card[1]; ?>">0</div>
                            <p class="dash-stat-label"><?php echo $card[0]; ?></p>
                            <p class="dash-stat-sub"><?php echo $card[2]; ?></p>
                            <a href="<?php echo $card[4]; ?>" class="dash-stat-link" aria-label="Open <?php echo $card[0]; ?>"><span>Open</span><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-8 sp-fade-up">
                    <div class="dashboard-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Quick Actions</h5>
                            <span class="badge bg-primary">Admin Tools</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><a class="quick-action" href="students.php"><i class="bi bi-person-plus"></i><span>Add Student</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="subjects.php"><i class="bi bi-journal-plus"></i><span>Add Subject</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="questions.php"><i class="bi bi-plus-circle"></i><span>Add Question</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="exams.php"><i class="bi bi-calendar-plus"></i><span>Create Exam</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="results.php"><i class="bi bi-award"></i><span>View Results</span></a></div>
                            <div class="col-md-4"><a class="quick-action" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span></a></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 sp-fade-up" data-delay="1">
                    <div class="dashboard-panel h-100">
                        <h5 class="mb-3">Performance Overview</h5>
                        <div class="progress-line"><span>Students</span><strong><?php echo $student_progress; ?>%</strong></div>
                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: <?php echo $student_progress; ?>%"></div>
                        </div>
                        <div class="progress-line"><span>Questions</span><strong><?php echo $question_progress; ?>%</strong></div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" style="width: <?php echo $question_progress; ?>%"></div>
                        </div>
                        <div class="progress-line"><span>Exams</span><strong><?php echo $exam_progress; ?>%</strong></div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-warning" style="width: <?php echo $exam_progress; ?>%"></div>
                        </div>
                        <div class="progress-line"><span>Results</span><strong><?php echo $result_progress; ?>%</strong></div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" style="width: <?php echo $result_progress; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-6 sp-fade-up">
                    <div class="dashboard-panel">
                        <h5>Students Growth</h5><div class="chart-container chart-container-md"><canvas id="adminStudentsChart" role="img" aria-label="Students growth line chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6 sp-fade-up" data-delay="1">
                    <div class="dashboard-panel">
                        <h5>Exam Statistics</h5><div class="chart-container chart-container-md"><canvas id="adminExamChart" role="img" aria-label="Exam statistics bar chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6 sp-fade-up">
                    <div class="dashboard-panel">
                        <h5>Result Statistics</h5><div class="chart-container chart-container-doughnut"><canvas id="adminResultChart" role="img" aria-label="Result statistics doughnut chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6 sp-fade-up" data-delay="1">
                    <div class="dashboard-panel">
                        <h5>Subject-wise Performance</h5><div class="chart-container chart-container-md"><canvas id="adminSubjectChart" role="img" aria-label="Subject-wise performance bar chart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="dashboard-panel sp-fade-up">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">Recent Activities (10 Latest)</h5>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#allActivityModal"><i class="bi bi-list-ul me-1"></i>View All Activity</button>
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
                                    <td><span class="badge bg-<?php echo h($activity["badge"]); ?>"><?php echo h($activity["status"]); ?></span></td>
                                </tr><?php } ?>
                            <?php if (count($latest_activities) == 0) { ?><tr>
                                    <td colspan="3" class="text-center empty-state">No recent activity available</td>
                                </tr><?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="allActivityModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content professional-modal" data-client-scope>
            <div class="modal-header">
                <h5 class="modal-title">All Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body scrollable-modal-body">
                <div class="modal-filter-bar">
                    <div class="row g-2">
                        <div class="col-md-4"><input class="form-control" data-client-filter="text" placeholder="Search activity"></div>
                        <div class="col-md-3"><input type="date" class="form-control" data-client-filter="date"></div>
                        <div class="col-md-3"><select class="form-select" data-client-filter="type">
                                <option value="">All Activity</option>
                                <option>Student Registration</option>
                                <option>Question Added</option>
                                <option>Exam Created</option>
                                <option>Result Generated</option>
                                <option>Feedback Submitted</option>
                            </select></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" data-client-table data-page-size="8">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity) { ?><tr data-type="<?php echo h($activity["type"]); ?>" data-date="<?php echo h(substr($activity["date"], 0, 10)); ?>">
                                    <td><i class="bi <?php echo h($activity["icon"]); ?> me-2"></i><?php echo h($activity["type"]); ?></td>
                                    <td><?php echo h($activity["detail"]); ?></td>
                                    <td><?php echo h($activity["date"]); ?></td>
                                    <td><span class="badge bg-<?php echo h($activity["badge"]); ?>"><?php echo h($activity["status"]); ?></span></td>
                                </tr><?php } ?>
                        </tbody>
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
        ChartManager.create("adminStudentsChart", {
            type: "line",
            data: {
                labels: ["Week 1", "Week 2", "Week 3", "Week 4", "Week 5", "Week 6"],
                datasets: [{
                    label: "Students",
                    data: [0, 1, 2, <?php echo $counts["students"]; ?>, <?php echo $counts["students"]; ?>, <?php echo $counts["students"]; ?>],
                    borderColor: "#2454d6",
                    backgroundColor: "rgba(36,84,214,.12)",
                    tension: .35,
                    fill: true
                }]
            },
            options: { plugins: { legend: { display: false } } }
        });
        ChartManager.create("adminExamChart", {
            type: "bar",
            data: {
                labels: ["Subjects", "Questions", "Exams"],
                datasets: [{
                    label: "Statistics",
                    data: [<?php echo $counts["subjects"]; ?>, <?php echo $counts["questions"]; ?>, <?php echo $counts["exams"]; ?>],
                    backgroundColor: ["#198754", "#ffc107", "#17a2b8"]
                }]
            },
            options: { plugins: { legend: { display: false } } }
        });
        ChartManager.create("adminResultChart", {
            type: "doughnut",
            data: {
                labels: ["Results", "Feedback", "Students"],
                datasets: [{
                    data: [<?php echo $counts["results"]; ?>, <?php echo $counts["feedback"]; ?>, <?php echo $counts["students"]; ?>],
                    backgroundColor: ["#dc3545", "#6c757d", "#2454d6"]
                }]
            }
        });
        ChartManager.create("adminSubjectChart", {
            type: "bar",
            data: {
                labels: [<?php echo $subject_labels; ?>],
                datasets: [{
                    label: "Average %",
                    data: [<?php echo $subject_values; ?>],
                    backgroundColor: "#2454d6"
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
    });
</script>
<?php include("../app/layouts/admin_footer.php");
mysqli_close($conn); ?>

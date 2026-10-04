<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "AI Dashboard";
include("../app/layouts/admin_header.php");

require_once("../app/ai/DashboardInsights.php");
$insights = new DashboardInsights();
$aiData = $insights->generateAdminInsights($conn);

// Stats for charts
$students = mysqli_query($conn, "SELECT DATE(created_at) AS date, COUNT(*) AS cnt FROM students GROUP BY DATE(created_at) ORDER BY date LIMIT 30");
$studentDates = []; $studentCounts = [];
while ($row = mysqli_fetch_assoc($students)) { $studentDates[] = $row['date']; $studentCounts[] = $row['cnt']; }

$subjectPerf = mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id");
$subjLabels = []; $subjValues = [];
while ($row = mysqli_fetch_assoc($subjectPerf)) { $subjLabels[] = $row['subject_name']; $subjValues[] = round($row['avgp'] ?? 0); }

$catResult = mysqli_query($conn, "SELECT skill_category, COUNT(*) AS cnt FROM results WHERE skill_category IS NOT NULL GROUP BY skill_category");
$catLabels = []; $catValues = [];
while ($row = mysqli_fetch_assoc($catResult)) { $catLabels[] = $row['skill_category']; $catValues[] = $row['cnt']; }

$examModeResult = mysqli_query($conn, "SELECT exam_mode, COUNT(*) AS cnt FROM exams GROUP BY exam_mode");
$modeLabels = []; $modeValues = [];
while ($row = mysqli_fetch_assoc($examModeResult)) { $modeLabels[] = $row['exam_mode'] ?? 'Standard'; $modeValues[] = $row['cnt']; }

$aiUsedCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions WHERE ai_generated = 1"))['c'] ?? 0;
$totalQuestions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions"))['c'] ?? 0;
$adaptiveResults = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE theta_score IS NOT NULL"))['c'] ?? 0;
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item active">AI Dashboard</li>
                </ol>
            </nav>
            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-robot"></i> AI Powered</span>
                    <h1>AI Insights Dashboard</h1>
                    <p>Machine-generated summary, risks, and recommendations for the whole platform.</p>
                </div>
            </div>

            <?php if ($aiData) { ?>
            <!-- AI Summary Box -->
            <div class="ai-insight-hero p-4 rounded-3 mb-4">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-chat-quote-fill fs-1 text-primary"></i>
                    <div>
                        <h5 class="fw-bold">AI System Summary</h5>
                        <p class="mb-2 fs-5"><?php echo h($aiData['summary']); ?></p>
                        <?php if ($aiData['risk_alert']) { ?>
                            <div class="alert alert-danger py-2 mb-2"><?php echo h($aiData['risk_alert']); ?></div>
                        <?php } ?>
                        <p class="mb-1"><strong>Recommendation:</strong> <?php echo h($aiData['recommendation']); ?></p>
                        <p class="mb-0"><strong>Trend:</strong> <?php echo h($aiData['trend']); ?></p>
                    </div>
                </div>
            </div>
            <?php } ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="dashboard-card p-3 text-center">
                        <h3 class="text-info mb-0"><?php echo $aiUsedCount; ?></h3>
                        <small class="text-muted">AI-Generated Questions</small>
                        <div class="progress mt-2" style="height:4px">
                            <div class="progress-bar bg-info" style="width: <?php echo $totalQuestions > 0 ? ($aiUsedCount/$totalQuestions)*100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card p-3 text-center">
                        <h3 class="text-primary mb-0"><?php echo $adaptiveResults; ?></h3>
                        <small class="text-muted">Adaptive Exams Taken</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card p-3 text-center">
                        <h3 class="text-warning mb-0"><?php echo $totalQuestions; ?></h3>
                        <small class="text-muted">Total Question Bank</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card p-3 text-center">
                        <h3 class="text-success mb-0">
                            <?php
                            $pCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE status='Pass'"))['c'] ?? 0;
                            $tCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results"))['c'] ?? 0;
                            echo $tCount > 0 ? round(($pCount/$tCount)*100) . '%' : '0%';
                            ?>
                        </h3>
                        <small class="text-muted">Overall Pass Rate</small>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="dashboard-panel">
                        <h5 class="mb-3">Student Skill Distribution</h5>
                        <div class="chart-container chart-container-doughnut"><canvas id="categoryChart" role="img" aria-label="Student skill distribution doughnut chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="dashboard-panel">
                        <h5 class="mb-3">Subject Performance</h5>
                        <div class="chart-container chart-container-md"><canvas id="subjectChartAI" role="img" aria-label="Subject performance bar chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="dashboard-panel">
                        <h5 class="mb-3">Exam Mode Distribution</h5>
                        <div class="chart-container chart-container-doughnut"><canvas id="modeChart" role="img" aria-label="Exam mode distribution pie chart"></canvas></div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="dashboard-panel">
                        <h5 class="mb-3">Student Registrations</h5>
                        <div class="chart-container chart-container-md"><canvas id="studentChartAI" role="img" aria-label="Student registrations line chart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- AI Actions -->
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="questions.php" class="dashboard-card d-block p-4 text-center h-100">
                        <i class="bi bi-question-circle display-6 text-success mb-2"></i>
                        <h6>Question Bank</h6>
                        <small class="text-muted">Manage all questions (<?php echo $aiUsedCount; ?> AI-generated)</small>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="exams.php" class="dashboard-card d-block p-4 text-center h-100">
                        <i class="bi bi-calendar-plus display-6 text-warning mb-2"></i>
                        <h6>Create Exam</h6>
                        <small class="text-muted">Set up exams (Standard or Adaptive)</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof ChartManager === "undefined") return;

    ChartManager.create("categoryChart", {
        type: "doughnut",
        data: {
            labels: [<?php foreach ($catLabels as $l) { echo "'$l',"; } ?>],
            datasets: [{
                data: [<?php echo implode(',', $catValues); ?>],
                backgroundColor: ["#4f46e5", "#ffc107", "#dc3545"]
            }]
        }
    });

    ChartManager.create("subjectChartAI", {
        type: "bar",
        data: {
            labels: [<?php foreach ($subjLabels as $l) { echo "'" . addslashes($l) . "',"; } ?>],
            datasets: [{
                label: "Average %",
                data: [<?php echo implode(',', $subjValues); ?>],
                backgroundColor: "#2454d6"
            }]
        },
        options: { scales: { y: { beginAtZero: true, max: 100 } } }
    });

    ChartManager.create("modeChart", {
        type: "pie",
        data: {
            labels: [<?php foreach ($modeLabels as $l) { echo "'$l',"; } ?>],
            datasets: [{
                data: [<?php echo implode(',', $modeValues); ?>],
                backgroundColor: ["#17a2b8", "#6f42c1"]
            }]
        }
    });

    ChartManager.create("studentChartAI", {
        type: "line",
        data: {
            labels: [<?php foreach ($studentDates as $d) { echo "'$d',"; } ?>],
            datasets: [{
                label: "New Registrations",
                data: [<?php echo implode(',', $studentCounts); ?>],
                borderColor: "#198754",
                tension: 0.3,
                fill: true,
                backgroundColor: "rgba(25,135,84,0.1)"
            }]
        }
    });
});
</script>

<?php include("../app/layouts/admin_footer.php"); mysqli_close($conn); ?>

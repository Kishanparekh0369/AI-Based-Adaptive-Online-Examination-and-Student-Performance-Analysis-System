<?php
require_once("../app/config/database.php");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "About Project";
include("../app/layouts/header.php");
include("../app/layouts/navbar.php");
?>

<section class="page-header">
    <div class="container">
        <span class="badge-eyebrow"><i class="bi bi-info-circle"></i>About</span>
        <h1>About Project</h1>
        <p class="mb-0">A clean, AI-powered internship project for online examinations and student performance analysis.</p>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7" data-reveal="left">
                <span class="section-eyebrow"><i class="bi bi-file-earmark-text"></i>Project Description</span>
                <h2 class="section-title">AI-Based Adaptive Online Examination System</h2>
                <p class="section-lead">This system helps an institute conduct online multiple-choice exams with intelligent adaptivity. It includes admin management, faculty subject control, student registration, timed exams, auto result generation, performance analysis, feedback and reports.</p>
                <p class="mb-0">The project follows simple Core PHP procedural coding and MySQLi database operations so it remains easy to understand, demonstrate and extend — while still delivering real AI features like Groq-powered question generation and Computerized Adaptive Testing (CAT).</p>
            </div>
            <div class="col-lg-5" data-reveal="right">
                <div class="info-card p-4">
                    <h5><i class="bi bi-collection me-2 text-primary"></i>Included Modules</h5>
                    <ul class="module-list mb-0">
                        <li><strong>Home Website</strong> — landing pages &amp; information</li>
                        <li><strong>Admin Module</strong> — full system oversight</li>
                        <li><strong>Faculty Module</strong> — subjects, exams, AI generator</li>
                        <li><strong>Student Module</strong> — registration &amp; exam terminal</li>
                        <li><strong>Question Management</strong> — banks with difficulty levels</li>
                        <li><strong>Results &amp; Reports</strong> — analytics, feedback, exports</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="section-title-row" data-reveal="fade">
            <div>
                <span class="section-eyebrow"><i class="bi bi-people"></i>Roles</span>
                <h2 class="section-title">Three Dedicated Role Workspaces</h2>
            </div>
            <p>Purpose-built portals keep every stakeholder focused on exactly what they need.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-reveal="zoom" data-reveal-delay="0">
                <div class="feature-card p-4 h-100">
                    <span class="icon-box mb-3"><i class="bi bi-shield-check"></i></span>
                    <h5>Admin</h5>
                    <p class="mb-0">Oversees system configuration, faculty accounts, student registry, subjects and global institutional reports.</p>
                </div>
            </div>
            <div class="col-md-4" data-reveal="zoom" data-reveal-delay="90">
                <div class="feature-card p-4 h-100">
                    <span class="icon-box mb-3"><i class="bi bi-person-badge"></i></span>
                    <h5>Faculty</h5>
                    <p class="mb-0">Manages assigned subjects, curates question banks, creates standard &amp; adaptive exams and analyses performance.</p>
                </div>
            </div>
            <div class="col-md-4" data-reveal="zoom" data-reveal-delay="180">
                <div class="feature-card p-4 h-100">
                    <span class="icon-box mb-3"><i class="bi bi-mortarboard"></i></span>
                    <h5>Student</h5>
                    <p class="mb-0">Registers online, takes timed exams with an adaptive engine and receives automated scoring &amp; feedback.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6" data-reveal="left">
                <span class="section-eyebrow"><i class="bi bi-cpu"></i>Technology Stack</span>
                <h2 class="section-title">Built With Modern, Reliable Tools</h2>
                <p class="section-lead">A lightweight but professional stack that runs entirely inside XAMPP — no external services required beyond the Groq AI API.</p>
                <div class="mt-4">
                    <span class="tech-chip"><i class="bi bi-filetype-html"></i>HTML5</span>
                    <span class="tech-chip"><i class="bi bi-filetype-css"></i>CSS3</span>
                    <span class="tech-chip"><i class="bi bi-bootstrap"></i>Bootstrap 5</span>
                    <span class="tech-chip"><i class="bi bi-filetype-js"></i>JavaScript</span>
                    <span class="tech-chip"><i class="bi bi-filetype-php"></i>Core PHP</span>
                    <span class="tech-chip"><i class="bi bi-database"></i>MySQLi</span>
                    <span class="tech-chip"><i class="bi bi-robot"></i>Groq AI (LLaMA 3.3)</span>
                </div>
            </div>
            <div class="col-lg-6" data-reveal="right">
                <div class="info-card p-4">
                    <h5><i class="bi bi-database-check me-2 text-primary"></i>Database Overview</h5>
                    <p class="mb-3">The system uses nine simple MySQL tables: <strong>admin, faculty, students, subjects, questions, exams, exam_questions, student_answers, results</strong> and <strong>feedback</strong>.</p>
                    <p class="mb-0">Relationships connect exams with subjects, questions with subjects, results with students &amp; exams, and feedback with students — keeping the schema readable and easy to extend.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5" data-reveal="fade">
            <span class="section-eyebrow"><i class="bi bi-list-check"></i>Workflow</span>
            <h2 class="section-title">How The Platform Works</h2>
            <p>From setup to results — a straightforward six-step examination lifecycle.</p>
        </div>
        <div class="row g-4">
            <?php
            $steps = array(
                array("bi-person-plus", "Admin Setup", "Admin creates subjects and registers faculty members with assigned subjects."),
                array("bi-key", "Faculty Login", "Faculty logs in to manage questions and generate AI-powered MCQs."),
                array("bi-file-earmark-plus", "Exam Creation", "Faculty creates standard or adaptive exams for their assigned subjects."),
                array("bi-clock-history", "Student Attempt", "Student registers, logs in and attempts scheduled exams with a real-time timer."),
                array("bi-calculator", "Instant Scoring", "System computes scores, percentage, pass/fail status and insights instantly."),
                array("bi-graph-up", "Analytics", "Faculty and Admin evaluate granular subject performance and analytics.")
            );
            foreach ($steps as $i => $step) {
            ?>
                <div class="col-md-6 col-lg-4" data-reveal="zoom" data-reveal-delay="<?php echo ($i % 3) * 90; ?>">
                    <div class="feature-card p-4 h-100">
                        <span class="feature-number"><?php echo str_pad($i + 1, 2, "0", STR_PAD_LEFT); ?></span>
                        <span class="icon-box mb-3"><i class="bi <?php echo $step[0]; ?>"></i></span>
                        <h5><?php echo $step[1]; ?></h5>
                        <p class="mb-0"><?php echo $step[2]; ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<?php include("../app/layouts/footer.php"); mysqli_close($conn); ?>

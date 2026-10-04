<?php
require_once __DIR__ . "/app/config/database.php";

$conn = conn();
$asset_path = "assets/";
$base_path = "";
$page_title = "Home - AI-Based Adaptive Online Examination System";
include __DIR__ . "/app/layouts/header.php";
include __DIR__ . "/app/layouts/navbar.php";

$student_count = 0;
$faculty_count = 0;
$subject_count = 0;
$question_count = 0;
$exam_count = 0;

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM students");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $student_count = $row["total"];
}
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM faculty");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $faculty_count = $row["total"];
}
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM subjects");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $subject_count = $row["total"];
}
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM questions");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $question_count = $row["total"];
}
$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM exams");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $exam_count = $row["total"];
}
?>

<section class="hero-section">
    <div class="hero-glow hero-glow-1"></div>
    <div class="hero-glow hero-glow-2"></div>
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Hero Content: order-1 on mobile, stays first on desktop -->
            <div class="col-lg-7 order-1">
                <span class="hero-badge"><i class="bi bi-stars"></i>Enterprise Multi-Role Platform</span>
                <h1 class="display-5 fw-bold">
                    AI-Based Adaptive Online Examination &amp; <span class="text-gradient">Student Performance Analysis</span> System
                </h1>
                <p class="lead mt-3">A multi-role examination platform for <strong>Administrators</strong>, <strong>Faculty</strong>, and <strong>Students</strong> — featuring subject-based exam creation, AI question generation, and real-time performance analytics.</p>
                <div class="d-flex gap-3 flex-wrap mt-4">
                    <a href="public/login.php" class="btn btn-hero-login btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Portal
                    </a>
                    <a href="student/register.php" class="btn btn-outline-light btn-lg"><i class="bi bi-person-plus me-2"></i>Student Register</a>
                </div>
                <div class="hero-trust">
                    <span><i class="bi bi-check-circle-fill"></i>Groq AI Question Generation</span>
                    <span><i class="bi bi-check-circle-fill"></i>Adaptive CAT Algorithm</span>
                    <span><i class="bi bi-check-circle-fill"></i>Real-time Analytics</span>
                </div>
            </div>
            <!-- Stats Grid: order-2 on mobile (below hero), order-last on desktop stays on right -->
            <div class="col-lg-5 order-2 order-lg-last hero-stats-col">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-box" style="--si:0">
                            <h2><span data-count="<?php echo $faculty_count; ?>" data-suffix="+">0</span></h2>
                            <p class="mb-0">Faculty</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box" style="--si:1">
                            <h2><span data-count="<?php echo $student_count; ?>" data-suffix="+">0</span></h2>
                            <p class="mb-0">Students</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box" style="--si:2">
                            <h2><span data-count="<?php echo $subject_count; ?>" data-suffix="+">0</span></h2>
                            <p class="mb-0">Subjects</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box" style="--si:3">
                            <h2><span data-count="<?php echo $exam_count; ?>" data-suffix="+">0</span></h2>
                            <p class="mb-0">Exams</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding bg-white" id="overview">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6" data-reveal="left">
                <span class="section-eyebrow"><i class="bi bi-diagram-3"></i>Overview</span>
                <h2 class="section-title">Project Overview</h2>
                <p>This system delivers a comprehensive 3-role academic examination environment. <strong>Admin</strong> oversees system configuration, faculty accounts, student registries, and institutional analytics. <strong>Faculty</strong> manages their assigned subjects, crafts standard and AI-adaptive exams, curate question banks, and analyzes student progress. <strong>Students</strong> take timed online exams and review automated performance feedback.</p>
                <p class="mb-0">The platform features real AI capabilities: adaptive question selection (CAT algorithm), Groq AI question generation, student analytics dashboards, and role-based subject permissions.</p>
            </div>
            <div class="col-lg-6" data-reveal="right">
                <div class="info-card p-4">
                    <h5>Role Architecture</h5>
                    <ul class="mb-0">
                        <li><strong>Admin:</strong> System administration, faculty management, and global reports.</li>
                        <li><strong>Faculty:</strong> Assigned subject control, exam creation, AI generation, and performance.</li>
                        <li><strong>Student:</strong> Online exams, adaptive testing, automated scoring, and feedback.</li>
                        <li><strong>Technology:</strong> Core PHP, MySQLi, Bootstrap 5, HTML5, CSS3, JavaScript, and Groq API.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5" data-reveal="fade">
            <h2 class="section-title">Key Features by Role</h2>
            <p>Purpose-built workspaces designed for each stakeholder in the examination lifecycle.</p>
        </div>
        <div class="row g-4">
            <?php
            $features = array(
                array("bi-shield-check", "Admin Control Center", "Manage faculty accounts, assign subjects, configure students, and oversee institutional metrics."),
                array("bi-person-badge", "Faculty Workspace", "Dedicated portal for faculty to manage assigned subjects, quizzes, and student evaluations."),
                array("bi-robot", "AI Question Generation", "Generate subject-specific multiple choice questions dynamically powered by LLaMA AI via Groq API."),
                array("bi-patch-question", "Subject Question Bank", "Categorize MCQs across Easy, Medium, and Hard difficulty levels per assigned subject."),
                array("bi-sliders", "Adaptive Testing (CAT)", "AI adjusts question difficulty dynamically in real-time according to student performance."),
                array("bi-graph-up", "Performance Analysis", "Drill-down analytics, pass/fail splits, score breakdowns, and downloadable PDF/CSV reports.")
            );
            foreach ($features as $index => $item) {
            ?>
                <div class="col-md-6 col-lg-4" data-reveal="zoom" data-reveal-delay="<?php echo ($index % 3) * 90; ?>">
                    <div class="feature-card p-4 h-100">
                        <span class="icon-box mb-3"><i class="bi <?php echo $item[0]; ?>"></i></span>
                        <h5><?php echo $item[1]; ?></h5>
                        <p class="mb-0"><?php echo $item[2]; ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6" data-reveal="left">
                <h2 class="section-title">Platform Workflow</h2>
                <div class="list-group shadow-sm">
                    <div class="list-group-item">1. Admin creates subjects and registers faculty members with assigned subjects.</div>
                    <div class="list-group-item">2. Faculty logs in to manage questions and generate AI-powered MCQs.</div>
                    <div class="list-group-item">3. Faculty creates standard or adaptive exams for their assigned subjects.</div>
                    <div class="list-group-item">4. Student registers, logs in, and attempts the scheduled exams with real-time timer.</div>
                    <div class="list-group-item">5. System computes instantaneous scores, percentage, pass/fail status, and insights.</div>
                    <div class="list-group-item">6. Faculty and Admin evaluate granular subject performance and analytics.</div>
                </div>
            </div>
            <div class="col-lg-6" data-reveal="right">
                <h2 class="section-title">Technology Stack</h2>
                <div class="row g-3">
                    <div class="col-6" data-reveal="zoom" data-reveal-delay="40">
                        <div class="info-card p-3"><i class="bi bi-filetype-html me-2 text-primary"></i>HTML5</div>
                    </div>
                    <div class="col-6" data-reveal="zoom" data-reveal-delay="90">
                        <div class="info-card p-3"><i class="bi bi-filetype-css me-2 text-primary"></i>CSS3</div>
                    </div>
                    <div class="col-6" data-reveal="zoom" data-reveal-delay="140">
                        <div class="info-card p-3"><i class="bi bi-bootstrap me-2 text-primary"></i>Bootstrap 5</div>
                    </div>
                    <div class="col-6" data-reveal="zoom" data-reveal-delay="190">
                        <div class="info-card p-3"><i class="bi bi-filetype-js me-2 text-primary"></i>JavaScript</div>
                    </div>
                    <div class="col-6" data-reveal="zoom" data-reveal-delay="240">
                        <div class="info-card p-3"><i class="bi bi-filetype-php me-2 text-primary"></i>Core PHP</div>
                    </div>
                    <div class="col-6" data-reveal="zoom" data-reveal-delay="290">
                        <div class="info-card p-3"><i class="bi bi-database me-2 text-primary"></i>MySQLi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4" data-reveal="left">
                <h2 class="section-title">Modules Overview</h2>
                <p>The system is organized into modular role-based sections with dedicated security boundaries.</p>
            </div>
            <div class="col-lg-8" data-reveal="right">
                <div class="row g-3">
                    <div class="col-md-6" data-reveal="zoom" data-reveal-delay="40">
                        <div class="info-card p-3"><strong>Admin Module:</strong> System control, faculty accounts, student management, subjects, global reports.</div>
                    </div>
                    <div class="col-md-6" data-reveal="zoom" data-reveal-delay="100">
                        <div class="info-card p-3"><strong>Faculty Module:</strong> Assigned subject control, exam creation, AI question bank, student analytics.</div>
                    </div>
                    <div class="col-md-6" data-reveal="zoom" data-reveal-delay="160">
                        <div class="info-card p-3"><strong>Student Module:</strong> Registration, profile, live exam terminal, result cards, and history.</div>
                    </div>
                    <div class="col-md-6" data-reveal="zoom" data-reveal-delay="220">
                        <div class="info-card p-3"><strong>Analytics & AI:</strong> CAT adaptive engine, Groq AI generator, performance analyzer, and export.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6" data-reveal="left">
                <h2 class="section-title">Database Overview</h2>
                <p>The system uses nine simple MySQL tables: admin, students, subjects, questions, exams, exam_questions, student_answers, results, and feedback.</p>
                <p class="mb-0">Relationships connect exams with subjects, questions with subjects, results with students and exams, and feedback with students.</p>
            </div>
            <div class="col-lg-6" data-reveal="right">
                <h2 class="section-title">Advantages</h2>
                <ul class="mb-0">
                    <li>Easy to install and run in XAMPP.</li>
                    <li>Simple procedural PHP code suitable for beginners.</li>
                    <li>Professional responsive UI with Bootstrap 5.</li>
                    <li>Automatic result calculation reduces manual work.</li>
                    <li>Expandable for future MCA project features.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row g-4">
          <div class="col-lg-6" data-reveal="left">
    <div class="info-card p-4 h-100">
        <h2 class="section-title mb-4">Developer Information</h2>

        <!-- Developer Info -->
        <div class="row align-items-center g-4">
            
            <!-- Image -->
            <div class="col-12 col-md-4 text-center">
                <img src="assets/images/me .jpeg"
                    alt="Kishan Parekh"
                    class="img-fluid rounded-circle border border-3 border-primary shadow"
                    style="width:180px; height:180px; object-fit:cover;">
            </div>

            <!-- Details -->
            <div class="col-12 col-md-8">
                <h4 class="fw-bold mb-2">Kishan Parekh</h4>

                <p class="text-primary fw-semibold mb-2">
                    MCA Student | Full Stack PHP Developer
                </p>

                <p class="text-muted mb-3">
                    Passionate Full Stack Developer with hands-on experience in
                    PHP, MySQL, JavaScript, Bootstrap, and modern web technologies.
                    I enjoy building responsive, user-friendly, and database-driven
                    web applications with clean UI and efficient backend logic.
                </p>

                <div class="row">
                    <div class="col-sm-6 mb-2">
                        <strong>Course:</strong><br>
                        Master of Computer Applications (MCA)
                    </div>

                    <div class="col-sm-6 mb-2">
                        <strong>Project Type:</strong><br>
                        6-Week Summer Research Internship
                    </div>

                    <div class="col-sm-6 mb-2">
                        <strong>College:</strong><br>
                        Post Graduate Department of Computer Science & Technology,<br>
                        Sardar Patel University, Vallabh Vidyanagar
                    </div>

                    <div class="col-sm-6 mb-2">
                        <strong>Guide:</strong><br>
                        Dr. Priti S. Sajja Mam
                    </div>
                </div>
            </div>

        </div>

        <hr class="my-4">

        <!-- Additional Details -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Name:</strong><br>
                Kishan Parekh
            </div>

            <div class="col-md-6 mb-3">
                <strong>Role:</strong><br>
                Full Stack Core PHP Developer
            </div>

            <div class="col-md-6 mb-3">
                <strong>Technologies:</strong><br>
                PHP, MySQL, HTML5, CSS3,<br>
                JavaScript, Bootstrap, AJAX
            </div>

            <div class="col-md-6 mb-3">
                <strong>Specialization:</strong><br>
                AI-Based Adaptive Examination System
            </div>
        </div>
    </div>
</div>
            <div class="col-lg-6" data-reveal="right">
                <div class="info-card p-4 h-100">
                    <h2 class="section-title">FAQ</h2>
                    <div class="accordion" id="faqBox">
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#faq1">Does this project use real AI?</button></h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqBox">
                                <div class="accordion-body">Yes! The system uses Groq API (LLaMA 3.3 70B) for AI question generation, adaptive difficulty algorithm (Computerized Adaptive Testing), personalized performance feedback, and smart analytics. AI is integrated throughout the system.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq2">Can it run in XAMPP?</button></h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqBox">
                                <div class="accordion-body">Yes. Import database.sql, then open the project from localhost.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq3">Which logins and roles are available?</button></h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqBox">
                                <div class="accordion-body">Three dedicated roles are supported: <strong>Admin</strong> (overall system, faculty & student administration), <strong>Faculty</strong> (subject-specific exam creation, AI generation & performance evaluation), and <strong>Student</strong> (exam taking & automated results).</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="cta-banner text-center" data-reveal="zoom">
            <span class="section-eyebrow" style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.25);color:#E0E7FF;"><i class="bi bi-chat-dots"></i>Get In Touch</span>
            <h2 class="section-title">Contact Details</h2>
            <p class="mb-4">Email: kishanparekh947@gmail.com &nbsp;|&nbsp; Phone: +91 93163 61979 &nbsp;|&nbsp; Location: Anand, Gujarat</p>
            <a href="public/contact.php" class="btn btn-hero-login btn-lg"><i class="bi bi-send me-2"></i>Send Feedback</a>
        </div>
    </div>
</section>

<?php include __DIR__ . "/app/layouts/footer.php";
mysqli_close($conn); ?>
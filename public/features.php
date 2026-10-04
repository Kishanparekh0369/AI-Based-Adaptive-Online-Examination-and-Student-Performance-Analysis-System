<?php
require_once("../app/config/database.php");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Features";
include("../app/layouts/header.php");
include("../app/layouts/navbar.php");
?>

<section class="page-header">
    <div class="container">
        <span class="badge-eyebrow"><i class="bi bi-stars"></i>Features</span>
        <h1>Platform Features</h1>
        <p class="mb-0">Main capabilities included in this internship version — organised by the role that uses them.</p>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5" data-reveal="fade">
            <span class="section-eyebrow"><i class="bi bi-grid-1x2"></i>All Capabilities</span>
            <h2 class="section-title">Everything The System Does</h2>
            <p>Simple, validated and database-connected functionality across all three roles.</p>
        </div>
        <div class="row g-4">
            <?php
            $items = array(
                array("bi-person-plus", "Student Registration & Login"),
                array("bi-shield-check", "Admin Dashboard"),
                array("bi-journal-bookmark", "Subject CRUD"),
                array("bi-patch-question", "Question Bank CRUD"),
                array("bi-file-earmark-plus", "Exam Creation"),
                array("bi-clock-history", "Timer Based Exam"),
                array("bi-calculator", "Automatic Result"),
                array("bi-graph-up", "Performance Analysis"),
                array("bi-chat-left-text", "Student Feedback"),
                array("bi-file-earmark-arrow-down", "Reports & Exports")
            );
            foreach ($items as $i => $item) {
            ?>
                <div class="col-md-6 col-lg-4" data-reveal="zoom" data-reveal-delay="<?php echo ($i % 3) * 80; ?>">
                    <div class="feature-card p-4 h-100">
                        <span class="feature-number"><?php echo str_pad($i + 1, 2, "0", STR_PAD_LEFT); ?></span>
                        <span class="icon-box mb-3"><i class="bi <?php echo $item[0]; ?>"></i></span>
                        <h5><?php echo $item[1]; ?></h5>
                        <p class="mb-0">Simple, validated and database-connected functionality.</p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="section-title-row" data-reveal="fade">
            <div>
                <span class="section-eyebrow"><i class="bi bi-robot"></i>AI Powered</span>
                <h2 class="section-title">Intelligent Features</h2>
            </div>
            <p>Real AI integration — not just a label. The platform generates and adapts questions automatically.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4" data-reveal="left">
                <div class="feature-card p-4 h-100">
                    <span class="icon-box mb-3"><i class="bi bi-robot"></i></span>
                    <h5>AI Question Generation</h5>
                    <p class="mb-0">Groq API (LLaMA 3.3 70B) generates subject-specific MCQs on demand with editable options and difficulty tagging.</p>
                </div>
            </div>
            <div class="col-lg-4" data-reveal="zoom" data-reveal-delay="100">
                <div class="feature-card p-4 h-100">
                    <span class="icon-box mb-3"><i class="bi bi-sliders"></i></span>
                    <h5>Adaptive Testing (CAT)</h5>
                    <p class="mb-0">Computerized Adaptive Testing adjusts question difficulty in real-time based on the student's running performance.</p>
                </div>
            </div>
            <div class="col-lg-4" data-reveal="right">
                <div class="feature-card p-4 h-100">
                    <span class="icon-box mb-3"><i class="bi bi-graph-up"></i></span>
                    <h5>Smart Analytics</h5>
                    <p class="mb-0">Drill-down subject analytics, score breakdowns, pass/fail splits and downloadable PDF/CSV reports.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="cta-banner text-center" data-reveal="zoom">
            <span class="section-eyebrow" style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.25);color:#E0E7FF;"><i class="bi bi-box-arrow-in-right"></i>Ready To Explore</span>
            <h2 class="section-title">Experience The Platform</h2>
            <p class="mb-4">Sign in to the portal or register as a student to try the adaptive exam engine.</p>
            <a href="../public/login.php" class="btn btn-hero-login btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In Portal</a>
        </div>
    </div>
</section>

<?php include("../app/layouts/footer.php");
mysqli_close($conn); ?>

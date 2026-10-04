<footer class="footer-section text-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6 col-lg-4" data-reveal="fade">
                <a class="footer-brand" href="<?php echo isset($base_path) ? $base_path : ''; ?>index.php" aria-label="AI Exam System home">
                    <span class="footer-logo"><i class="bi bi-mortarboard-fill"></i></span>
                    <span class="footer-brand-text">AI Exam <em>System</em></span>
                </a>
                <p class="footer-about mb-3">AI-Based Adaptive Online Examination and Student Performance Analysis System — a professional internship project built with Core PHP, MySQLi, Bootstrap 5, HTML5, CSS3, and JavaScript.</p>
                <div class="social-icons" aria-label="Contact channels">
                    <a href="mailto:kishanparekh947@gmail.com" title="Email" aria-label="Email"><i class="bi bi-envelope-fill"></i></a>
                    <a href="tel:+919316361979" title="Phone" aria-label="Phone"><i class="bi bi-telephone-fill"></i></a>
                    <a href="https://maps.google.com/?q=Anand,Gujarat,India" target="_blank" rel="noopener" title="Location" aria-label="Location"><i class="bi bi-geo-alt-fill"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2" data-reveal="fade" data-reveal-delay="90">
                <h6>Quick Links</h6>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>index.php"><i class="bi bi-chevron-right"></i>Home</a>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>public/about.php"><i class="bi bi-chevron-right"></i>About Project</a>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>public/features.php"><i class="bi bi-chevron-right"></i>Features</a>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>public/contact.php"><i class="bi bi-chevron-right"></i>Contact</a>
            </div>
            <div class="col-6 col-md-3 col-lg-2" data-reveal="fade" data-reveal-delay="180">
                <h6>Portals</h6>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>public/login.php"><i class="bi bi-chevron-right"></i>Sign In</a>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>student/register.php"><i class="bi bi-chevron-right"></i>Student Register</a>
                <a href="<?php echo isset($base_path) ? $base_path : ''; ?>public/contact.php"><i class="bi bi-chevron-right"></i>Send Feedback</a>
            </div>
            <div class="col-md-12 col-lg-4" data-reveal="fade" data-reveal-delay="270">
                <h6>Get In Touch</h6>
                <p class="footer-contact"><i class="bi bi-envelope"></i><a href="mailto:kishanparekh947@gmail.com">kishanparekh947@gmail.com</a></p>
                <p class="footer-contact"><i class="bi bi-telephone"></i><a href="tel:+919316361979">+91 93163 61979</a></p>
                <p class="footer-contact mb-0"><i class="bi bi-geo-alt"></i><span>Anand, Gujarat, India</span></p>
            </div>
        </div>
        <hr>
        <div class="footer-bottom">
            <p class="mb-0">Copyright &copy; <?php echo date("Y"); ?> AI Exam System. All Rights Reserved.</p>
            <p class="mb-0">Designed &amp; Developed by <strong>Kishan Parekh</strong></p>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>js/script.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/script.js'); ?>"></script>
<?php render_flash_alert(); ?>
<button id="scrollTopBtn" class="scroll-top-btn" aria-label="Scroll to top" title="Back to top">
    <i class="bi bi-arrow-up"></i>
</button>
</body>
</html>

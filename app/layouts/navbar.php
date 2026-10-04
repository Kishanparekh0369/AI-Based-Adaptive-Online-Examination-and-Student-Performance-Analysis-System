<nav class="navbar navbar-expand-lg navbar-dark site-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo isset($base_path) ? $base_path : ''; ?>index.php">
            <i class="bi bi-mortarboard-fill me-2"></i>AI Exam System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>public/about.php">About Project</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>public/features.php">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo isset($base_path) ? $base_path : ''; ?>public/contact.php">Contact</a></li>
                <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                    <a class="btn-navbar-login" href="<?php echo isset($base_path) ? $base_path : ''; ?>public/login.php">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Login</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

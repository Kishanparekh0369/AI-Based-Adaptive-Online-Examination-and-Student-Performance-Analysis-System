<?php
require_once("../app/config/database.php");

$conn = conn();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Contact";

if($_SERVER["REQUEST_METHOD"]=="POST")
{
    $name = clean_data($_POST["name"]);
    $email = clean_data($_POST["email"]);
    $subject = clean_data($_POST["subject"]);
    $message = clean_data($_POST["message"]);

    if($name=="" || $email=="" || $subject=="" || $message=="")
    {
        redirect_alert("All fields are required", "contact.php");
    }
    else if(!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        redirect_alert("Enter valid email", "contact.php");
    }
    else
    {
        mysqli_query($conn, "INSERT INTO contact_messages (name,email,subject,message) VALUES ('$name','$email','$subject','$message')");
        notify_user("admin", null, "New Contact Message", $name . " sent a contact message.", "contact_messages.php");
        redirect_alert("Your message has been submitted successfully", "contact.php");
    }
}

include("../app/layouts/header.php");
include("../app/layouts/navbar.php");
?>

<section class="page-header">
    <div class="container">
        <span class="badge-eyebrow"><i class="bi bi-envelope"></i>Contact</span>
        <h1>Get In Touch</h1>
        <p class="mb-0">Send a message or use the project contact details below — I usually respond within 24 hours.</p>
    </div>
</section>

<section class="section-padding bg-white">
    <div class="container">
        <div class="row g-4 mb-5">
            <div class="col-md-4" data-reveal="zoom" data-reveal-delay="0">
                <div class="contact-tile">
                    <span class="contact-tile-icon"><i class="bi bi-envelope-fill"></i></span>
                    <div>
                        <h6>Email</h6>
                        <p>kishanparekh947@gmail.com</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-reveal="zoom" data-reveal-delay="90">
                <div class="contact-tile">
                    <span class="contact-tile-icon"><i class="bi bi-telephone-fill"></i></span>
                    <div>
                        <h6>Phone</h6>
                        <p>+91 93163 61979</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-reveal="zoom" data-reveal-delay="180">
                <div class="contact-tile">
                    <span class="contact-tile-icon"><i class="bi bi-geo-alt-fill"></i></span>
                    <div>
                        <h6>Location</h6>
                        <p>Anand, Gujarat, India</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5" data-reveal="left">
                <div class="info-card p-4 h-100">
                    <h4 class="fw-bold mb-3">Send Me A Message</h4>
                    <p class="text-muted">Have a question about the AI-Based Adaptive Online Examination System, the source code, or a demo? Fill out the form and I'll get back to you.</p>
                    <hr>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="contact-tile-icon"><i class="bi bi-person-fill"></i></span>
                        <div>
                            <h6 class="mb-0" style="text-transform:none;letter-spacing:0;font-size:0.95rem;">Kishan Parekh</h6>
                            <small class="text-muted">Full Stack Core PHP Developer</small>
                        </div>
                    </div>
                    <p class="text-muted mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>MCA Student, Sardar Patel University</p>
                </div>
            </div>
            <div class="col-lg-7" data-reveal="right">
                <form method="post" class="form-card contact-form-card p-4">
                    <h4 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2 text-primary"></i>Contact Form</h4>
                    <div class="form-floating mb-3"><input type="text" name="name" class="form-control" id="contactName" placeholder="Name" required><label for="contactName">Name</label></div>
                    <div class="form-floating mb-3"><input type="email" name="email" class="form-control" id="contactEmail" placeholder="Email" required><label for="contactEmail">Email</label></div>
                    <div class="form-floating mb-3"><input type="text" name="subject" class="form-control" id="contactSubject" placeholder="Subject" required><label for="contactSubject">Subject</label></div>
                    <div class="form-floating mb-3"><textarea name="message" class="form-control" id="contactMessage" placeholder="Message" rows="5" maxlength="1000" required></textarea><label for="contactMessage">Message</label></div>
                    <button class="btn btn-submit-gradient no-loader"><i class="bi bi-send me-2"></i>Send Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include("../app/layouts/footer.php"); mysqli_close($conn); ?>

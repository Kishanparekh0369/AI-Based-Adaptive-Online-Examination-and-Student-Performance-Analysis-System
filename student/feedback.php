<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Feedback";
$student_id = (int)$_SESSION["student_id"];
$student_name = isset($_SESSION["student_name"]) ? (string)$_SESSION["student_name"] : "Student";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = clean_data($_POST["message"]);
    $rating = (int)($_POST["rating"] ?? 0);
    if ($message == "" || $rating < 1 || $rating > 5) {
        redirect_alert("Please select a rating and enter a message", "feedback.php");
    }
    if (strlen($message) > 1000) {
        redirect_alert("Feedback message must be under 1000 characters", "feedback.php");
    }
    $stmt = mysqli_prepare($conn, "INSERT INTO feedback (student_id, message, rating) VALUES (?, ?, ?)");
    if (!$stmt) {
        redirect_alert("Unable to submit feedback right now. Please try again.", "feedback.php");
    }
    mysqli_stmt_bind_param($stmt, "isi", $student_id, $message, $rating);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$saved) {
        redirect_alert("Unable to submit feedback right now. Please try again.", "feedback.php");
    }
    notify_user("admin", null, "Feedback Submitted", $_SESSION["student_name"] . " submitted feedback.", "feedback.php");
    notify_user("student", $student_id, "Feedback Sent", "Your feedback was submitted successfully.", "feedback.php");
    flash_script("Thank you for helping us improve the Student Portal.", "feedback.php", "success", "Feedback Submitted Successfully!");
    header("Location: feedback.php");
    exit;
}

$feedbacks = array();
$fstmt = mysqli_prepare($conn, "SELECT id, message, rating, created_at FROM feedback WHERE student_id=? ORDER BY id DESC");
if ($fstmt) {
    mysqli_stmt_bind_param($fstmt, "i", $student_id);
    mysqli_stmt_execute($fstmt);
    $fres = mysqli_stmt_get_result($fstmt);
    if ($fres) {
        while ($frow = mysqli_fetch_assoc($fres)) { $feedbacks[] = $frow; }
    }
    mysqli_stmt_close($fstmt);
}
$cnt = count($feedbacks);
$avg_rating = 0;
if ($cnt > 0) {
    $sum = 0;
    foreach ($feedbacks as $fb) { $sum += (int)$fb["rating"]; }
    $avg_rating = round($sum / $cnt, 1);
}
$rating_words = array(1 => "Poor", 2 => "Average", 3 => "Good", 4 => "Very Good", 5 => "Excellent");

include("../app/layouts/student_header.php");
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10">
            <div class="sp-main">
                <div class="sp-page-head sp-fade-up">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-chat-square-quote"></i> Feedback</span>
                        <h1>Share Your Feedback</h1>
                        <p>Your feedback helps us improve the examination experience.</p>
                    </div>
                    <div class="sp-head-chip"><i class="bi bi-person-circle"></i> Submitting as <strong><?php echo h($student_name); ?></strong></div>
                </div>

                <div class="row g-4 align-items-start">
                    <div class="col-lg-5 sp-fade-up">
                        <div class="sp-panel sp-fb-form-card">
                            <div class="sp-fb-form-head">
                                <span class="sp-fb-form-icon"><i class="bi bi-pencil-square"></i></span>
                                <div>
                                    <h3>Submit Feedback</h3>
                                    <p>Tell us about your learning experience.</p>
                                </div>
                            </div>
                            <form method="post" id="spFeedbackForm" novalidate>
                                <div class="mb-4">
                                    <label class="sp-fb-label">Overall Rating <span class="text-danger">*</span></label>
                                    <div class="sp-stars" id="spStars" role="radiogroup" aria-label="Overall rating">
                                        <input type="radio" name="rating" value="5" id="star5">
                                        <label for="star5" title="Excellent"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">5 - Excellent</span></label>
                                        <input type="radio" name="rating" value="4" id="star4">
                                        <label for="star4" title="Very Good"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">4 - Very Good</span></label>
                                        <input type="radio" name="rating" value="3" id="star3">
                                        <label for="star3" title="Good"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">3 - Good</span></label>
                                        <input type="radio" name="rating" value="2" id="star2">
                                        <label for="star2" title="Average"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">2 - Average</span></label>
                                        <input type="radio" name="rating" value="1" id="star1">
                                        <label for="star1" title="Poor"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">1 - Poor</span></label>
                                    </div>
                                    <div class="sp-rating-hint" id="spRatingHint">Tap a star to rate</div>
                                    <p class="sp-field-error" id="spRatingErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                </div>
                                <div class="mb-3">
                                    <label class="sp-fb-label" for="spFbMessage">Your Message <span class="text-danger">*</span></label>
                                    <textarea name="message" id="spFbMessage" class="form-control sp-textarea" rows="5" maxlength="1000" placeholder="Share your experience, suggestions, or concerns..." required aria-describedby="spMsgErr"></textarea>
                                    <div class="sp-counter"><span id="spFbCount">0</span> / 1000</div>
                                    <p class="sp-field-error" id="spMsgErr"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span></p>
                                </div>
                                <button type="submit" class="sp-btn sp-btn-primary w-100" id="spFbSubmit">
                                    <i class="bi bi-send-fill"></i> Submit Feedback
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-7 sp-fade-up" data-delay="1">
                        <div class="sp-panel">
                            <div class="sp-panel-head sp-fb-history-head">
                                <h3><i class="bi bi-clock-history me-1 text-primary"></i>Your Feedback History</h3>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ($cnt > 0) { ?><span class="sp-fb-avg"><i class="bi bi-star-fill"></i><?php echo $avg_rating; ?>/5 average</span><?php } ?>
                                    <span class="sp-fb-count"><?php echo $cnt; ?> submission<?php echo $cnt !== 1 ? "s" : ""; ?></span>
                                </div>
                            </div>
                            <?php if ($cnt > 0) { ?>
                                <div class="sp-fb-list">
                                    <?php $fi = 0; foreach ($feedbacks as $row) { $fi++; $rv = max(1, min(5, (int)$row["rating"])); ?>
                                        <article class="sp-fb-card sp-fade-up" data-delay="<?php echo min($fi, 6); ?>">
                                            <div class="sp-fb-top">
                                                <span class="sp-stars-static" aria-label="Rated <?php echo $rv; ?> out of 5">
                                                    <?php for ($s = 1; $s <= 5; $s++) { ?><i class="bi <?php echo $s <= $rv ? "bi-star-fill" : "bi-star"; ?>" aria-hidden="true"></i><?php } ?>
                                                </span>
                                                <span class="sp-fb-word"><?php echo h($rating_words[$rv]); ?></span>
                                                <span class="sp-fb-date"><i class="bi bi-calendar3"></i><?php echo h(date("d M Y, h:i A", strtotime($row["created_at"]))); ?></span>
                                            </div>
                                            <p class="sp-fb-msg"><?php echo h($row["message"]); ?></p>
                                        </article>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="sp-empty">
                                    <div class="sp-empty-icon"><i class="bi bi-chat-square-dots"></i></div>
                                    <h4>No Feedback Submitted Yet</h4>
                                    <p>Share your experience with the Student Portal.</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../app/layouts/student_footer.php");
mysqli_close($conn); ?>

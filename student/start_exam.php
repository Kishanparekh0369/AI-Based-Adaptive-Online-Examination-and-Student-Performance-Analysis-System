<?php
require_once("../app/config/database.php");

$conn = conn();require_student();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$student_id = $_SESSION["student_id"];
if (!isset($_GET["id"])) {
    redirect_alert("Invalid exam", "exams.php");
}

$exam_id = clean_data($_GET["id"]);
$check = mysqli_query($conn, "SELECT * FROM results WHERE student_id='$student_id' AND exam_id='$exam_id'");
if ($check && mysqli_num_rows($check) > 0) {
    redirect_alert("You have already submitted this exam", "result.php");
}
if (exam_blocked($student_id, $exam_id)) {
    redirect_alert("This exam attempt has been blocked", "exams.php", "warning");
}
$active_key = "active_exam_" . (int)$exam_id;
if (!empty($_SESSION[$active_key])) {
    block_exam_attempt($student_id, $exam_id, "Page refresh or reopen");
    unset($_SESSION[$active_key]);
    redirect_alert("This exam attempt has been blocked", "exams.php", "warning");
}

$exam_result = mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE e.id='$exam_id' AND e.status='Active'");
if (!$exam_result || mysqli_num_rows($exam_result) == 0) {
    redirect_alert("Exam not available", "exams.php");
}
$exam = mysqli_fetch_assoc($exam_result);
// Academic eligibility re-check (server-side; never trust the listing page)
$scope_check = student_exam_eligible($student_id, (int)$exam["id"]);
if (!$scope_check["ok"]) {
    redirect_alert($scope_check["reason"] !== "" ? $scope_check["reason"] : "This examination is not available for your academic program or semester.", "exams.php", "warning");
}
if (($exam["exam_mode"] ?? "Standard") == "Adaptive") {
    header("Location: adaptive_exam.php?id=" . urlencode($exam_id));
    exit;
}

$questions_result = mysqli_query($conn, "SELECT q.* FROM exam_questions eq LEFT JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id='$exam_id' ORDER BY eq.id ASC");
$questions = array();
while ($row = mysqli_fetch_assoc($questions_result)) {
    $questions[] = $row;
}

if (count($questions) == 0) {
    redirect_alert("No questions are assigned to this exam", "exams.php");
}

$_SESSION[$active_key] = 1;

$duration_seconds = max((int)$exam["duration"], 1) * 60;
$student_name = isset($_SESSION["student_name"]) ? $_SESSION["student_name"] : "Student";
$exam_shell_mode = "standard";
$exam_shell_action = "submit_exam.php";
$exam_shell_questions = $questions;
$exam_shell_total = count($questions);
$exam_shell_exam_id = $exam_id;
$exam_shell_student_id = $student_id;
$exam_shell_student_name = $student_name;
$exam_shell_title_suffix = "Exam Portal";
require("../app/layouts/exam_shell.php");
?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    var totalQuestions = <?php echo count($questions); ?>;
    var examId = "<?php echo h($exam_id); ?>";
    var studentId = "<?php echo h($student_id); ?>";
    var duration = <?php echo $duration_seconds; ?>;
    var storageKey = "exam_state_" + studentId + "_" + examId;
    var current = 0;
    var submitting = false;
    var state = JSON.parse(localStorage.getItem(storageKey) || "{}");
    state.answers = state.answers || {};
    state.statuses = state.statuses || {};
    state.startedAt = state.startedAt || Date.now();
    state.endsAt = state.endsAt || (Date.now() + duration * 1000);

    var cards = document.querySelectorAll(".exam-question-card");
    var palette = document.querySelectorAll(".palette-btn");
    var timer = document.getElementById("examTimer");
    var autosaveStatus = document.getElementById("autosaveStatus");
    var progress = document.getElementById("examProgress");

    Object.keys(state.answers).forEach(function(questionId){
        var input = document.querySelector("input[name='answer[" + questionId + "]'][value='" + state.answers[questionId] + "']");
        if(input){ input.checked = true; }
    });

    function syncCurrentAnswer()
    {
        var card = cards[current];
        if(!card){ return; }
        var qid = card.dataset.questionId;
        var checked = card.querySelector(".exam-answer:checked");
        if(checked){
            state.answers[qid] = checked.value;
            if(state.statuses[qid] != "review"){
                state.statuses[qid] = "answered";
            }
        } else if(state.statuses[qid] != "review") {
            state.statuses[qid] = "visited";
        }
    }

    function restoreQuestionAnswer(index)
    {
        var card = cards[index];
        if(!card){ return; }
        var qid = card.dataset.questionId;
        card.querySelectorAll(".exam-answer").forEach(function(input){
            input.checked = state.answers[qid] === input.value;
        });
    }

    function persist()
    {
        autosaveStatus.innerHTML = '<i class="bi bi-cloud-arrow-up"></i>Saving';
        localStorage.setItem(storageKey, JSON.stringify(state));
        setTimeout(function(){ autosaveStatus.innerHTML = '<i class="bi bi-cloud-check"></i>Saved'; }, 250);
    }

    function statusFor(index)
    {
        var card = cards[index];
        var qid = card.dataset.questionId;
        if(state.answers[qid] && state.statuses[qid] == "review"){ return "answered-review"; }
        if(state.answers[qid]){ return "answered"; }
        if(state.statuses[qid] == "review"){ return "review"; }
        if(state.statuses[qid] == "visited"){ return "visited"; }
        return "not-visited";
    }

    function updatePalette()
    {
        palette.forEach(function(btn, index){
            btn.className = "palette-btn " + statusFor(index) + (index == current ? " current" : "");
        });
        progress.innerHTML = (current + 1) + " / " + totalQuestions;
    }

    function showQuestion(index)
    {
        syncCurrentAnswer();
        current = Math.max(0, Math.min(index, totalQuestions - 1));
        cards.forEach(function(card){ card.classList.remove("active"); });
        restoreQuestionAnswer(current);
        cards[current].classList.add("active");
        var qid = cards[current].dataset.questionId;
        if(!state.answers[qid] && state.statuses[qid] != "review"){
            state.statuses[qid] = "visited";
        }
        updatePalette();
        persist();
    }

    document.querySelectorAll(".exam-answer").forEach(function(input){
        input.addEventListener("change", function(){
            var qid = cards[input.dataset.questionIndex].dataset.questionId;
            state.answers[qid] = input.value;
            if(state.statuses[qid] != "review"){
                state.statuses[qid] = "answered";
            }
            updatePalette();
            persist();
        });
    });

    palette.forEach(function(btn){
        btn.addEventListener("click", function(){ showQuestion(parseInt(btn.dataset.questionIndex, 10)); });
    });

    document.getElementById("prevQuestion").addEventListener("click", function(){ showQuestion(current - 1); });
    document.getElementById("saveNext").addEventListener("click", function(){
        syncCurrentAnswer();
        updatePalette();
        persist();
        if(current < totalQuestions - 1){
            showQuestion(current + 1);
        }
    });
    document.getElementById("markReview").addEventListener("click", function(){
        syncCurrentAnswer();
        state.statuses[cards[current].dataset.questionId] = "review";
        updatePalette();
        persist();
        if(current < totalQuestions - 1){
            showQuestion(current + 1);
        }
    });
    document.getElementById("clearResponse").addEventListener("click", function(){
        var qid = cards[current].dataset.questionId;
        delete state.answers[qid];
        cards[current].querySelectorAll(".exam-answer").forEach(function(input){ input.checked = false; });
        state.statuses[qid] = "visited";
        updatePalette();
        persist();
    });
    document.getElementById("fullscreenBtn").addEventListener("click", function(){
        if(!document.fullscreenElement){ window.requestExamFullscreen(true); }
        else if(window.Swal) {
            Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 1800 }).fire({ icon: 'success', title: 'Secure full-screen mode is active.' });
        }
    });

    window.examOnSecureStart = function(){
        state.endsAt = Date.now() + duration * 1000;
        state.startedAt = Date.now();
        persist();
        tick();
    };

    function submitWithSummary(auto)
    {
        if(submitting){ return; }
        var answered = Object.keys(state.answers).length;
        var review = Object.values(state.statuses).filter(function(value){ return value == "review"; }).length;
        var unanswered = totalQuestions - answered;
        var form = document.getElementById("examForm");
        document.getElementById("autoSubmit").value = auto ? "1" : "0";
        document.getElementById("timeSpentSeconds").value = Math.max(0, Math.round((Date.now() - state.startedAt) / 1000));

        function finish()
        {
            submitting = true;
            if (window.examFlowGuard) {
                window.examFlowGuard.markCompleted();
            }
            Swal.fire({
                title: "Submitting exam...",
                text: "Please wait while your result is prepared.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function(){
                    Swal.showLoading();
                }
            });
            localStorage.removeItem(storageKey);
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().finally(function(){ form.submit(); });
            } else {
                form.submit();
            }
        }

        if(auto){
            finish();
            return;
        }

        Swal.fire({
            title: "Are you sure you want to submit this exam?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Submit",
            cancelButtonText: "Cancel"
        }).then(function(result){
            if(result.isConfirmed){ finish(); }
        });
    }

    document.getElementById("submitExamBtn").addEventListener("click", function(){ submitWithSummary(false); });

    function fmtClock(totalSeconds)
    {
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        return String(minutes).padStart(2, "0") + ":" + String(seconds).padStart(2, "0");
    }

    function tick()
    {
        if(!window.examSecureStarted){
            timer.innerHTML = fmtClock(duration);
            return;
        }
        var remaining = Math.max(0, Math.floor((state.endsAt - Date.now()) / 1000));
        timer.innerHTML = fmtClock(remaining);
        if(remaining <= 0){
            Swal.fire({ title: "Time Over", text: "Your exam is being submitted automatically.", icon: "warning", allowOutsideClick: false, showConfirmButton: false });
            submitWithSummary(true);
        }
    }

    showQuestion(0);
    tick();
    setInterval(tick, 1000);
});
</script>
</body>
</html>

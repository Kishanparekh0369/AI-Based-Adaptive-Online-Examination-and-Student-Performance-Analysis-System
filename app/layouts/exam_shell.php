<?php
$exam_shell_mode = $exam_shell_mode ?? "standard";
$exam_shell_questions = $exam_shell_questions ?? [];
$exam_shell_action = $exam_shell_action ?? "";
$exam_shell_total = max(count($exam_shell_questions), (int)($exam["max_questions"] ?? 0), 1);
$exam_shell_student_id = $exam_shell_student_id ?? ($student_id ?? $studentId ?? "");
$exam_shell_exam_id = $exam_shell_exam_id ?? ($exam_id ?? $examId ?? "");
$exam_shell_student_name = $exam_shell_student_name ?? ($student_name ?? ($_SESSION["student_name"] ?? "Student"));
$exam_shell_title_suffix = $exam_shell_title_suffix ?? "Exam Portal";
$exam_shell_form_attrs = ' method="post" action="' . h($exam_shell_action) . '"';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($exam["exam_title"]); ?> - <?php echo h($exam_shell_title_suffix); ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon-16x16.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/apple-touch-icon.png?v=1">
    <meta name="theme-color" content="#2563EB">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body class="exam-portal-body">

<form<?php echo $exam_shell_form_attrs; ?> id="examForm">
    <input type="hidden" name="exam_id" value="<?php echo h($exam_shell_exam_id); ?>">
    <input type="hidden" name="auto_submit" id="autoSubmit" value="0">
    <input type="hidden" name="time_spent_seconds" id="timeSpentSeconds" value="0">

    <header class="exam-portal-header">
        <div>
            <h1><?php echo h($exam["exam_title"]); ?></h1>
            <p><?php echo h($exam["subject_name"]); ?> | <?php echo h($exam_shell_student_name); ?></p>
        </div>
        <div class="exam-header-metrics">
            <span><i class="bi bi-clock"></i><strong id="examTimer" data-duration="<?php echo (int)$duration_seconds; ?>">--:--</strong></span>
            <span><i class="bi bi-bar-chart"></i><strong id="examProgress">1 / <?php echo $exam_shell_total; ?></strong></span>
            <span id="autosaveStatus"><i class="bi bi-cloud-check"></i>Saved</span>
            <span id="examSecurityStatus" class="exam-security-pill"><i class="bi bi-shield-check"></i><strong>Secured</strong></span>
            <button class="btn btn-light btn-sm" type="button" id="fullscreenBtn" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        </div>
    </header>

    <main class="exam-portal-shell">
        <section class="exam-question-panel">
            <div id="questionArea">
                <?php if ($exam_shell_mode === "adaptive") { ?>
                    <div class="text-center py-5" id="loadingState">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted">Loading question...</p>
                    </div>
                <?php } else { ?>
                    <?php foreach ($exam_shell_questions as $index => $question) {
                        $number = $index + 1;
                        $qid = $question["id"];
                    ?>
                        <article class="exam-question-card <?php echo $index == 0 ? "active" : ""; ?>" data-question-index="<?php echo $index; ?>" data-question-id="<?php echo $qid; ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <span class="exam-question-count">Question <?php echo $number; ?> of <?php echo count($exam_shell_questions); ?></span>
                                    <h2><?php echo h($question["question"]); ?></h2>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-secondary"><?php echo h($question["difficulty"]); ?></span>
                                    <span class="badge bg-info text-dark"><?php echo h(isset($question["language"]) ? $question["language"] : "English"); ?></span>
                                    <span class="badge bg-primary"><?php echo h($question["marks"]); ?> Mark</span>
                                </div>
                            </div>
                            <?php foreach (array("A" => "option_a", "B" => "option_b", "C" => "option_c", "D" => "option_d") as $option_key => $option_field) { ?>
                                <label class="exam-option">
                                    <input class="form-check-input me-2 exam-answer" type="radio" name="answer[<?php echo $qid; ?>]" value="<?php echo $option_key; ?>" data-question-index="<?php echo $index; ?>">
                                    <span class="option-key"><?php echo $option_key; ?></span>
                                    <span><?php echo h($question[$option_field]); ?></span>
                                </label>
                            <?php } ?>
                        </article>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="exam-action-bar">
                <button type="button" class="btn btn-outline-secondary" id="prevQuestion"><i class="bi bi-arrow-left"></i> Previous</button>
                <button type="button" class="btn btn-primary" id="saveNext">Save & Next <i class="bi bi-arrow-right"></i></button>
                <button type="button" class="btn btn-warning" id="markReview">Mark For Review & Next</button>
                <button type="button" class="btn btn-outline-danger" id="clearResponse">Clear Response</button>
                <button type="button" class="btn btn-success no-loader" id="submitExamBtn">Submit Exam</button>
            </div>
        </section>

        <button type="button" class="btn btn-primary exam-palette-toggle" id="questionPaletteToggle" aria-expanded="false" aria-controls="examPalettePanel">
            <i class="bi bi-grid-3x3-gap"></i> Questions
        </button>
        <div class="exam-palette-backdrop" id="examPaletteBackdrop"></div>
        <aside class="exam-palette-panel" id="examPalettePanel" aria-label="Question Palette">
            <div class="exam-palette-mobile-head">
                <h3>Question Palette</h3>
                <button type="button" class="btn btn-light btn-sm" id="questionPaletteClose" aria-label="Close question palette"><i class="bi bi-x-lg"></i></button>
            </div>
            <h3>Question Palette</h3>
            <div class="exam-palette-scroll">
                <div class="palette-grid" id="questionPalette">
                    <?php if ($exam_shell_mode === "standard") { ?>
                        <?php foreach ($exam_shell_questions as $index => $question) { ?>
                            <button type="button" class="palette-btn not-visited <?php echo $index == 0 ? "current" : ""; ?>" data-question-index="<?php echo $index; ?>"><?php echo $index + 1; ?></button>
                        <?php } ?>
                    <?php } else { ?>
                        <?php for ($index = 0; $index < $exam_shell_total; $index++) { ?>
                            <button type="button" class="palette-btn not-visited <?php echo $index == 0 ? "current" : ""; ?>" data-question-index="<?php echo $index; ?>"><?php echo $index + 1; ?></button>
                        <?php } ?>
                    <?php } ?>
                </div>
                <div class="palette-legend">
                    <span><i class="legend-box not-visited"></i>Not Visited</span>
                    <span><i class="legend-box visited"></i>Visited</span>
                    <span><i class="legend-box answered"></i>Answered</span>
                    <span><i class="legend-box review"></i>Marked Review</span>
                    <span><i class="legend-box answered-review"></i>Answered + Review</span>
                </div>
            </div>
        </aside>
    </main>
</form>

<div id="examSecureGate" class="exam-secure-gate" role="dialog" aria-modal="true" aria-labelledby="examSecureGateTitle">
    <div class="exam-secure-card">
        <div class="exam-secure-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <p class="exam-secure-eyebrow">Secure Examination Mode</p>
        <h2 id="examSecureGateTitle"><?php echo h($exam["exam_title"]); ?></h2>
        <p class="exam-secure-sub"><?php echo h($exam["subject_name"]); ?> &middot; <?php echo h($exam_shell_student_name); ?> &middot; <?php echo (int)$exam_shell_total; ?> questions &middot; <?php echo (int)round($duration_seconds / 60); ?> minutes</p>
        <ul class="exam-secure-rules">
            <li><i class="bi bi-fullscreen"></i><span><strong>Full-screen is MANDATORY.</strong> The exam starts only after you enter secure full-screen mode.</span></li>
            <li><i class="bi bi-eye-slash"></i><span><strong>Do not switch tabs, minimize, or open other apps.</strong> Every violation is recorded — the 3rd violation blocks this attempt automatically.</span></li>
            <li><i class="bi bi-clipboard-x"></i><span><strong>Copy, paste, right-click and developer tools are disabled</strong> and monitored during the exam.</span></li>
            <li><i class="bi bi-stopwatch"></i><span><strong>The timer starts the moment you enter.</strong> Do not refresh, close, or navigate away from this tab.</span></li>
        </ul>
        <button type="button" class="exam-secure-enter" id="examSecureEnter"><i class="bi bi-shield-check"></i> Enter Secure Exam Mode</button>
        <p class="exam-secure-error" id="examSecureError" role="alert" style="display:none;"></p>
        <p class="exam-secure-note" id="examSecureNote">You will be asked for full-screen permission. It is required to begin.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.examFlowGuard = {
    examId: <?php echo (int)$exam_shell_exam_id; ?>,
    sessionId: null,
    completed: false,
    blocking: false,
    allowFullscreenExit: false,
    fullscreenWarningOpen: false,
    markCompleted: function() {
        this.completed = true;
        this.allowFullscreenExit = true;
    },
    setSession: function(sessionId) {
        this.sessionId = sessionId ? parseInt(sessionId, 10) : null;
    },
    block: function(reason, useBeacon) {
        if (this.completed || this.blocking || !this.examId) {
            return;
        }
        this.blocking = true;
        var data = new FormData();
        data.append('exam_id', this.examId);
        data.append('reason', reason || 'Navigation exit');
        if (this.sessionId) {
            data.append('session_id', this.sessionId);
        }
        if (useBeacon && navigator.sendBeacon) {
            navigator.sendBeacon('block_exam.php', data);
            return;
        }
        fetch('block_exam.php', { method: 'POST', body: data, keepalive: true }).catch(function(){});
    }
};

window.examSecureGate = true;
window.examSecureStarted = false;
window.examOnSecureStart = null;
window.examSecurity = { strikes: 0, maxStrikes: 3, modalOpen: false, lastViolationAt: 0 };

function examSecurityActive() {
    var g = window.examFlowGuard;
    return !!(window.examSecureStarted && g && !g.completed && !g.blocking);
}

function updateSecurityPill(blocked) {
    var pill = document.getElementById('examSecurityStatus');
    if (!pill) { return; }
    var s = window.examSecurity.strikes;
    if (blocked) {
        pill.className = 'exam-security-pill is-blocked';
        pill.innerHTML = '<i class="bi bi-shield-x"></i><strong>Blocked</strong>';
    } else if (s > 0) {
        pill.className = 'exam-security-pill is-warn';
        pill.innerHTML = '<i class="bi bi-shield-exclamation"></i><strong>Strike ' + s + '/3</strong>';
    } else {
        pill.className = 'exam-security-pill';
        pill.innerHTML = '<i class="bi bi-shield-check"></i><strong>Secured</strong>';
    }
}

function examToast(message) {
    if (!window.Swal) { return; }
    Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 2200, timerProgressBar: true }).fire({ icon: 'warning', title: message });
}

function blockAttempt(reason) {
    var g = window.examFlowGuard;
    updateSecurityPill(true);
    if (!g || g.completed || g.blocking) {
        window.location.replace('exams.php');
        return;
    }
    g.block(reason || 'Security violation limit exceeded', true);
    if (window.Swal) {
        Swal.fire({
            title: 'Exam Blocked',
            text: 'This attempt has been blocked due to repeated security violations. Contact your faculty if you believe this is a mistake.',
            icon: 'error',
            allowOutsideClick: false,
            allowEscapeKey: false,
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.replace('exams.php');
        });
    }
    setTimeout(function() { window.location.replace('exams.php'); }, 5000);
}

function showStrikeWarning(reason) {
    if (window.examSecurity.modalOpen || !window.Swal) {
        if (!window.Swal) { blockAttempt(reason); }
        return;
    }
    window.examSecurity.modalOpen = true;
    var n = window.examSecurity.strikes;
    Swal.fire({
        title: 'Security Warning (' + n + ' of ' + (window.examSecurity.maxStrikes - 1) + ')',
        html: '<p><strong>' + reason.replace(/</g, '&lt;') + '</strong></p>' +
              '<p>Return to the exam immediately. <strong>The timer keeps running.</strong> One more violation will <strong>block this attempt automatically</strong>.</p>',
        icon: 'warning',
        confirmButtonText: 'Return to Exam',
        allowOutsideClick: false,
        allowEscapeKey: false,
        timer: 15000,
        timerProgressBar: true
    }).then(function(result) {
        window.examSecurity.modalOpen = false;
        if (result.isConfirmed) {
            if (!document.fullscreenElement && document.fullscreenEnabled) {
                document.documentElement.requestFullscreen().catch(function(){});
            }
            return;
        }
        blockAttempt('Did not return to the exam in time');
    });
}

function recordViolation(reason) {
    if (!examSecurityActive() || window.examSecurity.modalOpen) {
        return;
    }
    var now = Date.now();
    if (now - window.examSecurity.lastViolationAt < 2500) {
        return;
    }
    window.examSecurity.lastViolationAt = now;
    window.examSecurity.strikes++;
    updateSecurityPill(false);
    if (window.examSecurity.strikes >= window.examSecurity.maxStrikes) {
        blockAttempt(reason);
    } else {
        showStrikeWarning(reason);
    }
}

window.requestExamFullscreen = function(showPrompt) {
    if (document.fullscreenElement || !document.fullscreenEnabled) {
        return Promise.resolve();
    }
    return document.documentElement.requestFullscreen().catch(function() {
        if (!showPrompt || !window.Swal || !examSecurityActive()) {
            return;
        }
        return Swal.fire({
            title: 'Full Screen is Mandatory',
            text: 'You must stay in full-screen mode for the entire exam. Tap below to re-enter now.',
            icon: 'warning',
            confirmButtonText: 'Enter Full Screen',
            showCancelButton: true,
            cancelButtonText: 'Exit Exam',
            allowOutsideClick: false
        }).then(function(result) {
            if (result.isConfirmed) {
                return document.documentElement.requestFullscreen().catch(function(){});
            }
            if (window.examFlowGuard && !window.examFlowGuard.completed) {
                window.examFlowGuard.block('Fullscreen refused', false);
                setTimeout(function() { window.location.replace('exams.php'); }, 600);
            }
        });
    });
};

document.addEventListener('fullscreenchange', function() {
    document.body.classList.toggle('exam-fullscreen-active', !!document.fullscreenElement);
    if (!document.fullscreenElement && examSecurityActive()) {
        recordViolation('You left full-screen mode. Full-screen is mandatory during the exam.');
    }
});

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        recordViolation('Tab switch or window minimize detected.');
    }
});

window.addEventListener('blur', function() {
    recordViolation('You moved away from the exam window.');
});

['copy', 'cut', 'paste'].forEach(function(evName) {
    document.addEventListener(evName, function(e) {
        if (!examSecurityActive()) { return; }
        e.preventDefault();
        examToast('Copy, cut and paste are disabled during the exam.');
    });
});

document.addEventListener('contextmenu', function(e) {
    if (!examSecurityActive()) { return; }
    if (e.target.closest && e.target.closest('.exam-question-panel')) {
        e.preventDefault();
    }
});

document.addEventListener('keydown', function(e) {
    if (!examSecurityActive()) { return; }
    var k = (e.key || '').toUpperCase();
    var mod = e.ctrlKey || e.metaKey;
    if (k === 'F12' ||
        (mod && e.shiftKey && ['I', 'J', 'C', 'K'].indexOf(k) >= 0) ||
        (mod && ['U', 'S', 'P'].indexOf(k) >= 0)) {
        e.preventDefault();
        recordViolation('Attempt to open developer tools or view source detected.');
    }
});

window.addEventListener('beforeunload', function(e) {
    if (examSecurityActive()) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var gate = document.getElementById('examSecureGate');
    var enterBtn = document.getElementById('examSecureEnter');
    var note = document.getElementById('examSecureNote');
    if (!document.fullscreenEnabled && note) {
        note.textContent = 'Full-screen is not supported on this device. You may continue, but tab-switch and activity monitoring stays active.';
    }
    function showGateError(message) {
        var errBox = document.getElementById('examSecureError');
        if (!errBox) { return; }
        if (message) {
            errBox.textContent = message;
            errBox.style.display = '';
        } else {
            errBox.textContent = '';
            errBox.style.display = 'none';
        }
    }
    if (enterBtn) {
        enterBtn.addEventListener('click', function() {
            enterBtn.disabled = true;
            enterBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Requesting full screen...';
            showGateError('');
            var entered = false;
            function done() {
                if (entered) { return; }
                entered = true;
                window.examSecureStarted = true;
                if (gate) { gate.classList.add('exam-secure-passed'); }
                updateSecurityPill(false);
                if (typeof window.examOnSecureStart === 'function') {
                    try { window.examOnSecureStart(); } catch (err) {}
                }
            }
            function failed(err) {
                if (entered) { return; }
                enterBtn.disabled = false;
                enterBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Try Again — Enter Secure Exam Mode';
                var why = err && err.name ? err.name : 'request denied';
                showGateError('Full-screen was blocked (' + why + '). Please allow full-screen for this site, then try again. The exam cannot start without secure full-screen mode.');
            }
            try {
                var el = document.documentElement;
                var reqFn = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
                if (document.fullscreenElement) {
                    done();
                } else if (document.fullscreenEnabled !== false && typeof reqFn === 'function') {
                    var p = reqFn.call(el);
                    if (p && typeof p.then === 'function') {
                        var settled = false;
                        p.then(function() { settled = true; done(); }).catch(function(err) { settled = true; failed(err); });
                        setTimeout(function() {
                            if (!settled && !entered && !document.fullscreenElement) {
                                settled = true;
                                failed({ name: 'timed out — no response from browser' });
                            }
                        }, 5000);
                    } else {
                        setTimeout(function() {
                            if (document.fullscreenElement) { done(); }
                            else { failed({ name: 'not supported by this browser' }); }
                        }, 600);
                    }
                } else {
                    done();
                }
            } catch (err) {
                failed(err);
            }
        });
    }

    var palettePanel = document.getElementById('examPalettePanel');
    var paletteToggle = document.getElementById('questionPaletteToggle');
    var paletteClose = document.getElementById('questionPaletteClose');
    var paletteBackdrop = document.getElementById('examPaletteBackdrop');

    function setPaletteOpen(open) {
        document.body.classList.toggle('exam-palette-open', open);
        if (paletteToggle) {
            paletteToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    if (paletteToggle) {
        paletteToggle.addEventListener('click', function() {
            setPaletteOpen(true);
        });
    }
    if (paletteClose) {
        paletteClose.addEventListener('click', function() {
            setPaletteOpen(false);
        });
    }
    if (paletteBackdrop) {
        paletteBackdrop.addEventListener('click', function() {
            setPaletteOpen(false);
        });
    }
    if (palettePanel) {
        palettePanel.addEventListener('click', function(event) {
            if (event.target.closest('.palette-btn') && window.matchMedia('(max-width: 767.98px)').matches) {
                setPaletteOpen(false);
            }
        });
    }
});

window.addEventListener('pagehide', function() {
    if (window.examFlowGuard && !window.examFlowGuard.completed) {
        window.examFlowGuard.block('Navigation exit', true);
    }
});
</script>

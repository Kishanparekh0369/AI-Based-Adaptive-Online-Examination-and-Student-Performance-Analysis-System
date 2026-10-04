(function() {
    'use strict';

    const DATA = window.adaptiveExamData;
    if (!DATA) return;

let state = {
    sessionId: null,
    theta: 0,
    currentDifficulty: 'Medium',
    askedIds: [],
    questions: [],
    currentIndex: -1,
    currentQId: null,
    questionStartTime: null,
    startTime: null,
    isSubmitting: false,
    ended: false,
    noMoreQuestions: false
};

    const DOM = {
        questionArea: document.getElementById('questionArea'),
        loadingState: document.getElementById('loadingState'),
        submitBtn: document.getElementById('submitExamBtn'),
        prevBtn: document.getElementById('prevQuestion'),
        nextBtn: document.getElementById('saveNext'),
        markReviewBtn: document.getElementById('markReview'),
        clearBtn: document.getElementById('clearResponse'),
        progress: document.getElementById('examProgress'),
        autosaveStatus: document.getElementById('autosaveStatus'),
        questionPalette: document.getElementById('questionPalette'),
        examTimer: document.getElementById('examTimer'),
        fullscreenBtn: document.getElementById('fullscreenBtn'),
    };

    state.currentQId = null;
    if (window.examSecureGate && !window.examSecureStarted) {
        window.examOnSecureStart = function() {
            state.questionStartTime = Date.now();
            init();
        };
    } else {
        state.questionStartTime = Date.now();
        init();
    }

    function init() {
        state.startTime = Date.now();
        startTimer(DATA.duration);
        startExam();
        setupEventListeners();

        window.addEventListener('pagehide', function() {
            if (!state.ended && state.sessionId) {
                var fd = new FormData();
                fd.append('action', 'abandon');
                fd.append('exam_id', DATA.examId);
                fd.append('student_id', DATA.studentId);
                fd.append('session_id', state.sessionId);
                navigator.sendBeacon('adaptive_ajax.php', fd);
            }
        });
    }

    function startExam() {
        showLoading('Initializing adaptive exam...');
        fetch('adaptive_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'start',
                exam_id: DATA.examId,
                student_id: DATA.studentId
            })
        })
        .then(function(r) { if (!r.ok) throw new Error('Server ' + r.status); return r.json(); })
        .then(function(data) {
            if (data.error) { showError(data.error); return; }
            state.sessionId = data.session_id;
            if (window.examFlowGuard) window.examFlowGuard.setSession(state.sessionId);
            state.theta = parseFloat(data.theta);
            state.currentDifficulty = data.difficulty;
            state.askedIds = data.asked_ids || [];
            loadNextQuestion();
        })
        .catch(function(e) { console.error(e); showError('Could not start exam. ' + e.message); });
    }

    function loadNextQuestion() {
        showLoading('Loading next question...');
        setSaveStatus('Saving');

        fetch('adaptive_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'get_question',
                exam_id: DATA.examId,
                student_id: DATA.studentId,
                session_id: state.sessionId,
                theta: state.theta,
                difficulty: state.currentDifficulty,
                asked_ids: JSON.stringify(state.askedIds)
            })
        })
        .then(function(r) { if (!r.ok) throw new Error('Server ' + r.status); return r.json(); })
        .then(function(data) {
            setSaveStatus('Saved');
            if (data.error) { showError(data.error); return; }
            if (data.exam_ended) {
                state.noMoreQuestions = true;
                updateUI();
                updateActionBar();
                return;
            }
            data.userAnswer = null;
            data.wasCorrect = null;
            state.questions.push(data);
            state.currentIndex = state.questions.length - 1;
            renderQuestion(state.questions[state.currentIndex]);
            updateUI();
        })
        .catch(function(e) {
            setSaveStatus('Saved');
            console.error(e);
            showError(e.message);
        });
    }

    function navigateTo(index) {
        if (index < 0 || index >= state.questions.length) return;
        state.currentIndex = index;
        renderQuestion(state.questions[index]);
        updateUI();
    }

    function submitAnswer() {
        if (state.isSubmitting || state.ended) return;
        var q = state.questions[state.currentIndex];
        if (!q) return;
        if (q.wasCorrect !== null) {
            if (state.currentIndex < state.questions.length - 1) {
                navigateTo(state.currentIndex + 1);
            } else if (!state.noMoreQuestions && state.questions.length < (DATA.maxQuestions || state.questions.length + 1)) {
                loadNextQuestion();
            }
            return;
        }
        if (!q.userAnswer) {
            q.status = q.status === 'review' ? 'review' : 'visited';
            if (state.currentIndex < state.questions.length - 1) {
                navigateTo(state.currentIndex + 1);
            } else {
                updateUI();
            }
            return;
        }

        state.isSubmitting = true;
        DOM.submitBtn.disabled = true;
        DOM.nextBtn.disabled = true;
        DOM.nextBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        setSaveStatus('Saving');

        var timeTaken = Math.max(1, Math.floor((Date.now() - state.questionStartTime) / 1000));

        fetch('adaptive_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'submit_answer',
                exam_id: DATA.examId,
                student_id: DATA.studentId,
                session_id: state.sessionId,
                question_id: q.id,
                answer: q.userAnswer,
                theta: state.theta,
                difficulty: q.difficulty,
                time_taken: timeTaken
            })
        })
        .then(function(r) { if (!r.ok) throw new Error('Server ' + r.status); return r.json(); })
        .then(function(data) {
            state.isSubmitting = false;
            setSaveStatus('Saved');
            if (data.error) { showError(data.error); return; }

            q.wasCorrect = data.was_correct;
            q.correctAnswer = data.correct_answer;

            state.theta = parseFloat(data.theta);
            state.currentDifficulty = data.next_difficulty;
            state.askedIds = data.asked_ids || state.askedIds;

            updateUI();
            renderPalette();

            if (data.exam_ended) {
                state.noMoreQuestions = true;
                updateUI();
                updateActionBar();
                return;
            }

            if (data.next_question) {
                data.next_question.userAnswer = null;
                data.next_question.wasCorrect = null;
                state.questions.push(data.next_question);
                state.currentIndex = state.questions.length - 1;
                renderQuestion(state.questions[state.currentIndex]);
                updateUI();
            } else {
                loadNextQuestion();
            }
        })
        .catch(function(e) {
            state.isSubmitting = false;
            setSaveStatus('Saved');
            DOM.nextBtn.disabled = false;
            DOM.nextBtn.innerHTML = 'Save & Next <i class="bi bi-arrow-right"></i>';
            console.error(e);
            showError(e.message);
        });
    }

    function finishExam() {
        if (state.ended) return;
        if (state.questions.length === 0) return;

        var currentQ = state.questions[state.currentIndex];
        if (currentQ && currentQ.wasCorrect === null && currentQ.userAnswer) {
            state.isSubmitting = true;
var timeTaken = Math.max(1, Math.floor((Date.now() - state.questionStartTime) / 1000));
            fetch('adaptive_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'submit_answer',
                    exam_id: DATA.examId,
                    student_id: DATA.studentId,
                    session_id: state.sessionId,
                    question_id: currentQ.id,
                    answer: currentQ.userAnswer,
                    theta: state.theta,
                    difficulty: currentQ.difficulty,
                    time_taken: timeTaken
                })
            })
            .then(function() { doFinish(); })
            .catch(function() { doFinish(); });
        } else {
            doFinish();
        }
    }

    function doFinish() {
        if (state.ended) return;
        state.ended = true;
        finishAndRedirect();
    }

    function finishAndRedirect() {
        Swal.fire({
            title: 'Submitting exam...',
            text: 'Please wait while your result is prepared.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
        fetch('adaptive_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'finish',
                exam_id: DATA.examId,
                student_id: DATA.studentId,
                session_id: state.sessionId,
                theta: state.theta
            })
        })
        .then(function(r) { return r.text().then(function(t) { try { return JSON.parse(t); } catch(e) { return {}; } }); })
        .then(function(data) {
            if (data.error) {
                state.ended = false;
                showError(data.error);
                return;
            }
            if (window.examFlowGuard) window.examFlowGuard.markCompleted();
            redirectToResult(true);
        })
        .catch(function(e) {
            state.ended = false;
            showError('Could not submit exam. ' + (e.message || 'Please try again.'));
        });
    }

    function redirectToResult(adaptive) {
        var url = 'result.php?submitted=1' + (adaptive ? '&adaptive=1' : '') + '&exam_id=' + DATA.examId;
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().finally(function() { window.location.replace(url); });
        } else {
            window.location.replace(url);
        }
    }

    function endExam(data) {
        if (state.ended) return;
        state.ended = true;
        finishAndRedirect();
    }

    function renderQuestion(q) {
        if (!q) return;
        DOM.loadingState.style.display = 'none';
        state.questionStartTime = Date.now();
        state.currentQId = q.id;

        var total = DATA.maxQuestions || Math.max(state.questions.length, state.currentIndex + 1);
        q.status = q.status || 'visited';

        var html = '<article class="exam-question-card active" data-question-index="' + state.currentIndex + '" data-question-id="' + q.id + '">';
        html += '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">';
        html += '<div>';
        html += '<span class="exam-question-count">Question ' + (state.currentIndex + 1) + ' of ' + total + '</span>';
        html += '<h2>' + escapeHtml(q.question) + '</h2>';
        html += '</div>';
        html += '<div class="d-flex flex-wrap gap-2">';
        html += '<span class="badge bg-secondary">' + escapeHtml(q.difficulty || 'Medium') + '</span>';
        html += '<span class="badge bg-info text-dark">' + escapeHtml(q.language || 'English') + '</span>';
        html += '<span class="badge bg-primary">' + (q.marks || 1) + ' Mark</span>';
        html += '</div></div>';

        var options = [
            { key: 'A', text: q.option_a },
            { key: 'B', text: q.option_b },
            { key: 'C', text: q.option_c },
            { key: 'D', text: q.option_d }
        ];

        options.forEach(function(opt) {
            var isSelected = q.userAnswer === opt.key;
            html += '<label class="exam-option">';
            html += '<input class="form-check-input me-2 exam-answer" type="radio" name="answer[' + q.id + ']" value="' + opt.key + '" data-question-index="' + state.currentIndex + '" ' + (isSelected ? 'checked' : '') + '>';
            html += '<span class="option-key">' + opt.key + '</span>';
            html += '<span>' + escapeHtml(opt.text) + '</span>';
            html += '</label>';
        });

        html += '</article>';
        DOM.questionArea.innerHTML = html;

        DOM.questionArea.querySelectorAll('.exam-answer').forEach(function(input) {
            input.addEventListener('change', function() {
                state.questions[state.currentIndex].userAnswer = input.value;
                if (state.questions[state.currentIndex].status !== 'review') {
                    state.questions[state.currentIndex].status = 'answered';
                }
                updateUI();
                setSaveStatus('Saved');
            });
        });

        updateActionBar();
    }

    function updateUI() {
        var total = DATA.maxQuestions || Math.max(state.questions.length, state.currentIndex + 1);
        if (DOM.progress) DOM.progress.textContent = (state.currentIndex + 1) + ' / ' + total;

        renderPalette();
    }

    function renderPalette() {
        if (!DOM.questionPalette) return;
        DOM.questionPalette.innerHTML = '';
        var total = DATA.maxQuestions || state.questions.length;
        for (var i = 0; i < total; i++) {
            var q = state.questions[i];
            var btn = document.createElement('button');
            btn.type = 'button';
            var cls = 'palette-btn ' + (q ? statusFor(q) : 'not-visited');
            if (i === state.currentIndex) cls += ' current';
            btn.className = cls;
            btn.textContent = i + 1;
            btn.title = q ? q.difficulty + (q.userAnswer ? ' - Answered' : '') + (q.wasCorrect !== null ? ' - Saved' : '') : 'Not Visited';
            (function(idx) {
                btn.addEventListener('click', function(event) {
                    event.preventDefault();
                    if (idx < state.questions.length) navigateTo(idx);
                });
            })(i);
            DOM.questionPalette.appendChild(btn);
        }
    }

    function updateActionBar() {
        var q = state.questions[state.currentIndex];
        if (DOM.submitBtn) {
            DOM.submitBtn.disabled = false;
            DOM.submitBtn.innerHTML = 'Submit Exam';
        }
        if (DOM.clearBtn) {
            DOM.clearBtn.disabled = false;
        }
        if (DOM.prevBtn) DOM.prevBtn.disabled = false;
        if (DOM.nextBtn) {
            DOM.nextBtn.disabled = !!state.noMoreQuestions;
            DOM.nextBtn.innerHTML = 'Save & Next <i class="bi bi-arrow-right"></i>';
        }
        if (DOM.markReviewBtn) DOM.markReviewBtn.disabled = false;
    }

    function showLoading(msg) {
        DOM.questionArea.innerHTML = '<div class="text-center py-5" id="loadingState">' +
            '<div class="spinner-border text-primary mb-3" role="status"></div>' +
            '<p class="text-muted">' + (msg || 'Loading...') + '</p></div>';
        DOM.loadingState = document.getElementById('loadingState');
    }

    function showError(msg) {
        DOM.questionArea.innerHTML = '<div class="alert alert-danger m-4">' +
            '<i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(msg) +
            '<br><button class="btn btn-sm btn-outline-danger mt-2" onclick="location.reload()">Retry</button>' +
            '</div>';
    }

    function startTimer(seconds) {
        var timerDisplay = DOM.examTimer;
        function updateTimer() {
            var remaining = Math.max(0, Math.floor(seconds - (Date.now() - state.startTime) / 1000));
            var m = String(Math.floor(remaining / 60)).padStart(2, '0');
            var s = String(remaining % 60).padStart(2, '0');
            timerDisplay.textContent = m + ':' + s;
            if (remaining <= 60) timerDisplay.style.color = '#ef4444';
            if (remaining <= 0 && !state.ended) { finishExam(); }
        }
        updateTimer();
        setInterval(updateTimer, 1000);
    }

    function setupEventListeners() {
        DOM.nextBtn.addEventListener('click', submitAnswer);
        DOM.markReviewBtn.addEventListener('click', function() {
            if (state.currentIndex >= 0 && state.currentIndex < state.questions.length) {
                state.questions[state.currentIndex].status = 'review';
                updateUI();
                if (state.questions[state.currentIndex].userAnswer && state.questions[state.currentIndex].wasCorrect === null) {
                    submitAnswer();
                } else if (state.currentIndex < state.questions.length - 1) {
                    navigateTo(state.currentIndex + 1);
                } else {
                    updateUI();
                }
            }
        });
        DOM.clearBtn.addEventListener('click', function() {
            if (state.currentIndex >= 0 && state.currentIndex < state.questions.length) {
                state.questions[state.currentIndex].userAnswer = null;
                state.questions[state.currentIndex].status = 'visited';
                renderQuestion(state.questions[state.currentIndex]);
                updateUI();
            }
        });
        DOM.prevBtn.addEventListener('click', function() {
            if (state.currentIndex > 0) navigateTo(state.currentIndex - 1);
        });
        DOM.submitBtn.addEventListener('click', function() {
            if (state.ended) return;
            Swal.fire({
                title: 'Are you sure you want to submit this exam?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Submit',
                cancelButtonText: 'Cancel'
            }).then(function(r) {
                if (r.isConfirmed) finishExam();
            });
        });
        if (DOM.fullscreenBtn) {
            DOM.fullscreenBtn.addEventListener('click', function() {
                if (!document.fullscreenElement && window.requestExamFullscreen) {
                    window.requestExamFullscreen(true);
                } else if (window.Swal) {
                    Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 1800 }).fire({ icon: 'success', title: 'Secure full-screen mode is active.' });
                }
            });
        }
    }

    function statusFor(q) {
        if (q.userAnswer && q.status === 'review') return 'answered-review';
        if (q.status === 'review') return 'review';
        if (q.wasCorrect !== null || q.userAnswer) return 'answered';
        return 'visited';
    }

    function setSaveStatus(text) {
        if (!DOM.autosaveStatus) return;
        var icon = text === 'Saving' ? 'bi-cloud-arrow-up' : 'bi-cloud-check';
        DOM.autosaveStatus.innerHTML = '<i class="bi ' + icon + '"></i>' + text;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
})();

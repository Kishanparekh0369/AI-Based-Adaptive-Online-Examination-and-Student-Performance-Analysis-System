/* ══════════════════════════════════════════════════
   Student AI Assistant widget
   ══════════════════════════════════════════════════ */
(function () {
    "use strict";

    var API_URL = "api/ai_assistant.php";
    var TIMEOUT_MS = 45000;
    var opened = false;
    var greeted = false;
    var pending = false;
    var studentName = "Student";
    var studentPhoto = "";
    var studentInitials = "S";

    var PAGE_IDS = ["dashboard", "exams", "performance", "ai_feedback", "result", "profile", "edit_profile", "change_password", "report", "feedback"];

    function $(sel, root) { return (root || document).querySelector(sel); }

    function readIdentity() {
        var panel = $("#spaiPanel");
        if (!panel) { return; }
        if (panel.getAttribute("data-student-name")) { studentName = panel.getAttribute("data-student-name"); }
        studentPhoto = panel.getAttribute("data-student-photo") || "";
        if (panel.getAttribute("data-student-initials")) { studentInitials = panel.getAttribute("data-student-initials"); }
    }

    function currentPageId() {
        try {
            var file = (window.location.pathname.split("/").pop() || "").split("?")[0].toLowerCase();
            file = file.replace(/\.php$/, "");
            if (PAGE_IDS.indexOf(file) !== -1) { return file; }
        } catch (e) {}
        return "dashboard";
    }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    // Safe mini-markdown: escape first, then format.
    function renderMarkdown(text) {
        var safe = esc(text);
        var lines = safe.split("\n");
        var html = "";
        var inUl = false;
        var inOl = false;

        function closeLists() {
            if (inUl) { html += "</ul>"; inUl = false; }
            if (inOl) { html += "</ol>"; inOl = false; }
        }

        lines.forEach(function (line) {
            var t = line.trim();
            var m;
            if (/^#{1,4}\s+/.test(t)) {
                closeLists();
                html += "<h4>" + inlineFmt(t.replace(/^#{1,4}\s+/, "")) + "</h4>";
            } else if ((m = t.match(/^[-*•]\s+(.*)/))) {
                if (!inUl) { closeLists(); html += "<ul>"; inUl = true; }
                html += "<li>" + inlineFmt(m[1]) + "</li>";
            } else if ((m = t.match(/^\d+[.)]\s+(.*)/))) {
                if (!inOl) { closeLists(); html += "<ol>"; inOl = true; }
                html += "<li>" + inlineFmt(m[1]) + "</li>";
            } else if (t === "") {
                closeLists();
            } else {
                closeLists();
                html += "<p>" + inlineFmt(t) + "</p>";
            }
        });
        closeLists();
        return html || "<p></p>";
    }

    function inlineFmt(s) {
        return s.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
    }

    function timeNow() {
        try {
            return new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
        } catch (e) { return ""; }
    }

    function nearBottom() {
        var body = $("#spaiBody");
        if (!body) { return true; }
        return (body.scrollHeight - body.scrollTop - body.clientHeight) < 120;
    }

    function scrollBody(force) {
        var body = $("#spaiBody");
        if (!body) { return; }
        if (force || nearBottom()) { body.scrollTop = body.scrollHeight; }
    }

    function userAvatarHtml() {
        return '<span class="spai-avatar-initials" data-spai-uav aria-hidden="true">' + esc(studentInitials) + "</span>";
    }

    function upgradeUserAvatar(row) {
        if (!studentPhoto) { return; }
        var holder = row.querySelector("[data-spai-uav]");
        if (!holder) { return; }
        var img = new Image();
        img.className = "spai-avatar-img";
        img.alt = "";
        img.setAttribute("aria-hidden", "true");
        img.onload = function () {
            if (holder.parentNode) { holder.parentNode.replaceChild(img, holder); }
        };
        img.src = studentPhoto;
    }

    function addMessage(role, htmlContent, actions) {
        var body = $("#spaiBody");
        if (!body) return;
        var stick = nearBottom();
        var row = document.createElement("div");
        row.className = "spai-row is-new" + (role === "user" ? " is-user" : "");

        var bubble = '<div class="spai-bubble">' + htmlContent + '<span class="spai-time">' + esc(timeNow()) + "</span>";
        if (actions && actions.length) {
            bubble += '<div class="spai-actions">';
            actions.forEach(function (a) {
                if (!a || !a.label || !a.url || !/^[a-z_]+\.php$/.test(a.url)) { return; }
                bubble += '<a class="spai-action" href="' + esc(a.url) + '"><i class="bi bi-arrow-right-circle"></i>' + esc(a.label) + "</a>";
            });
            bubble += "</div>";
        }
        bubble += "</div>";

        var inner = "";
        if (role !== "user") {
            inner += '<span class="spai-avatar" aria-hidden="true"><i class="bi bi-robot"></i></span>';
            inner += bubble;
        } else {
            inner += bubble + userAvatarHtml();
        }
        row.innerHTML = inner;
        body.appendChild(row);
        if (role === "user") { upgradeUserAvatar(row); }
        scrollBody(stick);
    }

    function showTyping() {
        var body = $("#spaiBody");
        if (!body) return null;
        var row = document.createElement("div");
        row.className = "spai-row";
        row.id = "spaiTypingRow";
        row.innerHTML = '<span class="spai-avatar" aria-hidden="true"><i class="bi bi-robot"></i></span>' +
            '<div class="spai-bubble"><span class="spai-typing" aria-label="AI is thinking"><span></span><span></span><span></span></span></div>';
        body.appendChild(row);
        scrollBody(true);
        return row;
    }

    function hideTyping() {
        var row = $("#spaiTypingRow");
        if (row && row.parentNode) { row.parentNode.removeChild(row); }
    }

    function setPending(on) {
        pending = on;
        var btn = $("#spaiSend");
        var input = $("#spaiInput");
        if (btn) { btn.disabled = on; }
        if (input) { input.disabled = on; if (!on) { try { input.focus(); } catch (e) {} } }
    }

    function postJSON(payload, timeoutMs) {
        var controller = null;
        var signal = null;
        if (window.AbortController) {
            controller = new AbortController();
            signal = controller.signal;
            setTimeout(function () { try { controller.abort(); } catch (e) {} }, timeoutMs || TIMEOUT_MS);
        }
        return fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
            signal: signal
        }).then(function (r) { return r.json(); });
    }

    function sendMessage(text) {
        var msg = (text == null ? "" : String(text)).trim();
        if (!msg || pending) { return; }
        if (msg.length > 1000) { msg = msg.slice(0, 1000); }

        addMessage("user", renderMarkdown(msg));
        var input = $("#spaiInput");
        if (input) { input.value = ""; input.style.height = "auto"; }

        setPending(true);
        showTyping();

        postJSON({ message: msg, current_page: currentPageId() }).then(function (data) {
            hideTyping();
            setPending(false);
            if (data && data.success) {
                addMessage("assistant", renderMarkdown(data.reply || ""), data.actions || []);
                updateStatus(data.ai_available !== false);
            } else {
                var errMsg = (data && data.message) || "Sorry, I couldn't process that request right now. Please try again.";
                addMessage("assistant", renderMarkdown(errMsg));
                if (data) { updateStatus(!!data.ai_available); }
            }
        }).catch(function () {
            hideTyping();
            setPending(false);
            addMessage("assistant", renderMarkdown("Sorry, the AI response is taking longer than expected. Please try again."));
        });
    }

    function updateStatus(online) {
        var el = $("#spaiStatus");
        if (!el) return;
        el.classList.toggle("is-off", !online);
        el.innerHTML = '<span class="dot" aria-hidden="true"></span>' + (online ? "AI Online" : "AI Busy");
    }

    var QUICK = [
        { icon: "bi-graph-up-arrow", label: "My Performance", q: "Analyze my current academic performance and summarize my strengths and areas for improvement." },
        { icon: "bi-journal-check", label: "My Exams", q: "Show me the examinations currently available or upcoming for me." },
        { icon: "bi-trophy", label: "My Results", q: "Summarize my latest examination results." },
        { icon: "bi-robot", label: "AI Feedback", q: "Explain my latest AI performance feedback." },
        { icon: "bi-book", label: "Study Tips", q: "Based on my performance data, suggest what I should study next." },
        { icon: "bi-question-circle", label: "Help", q: "What can you help me with?" }
    ];

    function greet() {
        if (greeted) { return; }
        greeted = true;
        var chips = $("#spaiChips");
        if (chips) {
            chips.innerHTML = "";
            QUICK.forEach(function (c) {
                var b = document.createElement("button");
                b.type = "button";
                b.className = "spai-chip";
                b.innerHTML = '<i class="bi ' + c.icon + '"></i>' + esc(c.label);
                b.addEventListener("click", function () { sendMessage(c.q); });
                chips.appendChild(b);
            });
        }
        addMessage("assistant", renderMarkdown(
            "Hi " + studentName + "! I'm your **Student AI Assistant**.\n\nI can help you with:\n- Your exams\n- Your results\n- Your performance\n- Strengths and improvements\n- Study recommendations\n\nWhat would you like to know?"
        ));
    }

    function openPanel() {
        var panel = $("#spaiPanel");
        var fab = $("#spaiFab");
        if (!panel || opened) return;
        opened = true;
        panel.classList.add("is-open");
        panel.setAttribute("aria-hidden", "false");
        if (fab) { fab.classList.add("is-open"); fab.setAttribute("aria-expanded", "true"); }
        greet();
        var input = $("#spaiInput");
        if (input) { try { input.focus(); } catch (e) {} }
        postJSON({ action: "status" }, 12000).then(function (data) {
            if (data) {
                if (data.name) { studentName = data.name; }
                if (data.full_name) {
                    var sub = document.querySelector("#spaiPanel .spai-head-sub");
                    if (sub) {
                        sub.textContent = "Personalized for " + data.full_name;
                        sub.setAttribute("title", data.full_name);
                    }
                    var panel = $("#spaiPanel");
                    if (panel) { panel.setAttribute("data-student-fullname", data.full_name); }
                }
                updateStatus(data.ai_available !== false);
            }
        }).catch(function () {});
    }

    function closePanel() {
        var panel = $("#spaiPanel");
        var fab = $("#spaiFab");
        if (!panel || !opened) return;
        opened = false;
        panel.classList.remove("is-open");
        panel.setAttribute("aria-hidden", "true");
        if (fab) { fab.classList.remove("is-open"); fab.setAttribute("aria-expanded", "false"); }
    }

    function clearChat() {
        function doClear() {
            var body = $("#spaiBody");
            if (body) { body.innerHTML = ""; }
            greeted = false;
            postJSON({ action: "reset" }, 12000).catch(function () {});
            greet();
        }
        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: "Start a new conversation?",
                text: "Your current AI conversation will be cleared.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "New Chat",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#2563EB"
            }).then(function (r) { if (r.isConfirmed) { doClear(); } });
        } else if (window.confirm("Start a new conversation? Your current AI conversation will be cleared.")) {
            doClear();
        }
    }

    function init() {
        readIdentity();
        var fab = $("#spaiFab");
        if (!fab) { return; }
        fab.addEventListener("click", function () {
            if (opened) { closePanel(); } else { openPanel(); }
        });
        var closeBtn = $("#spaiClose");
        if (closeBtn) { closeBtn.addEventListener("click", closePanel); }
        var minBtn = $("#spaiMin");
        if (minBtn) { minBtn.addEventListener("click", closePanel); }
        var newBtn = $("#spaiNew");
        if (newBtn) { newBtn.addEventListener("click", clearChat); }
        var sendBtn = $("#spaiSend");
        if (sendBtn) {
            sendBtn.addEventListener("click", function () {
                var input = $("#spaiInput");
                sendMessage(input ? input.value : "");
            });
        }
        var input = $("#spaiInput");
        if (input) {
            input.addEventListener("keydown", function (e) {
                if (e.key === "Enter" && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage(input.value);
                }
            });
            input.addEventListener("input", function () {
                input.style.height = "auto";
                input.style.height = Math.min(input.scrollHeight, 110) + "px";
            });
            input.addEventListener("focus", function () {
                setTimeout(function () {
                    try { input.scrollIntoView({ block: "nearest" }); } catch (e) {}
                    scrollBody(true);
                }, 300);
            });
        }
        if (window.visualViewport) {
            window.visualViewport.addEventListener("resize", function () {
                if (opened) { scrollBody(true); }
            });
        }
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && opened) { closePanel(); }
        });
    }

    window.StudentAI = {
        open: openPanel,
        close: closePanel,
        toggle: function () { if (opened) { closePanel(); } else { openPanel(); } },
        ask: function (prompt) { openPanel(); if (prompt) { setTimeout(function () { sendMessage(prompt); }, 250); } }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

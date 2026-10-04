/* ══════════════════════════════════════════════════
   Admin Portal — interactions.
   Dependent filter dropdowns + assignment expander.
   (Filter submit buttons use the global loading state.)
   ══════════════════════════════════════════════════ */
(function () {
    "use strict";

    function $all(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    /* ─── Dependent selects ─────────────────────────
       Parent:  <select name="course">
       Child:   <select data-dep-on="course" data-dep-attr="course">
                <select data-dep-on="course,semester" data-dep-attr="course,semester">
       Each child <option> carries data-<attr>="value".
       An option stays visible when every parent is empty,
       or the option value is empty, or it equals the parent.
    ───────────────────────────────────────────────── */
    function childVisible(opt, parents) {
        for (var i = 0; i < parents.length; i++) {
            var p = parents[i];
            var oval = (opt.getAttribute("data-" + p.attr) || "").toLowerCase();
            var pval = (p.value || "").toLowerCase();
            if (pval !== "" && oval !== "" && oval !== pval) { return false; }
        }
        return true;
    }

    function applyChild(child) {
        var on = (child.getAttribute("data-dep-on") || "").split(",");
        var attrs = (child.getAttribute("data-dep-attr") || "").split(",");
        var parents = [];
        for (var i = 0; i < on.length; i++) {
            var name = (on[i] || "").trim();
            var attr = ((attrs[i] || "").trim() || name);
            if (name === "") { continue; }
            var sel = document.querySelector('select[name="' + name + '"]');
            parents.push({ value: sel ? sel.value : "", attr: attr });
        }
        var hiddenSelected = false;
        $all("option", child).forEach(function (opt) {
            if (opt.value === "") { opt.hidden = false; return; }
            var vis = childVisible(opt, parents);
            opt.hidden = !vis;
            if (!vis && opt.selected) { hiddenSelected = true; }
        });
        if (hiddenSelected) {
            child.value = "";
            child.dispatchEvent(new Event("change", { bubbles: true }));
        }
    }

    function refreshDependents() {
        // Apply in DOM order (parent filters render before their
        // dependents), twice so chained resets settle correctly.
        $all("select[data-dep-on]").forEach(applyChild);
        $all("select[data-dep-on]").forEach(applyChild);
    }

    function initDepends() {
        var children = $all("select[data-dep-on]");
        if (!children.length) { return; }
        var parents = {};
        children.forEach(function (child) {
            (child.getAttribute("data-dep-on") || "").split(",").forEach(function (n) {
                n = (n || "").trim();
                if (n !== "") { parents[n] = true; }
            });
        });
        Object.keys(parents).forEach(function (name) {
            var sel = document.querySelector('select[name="' + name + '"]');
            if (sel && !sel.dataset.depBound) {
                sel.dataset.depBound = "1";
                sel.addEventListener("change", function () { refreshDependents(name); });
            }
        });
        children.forEach(applyChild);
    }

    /* ─── Assignment chips expander ───────────────── */
    function initExpanders() {
        $all("[data-admin-expand]").forEach(function (btn) {
            if (btn.dataset.bound === "1") { return; }
            btn.dataset.bound = "1";
            btn.addEventListener("click", function () {
                var target = document.getElementById(btn.getAttribute("data-admin-expand"));
                if (!target) { return; }
                var open = target.classList.toggle("d-none");
                btn.innerHTML = open ? btn.getAttribute("data-more") : btn.getAttribute("data-less");
            });
        });
    }

    /* ─── Generic confirm links (SweetAlert2) ─────────
       <a href="..." data-admin-confirm="Title|Text|ConfirmButton">
    ───────────────────────────────────────────────── */
    function initConfirmLinks() {
        $all("[data-admin-confirm]").forEach(function (link) {
            if (link.dataset.bound === "1") { return; }
            link.dataset.bound = "1";
            link.addEventListener("click", function (e) {
                e.preventDefault();
                var parts = (link.getAttribute("data-admin-confirm") || "||").split("|");
                var title = parts[0] || "Are you sure?";
                var text = parts[1] || "This action cannot be undone.";
                var okBtn = parts[2] || "Confirm";
                var href = link.getAttribute("href");
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        title: title,
                        text: text,
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: okBtn,
                        cancelButtonText: "Cancel",
                        confirmButtonColor: "#2563EB"
                    }).then(function (result) {
                        if (result.isConfirmed && href) { window.location.href = href; }
                    });
                } else if (window.confirm(title + "\n" + text)) {
                    if (href) { window.location.href = href; }
                }
            });
        });
    }

    /* ─── Admin question bank: bulk select, expand, density ── */
    function initAdminQuestionBank() {
        var card = document.getElementById("aqQuestionCard");
        if (!card) { return; }
        var selectAll = document.getElementById("aqSelectAll");
        var bulkBar = document.getElementById("aqBulkBar");
        var bulkCount = document.getElementById("aqBulkCount");
        var bulkForm = document.getElementById("aqBulkForm");
        var bulkDeleteBtn = document.getElementById("aqBulkDeleteBtn");
        var bulkClearBtn = document.getElementById("aqBulkClearBtn");

        function rowChecks() {
            return Array.prototype.slice.call(card.querySelectorAll(".aq-row-check"));
        }

        function refreshBulk() {
            var checked = rowChecks().filter(function (c) { return c.checked; });
            if (bulkCount) { bulkCount.textContent = checked.length; }
            if (bulkBar) { bulkBar.classList.toggle("d-none", checked.length === 0); }
            if (selectAll) {
                var all = rowChecks();
                selectAll.checked = all.length > 0 && checked.length === all.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener("change", function () {
                rowChecks().forEach(function (c) { c.checked = selectAll.checked; });
                refreshBulk();
            });
        }
        rowChecks().forEach(function (c) {
            c.addEventListener("change", refreshBulk);
        });
        if (bulkClearBtn) {
            bulkClearBtn.addEventListener("click", function () {
                rowChecks().forEach(function (c) { c.checked = false; });
                refreshBulk();
            });
        }
        if (bulkDeleteBtn && bulkForm) {
            bulkDeleteBtn.addEventListener("click", function () {
                var n = rowChecks().filter(function (c) { return c.checked; }).length;
                if (n === 0) { return; }
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        title: "Delete " + n + " question" + (n === 1 ? "" : "s") + "?",
                        text: "Selected questions will be permanently removed from the bank.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, delete",
                        cancelButtonText: "Cancel",
                        confirmButtonColor: "#DC2626"
                    }).then(function (result) {
                        if (result.isConfirmed) { bulkForm.submit(); }
                    });
                } else if (window.confirm("Delete " + n + " selected questions?")) {
                    bulkForm.submit();
                }
            });
        }

        Array.prototype.forEach.call(card.querySelectorAll("[data-aq-expand]"), function (btn) {
            btn.addEventListener("click", function () {
                var id = btn.getAttribute("data-aq-expand");
                var detail = card.querySelector('[data-qdetail="' + id + '"]');
                if (!detail) { return; }
                var open = detail.hasAttribute("hidden");
                detail.toggleAttribute("hidden");
                btn.setAttribute("aria-expanded", open ? "true" : "false");
            });
        });

        var densityBtn = document.getElementById("aqDensityBtn");
        if (densityBtn) {
            densityBtn.addEventListener("click", function () {
                var compact = card.classList.toggle("is-compact");
                densityBtn.setAttribute("aria-pressed", compact ? "true" : "false");
            });
        }

        refreshBulk();
    }

    /* ─── Admin AI: regenerate single question ──── */
    function initAdminAIRegen() {
        var modalEl = document.getElementById("aqRegenModal");
        if (!modalEl) { return; }
        var loading = document.getElementById("aqRegenLoading");
        var diff = document.getElementById("aqRegenDiff");
        var errBox = document.getElementById("aqRegenError");
        var footer = document.getElementById("aqRegenFooter");
        var saveForm = document.getElementById("aqRegenSaveForm");
        var saveBtn = document.getElementById("aqRegenSaveBtn");
        var againBtn = document.getElementById("aqRegenAgainBtn");
        var idLabel = document.getElementById("aqRegenIdLabel");
        var saveId = document.getElementById("aqRegenSaveId");
        var modal = (typeof bootstrap !== "undefined") ? new bootstrap.Modal(modalEl) : null;
        var current = null;

        function esc(s) {
            return String(s == null ? "" : s)
                .replace(/&/g, "&amp;").replace(/</g, "&lt;")
                .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }

        function optHtml(letter, text, correct) {
            return '<span class="aq-opt' + (correct ? " is-correct" : "") + '"><strong>' + letter + '.</strong> '
                + esc(text) + (correct ? ' <i class="bi bi-check-circle-fill"></i>' : "") + "</span>";
        }

        function showLoading(qid) {
            if (idLabel) { idLabel.textContent = "#" + qid; }
            if (saveId) { saveId.textContent = "#" + qid; }
            if (loading) { loading.classList.remove("d-none"); }
            if (diff) { diff.classList.add("d-none"); }
            if (errBox) { errBox.classList.add("d-none"); }
            if (footer) { footer.style.display = "none"; }
        }

        function showResult(data) {
            var letters = ["A", "B", "C", "D"];
            var oldHtml = "", newHtml = "";
            letters.forEach(function (L) {
                var key = "option_" + L.toLowerCase();
                oldHtml += optHtml(L, current["option_" + L.toLowerCase()] || current["option" + L] || "", (current.answer || "") === L);
                newHtml += optHtml(L, data[key] || "", (data.correct_answer || "") === L);
            });
            var oldQ = document.getElementById("aqOldQ");
            var oldOpts = document.getElementById("aqOldOpts");
            var newQ = document.getElementById("aqNewQ");
            var newOpts = document.getElementById("aqNewOpts");
            if (oldQ) { oldQ.textContent = current.question || ""; }
            if (oldOpts) { oldOpts.innerHTML = oldHtml; }
            if (newQ) { newQ.textContent = data.question || ""; }
            if (newOpts) { newOpts.innerHTML = newHtml; }
            document.getElementById("aqSaveQid").value = data.qid;
            document.getElementById("aqSaveQ").value = data.question || "";
            document.getElementById("aqSaveA").value = data.option_a || "";
            document.getElementById("aqSaveB").value = data.option_b || "";
            document.getElementById("aqSaveC").value = data.option_c || "";
            document.getElementById("aqSaveD").value = data.option_d || "";
            document.getElementById("aqSaveAns").value = data.correct_answer || "";
            if (loading) { loading.classList.add("d-none"); }
            if (diff) { diff.classList.remove("d-none"); }
            if (footer) { footer.style.display = ""; }
        }

        function showError(msg) {
            if (loading) { loading.classList.add("d-none"); }
            if (errBox) {
                errBox.textContent = msg || "AI could not regenerate this question right now.";
                errBox.classList.remove("d-none");
            }
        }

        function runRegen(btn) {
            if (!current) { return; }
            if (btn) { btn.disabled = true; btn.setAttribute("aria-busy", "true"); }
            showLoading(current.qid);
            var fd = new FormData();
            fd.append("ajax_action", "regenerate_question");
            fd.append("qid", current.qid);
            fetch("questions.php", { method: "POST", body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) { showResult(data); }
                    else { showError(data && data.message); }
                })
                .catch(function () { showError("Network error. Please try again."); })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.removeAttribute("aria-busy"); }
                });
        }

        Array.prototype.forEach.call(document.querySelectorAll("[data-aq-regen]"), function (btn) {
            btn.addEventListener("click", function () {
                current = {
                    qid: btn.getAttribute("data-aq-regen"),
                    question: btn.getAttribute("data-question") || "",
                    option_a: btn.getAttribute("data-option-a") || "",
                    option_b: btn.getAttribute("data-option-b") || "",
                    option_c: btn.getAttribute("data-option-c") || "",
                    option_d: btn.getAttribute("data-option-d") || "",
                    answer: btn.getAttribute("data-answer") || ""
                };
                if (modal) { modal.show(); }
                runRegen(null);
            });
        });

        if (againBtn) {
            againBtn.addEventListener("click", function () { runRegen(againBtn); });
        }
        if (saveBtn && saveForm) {
            saveBtn.addEventListener("click", function () {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...';
                saveForm.submit();
            });
        }

        var genForm = document.getElementById("aqAiGenerateForm");
        if (genForm) {
            genForm.addEventListener("submit", function () {
                var b = document.getElementById("aqAiGenerateBtn");
                if (b && !b.disabled) {
                    b.disabled = true;
                    b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Generating...';
                }
            });
        }
    }

    function init() {
        initDepends();
        initExpanders();
        initConfirmLinks();
        initAdminQuestionBank();
        initAdminAIRegen();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

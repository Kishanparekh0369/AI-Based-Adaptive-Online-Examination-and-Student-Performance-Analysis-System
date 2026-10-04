/* ══════════════════════════════════════════════════
   Faculty Portal — interactions
   Client filters, refresh buttons, table helpers.
   Server-side filtering/pagination remain authoritative;
   these helpers only enhance the current view.
   ══════════════════════════════════════════════════ */
(function () {
    "use strict";

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ─── Generic client-side card filter ─────────────
       Scope:  [data-fp-filter-scope]
       Search: [data-fp-search]  (matches [data-search] on cards)
       Cards:  [data-fp-card]
       Count:  [data-fp-count]   (optional, "{n} ...")
       Empty:  [data-fp-empty]   (optional)
       Clear:  [data-fp-clear]   (optional button) */
    function initCardFilters() {
        $all("[data-fp-filter-scope]").forEach(function (scope) {
            var search = $("[data-fp-search]", scope);
            var cards = $all("[data-fp-card]");
            var countEl = $("[data-fp-count]", scope);
            var emptyEl = $("[data-fp-empty]");
            var clearBtn = $("[data-fp-clear]", scope);
            var singular = scope.getAttribute("data-fp-singular") || "item";
            var plural = scope.getAttribute("data-fp-plural") || (singular + "s");

            // Cards may live outside the scope element (grid below toolbar).
            if (!cards.length) { return; }

            function apply() {
                var q = (search ? search.value : "").toLowerCase().trim();
                var visible = 0;
                cards.forEach(function (card) {
                    var text = (card.getAttribute("data-search") || "").toLowerCase();
                    var ok = !q || text.indexOf(q) !== -1;
                    var col = card.closest("[class*='col-']") || card;
                    col.style.display = ok ? "" : "none";
                    if (ok) { visible++; }
                });
                if (countEl) {
                    countEl.textContent = visible + " " + (visible === 1 ? singular : plural);
                }
                if (emptyEl) {
                    emptyEl.style.display = visible === 0 ? "" : "none";
                }
            }

            if (search) {
                search.addEventListener("input", apply);
            }
            if (clearBtn) {
                clearBtn.addEventListener("click", function () {
                    if (search) { search.value = ""; }
                    apply();
                    if (search) { try { search.focus(); } catch (e) {} }
                });
            }
            apply();
        });
    }

    /* ─── Refresh buttons: spinner + reload ─────────── */
    function initRefreshButtons() {
        $all("[data-fp-refresh]").forEach(function (btn) {
            if (btn.dataset.bound === "1") { return; }
            btn.dataset.bound = "1";
            btn.addEventListener("click", function () {
                if (btn.disabled) { return; }
                btn.disabled = true;
                btn.setAttribute("aria-busy", "true");
                var original = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Refreshing...';
                setTimeout(function () {
                    window.location.reload();
                }, 350);
                setTimeout(function () {
                    btn.disabled = false;
                    btn.removeAttribute("aria-busy");
                    btn.innerHTML = original;
                }, 5000);
            });
        });
    }

    /* ─── Table row live filter (current page only) ─── */
    function initTableFilters() {
        $all("[data-fp-table-search]").forEach(function (input) {
            var table = document.querySelector(input.getAttribute("data-fp-table-search"));
            if (!table) { return; }
            input.addEventListener("input", function () {
                var term = (input.value || "").toLowerCase().trim();
                table.querySelectorAll("tbody tr").forEach(function (row) {
                    if (row.querySelector("td[colspan]") && row.cells.length === 1) { return; }
                    row.style.display = row.innerText.toLowerCase().indexOf(term) !== -1 ? "" : "none";
                });
            });
        });
    }

    /* ─── Edit mode: scroll to builder + flash ────── */
    function initEditScroll() {
        var params;
        try {
            params = new URLSearchParams(window.location.search);
        } catch (e) { return; }
        if (!params.get("edit")) { return; }
        var form = document.getElementById("examFormBuilder");
        if (!form) { return; }
        setTimeout(function () {
            try {
                form.scrollIntoView({ behavior: "smooth", block: "start" });
            } catch (e) {
                form.scrollIntoView();
            }
            form.classList.add("fp-edit-flash");
            setTimeout(function () { form.classList.remove("fp-edit-flash"); }, 2600);
        }, 400);
    }

    /* ─── Password visibility toggles ───────────────
       Buttons: .fp-pw-toggle with aria-controls="inputId".
       Each toggle controls ONLY its own input. */
    function initPasswordToggles() {
        $all(".fp-pw-toggle").forEach(function (btn) {
            if (btn.dataset.bound === "1") { return; }
            btn.dataset.bound = "1";
            btn.addEventListener("click", function () {
                var input = document.getElementById(btn.getAttribute("aria-controls") || "");
                if (!input) {
                    var wrap = btn.closest(".fp-pw-wrap");
                    input = wrap ? wrap.querySelector('input[type="password"], input[type="text"]') : null;
                }
                if (!input) { return; }
                var showing = input.type === "text";
                input.type = showing ? "password" : "text";
                var icon = btn.querySelector("i");
                if (icon) { icon.className = showing ? "bi bi-eye" : "bi bi-eye-slash"; }
                btn.setAttribute("aria-label", showing ? "Show password" : "Hide password");
                btn.setAttribute("aria-pressed", showing ? "false" : "true");
            });
        });
    }

    /* ─── Password strength meter ───────────────────── */
    function fpPasswordScore(value) {
        if (!value) { return 0; }
        var score = 0;
        if (value.length >= 8) { score++; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score++; }
        if (/[0-9]/.test(value)) { score++; }
        if (/[^A-Za-z0-9]/.test(value)) { score++; }
        if (value.length >= 12 && score >= 3) { score = 4; }
        return Math.min(score, 4);
    }

    var fpStrengthLabels = ["", "Weak", "Fair", "Good", "Strong"];

    function initStrengthMeters() {
        $all("[data-fp-strength-for]").forEach(function (meter) {
            var input = document.getElementById(meter.getAttribute("data-fp-strength-for"));
            if (!input || input.dataset.strengthBound === "1") { return; }
            input.dataset.strengthBound = "1";
            var label = meter.querySelector(".fp-strength-label");
            var checklist = document.querySelector('[data-fp-checklist-for="' + meter.getAttribute("data-fp-strength-for") + '"]');
            function update() {
                var val = input.value || "";
                if (!val) {
                    meter.classList.remove("is-on");
                    meter.removeAttribute("data-score");
                    if (checklist) {
                        checklist.querySelectorAll("[data-check]").forEach(function (li) {
                            li.classList.remove("is-met");
                        });
                    }
                    return;
                }
                meter.classList.add("is-on");
                var score = fpPasswordScore(val);
                meter.setAttribute("data-score", String(score));
                if (label) { label.textContent = fpStrengthLabels[score] || "Weak"; }
                if (checklist) {
                    var checks = {
                        length: val.length >= 8,
                        cases: /[a-z]/.test(val) && /[A-Z]/.test(val),
                        number: /[0-9]/.test(val),
                        special: /[^A-Za-z0-9]/.test(val)
                    };
                    checklist.querySelectorAll("[data-check]").forEach(function (li) {
                        var key = li.getAttribute("data-check");
                        li.classList.toggle("is-met", !!checks[key]);
                    });
                }
            }
            input.addEventListener("input", update);
            input.addEventListener("focus", update);
        });
    }

    /* ─── Confirm-password live match ───────────────── */
    function initConfirmMatch() {
        $all("[data-fp-match]").forEach(function (confirm) {
            if (confirm.dataset.matchBound === "1") { return; }
            confirm.dataset.matchBound = "1";
            var main = document.getElementById(confirm.getAttribute("data-fp-match"));
            var errId = confirm.getAttribute("data-fp-match-error") || "";
            function setState(state, message) {
                confirm.classList.remove("is-valid", "is-invalid");
                var err = errId ? document.getElementById(errId) : null;
                if (state === "invalid") {
                    confirm.classList.add("is-invalid");
                    confirm.setAttribute("aria-invalid", "true");
                    if (err) {
                        err.classList.add("is-on");
                        var span = err.querySelector("span");
                        if (span) { span.textContent = message || "Passwords do not match."; }
                    }
                } else if (state === "valid") {
                    confirm.classList.add("is-valid");
                    confirm.removeAttribute("aria-invalid");
                    if (err) { err.classList.remove("is-on"); }
                } else {
                    confirm.removeAttribute("aria-invalid");
                    if (err) { err.classList.remove("is-on"); }
                }
            }
            confirm.addEventListener("input", function () {
                if (!confirm.value) { setState(""); return; }
                if (main && confirm.value !== main.value) {
                    setState("invalid", "Passwords do not match.");
                } else {
                    setState("valid");
                }
            });
            confirm.addEventListener("blur", function () {
                if (!confirm.value) {
                    setState("invalid", "Please confirm your new password.");
                } else if (main && confirm.value !== main.value) {
                    setState("invalid", "Passwords do not match.");
                } else if (confirm.value) {
                    setState("valid");
                }
            });
            if (main) {
                main.addEventListener("input", function () {
                    if (confirm.value) {
                        if (confirm.value !== main.value) {
                            setState("invalid", "Passwords do not match.");
                        } else {
                            setState("valid");
                        }
                    }
                });
            }
        });
    }

    /* ─── Photo preview before upload ───────────────── */
    function initPhotoPreview() {
        $all("[data-fp-photo-preview]").forEach(function (input) {
            if (input.dataset.previewBound === "1") { return; }
            input.dataset.previewBound = "1";
            input.addEventListener("change", function () {
                var img = document.getElementById(input.getAttribute("data-fp-photo-preview"));
                if (!img || !input.files || !input.files[0]) { return; }
                var file = input.files[0];
                if (["image/jpeg", "image/png", "image/webp"].indexOf(file.type) === -1) { return; }
                if (file.size > 2 * 1024 * 1024) { return; }
                var reader = new FileReader();
                reader.onload = function (e) {
                    if (img.tagName === "IMG") {
                        img.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            });
        });
    }

    /* ─── Submit button loading state ───────────────── */
    function initSubmitLoading() {
        $all("form[data-fp-loading]").forEach(function (form) {
            if (form.dataset.loadingBound === "1") { return; }
            form.dataset.loadingBound = "1";
            form.addEventListener("submit", function () {
                var btn = form.querySelector('[type="submit"]');
                if (!btn || btn.disabled) { return; }
                btn.disabled = true;
                btn.setAttribute("aria-busy", "true");
                btn.dataset.originalHtml = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + (form.getAttribute("data-fp-loading") || "Saving...");
            });
            window.addEventListener("pageshow", function (e) {
                if (e.persisted) {
                    var btn = form.querySelector('[type="submit"]');
                    if (btn) {
                        btn.disabled = false;
                        btn.removeAttribute("aria-busy");
                        if (btn.dataset.originalHtml) { btn.innerHTML = btn.dataset.originalHtml; }
                    }
                }
            });
        });
    }

    /* ─── Exam row confirms (clone / toggle / protected delete) */
    function fpSwalConfirm(title, text, confirmText, confirmColor, href) {
        if (typeof Swal === "undefined") {
            if (window.confirm(title + "\n" + text)) { window.location.href = href; }
            return;
        }
        Swal.fire({
            title: title,
            text: text,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: "Cancel",
            confirmButtonColor: confirmColor,
            reverseButtons: true,
            focusCancel: true
        }).then(function (result) {
            if (result && result.isConfirmed) { window.location.href = href; }
        });
    }

    function initExamConfirms() {
        $all(".js-confirm-clone").forEach(function (link) {
            if (link.dataset.bound === "1") { return; }
            link.dataset.bound = "1";
            link.addEventListener("click", function (e) {
                e.preventDefault();
                fpSwalConfirm("Duplicate this exam?", "A copy will be created as an Inactive draft with the same questions.", "Duplicate", "#4F46E5", link.getAttribute("href"));
            });
        });
        $all(".js-confirm-toggle").forEach(function (link) {
            if (link.dataset.bound === "1") { return; }
            link.dataset.bound = "1";
            link.addEventListener("click", function (e) {
                e.preventDefault();
                fpSwalConfirm("Change exam status?", "Students will immediately gain or lose access to this exam.", "Change Status", "#D97706", link.getAttribute("href"));
            });
        });
        $all("a.js-confirm-delete[data-attempts]").forEach(function (link) {
            if (link.dataset.attemptBound === "1") { return; }
            link.dataset.attemptBound = "1";
            link.addEventListener("click", function (e) {
                var n = parseInt(link.getAttribute("data-attempts") || "0", 10);
                if (n > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            title: "Cannot Delete Exam",
                            text: "This exam has " + n + " student attempt" + (n === 1 ? "" : "s") + ". Deleting it would destroy academic records. Set the exam to Inactive instead.",
                            icon: "error",
                            confirmButtonText: "Understood",
                            confirmButtonColor: "#2563EB"
                        });
                    } else {
                        window.alert("Cannot delete: " + n + " student attempts exist. Set Inactive instead.");
                    }
                }
            }, true);
        });
    }

    /* ─── Question bank: bulk select, expand, density ── */
    function initQuestionBank() {
        var card = document.getElementById("fpQuestionCard");
        if (!card) { return; }
        var selectAll = document.getElementById("fpSelectAll");
        var bulkBar = document.getElementById("fpBulkBar");
        var bulkCount = document.getElementById("fpBulkCount");
        var bulkForm = document.getElementById("fpBulkForm");
        var bulkDeleteBtn = document.getElementById("fpBulkDeleteBtn");
        var bulkClearBtn = document.getElementById("fpBulkClearBtn");

        function rowChecks() {
            return Array.prototype.slice.call(card.querySelectorAll(".fp-row-check"));
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

        Array.prototype.forEach.call(card.querySelectorAll("[data-fp-expand]"), function (btn) {
            btn.addEventListener("click", function () {
                var id = btn.getAttribute("data-fp-expand");
                var detail = card.querySelector('[data-qdetail="' + id + '"]');
                if (!detail) { return; }
                var open = detail.hasAttribute("hidden");
                detail.toggleAttribute("hidden");
                btn.setAttribute("aria-expanded", open ? "true" : "false");
            });
        });

        var densityBtn = document.getElementById("fpDensityBtn");
        if (densityBtn) {
            densityBtn.addEventListener("click", function () {
                var compact = card.classList.toggle("is-compact");
                densityBtn.setAttribute("aria-pressed", compact ? "true" : "false");
            });
        }

        refreshBulk();
    }

    function init() {
        initCardFilters();
        initRefreshButtons();
        initTableFilters();
        initQuestionBank();
        initEditScroll();
        initExamConfirms();
        initPasswordToggles();
        initStrengthMeters();
        initConfirmMatch();
        initPhotoPreview();
        initSubmitLoading();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

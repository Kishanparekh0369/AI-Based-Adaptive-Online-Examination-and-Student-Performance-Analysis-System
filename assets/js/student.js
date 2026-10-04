/* ══════════════════════════════════════════════════
   Student Portal — interactions
   Filters, reveal, strength meter, profile AJAX
   ══════════════════════════════════════════════════ */
(function () {
    "use strict";

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ─── Client-side exam filters (search + subject + status) ── */
    function initExamFilters() {
        var scope = document.querySelector("[data-exam-filter-scope]");
        if (!scope) return;

        var cards = $all("[data-exam-card]", scope);
        var search = $("[data-exam-search]", scope);
        var subject = $("[data-exam-subject]", scope);
        var status = $("[data-exam-status]", scope);
        var countEl = $("[data-exam-count]", scope);
        var emptyEl = $("[data-exam-empty]", scope);

        function apply() {
            var q = (search ? search.value : "").toLowerCase().trim();
            var subj = subject ? subject.value : "";
            var stat = status ? status.value : "";
            var visible = 0;

            cards.forEach(function (card) {
                var text = (card.getAttribute("data-search") || "").toLowerCase();
                var cSubj = card.getAttribute("data-subject") || "";
                var cStat = card.getAttribute("data-status") || "";

                var ok = true;
                if (q && text.indexOf(q) === -1) ok = false;
                if (subj && cSubj !== subj) ok = false;
                if (stat && cStat !== stat) ok = false;

                card.style.display = ok ? "" : "none";
                if (ok) visible++;
            });

            if (countEl) countEl.textContent = visible + (visible === 1 ? " exam" : " exams");
            if (emptyEl) emptyEl.style.display = visible === 0 ? "" : "none";
        }

        [search, subject, status].forEach(function (el) {
            if (!el) return;
            el.addEventListener(el.tagName === "SELECT" ? "change" : "input", apply);
        });

        var reset = $("[data-exam-reset]", scope);
        if (reset) {
            reset.addEventListener("click", function () {
                // Full reset: clear server-side query params too, otherwise
                // the list stays filtered even though inputs look empty.
                var btn = reset;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
                window.location.href = "exams.php";
            });
        }

        apply();
    }

    /* ─── Progress bars animate on load ───────────────── */
    function initProgressBars() {
        $all("[data-sp-progress]").forEach(function (bar) {
            var target = bar.getAttribute("data-sp-progress");
            requestAnimationFrame(function () {
                setTimeout(function () { bar.style.width = target + "%"; }, 60);
            });
        });
    }

    /* ─── Password strength meter ─────────────────────── */
    function passwordScore(v) {
        if (!v) return 0;
        var score = 0;
        if (v.length >= 8) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        if (v.length >= 12 && score >= 3) score = 4;
        return Math.min(score, 4);
    }

    function initStrength() {
        var labels = ["", "Weak", "Fair", "Good", "Strong"];
        $all("[data-sp-strength-for]").forEach(function (meter) {
            var input = document.getElementById(meter.getAttribute("data-sp-strength-for"));
            if (!input || input.dataset.bound === "1") return;
            input.dataset.bound = "1";
            var label = $(".sp-strength-label", meter);

            function update() {
                var v = input.value;
                if (!v) { meter.classList.remove("is-on"); meter.removeAttribute("data-score"); return; }
                meter.classList.add("is-on");
                var s = passwordScore(v);
                meter.setAttribute("data-score", String(s));
                if (label) label.textContent = labels[s] || "Weak";
            }
            input.addEventListener("input", update);
            input.addEventListener("focus", update);
        });
    }

    /* ─── Password show/hide ──────────────────────────── */
    function initPasswordToggles() {
        $all("[data-sp-pw-toggle]").forEach(function (btn) {
            if (btn.dataset.bound === "1") return;
            btn.dataset.bound = "1";
            btn.addEventListener("click", function () {
                var wrap = btn.closest(".input-group") || btn.closest(".sp-pw-wrap") || btn.parentElement;
                var input = wrap ? wrap.querySelector("input") : null;
                if (!input) return;
                var showing = input.type === "text";
                input.type = showing ? "password" : "text";
                var icon = btn.querySelector("i");
                if (icon) icon.className = showing ? "bi bi-eye" : "bi bi-eye-slash";
                btn.setAttribute("aria-label", showing ? "Show password" : "Hide password");
                btn.setAttribute("aria-pressed", showing ? "false" : "true");
            });
        });
    }

    /* ─── Profile AJAX (view/edit/password) ───────────── */
    function setBtnLoading(btn, loading, text) {
        if (!btn) return;
        if (loading) {
            btn.dataset.html = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute("aria-busy", "true");
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (text || "Saving...");
        } else {
            btn.disabled = false;
            btn.removeAttribute("aria-busy");
            if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
        }
    }

    function spShowError(input, errId, message) {
        var err = errId ? document.getElementById(errId) : null;
        if (err) {
            var span = err.querySelector("span");
            if (span) span.textContent = message || "";
            err.classList.toggle("is-on", !!message);
        }
        if (input) {
            input.classList.toggle("sp-input-invalid", !!message);
            input.classList.toggle("sp-input-valid", !message && (input.value || "").trim() !== "");
            if (message) { input.setAttribute("aria-invalid", "true"); }
            else { input.removeAttribute("aria-invalid"); }
        }
    }

    function spClearError(input, errId) {
        spShowError(input, errId, "");
    }

    function spAlert(icon, title, text) {
        if (typeof Swal !== "undefined") {
            Swal.fire({ icon: icon, title: title, text: text, confirmButtonColor: "#2563EB" });
        }
    }

    function initProfileForms() {
        // Change password form (change_password page)
        var pwForm = document.getElementById("spChangePasswordForm");
        if (pwForm) {
            var curInput = document.getElementById("spCurrentPw");
            var newInput = document.getElementById("spNewPw");
            var confInput = document.getElementById("spConfirmPw");

            function validatePwField(input, errId) {
                var v = (input.value || "");
                var name = input === curInput ? "Current password" : (input === newInput ? "New password" : "Confirm password");
                if (v === "") { spShowError(input, errId, name + " is required."); return false; }
                if ((input === newInput || input === confInput) && v.length < 8) {
                    spShowError(input, errId, "Password must be at least 8 characters."); return false;
                }
                if (input === confInput && newInput && v !== newInput.value) {
                    spShowError(input, errId, "Passwords do not match."); return false;
                }
                if (input === newInput && curInput && curInput.value !== "" && v === curInput.value) {
                    spShowError(input, errId, "New password must be different from current password."); return false;
                }
                spClearError(input, errId);
                return true;
            }

            [curInput, newInput, confInput].forEach(function (inp) {
                if (!inp) return;
                var map = {};
                map[curInput ? curInput.id : ""] = "spCurrentPwErr";
                map[newInput ? newInput.id : ""] = "spNewPwErr";
                map[confInput ? confInput.id : ""] = "spConfirmPwErr";
                inp.addEventListener("blur", function () { validatePwField(inp, map[inp.id]); });
                inp.addEventListener("input", function () {
                    if (inp.classList.contains("sp-input-invalid")) { validatePwField(inp, map[inp.id]); }
                    if (inp === newInput && confInput && confInput.value !== "") {
                        validatePwField(confInput, "spConfirmPwErr");
                    }
                });
            });

            pwForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var ok = true;
                if (curInput && !validatePwField(curInput, "spCurrentPwErr")) { ok = false; }
                if (newInput && !validatePwField(newInput, "spNewPwErr")) { ok = false; }
                if (confInput && !validatePwField(confInput, "spConfirmPwErr")) { ok = false; }
                if (!ok) {
                    var firstBad = pwForm.querySelector(".sp-input-invalid");
                    if (firstBad) { try { firstBad.focus(); } catch (err) {} }
                    return;
                }

                var btn = pwForm.querySelector("[type='submit']");
                var fd = new FormData(pwForm);
                fd.set("action", "change_password");

                setBtnLoading(btn, true, "Updating...");
                fetch("profile_ajax.php", { method: "POST", body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            pwForm.reset();
                            [curInput, newInput, confInput].forEach(function (inp) {
                                if (inp) { inp.classList.remove("sp-input-invalid", "sp-input-valid"); }
                            });
                            ["spCurrentPwErr", "spNewPwErr", "spConfirmPwErr"].forEach(function (id) {
                                var el = document.getElementById(id);
                                if (el) { el.classList.remove("is-on"); }
                            });
                            var meter = pwForm.querySelector("[data-sp-strength-for]");
                            if (meter) { meter.classList.remove("is-on"); meter.removeAttribute("data-score"); }
                            spAlert("success", "Password Changed Successfully", data.message || "Your password has been updated securely.");
                        } else {
                            if (data.message && data.message.toLowerCase().indexOf("current password") !== -1 && curInput) {
                                spShowError(curInput, "spCurrentPwErr", data.message);
                            }
                            spAlert("error", "Unable to Change Password", data.message || "Please check your details and try again.");
                        }
                    })
                    .catch(function () {
                        spAlert("error", "Error", "Something went wrong. Please try again.");
                    })
                    .finally(function () { setBtnLoading(btn, false); });
            });
        }

        // Edit profile form
        var editForm = document.getElementById("spEditProfileForm");
        if (editForm) {
            var nameInput = document.getElementById("spEditName");
            var phoneInput = document.getElementById("spEditPhone");
            var photoInput = document.getElementById("spEditPhoto");

            function validateName() {
                var v = (nameInput.value || "").trim();
                if (v === "") { spShowError(nameInput, "spEditNameErr", "Full name is required."); return false; }
                if (v.length < 3) { spShowError(nameInput, "spEditNameErr", "Full name must be at least 3 characters."); return false; }
                if (!/^[A-Za-z][A-Za-z\s.'-]{2,60}$/.test(v)) { spShowError(nameInput, "spEditNameErr", "Please enter a valid name (letters only)."); return false; }
                spClearError(nameInput, "spEditNameErr");
                return true;
            }

            function validatePhone() {
                var v = (phoneInput.value || "").trim();
                if (v === "") { spShowError(phoneInput, "spEditPhoneErr", "Mobile number is required."); return false; }
                if (!/^[0-9]{10}$/.test(v)) { spShowError(phoneInput, "spEditPhoneErr", "Please enter a valid 10 digit mobile number."); return false; }
                spClearError(phoneInput, "spEditPhoneErr");
                return true;
            }

            function validatePhoto() {
                if (!photoInput || !photoInput.files || !photoInput.files[0]) {
                    spShowError(null, "spEditPhotoErr", "");
                    return true;
                }
                var f = photoInput.files[0];
                if (["image/jpeg", "image/png"].indexOf(f.type) === -1) {
                    spShowError(null, "spEditPhotoErr", "Only JPEG and PNG images are allowed.");
                    return false;
                }
                if (f.size > 2 * 1024 * 1024) {
                    spShowError(null, "spEditPhotoErr", "File size must be under 2MB.");
                    return false;
                }
                spShowError(null, "spEditPhotoErr", "");
                return true;
            }

            if (nameInput) {
                nameInput.addEventListener("blur", validateName);
                nameInput.addEventListener("input", function () {
                    if (nameInput.classList.contains("sp-input-invalid")) { validateName(); }
                });
            }
            if (phoneInput) {
                phoneInput.addEventListener("blur", validatePhone);
                phoneInput.addEventListener("input", function () {
                    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "").slice(0, 10);
                    if (phoneInput.classList.contains("sp-input-invalid")) { validatePhone(); }
                });
            }
            if (photoInput) {
                var dropZone = document.getElementById("spAvatarDrop");
                var uploadBtn = document.getElementById("spUploadPhotoBtn");
                var resetBtn = document.getElementById("spResetPhotoBtn");
                var fileInfo = document.getElementById("spPhotoFileInfo");
                var preview = document.getElementById("spEditAvatarPreview");
                var originalPreview = preview ? preview.innerHTML : "";

                function openPicker() { photoInput.click(); }

                function showFileInfo(file) {
                    if (!fileInfo) { return; }
                    var kb = Math.max(Math.round(file.size / 1024), 1);
                    var label = file.name + " (" + kb + " KB)";
                    var span = fileInfo.querySelector("span");
                    if (span) { span.textContent = label; }
                    fileInfo.classList.remove("d-none");
                    if (resetBtn) { resetBtn.classList.remove("d-none"); }
                }

                function hideFileInfo() {
                    if (fileInfo) { fileInfo.classList.add("d-none"); }
                    if (resetBtn) { resetBtn.classList.add("d-none"); }
                }

                function renderPreview(file) {
                    if (!preview || !file) { return; }
                    var reader = new FileReader();
                    reader.onload = function (ev) {
                        preview.innerHTML = '<img src="' + ev.target.result + '" class="sp-profile-avatar mx-auto" alt="Profile preview" style="width:110px;height:110px;border-radius:24px;object-fit:cover;">';
                    };
                    reader.readAsDataURL(file);
                }

                function resetPhoto() {
                    photoInput.value = "";
                    if (preview) { preview.innerHTML = originalPreview; }
                    hideFileInfo();
                    spShowError(null, "spEditPhotoErr", "");
                }

                function handlePhotoFile(file) {
                    if (!file) { return; }
                    var okType = ["image/jpeg", "image/png"].indexOf(file.type) !== -1;
                    var okSize = file.size <= 2 * 1024 * 1024;
                    if (!okType) {
                        photoInput.value = "";
                        spShowError(null, "spEditPhotoErr", "Only JPEG and PNG images are allowed.");
                        return;
                    }
                    if (!okSize) {
                        photoInput.value = "";
                        spShowError(null, "spEditPhotoErr", "File size must be under 2MB.");
                        return;
                    }
                    spShowError(null, "spEditPhotoErr", "");
                    renderPreview(file);
                    showFileInfo(file);
                }

                if (dropZone) {
                    dropZone.addEventListener("click", openPicker);
                    dropZone.addEventListener("keydown", function (ev) {
                        if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); openPicker(); }
                    });
                    ["dragenter", "dragover"].forEach(function (evt) {
                        dropZone.addEventListener(evt, function (ev) {
                            ev.preventDefault();
                            dropZone.classList.add("is-dragover");
                        });
                    });
                    ["dragleave", "drop"].forEach(function (evt) {
                        dropZone.addEventListener(evt, function (ev) {
                            ev.preventDefault();
                            dropZone.classList.remove("is-dragover");
                        });
                    });
                    dropZone.addEventListener("drop", function (ev) {
                        var dt = ev.dataTransfer;
                        if (dt && dt.files && dt.files[0]) {
                            try { photoInput.files = dt.files; } catch (err) {}
                            handlePhotoFile(dt.files[0]);
                        }
                    });
                }
                if (uploadBtn) { uploadBtn.addEventListener("click", openPicker); }
                if (resetBtn) { resetBtn.addEventListener("click", resetPhoto); }
                photoInput.addEventListener("change", function () {
                    validatePhoto();
                    if (photoInput.files && photoInput.files[0]) {
                        var f = photoInput.files[0];
                        if (["image/jpeg", "image/png"].indexOf(f.type) !== -1 && f.size <= 2 * 1024 * 1024) {
                            renderPreview(f);
                            showFileInfo(f);
                        } else {
                            hideFileInfo();
                        }
                    } else {
                        hideFileInfo();
                    }
                });
            }

            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var okName = validateName();
                var okPhone = validatePhone();
                var okPhoto = validatePhoto();
                if (!okName || !okPhone || !okPhoto) {
                    var firstBad = editForm.querySelector(".sp-input-invalid");
                    if (firstBad) { try { firstBad.focus(); } catch (err) {} }
                    return;
                }

                var btn = editForm.querySelector("[type='submit']");
                var fd = new FormData(editForm);
                fd.set("action", "update_profile");

                setBtnLoading(btn, true, "Saving Changes...");
                fetch("profile_ajax.php", { method: "POST", body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            spAlert("success", "Profile Updated Successfully", data.message || "Your profile information has been updated.");
                            setTimeout(function () { window.location.reload(); }, 1600);
                        } else {
                            spAlert("error", "Unable to Update Profile", data.message || "Please check your information and try again.");
                        }
                    })
                    .catch(function () {
                        spAlert("error", "Error", "Something went wrong. Please try again.");
                    })
                    .finally(function () { setBtnLoading(btn, false); });
            });
        }
    }

    /* ─── Sidebar theme (dark / light), persisted ───── */
    var THEME_KEY = "sp-theme";
    function currentTheme() {
        try { return localStorage.getItem(THEME_KEY) === "light" ? "light" : "dark"; }
        catch (e) { return document.documentElement.getAttribute("data-sp-theme") === "light" ? "light" : "dark"; }
    }
    function applyTheme(theme) {
        document.documentElement.setAttribute("data-sp-theme", theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
        ["spThemeToggle", "spThemeToggleTop"].forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) return;
            var icon = btn.querySelector("i");
            if (icon) icon.className = theme === "light" ? "bi bi-moon-stars-fill" : "bi bi-sun-fill";
            btn.setAttribute("aria-pressed", theme === "light" ? "true" : "false");
            btn.setAttribute("title", theme === "light" ? "Switch to dark theme" : "Switch to light theme");
        });
    }
    function initTheme() {
        applyTheme(currentTheme());
        ["spThemeToggle", "spThemeToggleTop"].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn && !btn.dataset.bound) {
                btn.dataset.bound = "1";
                btn.addEventListener("click", function () {
                    applyTheme(currentTheme() === "light" ? "dark" : "light");
                });
            }
        });
    }

    /* ─── Drawer: close on Escape ─────────────────────── */
    function initDrawerKeys() {
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                document.body.classList.remove("sidebar-open", "sidebar-active", "menu-open");
            }
        });
    }

    /* ─── Feedback form ───────────────────────────────── */
    function initFeedbackForm() {
        var fbForm = document.getElementById("spFeedbackForm");
        if (!fbForm) { return; }
        var starsBox = document.getElementById("spStars");
        var hint = document.getElementById("spRatingHint");
        var msg = document.getElementById("spFbMessage");
        var count = document.getElementById("spFbCount");
        var counter = count ? count.parentElement : null;
        var words = ["", "Poor", "Average", "Good", "Very Good", "Excellent"];

        function selectedRating() {
            var c = fbForm.querySelector('.sp-stars input[name="rating"]:checked');
            return c ? parseInt(c.value, 10) : 0;
        }

        function paintHint() {
            var v = selectedRating();
            if (v > 0 && hint) {
                hint.innerHTML = "<strong>" + words[v] + "</strong> &middot; " + v + "/5";
                hint.classList.add("is-set");
            }
        }

        var starInputs = fbForm.querySelectorAll('.sp-stars input[name="rating"]');
        Array.prototype.forEach.call(starInputs, function (s) {
            s.addEventListener("change", function () {
                paintHint();
                spClearError(null, "spRatingErr");
                if (starsBox) { starsBox.classList.remove("sp-input-invalid"); }
            });
        });
        paintHint();

        function updateCount() {
            if (!msg || !count) { return; }
            count.textContent = msg.value.length;
            if (counter) { counter.classList.toggle("is-near", msg.value.length > 850); }
        }
        if (msg) {
            msg.addEventListener("input", function () {
                updateCount();
                if (msg.classList.contains("sp-input-invalid")) { validateMsg(); }
            });
            updateCount();
        }

        function validateRating() {
            if (selectedRating() < 1) {
                spShowError(null, "spRatingErr", "Please select a star rating.");
                if (starsBox) { starsBox.classList.add("sp-input-invalid"); }
                return false;
            }
            spClearError(null, "spRatingErr");
            if (starsBox) { starsBox.classList.remove("sp-input-invalid"); }
            return true;
        }

        function validateMsg() {
            var v = msg ? (msg.value || "").trim() : "";
            if (v === "") { spShowError(msg, "spMsgErr", "Please write your feedback message."); return false; }
            if (v.length < 10) { spShowError(msg, "spMsgErr", "Message must be at least 10 characters."); return false; }
            if (v.length > 1000) { spShowError(msg, "spMsgErr", "Message must be under 1000 characters."); return false; }
            spClearError(msg, "spMsgErr");
            return true;
        }

        fbForm.addEventListener("submit", function (e) {
            var okR = validateRating();
            var okM = validateMsg();
            if (!okR || !okM) {
                e.preventDefault();
                var target = !okR ? (starsBox ? starsBox.querySelector("label") : null) : msg;
                if (target) { try { target.focus(); } catch (err) {} }
                return;
            }
            var btn = fbForm.querySelector("[type='submit']");
            setBtnLoading(btn, true, "Submitting...");
        });
    }

    /* ─── Init ────────────────────────────────────────── */
    function init() {
        initExamFilters();
        initProgressBars();
        initStrength();
        initPasswordToggles();
        initProfileForms();
        initFeedbackForm();
        initTheme();
        initDrawerKeys();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

/* ─── Logout confirmation (global, with fallback) ───── */
function spConfirmLogout() {
    function doLogout() {
        if (window.__spLoggingOut) { return; }
        window.__spLoggingOut = true;
        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: "Signing out...",
                text: "Please wait while your session is securely ended.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function () { Swal.showLoading(); }
            });
            setTimeout(function () { window.location.href = "logout.php?confirm=yes"; }, 650);
        } else {
            window.location.href = "logout.php?confirm=yes";
        }
    }
    if (window.__spLoggingOut) { return false; }
    try {
        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: "Logout?",
                text: "Are you sure you want to end your current session?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Logout",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#DC2626",
                cancelButtonColor: "#64748B",
                reverseButtons: true,
                focusCancel: true
            }).then(function (result) {
                if (result && result.isConfirmed) { doLogout(); }
            });
            return false;
        }
    } catch (e) {}
    if (window.confirm("Are you sure you want to log out?")) { doLogout(); }
    return false;
}
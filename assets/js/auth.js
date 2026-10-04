/* ══════════════════════════════════════════════════
   Auth System — interactions & validation
   Login (role cards) + Registration
   ══════════════════════════════════════════════════ */
(function () {
    "use strict";

    /* ─── Helpers ─────────────────────────────────── */
    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function $all(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function fieldOf(input) {
        return input ? input.closest(".auth-field") : null;
    }

    function setFieldState(input, state, message) {
        var field = fieldOf(input);
        if (!field) return;

        field.classList.remove("is-valid", "is-invalid");
        input.removeAttribute("aria-invalid");

        if (state === "valid") {
            field.classList.add("is-valid");
        } else if (state === "invalid") {
            field.classList.add("is-invalid");
            input.setAttribute("aria-invalid", "true");
            var errEl = $(".auth-field-error", field);
            if (errEl) {
                errEl.innerHTML = '<i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span>';
                $("span", errEl).textContent = message || "This field is invalid.";
            }
        }
    }

    function clearField(input) {
        var field = fieldOf(input);
        if (field) field.classList.remove("is-valid", "is-invalid");
        if (input) input.removeAttribute("aria-invalid");
    }

    /* ─── Validators ──────────────────────────────── */
    var validators = {
        email: function (value) {
            if (!value) return "Please enter your email address.";
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) return "Please enter a valid email address.";
            return "";
        },
        text: function (value, min, label) {
            min = min || 2;
            label = label || "This field";
            if (!value) return label + " is required.";
            if (value.length < min) return label + " must be at least " + min + " characters.";
            return "";
        },
        passwordLogin: function (value) {
            if (!value) return "Password is required.";
            if (value.length < 6) return "Password must be at least 6 characters.";
            return "";
        },
        passwordNew: function (value) {
            if (!value) return "Password is required.";
            if (value.length < 8) return "Password must be at least 8 characters.";
            return "";
        },
        phone: function (value) {
            if (!value) return "Please enter your phone number.";
            if (!/^[0-9]{10}$/.test(value)) return "Please enter a valid 10 digit phone number.";
            return "";
        },
        requiredSelect: function (value, label) {
            if (!value) return "Please select " + (label || "an option") + ".";
            return "";
        },
        name: function (value) {
            if (!value) return "Please enter your full name.";
            if (value.length < 3) return "Full name must be at least 3 characters.";
            if (!/^[A-Za-z][A-Za-z\s.'-]{2,60}$/.test(value)) return "Please enter a valid name (letters only).";
            return "";
        }
    };

    function passwordScore(value) {
        if (!value) return 0;
        var score = 0;
        if (value.length >= 8) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        if (value.length >= 12 && score >= 3) score = 4;
        return Math.min(score, 4);
    }

    var strengthLabels = ["", "Weak", "Fair", "Good", "Strong"];

    /* ─── Role cards ──────────────────────────────── */
    function initRoleCards(form) {
        var inputs = $all('input[name="role"]', form);
        if (!inputs.length) return;

        function sync() {
            inputs.forEach(function (input) {
                var body = input.nextElementSibling;
                if (body && body.classList.contains("role-card-body")) {
                    body.setAttribute("aria-checked", input.checked ? "true" : "false");
                }
            });
        }

        inputs.forEach(function (input) {
            input.addEventListener("change", function () {
                sync();
                var wrap = form.querySelector('[data-field="role"]');
                if (wrap) wrap.classList.remove("is-invalid");
            });
        });

        sync();
    }

    /* ─── Password toggle ─────────────────────────── */
    function initPasswordToggles(root) {
        $all(".pw-toggle", root).forEach(function (btn) {
            if (btn.dataset.bound === "1") return;
            btn.dataset.bound = "1";
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                var input = document.getElementById(btn.getAttribute("aria-controls") || btn.dataset.target || "");
                if (!input) {
                    var wrap = btn.closest(".auth-input-wrap");
                    input = wrap ? wrap.querySelector("input[type=password], input[type=text]") : null;
                }
                if (!input) return;
                var showing = input.type === "text";
                input.type = showing ? "password" : "text";
                var icon = $("i", btn);
                if (icon) icon.className = showing ? "bi bi-eye" : "bi bi-eye-slash";
                btn.setAttribute("aria-label", showing ? "Show password" : "Hide password");
                btn.setAttribute("aria-pressed", showing ? "false" : "true");
                // Keep focus on input for accessibility
                input.focus({ preventScroll: true });
            });
            // Keyboard support
            btn.addEventListener("keydown", function (e) {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    btn.click();
                }
            });
        });
    }

    /* ─── Password strength meter ─────────────────── */
    function initStrength() {
        $all("[data-strength-for]").forEach(function (meter) {
            var input = document.getElementById(meter.getAttribute("data-strength-for"));
            if (!input || input.dataset.strengthBound === "1") return;
            input.dataset.strengthBound = "1";

            var label = $(".pw-strength-label", meter);

            function update() {
                var val = input.value;
                if (!val) {
                    meter.classList.remove("is-on");
                    meter.removeAttribute("data-score");
                    return;
                }
                meter.classList.add("is-on");
                var score = passwordScore(val);
                meter.setAttribute("data-score", String(score));
                if (label) label.textContent = strengthLabels[score] || "Weak";
            }

            input.addEventListener("input", update);
            input.addEventListener("focus", update);
        });
    }

    /* ─── Field-level validation wiring ───────────── */
    function validateInput(input, options) {
        options = options || {};
        var value = (input.value || "").trim();
        var name = input.getAttribute("name") || "";
        var msg = "";

        if (input.required && !value && input.type !== "radio" && input.type !== "checkbox") {
            msg = options.requiredMsg || (input.tagName === "SELECT" ? "Please select an option." : "This field is required.");
        } else if (value || options.validateEmpty) {
            switch (input.dataset.validate) {
                case "email":
                    msg = validators.email(value);
                    break;
                case "name":
                    msg = validators.name(value);
                    break;
                case "text":
                    msg = validators.text(value, parseInt(input.dataset.minlength || "3", 10), input.dataset.label || "This field");
                    break;
                case "password":
                    msg = input.dataset.mode === "new" ? validators.passwordNew(value) : validators.passwordLogin(value);
                    break;
                case "phone":
                    msg = validators.phone(value);
                    break;
                case "select":
                    msg = validators.requiredSelect(value, input.dataset.label);
                    break;
                default:
                    msg = "";
            }
        }

        if (msg) {
            setFieldState(input, "invalid", msg);
            return false;
        }
        if (value) setFieldState(input, "valid");
        else clearField(input);
        return true;
    }

    function initFieldValidation(form) {
        $all("[data-validate]", form).forEach(function (input) {
            var touched = false;

            input.addEventListener("blur", function () {
                touched = true;
                validateInput(input);
            });

            input.addEventListener("input", function () {
                if (!touched) return;
                if (fieldOf(input) && fieldOf(input).classList.contains("is-invalid")) {
                    validateInput(input);
                }
            });

            input.addEventListener("change", function () {
                if (input.dataset.validate === "select" || touched) {
                    validateInput(input);
                }
            });
        });
    }

    function validateForm(form) {
        var ok = true;
        var firstInvalid = null;

        // Role group
        var roleWrap = form.querySelector('[data-field="role"]');
        var roleInput = form.querySelector('input[name="role"]');
        if (roleWrap && roleInput) {
            var checked = form.querySelector('input[name="role"]:checked');
            if (!checked) {
                roleWrap.classList.add("is-invalid");
                var roleErr = $(".auth-field-error", roleWrap);
                if (roleErr) {
                    roleErr.innerHTML = '<i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><span></span>';
                    $("span", roleErr).textContent = "Please select how you want to log in.";
                }
                ok = false;
                firstInvalid = firstInvalid || roleInput;
            } else {
                roleWrap.classList.remove("is-invalid");
            }
        }

        $all("[data-validate]", form).forEach(function (input) {
            if (!validateInput(input)) {
                ok = false;
                if (!firstInvalid) firstInvalid = input;
            }
        });

        // Confirm password
        var confirm = form.querySelector('[data-match]');
        if (confirm) {
            var main = document.getElementById(confirm.getAttribute("data-match"));
            if (!confirm.value) {
                setFieldState(confirm, "invalid", "Please confirm your password.");
                ok = false;
                firstInvalid = firstInvalid || confirm;
            } else if (main && confirm.value !== main.value) {
                setFieldState(confirm, "invalid", "Passwords do not match.");
                ok = false;
                firstInvalid = firstInvalid || confirm;
            } else if (confirm.value) {
                setFieldState(confirm, "valid");
            }
        }

        if (firstInvalid) {
            try { firstInvalid.focus({ preventScroll: false }); } catch (e) { firstInvalid.focus(); }
            firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
        }

        return ok;
    }

    /* ─── Loading / double-submit guard ───────────── */
    function setLoading(btn, loading, loadingText, normalHtml) {
        if (!btn) return;
        if (loading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute("aria-busy", "true");
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>' + (loadingText || "Please wait...") + "</span>";
        } else {
            btn.disabled = false;
            btn.removeAttribute("aria-busy");
            btn.innerHTML = normalHtml || btn.dataset.originalHtml || btn.innerHTML;
        }
    }

    function bindSubmitGuard(form, options) {
        options = options || {};
        var submitting = false;
        var btn = form.querySelector('[type="submit"]');

        form.addEventListener("submit", function (e) {
            if (submitting) {
                e.preventDefault();
                return;
            }

            if (options.validate !== false) {
                if (!validateForm(form)) {
                    e.preventDefault();
                    return;
                }
            }

            submitting = true;
            setLoading(btn, true, options.loadingText || "Please wait...");
        });

        // Safety: re-enable if page restored from bfcache
        window.addEventListener("pageshow", function (e) {
            if (e.persisted) {
                submitting = false;
                setLoading(btn, false);
            }
        });
    }

    /* ─── Server-side errors → fields ─────────────── */
    function applyServerErrors(form, errors) {
        if (!errors) return;
        Object.keys(errors).forEach(function (key) {
            var input = form.querySelector('[name="' + key + '"]');
            if (input) setFieldState(input, "invalid", errors[key]);
        });
    }

    /* ─── Forgot password hint ────────────────────── */
    function bindForgot(root) {
        $all("[data-forgot-password]", root).forEach(function (el) {
            el.addEventListener("click", function (e) {
                e.preventDefault();
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "info",
                        title: "Password Help",
                        text: "Please contact your administrator to reset your account password.",
                        confirmButtonText: "OK",
                        confirmButtonColor: "#2563EB",
                        customClass: { popup: "auth-swal" }
                    });
                }
            });
        });
    }

    /* ─── Profile photo preview (register) ────────── */
    function initPhotoPreview() {
        $all("[data-photo-preview]").forEach(function (input) {
            input.addEventListener("change", function () {
                var imgId = input.getAttribute("data-photo-preview");
                var img = document.getElementById(imgId);
                if (!img) return;
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        img.classList.add("is-on");
                        var ph = img.parentElement && img.parentElement.querySelector(".auth-photo-placeholder");
                        if (ph) ph.style.display = "none";
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            });
        });
    }

    /* ─── Remember email ──────────────────────────── */
    function prefillRemembered(form) {
        var emailInput = form.querySelector('[name="email"]');
        if (!emailInput || emailInput.value) return;
        try {
            var val = localStorage.getItem("aiexam_remember_email");
            if (val) {
                emailInput.value = val;
                var remember = form.querySelector('[name="remember"]');
                if (remember) remember.checked = true;
            }
        } catch (e) { /* storage unavailable */ }
    }

    function storeRemembered(form, email) {
        try {
            var remember = form.querySelector('[name="remember"]');
            if (remember && remember.checked) {
                localStorage.setItem("aiexam_remember_email", email);
            } else {
                localStorage.removeItem("aiexam_remember_email");
            }
        } catch (e) { /* storage unavailable */ }
    }

    /* ─── Init ────────────────────────────────────── */
    function initAuthPage() {
        var loginForm = document.getElementById("authLoginForm");
        var registerForm = document.getElementById("authRegisterForm");

        initPasswordToggles(document);
        initStrength();
        bindForgot(document);
        initPhotoPreview();

        if (loginForm) {
            initRoleCards(loginForm);
            initFieldValidation(loginForm);
            prefillRemembered(loginForm);

            if (window.__AUTH_ERRORS__ && window.__AUTH_ERRORS__.email) {
                // focus first invalid from server on load
            }

            loginForm.addEventListener("submit", function (e) {
                var emailInput = loginForm.querySelector('[name="email"]');
                if (emailInput && emailInput.value) storeRemembered(loginForm, emailInput.value.trim());
            });

            bindSubmitGuard(loginForm, { loadingText: "Signing In..." });
        }

        if (registerForm) {
            initFieldValidation(registerForm);

            // Live confirm-password check
            var confirm = registerForm.querySelector("[data-match]");
            if (confirm) {
                confirm.addEventListener("input", function () {
                    if (!confirm.value) return;
                    var main = document.getElementById(confirm.getAttribute("data-match"));
                    if (main && confirm.value !== main.value) {
                        setFieldState(confirm, "invalid", "Passwords do not match.");
                    } else if (confirm.value) {
                        setFieldState(confirm, "valid");
                    }
                });
            }

            bindSubmitGuard(registerForm, { loadingText: "Creating Account..." });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAuthPage);
    } else {
        initAuthPage();
    }

    // Expose for PHP-injected server errors if needed
    window.AuthUI = {
        setFieldState: setFieldState,
        applyServerErrors: applyServerErrors,
        validateForm: validateForm
    };
})();

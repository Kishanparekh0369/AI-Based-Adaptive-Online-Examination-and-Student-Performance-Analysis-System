<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Change Password";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = $_POST["old_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $id = (int)$_SESSION["admin_id"];

    if ($old_password == "" || $new_password == "" || $confirm_password == "") {
        redirect_alert("All fields are required", "change_password.php");
    } else if (strlen($new_password) < 8) {
        redirect_alert("New password must be at least 8 characters", "change_password.php");
    } else if ($new_password != $confirm_password) {
        redirect_alert("Password confirmation does not match", "change_password.php");
    } else {
        $result = mysqli_query($conn, "SELECT password FROM admin WHERE id='$id' LIMIT 1");
        if ($result && $row = mysqli_fetch_assoc($result)) {
            if (password_verify($old_password, $row["password"])) {
                if (password_verify($new_password, $row["password"])) {
                    redirect_alert("New password must be different from the old password", "change_password.php");
                }
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                mysqli_query($conn, "UPDATE admin SET password='$new_hash' WHERE id='$id'");
                redirect_alert("Password changed successfully", "dashboard.php");
            } else {
                redirect_alert("Old password is incorrect", "change_password.php");
            }
        } else {
            redirect_alert("Account not found", "change_password.php");
        }
    }
}
include("../app/layouts/admin_header.php");
?>
<style>
.adm-pw-wrap { max-width: 1060px; margin: 0 auto; }
.adm-pw-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 20px;
    box-shadow: 0 20px 48px -20px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}
.adm-pw-side {
    position: relative;
    color: #fff;
    padding: clamp(24px, 3vw, 36px);
    background:
        radial-gradient(circle at 85% 10%, rgba(255, 255, 255, 0.20), transparent 45%),
        radial-gradient(circle at 10% 95%, rgba(34, 211, 238, 0.30), transparent 55%),
        linear-gradient(135deg, #1E293B 0%, #3730A3 55%, #2563EB 100%);
    overflow: hidden;
}
.adm-pw-side::before {
    content: "";
    position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
    background-size: 30px 30px;
    mask-image: radial-gradient(ellipse 90% 90% at 20% 20%, black 30%, transparent 75%);
    pointer-events: none;
}
.adm-pw-side > * { position: relative; z-index: 1; }
.adm-pw-shield {
    width: 60px; height: 60px; border-radius: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.7rem; color: #fff;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.30);
    margin-bottom: 18px;
    animation: admFloat 3.2s ease-in-out infinite;
}
@keyframes admFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
.adm-pw-side h2 { color: #fff; font-weight: 800; letter-spacing: -0.02em; font-size: clamp(1.3rem, 2.4vw, 1.65rem); margin: 0 0 8px; }
.adm-pw-side p { color: rgba(226, 232, 240, 0.82); font-size: 0.9rem; line-height: 1.65; margin: 0 0 20px; }
.adm-pw-tips { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.adm-pw-tips li {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 0.85rem; color: rgba(241, 245, 249, 0.92);
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px; padding: 10px 12px;
}
.adm-pw-tips li i { color: #6EE7B7; font-size: 1rem; margin-top: 1px; }
.adm-pw-form { padding: clamp(22px, 3vw, 36px); }
.adm-pw-field { margin-bottom: 18px; }
.adm-pw-field > label { display: block; font-size: 0.84rem; font-weight: 600; color: #1E293B; margin-bottom: 7px; }
.adm-pw-input { position: relative; display: flex; align-items: center; }
.adm-pw-input > i.adm-lead {
    position: absolute; left: 14px; font-size: 1rem; color: #94A3B8; pointer-events: none; z-index: 2;
    transition: color 0.22s ease;
}
.adm-pw-input input {
    width: 100%; min-height: 50px;
    padding: 11px 52px 11px 42px;
    font-size: 0.94rem; font-weight: 500; color: #0F172A;
    background: #fff; border: 1.5px solid #E2E8F0; border-radius: 12px; outline: none;
    transition: border-color 0.22s ease, box-shadow 0.22s ease;
}
.adm-pw-input input:focus { border-color: #2563EB; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); }
.adm-pw-input input:focus ~ i.adm-lead, .adm-pw-input:focus-within > i.adm-lead { color: #2563EB; }
.adm-pw-toggle {
    position: absolute; top: 50%; right: 4px; transform: translateY(-50%);
    width: 44px; height: 44px; min-width: 44px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 0; border-radius: 10px; background: transparent;
    color: #94A3B8; font-size: 1.05rem; cursor: pointer;
    transition: color 0.22s ease, background 0.22s ease;
}
.adm-pw-toggle:hover { color: #2563EB; background: rgba(37, 99, 235, 0.08); }
.adm-pw-toggle:focus-visible { outline: 2px solid #2563EB; outline-offset: 1px; }
.adm-strength { margin-top: 9px; display: none; }
.adm-strength.is-on { display: block; }
.adm-strength-track { height: 5px; border-radius: 999px; background: #E2E8F0; overflow: hidden; }
.adm-strength-bar { height: 100%; width: 0%; border-radius: 999px; background: #EF4444; transition: width 0.3s ease, background 0.3s ease; }
.adm-strength-meta { display: flex; justify-content: space-between; margin-top: 6px; font-size: 0.75rem; color: #64748B; }
.adm-strength-meta .adm-strength-label { font-weight: 700; color: #EF4444; }
.adm-strength[data-score="1"] .adm-strength-bar { width: 25%; background: #EF4444; }
.adm-strength[data-score="1"] .adm-strength-label { color: #EF4444; }
.adm-strength[data-score="2"] .adm-strength-bar { width: 50%; background: #F59E0B; }
.adm-strength[data-score="2"] .adm-strength-label { color: #D97706; }
.adm-strength[data-score="3"] .adm-strength-bar { width: 75%; background: #3B82F6; }
.adm-strength[data-score="3"] .adm-strength-label { color: #2563EB; }
.adm-strength[data-score="4"] .adm-strength-bar { width: 100%; background: #16A34A; }
.adm-strength[data-score="4"] .adm-strength-label { color: #16A34A; }
.adm-match { display: none; align-items: center; gap: 6px; margin-top: 7px; font-size: 0.8rem; font-weight: 600; }
.adm-match.is-ok { display: inline-flex; color: #15803D; }
.adm-match.is-bad { display: inline-flex; color: #B91C1C; }
.adm-pw-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
.adm-pw-actions .sp-btn { flex: 1 1 160px; min-height: 50px; justify-content: center; }
@media (max-width: 767.98px) {
    .adm-pw-input input { font-size: 16px; }
    .adm-pw-actions .sp-btn { flex: 1 1 100%; }
}
@media (prefers-reduced-motion: reduce) {
    .adm-pw-shield { animation: none; }
}
</style>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item">Account</li>
                    <li class="breadcrumb-item active">Change Password</li>
                </ol>
            </nav>

            <div class="sp-page-head sp-fade-up">
                <div>
                    <span class="sp-eyebrow"><i class="bi bi-shield-lock"></i> Account Security</span>
                    <h1>Change Password</h1>
                    <p>Update your administrator sign-in password. Use a strong, unique password.</p>
                </div>
            </div>

            <div class="adm-pw-wrap sp-fade-up" data-delay="1">
                <div class="adm-pw-card">
                    <div class="row g-0">
                        <div class="col-lg-5">
                            <div class="adm-pw-side h-100">
                                <span class="adm-pw-shield" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
                                <h2>Keep your account secure</h2>
                                <p>Your admin password protects the entire examination system. Change it periodically and never share it with anyone.</p>
                                <ul class="adm-pw-tips">
                                    <li><i class="bi bi-check-circle-fill"></i><span>Use at least 8 characters with letters, numbers &amp; symbols</span></li>
                                    <li><i class="bi bi-check-circle-fill"></i><span>Don't reuse passwords from other websites</span></li>
                                    <li><i class="bi bi-check-circle-fill"></i><span>You'll stay signed in on this device after changing</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <form method="post" class="adm-pw-form" id="admPwForm" novalidate autocomplete="off">
                                <div class="adm-pw-field">
                                    <label for="admOldPw">Current Password</label>
                                    <div class="adm-pw-input">
                                        <input type="password" id="admOldPw" name="old_password" placeholder="Enter your current password" autocomplete="current-password" required aria-describedby="admOldHint">
                                        <i class="bi bi-key adm-lead" aria-hidden="true"></i>
                                        <button type="button" class="adm-pw-toggle" data-target="admOldPw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                    </div>
                                    <small class="text-muted" id="admOldHint">We verify this before accepting a new password.</small>
                                </div>
                                <div class="adm-pw-field">
                                    <label for="admNewPw">New Password</label>
                                    <div class="adm-pw-input">
                                        <input type="password" id="admNewPw" name="new_password" placeholder="Min 8 characters" autocomplete="new-password" required minlength="8" aria-describedby="admStrengthBox">
                                        <i class="bi bi-lock adm-lead" aria-hidden="true"></i>
                                        <button type="button" class="adm-pw-toggle" data-target="admNewPw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                    </div>
                                    <div class="adm-strength" id="admStrengthBox" aria-live="polite">
                                        <div class="adm-strength-track"><div class="adm-strength-bar" id="admStrengthBar"></div></div>
                                        <div class="adm-strength-meta"><span>Password strength</span><span class="adm-strength-label" id="admStrengthLabel">Weak</span></div>
                                    </div>
                                </div>
                                <div class="adm-pw-field">
                                    <label for="admConfirmPw">Confirm New Password</label>
                                    <div class="adm-pw-input">
                                        <input type="password" id="admConfirmPw" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password" required minlength="8">
                                        <i class="bi bi-lock-fill adm-lead" aria-hidden="true"></i>
                                        <button type="button" class="adm-pw-toggle" data-target="admConfirmPw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                    </div>
                                    <span class="adm-match" id="admMatchMsg"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span></span></span>
                                </div>
                                <div class="adm-pw-actions">
                                    <a href="dashboard.php" class="sp-btn sp-btn-ghost"><i class="bi bi-arrow-left"></i> Cancel</a>
                                    <button type="submit" class="sp-btn sp-btn-primary" id="admPwSubmit"><i class="bi bi-shield-check"></i> Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Eye toggles
    document.querySelectorAll(".adm-pw-toggle").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            var input = document.getElementById(btn.getAttribute("data-target"));
            if (!input) return;
            var showing = input.type === "text";
            input.type = showing ? "password" : "text";
            var icon = btn.querySelector("i");
            if (icon) icon.className = showing ? "bi bi-eye" : "bi bi-eye-slash";
            btn.setAttribute("aria-label", showing ? "Show password" : "Hide password");
            btn.setAttribute("aria-pressed", showing ? "false" : "true");
            input.focus({ preventScroll: true });
        });
    });

    // Strength meter
    var newPw = document.getElementById("admNewPw");
    var box = document.getElementById("admStrengthBox");
    var label = document.getElementById("admStrengthLabel");
    var names = ["", "Weak", "Fair", "Good", "Strong"];
    function scorePw(v) {
        if (!v) return 0;
        var s = 0;
        if (v.length >= 8) s++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        if (v.length >= 12 && s >= 3) s = 4;
        return Math.min(s, 4);
    }
    if (newPw) {
        newPw.addEventListener("input", function () {
            var v = newPw.value;
            if (!v) { box.classList.remove("is-on"); box.removeAttribute("data-score"); return; }
            var sc = scorePw(v);
            box.classList.add("is-on");
            box.setAttribute("data-score", String(sc));
            label.textContent = names[sc] || "Weak";
            checkMatch();
        });
    }

    // Confirm match
    var confirmPw = document.getElementById("admConfirmPw");
    var matchMsg = document.getElementById("admMatchMsg");
    function checkMatch() {
        if (!confirmPw || !matchMsg) return;
        var a = newPw ? newPw.value : "", b = confirmPw.value;
        matchMsg.classList.remove("is-ok", "is-bad");
        if (!b) return;
        if (a === b && a.length >= 8) {
            matchMsg.classList.add("is-ok");
            matchMsg.querySelector("span").textContent = "Passwords match.";
        } else if (b.length > 0) {
            matchMsg.classList.add("is-bad");
            matchMsg.querySelector("span").textContent = a !== b ? "Passwords do not match." : "Use at least 8 characters.";
        }
    }
    if (confirmPw) {
        confirmPw.addEventListener("input", checkMatch);
    }

    // Loading state + double-submit guard
    var form = document.getElementById("admPwForm");
    var submitBtn = document.getElementById("admPwSubmit");
    var submitting = false;
    if (form) {
        form.addEventListener("submit", function (e) {
            if (submitting) { e.preventDefault(); return; }
            var a = newPw ? newPw.value : "", b = confirmPw ? confirmPw.value : "";
            if (a.length > 0 && a.length < 8) {
                e.preventDefault();
                if (typeof Swal !== "undefined") {
                    Swal.fire({ icon: "error", title: "Weak Password", text: "New password must be at least 8 characters.", confirmButtonColor: "#2563EB" });
                }
                newPw.focus();
                return;
            }
            if (b && a !== b) {
                e.preventDefault();
                if (typeof Swal !== "undefined") {
                    Swal.fire({ icon: "error", title: "Mismatch", text: "Password confirmation does not match.", confirmButtonColor: "#2563EB" });
                }
                confirmPw.focus();
                return;
            }
            submitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Updating...';
            }
        });
    }
});
</script>

<?php include("../app/layouts/admin_footer.php");
mysqli_close($conn); ?>
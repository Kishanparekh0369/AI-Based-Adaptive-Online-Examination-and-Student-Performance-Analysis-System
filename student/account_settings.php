<?php
require_once("../app/config/database.php");
require_once("../app/config/ai.php");

$conn = conn();
require_student();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Account Settings";
$id = (int)$_SESSION["student_id"];

$result = mysqli_query($conn, "SELECT * FROM students WHERE id=$id");
$student = mysqli_fetch_assoc($result);

include("../app/layouts/student_header.php");
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/student_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-gear-wide me-2"></i>Account Settings</h2>
                    <p class="text-muted mb-0">Manage your profile, security, and preferences</p>
                </div>
                <div>
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                        <i class="bi bi-shield-check me-1"></i>Verified Account
                    </span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="dashboard-card p-0 settings-nav-card">
                        <div class="p-3 text-center border-bottom">
                            <div class="settings-avatar-wrapper mx-auto mb-2">
                                <?php if (!empty($student['profile_image']) && file_exists("../assets/uploads/profile/" . $student['profile_image'])): ?>
                                    <img src="../assets/uploads/profile/<?php echo h($student['profile_image']); ?>" class="settings-avatar" id="headerProfileImg" alt="Profile">
                                <?php else: ?>
                                    <div class="settings-avatar settings-avatar-initials" id="headerProfileInitials">
                                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <button class="settings-avatar-edit-btn" onclick="document.getElementById('photoUploadInput').click()">
                                    <i class="bi bi-camera-fill"></i>
                                </button>
                                <input type="file" id="photoUploadInput" accept="image/jpeg,image/png" class="d-none" onchange="uploadProfilePhoto(this)">
                            </div>
                            <h6 class="fw-bold mb-0"><?php echo h($student['name']); ?></h6>
                            <small class="text-muted"><?php echo h($student['email']); ?></small>
                        </div>
                        <div class="settings-nav-list">
                            <a href="#profile-section" class="settings-nav-item active" data-section="profile">
                                <i class="bi bi-person-circle"></i> Profile
                            </a>
                            <a href="#security-section" class="settings-nav-item" data-section="security">
                                <i class="bi bi-shield-lock"></i> Security
                            </a>
                            <a href="#activity-section" class="settings-nav-item" data-section="activity">
                                <i class="bi bi-clock-history"></i> Login Activity
                            </a>
                            <a href="#preferences-section" class="settings-nav-item" data-section="preferences">
                                <i class="bi bi-sliders"></i> Preferences
                            </a>
                            <a href="#danger-section" class="settings-nav-item text-danger" data-section="danger">
                                <i class="bi bi-exclamation-triangle"></i> Delete Account
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div id="profile-section" class="settings-section active">
                        <div class="dashboard-card p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Profile Information</h5>
                                <button class="btn btn-outline-primary btn-sm" onclick="openEditProfileModal()">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Full Name</label>
                                        <span><?php echo h($student['name']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Email Address</label>
                                        <span><?php echo h($student['email']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Phone Number</label>
                                        <span><?php echo h($student['phone']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Student ID</label>
                                        <span>#<?php echo $student['id']; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Course</label>
                                        <span><?php echo h($student['course']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Semester</label>
                                        <span><?php echo h($student['semester']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Status</label>
                                        <span class="text-success"><i class="bi bi-check-circle-fill me-1 small"></i><?php echo h($student['status']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="settings-info-item">
                                        <label>Registered</label>
                                        <span><?php echo date('d M Y', strtotime($student['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="security-section" class="settings-section">
                        <div class="dashboard-card p-4 mb-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-key me-2 text-warning"></i>Change Password</h5>
                            <form id="changePasswordForm">
                                <input type="text" name="username" class="visually-hidden" value="<?php echo h($student['email'] ?? ''); ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-medium">Current Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" name="current_password" class="form-control" required minlength="6">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-medium">New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                                            <input type="password" name="new_password" class="form-control" required minlength="8" title="At least 8 characters">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-medium">Confirm Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
                                            <input type="password" name="confirm_password" class="form-control" required minlength="8" title="Must match the new password">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning mt-3 no-loader">
                                    <i class="bi bi-arrow-repeat me-1"></i> Update Password
                                </button>
                            </form>
                        </div>

                        <div class="dashboard-card p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="fw-bold mb-1"><i class="bi bi-shield-check me-2 text-success"></i>Two-Factor Authentication</h5>
                                    <p class="text-muted small mb-0">Add an extra layer of security to your account</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactorToggle" style="width:44px;height:22px" disabled>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0 small py-2">
                                <i class="bi bi-info-circle me-1"></i> Two-factor authentication will be available in a future update.
                            </div>
                        </div>
                    </div>

                    <div id="activity-section" class="settings-section">
                        <div class="dashboard-card p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-info"></i>Login Activity</h5>
                            <?php
                            $logins = mysqli_query($conn, "SELECT * FROM notifications WHERE user_type='student' AND user_id=$id AND title LIKE '%Login%' ORDER BY id ASC LIMIT 10");
                            if ($logins && mysqli_num_rows($logins) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Activity</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($log = mysqli_fetch_assoc($logins)): ?>
                                                <tr>
                                                    <td class="small"><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></td>
                                                    <td class="small"><?php echo h($log['message']); ?></td>
                                                    <td><span class="badge bg-success bg-opacity-10 text-success">Successful</span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-activity" style="font-size:2rem"></i>
                                    <p class="mt-2 mb-0 small">No recent login activity recorded.</p>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <?php if (!empty($student['last_login'])): ?>
                                    <span class="small text-muted">
                                        <i class="bi bi-dot me-1"></i>Last login: <strong><?php echo date('d M Y, h:i A', strtotime($student['last_login'])); ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="preferences-section" class="settings-section">
                        <div class="dashboard-card p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2 text-secondary"></i>Account Preferences</h5>
                            <div class="settings-preference-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-medium">Email Notifications</span>
                                        <p class="text-muted small mb-0">Receive exam results and updates via email</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-preference-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-medium">AI Performance Reports</span>
                                        <p class="text-muted small mb-0">Receive AI-generated insights after exams</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-light mt-3 mb-0 small py-2 text-muted border">
                                <i class="bi bi-info-circle me-1"></i> Preference settings coming soon.
                            </div>
                        </div>
                    </div>

                    <div id="danger-section" class="settings-section">
                        <div class="dashboard-card p-4 border-danger border-opacity-25">
                            <div class="d-flex align-items-start gap-3">
                                <div class="bg-danger bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-exclamation-triangle text-danger" style="font-size:1.5rem"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-danger mb-1">Delete Account</h5>
                                    <p class="text-muted small mb-2">Permanently delete your account and all associated data. This action cannot be undone.</p>
                                    <p class="small text-muted mb-2">This will remove:</p>
                                    <ul class="small text-muted mb-3">
                                        <li>Your profile and personal information</li>
                                        <li>All exam results and performance data</li>
                                        <li>AI feedback and insights</li>
                                    </ul>
                                    <button class="btn btn-outline-danger btn-sm" onclick="confirmDeleteAccount()">
                                        <i class="bi bi-trash me-1"></i> Delete My Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content professional-modal edit-modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <form id="editProfileForm">
                    <div class="text-center mb-3">
                        <div class="d-inline-block position-relative" style="width:90px;height:90px">
                            <div class="rounded-circle overflow-hidden border border-2 border-light shadow-sm w-100 h-100" id="editAvatarPreview">
                                <?php if (!empty($student['profile_image']) && file_exists("../assets/uploads/profile/" . $student['profile_image'])): ?>
                                    <img src="../assets/uploads/profile/<?php echo h($student['profile_image']); ?>" id="editAvatarImg" class="w-100 h-100" style="object-fit:cover">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center w-100 h-100" style="background:linear-gradient(135deg,#4f46e5,#2563eb);font-size:2rem;font-weight:700;color:#fff">
                                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="position-absolute bottom-0 end-0 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-primary rounded-circle p-1 lh-1 border border-2 border-white shadow" onclick="document.getElementById('editPhotoInput').click()" style="width:30px;height:30px" title="Upload photo">
                                    <i class="bi bi-folder-fill small"></i>
                                </button>
                            </div>
                            <input type="file" id="editPhotoInput" accept="image/jpeg,image/png" class="d-none" onchange="previewEditPhoto(this)">
                        </div>
                        <p class="text-muted small mt-1 mb-0"><i class="bi bi-info-circle me-1"></i>Upload a profile photo</p>
                    </div>
                    <div class="row g-2 g-md-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-medium mb-1">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person fs-6"></i></span>
                                <input type="text" name="name" class="form-control" value="<?php echo h($student['name']); ?>" placeholder="Enter your full name" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-medium mb-1">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone fs-6"></i></span>
                                <input type="text" name="phone" class="form-control" value="<?php echo h($student['phone']); ?>" placeholder="10-digit number" required maxlength="10" pattern="[0-9]{10}">
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <h6 class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-lock me-1"></i>Read-Only Information</h6>
                    <div class="row g-2 g-md-3">
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="bg-light rounded-3 px-3 py-2 border">
                                <small class="text-muted text-uppercase d-block" style="font-size:.65rem;letter-spacing:.5px">Email</small>
                                <span class="fw-medium small"><i class="bi bi-envelope text-primary me-1"></i><?php echo h($student['email']); ?></span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="bg-light rounded-3 px-3 py-2 border">
                                <small class="text-muted text-uppercase d-block" style="font-size:.65rem;letter-spacing:.5px">Course</small>
                                <span class="fw-medium small"><i class="bi bi-book text-warning me-1"></i><?php echo h($student['course']); ?></span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="bg-light rounded-3 px-3 py-2 border">
                                <small class="text-muted text-uppercase d-block" style="font-size:.65rem;letter-spacing:.5px">Semester</small>
                                <span class="fw-medium small"><i class="bi bi-layers text-info me-1"></i><?php echo h($student['semester']); ?></span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="profile_image_data" id="editProfileImageData">
                </form>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary px-4" onclick="submitEditProfile()">
                    <i class="bi bi-check-lg me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("changePasswordForm");
    if (!form) return;

    form.addEventListener("submit", function(event) {
        event.preventDefault();

        var button = form.querySelector("button[type='submit']");
        var originalText = button ? button.innerHTML : "";
        var formData = new FormData(form);
        formData.set("action", "change_password");

        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...';
        }

        fetch("profile_ajax.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                form.reset();
                Swal.fire({ icon: "success", title: "Updated", text: data.message || "Password changed successfully" });
            } else {
                Swal.fire({ icon: "error", title: "Error", text: data.message || "Could not change password" });
            }
        })
        .catch(function() {
            Swal.fire({ icon: "error", title: "Error", text: "Something went wrong. Please try again." });
        })
        .finally(function() {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        });
    });
});
</script>

<?php include("../app/layouts/student_footer.php"); ?>
<?php mysqli_close($conn); ?>

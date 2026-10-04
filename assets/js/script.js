function confirmDelete()
{
    return false;
}

function showAppToast(message, icon)
{
    if(typeof Swal === "undefined")
    {
        return;
    }
    Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true,
        width: "auto"
    }).fire({
        icon: icon || "success",
        title: message
    });
}

function confirmActionLink(event, title, text, icon)
{
    event.preventDefault();
    var link = event.currentTarget.getAttribute("href");
    Swal.fire({
        title: title || "Are you sure?",
        text: text || "This action cannot be undone.",
        icon: icon || "warning",
        showCancelButton: true,
        confirmButtonText: "YES",
        cancelButtonText: "NO",
        confirmButtonColor: "#dc3545"
    }).then(function(result){
        if(result.isConfirmed)
        {
            window.location.href = link;
        }
    });
}

function confirmSubmitExam(event)
{
    event.preventDefault();
    Swal.fire({
        title: "Submit Exam?",
        text: "Your selected answers will be submitted for final result calculation.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Submit Exam",
        cancelButtonText: "Review"
    }).then(function(result){
        if(result.isConfirmed)
        {
            document.getElementById("examForm").submit();
        }
    });
}

function validatePhone(phone)
{
    return /^[0-9]{10}$/.test(phone);
}

function startExamTimer(minutes)
{
    var seconds = parseInt(minutes, 10) * 60;
    var timer = document.getElementById("examTimer");
    var form = document.getElementById("examForm");
    var autoSubmit = document.getElementById("autoSubmit");
    var submitted = false;

    if(!timer || !form)
    {
        return;
    }

    if(isNaN(seconds) || seconds < 0)
    {
        seconds = 0;
    }

    function renderTimer()
    {
        var min = Math.floor(seconds / 60);
        var sec = seconds % 60;
        timer.innerHTML = (min < 10 ? "0" + min : min) + ":" + (sec < 10 ? "0" + sec : sec);

        if(seconds <= 0 && !submitted)
        {
            submitted = true;
            if(autoSubmit)
            {
                autoSubmit.value = "1";
            }
            Swal.fire({
                title: "Time Over!",
                text: "Your exam has been submitted automatically.",
                icon: "warning",
                allowOutsideClick: false,
                confirmButtonText: "View Result"
            }).then(function(){
                form.submit();
            });
            return;
        }

        seconds--;
    }

    renderTimer();
    setInterval(renderTimer, 1000);
}

function appConfirmLogout()
{
    if(window.__appLoggingOut)
    {
        return false;
    }
    if(typeof Swal === "undefined")
    {
        if(window.confirm("Are you sure you want to log out?"))
        {
            window.__appLoggingOut = true;
            window.location.href = "logout.php?confirm=yes";
        }
        return false;
    }
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
        focusCancel: true,
        allowOutsideClick: true,
        customClass: { popup: "auth-swal" }
    }).then(function(result){
        if(result && result.isConfirmed)
        {
            if(window.__appLoggingOut)
            {
                return;
            }
            window.__appLoggingOut = true;
            Swal.fire({
                title: "Signing out...",
                text: "Please wait while your session is securely ended.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function(){ Swal.showLoading(); }
            });
            setTimeout(function(){
                window.location.href = "logout.php?confirm=yes";
            }, 650);
        }
    });
    return false;
}

function confirmAdminLogout()
{
    return appConfirmLogout();
}

function confirmStudentLogout()
{
    return appConfirmLogout();
}

function confirmFacultyLogout()
{
    return appConfirmLogout();
}

// Account Settings - Profile functions
function openEditProfileModal() {
    fetch('profile_ajax.php?action=get_profile')
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success && resp.data) {
                var d = resp.data;
                var nameInput = document.querySelector('#editProfileForm [name="name"]');
                var phoneInput = document.querySelector('#editProfileForm [name="phone"]');
                if (nameInput) nameInput.value = d.name || '';
                if (phoneInput) phoneInput.value = d.phone || '';
                var preview = document.getElementById('editAvatarPreview');
                if (d.profile_image_url) {
                    preview.innerHTML = '<img src="' + d.profile_image_url + '" id="editAvatarImg" class="w-100 h-100" style="object-fit:cover">';
                } else {
                    preview.innerHTML = '<div class="d-flex align-items-center justify-content-center w-100 h-100" style="background:linear-gradient(135deg,#4f46e5,#2563eb);font-size:2rem;font-weight:700;color:#fff">' + (d.name ? d.name.charAt(0).toUpperCase() : 'S') + '</div>';
                }
                document.getElementById('editPhotoInput').value = '';
                var modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
                modal.show();
            }
        })
        .catch(function() {});
}

function previewEditPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('editAvatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" id="editAvatarImg" class="w-100 h-100" style="object-fit:cover">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitEditProfile() {
    var form = document.getElementById('editProfileForm');
    var formData = new FormData(form);
    formData.set('action', 'update_profile');
    var fileInput = document.getElementById('editPhotoInput');
    if (fileInput.files && fileInput.files[0]) {
        formData.set('profile_photo', fileInput.files[0]);
    }
    var btn = document.querySelector('#editProfileModal .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    fetch('profile_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.success) {
            Swal.fire({ icon: 'success', title: 'Updated!', text: resp.message || 'Profile updated successfully', timer: 2000, showConfirmButton: false });
            var modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
            if (modal) modal.hide();
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Failed to update profile' });
        }
    })
    .catch(function() {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.' });
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Changes';
    });
}

function uploadProfilePhoto(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: 'Too Large', text: 'File size must be under 2MB' });
        input.value = '';
        return;
    }
    if (['image/jpeg', 'image/png'].indexOf(file.type) === -1) {
        Swal.fire({ icon: 'error', title: 'Invalid Format', text: 'Only JPEG and PNG files are allowed' });
        input.value = '';
        return;
    }
    fetch('profile_ajax.php?action=get_profile')
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (!resp.success || !resp.data) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load profile data' });
                input.value = '';
                return;
            }
            var formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('name', resp.data.name || '');
            formData.append('phone', resp.data.phone || '');
            formData.append('profile_photo', file);
            return fetch('profile_ajax.php', { method: 'POST', body: formData });
        })
        .then(function(r) { return r ? r.json() : null; })
        .then(function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', title: 'Updated!', text: 'Profile photo updated successfully', timer: 2000, showConfirmButton: false });
                setTimeout(function() { location.reload(); }, 1500);
            } else if (resp) {
                Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Failed to update photo' });
            }
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong' });
        });
    input.value = '';
}

function confirmDeleteAccount() {
    Swal.fire({
        title: 'Delete Account?',
        text: 'This action cannot be undone! All your data will be permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        input: 'password',
        inputPlaceholder: 'Enter your password to confirm',
        inputAttributes: { autocapitalize: 'off', autocomplete: 'off' },
        preConfirm: function(password) {
            if (!password) {
                Swal.showValidationMessage('Password is required');
                return;
            }
            return fetch('profile_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_account&password=' + encodeURIComponent(password)
            }).then(function(r) {
                return r.json();
            }).then(function(resp) {
                if (!resp.success) {
                    throw new Error(resp.message || 'Failed to delete account');
                }
                return resp;
            }).catch(function(err) {
                Swal.showValidationMessage(err.message);
            });
        }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Your account has been permanently deleted.' });
            setTimeout(function() { window.location.href = (result.value && result.value.redirect) ? result.value.redirect : '../public/login.php'; }, 2000);
        }
    });
}
// END Account Settings functions

document.addEventListener("DOMContentLoaded", function(){
    var clockItems = document.querySelectorAll(".live-clock");
    var dateItems = document.querySelectorAll(".live-date");
    var welcome = document.getElementById("welcomeMessage");

    function updateClock()
    {
        var now = new Date();
        var hour = now.getHours();
        var greeting = "Good Evening";

        if(hour < 12)
        {
            greeting = "Good Morning";
        }
        else if(hour < 17)
        {
            greeting = "Good Afternoon";
        }

        clockItems.forEach(function(item){
            item.innerHTML = now.toLocaleTimeString();
        });

        dateItems.forEach(function(item){
            item.innerHTML = now.toLocaleDateString(undefined, { weekday: "short", year: "numeric", month: "short", day: "numeric" });
        });

        if(welcome)
        {
            welcome.innerHTML = greeting;
        }
    }

    updateClock();
    setInterval(updateClock, 1000);

    var currentPage = window.location.pathname.split("/").pop();
    document.querySelectorAll(".panel-sidebar a").forEach(function(link){
        var linkPage = link.getAttribute("href");
        if(linkPage == currentPage)
        {
            link.classList.add("active");
        }
    });

    document.querySelectorAll("[data-count]").forEach(function(counter){
        var target = parseFloat(counter.getAttribute("data-count"));
        var suffix = counter.getAttribute("data-suffix") || "";
        var current = 0;
        var steps = 35;
        var increment = target / steps;
        var timer = setInterval(function(){
            current += increment;
            if(current >= target)
            {
                current = target;
                clearInterval(timer);
            }
            counter.innerHTML = Math.round(current) + suffix;
        }, 25);
    });

    document.querySelectorAll("[data-bs-toggle='tooltip']").forEach(function(item){
        new bootstrap.Tooltip(item);
    });

    document.querySelectorAll(".js-confirm-delete").forEach(function(link){
        link.addEventListener("click", function(event){
            confirmActionLink(event, "Delete Confirmation", "Are you sure you want to delete this record?", "warning");
        });
    });

    document.querySelectorAll(".js-confirm-edit, .js-edit-question").forEach(function(button){
        button.addEventListener("click", function(){
            var modalId = button.getAttribute("data-target-modal");
            var modal = document.getElementById(modalId);
            if(modal)
            {
                var current = bootstrap.Modal.getInstance(modal);
                if(!current)
                {
                    current = new bootstrap.Modal(modal);
                }
                current.show();
            }
        });
    });

    document.querySelectorAll("form").forEach(function(form){
        if(form.getAttribute("data-ajax") === "true")
        {
            return;
        }
        form.addEventListener("submit", function(){
            var button = form.querySelector("button[type='submit'], button:not([type])");
            if(button && !button.classList.contains("no-loader"))
            {
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing';
                button.disabled = true;
            }
        });
    });

    document.querySelectorAll(".notification-toggle").forEach(function(button){
        button.addEventListener("click", function(){
            var url = button.getAttribute("data-notification-read-url");
            var badge = button.querySelector(".notification-badge");
            if(badge)
            {
                badge.remove();
            }
            if(url)
            {
                fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
                    .then(function(){ showAppToast("Notification marked as read"); })
                    .catch(function(){});
            }
        });
    });

    document.querySelectorAll(".notification-close").forEach(function(button){
        button.addEventListener("click", function(event){
            event.preventDefault();
            event.stopPropagation();
            var dropdown = button.closest(".dropdown");
            var toggle = dropdown ? dropdown.querySelector("[data-bs-toggle='dropdown']") : null;
            if(toggle && window.bootstrap)
            {
                bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
            }
        });
    });

    var sidebar = document.querySelector(".panel-sidebar");
    var backdrop = document.getElementById("sidebarBackdrop");
    function setSidebarOpen(open)
    {
        document.body.classList.toggle("sidebar-open", open);
        document.body.classList.toggle("sidebar-active", open);
        document.body.classList.toggle("menu-open", open);
    }
    document.querySelectorAll(".sidebar-open-btn").forEach(function(button){
        button.addEventListener("click", function(){
            setSidebarOpen(!document.body.classList.contains("sidebar-open"));
        });
    });
    document.querySelectorAll(".sidebar-close-btn").forEach(function(button){
        button.addEventListener("click", function(){
            setSidebarOpen(false);
        });
    });
    if(backdrop)
    {
        backdrop.addEventListener("click", function(){
            setSidebarOpen(false);
        });
    }
    if(sidebar)
    {
        sidebar.querySelectorAll("a").forEach(function(link){
            link.addEventListener("click", function(){
                setSidebarOpen(false);
            });
        });
    }

    var examTimer = document.getElementById("examTimer");
    if(examTimer && examTimer.dataset.duration)
    {
        startExamTimer(examTimer.dataset.duration);
    }

    document.querySelectorAll("[data-table-search]").forEach(function(input){
        var target = document.querySelector(input.getAttribute("data-table-search"));
        if(!target)
        {
            return;
        }
        input.addEventListener("input", function(){
            var term = input.value.toLowerCase();
            target.querySelectorAll("tbody tr").forEach(function(row){
                row.style.display = row.innerText.toLowerCase().indexOf(term) >= 0 ? "" : "none";
            });
        });
    });

    document.querySelectorAll("[data-client-table]").forEach(function(table){
        var scope = table.closest("[data-client-scope]") || document;
        var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr"));
        var pageSize = parseInt(table.getAttribute("data-page-size") || "10", 10);
        var pager = scope.querySelector("[data-client-pagination]");
        var filters = Array.prototype.slice.call(scope.querySelectorAll("[data-client-filter]"));
        var currentPage = 1;

        function matches(row)
        {
            return filters.every(function(filter){
                var key = filter.getAttribute("data-client-filter");
                var value = (filter.value || "").toLowerCase();
                if(value == ""){ return true; }
                if(key == "text"){ return row.innerText.toLowerCase().indexOf(value) >= 0; }
                return (row.getAttribute("data-" + key) || "").toLowerCase().indexOf(value) >= 0;
            });
        }

        function render()
        {
            var visible = rows.filter(matches);
            var totalPages = Math.max(Math.ceil(visible.length / pageSize), 1);
            if(currentPage > totalPages){ currentPage = totalPages; }
            rows.forEach(function(row){ row.style.display = "none"; });
            visible.slice((currentPage - 1) * pageSize, currentPage * pageSize).forEach(function(row){ row.style.display = ""; });

            if(pager)
            {
                pager.innerHTML = "";
                for(var page = 1; page <= totalPages; page++)
                {
                    var btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "btn btn-sm " + (page == currentPage ? "btn-primary" : "btn-outline-primary");
                    btn.textContent = page;
                    btn.addEventListener("click", function(){
                        currentPage = parseInt(this.textContent, 10);
                        render();
                    });
                    pager.appendChild(btn);
                }
            }
        }

        filters.forEach(function(filter){
            filter.addEventListener("input", function(){ currentPage = 1; render(); });
            filter.addEventListener("change", function(){ currentPage = 1; render(); });
        });
        render();
    });

    // Account Settings - Section navigation
    document.querySelectorAll(".settings-nav-item").forEach(function(item){
        item.addEventListener("click", function(e){
            e.preventDefault();
            var section = item.getAttribute("data-section");
            if (!section) return;
            document.querySelectorAll(".settings-nav-item").forEach(function(nav){ nav.classList.remove("active"); });
            item.classList.add("active");
            document.querySelectorAll(".settings-section").forEach(function(sec){ sec.classList.remove("active"); });
            var target = document.getElementById(section + "-section");
            if (target) target.classList.add("active");
            window.location.hash = section + "-section";
        });
    });
    if (window.location.hash) {
        var hash = window.location.hash.replace("#", "");
        var targetLink = document.querySelector('.settings-nav-item[data-section="' + hash.replace("-section", "") + '"]');
        if (targetLink) targetLink.click();
    }

    var scrollBtn = document.getElementById("scrollTopBtn");
    if(scrollBtn)
    {
        window.addEventListener("scroll", function(){
            if(window.scrollY > 250)
            {
                scrollBtn.classList.add("show");
            }
            else
            {
                scrollBtn.classList.remove("show");
            }
        });

        scrollBtn.addEventListener("click", function(){
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    // Scroll-reveal animations (progressive enhancement)
    var revealEls = document.querySelectorAll("[data-reveal]");
    if(revealEls.length)
    {
        revealEls.forEach(function(el){
            var delay = el.getAttribute("data-reveal-delay");
            if(delay)
            {
                el.style.setProperty("--reveal-delay", delay + "ms");
            }
        });

        if("IntersectionObserver" in window)
        {
            var revealObserver = new IntersectionObserver(function(entries){
                entries.forEach(function(entry){
                    if(entry.isIntersecting)
                    {
                        entry.target.classList.add("in");
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: "0px 0px -8% 0px" });

            revealEls.forEach(function(el){ revealObserver.observe(el); });
        }
        else
        {
            revealEls.forEach(function(el){ el.classList.add("in"); });
        }
    }
});

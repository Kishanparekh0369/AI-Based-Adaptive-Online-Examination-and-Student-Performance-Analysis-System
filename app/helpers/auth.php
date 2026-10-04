<?php
function current_logged_role()
{
    if (!empty($_SESSION["admin_id"])) {
        return "admin";
    }
    if (!empty($_SESSION["faculty_id"])) {
        return "faculty";
    }
    if (!empty($_SESSION["student_id"])) {
        return "student";
    }
    return "";
}

function unified_login_url()
{
    return "../public/login.php";
}

function dashboard_url_for_role($role)
{
    if ($role === "admin") {
        return "dashboard.php";
    }
    if ($role === "faculty") {
        return "dashboard.php";
    }
    if ($role === "student") {
        return "dashboard.php";
    }
    return "../public/login.php";
}

function guard_unauthorized($role)
{
    $current = current_logged_role();
    if ($current === $role) {
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
        }
        return;
    }

    if ($current === "") {
        flash_script("Please sign in to continue.", unified_login_url(), "warning", "Login Required");
        header("Location: " . unified_login_url());
        exit;
    }

    $dest = dashboard_url_for_role($current);
    flash_script("Access Denied. You are not authorized to view that page.", $dest, "error", "Access Denied");
    header("Location: " . $dest);
    exit;
}

function require_admin()
{
    guard_unauthorized("admin");
}

function require_student()
{
    guard_unauthorized("student");
}

function require_faculty()
{
    guard_unauthorized("faculty");
}

function faculty_login_password_matches($password, $storedPassword)
{
    $storedPassword = (string)$storedPassword;
    $passwordInfo = password_get_info($storedPassword);
    if ($storedPassword !== '' && $passwordInfo['algoName'] !== 'unknown') {
        return password_verify($password, $storedPassword);
    }
    return hash_equals((string)$storedPassword, (string)$password);
}

function student_login_password_matches($password, $storedPassword)
{
    $storedPassword = (string)$storedPassword;
    $passwordInfo = password_get_info($storedPassword);
    if ($storedPassword !== '' && $passwordInfo['algoName'] !== 'unknown') {
        return password_verify($password, $storedPassword);
    }
    return hash_equals((string)$storedPassword, (string)$password);
}

function admin_login_password_matches($password, $storedPassword)
{
    $storedPassword = (string)$storedPassword;
    $passwordInfo = password_get_info($storedPassword);
    if ($storedPassword !== '' && $passwordInfo['algoName'] !== 'unknown') {
        return password_verify($password, $storedPassword);
    }
    return hash_equals((string)$storedPassword, (string)$password);
}

if (!function_exists('student_password_matches')) {
    function student_password_matches($password, $storedPassword)
    {
        return student_login_password_matches($password, $storedPassword);
    }
}

function mark_login_success($role, $name, $dashboard_label = "")
{
    $_SESSION["login_success"] = array(
        "role" => (string)$role,
        "name" => (string)$name,
        "label" => (string)$dashboard_label
    );
}

function secure_session_regenerate()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function destroy_app_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
?>

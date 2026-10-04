<?php
require_once("../app/config/database.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../public/login.php");
    exit;
}

$conn = conn();
$email = isset($_POST["email"]) ? trim((string)$_POST["email"]) : "";
$password = isset($_POST["password"]) ? (string)$_POST["password"] : "";

if ($email === "" || $password === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../public/login.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, name, email, password, status FROM faculty WHERE email=? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($row && (isset($row["status"]) ? $row["status"] : "Active") === "Active" && faculty_login_password_matches($password, $row["password"])) {
        secure_session_regenerate();
        $_SESSION["faculty_id"] = (int)$row["id"];
        $_SESSION["faculty_name"] = $row["name"];
        $_SESSION["faculty_email"] = $row["email"];
        mysqli_query($conn, "UPDATE faculty SET last_login=NOW() WHERE id=" . (int)$row["id"]);
        mark_login_success("faculty", $row["name"]);
        redirect_alert("Faculty login successful", "dashboard.php");
    }
}

header("Location: ../public/login.php");
exit;

<?php
require_once __DIR__ . "/app/config/database.php";
require_once __DIR__ . "/app/helpers/auth.php";
$conn = conn();
$email = "kishan@gmail.com";
$stmt = mysqli_prepare($conn, "SELECT id, name, email, password, status FROM students WHERE email=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;
if ($row && student_login_password_matches("kishan@0369", $row["password"])) {
    $_SESSION["student_id"] = (int)$row["id"];
    $_SESSION["student_name"] = $row["name"];
    echo "LOGIN_OK " . $row["id"];
} else {
    echo "LOGIN_FAIL";
}
?>

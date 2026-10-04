<?php
require_once("../app/config/database.php");

$conn = conn();
require_student();

header('Content-Type: application/json');

$studentId = (int)$_SESSION["student_id"];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid action'];

function sp_json_fail($message)
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sp_student_password_matches($password, $storedPassword)
{
    $storedPassword = (string)$storedPassword;
    $passwordInfo = password_get_info($storedPassword);
    if ($storedPassword !== '' && $passwordInfo['algoName'] !== 'unknown') {
        return password_verify($password, $storedPassword);
    }
    return hash_equals((string)$storedPassword, (string)$password);
}

function sp_fetch_password_hash($conn, $studentId)
{
    $stmt = mysqli_prepare($conn, "SELECT password FROM students WHERE id=? LIMIT 1");
    if (!$stmt) { return null; }
    mysqli_stmt_bind_param($stmt, "i", $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? $row['password'] : null;
}

switch ($action) {

    case 'get_profile':
        $stmt = mysqli_prepare($conn, "SELECT id,name,email,phone,course,semester,status,profile_image,created_at,profile_image_updated_at,last_login FROM students WHERE id=? LIMIT 1");
        if (!$stmt) { sp_json_fail('Unable to load profile right now.'); }
        mysqli_stmt_bind_param($stmt, "i", $studentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $student = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if ($student) {
            $student['profile_image_url'] = '';
            if (!empty($student['profile_image']) && file_exists("../assets/uploads/profile/" . $student['profile_image'])) {
                $student['profile_image_url'] = "../assets/uploads/profile/" . $student['profile_image'];
            }
            $student['registration_date'] = !empty($student['created_at']) ? date('d M Y', strtotime($student['created_at'])) : '';
            $response = ['success' => true, 'data' => $student];
        } else {
            $response = ['success' => false, 'message' => 'Student not found'];
        }
        break;

    case 'update_profile':
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if ($name === '' || $phone === '') {
            sp_json_fail('Name and mobile number are required.');
        }
        if (!preg_match("/^[A-Za-z][A-Za-z\s.'-]{2,60}$/", $name)) {
            sp_json_fail('Please enter a valid full name (letters only).');
        }
        if (!preg_match("/^[0-9]{10}$/", $phone)) {
            sp_json_fail('Please enter a valid 10 digit mobile number.');
        }

        $filename = '';
        if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                sp_json_fail('Profile photo upload failed. Please try again.');
            }
            $mime = '';
            if (function_exists('finfo_open')) {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
                if ($fi) { $mime = (string)finfo_file($fi, $_FILES['profile_photo']['tmp_name']); finfo_close($fi); }
            }
            $allowed = ['image/jpeg', 'image/png'];
            if ($mime !== '' && !in_array($mime, $allowed, true)) {
                sp_json_fail('Only JPEG and PNG images are allowed.');
            }
            if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
                sp_json_fail('File size must be under 2MB.');
            }

            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $ext = $mime === 'image/png' ? 'png' : 'jpg';
            }
            $uploadDir = __DIR__ . '/../assets/uploads/profile/';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
            $filename = 'profile_' . $studentId . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $filename)) {
                sp_json_fail('Profile photo upload failed. Please try again.');
            }
        }

        if ($filename !== '') {
            $stmt = mysqli_prepare($conn, "UPDATE students SET name=?, phone=?, profile_image=?, profile_image_updated_at=NOW() WHERE id=?");
            if (!$stmt) { sp_json_fail('Unable to update profile right now.'); }
            mysqli_stmt_bind_param($stmt, "sssi", $name, $phone, $filename, $studentId);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE students SET name=?, phone=? WHERE id=?");
            if (!$stmt) { sp_json_fail('Unable to update profile right now.'); }
            mysqli_stmt_bind_param($stmt, "ssi", $name, $phone, $studentId);
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            $_SESSION["student_name"] = $name;
            $newImage = ($filename !== '' && file_exists('../assets/uploads/profile/' . $filename)) ? ('../assets/uploads/profile/' . $filename) : '';
            $response = ['success' => true, 'message' => 'Your profile information has been updated.', 'new_image' => $newImage, 'new_name' => $name];
        } else {
            mysqli_stmt_close($stmt);
            sp_json_fail('Unable to update profile right now.');
        }
        break;

    case 'change_password':
        $current = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $newPass === '' || $confirm === '') {
            sp_json_fail('All password fields are required.');
        }
        if (strlen($newPass) < 8) {
            sp_json_fail('New password must be at least 8 characters.');
        }
        if ($newPass !== $confirm) {
            sp_json_fail('New password and confirm password do not match.');
        }
        if ($newPass === $current) {
            sp_json_fail('New password must be different from your current password.');
        }

        $stored = sp_fetch_password_hash($conn, $studentId);
        if ($stored === null || !sp_student_password_matches($current, $stored)) {
            sp_json_fail('Your current password is incorrect.');
        }
        if (sp_student_password_matches($newPass, $stored)) {
            sp_json_fail('New password must be different from your current password.');
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, "UPDATE students SET password=? WHERE id=?");
        if (!$stmt) { sp_json_fail('Unable to change password right now.'); }
        mysqli_stmt_bind_param($stmt, "si", $newHash, $studentId);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            $response = ['success' => true, 'message' => 'Your password has been updated securely.'];
        } else {
            mysqli_stmt_close($stmt);
            sp_json_fail('Unable to change password right now.');
        }
        break;

    case 'delete_account':
        $password = (string)($_POST['password'] ?? '');
        if ($password === '') {
            sp_json_fail('Password is required to delete your account.');
        }
        $stored = sp_fetch_password_hash($conn, $studentId);
        if ($stored === null || !sp_student_password_matches($password, $stored)) {
            sp_json_fail('Incorrect password.');
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM students WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        unset($_SESSION["student_id"]);
        unset($_SESSION["student_name"]);
        $response = ['success' => true, 'message' => 'Your account has been deleted.', 'redirect' => '../public/login.php'];
        break;

    case 'record_login':
        $stmt = mysqli_prepare($conn, "UPDATE students SET last_login=NOW() WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $studentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $response = ['success' => true];
        break;
}

echo json_encode($response);

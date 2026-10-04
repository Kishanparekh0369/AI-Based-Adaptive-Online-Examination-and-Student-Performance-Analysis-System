<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

$conn = mysqli_connect("localhost", "root", "", "ai_adaptive_exam");

if (!$conn) {
    echo "<p style='color:red'>❌ Connection failed: " . mysqli_connect_error() . "</p>";
    exit;
}
echo "<p style='color:green'>✅ Database connected successfully</p>";

// Test admin table
$result = mysqli_query($conn, "SELECT * FROM admin");
if ($result === false) {
    echo "<p style='color:red'>❌ Admin query failed: " . mysqli_error($conn) . "</p>";
} else {
    echo "<p style='color:green'>✅ Admin query OK, rows: " . mysqli_num_rows($result) . "</p>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<p>Admin: {$row['name']} | Email: {$row['email']} | Password: {$row['password']}</p>";

        // Test exact login query
        $login = mysqli_query($conn, "SELECT * FROM admin WHERE email='{$row['email']}' AND password='{$row['password']}'");
        if ($login && mysqli_num_rows($login) > 0) {
            echo "<p style='color:green'>✅ Login query MATCHES for: {$row['email']}</p>";
        } else {
            echo "<p style='color:red'>❌ Login query FAILS for: {$row['email']}</p>";
            if ($login === false) echo "<p style='color:red'>SQL Error: " . mysqli_error($conn) . "</p>";
        }
    }
}

// Check PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check session
echo "<p>Session status: " . (session_status() === PHP_SESSION_NONE ? 'None' : 'Active') . "</p>";

mysqli_close($conn);
echo "<p><a href='admin/login.php'>Try Admin Login</a></p>";
echo "<p><a href='public/login.php'>Try Public Login</a></p>";
echo "<p><strong>Credentials:</strong> admin@gmail.com / admin123</p>";
?>
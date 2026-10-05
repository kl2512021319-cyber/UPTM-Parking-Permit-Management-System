<?php
session_start();
require_once "db.php";

// Redirect users who are already logged in
if (isset($_SESSION["user_id"])) {
    if (($_SESSION["role"] ?? "") === "Admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } else {
        // Find user by email
        $sql = "SELECT user_id, full_name, email, password, role
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (
            $user && $password === $user["password"])
         {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            // Redirect according to user role
            if ($user["role"] === "Admin") {
                header("Location: admin_dashboard.php");

            } else {
                header("Location: user_dashboard.php");
            }
            exit();
        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page login-page">
    <main class="auth-container">
        <!-- LEFT PANEL: LOGIN FORM -->
        <section class="auth-form-panel">
            <div class="auth-brand">
                <img src="images/uptm-logo.png" alt="UPTM Logo" class="auth-logo">
                <span class="brand-divider"></span>
                <div class="brand-text">
                    <strong>UPTM CHERAS</strong>
                    <span>Parking Permit Management System</span>
                </div>
            </div>

            <div class="auth-form-content">
                <div class="auth-heading">
                    <h1>LOGIN</h1>
                    <p>Log in to manage your campus parking permit.</p>
                </div>
                <!-- Login error message -->
                <?php if ($error !== ""): ?>
                    <div class="message error">
                        <?php echo htmlspecialchars($error,ENT_QUOTES,"UTF-8"); ?>
                    </div>
                <?php endif; ?>
                <!-- Login form -->
                <form action="login.php" method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email address"
                            autocomplete="email"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password"
                            placeholder="Enter your password" autocomplete="current-password"
                            oninput="this.setCustomValidity(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$%^&*()]).{8,}$/.test(this.value) ? '' : 
                            'Password must contain at least 8 characters: Uppercase letters(A-Z), Lowercase letters(a-z), Numbers(0-9) and Special character(ex.!@#$%^&*).')"
                            required
                        >
                    </div>

                    <button type="submit" class="btn-primary">
                        LOGIN
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <p class="auth-switch">Don't have an account?
                    <a href="register.php">Create an account</a>
                </p>
            </div>

            <p class="auth-footer">UPTM Parking Permit Management System</p>
        </section>
        <!-- RIGHT PANEL: CAMPUS IMAGE -->
        <section class="auth-visual-panel">
            <div class="visual-content">
                <span class="visual-tag">
                    YOUR CAMPUS PARKING PORTAL
                </span>
                <h2>Park smarter.<br>Start here.</h2>

                <p>
                    Track your applications, submit
                    payment proof and access your
                    parking permit in one place.
                </p>

                <div class="visual-line"></div>
                <span class="visual-location">
                    UPTM CHERAS · KUALA LUMPUR
                </span>
            </div>
        </section>
    </main>
</body>
</html>
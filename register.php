<?php
require_once "db.php";

$message = "";
$message_type = "";
$full_name = "";
$email = "";
$role = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = strtoupper(trim($_POST["full_name"] ?? ""));
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "";

    // Validate registration form
    if (
        $full_name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === "" ||
        $role === ""
    ) {
        $message = "Please fill in all fields.";
        $message_type = "error";

    } elseif (strlen($full_name) > 100) {
        $message = "Full name must not exceed 100 characters.";
        $message_type = "error";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 100
    ) {
        $message = "Please enter a valid email address.";
        $message_type = "error";

    } elseif (!in_array($role, ["Student", "Staff"], true)) {
        $message = "Please select a valid role.";
        $message_type = "error";

    } else {
        // Check if email is already registered
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($connect, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $message = "This email is already registered.";
            $message_type = "error";

        } else {
            // Save registered user
            $sql = "INSERT INTO users(full_name, email, password, role)
                    VALUES (?, ?, ?, ?)";

            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt,"ssss",$full_name,$email,$password,$role);

            if (mysqli_stmt_execute($stmt)) {
                $message = "Registration successful! You can now log in.";
                $message_type = "success";
                // Clear the form
                $full_name = "";
                $email = "";
                $role = "";

            } else {
                $message = "Registration failed. Please try again.";
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <main class="auth-container">
        <!-- LEFT SIDE: REGISTRATION FORM -->
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
                    <span class="eyebrow">WELCOME TO UPTM PARKING</span>
                    <h1>Create Account</h1>
                    <p>
                        Register to apply for your campus
                        parking permit.
                    </p>
                </div>
                <?php if ($message !== ""): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message,ENT_QUOTES,"UTF-8"); ?>
                    </div>
                <?php endif; ?>
                <form action="register.php" method="POST"class="auth-form">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" pattern=".{10,}"
                            title="Please enter at least 10 characters for your full name."
                            maxlength="100"
                            autocomplete="name"
                            value="<?php echo htmlspecialchars($full_name,ENT_QUOTES,'UTF-8'); ?>"
                            oninput="this.value = this.value.toUpperCase();"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email address"
                            maxlength="100"
                            autocomplete="email"
                            value="<?php echo htmlspecialchars($email,ENT_QUOTES,'UTF-8'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="at least 8 characters "
                            minlength="8"
                            autocomplete="new-password"
                            oninput="this.setCustomValidity(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$%^&*()]).{8,}$/.test(this.value) ? '' : 
                            'Password must contain at least 8 characters: Uppercase letters(A-Z), Lowercase letters(a-z), Numbers(0-9) and Special character(ex.!@#$%^&*).')"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password"
                            minlength="8"
                            autocomplete="new-password"
                            oninput="this.setCustomValidity(this.value !== document.getElementById('password').value? 'Passwords do not match.': '')"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="role">Register As</label>
                        <select id="role" name="role" required>
                            <option value="">Select your role</option>
                            <option value="Student" <?php
                                if ($role === "Student") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Student
                            </option>

                            <option value="Staff" <?php
                                if ($role === "Staff") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Staff
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Create Account<span aria-hidden="true">&rarr;</span></button>
                </form>

                <p class="auth-switch">
                    Already have an account?
                    <a href="login.php">Back to Login</a>
                </p>
            </div>
            <p class="auth-footer">UPTM Parking Permit Management System</p>
        </section>
        <!-- RIGHT SIDE: UPTM BUILDING IMAGE -->
        <section class="auth-visual-panel">
            <div class="visual-content">
                <span class="visual-tag">CAMPUS PARKING MADE SIMPLE</span>
                <h2>Your parking permit,<br>all in one place.</h2>
                <p>
                    Apply, track your application,
                    submit payment and print your
                    parking sticker online.
                </p>

                <div class="visual-line"></div>
                <span class="visual-location">UPTM CHERAS · KUALA LUMPUR</span>
            </div>
        </section>
    </main>
</body>
</html>
<?php session_start();
require_once "db.php";

// Allow Student and Staff only
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!in_array($_SESSION["role"] ?? "", ["Student", "Staff"], true)) {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$error = "";
$plate_number = "";
$vehicle_type = "";
$vehicle_model = "";
$vehicle_colour = "";

// Prevent HTML from being displayed as code
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES,"UTF-8");
}

// Process the application form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $plate_number = strtoupper(trim($_POST["plate_number"] ?? ""));
    $vehicle_type = trim( $_POST["vehicle_type"] ?? "");
    $vehicle_model = strtoupper(trim($_POST["vehicle_model"] ?? ""));
    $vehicle_colour = strtoupper(trim($_POST["vehicle_colour"] ?? ""));

    // Validate form
    if (
        $plate_number === "" ||
        $vehicle_type === "" ||
        $vehicle_model === "" ||
        $vehicle_colour === ""
    ) {
        $error = "Please fill in all vehicle details.";

    } elseif (!preg_match("/^[A-Z0-9]{3,10}$/", $plate_number)) {
        $error = "Please enter a valid vehicle plate number.";

    } elseif (!in_array($vehicle_type, ["Car", "Motorcycle"], true)) {
        $error = "Please select a valid vehicle type.";

    } elseif (
        strlen($vehicle_model) < 3 ||
        strlen($vehicle_model) > 100
    ) {
        $error = "Vehicle model must be between 3 and 100 characters.";

    } elseif (
        strlen($vehicle_colour) < 3 ||
        strlen($vehicle_colour) > 50
    ) {
        $error = "Vehicle colour must be between 3 and 50 characters.";
    } else {
        try {mysqli_begin_transaction($connect);

            // Insert vehicle details
            $vehicle_sql = "INSERT INTO vehicles(user_id, plate_number, vehicle_type, vehicle_model, vehicle_colour)
                            VALUES (?, ?, ?, ?, ?)";

            $vehicle_stmt = mysqli_prepare($connect, $vehicle_sql);

            if (!$vehicle_stmt) 
                {throw new Exception("Unable to prepare vehicle record.");}

            mysqli_stmt_bind_param(
                $vehicle_stmt,
                "issss",
                $user_id,
                $plate_number,
                $vehicle_type,
                $vehicle_model,
                $vehicle_colour
            );

            if (!mysqli_stmt_execute($vehicle_stmt)) 
            {throw new Exception("Unable to save vehicle details.");}

            $vehicle_id = mysqli_insert_id($connect);

            // Insert parking permit application
            $application_sql = "INSERT INTO permit_applications
                                (user_id, vehicle_id, application_date,status)
                                VALUES (?, ?, CURDATE(), 'Pending')";

            $application_stmt = mysqli_prepare($connect,$application_sql);

            if (!$application_stmt) 
            {throw new Exception("Unable to prepare application.");}

            mysqli_stmt_bind_param($application_stmt, "ii", $user_id, $vehicle_id);

            if (!mysqli_stmt_execute($application_stmt)) 
            {throw new Exception("Unable to submit application.");}

            mysqli_commit($connect);

            // Save a success message for the dashboard
            $_SESSION["application_success"] = "Your parking permit application has been submitted successfully! ". "Your application is now Pending and will be reviewed by Admin.";

            // Redirect user to dashboard
            header("Location: user_dashboard.php");
            exit();

        } catch (Throwable $e) {
            mysqli_rollback($connect);
            error_log($e->getMessage());
            $error = "Unable to submit your application. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Parking Permit | UPTM</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body class="dashboard-page apply-page">
    <!-- NAVIGATION -->
    <header class="site-header">
        <a href="user_dashboard.php" class="site-brand">
            <img src="images/uptm-logo.png" alt="UPTM Logo" class="site-logo">
            <span class="site-brand-text">
                <strong>UPTM Parking Permit</strong>
                <small>Management System</small>
            </span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="user_dashboard.php" class="nav-link">Dashboard</a>
            <a href="apply_permit.php" class="nav-link active" aria-current="page">Apply Permit</a>
            <a href="my_applications.php" class="nav-link">My Applications</a>
            <a href="logout.php" class="nav-link nav-logout">Logout</a>
        </nav>
    </header>

    <!-- MAIN CONTENT -->
    <main class="apply-new-main">
        <div class="apply-new-layout">
            <!-- LEFT SIDE: INFORMATION -->
            <section class="apply-new-intro">
                <span class="apply-new-eyebrow">
                    UPTM CHERAS PARKING
                </span>

                <h1>Apply for your <span>Parking Permit.</span></h1>

                <p class="apply-new-description">
                    Register your vehicle and submit your
                    parking permit application online.
                    Follow the application flow below
                    to receive your campus parking sticker.
                </p>

                <div class="apply-flow">
                    <h2>Parking Permit Application Flow</h2>
                    <div class="apply-flow-item">
                        <span class="apply-flow-number">01</span>
                        <div>
                            <strong>Submit Application</strong>
                            <p>Complete the vehicle details form and submit your application.</p>
                        </div>
                    </div>
                    <div class="apply-flow-item">
                        <span class="apply-flow-number">02</span>
                        <div>
                            <strong>Application Review</strong>
                            <p>Admin reviews your application and updates its approval status.</p>
                        </div>
                    </div>

                    <div class="apply-flow-item">
                        <span class="apply-flow-number">03</span>
                        <div>
                            <strong>Payment Verification</strong>
                            <p>
                                After approval, make the payment
                                and upload your payment proof
                                for Admin verification.
                            </p>
                        </div>
                    </div>

                    <div class="apply-flow-item">
                        <span class="apply-flow-number">04</span>
                        <div>
                            <strong>Print Parking Sticker</strong>
                            <p>
                                Once your payment is verified
                                and your permit is issued,
                                print your parking sticker.
                            </p>
                        </div>
                    </div>
                </div>

                <p class="apply-new-note">Already submitted an application?
                    <a href="my_applications.php">Check your application status &rarr;</a>
                </p>
            </section>

            <!-- RIGHT SIDE: APPLICATION FORM -->
            <section class="apply-new-form-card">
                <div class="apply-new-form-header">
                    <span class="apply-new-form-tag">
                        NEW APPLICATION
                    </span>
                    <h2>Vehicle Details</h2>
                    <p>Please provide accurate information about your vehicle.
                    </p>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="message error">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>
                <form action="apply_permit.php" method="POST" class="apply-new-form">
                    <div class="form-group">
                        <label for="plate_number">Vehicle Plate Number</label>
                        <input type="text" id="plate_number" name="plate_number" placeholder="e.g. ABC1234"
                            pattern=".{3,10}" maxlength="10" value="<?php echo e($plate_number); ?>"
                            title="Vehicle plate number must be at least 3 characters."
                            oninput="this.value = this.value.toUpperCase();" required>
                    </div>

                    <div class="form-group">
                        <label for="vehicle_type">Vehicle Type</label>
                        <select id="vehicle_type" name="vehicle_type" required>
                            <option value="">Select vehicle type</option>
                            <option value="Car" <?php if ($vehicle_type === "Car") {echo "selected";}?>>Car</option>
                            <option value="Motorcycle" <?php
                                if ($vehicle_type === "Motorcycle") {echo "selected";}?>>
                                Motorcycle
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="vehicle_model">Vehicle Model</label>
                        <input type="text" id="vehicle_model" name="vehicle_model" placeholder="e.g. Perodua Myvi"
                            pattern=".{3,100}" maxlength="100" value="<?php echo e($vehicle_model); ?>"
                            title="Vehicle model must be at least 3 characters."
                            oninput="this.value = this.value.toUpperCase();"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="vehicle_colour">Vehicle Colour</label>
                        <input type="text" id="vehicle_colour" name="vehicle_colour" placeholder="e.g. White"
                            pattern=".{3,50}" maxlength="50" value="<?php echo e($vehicle_colour); ?>"
                            title="Please enter a valid vehicle colour, e.g. WHITE, BLACK or DARK BLUE."
                            oninput="this.value = this.value.toUpperCase();"
                            required
                        >
                    </div>

                    <div class="apply-new-notice">Please check your vehicle detailsbefore submitting this application.</div>

                    <button type="submit" class="btn-primary">Submit Application
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <p class="apply-new-form-footer">
                    Your application will be marked as
                    <strong>Pending</strong> until Admin reviews it.
                </p>
            </section>
        </div>
    </main>
    <footer class="dashboard-footer"> &copy; <?php echo date("Y"); ?> UPTM Parking Permit Management System</footer>
</body>
</html>
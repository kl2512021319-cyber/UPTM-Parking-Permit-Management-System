<?php
session_start();
require_once "db.php";

// ADMIN ONLY
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: user_dashboard.php");
    exit();
}

// SAFE OUTPUT
function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// PROCESS ISSUE PERMIT
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "issue_permit") {
        $application_id = (int)($_POST["application_id"] ?? 0);
        $issue_date =trim($_POST["issue_date"] ?? "");
        $expiry_date = trim($_POST["expiry_date"] ?? "");

        // ---------------------------------------------
        // BASIC VALIDATION
        // ---------------------------------------------
        if ($application_id <= 0) {
            $_SESSION["permit_message"] = "Invalid application.";
            $_SESSION["permit_message_type"] = "error";
        } elseif (
            $issue_date === "" ||
            $expiry_date === ""
        ) {
            $_SESSION["permit_message"] = "Please enter the issue date and expiry date.";
            $_SESSION["permit_message_type"] = "error";
        } elseif (
            strtotime($expiry_date) <= strtotime($issue_date)
        ) {
            $_SESSION["permit_message"] = "Expiry date must be after the issue date.";
            $_SESSION["permit_message_type"] = "error";
        } else {

            // =========================================
            // CHECK APPLICATION + PAYMENT
            // =========================================
            $check_sql =
                "SELECT a.application_id, a.status, pay.payment_status
                 FROM permit_applications a
                 INNER JOIN payments pay
                    ON pay.application_id = a.application_id
                 WHERE a.application_id = ?
                 AND a.status = 'Approved'
                 AND pay.payment_status = 'Paid'
                 ORDER BY pay.payment_id DESC
                 LIMIT 1";

            $check_stmt = mysqli_prepare($connect,$check_sql);
            mysqli_stmt_bind_param($check_stmt, "i",$application_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $valid_application = mysqli_fetch_assoc($check_result);

            if (!$valid_application) {
                $_SESSION["permit_message"] = "This application is not ready for permit issuance.";
                $_SESSION["permit_message_type"] = "error";
            } else {
                // =====================================
                // CHECK EXISTING PERMIT
                // =====================================
                $permit_check_sql =
                    "SELECT permit_id
                     FROM parking_permits
                     WHERE application_id = ?
                     LIMIT 1";

                $permit_check_stmt = mysqli_prepare($connect, $permit_check_sql);
                mysqli_stmt_bind_param($permit_check_stmt, "i", $application_id);
                mysqli_stmt_execute($permit_check_stmt);
                $permit_check_result = mysqli_stmt_get_result($permit_check_stmt);
                $existing_permit = mysqli_fetch_assoc($permit_check_result);

                if ($existing_permit) {
                    $_SESSION["permit_message"] = "A parking permit has already been issued for this application.";
                    $_SESSION["permit_message_type"] = "error";
                } else {
                    // =================================
                    // GENERATE PERMIT NUMBER
                    // =================================
                    $permit_number = "UPTM-" . date("Y") ."-" .
                        str_pad($application_id,4,"0",STR_PAD_LEFT);
                    // =================================
                    // INSERT PERMIT
                    // =================================
                    $insert_sql =
                        "INSERT INTO parking_permits
                        (
                            application_id,permit_number,issue_date,expiry_date
                        )
                        VALUES (?, ?, ?, ?)";

                    $insert_stmt =mysqli_prepare($connect,$insert_sql);
                    mysqli_stmt_bind_param($insert_stmt, "isss", $application_id, $permit_number, $issue_date, $expiry_date);
                    if (
                        mysqli_stmt_execute($insert_stmt)
                    ) {
                        $_SESSION["permit_message"] = "Parking permit " . $permit_number ." has been issued successfully.";
                        $_SESSION["permit_message_type"] = "success";

                    } else {
                        $_SESSION["permit_message"] = "Parking permit could not be issued.";
                        $_SESSION["permit_message_type"] = "error";
                    }
                }
            }
        }
        header("Location: manage_permits.php");
        exit();
    }
}

// =====================================================
// MESSAGE
// =====================================================
$message = $_SESSION["permit_message"] ?? "";
$message_type = $_SESSION["permit_message_type"] ?? "";

unset(
    $_SESSION["permit_message"],
    $_SESSION["permit_message_type"]
);

// =====================================================
// COUNT PERMITS
// =====================================================
$count_sql = "SELECT COUNT(*) AS total FROM parking_permits";

$count_result = mysqli_query($connect,$count_sql);
$count_data = mysqli_fetch_assoc($count_result);
$total_permits = (int)($count_data["total"] ?? 0);

// =====================================================
// COUNT READY TO ISSUE
// =====================================================
$ready_count_sql =
    "SELECT COUNT(DISTINCT a.application_id) AS total
     FROM permit_applications a
     INNER JOIN payments pay
        ON pay.application_id = a.application_id
     LEFT JOIN parking_permits p
        ON p.application_id = a.application_id
     WHERE a.status = 'Approved'
     AND pay.payment_status = 'Paid'
     AND p.permit_id IS NULL";

$ready_count_result = mysqli_query($connect, $ready_count_sql);
$ready_count_data = mysqli_fetch_assoc($ready_count_result);
$total_ready =(int)($ready_count_data["total"] ?? 0);

// =====================================================
// GET APPLICATIONS READY FOR PERMIT
// =====================================================
$ready_sql =
    "SELECT DISTINCT
        a.application_id,
        a.application_date,

        u.full_name,
        u.email,
        u.role,

        v.plate_number,
        v.vehicle_type,
        v.vehicle_model,
        v.vehicle_colour,

        pay.payment_amount,
        pay.payment_method,
        pay.payment_reference,
        pay.verified_at

     FROM permit_applications a
     INNER JOIN users u
        ON a.user_id = u.user_id
     INNER JOIN vehicles v
        ON a.vehicle_id = v.vehicle_id
     INNER JOIN payments pay
        ON pay.application_id =
           a.application_id
     LEFT JOIN parking_permits p
        ON p.application_id =
           a.application_id
     WHERE a.status = 'Approved'
     AND pay.payment_status = 'Paid'
     AND p.permit_id IS NULL
     ORDER BY a.application_id DESC";

$ready_result = mysqli_query($connect,$ready_sql);
// =====================================================
// GET ISSUED PERMITS
// =====================================================
$issued_sql =
    "SELECT
        p.permit_id,
        p.permit_number,
        p.issue_date,
        p.expiry_date,

        a.application_id,

        u.full_name,
        u.email,
        u.role,

        v.plate_number,
        v.vehicle_type,
        v.vehicle_model,
        v.vehicle_colour

     FROM parking_permits p
     INNER JOIN permit_applications a
        ON p.application_id =
           a.application_id
     INNER JOIN users u
        ON a.user_id = u.user_id
     INNER JOIN vehicles v
        ON a.vehicle_id = v.vehicle_id
     ORDER BY p.permit_id DESC";

$issued_result = mysqli_query($connect,$issued_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Manage Permits | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css?v=10">
</head>
<body class="admin-page">
<header class="admin-header">
    <a href="admin_dashboard.php" class="admin-brand">
        <img src="images/uptm-logo.png" alt="UPTM Logo"class="admin-logo">
        <div class="admin-brand-text">
            <strong>UPTM Parking Permit</strong>
            <span>Administration Portal</span>
        </div>
    </a>
    <nav class="admin-nav">
        <a href="admin_dashboard.php" class="admin-nav-link">
            Dashboard
        </a>
        <a href="manage_users.php" class="admin-nav-link">
            Manage Users
        </a>
        <a href="manage_applications.php" class="admin-nav-link">
            Applications
        </a>
        <a href="manage_permits.php" class="admin-nav-link active">
            Permits
        </a>
        <a href="logout.php" class="admin-nav-link admin-logout">
            Logout
        </a>
    </nav>
</header>

<!-- =====================================================
     MAIN
===================================================== -->
<main class="admin-main">
    <!-- PAGE HEADING -->
    <section class="manage-page-heading">
        <span class="admin-eyebrow">PERMIT MANAGEMENT</span>
        <h1>Manage Parking Permits</h1>
    </section>
    <?php if ($message !== ""): ?>
        <div
            class="permit-message <?php
            echo $message_type === "success" ? "permit-message-success" : "permit-message-error";
            ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    <!-- =================================================
         SUMMARY
    ================================================== -->
    <section class="permit-summary-grid">
        <div class="permit-summary-card">
            <span>Ready to Issue</span>
            <strong><?php echo $total_ready; ?></strong>
            <p>Approved and paid applications</p>
        </div>
        <div class="permit-summary-card">
            <span>Issued Permits</span>
            <strong><?php echo $total_permits; ?></strong>
            <p>Total parking permits issued</p>
        </div>
    </section>
    <!-- =================================================
         READY TO ISSUE
    ================================================== -->
    <section class="permit-management-section">
        <div class="permit-section-heading">
            <div>
                <h2>Ready to Issue</h2>
            </div>
            <span class="permit-count-badge">
                <?php echo $total_ready; ?>
            </span>
        </div>
        <?php if (mysqli_num_rows($ready_result) > 0): ?>
            <div class="permit-ready-list">
                <?php while ($application = mysqli_fetch_assoc($ready_result)): ?>
                    <details class="permit-ready-item">
                        <summary class="permit-ready-row">
                            <div class="permit-ready-user">
                                <div class="permit-ready-icon">P</div>
                                <div>
                                    <h3><?php echo e($application["full_name"]);?></h3>
                                    <p>
                                        Application #
                                        <?php echo str_pad($application["application_id"],3,"0",STR_PAD_LEFT);?>
                                        &nbsp; | &nbsp;
                                        <?php echo e($application["plate_number"]);?>
                                        &nbsp; | &nbsp;
                                        <?php echo e($application["vehicle_model"]);?>
                                    </p>
                                </div>
                            </div>
                            <div class="permit-ready-status">
                                <span>
                                    &#10003; Payment Paid
                                </span>
                                <strong>
                                    &#9662;
                                </strong>
                            </div>
                        </summary>
                        <!-- =============================
                             DETAILS
                        ============================== -->
                        <div class="permit-ready-details">
                            <!-- USER -->
                            <div class="permit-info-section">
                                <h4>Applicant Details</h4>
                                <div class="permit-info-grid">
                                    <div>
                                        <span>Full Name</span>
                                        <strong>
                                            <?php
                                            echo e($application["full_name"]);?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Email</span>
                                        <strong>
                                            <?php
                                            echo e($application["email"]);?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Role</span>
                                        <strong>
                                            <?php
                                            echo e($application["role"]);?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Application ID</span>
                                        <strong>#
                                            <?php
                                            echo str_pad($application["application_id"],3,"0",STR_PAD_LEFT);
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <!-- VEHICLE -->
                            <div class="permit-info-section">
                                <h4>Vehicle Details</h4>
                                <div class="permit-info-grid">
                                    <div>
                                        <span>Plate Number</span>
                                        <strong>
                                            <?php
                                            echo e($application["plate_number"]);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Vehicle Type</span>
                                        <strong>
                                            <?php
                                            echo e($application["vehicle_type"]);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Vehicle Model</span>
                                        <strong>
                                            <?php
                                            echo e($application["vehicle_model"]);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Vehicle Colour</span>
                                        <strong>
                                            <?php
                                            echo e($application["vehicle_colour"]);
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <!-- PAYMENT -->
                            <div class="permit-info-section">
                                <h4>Payment Details</h4>
                                <div class="permit-info-grid">
                                    <div>
                                        <span>Payment Status</span>
                                        <strong class="permit-paid-text">Paid</strong>
                                    </div>
                                    <div>
                                        <span>Amount</span>
                                        <strong>RM
                                            <?php
                                            echo number_format(
                                                (float)$application["payment_amount"],2);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Payment Method</span>
                                        <strong>
                                            <?php
                                            echo e($application["payment_method"]);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Reference</span>
                                        <strong>
                                            <?php
                                            echo e($application["payment_reference"]);
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <!-- =============================
                                 ISSUE PERMIT
                            ============================= -->
                            <div class="issue-permit-section">
                                <div class="issue-permit-heading">
                                    <div>
                                        <span>ISSUE PERMIT</span>
                                        <h4>Parking Permit Details</h4>
                                    </div>
                                    <div class="auto-permit-number">
                                        Permit Number
                                        <strong>UPTM-<?php
                                            echo date("Y");
                                            ?>-<?php
                                            echo str_pad($application["application_id"],4,"0",STR_PAD_LEFT);
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                                <form method="POST" action="manage_permits.php"class="issue-permit-form">
                                    <input type="hidden" name="application_id" value="<?php
                                        echo $application["application_id"];
                                        ?>"
                                    >
                                    <input type="hidden" name="action" value="issue_permit">
                                    <div class="issue-permit-grid">
                                        <div>
                                            <label>Issue Date</label>
                                            <input type="date" name="issue_date"value="<?php
                                                echo date("Y-m-d");
                                                ?>"
                                                required
                                            >
                                        </div>
                                        <div>
                                            <label>Expiry Date</label>
                                            <input type="date" name="expiry_date" required>
                                        </div>
                                    </div>
                                    <p class="issue-permit-note">
                                        Permit number will be
                                        generated automatically
                                        after issuance.
                                    </p>
                                    <div class="issue-permit-action">
                                        <button type="submit" class="issue-permit-button"
                                            onclick="return confirm('Are you sure you want to issue this parking permit?');">
                                            Issue Parking Permit
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </details>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="permit-empty">
                <div>
                    &#128196;
                </div>
                <h3>No Permits Ready to Issue</h3>
                <p>
                    Applications will appear here
                    after payment has been verified.
                </p>
            </div>
        <?php endif; ?>
    </section>
    <!-- =================================================
         ISSUED PERMITS
    ================================================= -->
    <section class="permit-management-section issued-section">
        <div class="permit-section-heading">
            <div>
                <h2>Issued Parking Permits</h2>
                <p>
                    View all parking permits
                    that have been issued.
                </p>
            </div>
            <span class="permit-count-badge">
                <?php echo $total_permits; ?>
            </span>
        </div>
        <?php if (mysqli_num_rows($issued_result) > 0): ?>
            <div class="issued-permit-list">
                <?php while ($permit = mysqli_fetch_assoc($issued_result)): ?>
                    <div class="issued-permit-card">
                        <div class="issued-permit-top">
                            <div>
                                <span class="issued-permit-label">PARKING PERMIT</span>
                                <h3>
                                    <?php
                                    echo e($permit["permit_number"]);
                                    ?>
                                </h3>
                            </div>
                            <span class="issued-status">
                                &#10003; Issued
                            </span>
                        </div>
                        <div class="issued-permit-grid">
                            <div>
                                <span>Permit Holder</span>
                                <strong>
                                    <?php
                                    echo e($permit["full_name"]);
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>Plate Number</span>
                                <strong>
                                    <?php
                                    echo e($permit["plate_number"]);
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>Vehicle</span>
                                <strong>
                                    <?php
                                    echo e($permit["vehicle_model"]);
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>Issue Date</span>
                                <strong>
                                    <?php
                                    echo date("d M Y",strtotime($permit["issue_date"]));
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>Expiry Date</span>
                                <strong>
                                    <?php
                                    echo date("d M Y", strtotime($permit["expiry_date"]));
                                    ?>
                                </strong>
                            </div>
                            <div>
                                <span>Application</span>
                                <strong>
                                    #
                                    <?php
                                    echo str_pad($permit["application_id"],3,"0",STR_PAD_LEFT);
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="permit-empty">
                <div>
                    &#127991;
                </div>
                <h3>No Permits Issued Yet</h3>
                <p>
                    Issued parking permits
                    will appear here.
                </p>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="admin-footer">
    &copy;
    <?php echo date("Y"); ?>
    UPTM Parking Permit Management System
</footer>
</body>
</html>
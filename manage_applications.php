<?php
session_start();
require_once "db.php";

/* =========================
   ADMIN ONLY
========================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "Admin") {
    header("Location: user_dashboard.php");
    exit();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* =========================
   PROCESS ADMIN ACTION
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $application_id = (int)($_POST["application_id"] ?? 0);
    $action = $_POST["action"] ?? "";
    $admin_remark = trim($_POST["admin_remark"] ?? "");

    if ($application_id <= 0) {
        $_SESSION["admin_message"] = "Invalid application.";
        header("Location: manage_applications.php");
        exit();
    }
    /* =========================
       APPROVE APPLICATION
    ========================= */
    if ($action === "approve") {
        if ($admin_remark === "") {
            $admin_remark = "Application approved.";
        }

        $status = "Approved";
        $sql = "UPDATE permit_applications
                SET status = ?, admin_remark = ?
                WHERE application_id = ?
                AND status = 'Pending'";

        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $status, $admin_remark, $application_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION["admin_message"] = "Application #" . str_pad($application_id, 3, "0", STR_PAD_LEFT) . " has been approved.";
        } else {
            $_SESSION["admin_message"] = "Application could not be updated.";
        }
    }

    /* =========================
       REJECT APPLICATION
    ========================= */
    elseif ($action === "reject") {
        if ($admin_remark === "") {
            $_SESSION["admin_message"] = "Please enter a remark before rejecting the application.";
        } else {
            $status = "Rejected";
            $sql = "UPDATE permit_applications
                    SET status = ?, admin_remark = ?
                    WHERE application_id = ?
                    AND status = 'Pending'";
            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $status, $admin_remark, $application_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION["admin_message"] = "Application #" . str_pad($application_id, 3, "0", STR_PAD_LEFT) . " has been rejected.";
            } else {
                $_SESSION["admin_message"] = "Application could not be updated.";
            }
        }
    }

    /* =========================
       VERIFY PAYMENT
    ========================= */
    elseif ($action === "verify_payment") {
        $payment_id = (int)($_POST["payment_id"] ?? 0);
        if ($payment_id <= 0) {
            $_SESSION["admin_message"] ="Invalid payment.";
        } else {
            $sql = "UPDATE payments
                    SET payment_status = 'Paid', admin_remark = ?, verified_at = CURRENT_TIMESTAMP
                    WHERE payment_id = ?
                    AND application_id = ?
                    AND payment_status = 'Pending Verification'";
            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt, "sii", $admin_remark, $payment_id, $application_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION["admin_message"] = "Payment has been verified successfully.";
            } else {
                $_SESSION["admin_message"] = "Payment could not be verified.";
            }
        }
    }

    /* =========================
       REJECT PAYMENT
    ========================= */
    elseif ($action === "reject_payment") {
        $payment_id = (int)($_POST["payment_id"] ?? 0);
        if ($payment_id <= 0) {
            $_SESSION["admin_message"] = "Invalid payment.";
        } elseif ($admin_remark === "") {
            $_SESSION["admin_message"] = "Please enter a remark before rejecting the payment.";
        } else {
            $sql = "UPDATE payments
                    SET payment_status = 'Rejected', admin_remark = ?, verified_at = NULL
                    WHERE payment_id = ?
                    AND application_id = ?
                    AND payment_status = 'Pending Verification'";

            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt, "sii", $admin_remark, $payment_id, $application_id);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION["admin_message"] = "Payment has been rejected.";
            } else {
                $_SESSION["admin_message"] ="Payment could not be updated.";
            }
        }
    }
    header("Location: manage_applications.php");
    exit();
}

/* =========================
   MESSAGE
========================= */
$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);

/* =========================
   STATUS FILTER
========================= */
$status_filter = $_GET["status"] ?? "";
$allowed_statuses = ["Pending", "Approved","Rejected"];

if ($status_filter !== "" && !in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "";
}

/* =========================
   COUNT APPLICATIONS
========================= */
$count_sql = "SELECT COUNT(*) AS total, SUM(status = 'Pending') AS pending, SUM(status = 'Approved') AS approved, SUM(status = 'Rejected') AS rejected
              FROM permit_applications";

$count_result = mysqli_query($connect, $count_sql);
$count_data = mysqli_fetch_assoc($count_result);
$total_applications = (int)($count_data["total"] ?? 0);
$total_pending = (int)($count_data["pending"] ?? 0);
$total_approved = (int)($count_data["approved"] ?? 0);
$total_rejected = (int)($count_data["rejected"] ?? 0);

/* =========================
   GET APPLICATIONS
========================= */
$sql = "SELECT
            a.application_id, a.application_date, a.status,a.admin_remark,
            u.full_name, u.email, u.role,
            v.plate_number, v.vehicle_type, v.vehicle_model, v.vehicle_colour,
            pay.payment_id,
            pay.payment_amount,
            pay.payment_method,
            pay.payment_reference,
            pay.payment_proof,
            pay.payment_date,
            pay.payment_status,
            pay.admin_remark AS payment_admin_remark,
            pay.verified_at

        FROM permit_applications a
        INNER JOIN users u
            ON a.user_id = u.user_id
        INNER JOIN vehicles v
            ON a.vehicle_id = v.vehicle_id
        LEFT JOIN payments pay
            ON pay.payment_id = (
                SELECT p2.payment_id
                FROM payments p2
                WHERE p2.application_id = a.application_id
                ORDER BY p2.payment_id DESC
                LIMIT 1)";

if ($status_filter !== "") {
    $sql .= " WHERE a.status = ? ORDER BY a.application_id DESC";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "s",$status_filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql .= " ORDER BY a.application_id DESC";
    $result = mysqli_query($connect, $sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css?v=9">
</head>
<body class="admin-page">

<!-- =========================
     HEADER
========================= -->
<header class="admin-header">
    <a href="admin_dashboard.php" class="admin-brand">
        <img src="images/uptm-logo.png" alt="UPTM Logo" class="admin-logo">
        <div class="admin-brand-text">
            <strong>UPTM Parking Permit</strong>
            <span>Administration Portal</span>
        </div>
    </a>
    <nav class="admin-nav">
        <a href="admin_dashboard.php" class="admin-nav-link">Dashboard</a>
        <a href="manage_users.php" class="admin-nav-link">Manage Users</a>
        <a href="manage_applications.php" class="admin-nav-link active">Applications</a>
        <a href="manage_permits.php" class="admin-nav-link">Permits</a>
        <a href="logout.php"class="admin-nav-link admin-logout">Logout</a>
    </nav>
</header>

<!-- =========================
     MAIN
========================= -->
<main class="admin-main">
    <!-- PAGE TITLE -->
    <section class="manage-page-heading">
        <span class="admin-eyebrow">
            APPLICATION MANAGEMENT
        </span>
        <h1>Manage Applications</h1>
        <p>Review applications and verify parking permit payments.</p>
    </section>
    <!-- MESSAGE -->
    <?php if ($message !== ""): ?>
        <div class="admin-message">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <!-- =========================
         FILTER
    ========================= -->
    <section class="application-filter-tabs">
        <a href="manage_applications.php" class="<?php
            echo $status_filter === "" ? "filter-tab active" : "filter-tab";
            ?>"
        >
            All
            <span>
                <?php echo $total_applications; ?>
            </span>
        </a>

        <a href="manage_applications.php?status=Pending" class="<?php
            echo $status_filter === "Pending" ? "filter-tab active": "filter-tab";
            ?>"
        >
            Pending
            <span>
                <?php echo $total_pending; ?>
            </span>
        </a>

        <a href="manage_applications.php?status=Approved" class="<?php
            echo $status_filter === "Approved" ? "filter-tab active" : "filter-tab";
            ?>"
        >
            Approved
            <span>
                <?php echo $total_approved; ?>
            </span>
        </a>

        <a href="manage_applications.php?status=Rejected" class="<?php
            echo $status_filter === "Rejected" ? "filter-tab active" : "filter-tab";
            ?>"
        >
            Rejected
            <span>
                <?php echo $total_rejected; ?>
            </span>
        </a>
    </section>

    <!-- =========================
         APPLICATION LIST
    ========================= -->
    <section class="application-list-container">
        <div class="application-list-header">
            <div>
                <h2>Parking Permit Applications</h2>
                <p>Click an application to view details.</p>
            </div>
        </div>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($application = mysqli_fetch_assoc($result)): ?>
                <details class="application-item">
                    <!-- APPLICATION ROW -->
                    <summary class="application-row">
                        <div class="application-row-left">
                            <div class="application-icon">P</div>
                            <div>
                                <h3><?php echo e($application["full_name"]);?></h3>
                                <p>
                                    Application #
                                    <?php
                                    echo str_pad($application["application_id"],3,"0",STR_PAD_LEFT);
                                    ?>
                                    &nbsp; | &nbsp;

                                    <?php
                                    echo e($application["plate_number"]);
                                    ?>
                                    &nbsp; | &nbsp;

                                    <?php
                                    echo e($application["vehicle_model"]);
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="application-row-right">
                            <?php
                            $display_status = $application["status"];
                            $display_class = strtolower($application["status"]);

                            if ($application["status"] === "Approved") {
                                if (empty($application["payment_id"])) {
                                    $display_status = "Awaiting Payment";
                                    $display_class = "awaiting-payment";

                                } elseif ($application["payment_status"] === "Pending Verification") {
                                    $display_status = "Verify Payment";
                                    $display_class = "verify-payment";

                                } elseif ($application["payment_status"] === "Rejected") {
                                    $display_status = "Payment Rejected";
                                    $display_class = "payment-rejected";

                                } elseif ($application["payment_status"] === "Paid") {
                                    $display_status = "Payment Verified";
                                    $display_class = "payment-verified";
                                }
                            }
                            ?>

                            <span class="status-badge status-<?php echo $display_class; ?>">
                                <?php if (
                                    $display_status === "Approved" ||
                                    $display_status === "Payment Verified"
                                ): ?>
                                    &#10003;
                                <?php endif; ?>
                                <?php echo e($display_status); ?>
                            </span>
                            <span class="application-arrow">
                                &#9662;
                            </span>
                        </div>
                    </summary>
                    <!-- =========================
                         DETAILS
                    ========================= -->
                    <div class="application-details">
                        <!-- APPLICANT -->
                        <div class="application-detail-section">
                            <h4>Applicant Details</h4>
                            <div class="application-detail-grid">
                                <div>
                                    <span>Full Name</span>
                                    <strong>
                                        <?php
                                        echo e($application["full_name"]);
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>Email</span>
                                    <strong>
                                        <?php
                                        echo e($application["email"]);
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>User Type</span>
                                    <strong>
                                        <?php
                                        echo e($application["role"]);
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>Application Date</span>
                                    <strong>
                                        <?php
                                        echo date("d M Y", strtotime($application["application_date"]));
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- VEHICLE -->
                        <div class="application-detail-section">
                            <h4>Vehicle Details</h4>
                            <div class="application-detail-grid">
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
                        <!-- =========================
                             PENDING APPLICATION
                        ========================= -->
                        <?php if ($application["status"] === "Pending"): ?>
                            <div class="application-review-section">
                                <h4>Review Application</h4>
                                <form method="POST" action="manage_applications.php">
                                    <input type="hidden" name="application_id"value="<?php
                                        echo $application["application_id"];
                                        ?>"
                                    >
                                    <label>Admin Remark</label>
                                    <textarea name="admin_remark" rows="3" placeholder="Enter remark here..."></textarea>

                                    <p class="remark-note">
                                        Remark is optional for approval,
                                        but required when rejecting an application.
                                    </p>

                                    <div class="application-action-buttons">
                                        <button type="submit" name="action" value="reject" class="reject-button" onclick="return confirm('Are you sure you want to reject this application?');">
                                            Reject
                                        </button>

                                        <button type="submit" name="action" value="approve" class="approve-button" onclick="return confirm('Are you sure you want to approve this application?');">
                                            Approve
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <!-- =========================
                             APPROVED
                        ======================== -->
                        <?php elseif ($application["status"] === "Approved"): ?>
                            <div class="review-result">
                                <div>
                                    <span>Application Status</span>
                                    <strong class="result-approved">Approved</strong>
                                </div>
                                <div>
                                    <span>Admin Remark</span>
                                    <strong>
                                        <?php
                                        echo !empty($application["admin_remark"]) ? e($application["admin_remark"]): "-";
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <!-- =========================
                                 NO PAYMENT
                            ======================== -->
                            <?php if (empty($application["payment_id"])): ?>
                                <div class="admin-payment-box">
                                    <div class="admin-payment-title">
                                        <div>
                                            <span>PAYMENT</span>
                                            <h4>Waiting for Payment</h4>
                                        </div>
                                        <span class="admin-payment-waiting">Not Submitted</span>
                                    </div>

                                    <p class="admin-payment-note">
                                        The application has been approved.
                                        Waiting for the user to submit
                                        the RM10.00 parking permit payment.
                                    </p>
                                </div>
                            <!-- =========================
                                 PAYMENT PENDING
                            ========================= -->
                            <?php elseif ($application["payment_status"]=== "Pending Verification"): ?>
                                <div class="admin-payment-box">
                                    <div class="admin-payment-title">
                                        <div>
                                            <span>PAYMENT</span>
                                            <h4>Payment Verification</h4>
                                        </div>
                                        <span class="admin-payment-pending">
                                            Pending Verification
                                        </span>
                                    </div>

                                    <div class="admin-payment-grid">
                                        <div>
                                            <span>Amount</span>
                                            <strong>RM
                                                <?php
                                                echo number_format((float)$application["payment_amount"],2);
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

                                        <div>
                                            <span>Payment Date</span>
                                            <strong>
                                                <?php
                                                echo date("d M Y",strtotime($application["payment_date"]));
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                    <!-- PAYMENT PROOF -->
                                    <div class="payment-proof-row">
                                        <div>
                                            <span class="payment-proof-label">
                                                PAYMENT PROOF
                                            </span>
                                            <p>
                                                Open the uploaded receipt
                                                before verifying the payment.
                                            </p>
                                        </div>
                                        <a href="<?php
                                            echo e($application["payment_proof"]);
                                            ?>"
                                            target="_blank"
                                            class="view-payment-proof"
                                        >
                                            View Payment Proof
                                        </a>
                                    </div>
                                    <!-- VERIFY FORM -->
                                    <form method="POST" action="manage_applications.php"class="payment-review-form">
                                        <input type="hidden" name="application_id"value="<?php
                                            echo $application["application_id"];
                                            ?>"
                                        >

                                        <input type="hidden" name="payment_id"value="<?php
                                            echo $application["payment_id"];
                                            ?>"
                                        >

                                        <label>Payment Remark</label>
                                        <textarea name="admin_remark" rows="3" placeholder="Enter payment remark..."></textarea>

                                        <p class="remark-note">
                                            Remark is optional when verifying,
                                            but required when rejecting payment.
                                        </p>
                                        <div class="application-action-buttons">
                                            <button type="submit" name="action" value="reject_payment" class="reject-button"
                                                onclick="return confirm('Are you sure you want to reject this payment?');"
                                            >
                                                Reject Payment
                                            </button>

                                            <button type="submit" name="action" value="verify_payment" class="approve-button"
                                                onclick="return confirm('Are you sure you want to verify this payment?');"
                                            >
                                                Verify Payment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <!-- =========================
                                 PAID
                            ========================= -->
                            <?php elseif ($application["payment_status"] === "Paid"): ?>
                                <div class="admin-payment-box">
                                    <div class="admin-payment-title">
                                        <div>
                                            <span>PAYMENT</span>
                                            <h4>Payment Details</h4>
                                        </div>
                                        <span class="admin-payment-paid">
                                            &#10003; Paid
                                        </span>
                                    </div>

                                    <div class="admin-payment-grid">
                                        <div>
                                            <span>Amount</span>
                                            <strong>RM
                                                <?php
                                                echo number_format((float)$application["payment_amount"],2);
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
                                        <div>
                                            <span>Verified Date</span>
                                            <strong>
                                                <?php
                                                if (!empty($application["verified_at"])) {
                                                    echo date("d M Y",strtotime($application["verified_at"]));
                                                } else {
                                                    echo "-";
                                                }
                                                ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <?php if (!empty($application["payment_admin_remark"])): ?>
                                        <div class="payment-admin-remark">
                                            <strong>Payment Remark</strong>
                                            <p>
                                                <?php
                                                echo e($application["payment_admin_remark"]);
                                                ?>
                                            </p>
                                        </div>

                                    <?php endif; ?>
                                    <div class="payment-verified-note">
                                        &#10003;
                                        Payment verified.
                                        This application is now ready
                                        for parking permit issuance.
                                    </div>
                                </div>
                            <!-- =========================
                                 PAYMENT REJECTED
                            ========================= -->
                            <?php elseif ($application["payment_status"]=== "Rejected"): ?>
                                <div class="admin-payment-box">
                                    <div class="admin-payment-title">
                                        <div>
                                            <span>PAYMENT</span>
                                            <h4>Payment Details</h4>
                                        </div>

                                        <span class="admin-payment-rejected">
                                            Rejected
                                        </span>
                                    </div>
                                    <div class="admin-payment-grid">
                                        <div>
                                            <span>Amount</span>
                                            <strong>RM
                                                <?php
                                                echo number_format((float)$application["payment_amount"],2);
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
                                    <div class="payment-admin-remark rejected">
                                        <strong>Admin Remark</strong>
                                        <p>
                                            <?php
                                            echo !empty($application["payment_admin_remark"])? e($application["payment_admin_remark"]): "-";
                                            ?>
                                        </p>
                                    </div>
                                    <p class="admin-payment-note">
                                        Waiting for the user to
                                        submit a new payment proof.
                                    </p>
                                </div>
                            <?php endif; ?>
                        <!-- =========================
                             REJECTED APPLICATION
                        ========================= -->
                        <?php else: ?>
                            <div class="review-result">
                                <div>
                                    <span>Application Status</span>
                                    <strong class="result-rejected">Rejected</strong>
                                </div>
                                <div>
                                    <span>Admin Remark</span>
                                    <strong>
                                        <?php
                                        echo !empty($application["admin_remark"])? e($application["admin_remark"]): "-";
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-application">
                <div class="no-application-icon">
                    &#128196;
                </div>
                <h3>No Applications Found</h3>
                <p>
                    There are currently no applications
                    under this status.
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
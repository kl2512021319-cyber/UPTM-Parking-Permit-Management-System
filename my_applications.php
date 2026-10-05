<?php
session_start();
require_once "db.php";

// STUDENT / STAFF ONLY
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!in_array($_SESSION["role"] ?? "",["Student", "Staff"],true)) {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
// SAFE OUTPUT
function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// PAYMENT SETTINGS
$permit_fee = 10.00;
// PROCESS PAYMENT SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $application_id = (int) ($_POST["application_id"] ?? 0);
    $payment_method = trim($_POST["payment_method"] ?? "");
    $payment_reference = trim($_POST["payment_reference"] ?? "");
    $allowed_methods = ["Bank Transfer","Cash Deposit"];

    // Check application belongs to user
    $check_sql =
        "SELECT application_id, status
         FROM permit_applications
         WHERE application_id = ?
         AND user_id = ?
         LIMIT 1";

    $check_stmt = mysqli_prepare($connect, $check_sql);
    mysqli_stmt_bind_param($check_stmt,"ii",$application_id,$user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $application_check = mysqli_fetch_assoc($check_result);

    if (!$application_check) {
        $_SESSION["payment_message"] = "Invalid application.";
        $_SESSION["payment_message_type"] = "error";
    } elseif (
        $application_check["status"] !== "Approved"
    ) {
        $_SESSION["payment_message"] = "Payment is only available for approved applications.";
        $_SESSION["payment_message_type"] = "error";

    } elseif (!in_array($payment_method,$allowed_methods,true)
    ) {
        $_SESSION["payment_message"] = "Please select a valid payment method.";
        $_SESSION["payment_message_type"] = "error";

    } elseif ($payment_reference === "") {
        $_SESSION["payment_message"] = "Please enter your payment reference.";
        $_SESSION["payment_message_type"] = "error";

    } elseif (
        !isset($_FILES["payment_proof"]) || $_FILES["payment_proof"]["error"] !== UPLOAD_ERR_OK
    ) {
        $_SESSION["payment_message"] = "Please upload your payment proof.";
        $_SESSION["payment_message_type"] = "error";

    } else {
       // CHECK EXISTING PAYMENT
        $payment_check_sql =
            "SELECT payment_id, payment_status, payment_proof
             FROM payments
             WHERE application_id = ?
             ORDER BY payment_id DESC
             LIMIT 1";

        $payment_check_stmt = mysqli_prepare($connect, $payment_check_sql);
        mysqli_stmt_bind_param($payment_check_stmt,"i",$application_id);
        mysqli_stmt_execute($payment_check_stmt);
        $payment_check_result = mysqli_stmt_get_result($payment_check_stmt);
        $existing_payment = mysqli_fetch_assoc($payment_check_result);

        // Cannot upload again if waiting / paid
        if ($existing_payment && in_array($existing_payment["payment_status"],["Pending Verification", "Paid"],true)) {
            $_SESSION["payment_message"] = "A payment has already been submitted for this application.";
            $_SESSION["payment_message_type"] = "error";

        } else {
            // VALIDATE FILE
            $file = $_FILES["payment_proof"];
            $original_name = $file["name"];
            $temporary_name = $file["tmp_name"];
            $file_size =$file["size"];

            // Maximum 5MB
            $maximum_size = 5 * 1024 * 1024;
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_extensions = ["jpg","jpeg","png","pdf"];

            if (!in_array($extension, $allowed_extensions, true)) {
                $_SESSION["payment_message"] = "Payment proof must be JPG, JPEG, PNG or PDF.";
                $_SESSION["payment_message_type"] = "error";

            } elseif ($file_size > $maximum_size) {
                $_SESSION["payment_message"] = "Payment proof must not exceed 5MB.";
                $_SESSION["payment_message_type"] = "error";

            } else {
                // CREATE UPLOAD FOLDER
                $upload_directory = __DIR__ . "/uploads/payment_proofs/";

                if (!is_dir($upload_directory)) {
                    mkdir($upload_directory, 0777, true);
                }

                // UNIQUE FILE NAME
                $new_file_name = "payment_" . $application_id . "_" . time() . "." . $extension;
                $file_destination = $upload_directory . $new_file_name;
                $database_file_path = "uploads/payment_proofs/" . $new_file_name;

                // SAVE FILE
                if (move_uploaded_file($temporary_name, $file_destination)) {

                    // NEW PAYMENT
                    if (!$existing_payment) {
                        $insert_sql =
                            "INSERT INTO payments(application_id, payment_amount, payment_method, payment_reference, payment_proof, payment_status)
                            VALUES (?, ?, ?, ?, ?, 'Pending Verification')";

                        $insert_stmt = mysqli_prepare($connect,$insert_sql);
                        mysqli_stmt_bind_param($insert_stmt,"idsss",$application_id,$permit_fee,$payment_method,$payment_reference,$database_file_path);

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $_SESSION["payment_message"] = "Payment proof submitted successfully.";
                            $_SESSION["payment_message_type"] = "success";

                        } else {
                            // Delete uploaded file if database fails
                            if (file_exists($file_destination)) {
                                unlink($file_destination);
                            }
                            $_SESSION["payment_message"] = "Payment could not be submitted.";
                            $_SESSION["payment_message_type"] = "error";
                        }

                    // RE-UPLOAD REJECTED PAYMENT
                    } else {
                        $payment_id = (int) $existing_payment["payment_id"];
                        $old_payment_proof =$existing_payment["payment_proof"] ?? "";
                        $update_sql =
                            "UPDATE payments
                             SET payment_amount = ?,
                                 payment_method = ?,
                                 payment_reference = ?,
                                 payment_proof = ?,
                                 payment_date = CURRENT_TIMESTAMP,
                                 payment_status = 'Pending Verification',
                                 admin_remark = NULL,
                                 verified_at = NULL
                             WHERE payment_id = ?";

                        $update_stmt = mysqli_prepare($connect, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "dsssi", $permit_fee,$payment_method, $payment_reference, $database_file_path, $payment_id);

                        if (mysqli_stmt_execute($update_stmt)) {
                            // Delete old rejected payment proof
                            if ($old_payment_proof !== "") {
                                $old_file = __DIR__ . "/" . $old_payment_proof;
                                if (file_exists($old_file) && is_file($old_file)) {
                                    unlink($old_file);
                                }
                            }

                            $_SESSION["payment_message"] = "New payment proof submitted successfully.";
                            $_SESSION["payment_message_type"] = "success";

                        } else {

                            if (file_exists($file_destination)) {unlink($file_destination);}
                            $_SESSION["payment_message"] = "Payment could not be updated.";
                            $_SESSION["payment_message_type"] = "error";
                        }
                    }
                } else {
                    $_SESSION["payment_message"] = "Payment proof could not be uploaded.";
                    $_SESSION["payment_message_type"] = "error";
                }
            }
        }
    }
    // Prevent form resubmission
    header("Location: my_applications.php");
    exit();
}

// GET MESSAGE
$payment_message = $_SESSION["payment_message"] ?? "";
$payment_message_type = $_SESSION["payment_message_type"] ?? "";

unset($_SESSION["payment_message"], $_SESSION["payment_message_type"]);

// GET USER APPLICATIONS
$sql =
    "SELECT
        a.application_id,
        a.application_date,
        a.status,
        a.admin_remark,

        v.plate_number,
        v.vehicle_type,
        v.vehicle_model,
        v.vehicle_colour,

        pay.payment_id,
        pay.payment_amount,
        pay.payment_method,
        pay.payment_reference,
        pay.payment_proof,
        pay.payment_date,
        pay.payment_status,
        pay.admin_remark AS payment_admin_remark,
        pay.verified_at,

        p.permit_number,
        p.issue_date,
        p.expiry_date

     FROM permit_applications a
     INNER JOIN vehicles v
        ON a.vehicle_id = v.vehicle_id

     LEFT JOIN payments pay
        ON pay.payment_id = (
            SELECT p2.payment_id
            FROM payments p2
            WHERE p2.application_id = a.application_id
            ORDER BY p2.payment_id DESC
            LIMIT 1
        )

     LEFT JOIN parking_permits p
        ON p.application_id = a.application_id
     WHERE a.user_id = ?
     ORDER BY a.application_id DESC";

$stmt = mysqli_prepare($connect,$sql);
mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$applications = [];

while ($row = mysqli_fetch_assoc($result)) {
    $applications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body class="dashboard-page applications-page">
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
        <a href="apply_permit.php" class="nav-link">Apply Permit</a>
        <a href="my_applications.php" class="nav-link active">My Applications</a>
        <a href="logout.php" class="nav-link nav-logout">Logout</a>
    </nav>
</header>

<main class="dashboard-main">
    <section class="applications-heading">
        <div>
            <span class="section-eyebrow">YOUR PARKING PERMIT</span>
            <h1>My Applications</h1>
            <p>
                Track your applications, payment
                and parking permit details in one place.
            </p>
        </div>
        <a href="apply_permit.php" class="applications-new-button">+ New Application</a>
    </section>

    <?php if ($payment_message !== ""): ?>
        <div class="payment-message<?php
            echo $payment_message_type === "success"? "payment-message-success": "payment-message-error";
            ?>">
            <?php echo e($payment_message); ?>
        </div>
    <?php endif; ?>

    <?php if (count($applications) === 0): ?>
        <section class="applications-empty">
            <div class="applications-empty-icon">
                &#128663;
            </div>
            <h2>No applications yet</h2>
            <p>
                You have not submitted a parking permit
                application. Apply now to get started.
            </p>
            <a href="apply_permit.php" class="applications-new-button">Apply Parking Permit &rarr;</a>
        </section>
    <?php else: ?>
        <div class="applications-list">
        <?php foreach ($applications as $application): ?>
            <article class="application-card">
                <div class="application-card-header">
                    <div>
                        <span class="application-id">
                            Application #
                            <?php
                            echo str_pad((string) $application["application_id"],3,"0",STR_PAD_LEFT);
                            ?>
                        </span>
                        <h2>
                            <?php
                            echo e($application["plate_number"]);
                            ?>
                            <span>&bull;</span>
                            <?php
                            echo e($application["vehicle_model"]);
                            ?>
                        </h2>
                    </div>
                    <span class="status-badge status-<?php
                        echo e(strtolower($application["status"]));
                        ?>">

                        <?php
                        echo e($application["status"]);
                        ?>
                    </span>
                </div>

                <div class="application-details-grid">
                    <div class="application-detail">
                        <span>Vehicle Type</span>
                        <strong>
                            <?php
                            echo e($application["vehicle_type"]);
                            ?>
                        </strong>
                    </div>
                    <div class="application-detail">
                        <span>Vehicle Colour</span>
                        <strong>
                            <?php
                            echo e($application["vehicle_colour"]);
                            ?>
                        </strong>
                    </div>
                    <div class="application-detail">
                        <span>Application Date</span>
                        <strong>
                            <?php
                            echo date("d M Y",strtotime($application["application_date"]));
                            ?>
                        </strong>
                    </div>
                </div>
                <?php if (!empty($application["admin_remark"])): ?>
                    <div class="application-remark">
                        <strong>Admin Remark</strong>
                        <p>
                            <?php
                            echo e($application["admin_remark"]);
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if ($application["status"] === "Pending"): ?>
                    <div class="application-status-note pending-note">
                        Your application has been
                        submitted successfully and
                        is waiting for Admin review.
                    </div>
                <?php elseif ($application["status"] === "Rejected"): ?>
                    <div class="application-status-note rejected-note">
                        Your application was rejected.
                        Please refer to the Admin remark
                        for more information.
                    </div>
                <?php elseif ($application["status"] === "Approved"): ?>
                    <div class="application-status-note approved-note">
                        Your parking permit application
                        has been approved.
                    </div>

                    <?php if (empty($application["payment_id"])): ?>
                        <section class="payment-section">
                            <div class="payment-heading">
                                <div>
                                    <span class="payment-label">PAYMENT REQUIRED</span>
                                    <h3>Parking Permit Payment</h3>
                                </div>
                                <div class="payment-amount">
                                    <span>Permit Fee</span>
                                    <strong>RM
                                        <?php
                                        echo number_format($permit_fee,2);
                                        ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="payment-instruction">
                                <strong>Payment Instructions</strong>
                                <p>
                                    Please make a payment
                                    of RM10.00 using Bank
                                    Transfer or Cash Deposit.
                                    After payment, enter the
                                    payment reference and
                                    upload your payment proof.
                                </p>
                            </div>

                            <form method="POST" enctype="multipart/form-data"class="payment-form">
                                <input type="hidden" name="application_id" value="<?php
                                    echo e($application["application_id"]);
                                    ?>"
                                >
                                <div class="payment-form-grid">
                                    <div class="payment-form-group">
                                        <label>Payment Method</label>
                                        <select name="payment_method" required>
                                            <option value=""> Select Payment Method</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Cash Deposit">Cash Deposit</option>
                                        </select>
                                    </div>
                                    <div class="payment-form-group">
                                        <label>Payment Reference</label>
                                        <input type="text" name="payment_reference" placeholder="Example: TXN123456" maxlength="100" required>
                                    </div>
                                </div>

                                <div class="payment-form-group">
                                    <label>Payment Proof</label>
                                    <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small>
                                        JPG, JPEG, PNG or PDF.
                                        Maximum file size: 5MB.
                                    </small>
                                </div>
                                <button type="submit" class="submit-payment-button">Submit Payment Proof</button>
                            </form>
                        </section>

                    <?php elseif ($application["payment_status"] === "Pending Verification"): ?>
                        <section class="payment-section">
                            <div class="payment-heading">
                                <div>
                                    <span class="payment-label">PAYMENT SUBMITTED</span>
                                    <h3>Payment Details</h3>
                                </div>
                                <span class="payment-status-badge payment-pending">
                                    Pending Verification
                                </span>
                            </div>
                            <div class="payment-details-grid">
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
                            <div class="payment-waiting-note">
                                Your payment proof has been
                                submitted and is waiting for
                                Admin verification.
                            </div>
                        </section>
                
                    <?php elseif ($application["payment_status"]=== "Rejected"): ?>
                        <section class="payment-section">
                            <div class="payment-heading">
                                <div>
                                    <span class="payment-label">PAYMENT REVIEW</span>
                                    <h3>Payment Details</h3>
                                </div>
                                <span class="payment-status-badge payment-rejected">Rejected</span>
                            </div>
                            <?php if (!empty($application["payment_admin_remark"])): ?>
                                <div class="payment-rejected-note">
                                    <strong>Admin Remark</strong>
                                    <p>
                                        <?php
                                        echo e($application["payment_admin_remark"]);
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <h4 class="payment-resubmit-title">Submit New Payment Proof</h4>

                            <form method="POST" enctype="multipart/form-data" class="payment-form">
                                <input type="hidden" name="application_id" value="<?php
                                    echo e($application["application_id"]);
                                    ?>"
                                >
                                <div class="payment-form-grid">
                                    <div class="payment-form-group">
                                        <label>Payment Method</label>
                                        <select name="payment_method" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Cash Deposit">Cash Deposit</option>
                                        </select>
                                    </div>
                                    <div class="payment-form-group">
                                        <label>Payment Reference</label>
                                        <input type="text" name="payment_reference" placeholder="Enter new payment reference"
                                            maxlength="100"
                                            required
                                        >
                                    </div>
                                </div>
                                <div class="payment-form-group">
                                    <label>New Payment Proof</label>
                                    <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small>
                                        JPG, JPEG, PNG or PDF.
                                        Maximum file size: 5MB.
                                    </small>
                                </div>
                                <button type="submit" class="submit-payment-button">Submit New Payment Proof</button>
                            </form>
                        </section>

                    <?php elseif ($application["payment_status"]=== "Paid"): ?>
                        <section class="payment-section">
                            <div class="payment-heading">
                                <div>
                                    <span class="payment-label">PAYMENT COMPLETED</span>
                                    <h3>Payment Details</h3>
                                </div>
                                <span class="payment-status-badge payment-paid">
                                    &#10003; Paid
                                </span>
                            </div>

                            <div class="payment-details-grid">
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
                            <div class="payment-success-note">
                                &#10003;
                                Your payment has been
                                successfully verified.
                                <?php if (empty($application["permit_number"])): ?>
                                    Your parking permit is
                                    waiting to be issued.
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                    <!-- =================================
                         PARKING PERMIT / STICKER
                    ================================== -->
                    <?php if (!empty($application["permit_number"])): ?>
                        <section class="permit-details">
                            <div class="permit-details-heading">
                                <div>
                                    <span class="permit-label">PARKING PERMIT ISSUED</span>
                                    <h3>Parking Sticker</h3>
                                </div>
                                <span class="permit-issued-badge">&#10003; Issued</span>
                            </div>
                            <div class="parking-sticker" id="sticker-<?php
                                echo (int)$application["application_id"];
                                ?>"
                            >
                                <div class="sticker-header">
                                    <img src="images/uptm-logo.png" alt="UPTM Logo">
                                    <div>
                                        <strong>PARKING PERMIT</strong>
                                        <span>UNIVERSITI POLY-TECH MALAYSIA</span>
                                    </div>
                                </div>
                                <div class="sticker-line"></div>
                                <div class="sticker-vehicle">
                                    <span>VEHICLE REGISTRATION</span>
                                    <strong>
                                        <?php
                                        echo e($application["plate_number"]);
                                        ?>
                                    </strong>
                                </div>
                                <div class="sticker-information">
                                    <div>
                                        <span>Permit No.</span>
                                        <strong>
                                            <?php
                                            echo e($application["permit_number"]);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Vehicle</span>
                                        <strong>
                                            <?php
                                            echo e($application["vehicle_model"]);
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Issue Date</span>
                                        <strong>
                                            <?php
                                            echo date("d M Y",strtotime($application["issue_date"]));
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span>Valid Until</span>
                                        <strong>
                                            <?php
                                            echo date("d M Y",strtotime($application["expiry_date"]));
                                            ?>
                                        </strong>
                                    </div>
                                </div>

                                <div class="sticker-footer">
                                    <span>UPTM PARKING</span>
                                    <strong>
                                        <?php
                                        echo date("Y",strtotime($application["issue_date"]));
                                        ?>
                                        /
                                        <?php
                                        echo date("Y",strtotime($application["expiry_date"]));
                                        ?>
                                    </strong>
                                </div>
                            </div>

                            <!-- PRINT -->
                            <div class="permit-print-area">
                                <p>
                                    Your parking permit is ready.
                                    Print and display this sticker
                                    on your vehicle.
                                </p>

                                <button type="button" class="print-sticker-button"
                                    onclick="printSticker('sticker-<?php echo (int)$application['application_id']; ?>')">
                                    Print Parking Sticker
                                </button>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="dashboard-footer">
    &copy;
    <?php echo date("Y"); ?>
    UPTM Parking Permit Management System
</footer>

<!-- =====================================================
     PRINT STICKER
===================================================== -->
<script>
function printSticker(stickerId)
{
    const sticker = document.getElementById(stickerId);
    if (!sticker) {
        alert("Parking sticker not found.");
        return;
    }
    const printWindow = window.open("","","width=700,height=650");
    if (!printWindow) {
        alert("Please allow pop-ups to print the parking sticker.");
        return;
    }
    const stickerContent = sticker.outerHTML;
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>UPTM Parking Sticker</title>
            <style>
                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    padding: 40px;
                    background: #ffffff;
                    font-family:
                        Arial,
                        sans-serif;
                }

                .parking-sticker {
                    width: 420px;
                    margin: 0 auto;
                    border: 3px solid #174a91;
                    border-radius: 12px;
                    overflow: hidden;
                    background: #ffffff;
                    color: #24364d;
                }

                .sticker-header {
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    padding: 18px;
                    background: #174a91;
                    color: #ffffff;
                }

                .sticker-header img {
                    width: 60px;
                    height: auto;
                    padding: 5px;
                    border-radius: 5px;
                    background: #ffffff;
                }

                .sticker-header strong {
                    display: block;
                    margin-bottom: 4px;
                    font-size: 19px;
                    letter-spacing: 1px;
                }
                .sticker-header span {
                    font-size: 9px;
                    letter-spacing: 0.5px;
                }
                .sticker-line {
                    height: 5px;
                    background: #d92735;
                }

                .sticker-vehicle {
                    padding: 25px 20px;
                    text-align: center;
                }
                .sticker-vehicle span {
                    display: block;
                    margin-bottom: 7px;
                    color: #718096;
                    font-size: 9px;
                    font-weight: bold;
                    letter-spacing: 1px;
                }
                .sticker-vehicle strong {
                    display: block;
                    color: #174a91;
                    font-size: 34px;
                    letter-spacing: 3px;
                }

                .sticker-information {
                    display: grid;
                    grid-template-columns:
                        1fr 1fr;
                    gap: 1px;
                    background: #dfe5ec;
                    border-top:
                        1px solid #dfe5ec;
                }

                .sticker-information div {
                    padding: 13px;
                    background: #ffffff;
                }
                .sticker-information span {
                    display: block;
                    margin-bottom: 5px;
                    color: #8793a3;
                    font-size: 8px;
                }

                .sticker-information strong {
                    color: #33445a;
                    font-size: 10px;
                }
                .sticker-footer {
                    display: flex;
                    justify-content:
                        space-between;
                    align-items: center;
                    padding: 12px 18px;
                    background: #f2f5f8;
                }

                .sticker-footer span {
                    color: #174a91;
                    font-size: 9px;
                    font-weight: bold;
                }

                .sticker-footer strong {
                    color: #d92735;
                    font-size: 14px;
                }

                @media print {
                    body {
                        padding: 0;
                    }

                    .parking-sticker {
                        margin: 0 auto;
                        page-break-inside:
                            avoid;
                    }
                }
            </style>
        </head>
        <body>
            ${stickerContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.onload = function()
    {
        setTimeout(function()
            {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            },
            300
        );
    };
}
</script>
</body>
</html>
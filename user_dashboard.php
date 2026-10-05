<?php
session_start();
require_once "db.php";

// Get success message after submitting an application
$application_success = $_SESSION["application_success"] ?? "";

// Remove the message so it appears only once
unset($_SESSION["application_success"]);

// Only Student and Staff can access this page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!in_array($_SESSION["role"] ?? "", ["Student", "Staff"], true)) {
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "User";
$role = $_SESSION["role"];

// Get application statistics for the logged-in user
$sql = "SELECT
            COUNT(*) AS total_applications,
            COALESCE(SUM(status = 'Pending'), 0) AS pending_applications,
            COALESCE(SUM(status = 'Approved'), 0) AS approved_applications,
            COALESCE(SUM(status = 'Rejected'), 0) AS rejected_applications
        FROM permit_applications
        WHERE user_id = ?";

$stmt = mysqli_prepare($connect, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
$total = (int) ($stats["total_applications"] ?? 0);
$pending = (int) ($stats["pending_applications"] ?? 0);
$approved = (int) ($stats["approved_applications"] ?? 0);
$rejected = (int) ($stats["rejected_applications"] ?? 0);

// Get the latest application, vehicle and permit details
$recent_sql = "SELECT
                    a.application_id,
                    a.application_date,
                    a.status,
                    v.plate_number,
                    v.vehicle_model,
                    p.permit_id,
                    p.permit_number
                FROM permit_applications a
                INNER JOIN vehicles v
                    ON a.vehicle_id = v.vehicle_id
                LEFT JOIN parking_permits p
                    ON a.application_id = p.application_id
                WHERE a.user_id = ?
                ORDER BY a.application_date DESC,
                         a.application_id DESC
                LIMIT 1";

$recent_stmt = mysqli_prepare($connect, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $user_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
$recent = mysqli_fetch_assoc($recent_result);

// Escape text before displaying it in HTML
function e($value) {
    return htmlspecialchars((string) $value,ENT_QUOTES,"UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <!-- TOP NAVIGATION -->
    <header class="site-header">
        <a href="user_dashboard.php" class="site-brand">
            <img src="images/uptm-logo.png" alt="UPTM Logo" class="site-logo">
            <span class="site-brand-text">
                <strong>UPTM Parking Permit</strong>
                <small>Management System</small>
            </span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="user_dashboard.php" class="nav-link active" aria-current="page">
                Dashboard
            </a>
            <a href="apply_permit.php" class="nav-link">
                Apply Permit
            </a>
            <a href="my_applications.php" class="nav-link">
                My Applications
            </a>
            <a href="logout.php" class="nav-link nav-logout">
                Logout
            </a>
        </nav>
    </header>
    <main class="dashboard-main">
        <!-- WELCOME SECTION -->
        <section class="dashboard-hero">
            <div class="dashboard-hero-content">
                <span class="dashboard-eyebrow">
                    <?php echo e(strtoupper($role)); ?> PORTAL
                </span>
                <h1>Welcome back, <br><?php echo e($full_name); ?>!</h1>
                <p>
                    Apply for your campus parking permit,
                    track your application and access your
                    parking sticker after payment verification.
                </p>
                <a href="apply_permit.php" class="dashboard-hero-button">
                    Apply Parking Permit
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </section>
        <!-- APPLICATION STATISTICS -->
        <section class="dashboard-section">
            <div class="section-heading">
                <div><h2>Application Summary</h2></div>
                <a href="my_applications.php" class="section-link">
                    View All Applications &rarr;
                </a>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-icon stat-icon-blue">&#128203;</span>
                    <span class="stat-number"><?php echo $total; ?></span>
                    <span class="stat-label">Total Applications</span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon stat-icon-yellow">&#9203;</span>
                    <span class="stat-number"><?php echo $pending; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon stat-icon-green">&#10003;</span>
                    <span class="stat-number"><?php echo $approved; ?></span>
                    <span class="stat-label">Approved</span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon stat-icon-red">&#10005;</span>
                    <span class="stat-number"><?php echo $rejected; ?></span>
                    <span class="stat-label">Rejected</span>
                </div>
            </div>
        </section>
        <!-- RECENT APPLICATION -->
        <section class="dashboard-bottom-grid">
            <div class="dashboard-panel">
                <div class="panel-heading">
                    <h2>Latest Application</h2>
                    <p>Your most recent parking permit request.</p>
                </div>
                <?php if ($recent): ?>
                    <div class="recent-application recent-application-row">
                        <div class="recent-item">
                            <span>Application</span>
                            <strong>#<?php
                                echo str_pad((string) $recent["application_id"],3,"0",STR_PAD_LEFT);
                                ?>
                            </strong>
                        </div>
                        <div class="recent-item">
                            <span>Plate Number</span>
                            <strong><?php echo e($recent["plate_number"]); ?></strong>
                        </div>
                        <div class="recent-item">
                            <span>Vehicle</span>
                            <strong><?php echo e($recent["vehicle_model"]); ?></strong>
                        </div>
                        <div class="recent-item">
                            <span>Submitted</span>
                            <strong><?php echo date("d M Y",strtotime($recent["application_date"]));?></strong>
                        </div>

                        <?php if (!empty($recent["permit_id"])): ?>
                            <span class="status-badge status-permit-issued">&#10003; Permit Issued</span>
                            <a href="my_applications.php" class="recent-link">
                                View & Print Permit &rarr;
                            </a>
                        <?php else: ?>
                            <span class="status-badge status-<?php echo strtolower(e($recent["status"]));?>">
                                <?php echo e($recent["status"]); ?>
                            </span>
                            <a href="my_applications.php"class="recent-link">
                                View Details &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">&#128663;</span>
                        <h3>No applications yet</h3>
                        <p>
                            You have not submitted a parking
                            permit application.
                        </p>
                        <a href="apply_permit.php" class="empty-link">
                            Apply Now &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <footer class="dashboard-footer">
        &copy; <?php echo date("Y"); ?>
        UPTM Parking Permit Management System
    </footer>
<?php if ($application_success !== ""): ?>
<script>
    alert(<?php echo json_encode(
            $application_success,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ); ?>
    );
</script>
<?php endif; ?>
</body>
</html>
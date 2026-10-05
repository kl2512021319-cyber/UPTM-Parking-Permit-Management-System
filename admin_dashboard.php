<?php session_start();
require_once "db.php";

// Check whether user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Only Admin can access this page
if ($_SESSION["role"] !== "Admin") {
    header("Location: user_dashboard.php");
    exit();
}

$admin_name = $_SESSION["full_name"] ?? "Admin";

// DASHBOARD STATISTICS 
$user_query = "SELECT COUNT(*) AS total FROM users WHERE role IN ('Student', 'Staff')";
$user_result = mysqli_query($connect, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$total_users = $user_data["total"];

// Pending Applications
$pending_query = "SELECT COUNT(*) AS total FROM permit_applications WHERE status = 'Pending'";
$pending_result = mysqli_query($connect, $pending_query);
$pending_data = mysqli_fetch_assoc($pending_result);
$total_pending = $pending_data["total"];

// Approved Applications
$approved_query = "SELECT COUNT(*) AS total FROM permit_applications WHERE status = 'Approved'";
$approved_result = mysqli_query($connect, $approved_query);
$approved_data = mysqli_fetch_assoc($approved_result);
$total_approved = $approved_data["total"];

// Total Parking Permits
$permit_query = "SELECT COUNT(*) AS total FROM parking_permits";
$permit_result = mysqli_query($connect, $permit_query);
$permit_data = mysqli_fetch_assoc($permit_result);
$total_permits = $permit_data["total"];

// Get recent applications
$recent_query = "SELECT a.application_id, a.application_date, a.status, u.full_name, v.plate_number, v.vehicle_model
                 FROM permit_applications a
                 INNER JOIN users u
                    ON a.user_id = u.user_id
                 INNER JOIN vehicles v
                    ON a.vehicle_id = v.vehicle_id
                 ORDER BY a.application_id DESC
                 LIMIT 5";

$recent_result = mysqli_query($connect, $recent_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body class="admin-page">
    <!-- =========================
         TOP NAVIGATION
    ========================== -->
    <header class="admin-header">
        <a href="admin_dashboard.php" class="admin-brand">
            <img src="images/uptm-logo.png" alt="UPTM Logo" class="admin-logo">
            <div class="admin-brand-text">
                <strong>UPTM Parking Permit</strong>
                <span>
                    Administration Portal
                </span>
            </div>
        </a>
        <nav class="admin-nav">
            <a href="admin_dashboard.php" class="admin-nav-link active">Dashboard</a>
            <a href="manage_users.php" class="admin-nav-link">Manage Users</a>
            <a href="manage_applications.php" class="admin-nav-link">Applications</a>
            <a href="manage_permits.php" class="admin-nav-link">Permits</a>
            <a href="logout.php" class="admin-nav-link admin-logout">Logout</a>
        </nav>
    </header>

    <!-- =========================
         MAIN CONTENT
    ========================== -->
    <main class="admin-main">
        <section class="admin-welcome">
            <div>
                <span class="admin-eyebrow">
                    ADMINISTRATION PORTAL
                </span>
                <h1>Welcome back, <br><?php echo htmlspecialchars($admin_name);?>.</h1>
            </div>
            <div class="admin-date">
                <span>Today</span>
                <strong><?php echo date("d M Y"); ?></strong>
            </div>
        </section>

        <!-- =========================
             SHORT CARDS
        ========================== -->
        <section class="admin-stats">
            <a href="manage_users.php" class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">
                        TOTAL USERS
                    </span>
                </div>
                <strong class="admin-stat-number">
                    <?php echo $total_users; ?>
                </strong>
                <span class="admin-stat-description">
                    Registered students and staff
                </span>
            </a>

            <a href="manage_applications.php?status=Pending" class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">
                        PENDING APPLICATIONS
                    </span>
                </div>
                <strong class="admin-stat-number">
                    <?php echo $total_pending; ?>
                </strong>
                <span class="admin-stat-description">
                    Waiting for your review
                </span>
            </a>

            <a href="manage_applications.php?status=Approved" class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">
                        APPROVED
                    </span>
                </div>
                <strong class="admin-stat-number">
                    <?php echo $total_approved; ?>
                </strong>
                <span class="admin-stat-description">
                    Approved applications
                </span>
            </a>

            <a href="manage_permits.php" class="admin-stat-card">
                <div class="admin-stat-top">
                    <span class="admin-stat-label">
                        PARKING PERMITS
                    </span>
                </div>
                <strong class="admin-stat-number">
                    <?php echo $total_permits; ?>
                </strong>
                <span class="admin-stat-description">
                    Permits issued
                </span>
            </a>
        </section>

        <!-- =========================
             MAIN DASHBOARD GRID
        ========================== -->
        <section class="admin-dashboard-grid">
            <!-- RECENT APPLICATIONS -->
            <div class="admin-panel recent-applications">
                <div class="admin-panel-header">
                    <div>
                        <h2>Recent Applications</h2>
                        <p>Latest parking permit applications submitted by users.</p>
                    </div>
                    <a href="manage_applications.php">View All &rarr;</a>
                </div>
                <?php if (mysqli_num_rows($recent_result) > 0): ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Application</th>
                                    <th>Applicant</th>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($application = mysqli_fetch_assoc($recent_result)): ?>
                                    <tr>
                                        <td><strong>#<?php echo str_pad($application["application_id"],3,"0",STR_PAD_LEFT);?></strong></td>
                                        <td><?php echo htmlspecialchars($application["full_name"]);?></td>
                                        <td>
                                            <div class="admin-vehicle">
                                                <strong><?php echo htmlspecialchars($application["plate_number"]);?></strong>
                                                <span>
                                                    <?php
                                                    echo htmlspecialchars($application["vehicle_model"]);
                                                    ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="status-badge status-<?php
                                                echo strtolower($application["status"]);
                                                ?>">

                                                <?php
                                                echo htmlspecialchars($application["status"]);
                                                ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php
                                            echo date("d M Y",strtotime($application["application_date"]));
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <div class="admin-empty">
                        <strong>No applications yet</strong>
                        <p>New parking permit applications will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- QUICK MANAGEMENT -->
            <aside class="admin-panel admin-quick-panel">
                <div class="admin-panel-header">
                    <div>
                        <h2>Quick Management</h2>
                        <p>Access the main administrationfunctions.</p>
                    </div>
                </div>

                <!-- MANAGE USERS -->
                <a href="manage_users.php" class="admin-quick-item">
                    <span class="admin-quick-number">
                        01
                    </span>

                    <div>
                        <strong>Manage Users</strong>
                        <p>Add, view, update and delete user accounts.</p>
                    </div>

                    <span class="admin-quick-arrow">
                        &rarr;
                    </span>
                </a>

                <!-- APPLICATIONS -->
                <a href="manage_applications.php" class="admin-quick-item">
                    <span class="admin-quick-number">
                        02
                    </span>

                    <div>
                        <strong>Manage Applications</strong>
                        <p>Review vehicle details, approve or reject applications and verify payments.</p>
                    </div>

                    <span class="admin-quick-arrow">
                        &rarr;
                    </span>
                </a>

                <!-- PERMITS -->
                <a href="manage_permits.php" class="admin-quick-item">
                    <span class="admin-quick-number">
                        03
                    </span>

                    <div>
                        <strong>Manage Parking Permits</strong>
                        <p>Issue and manage approved parking permits.</p>
                    </div>

                    <span class="admin-quick-arrow">
                        &rarr;
                    </span>
                </a>
            </aside>
        </section>
    </main>
    <footer class="admin-footer">
        &copy;
        <?php echo date("Y"); ?>
        UPTM Parking Permit Management System
    </footer>
</body>
</html>
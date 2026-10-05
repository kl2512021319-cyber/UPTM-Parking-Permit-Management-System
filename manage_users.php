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

// PROCESS ACTION
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    // ADD USER
    if ($action === "add_user") {
        $full_name = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = trim($_POST["password"] ?? "");
        $role = trim($_POST["role"] ?? "");

        if (
            $full_name === "" ||
            $email === "" ||
            $password === "" ||
            $role === ""
        ) {
            $_SESSION["user_message"] = "Please complete all fields.";
            $_SESSION["user_message_type"] = "error";

        } elseif (
            !filter_var($email,FILTER_VALIDATE_EMAIL)
        ) {
            $_SESSION["user_message"] = "Please enter a valid email address.";
            $_SESSION["user_message_type"] = "error";

        } elseif (
            !in_array($role,["Student", "Staff"],true)
        ) {
            $_SESSION["user_message"] = "Invalid user role.";
            $_SESSION["user_message_type"] = "error";
        } else {
            // CHECK EMAIL
            $check_sql =
                "SELECT user_id
                 FROM users
                 WHERE email = ?
                 LIMIT 1";

            $check_stmt = mysqli_prepare($connect,$check_sql);
            mysqli_stmt_bind_param($check_stmt,"s",$email);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) > 0) {
                $_SESSION["user_message"] ="This email address is already registered.";
                $_SESSION["user_message_type"] = "error";
            } else {
                // INSERT USER
                $insert_sql =
                    "INSERT INTO users(full_name, email, password, role)
                    VALUES (?, ?, ?, ?)";

                $insert_stmt = mysqli_prepare($connect,$insert_sql);
                mysqli_stmt_bind_param($insert_stmt,"ssss",$full_name,$email,$password,$role);

                if (mysqli_stmt_execute($insert_stmt)) {
                    $_SESSION["user_message"] = $role . " account has been added successfully.";
                    $_SESSION["user_message_type"] = "success";
                } else {
                    $_SESSION["user_message"] = "User could not be added.";
                    $_SESSION["user_message_type"] = "error";
                }
            }
        }
        header("Location: manage_users.php");
        exit();
    }

    // UPDATE USER
    elseif ($action === "update_user") {
        $user_id = (int)($_POST["user_id"] ?? 0);
        $full_name = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = trim($_POST["password"] ?? "");
        $role = trim($_POST["role"] ?? "");

        if ($user_id <= 0) {
            $_SESSION["user_message"] = "Invalid user.";
            $_SESSION["user_message_type"] = "error";
        } elseif (
            $full_name === "" ||
            $email === "" ||
            $role === ""
        ) {
            $_SESSION["user_message"] = "Please complete all required fields.";
            $_SESSION["user_message_type"] = "error";
        } elseif (
            !filter_var($email,FILTER_VALIDATE_EMAIL)
        ) {
            $_SESSION["user_message"] = "Please enter a valid email address.";
            $_SESSION["user_message_type"] = "error";
        } elseif (
            !in_array($role,["Student", "Staff"],true)
        ) {
            $_SESSION["user_message"] = "Invalid user role.";
            $_SESSION["user_message_type"] = "error";

        } else {
            // MAKE SURE USER IS NOT ADMIN
            $user_check_sql =
                "SELECT role
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1";

            $user_check_stmt = mysqli_prepare($connect,$user_check_sql);
            mysqli_stmt_bind_param($user_check_stmt,"i",$user_id);
            mysqli_stmt_execute($user_check_stmt);
            $user_check_result = mysqli_stmt_get_result($user_check_stmt);
            $user_check =mysqli_fetch_assoc($user_check_result);

            if (!$user_check ||$user_check["role"] === "Admin") {
                $_SESSION["user_message"] = "Admin account cannot be edited here.";
                $_SESSION["user_message_type"] = "error";
            } else {
                // CHECK DUPLICATE EMAIL
                $email_check_sql =
                    "SELECT user_id
                     FROM users
                     WHERE email = ?
                     AND user_id != ?
                     LIMIT 1";

                $email_check_stmt = mysqli_prepare($connect,$email_check_sql);
                mysqli_stmt_bind_param($email_check_stmt,"si",$email,$user_id);
                mysqli_stmt_execute($email_check_stmt);
                $email_check_result = mysqli_stmt_get_result($email_check_stmt);
                
                if (mysqli_num_rows($email_check_result) > 0) {
                    $_SESSION["user_message"] = "This email address is already used by another account.";
                    $_SESSION["user_message_type"] = "error";
                } else {
                    // PASSWORD ENTERED
                    if ($password !== "") {
                        $update_sql = "UPDATE users
                             SET full_name = ?,email = ?, password = ?, role = ?
                             WHERE user_id = ?
                             AND role != 'Admin'";

                        $update_stmt =mysqli_prepare($connect,$update_sql);
                        mysqli_stmt_bind_param($update_stmt,"ssssi",$full_name,$email,$password,$role,$user_id);

                    // KEEP OLD PASSWORD
                    } else {
                        $update_sql =
                            "UPDATE users
                             SET full_name = ?, email = ?, role = ?
                             WHERE user_id = ?
                             AND role != 'Admin'";

                        $update_stmt = mysqli_prepare($connect,$update_sql);
                        mysqli_stmt_bind_param($update_stmt,"sssi",$full_name,$email,$role,$user_id);
                    }
                    if (mysqli_stmt_execute($update_stmt)) {
                        $_SESSION["user_message"] = "User information has been updated successfully.";
                        $_SESSION["user_message_type"] = "success";
                    } else {
                        $_SESSION["user_message"] = "User information could not be updated.";
                        $_SESSION["user_message_type"] = "error";
                    }
                }
            }
        }
        header("Location: manage_users.php");
        exit();
    }

    // DELETE USER
    elseif ($action === "delete_user") {
        $user_id = (int)($_POST["user_id"] ?? 0);

        if ($user_id <= 0) {
            $_SESSION["user_message"] = "Invalid user.";
            $_SESSION["user_message_type"] = "error";

        } else {
            // CHECK USER
            $check_sql =
                "SELECT role
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1";

            $check_stmt = mysqli_prepare($connect,$check_sql);
            mysqli_stmt_bind_param($check_stmt,"i",$user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $user = mysqli_fetch_assoc($check_result);

            if (!$user) {
                $_SESSION["user_message"] = "User not found.";
                $_SESSION["user_message_type"] = "error";

            } elseif ($user["role"] === "Admin") {
                $_SESSION["user_message"] = "Admin account cannot be deleted.";
                $_SESSION["user_message_type"] = "error";

            } else {
                // DELETE USER
                $delete_sql =
                    "DELETE FROM users
                     WHERE user_id = ?
                     AND role != 'Admin'";

                $delete_stmt = mysqli_prepare($connect,$delete_sql);
                mysqli_stmt_bind_param($delete_stmt,"i",$user_id);

                if (mysqli_stmt_execute($delete_stmt)) {
                    if (mysqli_stmt_affected_rows($delete_stmt) > 0) {
                        $_SESSION["user_message"] = "User has been deleted successfully.";
                        $_SESSION["user_message_type"] = "success";

                    } else {
                        $_SESSION["user_message"] = "User could not be deleted.";
                        $_SESSION["user_message_type"] = "error";
                    }

                } else {
                    
                    $_SESSION["user_message"] = "This user cannot be deleted because the account has related parking application records.";
                    $_SESSION["user_message_type"] = "error";
                }
            }
        }
        header("Location: manage_users.php");
        exit();
    }
}

// MESSAGE
$message = $_SESSION["user_message"] ?? "";
$message_type = $_SESSION["user_message_type"] ?? "";
unset($_SESSION["user_message"],$_SESSION["user_message_type"]);

// SEARCH
$search = trim($_GET["search"] ?? "");

// COUNTS
$count_sql =
    "SELECT COUNT(*) AS total, SUM(role = 'Student') AS students, SUM(role = 'Staff') AS staff
     FROM users
     WHERE role != 'Admin'";

$count_result =mysqli_query($connect, $count_sql);
$count_data = mysqli_fetch_assoc($count_result);
$total_users = (int)($count_data["total"] ?? 0);
$total_students = (int)($count_data["students"] ?? 0);
$total_staff = (int)($count_data["staff"] ?? 0);

// GET USERS
if ($search !== "") {
    $search_value = "%" . $search . "%";
    $sql =
        "SELECT user_id, full_name, email, role, created_at
         FROM users
         WHERE full_name LIKE ?
         OR email LIKE ?
         OR role LIKE ?
         ORDER BY
            CASE
                WHEN role = 'Admin' THEN 1
                ELSE 2
            END, user_id DESC";

    $stmt = mysqli_prepare($connect,$sql);
    mysqli_stmt_bind_param($stmt,"sss",$search_value,$search_value,$search_value);
    mysqli_stmt_execute($stmt);
    $result =mysqli_stmt_get_result($stmt);

} else {
    $sql =
        "SELECT user_id, full_name, email, role, created_at
         FROM users
         ORDER BY
            CASE
                WHEN role = 'Admin' THEN 1
                ELSE 2
            END, user_id DESC";

    $result = mysqli_query($connect, $sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | UPTM Parking Permit</title>
    <link rel="stylesheet" href="style.css?v=11">
</head>
<body class="admin-page">
<!-- ====================================================
     HEADER
===================================================== -->
<header class="admin-header">
    <a href="admin_dashboard.php" class="admin-brand">
        <img src="images/uptm-logo.png" alt="UPTM Logo" class="admin-logo">
        <div class="admin-brand-text">
            <strong>UPTM Parking Permit</strong>
            <span>Administration Portal</span>
        </div>
    </a>
    <nav class="admin-nav">
        <a href="admin_dashboard.php"class="admin-nav-link">
            Dashboard
        </a>
        <a href="manage_users.php" class="admin-nav-link active">
            Manage Users
        </a>
        <a href="manage_applications.php" class="admin-nav-link">
            Applications
        </a>
        <a href="manage_permits.php" class="admin-nav-link" >
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
    <section class="manage-page-heading">
        <span class="admin-eyebrow">
            USER MANAGEMENT
        </span>
        <h1>Manage Users</h1>
        <p>
            View, search, add, update and
            manage Student and Staff accounts.
        </p>
    </section>
    <?php if ($message !== ""): ?>
        <div class=" manage-user-message
            <?php
            echo $message_type === "success"? "manage-user-success": "manage-user-error";
            ?>"
        >
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <section class="user-summary-grid">
        <div class="user-summary-card">
            <span>Total Users</span>
            <strong>
                <?php echo $total_users; ?>
            </strong>
        </div>
        <div class="user-summary-card">
            <span>Students</span>
            <strong>
                <?php echo $total_students; ?>
            </strong>
        </div>
        <div class="user-summary-card">
            <span>Staff</span>
            <strong>
                <?php echo $total_staff; ?>
            </strong>
        </div>
    </section>
    <!-- =================================================
         ADD USER
    ================================================== -->
    <details class="add-user-panel">
        <summary>
            <div>
                <span>ADD NEW USER</span>
                <strong>Create Student / Staff Account</strong>
            </div>
            <div class="add-user-plus">+</div>
        </summary>
        <form method="POST" action="manage_users.php"class="admin-user-form">
            <input type="hidden" name="action"value="add_user">
            <div class="admin-user-form-grid">
                <div class="admin-user-form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter full name" maxlength="100" required>
                </div>
                <div class="admin-user-form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter email" maxlength="100" required>
                </div>
                <div class="admin-user-form-group">
                    <label>Password</label>
                    <input type="text" name="password" placeholder="Enter password" required>
                </div>
                <div class="admin-user-form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="Student">Student</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>
            </div>
            <div class="admin-user-form-action">
                <button type="submit" class="add-user-button">Add User</button>
            </div>
        </form>
    </details>

    <!-- =================================================
         USERS SECTION
    ================================================== -->
    <section class="manage-users-section">
        <!-- SEARCH -->
        <div class="manage-users-toolbar">
            <div>
                <h2>Registered Users</h2>
                <p>Manage all registered accounts.</p>
            </div>
            <form method="GET" action="manage_users.php" class="user-search-form">
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search name, email or role...">
                <button type="submit">Search</button>
                <?php if ($search !== ""): ?>
                    <a href="manage_users.php" class="clear-user-search">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <!-- SEARCH RESULT -->
        <?php if ($search !== ""): ?>
            <div class="search-result-text">
                Search result for:
                <strong>
                    "<?php echo e($search); ?>"
                </strong>
            </div>
        <?php endif; ?>
        <!-- =================================================
             USER LIST
        ================================================== -->
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="admin-user-card-grid">
                <?php while ($user = mysqli_fetch_assoc($result)): ?>
                    <details class="admin-user-card">
                        <summary class="admin-user-card-summary">
                            <div class="user-card-top">
                                <div class="admin-user-avatar <?php echo strtolower(e($user["role"])); ?>">
                                    <?php
                                    echo strtoupper(substr($user["full_name"], 0, 1));
                                    ?>
                                </div>
                                <div class="user-card-name">
                                    <div class="user-name-role">
                                        <h3><?php echo e($user["full_name"]); ?></h3>
                                        <span class="user-role-badgerole-<?php echo strtolower(e($user["role"]));?>">
                                            <?php echo e($user["role"]); ?>
                                        </span>
                                    </div>
                                    <span class="user-card-arrow">
                                        &#9662;
                                    </span>
                                </div>
                            </div>
                            <div class="user-card-information">
                                <div class="user-card-info user-card-email">
                                    <span>Email</span>
                                    <strong><?php echo e($user["email"]); ?></strong>
                                </div>
                                <div class="user-card-info">
                                    <span>User ID</span>
                                    <strong>#<?php
                                        echo str_pad($user["user_id"],3,"0",STR_PAD_LEFT);
                                        ?>
                                    </strong>
                                </div>
                                <div class="user-card-info">
                                    <span>Registered Date</span>
                                    <strong>
                                        <?php
                                        echo date("d M Y",strtotime($user["created_at"]));
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="user-card-view">Click to view account details</div>
                        </summary>
                        <!-- =====================================
                            EXPANDED DETAILS
                        ===================================== -->
                        <div class="admin-user-card-details">
                            <?php if ($user["role"] === "Admin"): ?>
                                <div class="admin-account-note">
                                    <strong>Administrator Account</strong>
                                    <p>
                                        This account is protected and
                                        cannot be edited or deleted
                                        from Manage Users.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="edit-user-section">
                                    <div class="edit-user-heading">
                                        <span>EDIT USER</span>
                                        <h4>Update Account Information</h4>
                                    </div>
                                    <form method="POST" action="manage_users.php" class="admin-user-form">
                                        <input type="hidden" name="action" value="update_user">
                                        <input type="hidden" name="user_id" value="<?php
                                            echo (int)$user["user_id"];
                                            ?>"
                                        >
                                        <div class="admin-user-form-grid">
                                            <div class="admin-user-form-group">
                                                <label>Full Name</label>
                                                <input type="text" name="full_name" value="<?php
                                                    echo e($user["full_name"]);
                                                    ?>"
                                                    minlength="3"
                                                    maxlength="100"
                                                    oninput=" this.value = this.value.toUpperCase();"
                                                    required
                                                >
                                            </div>

                                            <div class="admin-user-form-group">
                                                <label>Email</label>
                                                <input type="email" name="email"value="<?php
                                                    echo e($user["email"]);
                                                    ?>"
                                                    maxlength="100"
                                                    required
                                                >
                                            </div>

                                            <div class="admin-user-form-group">
                                                <label>New Password</label>
                                                <input type="password" name="password" placeholder="Leave blank to keep current password" autocomplete="new-password">
                                                <small class="password-remark">
                                                    Leave blank to keep the
                                                    current password.
                                                </small>
                                            </div>
                                            <div class="admin-user-form-group">
                                                <label>Role</label>
                                                <select name="role" required>
                                                    <option value="Student"
                                                        <?php
                                                        echo $user["role"] === "Student" ? "selected": "";
                                                        ?>
                                                    >
                                                        Student
                                                    </option>

                                                    <option value="Staff"
                                                        <?php
                                                        echo $user["role"] === "Staff" ? "selected" : "";
                                                        ?>
                                                    >
                                                        Staff
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="edit-user-actions">
                                            <button type="submit" class="update-user-button">
                                                Update User
                                            </button>
                                        </div>
                                    </form>

                                    <form method="POST" action="manage_users.php" class="delete-user-form"
                                        onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action"value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php
                                            echo (int)$user["user_id"];
                                            ?>"
                                        >
                                        <button type="submit" class="delete-user-button">
                                            Delete User
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="users-empty">
                <div>
                    &#128100;
                </div>
                <h3>No Users Found</h3>
                <p>
                    <?php if ($search !== ""): ?>
                        No user matches your search.
                    <?php else: ?>
                        No registered users found.
                    <?php endif; ?>
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
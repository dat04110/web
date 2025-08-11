<?php
session_start(); // Start session to check login status

// Log configuration
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting admin.php processing\n", FILE_APPEND);

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection information
$host = 'localhost';
$dbname = 'se07201_sdlc';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully connected to MySQL database $dbname\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection error: " . $e->getMessage());
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Retrieve current user information
try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User not found for ID {$_SESSION['user_id']}\n", FILE_APPEND);
        die("User information not found.");
    }
    $username = $user['username'];
    $current_role = $user['role'] ?? 'customer';
    $_SESSION['role'] = $current_role; // Update role in session
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user information: " . $e->getMessage());
}

// Variables for messages
$error_message = '';
$success_message = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_role === 'admin') {
    if (isset($_POST['action']) && $_POST['action'] === 'promote' && isset($_POST['id'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "CSRF validation failed.";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed for promotion\n", FILE_APPEND);
        } else {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'customer'");
            } catch (PDOException $e) {
                if ($e->getCode() != '1060') {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error checking/creating role column: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - User ID {$_POST['id']} promoted to admin\n", FILE_APPEND);
                $success_message = "User promoted successfully.";
            } catch (PDOException $e) {
                $error_message = "Error promoting user: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error promoting user: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'demote' && isset($_POST['id'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "CSRF validation failed.";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed for demotion\n", FILE_APPEND);
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - User ID {$_POST['id']} demoted to customer\n", FILE_APPEND);
                $success_message = "User demoted successfully.";
            } catch (PDOException $e) {
                $error_message = "Error demoting user: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error demoting user: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['id']) && isset($_POST['status'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "CSRF validation failed.";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed for status update\n", FILE_APPEND);
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
                $stmt->execute([':status' => $_POST['status'], ':id' => $_POST['id']]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order ID {$_POST['id']} status updated to {$_POST['status']}\n", FILE_APPEND);
                $success_message = "Order status updated successfully.";
            } catch (PDOException $e) {
                $error_message = "Error updating order status: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error updating order status: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete_order' && isset($_POST['id'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "CSRF validation failed.";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed for order deletion\n", FILE_APPEND);
        } else {
            try {
                // Check if the order exists
                $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                if (!$stmt->fetch()) {
                    $error_message = "Order not found.";
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order ID {$_POST['id']} does not exist\n", FILE_APPEND);
                } else {
                    // Delete order from the orders table (order_details will be deleted automatically via ON DELETE CASCADE)
                    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = :id");
                    $stmt->execute([':id' => $_POST['id']]);
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order ID {$_POST['id']} deleted successfully\n", FILE_APPEND);
                    $success_message = "Order deleted successfully.";
                }
            } catch (PDOException $e) {
                $error_message = "Error deleting order: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error deleting order: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
}

// Retrieve list of users
try {
    $stmt = $pdo->query("SELECT id, username, phone_number, address, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user list: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user list: " . $e->getMessage());
}

// Retrieve list of orders (admin only)
$orders = [];
if ($current_role === 'admin') {
    try {
        $stmt = $pdo->query("SELECT * FROM orders");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving order list: " . $e->getMessage() . "\n", FILE_APPEND);
        die("Error retrieving order list: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Coffee Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f1e9;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            height: 100%;
            font-family: 'Arial', sans-serif;
        }
        html {
            height: 100%;
        }
        .header {
            background-color: #3c2f2f;
            padding: 15px 0;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .logo img {
            height: 100px;
            object-fit: contain;
        }
        .dropdown-menu {
            background-color: #4b3b2a;
            border: none;
            border-radius: 5px;
        }
        .dropdown-item {
            color: #f8f1e9;
            text-transform: uppercase;
            font-weight: 500;
            padding: 10px 15px;
        }
        .dropdown-item:hover {
            background-color: #5a3c2a;
            color: #e6b800;
        }
        .admin-dashboard {
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            margin: 20px auto;
            max-width: 1200px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            table-layout: fixed;
        }
        .admin-table th, .admin-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .admin-table th {
            background-color: #6f4e37;
            color: #fff;
            font-weight: 600;
        }
        .admin-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .admin-table tr:hover {
            background-color: #f1ece6;
        }
        .btn {
            transition: background-color 0.3s, border-color 0.3s;
        }
        .btn-warning {
            background-color: #e6b800;
            border-color: #e6b800;
            color: #3c2f2f;
        }
        .btn-warning:hover {
            background-color: #d4a700;
            border-color: #d4a700;
        }
        .btn-success {
            background-color: #6f4e37;
            border-color: #6f4e37;
        }
        .btn-success:hover {
            background-color: #5a3c2a;
            border-color: #5a3c2a;
        }
        .btn-danger {
            background-color: #a94442;
            border-color: #a94442;
        }
        .btn-danger:hover {
            background-color: #953b39;
            border-color: #953b39;
        }
        .footer {
            background-color: #2d2d2f;
            color: #f8f1e9;
            padding: 40px 0 20px;
            border-top: 2px solid #444;
            width: 100%;
            font-family: 'Arial', sans-serif;
        }
        .footer .contact-info {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .footer .contact-info > div {
            flex: 1;
            min-width: 200px;
            margin-bottom: 20px;
        }
        .footer .contact-info p {
            margin: 5px 0;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        .footer .contact-info .phone {
            color: #f8b700;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .footer .contact-info .social {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .footer .contact-info .social a {
            color: #f8f1e9;
            font-size: 1.5rem;
            margin: 0 10px;
            transition: color 0.3s ease;
            text-decoration: none;
        }
        .footer .contact-info .social a:hover {
            color: #f8b700;
        }
        .footer .contact-info .social a:first-child {
            margin-left: 0;
        }
        .footer .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #ccc;
        }
        .avatar-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
        }
        @media (max-width: 768px) {
            .footer .contact-info {
                flex-direction: column;
                text-align: center;
            }
            .footer .contact-info .social {
                margin-top: 15px;
            }
        }
        .container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .content {
            flex: 1 0 auto;
            width: 100%;
        }
        footer {
            flex-shrink: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <div class="header">
                <div class="row align-items-center">
                    <div class="col-6 col-md-2">
                        <div class="logo">
                            <img src="https://spencil.vn/wp-content/uploads/2024/06/mau-thiet-ke-logo-thuong-hieu-cafe-SPencil-Agency-2.png" alt="Coffee Shop Logo">
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <h2 class="text-center text-white">Admin Dashboard</h2>
                    </div>
                    <div class="col-6 col-md-2 d-flex justify-content-end">
                        <div class="account dropdown">
                            <a href="#" class="dropdown-toggle" id="accountDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="https://cdn.kona-blue.com/upload/kona-blue_com/post/images/2024/09/18/457/avatar-mac-dinh-11.jpg" alt="Avatar" class="avatar-img">
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="accountDropdown">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="ASM.php">Home</a></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="admin-dashboard">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <h2>Welcome <?php echo htmlspecialchars($username); ?> - System Management</h2>
                <p>Current Role: <strong><?php echo htmlspecialchars($current_role); ?></strong></p>

                <h3>User List</h3>
                <table class="admin-table">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Phone Number</th>
                        <th>Address</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone_number'] ?? 'Not provided'); ?></td>
                            <td><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></td>
                            <td><?php echo htmlspecialchars($user['role'] ?? 'customer'); ?></td>
                            <td>
                                <?php if ($current_role === 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <form action="admin.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="promote">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">Promote to Admin</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="admin.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="demote">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Demote to Customer</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php if ($current_role === 'admin'): ?>
                    <h3>Order List</h3>
                    <table class="admin-table">
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Total Price</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?> VND</td>
                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                <td><?php echo htmlspecialchars($order['status']); ?></td>
                                <td>
                                    <form action="admin.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Confirm</button>
                                    </form>
                                    <form action="admin.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                    </form>
                                    <form action="admin.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_order">
                                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete order ID <?php echo $order['id']; ?> of <?php echo htmlspecialchars($order['customer_name']); ?>?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p class="text-danger">You do not have permission to access the order list.</p>
                <?php endif; ?>
            </div>
        </div>
        <footer class="footer">
            <div class="contact-info">
                <div>
                    <p>About Us</p>
                    <p>BTEC Sweet Shop brings delicious, high-quality coffee, 
                        spreading sweet joy to every home.</p>
                </div>
                <div>
                    <p>Contact</p>
                    <p class="contact-item"><i class="fas fa-map-marker-alt"></i> 27 Bac Lai Xa</p>
                    <p class="contact-item"><i class="fas fa-phone"></i> <span class="phone">0384687885</span></p>
                    <p class="contact-item"><i class="fas fa-envelope"></i> lequocdat468@gmail.com</p>
                </div>
                <div class="social">
                    <a href="#">Contact</a>
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="copyright">
                <p>©2025 BTEC Sweet Shop. All Rights Reserved</p>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
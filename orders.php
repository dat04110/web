<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Logging configuration
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing orders.php\n", FILE_APPEND);

// Database connection information
$host = 'localhost';
$dbname = 'se07201_sdlc';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully connected to MySQL with $dbname\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection error: " . $e->getMessage());
}

// Retrieve order list
try {
    $stmt = $pdo->query("SELECT id, customer_name, phone_number, address, total_price, order_date, status FROM orders");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved order list, count: " . count($orders) . "\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving order list: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving order list: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Shop - Order History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f1e9;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .header {
            background-color: #3c2f2f;
            padding: 15px 0;
        }
        .logo img {
            height: 100px;
            object-fit: contain;
        }
        .order-table {
            margin-top: 20px;
        }
        .order-table th, .order-table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-6 col-md-2">
                    <div class="logo">
                        <img src="https://spencil.vn/wp-content/uploads/2024/06/mau-thiet-ke-logo-thuong-hieu-cafe-SPencil-Agency-2.png" alt="Coffee Shop Logo">
                    </div>
                </div>
                <div class="col-6 col-md-10 text-end">
                    <a href="profile.php" class="btn btn-light me-2">Profile</a>
                    <a href="index.php" class="btn btn-danger">Log Out</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container mt-4">
        <h2>Order History</h2>
        <?php if (!empty($orders)): ?>
            <table class="table table-striped order-table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Phone Number</th>
                        <th>Address</th>
                        <th>Total Price</th>
                        <th>Order Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($order['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($order['address'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($order['order_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center">No orders found.</p>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
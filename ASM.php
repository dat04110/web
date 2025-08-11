<?php
session_start(); // Start session to check login status

// Log configuration
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting ASM.php processing\n", FILE_APPEND);

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

// Check login status
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit;
}

// Retrieve user information from the users table
try {
    $stmt = $pdo->prepare("SELECT username, phone_number, address FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User information not found for ID {$_SESSION['user_id']}\n", FILE_APPEND);
        die("User information not found.");
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved user information for ID {$_SESSION['user_id']}\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user information: " . $e->getMessage());
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $user_id = $_SESSION['user_id'];
    $total_price = floatval($_POST['total_price']);
    $order_date = date('Y-m-d H:i:s');
    $status = 'pending';
    $customer_name = $user['username'];
    $phone_number = $user['phone_number'] ?? '';
    $address = $user['address'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, phone_number, address, total_price, order_date, status) VALUES (:user_id, :customer_name, :phone_number, :address, :total_price, :order_date, :status)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':customer_name' => $customer_name,
            ':phone_number' => $phone_number,
            ':address' => $address,
            ':total_price' => $total_price,
            ':order_date' => $order_date,
            ':status' => $status
        ]);

        $order_id = $pdo->lastInsertId();
        $cart_items = json_decode($_POST['cart_items'], true);
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)");
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['id'],
                ':quantity' => $item['quantity'],
                ':price' => $item['price']
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error placing order: ' . $e->getMessage()]);
    }
    exit;
}

// Retrieve product list with category filter
$loai = isset($_GET['loai']) ? $_GET['loai'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $query = "SELECT id AS product_id, name AS product_name, price AS product_price, image AS product_image, description AS product_description, category AS product_category FROM products";
    $params = [];

    if ($loai) {
        $query .= " WHERE category = :loai";
        $params[':loai'] = $loai;
    }
    if ($search) {
        $query .= $loai ? " AND" : " WHERE";
        $query .= " name LIKE :search";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved product list, count: " . count($products) . "\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving product list: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving product list: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f1e9;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            height: 100%;
        }
        html {
            height: 100%;
        }
        .header {
            background-color: #3c2f2f;
            padding: 15px 0;
            width: 100%;
        }
        .logo img {
            height: 100px;
            object-fit: contain;
        }
        .search-form .btn {
            background-color: #6f4e37;
            border-color: #6f4e37;
        }
        .search-form .btn:hover {
            background-color: #5a3c2a;
            border-color: #5a3c2a;
        }
        .cart img, .account img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
        }
        .cart {
            cursor: pointer;
        }
        .navbar {
            background-color: #4b3b2a;
            width: 100%;
        }
        .navbar-nav .nav-link {
            color: #f8f1e9;
            text-transform: uppercase;
            font-weight: 500;
        }
        .navbar-nav .nav-link:hover {
            color: #e6b800;
        }
        .sidebar {
            background-color: #6f4e37;
            padding: 15px;
            border-radius: 5px;
            min-height: 100vh;
        }
        .sidebar h3 {
            color: #fff;
            background-color: #3c2f2f;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-size: 1.25rem;
        }
        .sidebar .list-group-item {
            padding: 10px;
            background-color: transparent;
            border: none;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .sidebar .list-group-item a {
            color: #fff;
            text-decoration: none;
            display: block;
        }
        .sidebar .list-group-item:hover {
            background-color: #5a3c2a;
            border-radius: 5px;
        }
        .sidebar .list-group-item:hover a {
            color: #fff;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            width: 100%;
            min-height: calc(100vh - 200px);
        }
        .product-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background-color: #fff;
            text-align: center;
            transition: box-shadow 0.3s;
        }
        .product-item:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .product-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
        }
        .product-item h4 {
            font-size: 1.1rem;
            margin: 10px 0;
            color: #3c2f2f;
        }
        .product-item p {
            font-size: 0.9rem;
            color: #555;
        }
        .product-buttons .btn {
            font-size: 0.9rem;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item span {
            font-size: 0.9rem;
            color: #555;
        }
        .cart-item .btn-danger {
            font-size: 0.8rem;
            padding: 5px 10px;
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
        @media (max-width: 768px) {
            .footer .contact-info {
                flex-direction: column;
                text-align: center;
            }
            .footer .contact-info .social {
                margin-top: 15px;
            }
        }
        .dropdown-menu {
            background-color: #4b3b2a;
            border: none;
        }
        .dropdown-item {
            color: #f8f1e9;
            text-transform: uppercase;
            font-weight: 500;
        }
        .dropdown-item:hover {
            background-color: #5a3c2a;
            color: #e6b800;
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
        @media (min-width: 992px) {
            .dont-collapse-lg {
                display: block !important;
            }
        }
        .no-results {
            text-align: center;
            font-size: 1.1rem;
            color: #555;
            margin-top: 20px;
        }
        .sidebar .list-group-item.active {
            background-color: #5a3c2a;
            border-radius: 5px;
        }
        .sidebar .list-group-item.active a {
            color: #e6b800;
            font-weight: bold;
        }
        .carousel-item img {
            height: 300px;
            object-fit: cover;
            border-radius: 5px;
        }
        .carousel-caption {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 5px;
            padding: 10px;
        }
        .carousel-caption h5, .carousel-caption p {
            color: #f8f1e9;
        }
        .carousel-control-prev, .carousel-control-next {
            background: rgba(0, 0, 0, 0.3);
            width: 5%;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <div class="row align-items-center">
                <div class="col-6 col-md-2">
                    <div class="logo">
                        <img src="https://spencil.vn/wp-content/uploads/2024/06/mau-thiet-ke-logo-thuong-hieu-cafe-SPencil-Agency-2.png" alt="Coffee Shop Logo">
                    </div>
                </div>
                <div class="col-12 col-md-8">
                    <form class="search-form d-flex" id="searchForm">
                        <input type="text" class="form-control me-2" id="searchInput" placeholder="Search Coffee Products" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </form>
                </div>
                <div class="col-6 col-md-2 d-flex justify-content-end">
                    <div class="cart me-2" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <img src="https://img.pikbest.com/png-images/qiantu/shopping-cart-icon-png-free-image_2605207.png!sw800" alt="Cart">
                    </div>
                    <div class="account dropdown">
                        <a href="#" class="dropdown-toggle" id="accountDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="https://cdn.kona-blue.com/upload/kona-blue_com/post/images/2024/09/18/457/avatar-mac-dinh-11.jpg" alt="Avatar">
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="accountDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">Orders</a></li>
                            <li><a class="dropdown-item" href="admin.php">Admin</a></li>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="addsanpham.php">Add Product</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" href="ASM.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#cartModal">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cartModalLabel">Cart</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modal-cart-items"></div>
                        <p id="modal-cart-empty" class="text-center">Cart is empty.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="orderButton">Place Order</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productDetailsModalLabel">Product Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <img src="" id="detailImage" alt="Product Image" class="img-fluid" style="max-height: 300px; object-fit: cover;">
                            </div>
                            <div class="col-md-8">
                                <h4 id="detailName"></h4>
                                <p id="detailPrice"></p>
                                <p id="detailDescription"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="content mt-4">
            <div class="row">
                <div class="col-12 mb-3 d-lg-none">
                    <button class="btn sidebar-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
                        <i class="fas fa-bars"></i> Categories
                    </button>
                </div>
                <div class="col-lg-3 col-md-4 col-12 sidebar collapse dont-collapse-lg" id="sidebarCollapse">
                    <h3>Vietnamese Coffee</h3>
                    <ul class="list-group">
                        <li class="list-group-item"><a href="#" data-name="Arabica Coffee">Arabica Coffee</a></li>
                        <li class="list-group-item"><a href="#" data-name="Robusta Coffee">Robusta Coffee</a></li>
                        <li class="list-group-item"><a href="#" data-name="Culi Coffee">Culi Coffee</a></li>
                        <li class="list-group-item"><a href="#" data-name="Cherry Coffee">Cherry Coffee</a></li>
                        <li class="list-group-item"><a href="#" data-name="Moka">Moka</a></li>
                    </ul>
                    <h3 class="mt-4">Italian Coffee</h3>
                    <ul class="list-group">
                        <li class="list-group-item"><a href="#" data-name="Espresso">Espresso</a></li>
                        <li class="list-group-item"><a href="#" data-name="Cappuccino">Cappuccino</a></li>
                        <li class="list-group-item"><a href="#" data-name="Macchiato">Macchiato</a></li>
                        <li class="list-group-item"><a href="#" data-name="Latte">Latte</a></li>
                        <li class="list-group-item"><a href="#" data-name="Mocha">Mocha</a></li>
                        <li class="list-group-item"><a href="#" data-name="Americano">Americano</a></li>
                    </ul>
                </div>
                <div class="col-lg-9 col-md-8">
                    <!-- Slider Bar -->
                    <div id="coffeeCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="https://images.unsplash.com/photo-1512568400610-62da28bc8a13?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" class="d-block w-100" alt="Coffee Promotion 1">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Discover Our Premium Arabica</h5>
                                    <p>Experience the rich aroma of our hand-picked beans.</p>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <img src="https://saltysweetjourneys.com/wp-content/uploads/2023/06/coffee-3095242_1280-1-1024x638.jpg" class="d-block w-100" alt="Coffee Promotion 2">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Espresso Bliss</h5>
                                    <p>Indulge in the bold flavors of our classic Espresso.</p>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <img src="https://capherangxay.vn/wp-content/uploads/2015/03/arabica.jpg" class="d-block w-100" alt="Coffee Promotion 3">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Arabica Coffee</h5>
                                    <p>Try our limited-edition Arabica Coffee today!</p>
                                </div>
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#coffeeCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#coffeeCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <!-- End Slider Bar -->
                    <!-- Category Filter -->
                    <div class="mb-4">
                        <h3>Products</h3>
                        <form class="category-filter" method="GET" action="ASM.php">
                            <div class="input-group">
                                <select name="loai" class="form-select" onchange="this.form.submit()">
                                    <option value="" <?php echo !$loai ? 'selected' : ''; ?>>All</option>
                                    <option value="Vietnamese Coffee" <?php echo $loai === 'Vietnamese Coffee' ? 'selected' : ''; ?>>Vietnamese Coffee</option>
                                    <option value="Italian Coffee" <?php echo $loai === 'Italian Coffee' ? 'selected' : ''; ?>>Italian Coffee</option>
                                </select>
                                <?php if ($search): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if (empty($products)): ?>
                            <div class="alert alert-info">No products found.</div>
                        <?php endif; ?>
                    </div>
                    <!-- Product Grid -->
                    <div class="product-grid" id="productGrid">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <div class="product-item" data-id="<?php echo htmlspecialchars($product['product_id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     data-name="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     data-price="<?php echo htmlspecialchars($product['product_price'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     data-description="<?php echo htmlspecialchars($product['product_description'] ?? 'No description available.', ENT_QUOTES, 'UTF-8'); ?>">
                                    <h4><?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <img src="<?php echo htmlspecialchars($product['product_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <p>Price: <?php echo number_format($product['product_price'], 0, ',', '.'); ?> VND</p>
                                    <div class="product-buttons">
                                        <a href="#" class="btn btn-link">Details</a>
                                        <button class="btn btn-success add-to-cart">Add to Cart</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center no-results">No products available.</p>
                        <?php endif; ?>
                    </div>
                    <p class="no-results" id="noResults" style="display: none;">No products found.</p>
                </div>
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
                    <a href="https://www.facebook.com/quocdat0411?locale=vi_VN"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.youtube.com/@MixiGaming3con" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div class="copyright">
                <p>©2025 BTEC Sweet Shop. All Rights Reserved</p>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            const cartItems = JSON.parse(localStorage.getItem('cart')) || [];
            const modalCartContainer = document.getElementById('modal-cart-items');
            const modalCartEmptyMessage = document.getElementById('modal-cart-empty');
            const productGrid = document.getElementById('productGrid');
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('searchInput');
            const noResultsMessage = document.getElementById('noResults');
            const orderButton = document.getElementById('orderButton');

            function updateCart() {
                modalCartContainer.innerHTML = '';
                if (cartItems.length === 0) {
                    modalCartEmptyMessage.style.display = 'block';
                    orderButton.disabled = true;
                } else {
                    modalCartEmptyMessage.style.display = 'none';
                    orderButton.disabled = false;
                    cartItems.forEach(item => {
                        const cartItem = document.createElement('div');
                        cartItem.classList.add('cart-item');
                        cartItem.innerHTML = `
                            <span>${item.name} - ${item.price.toLocaleString('vi-VN')} VND x ${item.quantity}</span>
                            <button class="btn btn-danger remove-from-cart" data-id="${item.id}">Remove</button>
                        `;
                        modalCartContainer.appendChild(cartItem);
                    });
                }

                document.querySelectorAll('.remove-from-cart').forEach(button => {
                    button.addEventListener('click', () => {
                        const id = button.getAttribute('data-id');
                        const item = cartItems.find(item => item.id === id);
                        if (item) {
                            item.quantity -= 1;
                            if (item.quantity <= 0) {
                                const index = cartItems.findIndex(item => item.id === id);
                                cartItems.splice(index, 1);
                            }
                            localStorage.setItem('cart', JSON.stringify(cartItems));
                            updateCart();
                        }
                    });
                });
            }

            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', () => {
                    const product = button.closest('.product-item');
                    const id = product.getAttribute('data-id');
                    const name = product.getAttribute('data-name');
                    const price = parseInt(product.getAttribute('data-price'));

                    const existingItem = cartItems.find(item => item.id === id);
                    if (existingItem) {
                        existingItem.quantity += 1;
                    } else {
                        cartItems.push({ id, name, price, quantity: 1 });
                    }

                    localStorage.setItem('cart', JSON.stringify(cartItems));
                    updateCart();
                });
            });

            orderButton.addEventListener('click', () => {
                if (cartItems.length > 0) {
                    const total_price = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);

                    fetch('ASM.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=place_order&total_price=${total_price}&cart_items=${encodeURIComponent(JSON.stringify(cartItems))}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            cartItems.length = 0;
                            localStorage.setItem('cart', JSON.stringify(cartItems));
                            updateCart();
                            bootstrap.Modal.getInstance(document.getElementById('cartModal')).hide();
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error submitting order: ' + error.message);
                    });
                }
            });

            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                filterProducts();
            });

            searchInput.addEventListener('input', filterProducts);

            document.querySelectorAll('.sidebar .list-group-item a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productName = link.getAttribute('data-name');
                    searchInput.value = productName;
                    filterProducts();

                    document.querySelectorAll('.sidebar .list-group-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    link.parentElement.classList.add('active');
                });
            });

            function filterProducts() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const productItems = productGrid.querySelectorAll('.product-item');
                let hasResults = false;

                productItems.forEach(item => {
                    const productName = item.getAttribute('data-name').toLowerCase();
                    if (productName.includes(searchTerm)) {
                        item.style.display = 'block';
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                noResultsMessage.style.display = hasResults || searchTerm === '' ? 'none' : 'block';
                if (productItems.length === 0) {
                    noResultsMessage.style.display = 'none';
                }
            }

            document.querySelectorAll('.btn-link').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const product = button.closest('.product-item');
                    const name = product.getAttribute('data-name');
                    const price = parseInt(product.getAttribute('data-price'));
                    const image = product.querySelector('img').src;
                    const description = product.getAttribute('data-description') || 'No description available.';

                    document.getElementById('detailName').textContent = name;
                    document.getElementById('detailPrice').textContent = `Price: ${price.toLocaleString('vi-VN')} VND`;
                    document.getElementById('detailImage').src = image;
                    document.getElementById('detailDescription').textContent = description;

                    const productDetailsModal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
                    productDetailsModal.show();
                });
            });

            updateCart();
        });
    </script>
</body>
</html>
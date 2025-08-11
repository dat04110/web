<?php
session_start(); // Start session to check login status

// Logging configuration
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing contact.php\n", FILE_APPEND);

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

// Check login status
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit;
}

// Retrieve user information
try {
    $stmt = $pdo->prepare("SELECT username, phone_number, address FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User information not found for ID {$_SESSION['user_id']}\n", FILE_APPEND);
        die("User information not found.");
    }
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user information: " . $e->getMessage());
}

// Process contact message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? $user['phone_number'];
    $message = $_POST['message'] ?? '';
    $created_at = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, message, created_at) VALUES (:name, :email, :phone, :message, :created_at)");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':message' => $message,
            ':created_at' => $created_at
        ]);

        $success = true;
        $message = "Your message has been sent successfully!";
        // Add JavaScript redirect to homepage after 2 seconds
        echo "<script>
                setTimeout(function() {
                    window.location.href = 'ASM.php';
                }, 2000);
              </script>";
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error saving contact message: " . $e->getMessage() . "\n", FILE_APPEND);
        $success = false;
        $message = "An error occurred while sending the message: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Coffee Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f1e9;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .contact-form {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .contact-form h2 {
            color: #3c2f2f;
            text-align: center;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        .form-label {
            font-weight: 500;
            color: #3c2f2f;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ccc;
            padding: 10px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #6f4e37;
            box-shadow: 0 0 5px rgba(111, 78, 55, 0.3);
        }
        .btn-primary {
            background-color: #6f4e37;
            border-color: #6f4e37;
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #5a3c2a;
            border-color: #5a3c2a;
        }
        .alert {
            margin-top: 20px;
            text-align: center;
        }
        .footer {
            background-color: #2d2d2f;
            color: #f8f1e9;
            padding: 40px 0 20px;
            border-top: 2px solid #444;
            width: 100%;
            margin-top: auto;
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
            font-size: 2rem;
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
            .contact-form {
                margin: 20px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="contact-form">
            <h2>Contact Us</h2>
            <?php if (isset($success)): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email">
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                </div>
                <button type="submit" name="submit_contact" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
    <footer class="footer">
        <div class="contact-info">
            <div>
                <p>About Us</p>
                <p>BTEC Sweet Shop offers delicious, high-quality coffee, spreading sweet joy to every home.</p>
            </div>
            <div>
                <p>Contact</p>
                <p class="contact-item"><i class="fas fa-map-marker-alt"></i> 27 Bac Lai Xa</p>
                <p class="contact-item"><i class="fas fa-phone"></i> <span class="phone">0384687885</span></p>
                <p class="contact-item"><i class="fas fa-envelope"></i> lequocdat468@gmail.com</p>
            </div>
            <div class="social">
                <a href="#">Contact</a>
                <a href="https://www.facebook.com/quocdat0411?locale=vi_VN" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://www.youtube.com/@MixiGaming3con" target="_blank"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
        <div class="copyright">
            <p>©2025 BTEC Sweet Shop. All Rights Reserved</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
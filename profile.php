<?php
session_start();

// Logging configuration
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing profile.php\n", FILE_APPEND);

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
    die(json_encode(['success' => false, 'message' => 'Database connection error']));
}

// Check login status
if (!isset($_SESSION['user_id'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Not logged in, redirecting to index.php\n", FILE_APPEND);
    header('Location: index.php?error=not_logged_in');
    exit;
}

// Function to validate phone number
function validatePhoneNumber($phone) {
    // Remove spaces and non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Validate Vietnamese phone number (starts with 0, 10-11 digits)
    return preg_match('/^0[0-9]{9,10}$/', $phone);
}

// Retrieve user information
try {
    $stmt = $pdo->prepare("SELECT username, email, phone_number, address FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User not found for ID {$_SESSION['user_id']}\n", FILE_APPEND);
        session_destroy();
        header('Location: login.php?error=user_not_found');
        exit;
    }
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved user information for ID {$_SESSION['user_id']}\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Error retrieving user information']));
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    // Validate data
    if (empty($username) || empty($email)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Required data missing\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Username and email are required']);
        exit;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid email: $email\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }

    // Validate phone number if provided
    if (!empty($phone_number) && !validatePhoneNumber($phone_number)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid phone number: $phone_number\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, phone_number = :phone_number, address = :address WHERE id = :user_id");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':phone_number' => $phone_number ?: null,
            ':address' => $address ?: null,
            ':user_id' => $_SESSION['user_id']
        ]);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully updated profile for user ID {$_SESSION['user_id']}\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error updating profile for user ID {$_SESSION['user_id']}: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Shop - User Profile</title>
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
        .profile-form {
            max-width: 600px;
            margin: 20px auto;
        }
        .error-message {
            color: red;
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="row align-items-center">
            <div class="col-6 col-md-2">
                <div class="logo">
                    <img src="https://spencil.vn/wp-content/uploads/2024/06/mau-thiet-ke-logo-thuong-hieu-cafe-SPencil-Agency-2.png" alt="Coffee Shop Logo">
                </div>
            </div>
            <div class="col-6 col-md-10 text-end">
                <a href="ASM.php" class="btn btn-success me-2">Home</a>
                <a href="orders.php" class="btn btn-light me-2">Order History</a>
                <a href="logout.php" class="btn btn-danger">Log Out</a>
            </div>
        </div>
    </div>
    <div class="container mt-4">
        <h2>User Profile</h2>
        <div id="errorMessage" class="error-message"></div>
        <form id="profileForm" class="profile-form">
            <input type="hidden" name="action" value="update_profile">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Example: 0987654321">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address"><?php echo htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Display error message from URL if present
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            if (error === 'not_logged_in') {
                alert('Please log in to access this page');
            } else if (error === 'user_not_found') {
                alert('User information not found. Please log in again');
            }

            const profileForm = document.getElementById('profileForm');
            const errorMessage = document.getElementById('errorMessage');

            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                errorMessage.style.display = 'none';

                const formData = new FormData(profileForm);
                fetch('profile.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        errorMessage.textContent = data.message;
                        errorMessage.style.display = 'block';
                    }
                })
                .catch(error => {
                    errorMessage.textContent = 'System error, please try again later';
                    errorMessage.style.display = 'block';
                    console.error('Error updating profile:', error);
                });
            });
        });
    </script>
</body>
</html>
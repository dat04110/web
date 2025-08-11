<?php
// Start session at the beginning
session_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Initialize messages
$register_msg = '';
$login_msg = '';
$error_msg = '';
$reset_msg = '';

// Database configuration (consider moving to a separate config file)
$host = 'localhost';
$dbname = 'se07201_sdlc';
$username = 'root';
$password = '';

try {
    $connect = new mysqli($host, $username, $password, $dbname);
    $connect->set_charset('utf8mb4');
    
    if ($connect->connect_error) {
        throw new Exception('Database connection failed: ' . $connect->connect_error);
    }

    // Create password_reset_tokens table
    $sql_create_table = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        INDEX idx_email (email),
        INDEX idx_token (token)
    )";
    $connect->query($sql_create_table);

} catch (Exception $e) {
    $error_msg = "<p class='text-red-500 text-center'>" . htmlspecialchars($e->getMessage()) . "</p>";
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type'])) {
    try {
        switch ($_POST['form_type']) {
            case 'register':
                $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $password = $_POST['password'];

                // Validate inputs
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $register_msg = "<p class='text-red-500 text-center'>Invalid email!</p>";
                } elseif (!preg_match("/^[a-zA-Z0-9]{3,30}$/", $username)) {
                    $register_msg = "<p class='text-red-500 text-center'>Username must be 3-30 characters and contain only letters or numbers!</p>";
                } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
                    $register_msg = "<p class='text-red-500 text-center'>Password must be at least 8 characters, including uppercase, lowercase, numbers, and special characters!</p>";
                } else {
                    $stmt = $connect->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
                    $stmt->bind_param("ss", $username, $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $register_msg = "<p class='text-red-500 text-center'>Username or email already exists!</p>";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $connect->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $username, $hashed_password, $email);
                        
                        if ($stmt->execute()) {
                            $register_msg = "<p class='text-green-500 text-center'>Registration successful! Please log in.</p>";
                            echo "<script>document.addEventListener('DOMContentLoaded', function() { toggleForm('loginForm'); });</script>";
                        } else {
                            throw new Exception('Registration failed');
                        }
                    }
                    $stmt->close();
                }
                break;

            case 'login':
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $password = $_POST['password'];

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $login_msg = "<p class='text-red-500 text-center'>Invalid email!</p>";
                } else {
                    $stmt = $connect->prepare("SELECT id, username, password FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        if (password_verify($password, $user['password'])) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            header("Location: ASM.php");
                            exit();
                        } else {
                            $login_msg = "<p class='text-red-500 text-center'>Incorrect password!</p>";
                        }
                    } else {
                        $login_msg = "<p class='text-red-500 text-center'>Email does not exist!</p>";
                    }
                    $stmt->close();
                }
                break;

            case 'forgot':
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $reset_msg = "<p class='text-red-500 text-center'>Invalid email!</p>";
                } else {
                    $stmt = $connect->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        $stmt = $connect->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $email, $token, $expires_at);
                        
                        if ($stmt->execute()) {
                            $reset_msg = "<p class='text-green-500 text-center'>A password reset link has been sent to your email!</p>";
                            $reset_msg .= "<p class='text-blue-500 text-center'>Token (for demo): <a href='?reset_token=" . htmlspecialchars($token) . "'>Reset Link</a></p>";
                        } else {
                            throw new Exception('Error creating reset link');
                        }
                    } else {
                        $reset_msg = "<p class='text-red-500 text-center'>Email does not exist!</p>";
                    }
                    $stmt->close();
                }
                break;

            case 'reset':
                $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
                $new_password = $_POST['new_password'];

                if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $new_password)) {
                    $reset_msg = "<p class='text-red-500 text-center'>New password must be at least 8 characters, including uppercase, lowercase, numbers, and special characters!</p>";
                } else {
                    $stmt = $connect->prepare("SELECT email FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
                    $stmt->bind_param("s", $token);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $email = $row['email'];
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        $stmt = $connect->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $stmt->bind_param("ss", $hashed_password, $email);
                        
                        if ($stmt->execute()) {
                            $stmt = $connect->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                            $stmt->bind_param("s", $token);
                            $stmt->execute();
                            $reset_msg = "<p class='text-green-500 text-center'>Password reset successfully!</p>";
                            echo "<script>document.addEventListener('DOMContentLoaded', function() { toggleForm('loginForm'); });</script>";
                        } else {
                            throw new Exception('Error resetting password');
                        }
                    } else {
                        $reset_msg = "<p class='text-red-500 text-center'>Invalid or expired token!</p>";
                    }
                    $stmt->close();
                }
                break;
        }
    } catch (Exception $e) {
        $error_msg = "<p class='text-red-500 text-center'>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

$connect->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Coffee Shop - Register & Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Roboto:wght@300;400;500&display=swap');

        body {
            background-image: url('coffee-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .form-container {
            max-width: 32rem;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            background-color: rgba(255, 245, 238, 0.95);
            transition: all 0.4s ease-in-out;
            z-index: 2;
            position: relative;
        }

        .form-container.hidden {
            transform: translateY(50px);
            opacity: 0;
            pointer-events: none;
            position: absolute;
        }

        .form-container.active {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
            position: relative;
        }

        .form-container h2 {
            font-family: 'Playfair Display', serif;
            color: #3c2f2f;
            font-size: 2.25rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
            letter-spacing: 0.05em;
        }

        .form-container label {
            color: #4a3728;
            font-weight: 500;
            font-size: 0.9rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .form-container input {
            width: 100%;
            padding: 0.85rem;
            border: 2px solid #d4a373;
            border-radius: 0.5rem;
            background-color: #fff8f0;
            font-size: 1rem;
            color: #3c2f2f;
            transition: all 0.3s ease;
        }

        .form-container input:focus {
            border-color: #8b5e3c;
            box-shadow: 0 0 8px rgba(139, 94, 60, 0.3);
            background-color: #ffffff;
            outline: none;
        }

        .form-container button {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(to right, #8b5e3c, #6b4e31);
            color: #fff8f0;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .form-container button:hover {
            background: linear-gradient(to right, #6b4e31, #4a3728);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .form-container button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .form-container a {
            color: #d4a373;
            font-weight: 500;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .form-container a:hover {
            color: #8b5e3c;
            text-decoration: underline;
        }

        .form-container p {
            color: #4a3728;
            margin-top: 1.25rem;
            text-align: center;
            font-size: 0.9rem;
        }

        @media (max-width: 640px) {
            .form-container {
                padding: 1.25rem;
                max-width: 90%;
            }

            .form-container h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="relative w-full max-w-md p-6">
        <!-- Register Form -->
        <div id="registerForm" class="form-container <?php echo !isset($_GET['reset_token']) ? 'active' : 'hidden'; ?>">
            <h2>Register</h2>
            <?php echo $error_msg; ?>
            <?php echo $register_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="register">
                <div>
                    <label for="regEmail" class="block">Email</label>
                    <input type="email" id="regEmail" name="email" class="mt-1" required>
                </div>
                <div>
                    <label for="regUsername" class="block">Username</label>
                    <input type="text" id="regUsername" name="username" class="mt-1" required pattern="[a-zA-Z0-9]{3,30}" title="Username must be 3-30 characters and contain only letters or numbers">
                </div>
                <div>
                    <label for="regPassword" class="block">Password</label>
                    <input type="password" id="regPassword" name="password" class="mt-1" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}" title="Password must be at least 8 characters, including uppercase, lowercase, numbers, and special characters">
                </div>
                <button type="submit">Register</button>
            </form>
            <p>Already have an account? <a href="#" onclick="toggleForm('loginForm')">Login</a></p>
            <p><a href="#" onclick="toggleForm('forgotForm')">Forgot Password?</a></p>
        </div>

        <!-- Login Form -->
        <div id="loginForm" class="form-container hidden">
            <h2>Login</h2>
            <?php echo $error_msg; ?>
            <?php echo $login_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="login">
                <div>
                    <label for="loginEmail" class="block">Email</label>
                    <input type="email" id="loginEmail" name="email" class="mt-1" required>
                </div>
                <div>
                    <label for="loginPassword" class="block">Password</label>
                    <input type="password" id="loginPassword" name="password" class="mt-1" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <p>Don't have an account? <a href="#" onclick="toggleForm('registerForm')">Register</a></p>
            <p><a href="#" onclick="toggleForm('forgotForm')">Forgot Password?</a></p>
        </div>

        <!-- Forgot Password Form -->
        <div id="forgotForm" class="form-container hidden">
            <h2>Forgot Password</h2>
            <?php echo $error_msg; ?>
            <?php echo $reset_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="forgot">
                <div>
                    <label for="forgotEmail" class="block">Email</label>
                    <input type="email" id="forgotEmail" name="email" class="mt-1" required>
                </div>
                <button type="submit">Send Reset Link</button>
            </form>
            <p><a href="#" onclick="toggleForm('loginForm')">Back to Login</a></p>
        </div>

        <!-- Reset Password Form -->
        <div id="resetForm" class="form-container <?php echo isset($_GET['reset_token']) ? 'active' : 'hidden'; ?>">
            <h2>Reset Password</h2>
            <?php echo $error_msg; ?>
            <?php echo $reset_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="reset">
                <input type="hidden" name="token" value="<?php echo isset($_GET['reset_token']) ? htmlspecialchars($_GET['reset_token']) : ''; ?>">
                <div>
                    <label for="newPassword" class="block">New Password</label>
                    <input type="password" id="newPassword" name="new_password" class="mt-1" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}" title="Password must be at least 8 characters, including uppercase, lowercase, numbers, and special characters">
                </div>
                <button type="submit">Reset Password</button>
            </form>
            <p><a href="#" onclick="toggleForm('loginForm')">Back to Login</a></p>
        </div>
    </div>

    <script>
        function toggleForm(formId) {
            const forms = ['registerForm', 'loginForm', 'forgotForm', 'resetForm'];
            forms.forEach(form => {
                const element = document.getElementById(form);
                element.classList.toggle('hidden', form !== formId);
                element.classList.toggle('active', form === formId);
            });
        }
    </script>
</body>
</html>
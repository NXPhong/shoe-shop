<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$errors = '';

// Lấy thông báo đăng ký thành công (nếu có)
$successMessage = '';
if (isset($_GET['message']) && isset($_GET['status']) && $_GET['status'] === 'success') {
    $successMessage = htmlspecialchars($_GET['message']);
}

include('dbconnect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']); // Loại bỏ khoảng trắng
    $password = $_POST['password'];

    // Debug: Hiển thị thông tin đăng nhập (chỉ để test, xóa sau khi sửa xong)
    // echo "Email nhập: " . $email . "<br>";
    // echo "Password nhập: " . $password . "<br>";

    // Kiểm tra tài khoản admin TRƯỚC
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    if (!$stmt) {
        $errors = "Lỗi prepare statement: " . $conn->error;
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Debug: Kiểm tra dữ liệu admin
            // echo "Admin username: " . $admin['username'] . "<br>";
            // echo "Admin password hash: " . $admin['password'] . "<br>";
            
            if (password_verify($password, $admin['password'])) {
                $_SESSION['username'] = $email;
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                header("Location: admin.php");
                exit();
            } else {
                $errors = "Mật khẩu admin không đúng.";
            }
        } else {
            // Nếu không phải admin, kiểm tra tài khoản khách hàng
            $stmt2 = $conn->prepare("SELECT * FROM customer WHERE email = ?");
            if (!$stmt2) {
                $errors = "Lỗi prepare statement customer: " . $conn->error;
            } else {
                $stmt2->bind_param("s", $email);
                $stmt2->execute();
                $result2 = $stmt2->get_result();

                if ($result2->num_rows == 1) {
                    $customer = $result2->fetch_assoc();
                    
                    // Kiểm tra mật khẩu khách hàng
                    if ($password === $customer['password']) {
                        $_SESSION['email'] = $email;
                        $_SESSION['customer_fullName'] = $customer['fullName'];
                        $_SESSION['customer_id'] = $customer['customer_id'];
                        header("Location: index.php");
                        exit();
                    } else {
                        $errors = "Mật khẩu không đúng.";
                    }
                } else {
                    $errors = "Email hoặc username không tồn tại.";
                }
                $stmt2->close();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Log into your account</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 50%, #2a2a2a 100%);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        .container {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        .left-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: linear-gradient(135deg, #000000 0%, #111111 100%);
        }

        .nike-logo {
            width: 400px;
            height: 250px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .swoosh-container {
            width: 280px;
            height: 140px;
            position: relative;
            animation: float 4s ease-in-out infinite;
            margin-bottom: 30px;
        }

        .swoosh {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .swoosh svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 25px 50px rgba(255, 255, 255, 0.3));
        }

        .nike-text {
            font-size: 48px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 8px;
            font-family: 'Arial Black', Arial, sans-serif;
            text-shadow: 0 10px 30px rgba(255, 255, 255, 0.5);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg) scale(1); 
            }
            50% { 
                transform: translateY(-15px) rotate(1deg) scale(1.05); 
            }
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1;
                text-shadow: 0 10px 30px rgba(255, 255, 255, 0.5);
            }
            50% { 
                opacity: 0.9;
                text-shadow: 0 10px 30px rgba(255, 255, 255, 0.8);
            }
        }

        .decorative-circles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            animation: rotate 25s linear infinite;
        }

        .circle:nth-child(1) {
            width: 400px;
            height: 400px;
            top: 10%;
            left: 5%;
            border-width: 3px;
            border-color: rgba(255, 255, 255, 0.3);
        }

        .circle:nth-child(2) {
            width: 250px;
            height: 250px;
            top: 55%;
            right: 10%;
            animation-duration: 20s;
            animation-direction: reverse;
            border-color: rgba(255, 255, 255, 0.25);
        }

        .circle:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 30%;
            right: 30%;
            animation-duration: 15s;
            border-color: rgba(255, 255, 255, 0.2);
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            border-left: 2px solid rgba(255, 255, 255, 0.3);
        }

        .login-form {
            width: 100%;
            max-width: 400px;
            color: white;
        }

        .header-info {
            text-align: center;
            margin-bottom: 40px;
        }

        .signing-into {
            color: #cccccc;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .brand-name {
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .brand-name .mini-swoosh {
            width: 30px;
            height: 18px;
        }

        .main-title {
            font-size: 32px;
            font-weight: 300;
            margin-bottom: 40px;
            text-align: center;
            color: #ffffff;
            text-shadow: 0 5px 15px rgba(255, 255, 255, 0.2);
        }

        .login-options {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .login-btn {
            width: 100%;
            padding: 16px 24px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
        }

        .login-btn:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 255, 255, 0.2);
        }

        .login-btn.primary {
            background: #ffffff;
            color: #000000;
            border-color: #ffffff;
            font-weight: 600;
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
        }

        .login-btn.primary:hover {
            background: #f0f0f0;
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.4);
        }

        .login-btn.email {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
        }

        .login-btn.google {
            background: rgba(66, 133, 244, 0.2);
            border-color: rgba(66, 133, 244, 0.6);
            color: #ffffff;
        }

        .login-btn.google:hover {
            border-color: #4285f4;
            background: rgba(66, 133, 244, 0.35);
        }

        .login-btn.apple {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
        }

        .divider {
            margin: 20px 0;
            text-align: center;
            position: relative;
            color: #cccccc;
            font-weight: 500;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
        }

        .divider span {
            background: rgba(0, 0, 0, 0.9);
            padding: 0 20px;
        }

        .last-login {
            color: #ffffff;
            font-size: 14px;
            text-align: center;
            margin: 20px 0;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .signup-link {
            text-align: center;
            margin-top: 30px;
            color: #cccccc;
            font-weight: 500;
        }

        .signup-link a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            padding-bottom: 2px;
        }

        .signup-link a:hover {
            border-bottom-color: #ffffff;
        }

        .success-message, .error-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.2);
            border: 2px solid rgba(34, 197, 94, 0.6);
            color: #4ade80;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 2px solid rgba(239, 68, 68, 0.6);
            color: #f87171;
        }

        /* Traditional form styles (hidden by default) */
        .traditional-form {
            display: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e5e5e5;
            font-size: 14px;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #cccccc;
            font-size: 16px;
        }

        .form-input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .form-input::placeholder {
            color: #aaaaaa;
        }

        .forgot-password {
            text-align: right;
            margin-top: 8px;
        }

        .forgot-password a {
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        }

        .forgot-password a:hover {
            border-bottom-color: #ffffff;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: #ffffff;
            border: none;
            border-radius: 12px;
            color: #000000;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.4);
            background: #f0f0f0;
        }

        .back-btn {
            background: none;
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: #ffffff;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .back-btn:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="decorative-circles">
                <div class="circle"></div>
                <div class="circle"></div>
                <div class="circle"></div>
            </div>
            <div class="nike-logo">
                <div class="swoosh-container">
                    <div class="swoosh">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg" 
                             alt="Nike Logo" 
                             style="width: 100%; height: 100%; object-fit: contain; filter: brightness(0) invert(1) drop-shadow(0 25px 50px rgba(255, 255, 255, 0.4));">
                    </div>
                </div>
                <div class="nike-text">NIKE</div>
            </div>
        </div>

        <div class="right-panel">
            <div class="login-form">
                <div class="header-info">
                    <div class="signing-into">Đăng nhập</div>
                    <div class="brand-name">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg" 
                             alt="Nike Logo" 
                             style="width: 30px; height: 18px; filter: brightness(0) invert(1);">
                        NIKE
                    </div>
                </div>

                <h1 class="main-title">Log into your account</h1>

                <!-- Success/Error Messages -->
                <?php if (isset($successMessage) && $successMessage): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors) && $errors): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($errors) ?>
                    </div>
                <?php endif; ?>

                <!-- Modern Login Options -->
                <div class="login-options" id="loginOptions">
                    <button class="login-btn primary" onclick="showTraditionalForm()">
                        <i class="fas fa-running"></i>
                        Login with Nike Account
                    </button>
                    
                    <button class="login-btn email" >
                        <i class="fas fa-envelope"></i>
                        Login with email
                    </button>
                    
                    <button class="login-btn google">
                        <i class="fab fa-google"></i>
                        Login with Google
                    </button>
                    
                    <button class="login-btn apple">
                        <i class="fab fa-facebook"></i>
                        Login with Facebook
                    </button>
                </div>

                <div class="last-login">
                    <i class="fas fa-history"></i>
                    You last logged in with Nike Account
                </div>

                <!-- Traditional Form (Hidden) -->
                <div class="traditional-form" id="traditionalForm">
                    <button class="back-btn" onclick="showLoginOptions()">
                        ← Back to login options
                    </button>
                    
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="email">User Name or Email</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-user"></i>
                                <input type="text" name="email" id="email" placeholder="User Name or Email" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" name="password" id="password" placeholder="Password" class="form-input" required>
                            </div>
                            <div class="forgot-password">
                                <a href="#">Forget Password?</a>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn">Login</button>
                    </form>
                </div>

                <div class="signup-link">
                    <span>Don't have an account?</span>
                    <a href="register.php">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTraditionalForm() {
            document.getElementById('loginOptions').style.display = 'none';
            document.getElementById('traditionalForm').style.display = 'block';
        }

        function showLoginOptions() {
            document.getElementById('loginOptions').style.display = 'flex';
            document.getElementById('traditionalForm').style.display = 'none';
        }

        // Add some interactive effects
        document.querySelectorAll('.login-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Add subtle parallax effect to logo
        document.addEventListener('mousemove', function(e) {
            const logo = document.querySelector('.nike-logo');
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            logo.style.transform = `translate(${x}px, ${y}px)`;
        });
    </script>
</body>
</html>
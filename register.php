<?php
include('dbconnect.php');

$message = '';
$status = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone = trim($_POST['phone']);

    if (empty($fullName) || empty($email) || empty($password) || empty($phone)) {
        $message = "Vui lòng điền đầy đủ thông tin";
        $status = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ";
        $status = "error";
    } else {
        // Kiểm tra email đã tồn tại
        $stmt = $conn->prepare("SELECT email FROM customer WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $message = "Email đã được sử dụng";
            $status = "error";
        } else {
            // Lưu mật khẩu nguyên bản (không mã hóa)
            $stmt = $conn->prepare("INSERT INTO customer (fullName, email, password, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullName, $email, $password, $phone);
            if ($stmt->execute()) {
                // Chuyển hướng sang login.php với thông báo thành công
                header("Location: login.php?message=Đăng ký thành công! Vui lòng đăng nhập.&status=success");
                exit();
            } else {
                $message = "Lỗi khi lưu dữ liệu: " . $stmt->error;
                $status = "error";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Đăng ký - Nike</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 25%, #2d2d2d 50%, #1a1a1a 75%, #000000 100%);
            background-size: 400% 400%;
            animation: gradientShift 10s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .card-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .nike-logo {
            filter: brightness(0) invert(1);
            transition: all 0.3s ease;
        }
        
        .nike-logo:hover {
            transform: scale(1.1) rotate(5deg);
        }
        
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-field {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(249, 250, 251, 0.8);
        }
        
        .input-field:focus {
            outline: none;
            border-color: #ff4500;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 4px rgba(255, 69, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: all 0.3s ease;
        }
        
        .input-field:focus + .input-icon {
            color: #ff4500;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #ff4500 0%, #ff6b35 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 69, 0, 0.3);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .submit-btn:hover::before {
            left: 100%;
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 69, 0, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: -2s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: -4s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: -1s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .success-message {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
        }
        
        .error-message {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
        }
        
        .form-container {
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .title-glow {
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4 relative">
    <!-- Floating background elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="form-container card-glass p-8 rounded-3xl shadow-2xl w-full max-w-md relative z-10">
        <!-- Logo Section -->
        <div class="flex justify-center mb-8">
            <div class="logo">
               
                    <img src="https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg" alt="Nike Logo">
            </div>
        </div>

        <!-- Title -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 title-glow mb-2">Tham gia Nike</h1>
            <p class="text-gray-600 font-medium">Tạo tài khoản để trải nghiệm đẳng cấp</p>
        </div>

        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl text-center font-medium shadow-lg <?php echo $status === 'success' ? 'success-message' : 'error-message'; ?>">
                <i class="fas <?php echo $status === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form action="register.php" method="POST" class="space-y-6">
            <!-- Full Name Field -->
            <div class="input-group">
                <input type="text" id="fullName" name="fullName" required
                       placeholder="Họ và tên"
                       class="input-field"
                       value="<?php echo isset($fullName) ? htmlspecialchars($fullName) : ''; ?>" />
                <i class="fas fa-user input-icon"></i>
            </div>

            <!-- Email Field -->
            <div class="input-group">
                <input type="email" id="email" name="email" required
                       placeholder="Địa chỉ email"
                       class="input-field"
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" />
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <!-- Password Field -->
            <div class="input-group">
                <input type="password" id="password" name="password" required
                       placeholder="Mật khẩu"
                       class="input-field" />
                <i class="fas fa-lock input-icon"></i>
            </div>

            <!-- Phone Field -->
            <div class="input-group">
                <input type="tel" id="phone" name="phone" required
                       placeholder="Số điện thoại"
                       class="input-field"
                       value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" />
                <i class="fas fa-phone input-icon"></i>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn w-full text-white py-4 px-6 rounded-xl font-semibold text-lg shadow-lg">
                <i class="fas fa-user-plus mr-2"></i>
                Tạo tài khoản Nike
            </button>
        </form>

        <!-- Login Link -->
        <div class="mt-8 text-center">
            <p class="text-gray-600 mb-4">Đã có tài khoản Nike?</p>
            <a href="login.php" class="inline-flex items-center text-orange-500 hover:text-orange-600 font-semibold transition-all hover:underline">
                <i class="fas fa-sign-in-alt mr-2"></i>
                Đăng nhập ngay
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>Bằng cách đăng ký, bạn đồng ý với</p>
            <a href="#" class="text-orange-500 hover:underline">Điều khoản & Chính sách</a>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus effects to inputs
            const inputs = document.querySelectorAll('.input-field');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('.input-icon').style.transform = 'translateY(-50%) scale(1.1)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('.input-icon').style.transform = 'translateY(-50%) scale(1)';
                });
            });

            // Add submit button loading effect
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.submit-btn');
            
            form.addEventListener('submit', function() {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>
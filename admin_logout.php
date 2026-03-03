<?php
session_start(); // Bắt đầu phiên

// Kiểm tra xem admin đã đăng nhập chưa
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Xử lý đăng xuất khi xác nhận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    session_unset(); // Xóa tất cả biến phiên
    session_destroy(); // Hủy phiên
    header("Location: login.php?logout=success"); // Chuyển hướng về trang đăng nhập
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nike Admin - Đăng xuất</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --card-bg: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .logout-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .logout-icon {
            font-size: 3rem;
            color: var(--danger-color);
            margin-bottom: 1rem;
        }

        .logout-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .logout-message {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .logout-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        @media (max-width: 480px) {
            .logout-container {
                margin: 1rem;
                padding: 1.5rem;
            }

            .logout-title {
                font-size: 1.25rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <i class="fas fa-sign-out-alt logout-icon"></i>
        <h2 class="logout-title">Xác nhận đăng xuất</h2>
        <p class="logout-message">Bạn có chắc chắn muốn đăng xuất khỏi hệ thống quản trị?</p>
        <form method="POST" class="logout-actions">
            <button type="submit" name="confirm_logout" class="btn btn-danger">
                <i class="fas fa-check"></i> Xác nhận
            </button>
            <a href="admin.php" class="btn btn-primary">
                <i class="fas fa-times"></i> Hủy
            </a>
        </form>
    </div>
</body>
</html>
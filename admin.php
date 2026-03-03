<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra quyền admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$errors = '';
$success = '';
include('dbconnect.php');

// Xử lý các action
$action = $_GET['action'] ?? 'dashboard';

// Xử lý thêm sản phẩm
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $image = $_FILES['image']['name'] ?? '';

    if (empty($name) || $price <= 0 || $quantity <= 0 || empty($category)) {
        $errors = "Vui lòng điền đầy đủ thông tin hợp lệ.";
    } else {
        $target_dir = "img/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . basename($image);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK && in_array($_FILES['image']['type'], $allowed_types) && move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $sql = "INSERT INTO products (name, price, category, stock, description, image, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            if ($stmt && $stmt->bind_param("sdssss", $name, $price, $category, $quantity, $description, $target_file) && $stmt->execute()) {
                $success = "Thêm sản phẩm thành công!";
                $stmt->close();
            } else {
                $errors = "Lỗi thêm sản phẩm: " . ($stmt ? $stmt->error : $conn->error);
            }
        } else {
            $errors = "Lỗi upload ảnh hoặc định dạng không hợp lệ.";
        }
    }
}

// Xử lý cập nhật sản phẩm
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($id && !empty($name) && $price > 0 && $quantity >= 0) {
        $sql = "UPDATE products SET name=?, price=?, category=?, stock=?, description=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt && $stmt->bind_param("sdsssi", $name, $price, $category, $quantity, $description, $status, $id) && $stmt->execute()) {
            $success = "Cập nhật sản phẩm thành công!";
            $stmt->close();
        } else {
            $errors = "Lỗi cập nhật sản phẩm.";
        }
    }
}

// Xử lý xóa sản phẩm
if (isset($_GET['delete_product'])) {
    $id = filter_input(INPUT_GET, 'delete_product', FILTER_VALIDATE_INT);
    if ($id) {
        $sql = "UPDATE products SET status='inactive' WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt && $stmt->bind_param("i", $id) && $stmt->execute()) {
            $success = "Xóa sản phẩm thành công!";
            $stmt->close();
        }
    }
}

// Xử lý cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order_status'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    if ($order_id && $status) {
        $sql = "UPDATE bill_guest SET status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt && $stmt->bind_param("si", $status, $order_id) && $stmt->execute()) {
            $success = "Cập nhật trạng thái đơn hàng thành công!";
            $stmt->close();
        }
    }
}

// Lấy dữ liệu thống kê
$currentMonth = date('Y-m');

// Doanh thu tháng này
$sql = "SELECT SUM(total_price) as total_revenue FROM bill_guest WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total_revenue'] ?? 0;
$stmt->close();

// Đơn hàng mới tháng này
$sql = "SELECT COUNT(*) as new_orders FROM bill_guest WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$new_orders = $stmt->get_result()->fetch_assoc()['new_orders'] ?? 0;
$stmt->close();

// Khách hàng mới tháng này
$sql = "SELECT COUNT(*) as new_customers FROM customer WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$new_customers = $stmt->get_result()->fetch_assoc()['new_customers'] ?? 0;
$stmt->close();

// Tổng sản phẩm tồn kho
$sql = "SELECT SUM(stock) as total_stock FROM products WHERE status = 'active'";
$result = $conn->query($sql);
$total_stock = $result->fetch_assoc()['total_stock'] ?? 0;

// Dữ liệu cho các trang khác nhau
switch ($action) {
    case 'products':
        $sql = "SELECT * FROM products ORDER BY created_at DESC";
        $products_result = $conn->query($sql);
        break;
        
    case 'orders':
        $sql = "SELECT bg.*, c.fullName, c.email 
                FROM bill_guest bg 
                LEFT JOIN customer c ON bg.customer_id = c.customer_id 
                ORDER BY bg.created_at DESC";
        $orders_result = $conn->query($sql);
        break;
        
    case 'customers':
        $sql = "SELECT * FROM customer ORDER BY created_at DESC";
        $customers_result = $conn->query($sql);
        break;
        
    default: // dashboard
        // Đơn hàng gần đây
        $sql = "SELECT bg.id, bg.created_at, bg.total_price, bg.payment_method, bg.status, c.fullName 
                FROM bill_guest bg 
                LEFT JOIN customer c ON bg.customer_id = c.customer_id 
                ORDER BY bg.created_at DESC LIMIT 5";
        $recent_orders_result = $conn->query($sql);
        $recent_orders = [];
        if ($recent_orders_result->num_rows > 0) {
            while ($row = $recent_orders_result->fetch_assoc()) {
                $recent_orders[] = $row;
            }
        }
        
        // Dữ liệu biểu đồ doanh thu
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_price) as revenue 
                FROM bill_guest 
                GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                ORDER BY month DESC LIMIT 6";
        $chart_result = $conn->query($sql);
        $months = [];
        $revenues = [];
        if ($chart_result->num_rows > 0) {
            while ($row = $chart_result->fetch_assoc()) {
                $months[] = $row['month'];
                $revenues[] = $row['revenue'];
            }
        }
        $months = array_reverse($months);
        $revenues = array_reverse($revenues);
        break;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nike Admin - Quản trị hệ thống</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            transition: transform 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logo i {
            color: var(--primary-color);
        }

        .nav-menu {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.75rem;
            font-weight: chiều dài 600;
            text-transform: uppercase;
            color: var(--text-secondary);
            letter-spacing: 0.05em;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Top Bar */
        .top-bar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s ease;
            display: none;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card-icon.primary { background: rgba(59, 130, 246, 0.1); color: var(--primary-color); }
        .stat-card-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .stat-card-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
        .stat-card-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background: var(--dark-bg);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-secondary { background: var(--border-color); color: var(--text-primary); }

        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.active { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .status-badge.inactive { background: rgba(107, 114, 128, 0.1); color: #9ca3af; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
        .status-badge.completed { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .status-badge.cancelled { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }

        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 1rem;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--text-primary);
        }

        .quick-action:hover {
            transform: translateY(-2px);
            color: var(--primary-color);
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 1rem;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .product-price {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 1rem;
            }

            .content-area {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-running"></i>
                Nike Admin
            </div>
        </div>
        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">Tổng quan</div>
                <div class="nav-item">
                    <a href="?action=dashboard" class="nav-link <?php echo $action == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="?action=analytics" class="nav-link <?php echo $action == 'analytics' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Thống kê
                    </a>
                </div>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Quản lý</div>
                <div class="nav-item">
                    <a href="?action=products" class="nav-link <?php echo $action == 'products' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i> Sản phẩm 
                        <span class="nav-badge"><?php echo $total_stock; ?></span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="?action=orders" class="nav-link <?php echo $action == 'orders' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Đơn hàng 
                        <span class="nav-badge"><?php echo $new_orders; ?></span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="?action=customers" class="nav-link <?php echo $action == 'customers' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Khách hàng 
                        <span class="nav-badge"><?php echo $new_customers; ?></span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="?action=inventory" class="nav-link <?php echo $action == 'inventory' ? 'active' : ''; ?>">
                        <i class="fas fa-warehouse"></i> Tồn kho
                    </a>
                </div>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Hệ thống</div>
                <div class="nav-item">
                    <a href="?action=settings" class="nav-link <?php echo $action == 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Cài đặt
                    </a>
                </div>
                <div class="nav-item">
                    <a href="admin_logout.php" class="nav-link" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?')">
                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="left-section">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb">
                    <i class="fas fa-home"></i>
                    <span>Admin</span>
                    <i class="fas fa-chevron-right"></i>
                    <span><?php 
                        switch($action) {
                            case 'products': echo 'Sản phẩm'; break;
                            case 'orders': echo 'Đơn hàng'; break;
                            case 'customers': echo 'Khách hàng'; break;
                            case 'inventory': echo 'Tồn kho'; break;
                            case 'settings': echo 'Cài đặt'; break;
                            default: echo 'Dashboard';
                        }
                    ?></span>
                </div>
            </div>
            <div class="user-menu">
                <div class="user-avatar">A</div>
                <span>Admin</span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php switch($action): 
                case 'dashboard': ?>
                    <!-- Dashboard Content -->
                    <div class="page-header">
                        <h1 class="page-title">Dashboard</h1>
                        <p class="page-subtitle">Tổng quan hệ thống quản lý Nike Store</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-card-icon success">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($total_revenue); ?>đ</div>
                            <div class="stat-label">Doanh thu tháng này</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-icon primary">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-value"><?php echo $new_orders; ?></div>
                            <div class="stat-label">Đơn hàng mới</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-icon warning">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo $new_customers; ?></div>
                            <div class="stat-label">Khách hàng mới</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-icon danger">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_stock; ?></div>
                            <div class="stat-label">Sản phẩm tồn kho</div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <a href="?action=products" class="quick-action">
                            <i class="fas fa-plus-circle"></i>
                            <div>Thêm sản phẩm</div>
                        </a>
                        <a href="?action=orders" class="quick-action">
                            <i class="fas fa-eye"></i>
                            <div>Xem đơn hàng</div>
                        </a>
                        <a href="?action=customers" class="quick-action">
                            <i class="fas fa-user-plus"></i>
                            <div>Quản lý khách hàng</div>
                        </a>
                        <a href="?action=settings" class="quick-action">
                            <i class="fas fa-cogs"></i>
                            <div>Cài đặt hệ thống</div>
                        </a>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                        <!-- Revenue Chart -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Biểu đồ doanh thu</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Đơn hàng gần đây</h3>
                                <a href="?action=orders" class="btn btn-primary btn-sm">Xem tất cả</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_orders)): ?>
                                    <p class="text-center" style="color: var(--text-secondary); padding: 2rem;">
                                        Chưa có đơn hàng nào
                                    </p>
                                <?php else: ?>
                                    <div style="space-y: 1rem;">
                                        <?php foreach ($recent_orders as $order): ?>
                                            <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                    <strong>#<?php echo $order['id']; ?></strong>
                                                    <span class="status-badge <?php echo $order['status']; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </div>
                                                <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                                    <?php echo $order['fullName'] ?? 'Khách vãng lai'; ?><br>
                                                    <?php echo number_format($order['total_price']); ?>đ<br>
                                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php break; 
                case 'products': ?>
                    <!-- Products Management -->
                    <div class="page-header">
                        <h1 class="page-title">Quản lý sản phẩm</h1>
                        <button class="btn btn-primary" onclick="showAddProductModal()">
                            <i class="fas fa-plus"></i> Thêm sản phẩm mới
                        </button>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Danh sách sản phẩm</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Hình ảnh</th>
                                            <th>Tên sản phẩm</th>
                                            <th>Giá</th>
                                            <th>Danh mục</th>
                                            <th>Tồn kho</th>
                                            <th>Trạng thái</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($products_result && $products_result->num_rows > 0): ?>
                                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $product['id']; ?></td>
                                                    <td>
                                                        <?php if ($product['image']): ?>
                                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                        <?php else: ?>
                                                            <div style="width: 50px; height: 50px; background: var(--border-color); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="fas fa-image" style="color: var(--text-secondary);"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo number_format($product['price']); ?>đ</td>
                                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                                    <td><?php echo $product['stock']; ?></td>
                                                    <td>
                                                        <span class="status-badge <?php echo $product['status']; ?>">
                                                            <?php echo $product['status'] == 'active' ? 'Hoạt động' : 'Ngừng bán'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-secondary btn-sm" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="?action=products&delete_product=<?php echo $product['id']; ?>" 
                                                           class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                                    Chưa có sản phẩm nào
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php break; 
                case 'orders': ?>
                    <!-- Orders Management -->
                    <div class="page-header">
                        <h1 class="page-title">Quản lý đơn hàng</h1>
                        <p class="page-subtitle">Theo dõi và xử lý các đơn hàng</p>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Danh sách đơn hàng</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Mã đơn</th>
                                            <th>Khách hàng</th>
                                            <th>Email</th>
                                            <th>Tổng tiền</th>
                                            <th>Thanh toán</th>
                                            <th>Trạng thái</th>
                                            <th>Ngày đặt</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>#<?php echo $order['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($order['fullName'] ?? 'Khách vãng lai'); ?></td>
                                                    <td><?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?></td>
                                                    <td><?php echo number_format($order['total_price']); ?>đ</td>
                                                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                                    <td>
                                                        <span class="status-badge <?php echo $order['status']; ?>">
                                                            <?php 
                                                                switch($order['status']) {
                                                                    case 'pending': echo 'Chờ xử lý'; break;
                                                                    case 'processing': echo 'Đang xử lý'; break;
                                                                    case 'shipped': echo 'Đã giao'; break;
                                                                    case 'completed': echo 'Hoàn thành'; break;
                                                                    case'số lượng cancelled': echo 'Đã hủy'; break;
                                                                    default: echo ucfirst($order['status']);
                                                                }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-primary btn-sm" onclick="updateOrderStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                                    Chưa có đơn hàng nào
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php break; 
                case 'customers': ?>
                    <!-- Customers Management -->
                    <div class="page-header">
                        <h1 class="page-title">Quản lý khách hàng</h1>
                        <p class="page-subtitle">Thông tin khách hàng và lịch sử mua hàng</p>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Danh sách khách hàng</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Họ tên</th>
                                            <th>Email</th>
                                            <th>Số điện thoại</th>
                                            <th>Địa chỉ</th>
                                            <th>Ngày đăng ký</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($customers_result && $customers_result->num_rows > 0): ?>
                                            <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $customer['customer_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($customer['fullName']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['Address'] ?? 'N/A'); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-primary btn-sm">
                                                            <i class="fas fa-eye"></i> Xem
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                                    Chưa có khách hàng nào
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php break; 
                default: ?>
                    <div class="page-header">
                        <h1 class="page-title">Tính năng đang phát triển</h1>
                        <p class="page-subtitle">Tính năng này sẽ được cập nhật trong phiên bản sau</p>
                    </div>
                <?php break; 
            endswitch; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal" id="addProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Thêm sản phẩm mới</h3>
                <button class="modal-close" onclick="hideAddProductModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tên sản phẩm</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Giá (VNĐ)</label>
                            <input type="number" name="price" class="form-control" min="0" step="1000" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Danh mục</label>
                            <select name="category" class="form-control" required>
                                <option value="">Chọn danh mục</option>
                                <option value="Giày thể thao">Giày thể thao</option>
                                <option value="Giày chạy bộ">Giày chạy bộ</option>
                                <option value="Giày bóng đá">Giày bóng đá</option>
                                <option value="Phụ kiện">Phụ kiện</option>
                                <option value="Quần áo">Quần áo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Số lượng</label>
                            <input type="number" name="quantity" class="form-control" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hình ảnh</label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideAddProductModal()">Hủy</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Thêm sản phẩm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal" id="editProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Chỉnh sửa sản phẩm</h3>
                <button class="modal-close" onclick="hideEditProductModal()">&times;</button>
            </div>
            <form method="POST" id="editProductForm">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tên sản phẩm</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Giá (VNĐ)</label>
                            <input type="number" name="price" id="edit_price" class="form-control" min="0" step="1000" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Danh mục</label>
                            <select name="category" id="edit_category" class="form-control" required>
                                <option value="Giày thể thao">Giày thể thao</option>
                                <option value="Giày chạy bộ">Giày chạy bộ</option>
                                <option value="Giày bóng đá">Giày bóng đá</option>
                                <option value="Phụ kiện">Phụ kiện</option>
                                <option value="Quần áo">Quần áo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Số lượng</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Ngừng bán</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideEditProductModal()">Hủy</button>
                    <button type="submit" name="update_product" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Order Status Modal -->
    <div class="modal" id="updateOrderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cập nhật trạng thái đơn hàng</h3>
                <button class="modal-close" onclick="hideUpdateOrderModal()">&times;</button>
            </div>
            <form method="POST" id="updateOrderForm">
                <input type="hidden" name="order_id" id="update_order_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Trạng thái đơn hàng</label>
                        <select name="status" id="update_order_status" class="form-control" required>
                            <option value="pending">Chờ xử lý</option>
                            <option value="processing">Đang xử lý</option>
                            <option value="shipped">Đã giao</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideUpdateOrderModal()">Hủy</button>
                    <button type="submit" name="update_order_status" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Chart.js Configuration
        <?php if ($action == 'dashboard'): ?>
        const ctx = document.getElementById('revenueChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: <?php echo json_encode($revenues); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#f8fafc'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#cbd5e1',
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
                                }
                            },
                            grid: {
                                color: '#475569'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#cbd5e1'
                            },
                            grid: {
                                color: '#475569'
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        sidebarToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Click outside to close sidebar on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 1024 && 
                !sidebar.contains(e.target) && 
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Modal Functions
        function showAddProductModal() {
            document.getElementById('addProductModal').classList.add('show');
        }

        function hideAddProductModal() {
            document.getElementById('addProductModal').classList.remove('show');
        }

        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_category').value = product.category;
            document.getElementById('edit_quantity').value = product.stock;
            document.getElementById('edit_status').value = product.status;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('editProductModal').classList.add('show');
        }

        function hideEditProductModal() {
            document.getElementById('editProductModal').classList.remove('show');
        }

        function updateOrderStatus(orderId, currentStatus) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('update_order_status').value = currentStatus;
            document.getElementById('updateOrderModal').classList.add('show');
        }

        function hideUpdateOrderModal() {
            document.getElementById('updateOrderModal').classList.remove('show');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
                }
            });
        });

        // Price formatting
        document.querySelectorAll('input[type="number"][name="price"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.value = Math.round(this.value / 1000) * 1000;
                }
            });
        });

        // Image preview for product forms
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Create preview if doesn't exist
                        let preview = input.parentNode.querySelector('.image-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.className = 'image-preview';
                            preview.style.cssText = 'margin-top: 10px; text-align: center;';
                            input.parentNode.appendChild(preview);
                        }
                        preview.innerHTML = `<img src="${e.target.result}" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid var(--border-color);">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        // Search functionality
        function addSearchBar() {
            const tables = document.querySelectorAll('.table');
            tables.forEach(table => {
                const container = table.closest('.card-body');
                if (container && !container.querySelector('.search-bar')) {
                    const searchBar = document.createElement('div');
                    searchBar.className = 'search-bar';
                    searchBar.style.cssText = 'margin-bottom: 1rem;';
                    searchBar.innerHTML = `
                        <input type="text" placeholder="Tìm kiếm..." 
                               style="width: 300px; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-primary);"
                               oninput="searchTable(this, '${table.className}')">
                    `;
                    container.insertBefore(searchBar, table.parentNode);
                }
            });
        }

        function searchTable(input, tableClass) {
            const searchTerm = input.value.toLowerCase();
            const table = input.closest('.card-body').querySelector('.table');
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Add search bars on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addSearchBar);
        } else {
            addSearchBar();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.search-bar input');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }

            // Ctrl/Cmd + N for new product (only on products page)
            if ((e.ctrlKey || e.metaKey) && e.key === 'n' && window.location.search.includes('products')) {
                e.preventDefault();
                showAddProductModal();
            }
        });

        // Status badge colors update
        document.querySelectorAll('.status-badge').forEach(badge => {
            const status = badge.className.split(' ').pop();
            badge.addEventListener('click', function() {
                if (this.closest('tr')) {
                    const row = this.closest('tr');
                    row.style.backgroundColor = 'var(--primary-color)';
                    row.style.opacity = '0.8';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                        row.style.opacity = '';
                    }, 200);
                }
            });
        });

        // Quick stats update animation
        function animateStatsCards() {
            document.querySelectorAll('.stat-value').forEach((stat, index) => {
                const finalValue = stat.textContent;
                const numericValue = parseInt(finalValue.replace(/[^\d]/g, ''));
                
                if (!isNaN(numericValue)) {
                    stat.textContent = '0';
                    let current = 0;
                    const increment = numericValue / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= numericValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            const formatted = Math.floor(current).toLocaleString('vi-VN');
                            stat.textContent = finalValue.includes('đ') ? formatted + 'đ' : formatted;
                        }
                    }, 30);
                }
            });
        }

        // Run animation on dashboard load
        if (window.location.search.includes('dashboard') || window.location.search === '') {
            setTimeout(animateStatsCards, 500);
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 400px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            `;

            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            notification.style.backgroundColor = colors[type] || colors.info;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Export functionality
        function exportToCSV(tableId, filename) {
            const table = document.querySelector(tableId);
            if (!table) return;

            const rows = table.querySelectorAll('tr');
            const csvContent = Array.from(rows).map(row => {
                const cells = row.querySelectorAll('th, td');
                return Array.from(cells).map(cell => {
                    let text = cell.textContent.trim();
                    return `"${text.replace(/"/g, '""')}"`;
                }).join(',');
            }).join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
        }

        // Add export buttons to tables
        document.querySelectorAll('.card-header h3').forEach(title => {
            if (title.textContent.includes('Danh sách')) {
                const exportBtn = document.createElement('button');
                exportBtn.className = 'btn btn-secondary btn-sm';
                exportBtn.innerHTML = '<i class="fas fa-download"></i> Xuất CSV';
                exportBtn.style.marginLeft = 'auto';
                
                exportBtn.addEventListener('click', () => {
                    const table = title.closest('.card').querySelector('.table');
                    const filename = title.textContent.toLowerCase().replace(/\s+/g, '_') + '.csv';
                    exportToCSV('.table', filename);
                });

                title.parentNode.appendChild(exportBtn);
            }
        });

        // Responsive table handling
        function makeTablesResponsive() {
            document.querySelectorAll('.table-container').forEach(container => {
                const table = container.querySelector('table');
                if (table && window.innerWidth <= 768) {
                    table.style.fontSize = '0.875rem';
                    // Hide less important columns on mobile
                    const headers = table.querySelectorAll('th');
                    const rows = table.querySelectorAll('tbody tr');
                    
                    headers.forEach((header, index) => {
                        if (header.textContent.includes('Hình ảnh') || 
                            header.textContent.includes('Mô tả') ||
                            header.textContent.includes('Ngày')) {
                            header.style.display = 'none';
                            rows.forEach(row => {
                                const cell = row.children[index];
                                if (cell) cell.style.display = 'none';
                            });
                        }
                    });
                }
            });
        }

        window.addEventListener('resize', makeTablesResponsive);
        makeTablesResponsive();

        // Auto-refresh for dashboard
        if (window.location.search.includes('dashboard') || window.location.search === '') {
            setInterval(() => {
                // Only refresh if user is still on the page
                if (document.visibilityState === 'visible') {
                    fetch(window.location.href)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const newDoc = parser.parseFromString(html, 'text/html');
                            
                            // Update stats cards
                            document.querySelectorAll('.stat-value').forEach((stat, index) => {
                                const newStat = newDoc.querySelectorAll('.stat-value')[index];
                                if (newStat && stat.textContent !== newStat.textContent) {
                                    stat.style.animation = 'pulse 0.5s';
                                    stat.textContent = newStat.textContent;
                                }
                            });
                        })
                        .catch(console.error);
                }
            }, 30000); // Refresh every 30 seconds
        }

        console.log('Nike Store Admin Panel loaded successfully! 🚀');
    </script>

    <style>
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .notification {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        /* Print styles */
        @media print {
            .sidebar, .top-bar, .modal, .btn, .quick-actions {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 1rem !important;
            }
            
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
        }

        /* High contrast mode */
        @media (prefers-contrast: high) {
            :root {
                --primary-color: #0066cc;
                --secondary-color: #666666;
                --background-color: #000000;
                --card-bg: #ffffff;
                --text-primary: #000000;
                --text-secondary: #333333;
                --border-color: #999999;
            }
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus indicators for accessibility */
        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Enhanced mobile experience */
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .modal-content {
                width: 95vw;
                margin: 0.5rem;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .btn {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
</body>
</html>
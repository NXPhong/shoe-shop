<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra xem user đã đăng nhập chưa
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "long";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Kết nối thất bại: " . $e->getMessage());
}

// Lấy thông tin customer dựa trên email trong session
$customer_email = $_SESSION['email'];

try {
    // Tìm customer_id dựa trên email
    $stmt_customer = $pdo->prepare("SELECT * FROM customer WHERE email = ?");
    $stmt_customer->execute([$customer_email]);
    $current_customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    if (!$current_customer) {
        // Nếu không tìm thấy customer, logout và redirect về login
        session_unset();
        session_destroy();
        header("Location: login.php?message=Tài khoản không tồn tại&status=error");
        exit();
    }

    $customer_id = $current_customer['customer_id'];

    // Lấy tất cả customers (có thể dùng cho admin)
    $stmt_customers = $pdo->query("SELECT * FROM customer ORDER BY customer_id DESC");
    $customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

    // Lấy đơn hàng của customer hiện tại
    $stmt_orders = $pdo->prepare("SELECT bg.*, c.gh_name, c.price, c.img FROM bill_guest bg LEFT JOIN cart c ON bg.cart_id = c.gh_id WHERE bg.customer_id = ? ORDER BY bg.created_at DESC");
    $stmt_orders->execute([$customer_id]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // Lấy sản phẩm từ các bảng
    $stmt_moi = $pdo->query("SELECT id, ten_nb as name, gia_nb as price, hanh_nb as image, 'Mới & Nổi Bật' as category FROM moi");
    $products_moi = $stmt_moi->fetchAll(PDO::FETCH_ASSOC);

    $stmt_nam = $pdo->query("SELECT id, ten_nam as name, gia_nam as price, hanh_nam as image, 'Giày Nam' as category FROM nam");
    $products_nam = $stmt_nam->fetchAll(PDO::FETCH_ASSOC);

    $stmt_nu = $pdo->query("SELECT id, ten_nu as name, gia_nu as price, hanh_nu as image, 'Giày Nữ' as category FROM nu");
    $products_nu = $stmt_nu->fetchAll(PDO::FETCH_ASSOC);

    $products = array_merge($products_moi, $products_nam, $products_nu);

    // Lấy giỏ hàng của customer hiện tại
    $stmt_cart = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ?");
    $stmt_cart->execute([$customer_id]);
    $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Lỗi truy vấn: " . $e->getMessage(), 3, 'errors.log');
    echo "Lỗi truy vấn, vui lòng kiểm tra logs.";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài Khoản Nike - Just Do It</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            color: #333333;
            min-height: 100vh;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #ff6b00;
            text-shadow: 0 2px 10px rgba(255, 107, 0, 0.3);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: #333333;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: #ff6b00;
            transform: translateY(-2px);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #ff6b00, #ff8c42);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .account-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 2rem;
            background: linear-gradient(135deg, rgba(255, 107, 0, 0.05), rgba(255, 140, 66, 0.02));
            border-radius: 20px;
            border: 1px solid rgba(255, 107, 0, 0.1);
            box-shadow: 0 10px 40px rgba(255, 107, 0, 0.1);
        }

        .account-header h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #ff6b00, #ff8c42);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .account-header p {
            font-size: 1.2rem;
            color: #666666;
        }

        .account-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .profile-sidebar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            height: fit-content;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff6b00, #ff8c42);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.3);
        }

        .profile-info {
            text-align: center;
        }

        .profile-info h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #ff6b00;
        }

        .profile-info .member-since {
            color: #666666;
            margin-bottom: 2rem;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 1rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #333333;
            text-decoration: none;
            padding: 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 107, 0, 0.1);
            color: #ff6b00;
            transform: translateX(10px);
            box-shadow: 0 5px 20px rgba(255, 107, 0, 0.2);
        }

        .main-content {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .section {
            margin-bottom: 3rem;
        }

        .section h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #ff6b00;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            color: #ff6b00;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 1rem;
            border: 2px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            color: #333333;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b00;
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.2);
            background: white;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b00, #ff8c42);
            color: white;
            box-shadow: 0 10px 30px rgba(255, 107, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 107, 0, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #ff6b00;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666666;
        }

        .order-history {
            display: grid;
            gap: 1.5rem;
        }

        .order-item {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .order-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-id {
            font-weight: bold;
            color: #ff6b00;
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .order-details {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1rem;
            align-items: center;
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            display: block;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .product-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .product-card .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .product-card h4 {
            color: #333333;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .product-card .category {
            color: #666666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .product-card .price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #ff6b00;
        }

        .customers-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .customer-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .cart-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .cart-item {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 107, 0, 0.05);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 150px;
            height: 150px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            width: 80px;
            height: 80px;
            bottom: 30%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .back-button {
            margin-bottom: 2rem;
        }

        .back-button a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #ff6b00;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-button a:hover {
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .account-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .nav-links {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="container">
        <!-- Back button to home -->
        <div class="back-button">
            <a href="index.php">← Quay lại trang chủ</a>
        </div>

        <div class="account-header">
            <h1>Dashboard Nike</h1>
            <p>Xin chào, <?php echo htmlspecialchars($current_customer['fullName'] ?? 'Khách'); ?>! Quản lý đơn hàng và giỏ hàng của bạn.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($orders); ?></div>
                <div class="stat-label">Đơn Hàng</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($products); ?></div>
                <div class="stat-label">Sản Phẩm</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($cart_items); ?></div>
                <div class="stat-label">Trong Giỏ</div>
            </div>
        </div>

        <div class="account-content">
            <div class="profile-sidebar">
                <div class="profile-pic"><?php echo strtoupper(substr($current_customer['fullName'] ?? 'U', 0, 1)); ?></div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($current_customer['fullName'] ?? 'User'); ?></h2>
                    <p class="member-since">ID: #<?php echo htmlspecialchars($current_customer['customer_id'] ?? '0'); ?></p>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="#" class="menu-item active" data-section="profile">👤 Thông Tin</a></li>
                    <li><a href="#" class="menu-item" data-section="orders">📦 Đơn Hàng</a></li>
                    <li><a href="#" class="menu-item" data-section="products">👟 Sản Phẩm</a></li>
                    <li><a href="#" class="menu-item" data-section="cart">🛒 Giỏ Hàng</a></li>
                    <li><a href="#" class="menu-item" data-section="customers">👥 Khách Hàng</a></li>
                    <li><a href="index.php?go=logout" style="color: #dc3545;">🚪 Đăng Xuất</a></li>
                </ul>
            </div>

            <div class="main-content">
                <!-- Profile Section -->
                <div class="section" id="profileSection">
                    <h3>👤 Thông Tin Tài Khoản</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Họ Tên</label>
                            <input type="text" value="<?php echo htmlspecialchars($current_customer['fullName'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="text" value="<?php echo htmlspecialchars($current_customer['email'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Số Điện Thoại</label>
                            <input type="text" value="<?php echo htmlspecialchars($current_customer['phone'] ?? 'Chưa cập nhật'); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Địa Chỉ</label>
                            <input type="text" value="<?php echo htmlspecialchars(($current_customer['Address'] ?? '') . (($current_customer['city'] ?? '') ? ', ' . $current_customer['city'] : '')); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>ID Khách Hàng</label>
                            <input type="text" value="#<?php echo htmlspecialchars($current_customer['customer_id'] ?? '0'); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Trạng Thái</label>
                            <input type="text" value="Hoạt động bình thường ✅" readonly>
                        </div>
                    </div>
                </div>

                <!-- Orders Section -->
                <div class="section" id="ordersSection" style="display: none;">
                    <h3>📦 Đơn Hàng Của Bạn</h3>
                    <div class="order-history">
                        <?php if (empty($orders)): ?>
                            <div style="text-align: center; padding: 2rem; color: #666;">
                                <p>Bạn chưa có đơn hàng nào.</p>
                                <a href="index.php" class="btn btn-primary" style="margin-top: 1rem;">Mua sắm ngay</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <span class="order-id">#NK<?php echo htmlspecialchars($order['id']); ?></span>
                                    <span class="order-status">Đã Giao</span>
                                </div>
                                <div class="order-details">
                                    <?php
                                    $orderImage = (!empty($order['img']) && file_exists($order['img'])) ? htmlspecialchars($order['img']) : 'img/placeholder.jpg';
                                    ?>
                                    <img src="<?php echo $orderImage; ?>" alt="<?php echo htmlspecialchars($order['gh_name'] ?? 'Sản phẩm'); ?>" class="product-image" loading="lazy" onerror="this.src='img/placeholder.jpg';">
                                    <div>
                                        <h4><?php echo htmlspecialchars($order['gh_name'] ?? 'Sản phẩm không xác định'); ?></h4>
                                        <p>Email: <?php echo htmlspecialchars($order['email'] ?? ''); ?></p>
                                        <p>SĐT: <?php echo htmlspecialchars($order['phone'] ?? ''); ?></p>
                                        <p>Ngày đặt: <?php echo isset($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : 'Không xác định'; ?></p>
                                    </div>
                                    <div>
                                        <strong><?php echo number_format($order['total_price'] ?? 0, 0, ',', '.'); ?> VNĐ</strong>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Products Section -->
                <div class="section" id="productsSection" style="display: none;">
                    <h3>👟 Sản Phẩm Nike</h3>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php
                            $imagePath = (!empty($product['image']) && file_exists($product['image'])) ? htmlspecialchars($product['image']) : 'img/placeholder.jpg';
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" loading="lazy" onerror="this.src='img/placeholder.jpg';">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p class="category"><?php echo htmlspecialchars($product['category']); ?></p>
                            <div class="price"><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cart Section -->
                <div class="section" id="cartSection" style="display: none;">
                    <h3>🛒 Giỏ Hàng Của Bạn</h3>
                    <div class="cart-grid">
                        <?php if (empty($cart_items)): ?>
                            <div style="text-align: center; padding: 2rem; color: #666;">
                                <p>Giỏ hàng của bạn đang trống.</p>
                                <a href="index.php" class="btn btn-primary" style="margin-top: 1rem;">Mua sắm ngay</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <div class="order-header">
                                    <span class="order-id">#GH<?php echo htmlspecialchars($item['gh_id']); ?></span>
                                    <span class="order-status">Trong Giỏ</span>
                                    </div>
                                <div class="order-details">
                                    <?php
                                    $cartImage = !empty($item['img']) && file_exists($item['img']) ? htmlspecialchars($item['img']) : 'img/placeholder.jpg';
                                    ?>
                                    <img src="<?php echo $cartImage; ?>" alt="<?php echo htmlspecialchars($item['gh_name'] ?? 'Sản phẩm'); ?>" class="product-image" loading="lazy">
                                    <div>
                                        <h4><?php echo htmlspecialchars($item['gh_name'] ?? 'Sản phẩm không xác định'); ?></h4>
                                        <p>Số lượng: <?php echo htmlspecialchars($item['quantity'] ?? 1); ?></p>
                                        <p>Kích cỡ: <?php echo htmlspecialchars($item['size'] ?? 'M'); ?></p>
                                        <p>Ngày thêm: <?php echo date('d/m/Y H:i', strtotime($item['created_at'] ?? 'now')); ?></p>
                                    </div>
                                    <div>
                                        <strong><?php echo number_format($item['price'], 0, ',', '.'); ?> VNĐ</strong>
                                        <br><small>Tổng: <?php echo number_format($item['price'] * ($item['quantity'] ?? 1), 0, ',', '.'); ?> VNĐ</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Cart Total -->
                            <div class="cart-item" style="background: linear-gradient(135deg, rgba(255, 107, 0, 0.1), rgba(255, 140, 66, 0.05)); border: 2px solid rgba(255, 107, 0, 0.2);">
                                <div class="order-header">
                                    <span style="font-size: 1.2rem; font-weight: bold;">💰 Tổng Giỏ Hàng</span>
                                    <span style="font-size: 1.5rem; font-weight: bold; color: #ff6b00;">
                                        <?php 
                                        $total = 0;
                                        foreach ($cart_items as $item) {
                                            $total += $item['price'] * ($item['quantity'] ?? 1);
                                        }
                                        echo number_format($total, 0, ',', '.') . ' VNĐ';
                                        ?>
                                    </span>
                                </div>
                                <div style="text-align: center; margin-top: 1rem;">
                                    <a href="checkout.php" class="btn btn-primary">Thanh Toán Ngay</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Customers Section (if user is admin) -->
                <?php if (isset($customers) && count($customers) > 0): ?>
                <div class="section" id="customersSection" style="display: none;">
                    <h3>👥 Danh Sách Khách Hàng</h3>
                    <div class="customers-grid">
                        <?php foreach ($customers as $customer): ?>
                        <div class="customer-card">
                            <div class="order-header">
                                <span class="order-id">#KH<?php echo htmlspecialchars($customer['customer_id']); ?></span>
                                <span class="order-status" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6;">Khách Hàng</span>
                            </div>
                            <div style="margin-top: 1rem;">
                                <h4><?php echo htmlspecialchars($customer['fullName'] ?? 'Không có tên'); ?></h4>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                                <p><strong>SĐT:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'Chưa cập nhật'); ?></p>
                                <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars(($customer['Address'] ?? '') . (($customer['city'] ?? '') ? ', ' . $customer['city'] : '')); ?></p>
                                <p><strong>Ngày tạo:</strong> <?php echo isset($customer['created_at']) ? date('d/m/Y H:i', strtotime($customer['created_at'])) : 'Không xác định'; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            const sections = document.querySelectorAll('.section');

            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all menu items
                    menuItems.forEach(menuItem => menuItem.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Hide all sections
                    sections.forEach(section => section.style.display = 'none');
                    
                    // Show corresponding section
                    const sectionId = this.getAttribute('data-section') + 'Section';
                    const targetSection = document.getElementById(sectionId);
                    if (targetSection) {
                        targetSection.style.display = 'block';
                    }
                });
            });

            // Enhanced floating animation
            const floatingElements = document.querySelectorAll('.floating-element');
            floatingElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 2}s`;
                element.style.animationDuration = `${6 + index * 2}s`;
            });

            // Add scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe cards for animation
            const cards = document.querySelectorAll('.stat-card, .order-item, .product-card, .customer-card, .cart-item');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            // Add dynamic hover effects
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add loading animation for images
            const images = document.querySelectorAll('.product-image');
            images.forEach(img => {
                img.addEventListener('load', function() {
                    this.style.opacity = '1';
                });
                
                img.addEventListener('error', function() {
                    this.src = 'img/placeholder.jpg';
                    this.alt = 'Hình ảnh không khả dụng';
                });
            });

            // Add dynamic background gradient animation
            let gradientAngle = 135;
            setInterval(() => {
                gradientAngle += 1;
                if (gradientAngle > 225) gradientAngle = 135;
                document.body.style.background = `linear-gradient(${gradientAngle}deg, #ffffff 0%, #f8f9fa 100%)`;
            }, 100);

            // Add Nike-inspired particle effect
            function createParticle() {
                const particle = document.createElement('div');
                particle.style.position = 'fixed';
                particle.style.width = '4px';
                particle.style.height = '4px';
                particle.style.background = 'rgba(255, 107, 0, 0.6)';
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '-1';
                particle.style.left = Math.random() * window.innerWidth + 'px';
                particle.style.top = window.innerHeight + 'px';
                particle.style.transition = 'all 3s linear';
                
                document.body.appendChild(particle);
                
                // Animate particle
                setTimeout(() => {
                    particle.style.top = '-10px';
                    particle.style.opacity = '0';
                }, 50);
                
                // Remove particle
                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                }, 3000);
            }

            // Create particles periodically
            setInterval(createParticle, 300);

            // Add "Just Do It" motivation tooltip
            const logo = document.querySelector('.logo');
            if (logo) {
                logo.title = 'Nike - Just Do It! ✨';
                logo.addEventListener('click', function() {
                    this.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 200);
                });
            }

            // Add success messages for interactions
            function showSuccess(message) {
                const toast = document.createElement('div');
                toast.style.position = 'fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
                toast.style.color = 'white';
                toast.style.padding = '1rem 2rem';
                toast.style.borderRadius = '10px';
                toast.style.zIndex = '1000';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                toast.textContent = message;
                
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.opacity = '1';
                    toast.style.transform = 'translateX(0)';
                }, 100);
                
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 3000);
            }

            // Add click events for buttons
            document.querySelectorAll('.btn-primary').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!this.href || this.href.includes('#')) {
                        e.preventDefault();
                        showSuccess('Tính năng đang được phát triển! 🚀');
                    }
                });
            });
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.style.outline = '2px solid #ff6b00';
                setTimeout(() => {
                    document.body.style.outline = 'none';
                }, 3000);
            }
        });

        // Performance optimization - Lazy loading
        if ('IntersectionObserver' in window) {
            const lazyImages = document.querySelectorAll('img[loading="lazy"]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.src || img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(img => imageObserver.observe(img));
        }
    </script>
</body>
</html>
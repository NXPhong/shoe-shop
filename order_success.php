	<?php
	if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
	include('dbconnect.php');
     // Lấy dữ liệu giỏ hàng
$sql = "SELECT * FROM cart";
$result = $conn->query($sql);
$subtotal = 0;
$cart_items = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subtotal += $row['price'] * $row['sl'];
        $cart_items[] = $row;
    }
}
	// Lấy đơn hàng mới nhất từ bảng bill_guest
	$sql = "SELECT * FROM bill_guest ORDER BY id DESC LIMIT 1";
	$result = $conn->query($sql);
	$order = null;

	if ($result && $result->num_rows > 0) {
		$order = $result->fetch_assoc();
	} else {
		echo "<script>alert('Không tìm thấy đơn hàng!'); window.location.href='index.php';</script>";
		exit();
	}
	?>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['customer_id'])) {
    echo "<script>alert('Vui lòng đăng nhập để xem đơn hàng!'); window.location.href='login.php';</script>";
    exit();
}

include('dbconnect.php');

$customer_id = $_SESSION['customer_id'];

// Lấy đơn hàng mới nhất của customer hiện tại
$sql = "SELECT * FROM bill_guest WHERE customer_id = ? ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$order = null;

if ($result && $result->num_rows > 0) {
    $order = $result->fetch_assoc();
} else {
    echo "<script>alert('Không tìm thấy đơn hàng của bạn!'); window.location.href='index.php';</script>";
    exit();
}

// Kiểm tra xem bảng order_items có tồn tại không
$table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'order_items'";
$check_result = $conn->query($check_table_sql);
if ($check_result && $check_result->num_rows > 0) {
    $table_exists = true;
}

$order_items = [];

if ($table_exists) {
    // Lấy chi tiết sản phẩm trong đơn hàng từ bảng order_items
    $sql_items = "SELECT oi.*, p.name as product_name, p.image as product_image 
                  FROM order_items oi 
                  LEFT JOIN products p ON oi.product_id = p.id 
                  WHERE oi.order_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $order['id']);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();

    if ($result_items && $result_items->num_rows > 0) {
        while ($row = $result_items->fetch_assoc()) {
            $order_items[] = $row;
        }
    }
}

// Nếu không có dữ liệu từ order_items hoặc bảng không tồn tại, lấy từ cart với JOIN products
if (empty($order_items)) {
    $sql_cart = "SELECT c.*, p.name as product_name, p.image as product_image, p.price as product_price 
                 FROM cart c 
                 LEFT JOIN products p ON c.product_id = p.id 
                 WHERE c.customer_id = ?";
    $stmt_cart = $conn->prepare($sql_cart);
    $stmt_cart->bind_param("i", $customer_id);
    $stmt_cart->execute();
    $result_cart = $stmt_cart->get_result();
    
    if ($result_cart && $result_cart->num_rows > 0) {
        while ($row = $result_cart->fetch_assoc()) {
            $order_items[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Hàng - NIKEVN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .success-header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            animation: bounce 1s ease-in-out;
        }

        .success-icon i {
            font-size: 40px;
            color: white;
        }

        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }

        .order-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .order-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .order-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .order-number {
            font-size: 18px;
            opacity: 0.9;
        }

        .delivery-timeline {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            padding: 25px;
            margin: 0;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 18px;
        }

        .timeline-content h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .timeline-content p {
            color: #666;
            font-size: 14px;
        }

        .voucher-notice {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            display: flex;
            align-items: center;
        }

        .voucher-notice i {
            color: #856404;
            margin-right: 10px;
            font-size: 20px;
        }

        .section {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
        }

        .section:last-child {
            border-bottom: none;
        }

        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 20px;
            color: #333;
            font-weight: 600;
        }

        .section-title i {
            margin-right: 10px;
            width: 25px;
            color: #28a745;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: #333;
            font-size: 16px;
        }

        .product-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: transform 0.2s ease;
        }

        .product-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-image {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }

        .product-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-name {
            font-weight: 600;
            font-size: 18px;
            color: #333;
            margin-bottom: 8px;
        }

        .product-variants {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .variant-tag {
            background: #e9ecef;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            color: #666;
        }

        .product-price {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .product-price div:last-child {
            font-size: 18px;
            font-weight: 700;
            color: #e53935;
        }

        .payment-summary {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 25px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .payment-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            font-size: 20px;
            font-weight: 700;
            color: #28a745;
        }

        .footer-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding: 30px;
            background: #f8f9fa;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-continue {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-track {
            background: linear-gradient(135deg, #007bff, #6f42c1);
            color: white;
        }

        .btn-track:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
        }

        @media (max-width: 768px) {
            .order-container {
                margin: 0 10px;
                border-radius: 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .product-item {
                flex-direction: column;
                text-align: center;
            }

            .product-image {
                width: 80px;
                height: 80px;
                margin: 0 auto;
            }

            .footer-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-badge i {
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="success-header">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1>Đặt hàng thành công!</h1>
        <p>Cảm ơn bạn đã tin tường và mua sắm tại NIKEVN</p>
    </div>

    <div class="order-container">
        <div class="order-header">
            <h1>Chi Tiết Đơn Hàng</h1>
            <div class="order-number">Mã đơn hàng: NIKEVN<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
        </div>

        <div class="delivery-timeline">
            <div class="timeline-item">
                <div class="timeline-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="timeline-content">
                    <h4>Thời gian giao hàng dự kiến</h4>
                    <p><strong>31 Th05 - 01 Th06</strong></p>
                </div>
            </div>
            <div class="voucher-notice">
                <i class="fas fa-gift"></i>
                <span>Giao đúng hẹn nhận voucher 15.000đ - Nếu trễ sau 04/06/2025</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <i class="fas fa-truck-moving"></i>
                Thông tin vận chuyển
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Đơn vị vận chuyển</span>
                    <span class="info-value">NIKEVN Express</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Mã vận đơn</span>
                    <span class="info-value">NIKEVN<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Trạng thái</span>
                    <span class="status-badge">
                        <i class="fas fa-check-circle"></i>
                        Đã xác nhận
                    </span>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <i class="fas fa-map-marker-alt"></i>
                Địa chỉ nhận hàng
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Người nhận</span>
                    <span class="info-value"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Số điện thoại</span>
                    <span class="info-value"><?= htmlspecialchars($order['phone']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Địa chỉ</span>
                    <span class="info-value"><?= htmlspecialchars($order['address']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($order['email']); ?></span>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <i class="fas fa-shopping-bag"></i>
                Sản phẩm đã đặt (<?= count($order_items); ?> sản phẩm)
            </div>
            <div class="product-list">
                <?php if (!empty($order_items)): ?>
                    <?php foreach ($order_items as $item): 
                        // Xác định giá trị hiển thị
                        $product_name = $item['gh_name'] ?? $item['product_name'] ?? 'Sản phẩm';
                        $product_image = $item['img'] ?? $item['product_image'] ?? $item['image'] ?? 'images/default.jpg';
                        $product_size = $item['size'] ?? 'N/A';
                        $product_quantity = $item['sl'] ?? $item['quantity'] ?? 1;
                        $product_price = $item['price'] ?? $item['product_price'] ?? 0;
                        $total_item_price = $product_price * $product_quantity;
                    ?>
                    <div class="product-item">
                        <img src="<?= htmlspecialchars($product_image); ?>" 
                             alt="<?= htmlspecialchars($product_name); ?>" 
                             class="product-image"
                             onerror="this.src='images/default.jpg'">
                        <div class="product-details">
                            <div class="product-name">  <h4><?php echo htmlspecialchars($item['gh_name'] ?? 'Sản phẩm không xác định'); ?></h4></div>
                            <div class="product-variants">
                                <span class="variant-tag">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                    Size: <?= htmlspecialchars($product_size); ?>
                                </span>
                                <span class="variant-tag">
                                    <i class="fas fa-cubes"></i>
                                    SL: <?= htmlspecialchars($product_quantity); ?>
                                </span>
                            </div>
                            <div class="product-price">
                                <div style="margin-bottom: 5px;">
                                    Đơn giá: <?= number_format($product_price, 0, ',', '.'); ?>₫
                                </div>
                                <div style="font-size: 18px; color: #e53935;">
                                    Thành tiền: <?= number_format($total_item_price, 0, ',', '.'); ?>₫
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-shopping-cart" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Không tìm thấy thông tin sản phẩm trong đơn hàng.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <i class="fas fa-receipt"></i>
                Chi tiết thanh toán
            </div>
            <div class="payment-summary">
                <?php
                // Tính tổng tiền từ các sản phẩm
                $subtotal = 0;
                foreach ($order_items as $item) {
                    $price = $item['price'] ?? $item['product_price'] ?? 0;
                    $quantity = $item['sl'] ?? $item['quantity'] ?? 1;
                    $subtotal += $price * $quantity;
                }
                $shipping_fee = $subtotal > 500000 ? 0 : 30000; // Miễn phí ship từ 500k
                $total = $subtotal + $shipping_fee;
                ?>
                <div class="payment-row">
                    <span>Tạm tính (<?= count($order_items); ?> sản phẩm):</span>
                    <span><?= number_format($subtotal, 0, ',', '.'); ?>₫</span>
                </div>
                <div class="payment-row">
                    <span>Phí vận chuyển:</span>
                    <span><?= $shipping_fee > 0 ? number_format($shipping_fee, 0, ',', '.') . '₫' : 'Miễn phí'; ?></span>
                </div>
                <div class="payment-row">
                    <span>Thời gian đặt:</span>
                    <span><?= date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="payment-row">
                    <span><strong>Tổng cộng:</strong></span>
                    <span><?= number_format($order['total_price'] ?? $total, 0, ',', '.'); ?>₫</span>
                </div>
            </div>
        </div>

        <div class="footer-actions">
            <button class="btn btn-cancel" onclick="confirmCancel()">
                <i class="fas fa-times"></i>
                Hủy đơn hàng
            </button>
            <a href="track_order.php?order_id=<?= $order['id']; ?>" class="btn btn-track">
                <i class="fas fa-search"></i>
                Theo dõi đơn hàng
            </a>
            <a href="index.php" class="btn btn-continue">
                <i class="fas fa-shopping-cart"></i>
                Tiếp tục mua sắm
            </a>
        </div>
    </div>

    <script>
        function confirmCancel() {
            if (confirm('Bạn có chắc chắn muốn hủy đơn hàng này không?')) {
                window.location.href = 'cancel_order.php?order_id=<?= $order['id']; ?>';
            }
        }

        // Animation cho các phần tử khi trang load
        window.addEventListener('load', function() {
            const sections = document.querySelectorAll('.section');
            sections.forEach((section, index) => {
                setTimeout(() => {
                    section.style.opacity = '0';
                    section.style.transform = 'translateY(20px)';
                    section.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        section.style.opacity = '1';
                        section.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>
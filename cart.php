<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php?message=Vui lòng đăng nhập&status=error");
    exit();
}

include('dbconnect.php');

// Get customer_id from session
$customer_email = $_SESSION['email'];
$stmt_customer = $conn->prepare("SELECT customer_id FROM customer WHERE email = ?");
$stmt_customer->bind_param("s", $customer_email);
$stmt_customer->execute();
$result_customer = $stmt_customer->get_result();
$current_customer = $result_customer->fetch_assoc();

if (!$current_customer) {
    session_unset();
    session_destroy();
    header("Location: login.php?message=Tài khoản không tồn tại&status=error");
    exit();
}

$customer_id = $current_customer['customer_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['gh_id']) ? $conn->real_escape_string($_POST['gh_id']) : null;
    $action = isset($_POST['action']) ? $_POST['action'] : null;

    if (!$id || !$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
        exit;
    }

    // Verify that the cart item belongs to the logged-in user
    $stmt_check = $conn->prepare("SELECT customer_id FROM cart WHERE gh_id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $cart_item = $result_check->fetch_assoc();

    if (!$cart_item || $cart_item['customer_id'] != $customer_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit;
    }

    if ($action === 'update_quantity') {
        $quantity = isset($_POST['sl']) ? (int)$_POST['sl'] : 0;
        if ($quantity > 0) {
            $sql = "UPDATE cart SET sl = ? WHERE gh_id = ? AND customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $quantity, $id, $customer_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $conn->error]);
            }
        } else {
            $sql = "DELETE FROM cart WHERE gh_id = ? AND customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $customer_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Đã xóa sản phẩm do số lượng về 0']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi xóa: ' . $conn->error]);
            }
        }
        exit;
    }

    if ($action === 'update_size') {
        $size = isset($_POST['size']) ? $conn->real_escape_string($_POST['size']) : null;
        if ($size) {
            $sql = "UPDATE cart SET size = ? WHERE gh_id = ? AND customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $size, $id, $customer_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cập nhật kích cỡ thành công']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật kích cỡ: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Kích cỡ không hợp lệ']);
        }
        exit;
    }

    if ($action === 'remove') {
        $sql = "DELETE FROM cart WHERE gh_id = ? AND customer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $customer_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi xóa: ' . $conn->error]);
        }
        exit;
    }

        if (isset($_GET['clear_cart'])) {
    // Kiểm tra nếu có trong hóa đơn thì không xóa
    $check = "SELECT * FROM bill_guest WHERE customer_id = $customer_id";
    $check_result = $conn->query($check);

    if ($check_result->num_rows > 0) {
        echo "<script>alert('Không thể xóa! Một số sản phẩm đã có trong hóa đơn.');</script>";
    } else {
        $sql = "DELETE FROM cart WHERE customer_id = $customer_id";
        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('Đã xóa toàn bộ sản phẩm trong giỏ hàng.');</script>";
        } else {
            echo "<script>alert('Lỗi: " . $conn->error . "');</script>";
        }
    }
}

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
    exit;
}

// Fetch cart items for the logged-in user
$sql = "SELECT * FROM cart WHERE customer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$subtotal = 0;
$item_count = 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Giỏ Hàng - Fashion Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
       * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            min-height: 100vh;
        }

        .cart-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .cart-title {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-count {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .clear-cart-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
        }

        .cart-item {
            background: #f8f9ff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            position: relative;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .cart-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.1);
        }

        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-name {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .item-description {
            color: #718096;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .item-size {
            background: #e2e8f0;
            color: #4a5568;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            align-self: flex-start;
            margin-bottom: 10px;
        }

        .item-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .qty-btn {
            background: none;
            border: none;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #667eea;
            transition: all 0.2s ease;
        }

        .qty-btn:hover {
            background: #667eea;
            color: white;
        }

        .qty-display {
            min-width: 50px;
            text-align: center;
            font-weight: 600;
            color: #2d3748;
            background: #f7fafc;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-price {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        .remove-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #fed7d7;
            color: #c53030;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #fc8181;
            color: white;
            transform: rotate(90deg);
        }

        .summary-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 25px;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            margin-top: 20px;
        }

        .shipping-info {
            background: #e6fffa;
            color: #065f46;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
        }

        .member-checkout-btn {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        .member-checkout-btn:hover {
            box-shadow: 0 15px 35px rgba(72, 187, 120, 0.3);
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-cart i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #cbd5e0;
        }

        .empty-cart h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #4a5568;
        }

        .continue-shopping-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .continue-shopping-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #48bb78;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateX(400px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: #f56565;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 15px;
                gap: 20px;
            }
            
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-controls {
                justify-content: center;
                gap: 20px;
            }
        }

        .promo-section {
            background: linear-gradient(135deg, #ffecd2, #fcb69f);
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .promo-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .apply-promo-btn {
            background: linear-gradient(135deg, #fd7f6f, #7eb0d3);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            width: 100%;
        }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="container">
    <div class="cart-section">
        <div class="cart-header">
            <div class="cart-title">
                <i class="fas fa-shopping-cart"></i>
                Giỏ hàng của bạn
                <?php if ($result && $result->num_rows > 0): ?>
                    <span class="cart-count"><?php echo $result->num_rows; ?></span>
                <?php endif; ?>
            </div>
            <?php if ($result && $result->num_rows > 0): ?>
                <button class="clear-cart-btn" onclick="clearCart()">
                    <i class="fas fa-trash"></i>
                    Xóa tất cả
                </button>
            <?php endif; ?>
        </div>

        <div class="cart-items">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="cart-item" data-id="<?php echo $row['gh_id']; ?>">
                        <button class="remove-btn" onclick="removeItem('<?php echo $row['gh_id']; ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <img src="<?php echo htmlspecialchars($row['img']); ?>" 
                             alt="<?php echo htmlspecialchars($row['gh_name']); ?>" 
                             class="item-image" />
                        
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($row['gh_name']); ?></div>
                            <div class="item-description"><?php echo htmlspecialchars($row['description']); ?></div>
                            <div class="item-size">
                                Kích cỡ: 
                                <select onchange="updateSize('<?php echo $row['gh_id']; ?>', this.value)">
                                    <option value="38" <?php echo $row['size'] == '38' ? 'selected' : ''; ?>>38</option>
                                    <option value="39" <?php echo $row['size'] == '39' ? 'selected' : ''; ?>>39</option>
                                    <option value="40" <?php echo $row['size'] == '40' ? 'selected' : ''; ?>>40</option>
                                    <option value="41" <?php echo $row['size'] == '41' ? 'selected' : ''; ?>>41</option>
                                    <option value="42" <?php echo $row['size'] == '42' ? 'selected' : ''; ?>>42</option>
                                </select>
                            </div>
                            
                            <div class="item-controls">
                                <div class="quantity-control">
                                    <button class="qty-btn" onclick="updateQuantity('<?php echo $row['gh_id']; ?>', -1, <?php echo $row['price']; ?>)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <div class="qty-display"><?php echo htmlspecialchars($row['sl']); ?></div>
                                    <button class="qty-btn" onclick="updateQuantity('<?php echo $row['gh_id']; ?>', 1, <?php echo $row['price']; ?>)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="item-price"><?php echo number_format($row['price'] * $row['sl'], 0, ',', '.'); ?>₫</div>
                            </div>
                        </div>
                    </div>
                    <?php 
                    $subtotal += $row['price'] * $row['sl']; 
                    $item_count++;
                    ?>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Giỏ hàng trống</h3>
                    <p>Bạn chưa có sản phẩm nào trong giỏ hàng</p>
                    <a href="index.php" class="continue-shopping-btn">
                        <i class="fas fa-arrow-left"></i>
                        Tiếp tục mua sắm
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
    <div class="summary-section">
        <h2 class="summary-title">
            <i class="fas fa-receipt"></i> Tóm tắt đơn hàng
        </h2>
        
        <div class="summary-row">
            <span>Tạm tính (<?php echo $item_count; ?> sản phẩm)</span>
            <span id="subtotal"><?php echo number_format($subtotal, 0, ',', '.'); ?>₫</span>
        </div>
        
        <div class="shipping-info">
            <i class="fas fa-truck"></i>
            <span>Miễn phí giao hàng cho đơn hàng trên 500.000₫</span>
        </div>
        
        <div class="promo-section">
            <h4>Mã giảm giá</h4>
            <input type="text" class="promo-input" placeholder="Nhập mã giảm giá" id="promoCode">
            <button class="apply-promo-btn" onclick="applyPromo()">Áp dụng</button>
        </div>
        
        <div class="summary-row total">
            <span>Tổng cộng</span>
            <span id="total"><?php echo number_format($subtotal, 0, ',', '.'); ?>₫</span>
        </div>
        
        <a href="?go=checkout">
            <button class="checkout-btn">
                <i class="fas fa-credit-card"></i>
                Thanh toán
            </button>
        </a>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="continue-shopping-btn">
                <i class="fas fa-arrow-left"></i>
                Tiếp tục mua sắm
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="notification" id="notification"></div>

<script>
function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification' + (isError ? ' error' : '') + ' show';
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

function updateQuantity(itemId, delta, price) {
    const item = document.querySelector(`[data-id="${itemId}"]`);
    const qtyDisplay = item.querySelector('.qty-display');
    let quantity = parseInt(qtyDisplay.textContent);
    quantity += delta;

    if (quantity <= 0) {
        removeItem(itemId);
        return;
    }

    // Update UI temporarily
    qtyDisplay.textContent = quantity;
    const priceElement = item.querySelector('.item-price');
    priceElement.textContent = (quantity * price).toLocaleString('vi-VN') + '₫';

    // Send update request
    item.classList.add('loading');
    
    fetch('cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `gh_id=${encodeURIComponent(itemId)}&sl=${quantity}&action=update_quantity`
    })
    .then(response => response.json())
    .then(data => {
        item.classList.remove('loading');
        if (data.success) {
            updateSummary();
            showNotification(data.message);
        } else {
            showNotification(data.message, true);
            qtyDisplay.textContent = quantity - delta;
            priceElement.textContent = ((quantity - delta) * price).toLocaleString('vi-VN') + '₫';
        }
    })
    .catch(error => {
        item.classList.remove('loading');
        showNotification('Có lỗi xảy ra', true);
        qtyDisplay.textContent = quantity - delta;
        priceElement.textContent = ((quantity - delta) * price).toLocaleString('vi-VN') + '₫';
    });
}

function updateSize(itemId, size) {
    const item = document.querySelector(`[data-id="${itemId}"]`);
    item.classList.add('loading');
    
    fetch('cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `gh_id=${encodeURIComponent(itemId)}&size=${encodeURIComponent(size)}&action=update_size`
    })
    .then(response => response.json())
    .then(data => {
        item.classList.remove('loading');
        if (data.success) {
            showNotification(data.message);
        } else {
            showNotification(data.message, true);
        }
    })
    .catch(error => {
        item.classList.remove('loading');
        showNotification('Có lỗi xảy ra', true);
    });
}

function removeItem(itemId) {
    const item = document.querySelector(`[data-id="${itemId}"]`);
    
    if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
        item.style.opacity = '0.5';
        
        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `gh_id=${encodeURIComponent(itemId)}&action=remove`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                item.remove();
                updateSummary();
                showNotification(data.message);
                
                if (document.querySelectorAll('.cart-item').length === 0) {
                    location.reload();
                }
            } else {
                item.style.opacity = '1';
                showNotification(data.message, true);
            }
        })
        .catch(error => {
            item.style.opacity = '1';
            showNotification('Có lỗi xảy ra', true);
        });
    }
}

function clearCart() {
    if (confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?')) {
        document.querySelector('.cart-items').style.opacity = '0.5';
        
        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=clear_cart'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                document.querySelector('.cart-items').style.opacity = '1';
                showNotification(data.message, true);
            }
        })
        .catch(error => {
            document.querySelector('.cart-items').style.opacity = '1';
            showNotification('Có lỗi xảy ra', true);
        });
    }
}

function updateSummary() {
    let subtotal = 0;
    const items = document.querySelectorAll('.cart-item');

    items.forEach(item => {
        const priceText = item.querySelector('.item-price').textContent.replace(/[₫.,]/g, '').replace(/\s/g, '');
        subtotal += parseInt(priceText);
    });

    document.getElementById('subtotal').textContent = subtotal.toLocaleString('vi-VN') + '₫';
    document.getElementById('total').textContent = subtotal.toLocaleString('vi-VN') + '₫';
}

function applyPromo() {
    const promoCode = document.getElementById('promoCode').value.trim();
    if (!promoCode) {
        showNotification('Vui lòng nhập mã giảm giá', true);
        return;
    }
    
    const validCodes = {
        'WELCOME10': 0.1,
        'SAVE20': 0.2,
        'FREESHIP': 0.05
    };
    
    if (validCodes[promoCode.toUpperCase()]) {
        const discount = validCodes[promoCode.toUpperCase()];
        const currentTotal = parseInt(document.getElementById('total').textContent.replace(/[₫.,]/g, ''));
        const newTotal = currentTotal * (1 - discount);
        
        document.getElementById('total').textContent = newTotal.toLocaleString('vi-VN') + '₫';
        showNotification(`Áp dụng mã giảm giá thành công! Giảm ${(discount * 100)}%`);
        document.getElementById('promoCode').disabled = true;
        document.querySelector('.apply-promo-btn').disabled = true;
        document.querySelector('.apply-promo-btn').textContent = 'Đã áp dụng';
    } else {
        showNotification('Mã giảm giá không hợp lệ', true);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.cart-item');
    items.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>
</body>
</html>
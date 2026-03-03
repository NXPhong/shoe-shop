<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('dbconnect.php');

$customer_id = $_SESSION['customer_id'] ?? null;

// Fetch cart items
if ($customer_id) {
    $stmt = $conn->prepare("SELECT * FROM cart WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM cart WHERE customer_id IS NULL");
}
$stmt->execute();
$result = $stmt->get_result();

$subtotal = 0;
$cart_items = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subtotal += $row['price'] * $row['sl'];
        $cart_items[] = $row;
    }
}
$stmt->close();

// Handle order processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $payment_method = $_POST['payment_method'];
    
    $card_name = isset($_POST['card_name']) ? $_POST['card_name'] : '';
    $card_number = isset($_POST['card_number']) ? $_POST['card_number'] : '';
    $exp = isset($_POST['exp']) ? $_POST['exp'] : '';
    $cvv = isset($_POST['cvv']) ? $_POST['cvv'] : '';
    $crypto_txn_hash = isset($_POST['crypto_txn_hash']) ? $_POST['crypto_txn_hash'] : '';

    $errors = [];
    $success_inserts = 0;
    $successful_cart_items = [];

    foreach ($cart_items as $item) {
        $gh_id = $item['gh_id'];
        $item_total = $item['price'] * $item['sl'];

        if (!$gh_id || $gh_id <= 0) {
            $errors[] = "Invalid cart_id for product: " . $item['gh_name'];
            continue;
        }

        $sql_insert = "INSERT INTO bill_guest 
        (email, first_name, last_name, phone, address, card_name, card_number, exp, cvv, total_price, customer_id, cart_id, payment_method, crypto_txn_hash) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_insert);
        if (!$stmt) {
            $errors[] = "Prepare error for product " . $item['gh_name'] . ": " . $conn->error;
            continue;
        }

        $stmt->bind_param("sssssssssdiiss", 
            $email, $first_name, $last_name, $phone, $address,
            $card_name, $card_number, $exp, $cvv,
            $item_total, $customer_id, $gh_id, $payment_method, $crypto_txn_hash
        );

        if ($stmt->execute()) {
            $success_inserts++;
            $successful_cart_items[] = $item;
        } else {
            $errors[] = "Execute error for product " . $item['gh_name'] . ": " . $stmt->error;
        }

        $stmt->close();
    }

    if ($success_inserts > 0) {
        $message = empty($errors) ? "Order placed successfully! Thank you for your purchase." : 
            "Order placed successfully for $success_inserts products.\nSome products had errors: " . implode("\n", $errors);
        echo "<script>
            alert('$message');
            window.location.href='order_success.php';
        </script>";
        exit();
    } else {
        echo "<script>alert('Order placement failed:\n" . implode("\n", $errors) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - Checkout</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/web3@1.7.0/dist/web3.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #1a1a1a 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 40px;
            color: black;
        }

        .checkout-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .checkout-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .checkout-wrapper {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
            align-items: start;
        }

        .form-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #ff6b6b;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .order-note {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .order-note a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 500;
        }

        .order-note a:hover {
            text-decoration: underline;
        }

        .payment-methods {
            margin: 25px 0;
        }

        .payment-option {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .payment-option:hover {
            border-color: #ff6b6b;
            background: white;
        }

        .payment-option.selected {
            border-color: #ff6b6b;
            background: #fff5f5;
        }

        .payment-option input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
            accent-color: #ff6b6b;
        }

        .payment-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            color: #2c3e50;
        }

        .payment-option i {
            font-size: 1.2rem;
            color: #ff6b6b;
        }

        .card-details, .crypto-details {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e1e8ed;
        }

        .card-details.show, .crypto-details.show {
            display: block;
        }

        .btn-checkout, .btn-crypto-pay {
            width: 100%;
            background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);
            color: white;
            border: none;
            padding: 18px 25px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 20px;
        }

        .btn-checkout:hover, .btn-crypto-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
        }

        .summary-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 1.4rem;
            color: #2c3e50;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-weight: 600;
            font-size: 1.2rem;
            color: #2c3e50;
            padding-top: 20px;
            border-top: 2px solid #ff6b6b;
        }

        .price {
            color: #ff6b6b;
            font-weight: 600;
        }

        .free-shipping {
            background: linear-gradient(135deg, #52c234 0%, #4CAF50 100%);
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
            font-weight: 500;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #52c234, #4CAF50);
            width: 100%;
            border-radius: 3px;
        }

        .cart-items {
            margin-top: 25px;
        }

        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .item-info {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .item-price {
            font-weight: 600;
            color: #ff6b6b;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
            color: #6c757d;
        }

        .security-badge i {
            color: #28a745;
        }

        .cod-info, .crypto-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-size: 14px;
            color: #856404;
        }

        .crypto-info i {
            color: #f39c12;
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .checkout-wrapper {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .form-section,
            .summary-section {
                padding: 25px;
            }
            
            .checkout-header h1 {
                font-size: 2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkout-header">
            <h1><i class="fas fa-shopping-cart"></i> Thanh toán</h1>
            <p>Hoàn tất đơn hàng của bạn một cách an toàn và nhanh chóng</p>
        </div>

        <div class="checkout-wrapper">
            <div class="form-section">
                <form method="post" id="checkoutForm">
                    <input type="hidden" name="crypto_txn_hash" id="cryptoTxnHash">
                    <h2 class="section-title">
                        <i class="fas fa-truck"></i>
                        Thông tin giao hàng
                    </h2>
                    
                    <div class="form-group input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Địa chỉ email của bạn" required>
                    </div>
                    
                    <div class="order-note">
                        Bạn chưa phải là thành viên? <a href="#">Đăng nhập</a> hoặc <a href="#">Đăng ký ngay</a>
                    </div>

                    <div class="form-row">
                        <div class="form-group input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="first_name" class="form-control" placeholder="Họ và tên đệm" required>
                        </div>
                        <div class="form-group input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="last_name" class="form-control" placeholder="Tên" required>
                        </div>
                    </div>

                    <div class="form-group input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" class="form-control" placeholder="Số điện thoại liên hệ" required>
                    </div>

                    <div class="form-group input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="address" class="form-control" placeholder="Địa chỉ giao hàng chi tiết" required>
                    </div>

                    <div class="order-note">
                        <a href="#"><i class="fas fa-edit"></i> Nhập địa chỉ thủ công</a>
                    </div>

                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Phương thức thanh toán
                    </h2>

                    <div class="payment-methods">
                        <div class="payment-option" onclick="selectPayment('online')">
                            <input type="radio" name="payment_method" value="online" id="online">
                            <label for="online">
                                <i class="fas fa-credit-card"></i>
                                <span>Thanh toán online</span>
                            </label>
                        </div>

                        <div class="payment-option" onclick="selectPayment('cod')">
                            <input type="radio" name="payment_method" value="cod" id="cod" checked>
                            <label for="cod">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Thanh toán khi nhận hàng (COD)</span>
                            </label>
                        </div>

                        <div class="payment-option" onclick="selectPayment('crypto')">
                            <input type="radio" name="payment_method" value="crypto" id="crypto">
                            <label for="crypto">
                                <i class="fab fa-ethereum"></i>
                                <span>Thanh toán bằng Ethereum</span>
                            </label>
                        </div>
                    </div>

                    <div class="card-details" id="cardDetails">
                        <div class="form-group input-icon">
                            <i class="fas fa-user-circle"></i>
                            <input type="text" name="card_name" class="form-control" placeholder="Tên chủ thẻ">
                        </div>

                        <div class="form-group input-icon">
                            <i class="fas fa-credit-card"></i>
                            <input type="text" name="card_number" class="form-control" placeholder="Số thẻ tín dụng" maxlength="19">
                        </div>

                        <div class="form-row">
                            <div class="form-group input-icon">
                                <i class="fas fa-calendar"></i>
                                <input type="text" name="exp" class="form-control" placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="text" name="cvv" class="form-control" placeholder="CVV" maxlength="4">
                            </div>
                        </div>

                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>Thanh toán được bảo mật bằng SSL 256-bit</span>
                        </div>
                    </div>

                    <div class="crypto-details" id="cryptoDetails">
                        <div class="form-group">
                            <p>Thanh toán bằng Ethereum qua MetaMask</p>
                            <p>Tổng số tiền: <span id="ethAmount"><?php echo number_format($subtotal / 1000000, 6); ?> ETH</span></p>
                            <button type="button" class="btn-crypto-pay" onclick="processCryptoPayment()">Thanh toán bằng MetaMask</button>
                        </div>
                        <div class="crypto-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Lưu ý:</strong> Đảm bảo bạn có đủ ETH trong ví MetaMask để thanh toán và phí gas.
                        </div>
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>Giao dịch được bảo mật trên blockchain Ethereum</span>
                        </div>
                    </div>

                    <div class="cod-info" id="codInfo">
                        <i class="fas fa-info-circle"></i>
                        <strong>Thanh toán khi nhận hàng:</strong> Bạn sẽ thanh toán bằng tiền mặt khi nhận được sản phẩm. Phí COD: 0đ
                    </div>

                    <button type="submit" class="btn-checkout" id="submitButton">
                        <i class="fas fa-lock"></i> Đặt hàng ngay
                    </button>
                </form>
            </div>

            <div class="summary-section">
                <h2 class="summary-title">
                    <i class="fas fa-receipt"></i> Tóm tắt đơn hàng
                </h2>

                <div class="summary-row">
                    <span>Tạm tính</span>
                    <span class="price"><?php echo number_format($subtotal, 0, ',', '.'); ?>₫</span>
                </div>

                <div class="summary-row">
                    <span>Phí vận chuyển</span>
                    <span style="color: #28a745; font-weight: 600;">Miễn phí</span>
                </div>

                <div class="free-shipping">
                    <i class="fas fa-check-circle"></i> Bạn được miễn phí vận chuyển!
                </div>

                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>

                <div class="summary-row total">
                    <span>Tổng cộng</span>
                    <span class="price"><?php echo number_format($subtotal, 0, ',', '.'); ?>₫</span>
                </div>

                <?php if (!empty($cart_items)): ?>
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['img']); ?>" alt="<?php echo htmlspecialchars($item['gh_name']); ?>" class="item-image">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['gh_name']); ?></div>
                            <div class="item-info">SL: <?php echo htmlspecialchars($item['sl']); ?> | Size: <?php echo htmlspecialchars($item['size']); ?></div>
                            <div class="item-price"><?php echo number_format($item['price'], 0, ',', '.'); ?>₫</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="cart-items">
                    <p style="text-align: center; color: #6c757d; padding: 20px;">
                        <i class="fas fa-shopping-cart"></i><br>
                        Giỏ hàng của bạn đang trống
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Smart contract details (replace with your actual deployed contract)
        const contractAddress = 'YOUR_DEPLOYED_CONTRACT_ADDRESS'; // Replace with your contract address
        const contractABI = [
            {
                "inputs": [
                    {"name": "amount", "type": "uint256"},
                    {"name": "orderId", "type": "string"}
                ],
                "name": "processPayment",
                "outputs": [],
                "stateMutability": "payable",
                "type": "function"
            }
        ];

        async function processCryptoPayment() {
            if (typeof window.ethereum === 'undefined') {
                alert('Vui lòng cài đặt MetaMask để sử dụng thanh toán bằng Ethereum');
                return;
            }

            try {
                const web3 = new Web3(window.ethereum);
                await window.ethereum.request({ method: 'eth_requestAccounts' });
                const accounts = await web3.eth.getAccounts();
                const userAddress = accounts[0];

                // Switch to Ropsten Testnet (for testing) - Replace with Mainnet for production
                await window.ethereum.request({
                    method: 'wallet_addEthereumChain',
                    params: [{
                        chainId: '0x3', // Ropsten Testnet
                        chainName: 'Ropsten Test Network',
                        rpcUrls: ['https://ropsten.infura.io/v3/YOUR_INFURA_PROJECT_ID'], // Replace with your Infura ID
                        nativeCurrency: { symbol: 'ETH', decimals: 18 }
                    }]
                });

                const contract = new web3.eth.Contract(contractABI, contractAddress);
                const ethAmount = <?php echo $subtotal / 1000000; ?>;
                const weiAmount = web3.utils.toWei(ethAmount.toString(), 'ether');
                const orderId = 'ORDER_' + Date.now();

                // Check balance
                const balance = await web3.eth.getBalance(userAddress);
                const ethBalance = web3.utils.fromWei(balance, 'ether');
                if (ethBalance < ethAmount) {
                    alert('Số dư ETH không đủ. Vui lòng nạp thêm ETH từ faucet testnet.');
                    return;
                }

                // Send transaction with gas settings
                const tx = await contract.methods.processPayment(weiAmount, orderId)
                    .send({
                        from: userAddress,
                        value: weiAmount,
                        gas: 200000,
                        gasPrice: await web3.eth.getGasPrice()
                    });

                document.getElementById('cryptoTxnHash').value = tx.transactionHash;
                alert('Thanh toán thành công! Giao dịch: ' + tx.transactionHash);
                document.getElementById('checkoutForm').submit();
            } catch (error) {
                console.error('Crypto payment error:', error);
                alert('Lỗi khi thực hiện thanh toán bằng Ethereum: ' + error.message);
            }
        }

        function selectPayment(method) {
            document.getElementById(method).checked = true;
            
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`#${method}`).closest('.payment-option').classList.add('selected');
            
            const cardDetails = document.getElementById('cardDetails');
            const cryptoDetails = document.getElementById('cryptoDetails');
            const codInfo = document.getElementById('codInfo');
            const submitButton = document.getElementById('submitButton');
            
            cardDetails.classList.remove('show');
            cryptoDetails.classList.remove('show');
            codInfo.style.display = 'none';
            
            cardDetails.querySelectorAll('input').forEach(input => input.required = false);
            
            if (method === 'online') {
                cardDetails.classList.add('show');
                cardDetails.querySelectorAll('input').forEach(input => input.required = true);
                submitButton.style.display = 'block';
            } else if (method === 'crypto') {
                cryptoDetails.classList.add('show');
                submitButton.style.display = 'none';
            } else {
                codInfo.style.display = 'block';
                submitButton.style.display = 'block';
            }
        }

        // Initialize default selection
        selectPayment('cod');

        // Format card number input
        document.querySelector('input[name="card_number"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            if (formattedValue.length > 19) formattedValue = formattedValue.substr(0, 19);
            e.target.value = formattedValue;
        });

        // Format expiry date input
        document.querySelector('input[name="exp"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Format CVV input
        document.querySelector('input[name="cvv"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });

        // Format phone input
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9+\-\s]/g, '');
        });

        // Add click event listeners to payment options
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.payment-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    selectPayment(radio.value);
                });
            });
        });

        // Prevent form submission if cart is empty
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            <?php if (empty($cart_items)): ?>
            e.preventDefault();
            alert('Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm trước khi thanh toán.');
            <?php endif; ?>
        });
    </script>
</body>
</html>
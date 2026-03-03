<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('dbconnect.php');

$showPopup = false;
$popupData = [];

// Set customer_id to NULL for guests, otherwise use session customer_id
$customer_id = isset($_SESSION['customer_id']) ? intval($_SESSION['customer_id']) : NULL;

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM nam WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        echo "Không tìm thấy sản phẩm.";
        exit;
    }
    $stmt->close();
} else {
    echo "ID sản phẩm không hợp lệ.";
    exit;
}

// Lấy sản phẩm tương tự (cùng loại hoặc ngẫu nhiên)
$similar_sql = "SELECT * FROM nam WHERE id != ? ORDER BY RAND() LIMIT 4";
$stmt = $conn->prepare($similar_sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$similar_result = $stmt->get_result();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $gh_name = $_POST['gh_name'];
    $price = floatval($_POST['price']);
    $size = $_POST['size'];
    $sl = intval($_POST['sl']);
    $img = $_POST['img'] ?: 'default_image.jpg'; // Default image if empty
    $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? intval($_POST['customer_id']) : NULL;

    // Debug the customer_id value
    var_dump($customer_id);

    // Validate img to ensure it's not empty
    if (empty($img)) {
        $img = 'default_image.jpg'; // Set a default image path
    }

    $sql = "INSERT INTO cart (customer_id, gh_name, price, size, sl, img) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdsis", $customer_id, $gh_name, $price, $size, $sl, $img);

    if ($stmt->execute()) {
        $showPopup = true;
        $popupData = compact('gh_name', 'price', 'size', 'sl', 'img', 'customer_id');
    } else {
        echo "Lỗi: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($product['ten_nam'] ?? 'Chi tiết sản phẩm'); ?> - Chi tiết sản phẩm</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <style>
    /* Your existing CSS remains unchanged - included for completeness */
    .product-detail-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 40px 20px;
      background: #fff;
    }

    .product-main {
      display: flex;
      gap: 60px;
      margin-bottom: 80px;
      align-items: flex-start;
    }

    .product-gallery {
      flex: 1;
      max-width: 600px;
    }

    .gallery-wrapper {
      display: flex;
      gap: 20px;
    }

    .thumbnails-vertical {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .thumbnail-img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.3s ease;
      opacity: 0.7;
    }

    .thumbnail-img:hover,
    .thumbnail-img.active {
      border-color: #000;
      opacity: 1;
      transform: scale(1.05);
    }

    .main-image-wrapper {
      position: relative;
      flex: 1;
    }

    .main-product-image {
      width: 100%;
      max-width: 500px;
      height: 500px;
      object-fit: cover;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .product-badge {
      position: absolute;
      top: 20px;
      left: 20px;
      background: linear-gradient(135deg, #ff6b6b, #ee5a24);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 14px;
      box-shadow: 0 4px 12px rgba(255,107,107,0.3);
    }

    .product-info-section {
      flex: 1;
      max-width: 500px;
    }

    .product-category {
      color: #007bff;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 14px;
      letter-spacing: 1px;
      margin-bottom: 8px;
    }

    .product-title {
      font-size: 36px;
      font-weight: 700;
      color: #000;
      margin-bottom: 12px;
      line-height: 1.2;
    }

    .product-description {
      color: #666;
      font-size: 16px;
      line-height: 1.6;
      margin-bottom: 20px;
    }

    .product-price {
      font-size: 32px;
      font-weight: 700;
      color: #e60023;
      margin-bottom: 30px;
    }

    .product-rating {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 30px;
    }

    .stars {
      display: flex;
      gap: 2px;
    }

    .star {
      color: #ffc107;
      font-size: 18px;
    }

    .rating-text {
      color: #666;
      font-size: 14px;
    }

    .product-form {
      background: #f8f9fa;
      padding: 30px;
      border-radius: 16px;
      border: 1px solid #eee;
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-label {
      display: block;
      font-weight: 600;
      font-size: 16px;
      color: #000;
      margin-bottom: 8px;
    }

    .form-select,
    .form-input {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }

    .form-select:focus,
    .form-input:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .size-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
      gap: 8px;
      margin-top: 8px;
    }

    .size-option {
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: white;
    }

    .size-option:hover {
      border-color: #007bff;
      background: #f0f8ff;
    }

    .size-option.selected {
      border-color: #000;
      background: #000;
      color: white;
    }

    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 8px;
    }

    .qty-btn {
      width: 40px;
      height: 40px;
      border: 2px solid #ddd;
      background: white;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      transition: all 0.3s ease;
    }

    .qty-btn:hover {
      border-color: #007bff;
      background: #f0f8ff;
    }

    .qty-input {
      width: 80px;
      text-align: center;
      font-weight: 600;
    }

    .add-to-cart-btn {
      width: 100%;
      background: linear-gradient(135deg, #000, #333);
      color: white;
      border: none;
      padding: 18px 24px;
      border-radius: 50px;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 24px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .add-to-cart-btn:hover {
      background: linear-gradient(135deg, #333, #555);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    .product-features {
      margin-top: 30px;
      padding-top: 30px;
      border-top: 1px solid #eee;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      color: #666;
    }

    .feature-icon {
      color: #007bff;
      font-size: 18px;
    }

    .similar-products {
      margin-top: 80px;
      padding-top: 40px;
      border-top: 2px solid #f0f0f0;
    }

    .section-title {
      font-size: 32px;
      font-weight: 700;
      text-align: center;
      margin-bottom: 40px;
      color: #000;
      position: relative;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: linear-gradient(135deg, #007bff, #0056b3);
      border-radius: 2px;
    }

    .similar-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
    }

    .similar-product-card {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      border: 1px solid #f0f0f0;
    }

    .similar-product-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }

    .similar-product-image {
      width: 100%;
      height: 250px;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .similar-product-card:hover .similar-product-image {
      transform: scale(1.05);
    }

    .similar-product-info {
      padding: 20px;
    }

    .similar-product-title {
      font-size: 18px;
      font-weight: 600;
      color: #000;
      margin-bottom: 8px;
      line-height: 1.4;
      height: 50px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .similar-product-price {
      font-size: 20px;
      font-weight: 700;
      color: #e60023;
      margin-bottom: 12px;
    }

    .similar-product-btn {
      width: 100%;
      background: #000;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .similar-product-btn:hover {
      background: #333;
      transform: translateY(-1px);
    }

    .enhanced-popup {
      position: fixed;
      top: 20px;
      right: 20px;
      background: white;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      width: 380px;
      padding: 20px;
      z-index: 9999;
      animation: slideInRight 0.4s ease-out;
      border: 1px solid #e0e0e0;
    }

    .popup-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .popup-success {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      color: #28a745;
    }

    .popup-close {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #999;
      transition: color 0.3s ease;
    }

    .popup-close:hover {
      color: #666;
    }

    .popup-content {
      display: flex;
      gap: 16px;
      margin-bottom: 20px;
    }

    .popup-image {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
    }

    .popup-details {
      flex: 1;
      font-size: 14px;
    }

    .popup-product-name {
      font-weight: 600;
      margin-bottom: 4px;
    }

    .popup-actions {
      display: flex;
      gap: 12px;
    }

    .popup-btn {
      flex: 1;
      padding: 12px 16px;
      border-radius: 8px;
      text-decoration: none;
      text-align: center;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .popup-btn-view {
      border: 2px solid #ddd;
      color: #333;
      background: white;
    }

    .popup-btn-view:hover {
      border-color: #007bff;
      color: #007bff;
    }

    .popup-btn-checkout {
      background: #000;
      color: white;
      border: 2px solid #000;
    }

    .popup-btn-checkout:hover {
      background: #333;
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(100px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @media (max-width: 768px) {
      .product-main {
        flex-direction: column;
        gap: 30px;
      }

      .gallery-wrapper {
        flex-direction: column-reverse;
      }

      .thumbnails-vertical {
        flex-direction: row;
        justify-content: center;
      }

      .product-title {
        font-size: 28px;
      }

      .product-price {
        font-size: 24px;
      }

      .similar-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
      }

      .enhanced-popup {
        width: calc(100vw - 40px);
        right: 20px;
        left: 20px;
      }
    }
  </style>
</head>
<body>

<div class="product-detail-container">
  <div class="product-main">
    <div class="product-gallery">
      <div class="gallery-wrapper">
        <div class="thumbnails-vertical">
          <img src="<?php echo htmlspecialchars($product['hanh_nam'] ?? 'default_image.jpg'); ?>" 
               onclick="changeImage(this)" 
               data-src="<?php echo htmlspecialchars($product['hanh_nam'] ?? 'default_image.jpg'); ?>" 
               class="thumbnail-img active" />
          <?php if (!empty($product['imgctn1'])): ?>
            <img src="<?php echo htmlspecialchars($product['imgctn1']); ?>" 
                 onclick="changeImage(this)" 
                 data-src="<?php echo htmlspecialchars($product['imgctn1']); ?>" 
                 class="thumbnail-img" />
          <?php endif; ?>
          <?php if (!empty($product['imgctn2'])): ?>
            <img src="<?php echo htmlspecialchars($product['imgctn2']); ?>" 
                 onclick="changeImage(this)" 
                 data-src="<?php echo htmlspecialchars($product['imgctn2']); ?>" 
                 class="thumbnail-img" />
          <?php endif; ?>
          <?php if (!empty($product['imgctn3'])): ?>
            <img src="<?php echo htmlspecialchars($product['imgctn3']); ?>" 
                 onclick="changeImage(this)" 
                 data-src="<?php echo htmlspecialchars($product['imgctn3']); ?>" 
                 class="thumbnail-img" />
          <?php endif; ?>
        </div>
        
        <div class="main-image-wrapper">
          <div class="product-badge">
            <i class="fas fa-star"></i> Mới
          </div>
          <img id="mainPreview" 
               src="<?php echo htmlspecialchars($product['hanh_nam'] ?? 'default_image.jpg'); ?>" 
               alt="<?php echo htmlspecialchars($product['ten_nam'] ?? 'Product'); ?>" 
               class="main-product-image" />
        </div>
      </div>
    </div>

    <div class="product-info-section">
      <div class="product-category">
        <i class="fas fa-leaf"></i> Vật liệu bền vững
      </div>
      
      <h1 class="product-title"><?php echo htmlspecialchars($product['ten_nam'] ?? 'Sản phẩm'); ?></h1>
      
      <p class="product-description"><?php echo htmlspecialchars($product['mota_nam'] ?? 'Mô tả không có'); ?></p>
      
      <div class="product-rating">
        <div class="stars">
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
        </div>
        <span class="rating-text">(4.8) - 124 đánh giá</span>
      </div>
      
      <div class="product-price">
        <?php echo number_format($product['gia_nam'] ?? 0, 0, ',', '.'); ?>₫
      </div>

      <form method="POST" class="product-form">
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-ruler"></i> Chọn kích cỡ
          </label>
          <div class="size-options">
            <div class="size-option" onclick="selectSize(this, '38')">38</div>
            <div class="size-option" onclick="selectSize(this, '39')">39</div>
            <div class="size-option" onclick="selectSize(this, '40')">40</div>
            <div class="size-option" onclick="selectSize(this, '40.5')">40.5</div>
            <div class="size-option" onclick="selectSize(this, '41')">41</div>
            <div class="size-option" onclick="selectSize(this, '42')">42</div>
          </div>
          <input type="hidden" name="size" id="selectedSize" required />
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-sort-numeric-up"></i> Số lượng
          </label>
          <div class="quantity-controls">
            <button type="button" class="qty-btn" onclick="changeQuantity(-1)">
              <i class="fas fa-minus"></i>
            </button>
            <input type="number" name="sl" id="quantity" value="1" min="1" class="qty-input form-input" required />
            <button type="button" class="qty-btn" onclick="changeQuantity(1)">
              <i class="fas fa-plus"></i>
            </button>
          </div>
        </div>

        <input type="hidden" name="gh_name" value="<?php echo htmlspecialchars($product['ten_nam'] ?? ''); ?>" />
        <input type="hidden" name="img" value="<?php echo htmlspecialchars($product['hanh_nam'] ?? 'default_image.jpg'); ?>" />
        <input type="hidden" name="price" value="<?php echo htmlspecialchars($product['gia_nam'] ?? 0); ?>" />
        <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer_id ?? ''); ?>" />

        <button type="submit" class="add-to-cart-btn">
          <i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng
        </button>
      </form>

      <div class="product-features">
        <div class="feature-item">
          <i class="fas fa-shipping-fast feature-icon"></i>
          <span>Miễn phí vận chuyển cho đơn hàng trên 500.000₫</span>
        </div>
        <div class="feature-item">
          <i class="fas fa-undo feature-icon"></i>
          <span>Đổi trả trong vòng 30 ngày</span>
        </div>
        <div class="feature-item">
          <i class="fas fa-certificate feature-icon"></i>
          <span>Bảo hành chính hãng 12 tháng</span>
        </div>
        <div class="feature-item">
          <i class="fas fa-headset feature-icon"></i>
          <span>Hỗ trợ khách hàng 24/7</span>
        </div>
      </div>
    </div>
  </div>

  <div class="similar-products">
    <h2 class="section-title">Sản phẩm tương tự</h2>
    <div class="similar-grid">
      <?php while ($similar_product = $similar_result->fetch_assoc()): ?>
        <div class="similar-product-card">
          <img src="<?php echo htmlspecialchars($similar_product['hanh_nam'] ?? 'default_image.jpg'); ?>" 
               alt="<?php echo htmlspecialchars($similar_product['ten_nam'] ?? 'Product'); ?>" 
               class="similar-product-image" />
          <div class="similar-product-info">
            <h3 class="similar-product-title"><?php echo htmlspecialchars($similar_product['ten_nam'] ?? 'Sản phẩm'); ?></h3>
            <div class="similar-product-price">
              <?php echo number_format($similar_product['gia_nam'] ?? 0, 0, ',', '.'); ?>₫
            </div>
            <button class="similar-product-btn" onclick="window.location.href='?go=add_to_cart_n&id=<?php echo $similar_product['id']; ?>'">
              Xem chi tiết
            </button>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<script>
function changeImage(element) {
  const thumbnails = document.querySelectorAll('.thumbnail-img');
  thumbnails.forEach(img => img.classList.remove('active'));
  element.classList.add('active');
  const src = element.getAttribute('data-src');
  document.getElementById('mainPreview').src = src;
}

function selectSize(element, size) {
  const sizeOptions = document.querySelectorAll('.size-option');
  sizeOptions.forEach(option => option.classList.remove('selected'));
  element.classList.add('selected');
  document.getElementById('selectedSize').value = size;
}

function changeQuantity(delta) {
  const quantityInput = document.getElementById('quantity');
  let currentValue = parseInt(quantityInput.value);
  let newValue = currentValue + delta;
  if (newValue >= 1) {
    quantityInput.value = newValue;
  }
}

setTimeout(() => {
  const popup = document.getElementById('cartPopup');
  if (popup) {
    popup.style.opacity = '0';
    popup.style.transform = 'translateX(100px)';
    setTimeout(() => popup.remove(), 300);
  }
}, 5000);
</script>

<?php if ($showPopup): ?>
  <div class="enhanced-popup" id="cartPopup">
    <div class="popup-header">
      <div class="popup-success">
        <i class="fas fa-check-circle"></i>
        <span>Đã thêm vào giỏ hàng!</span>
      </div>
      <button class="popup-close" onclick="document.getElementById('cartPopup').remove()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <div class="popup-content">
      <img src="<?php echo htmlspecialchars($popupData['img']); ?>" 
           alt="<?php echo htmlspecialchars($popupData['gh_name']); ?>" 
           class="popup-image" />
      <div class="popup-details">
        <div class="popup-product-name"><?php echo htmlspecialchars($popupData['gh_name']); ?></div>
        <div>Size: <?php echo htmlspecialchars($popupData['size']); ?></div>
        <div>Số lượng: <?php echo htmlspecialchars($popupData['sl']); ?></div>
        <div style="font-weight: 600; color: #e60023;">
          <?php echo number_format($popupData['price'], 0, ',', '.'); ?>₫
        </div>
      </div>
    </div>
    
    <div class="popup-actions">
      <a href="?go=cart" class="popup-btn popup-btn-view">
        <i class="fas fa-shopping-cart"></i> Xem giỏ hàng
      </a>
      <a href="?go=checkout" class="popup-btn popup-btn-checkout">
        <i class="fas fa-credit-card"></i> Thanh toán
      </a>
    </div>
  </div>
<?php endif; ?>

</body>
</html>
<?php
// Close database connection
$conn->close();
?>
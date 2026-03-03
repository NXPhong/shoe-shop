<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('dbconnect.php');

if (isset($_GET['go']) && $_GET['go'] === 'logout') {
    session_unset();
    session_destroy();

    session_start();
    $_SESSION['logout_message'] = 'Bạn đã đăng xuất thành công';

    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$logoutMessage = '';
if (isset($_SESSION['logout_message'])) {
    $logoutMessage = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Phùng Đức Long</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    .custom-alert {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      border-radius: 8px;
      padding: 12px 50px 12px 20px;
      font-size: 16px;
      font-weight: 500;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      max-width: 90%;
      z-index: 9999;
      display: flex;
      align-items: center;
      animation: slideInCheck 0.5s ease forwards;
    }

    .custom-alert .checkmark {
      position: absolute;
      right: 20px;
      font-size: 20px;
      color: #28a745;
      opacity: 0;
      animation: checkmarkFade 0.5s ease forwards;
      animation-delay: 0.2s;
    }

    @keyframes checkmarkFade {
      0% { opacity: 0; transform: translateX(20px); }
      100% { opacity: 1; transform: translateX(0); }
    }

    @keyframes slideInCheck {
      0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
      100% { opacity: 1; transform: translateX(-50%) translateY(0); }
    }

    .dropdown-custom {
      position: relative;
      display: inline-block;
      margin-left: 10px;
      cursor: pointer;
    }

    .dropdown-toggle-custom {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 500;
      color: #333;
    }

    .dropdown-toggle-custom img {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      object-fit: cover;
    }

    .dropdown-menu-custom {
      display: none;
      position: absolute;
      top: 120%;
      right: 0;
      background-color: #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-radius: 8px;
      min-width: 180px;
      z-index: 999;
      padding: 8px 0;
    }

    .dropdown-menu-custom a {
      display: flex;
      align-items: center;
      padding: 10px 16px;
      font-size: 14px;
      color: #333;
      text-decoration: none;
      gap: 10px;
      transition: background 0.3s;
    }

    .dropdown-menu-custom a:hover {
      background-color: #f2f2f2;
    }

    .dropdown-divider-custom {
      height: 1px;
      background-color: #e0e0e0;
      margin: 4px 0;
    }
  </style>
</head>
<body>

<?php if ($logoutMessage): ?>
  <div id="logout-alert" class="custom-alert">
    <span class="checkmark">&#10003;</span>
    <?= htmlspecialchars($logoutMessage) ?>
  </div>
<?php endif; ?>

<section1>
  <nav>
    <div class="logo">
      <a href="?go=home">
        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg" alt="Nike Logo">
      </a>
    </div>

    <ul>
      <li><a href="?go=MoiNb">MỚI & NỔI BẬT</a></li>
      <li><a href="?go=Nam">NAM</a></li>
      <li><a href="?go=nu">NỮ</a></li>
      <li><a href="#Review">THỂ THAO</a></li>
      <li><a href="#Servises">GIẢM GIÁ</a></li>
    </ul>

    <div class="icons">
      <a href="?go=order_success"><i class="fa-solid fa-heart"></i></a>
      <a href="?go=cart"><i class="fa-solid fa-cart-shopping"></i></a>
      <?php if (isset($_SESSION['customer_fullName'])): ?>
        <div class="dropdown-custom">
          <div class="dropdown-toggle-custom">
            <span><?= htmlspecialchars($_SESSION['customer_fullName']) ?></span>
            <img src="image/undraw_profile.svg" alt="Avatar">
          </div>
          <div class="dropdown-menu-custom">
            <a href="?go=user"><i class="fas fa-user"></i> Profile</a>
            <a href="#"><i class="fas fa-cogs"></i> Settings</a>
            <a href="#"><i class="fas fa-list"></i> Activity Log</a>
            <div class="dropdown-divider-custom"></div>
            <a href="?go=logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" style="text-decoration:none;"><i class="fa-solid fa-user"></i> Login</a>
      <?php endif; ?>
    </div>
  </nav>
</section1>

<script>
  // Tự động ẩn thông báo sau 3 giây
  setTimeout(() => {
    const alert = document.getElementById('logout-alert');
    if (alert) {
      alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      alert.style.opacity = '0';
      alert.style.transform = 'translateX(-50%) translateY(-20px)';
      setTimeout(() => alert.remove(), 500);
    }
  }, 3000);

  // Dropdown toggle bằng click
  document.addEventListener("DOMContentLoaded", function () {
    const dropdownToggle = document.querySelector(".dropdown-toggle-custom");
    const dropdownMenu = document.querySelector(".dropdown-menu-custom");

    if (dropdownToggle && dropdownMenu) {
      dropdownToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
      });

      document.addEventListener("click", function () {
        dropdownMenu.style.display = "none";
      });

      dropdownMenu.addEventListener("click", function (e) {
        e.stopPropagation();
      });
    }
  });
</script>

</body>
</html>

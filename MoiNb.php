<?php
include('dbconnect.php'); 

// Lấy các giá trị lọc từ form
$price = isset($_GET['price']) ? $_GET['price'] : '';
$size = isset($_GET['size']) ? $_GET['size'] : '';
$color = isset($_GET['color']) ? $_GET['color'] : '';
$danhmuc = isset($_GET['danhmuc']) ? $_GET['danhmuc'] : '';

// Điều kiện SQL
$sql = "SELECT * FROM moi WHERE 1=1";

if ($danhmuc != '') {
    $sql .= " AND danhmuc = '$danhmuc'";
}

if ($price == "1") {
    $sql .= " AND gia_nb < 500000";
} elseif ($price == "2") {
    $sql .= " AND gia_nb BETWEEN 500000 AND 1000000";
} elseif ($price == "3") {
    $sql .= " AND gia_nb > 1000000";
}

if ($size != '') {
    $sql .= " AND size = '$size'";
}

if ($color != '') {
    $sql .= " AND color = '$color'";
}

$result = $conn->query($sql);
$total_products = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giày Nam - Nike</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
            }
        }
        
        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0) !important;
                position: relative !important;
            }
        }
        
        .sidebar-overlay {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease-in-out;
        }
        
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .product-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .product-card .hover-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.3s ease;
        }
        
        .product-card:hover .hover-actions {
            opacity: 1;
            transform: translateX(0);
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .action-btn:hover {
            background: #ff4500;
            color: white;
            transform: scale(1.1);
        }
        
        .filter-input {
            background: rgba(249, 250, 251, 0.8);
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #ff4500;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 69, 0, 0.1);
        }
        
        .filter-btn {
            background: linear-gradient(135deg, #ff4500 0%, #ff6b35 100%);
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, 0.3);
        }
        
        .clear-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            transition: all 0.3s ease;
        }
        
        .clear-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(107, 114, 128, 0.3);
        }
        
        .category-chip {
            background: rgba(255, 69, 0, 0.1);
            color: #ff4500;
            border: 1px solid rgba(255, 69, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .category-chip:hover {
            background: #ff4500;
            color: white;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 600;
        }
        
        .product-title {
            color: #1f2937;
            font-weight: 600;
            transition: color 0.3s ease;
            /* Đảm bảo tiêu đề luôn chiếm đúng 2 dòng */
            height: 3rem; /* 48px - chiều cao cho 2 dòng */
            line-height: 1.5rem; /* 24px - chiều cao mỗi dòng */
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            /* Đảm bảo luôn có đủ không gian cho 2 dòng */
            min-height: 3rem;
        }
        
        .product-card:hover .product-title {
            color: #ff4500;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
            
            .product-title {
                height: 3rem;
                line-height: 1.4rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-40">
        <div class="px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button id="filterToggle" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <button id="filterToggleDesktop" class="hidden lg:block p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-filter text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3">
                        <div class="bg-black rounded-full p-2">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.8 5.8C2 7.2 2 9.2 2 13.2s0 6 .8 7.4c.7 1.2 1.8 1.8 3.2 1.8 1.4 0 2.5-.6 3.2-1.8.8-1.4.8-3.4.8-7.4s0-6-.8-7.4C8.5 4.6 7.4 4 6 4s-2.5.6-3.2 1.8zm12 0C14 7.2 14 9.2 14 13.2s0 6 .8 7.4c.7 1.2 1.8 1.8 3.2 1.8s2.5-.6 3.2-1.8c.8-1.4.8-3.4.8-7.4s0-6-.8-7.4C20.5 4.6 19.4 4 18 4s-2.5.6-3.2 1.8z"/>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">Mới & Nổi bật</h1>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-shoe-prints mr-1"></i>
                        <?= $total_products ?> sản phẩm
                    </span>
                    <button class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors" id="filterToggleMobile">
                        <i class="fas fa-filter text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="flex relative">
        <!-- Sidebar Overlay -->
        <div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"></div>
        
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar fixed lg:static w-80 h-screen lg:h-auto bg-white shadow-lg z-40 overflow-y-auto lg:block">
            <div class="p-6">
                <!-- Close button for mobile -->
                <div class="flex items-center justify-between mb-6 lg:hidden">
                    <h2 class="text-xl font-bold text-gray-900">Bộ lọc</h2>
                    <button id="closeSidebar" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Categories -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Danh mục thể thao</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Phong cách sống</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Jordan</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Chạy bộ</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Bóng rổ</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Bóng đá</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Gym</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Trượt ván</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Golf</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Tennis</button>
                        <button class="category-chip px-3 py-2 text-sm rounded-lg border transition-all">Điền kinh</button>
                    </div>
                </div>

                <!-- Filter Form -->
                <form method="GET" action="" id="filterForm" class="space-y-6">
                    <!-- Gender Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-venus-mars mr-2 text-orange-500"></i>Giới tính
                        </label>
                        <select name="danhmuc" class="filter-input w-full">
                            <option value="">Tất cả</option>
                            <option value="Nam" <?= $danhmuc == 'Nam' ? 'selected' : '' ?>>Nam</option>
                            <option value="Nu" <?= $danhmuc == 'Nu' ? 'selected' : '' ?>>Nữ</option>
                        </select>
                    </div>

                    <!-- Price Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-tag mr-2 text-orange-500"></i>Mức giá
                        </label>
                        <select name="price" class="filter-input w-full">
                            <option value="">Tất cả mức giá</option>
                            <option value="1" <?= $price == '1' ? 'selected' : '' ?>>Dưới 500.000₫</option>
                            <option value="2" <?= $price == '2' ? 'selected' : '' ?>>500.000₫ - 1.000.000₫</option>
                            <option value="3" <?= $price == '3' ? 'selected' : '' ?>>Trên 1.000.000₫</option>
                        </select>
                    </div>

                    <!-- Size Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-ruler mr-2 text-orange-500"></i>Kích cỡ
                        </label>
                        <select name="size" class="filter-input w-full">
                            <option value="">Tất cả size</option>
                            <option value="S" <?= $size == 'S' ? 'selected' : '' ?>>Size S</option>
                            <option value="M" <?= $size == 'M' ? 'selected' : '' ?>>Size M</option>
                            <option value="L" <?= $size == 'L' ? 'selected' : '' ?>>Size L</option>
                        </select>
                    </div>

                    <!-- Color Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-palette mr-2 text-orange-500"></i>Màu sắc
                        </label>
                        <select name="color" class="filter-input w-full">
                            <option value="">Tất cả màu</option>
                            <option value="Đen" <?= $color == 'Đen' ? 'selected' : '' ?>>Đen</option>
                            <option value="Trắng" <?= $color == 'Trắng' ? 'selected' : '' ?>>Trắng</option>
                            <option value="Xanh" <?= $color == 'Xanh' ? 'selected' : '' ?>>Xanh</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="filter-btn flex-1 text-white py-3 px-4 rounded-lg font-medium">
                            <i class="fas fa-search mr-2"></i>Áp dụng
                        </button>
                        <button type="button" id="clearFilters" class="clear-btn flex-1 text-white py-3 px-4 rounded-lg font-medium">
                            <i class="fas fa-eraser mr-2"></i>Xóa lọc
                        </button>
                    </div>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 lg:ml-0">
            <!-- Active Filters Display -->
            <div id="activeFilters" class="mb-6"></div>

            <!-- Products Grid -->
            <div class="grid-container">
                <?php 
                $counter = 0;
                while ($row = $result->fetch_assoc()) { 
                    $counter++;
                ?>
                    <div class="product-card animate-fade-in bg-white rounded-2xl overflow-hidden shadow-sm" style="animation-delay: <?= $counter * 0.1 ?>s">
                        <!-- Hover Actions -->
                        <div class="hover-actions">
                            <button class="action-btn" title="Thêm vào yêu thích">
                                <i class="fas fa-heart"></i>
                            </button>
                            <button class="action-btn" title="Thêm vào giỏ hàng">
                                <i class="fas fa-cart-plus"></i>
                            </button>
                        </div>

                        <!-- Product Image -->
                        <a href="?go=add_to_cart&id=<?= $row['id'] ?>" class="block">
                            <div class="aspect-square overflow-hidden bg-gray-100">
                                <img src="<?= $row['hanh_nb'] ?>" 
                                     alt="<?= $row['ten_nb'] ?>" 
                                     class="w-full h-full object-cover transition-transform duration-300 hover:scale-105"
                                     loading="lazy">
                            </div>
                        </a>

                        <!-- Product Info -->
                        <div class="p-4">
                            <h3 class="product-title text-lg mb-2"><?= $row['ten_nb'] ?></h3>
                            
                            <div class="flex items-center justify-between mb-3">
                                <div class="price-badge px-3 py-1 rounded-full text-sm">
                                    <?= number_format($row['gia_nb'], 0, ',', '.') ?>₫
                                </div>
                                <div class="flex items-center space-x-1 text-sm text-gray-500">
                                    <i class="fas fa-star text-yellow-400"></i>
                                    <span>4.8</span>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 line-clamp-2 mb-4"><?= $row['mota_nb'] ?></p>

                            <button class="w-full bg-black text-white py-2.5 px-4 rounded-lg font-medium hover:bg-gray-800 transition-colors">
                                <i class="fas fa-shopping-bag mr-2"></i>Thêm vào giỏ
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- No Products Message -->
            <?php if ($total_products == 0): ?>
                <div class="text-center py-16">
                    <div class="mb-4">
                        <i class="fas fa-search text-6xl text-gray-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Không tìm thấy sản phẩm</h3>
                    <p class="text-gray-500 mb-6">Thử điều chỉnh bộ lọc để xem thêm sản phẩm</p>
                    <button id="clearAllFilters" class="bg-orange-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-orange-600 transition-colors">
                        <i class="fas fa-refresh mr-2"></i>Xóa tất cả bộ lọc
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // DOM Elements
        const filterToggle = document.getElementById('filterToggle');
        const filterToggleDesktop = document.getElementById('filterToggleDesktop');
        const filterToggleMobile = document.getElementById('filterToggleMobile');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const closeSidebar = document.getElementById('closeSidebar');
        const clearFilters = document.getElementById('clearFilters');
        const clearAllFilters = document.getElementById('clearAllFilters');
        const activeFilters = document.getElementById('activeFilters');

        // Toggle Sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('show');
            
            // Change icon
            const icon = filterToggleDesktop?.querySelector('i') || filterToggle?.querySelector('i');
            if (sidebar.classList.contains('open')) {
                icon.className = 'fas fa-times text-xl';
            } else {
                icon.className = 'fas fa-filter text-xl';
            }
        }

        function closeSidebarFunc() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        }

        // Event Listeners
        filterToggle?.addEventListener('click', toggleSidebar);
        filterToggleDesktop?.addEventListener('click', toggleSidebar);
        filterToggleMobile?.addEventListener('click', toggleSidebar);
        closeSidebar?.addEventListener('click', closeSidebarFunc);
        sidebarOverlay?.addEventListener('click', closeSidebarFunc);

        // Clear Filters
        clearFilters?.addEventListener('click', function() {
            const form = document.getElementById('filterForm');
            const selects = form.querySelectorAll('select');
            selects.forEach(select => select.value = '');
            updateActiveFilters();
        });

        clearAllFilters?.addEventListener('click', function() {
            window.location.href = window.location.pathname;
        });

        // Update Active Filters Display
        function updateActiveFilters() {
            const params = new URLSearchParams(window.location.search);
            const filters = [];
            
            if (params.get('danhmuc')) {
                filters.push({ label: 'Giới tính', value: params.get('danhmuc'), param: 'danhmuc' });
            }
            if (params.get('price')) {
                const priceLabels = { '1': 'Dưới 500k', '2': '500k - 1tr', '3': 'Trên 1tr' };
                filters.push({ label: 'Giá', value: priceLabels[params.get('price')] || params.get('price'), param: 'price' });
            }
            if (params.get('size')) {
                filters.push({ label: 'Size', value: params.get('size'), param: 'size' });
            }
            if (params.get('color')) {
                filters.push({ label: 'Màu', value: params.get('color'), param: 'color' });
            }

            if (filters.length > 0) {
                activeFilters.innerHTML = `
                    <div class="flex items-center flex-wrap gap-2 mb-4">
                        <span class="text-sm font-medium text-gray-700">Bộ lọc đang áp dụng:</span>
                        ${filters.map(filter => `
                            <span class="inline-flex items-center px-3 py-1 bg-orange-100 text-orange-800 text-sm rounded-full">
                                <span class="font-medium">${filter.label}:</span>
                                <span class="ml-1">${filter.value}</span>
                                <button onclick="removeFilter('${filter.param}')" class="ml-2 hover:text-orange-600">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </span>
                        `).join('')}
                        <button onclick="clearAllActiveFilters()" class="text-sm text-gray-500 hover:text-gray-700 underline ml-2">
                            Xóa tất cả
                        </button>
                    </div>
                `;
            } else {
                activeFilters.innerHTML = '';
            }
        }

        // Remove specific filter
        function removeFilter(param) {
            const url = new URL(window.location);
            url.searchParams.delete(param);
            window.location.href = url.toString();
        }

        // Clear all active filters
        function clearAllActiveFilters() {
            window.location.href = window.location.pathname;
        }

        // Auto-submit form on change
        document.querySelectorAll('#filterForm select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });

        // Initialize active filters display
        updateActiveFilters();

        // Handle responsive sidebar
        function handleResize() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            }
        }

        window.addEventListener('resize', handleResize);

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scroll behavior
            document.documentElement.style.scrollBehavior = 'smooth';
            
            // Handle ESC key to close sidebar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSidebarFunc();
                }
            });
        });
    </script>
</body>
</html>
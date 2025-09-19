<?php
    require_once 'auth.php';
    requireLogin();
    $currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Quản lý kho'; ?></title>

    <link rel="stylesheet" href="../css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            width: 250px;
            padding: 1rem;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .main-content {
            margin-left: 0;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        @media (min-width: 768px) {
            .main-content {
                margin-left: 250px;
            }
        }
        @media (max-width: 767px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .sidebar.show::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="position-sticky pt-3">
           <div class="text-center text-white mb-4">
                <h5 class="d-flex align-items-center justify-content-center">
                    <img src="../img/logo.png" alt="Logo" style="height: 100px; margin-right: 2px;">
                    Quản lý kho
                </h5>
                <small>Xin chào, <?php echo htmlspecialchars($currentUser['full_name']); ?></small>
            </div>

            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box"></i> Sản phẩm
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="stock_in.php">
                        <i class="fas fa-arrow-down"></i> Nhập kho
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="stock_out.php">
                        <i class="fas fa-arrow-up"></i> Xuất kho
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="transactions.php">
                        <i class="fas fa-list"></i> Lịch sử giao dịch
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i> Báo cáo
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="suppliers.php">
                        <i class="fas fa-truck"></i> Nhà cung cấp
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="categories.php">
                        <i class="fas fa-list"></i> Danh mục sản phẩm
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="zones.php">
                        <i class="fas fa-warehouse"></i> Quản lý khu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="staffs.php">
                        <i class="fas fa-user"></i> Danh sách nhân viên
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item mt-3">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main content container -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Mobile menu toggle -->
            <button class="btn btn-primary d-md-none mb-3" type="button" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i> Menu
            </button>

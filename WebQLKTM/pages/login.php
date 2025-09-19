<?php
    require_once '../config/database.php';
    require_once '../includes/auth.php';

    if (isLoggedIn()) {
        header('Location: index.php');
        exit();
    }

    $error = '';

    if ($_POST) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT id, username, password, full_name, role FROM users WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Kiểm tra hash hoặc plain text
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: index.php');
                    exit();
                }
            }
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng!';
        }
    }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Quản lý kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h3 class="login-heading">
                <i class="fas fa-warehouse me-2"></i>Đăng nhập hệ thống
            </h3>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label">Tên đăng nhập</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <button type="submit" class="btn-login w-100">
                    <i class="fas fa-sign-in-alt me-1"></i> Đăng nhập
                </button>
            </form>
            <div class="text-end mt-3">
                <a href="register.php" class="btn btn-outline-success">
                    <i class="fas fa-user-plus"></i> Đăng ký
                </a>
            </div>

        </div>
    </div>
</body>
</html>


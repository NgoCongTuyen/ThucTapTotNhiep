<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');
    $role = 'staff';

    if ($username && $full_name && $password) {
        if ($password !== $confirm) {
            $error = "Mật khẩu nhập lại không khớp!";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();

                // kiểm tra trùng username
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = "Tên đăng nhập đã tồn tại!";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (username, password, full_name, role, created_at) 
                              VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $hash, $full_name, $role]);
                    $success = "Đăng ký thành công! Bạn có thể đăng nhập.";
                }
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra: " . $e->getMessage();
            }
        }
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Quản lý kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
<div class="login-container">
    <div class="login-box">
        <h3 class="login-heading">
            <i class="fas fa-user-plus me-2"></i>Đăng ký tài khoản
        </h3>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success text-center"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Tên đăng nhập *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" name="username" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Họ và tên *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                    <input type="text" class="form-control" name="full_name" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Nhập lại mật khẩu *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="confirm" required>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100">
                <i class="fas fa-user-plus me-1"></i> Đăng ký
            </button>

            <div class="text-center mt-3">
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Đã có tài khoản? Đăng nhập</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>

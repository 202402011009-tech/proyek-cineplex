<?php
// Simpan dengan nama: login.php
session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) { header("Location: index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin123') {
        $_SESSION['logged_in'] = true; header("Location: index.php"); exit;
    } else { $error = "Kredensial tidak valid!"; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Cineplex Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: url('https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=2070') center/cover no-repeat; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(10,10,10,0.85); z-index: 1; backdrop-filter: blur(5px); }
        .login-box { background: linear-gradient(145deg, #1a1a1a, #0a0a0a); border: 1px solid #e50914; padding: 50px 40px; border-radius: 15px; width: 100%; max-width: 420px; z-index: 2; box-shadow: 0 0 30px rgba(229, 9, 20, 0.3); }
        .logo-cinema { color: #e50914; font-size: 32px; font-weight: 900; text-align: center; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 30px;}
        .form-control { background: rgba(255,255,255,0.05); border: 1px solid #333; color: white; padding: 12px 20px; border-radius: 8px;}
        .form-control:focus { background: rgba(255,255,255,0.1); border-color: #e50914; color: white; box-shadow: 0 0 10px rgba(229,9,20,0.5);}
        .btn-cinema { background: #e50914; color: white; font-weight: bold; width: 100%; padding: 12px; border: none; border-radius: 8px; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s;}
        .btn-cinema:hover { background: #b80710; transform: scale(1.02); }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="login-box">
        <div class="logo-cinema"><i class="fa-solid fa-ticket-simple me-2"></i>CinePlex HQ</div>
        <?php if($error): ?><div class="alert alert-danger bg-danger text-white border-0 py-2 text-center"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="text-white-50 mb-2 small"><i class="fa-solid fa-user me-2"></i>ID Karyawan</label>
                <input type="text" name="username" class="form-control" placeholder="admin" required>
            </div>
            <div class="mb-5">
                <label class="text-white-50 mb-2 small"><i class="fa-solid fa-lock me-2"></i>Kata Sandi</label>
                <input type="password" name="password" class="form-control" placeholder="admin123" required>
            </div>
            <button type="submit" class="btn-cinema">Buka Sistem Kasir</button>
        </form>
    </div>
</body>
</html>
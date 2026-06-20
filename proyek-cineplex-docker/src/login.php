<?php
session_start();
// Jika tombol login ditekan
if(isset($_POST['login'])){
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = htmlspecialchars($_POST['username']);
    
    // Sistem Multi-User Cerdas: Jika ketik 'admin', jadi admin. Sisanya jadi pelanggan.
    if(strtolower($_POST['username']) === 'admin'){
        $_SESSION['role'] = 'admin';
    } else {
        $_SESSION['role'] = 'customer';
    }
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - CinePlex HQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0a0a0a; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .login-box { background: #151515; padding: 40px; border-radius: 15px; border-top: 5px solid #e50914; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        .form-control { background: #000 !important; color: #fff !important; border: 1px solid #333; padding: 12px; }
        .form-control:focus { border-color: #e50914; box-shadow: 0 0 10px rgba(229,9,20,0.3); }
        .btn-login { background: #e50914; color: white; border: none; padding: 12px; font-weight: bold; font-size: 16px; border-radius: 8px; transition: 0.3s; }
        .btn-login:hover { background: #b0060f; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="text-center mb-4">
            <i class="fa-solid fa-film" style="color: #e50914; font-size: 40px; margin-bottom: 10px;"></i>
            <h3 class="fw-bold" style="letter-spacing: 1px;">CINEPLEX SYSTEM</h3>
        </div>
        
        <div class="alert bg-dark border-secondary text-center text-muted small" style="font-size: 12px;">
            <i class="fa-solid fa-circle-info text-warning me-1"></i> <b>SISTEM MULTI-USER AKTIF</b><br>
            Ketik <b>admin</b> untuk akses penuh, atau ketik <b>nama bebas (daftar baru)</b> untuk akses Pengunjung.
        </div>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="small fw-bold text-muted mb-2">Username / Nama Anda</label>
                <input type="text" name="username" class="form-control" required placeholder="Contoh: admin atau budi...">
            </div>
            <div class="mb-4">
                <label class="small fw-bold text-muted mb-2">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Bebas isi apa saja...">
            </div>
            <button type="submit" name="login" class="btn btn-login w-100"><i class="fa-solid fa-right-to-bracket me-2"></i> MASUK APLIKASI</button>
        </form>
    </div>
</body>
</html>
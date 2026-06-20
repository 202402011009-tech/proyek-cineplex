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
    <title>Login - CinePlex System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            margin: 0; 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            overflow: hidden;
            background-color: #000;
        }
        
        /* Animasi Background Slideshow (Wallpaper Bergerak) */
        .bg-slideshow {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -2;
            background-size: cover;
            background-position: center;
            animation: slideBg 20s infinite alternate;
        }
        
        /* Efek gelap transparan agar tulisan login tetap jelas terbaca */
        .bg-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.9));
            z-index: -1;
        }
        
        /* Daftar Gambar Wallpaper (Mengambil dari Unsplash) */
        @keyframes slideBg {
            0%   { background-image: url('https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=2070'); transform: scale(1); }
            25%  { background-image: url('https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=2070'); transform: scale(1.05); }
            26%  { background-image: url('https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=2070'); transform: scale(1.05); }
            50%  { background-image: url('https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=2070'); transform: scale(1); }
            51%  { background-image: url('https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=2025'); transform: scale(1); }
            75%  { background-image: url('https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=2025'); transform: scale(1.05); }
            76%  { background-image: url('https://images.unsplash.com/photo-1598899134739-24c46f58b8c0?q=80&w=2056'); transform: scale(1.05); }
            100% { background-image: url('https://images.unsplash.com/photo-1598899134739-24c46f58b8c0?q=80&w=2056'); transform: scale(1); }
        }

        /* Efek Kaca (Glassmorphism) Mewah pada Kotak Login */
        .login-box { 
            position: relative;
            background: rgba(15, 15, 15, 0.6); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 45px 40px; 
            border-radius: 20px; 
            border-top: 5px solid #e50914; 
            border-bottom: 1px solid rgba(255,255,255,0.1);
            border-left: 1px solid rgba(255,255,255,0.1);
            border-right: 1px solid rgba(255,255,255,0.1);
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.8); 
        }
        
        .form-control { 
            background: rgba(0, 0, 0, 0.6) !important; 
            color: #fff !important; 
            border: 1px solid #444; 
            padding: 12px; 
            border-radius: 8px;
        }
        .form-control:focus { 
            border-color: #e50914; 
            box-shadow: 0 0 15px rgba(229,9,20,0.5); 
            outline: none; 
        }
        .btn-login { 
            background: #e50914; 
            color: white; 
            border: none; 
            padding: 14px; 
            font-weight: bold; 
            font-size: 16px; 
            border-radius: 8px; 
            transition: 0.3s; 
            letter-spacing: 1px;
        }
        .btn-login:hover { 
            background: #b0060f; 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(229,9,20,0.5); 
            color: white;
        }
    </style>
</head>
<body>
    <!-- Pemanggil Animasi Background -->
    <div class="bg-slideshow"></div>
    <div class="bg-overlay"></div>
    
    <div class="login-box">
        <div class="text-center mb-5">
            <i class="fa-solid fa-film" style="color: #e50914; font-size: 45px; margin-bottom: 15px; filter: drop-shadow(0 0 10px rgba(229,9,20,0.6));"></i>
            <h3 class="fw-bold m-0" style="letter-spacing: 2px;">CINEPLEX</h3>
            <p class="text-muted small m-0" style="letter-spacing: 5px;">SYSTEM</p>
        </div>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="small fw-bold text-light mb-2">Username</label>
                <!-- Tulisan bayangan/placeholder sudah dihilangkan -->
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-5">
                <label class="small fw-bold text-light mb-2">Password</label>
                <!-- Tulisan bayangan/placeholder sudah dihilangkan -->
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-login w-100">LOGIN <i class="fa-solid fa-arrow-right ms-2"></i></button>
        </form>
    </div>
</body>
</html>
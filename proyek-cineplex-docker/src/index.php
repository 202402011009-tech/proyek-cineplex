<?php
// ==========================================
// BACK-END API & DASHBOARD
// ==========================================
session_start();
if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

$host = "db"; $user = "root"; $pass = "rootsecurepwd123"; $db = "cinema_dw";

// VARIABEL MULTI-USER DARI LOGIN
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'customer';
$nama_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Pengunjung';

// --- JURUS RAHASIA: AUTO-CREATE DATABASE & TABLES ---
try {
    $conn_init = new mysqli($host, $user, $pass);
    $conn_init->query("CREATE DATABASE IF NOT EXISTS `$db`");
    $conn_init->select_db($db);

    $conn_init->query("CREATE TABLE IF NOT EXISTS dim_film (id_film INT AUTO_INCREMENT PRIMARY KEY, judul_film VARCHAR(100))");
    $conn_init->query("CREATE TABLE IF NOT EXISTS dim_tipe_tiket (id_tipe INT AUTO_INCREMENT PRIMARY KEY, nama_tipe VARCHAR(50), harga INT)");
    $conn_init->query("CREATE TABLE IF NOT EXISTS dim_cabang (id_cabang INT AUTO_INCREMENT PRIMARY KEY, nama_cabang VARCHAR(50), kota_cabang VARCHAR(50))");
    $conn_init->query("CREATE TABLE IF NOT EXISTS fakta_penjualan (id_fakta INT AUTO_INCREMENT PRIMARY KEY, id_film INT, id_cabang INT, id_tipe INT, jumlah_tiket INT, total_pendapatan INT, waktu_transaksi DATETIME)");

    $cek = $conn_init->query("SELECT COUNT(*) as c FROM dim_film")->fetch_assoc()['c'];
    if($cek == 0) {
        $conn_init->query("INSERT INTO dim_film (judul_film) VALUES ('FAST X'), ('JOHN WICK: CHAPTER 4'), ('OPPENHEIMER'), ('THE SUPER MARIO BROS'), ('EVIL DEAD RISE'), ('GUARDIANS OF THE GALAXY 3'), ('SPIDER-MAN: ACROSS THE SPIDER-VERSE'), ('MISSION: IMPOSSIBLE - DEAD RECKONING')");
        $conn_init->query("INSERT INTO dim_tipe_tiket (nama_tipe, harga) VALUES ('Studio 1', 40000), ('Studio 2', 40000), ('Studio 3', 45000), ('Studio 4', 45000), ('Studio 5', 50000), ('Studio 6', 50000), ('Studio 7', 55000), ('VVIP Premiere', 120000)");
        $conn_init->query("INSERT INTO dim_cabang (nama_cabang, kota_cabang) VALUES ('Cineplex Pusat', 'Jakarta'), ('Cineplex Cabang', 'Bandung'), ('Cineplex East', 'Surabaya')");
    }
    $conn_init->close();
} catch (Exception $e) { /* Abaikan jika sudah ada */ }
// ----------------------------------------------------

// API UNTUK MEMPROSES PEMBELIAN TIKET
if (isset($_GET['action']) && $_GET['action'] == 'buy_ticket') {
    header('Content-Type: application/json');
    try {
        $conn = new mysqli($host, $user, $pass, $db);
        $data = json_decode(file_get_contents('php://input'), true);
        
        $judul = $conn->real_escape_string($data['judul']);
        $studio = $conn->real_escape_string($data['studio']);
        $qty = (int)$data['qty'];
        $total = (int)$data['total'];
        
        $res_film = $conn->query("SELECT id_film FROM dim_film WHERE judul_film LIKE '%$judul%' LIMIT 1");
        $id_film = ($res_film->num_rows > 0) ? $res_film->fetch_assoc()['id_film'] : 1;

        $res_tipe = $conn->query("SELECT id_tipe FROM dim_tipe_tiket WHERE nama_tipe LIKE '%$studio%' LIMIT 1");
        $id_tipe = ($res_tipe->num_rows > 0) ? $res_tipe->fetch_assoc()['id_tipe'] : 1;

        $conn->query("INSERT INTO fakta_penjualan (id_film, id_cabang, id_tipe, jumlah_tiket, total_pendapatan, waktu_transaksi) 
                      VALUES ($id_film, 1, $id_tipe, $qty, $total, NOW())");

        echo json_encode(["success" => true]);
    } catch (Exception $e) { echo json_encode(["success" => false, "error" => $e->getMessage()]); }
    exit;
}

// API KHUSUS ADMIN (DASHBOARD & LAPORAN)
if (isset($_GET['action']) && $_GET['action'] == 'get_data') {
    header('Content-Type: application/json');
    if($role !== 'admin') { echo json_encode(["success"=>false, "error"=>"Unauthorized"]); exit; }

    $out = ["success"=>true, "kpi"=>["rev"=>0, "visitor"=>0, "rows"=>0], "table"=>[], "chart_studio"=>[], "chart_film"=>[]];
    try {
        $conn = new mysqli($host, $user, $pass, $db);
        
        if (!isset($_GET['limit']) || $_GET['limit'] != 'all') {
            $r_film = rand(1, 8); $r_cabang = rand(1, 3); $r_tipe = rand(1, 8); $r_qty = rand(1, 3);
            $get_harga = $conn->query("SELECT harga FROM dim_tipe_tiket WHERE id_tipe = $r_tipe");
            if ($get_harga && $get_harga->num_rows > 0) {
                $total = $r_qty * $get_harga->fetch_assoc()['harga'];
                $conn->query("INSERT INTO fakta_penjualan (id_film, id_cabang, id_tipe, jumlah_tiket, total_pendapatan, waktu_transaksi) 
                              VALUES ($r_film, $r_cabang, $r_tipe, $r_qty, $total, NOW())");
            }
        }

        $timeframe = isset($_GET['time']) ? $_GET['time'] : 'today';
        $time_cond = "DATE(p.waktu_transaksi) = CURDATE()"; 
        if ($timeframe == 'weekly') $time_cond = "p.waktu_transaksi >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        else if ($timeframe == 'monthly') $time_cond = "p.waktu_transaksi >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        else if ($timeframe == 'yearly') $time_cond = "p.waktu_transaksi >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        else if ($timeframe == '5years') $time_cond = "p.waktu_transaksi >= DATE_SUB(NOW(), INTERVAL 5 YEAR)";

        $kpi = $conn->query("SELECT SUM(jumlah_tiket) as v, SUM(total_pendapatan) as r, COUNT(*) as c FROM fakta_penjualan p WHERE $time_cond")->fetch_assoc();
        $out['kpi']['rev'] = (float)$kpi['r']; 
        $out['kpi']['visitor'] = (int)$kpi['v'];
        $out['kpi']['rows'] = (int)$kpi['c'];

        $limit_clause = "LIMIT 15";
        if (isset($_GET['limit']) && $_GET['limit'] == 'all') $limit_clause = ""; 

        $sql_tbl = "SELECT f.judul_film, t.nama_tipe, c.kota_cabang, p.jumlah_tiket, p.total_pendapatan, p.waktu_transaksi 
                    FROM fakta_penjualan p JOIN dim_film f ON p.id_film = f.id_film 
                    JOIN dim_tipe_tiket t ON p.id_tipe = t.id_tipe JOIN dim_cabang c ON p.id_cabang = c.id_cabang
                    WHERE $time_cond ORDER BY p.id_fakta DESC $limit_clause";
        $res = $conn->query($sql_tbl);
        while ($row = $res->fetch_assoc()) $out['table'][] = $row;

        $res2 = $conn->query("SELECT t.nama_tipe, SUM(p.total_pendapatan) as total FROM fakta_penjualan p JOIN dim_tipe_tiket t ON p.id_tipe = t.id_tipe WHERE $time_cond GROUP BY t.nama_tipe");
        while ($row = $res2->fetch_assoc()) $out['chart_studio'][] = $row;

        $res3 = $conn->query("SELECT f.judul_film, SUM(p.jumlah_tiket) as v FROM fakta_penjualan p JOIN dim_film f ON p.id_film = f.id_film WHERE $time_cond GROUP BY f.judul_film");
        while ($row = $res3->fetch_assoc()) $out['chart_film'][] = $row;

    } catch (Exception $e) { $out['success'] = false; }
    echo json_encode($out); exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>CinePlex HQ - <?php echo ucfirst($role); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        :root { --bg-dark: #0a0a0a; --bg-card: #151515; --c-red: #e50914; --c-gold: #d4af37; --text-main: #ffffff; --text-mut: #888; }
        body { background-color: var(--bg-dark); color: var(--text-main); font-family: 'Segoe UI', Tahoma, sans-serif; display: flex; overflow: hidden; margin: 0; }
        
        /* LAYOUT */
        .sidebar { width: 260px; background-color: #000; height: 100vh; padding: 20px 0; border-right: 1px solid #222; overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
        
        .main-content { flex: 1; padding: 30px; height: 100vh; overflow-y: auto; position: relative; }

        .brand-logo { color: var(--c-red); font-size: 24px; font-weight: 900; text-align: center; margin-bottom: 20px; letter-spacing: 1px;}
        .menu-item { padding: 12px 25px; color: var(--text-mut); text-decoration: none; display: block; font-weight: 600; transition: 0.3s; cursor: pointer; border-left: 4px solid transparent; font-size: 14px;}
        .menu-item:hover, .menu-item.active { background: rgba(229,9,20,0.1); color: var(--c-red); border-left: 4px solid var(--c-red); }
        .menu-item i { width: 25px; }

        /* HEADER DIUBAH AGAR BISA MENAMPUNG PEMUTAR MUSIK DI TENGAH/KANAN */
        .top-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 20px; margin-bottom: 30px; }
        .time-display { font-size: 24px; font-weight: 800; color: var(--c-gold); letter-spacing: 2px; line-height: 1;}
        .date-display { font-size: 14px; color: var(--text-mut); text-transform: uppercase; font-weight: bold; }

        .filter-group { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .btn-filter { background: var(--bg-card); color: var(--text-mut); border: 1px solid #333; padding: 8px 20px; border-radius: 30px; font-size: 14px; font-weight: bold; transition: 0.3s; white-space: nowrap; }
        .btn-filter.active { background: var(--c-red); color: white; border-color: var(--c-red); box-shadow: 0 0 15px rgba(229,9,20,0.4);}
        
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid #222; border-top: 3px solid var(--c-red); }
        .kpi-card h6 { color: #ffffff !important; font-size: 16px; font-weight: bold !important; letter-spacing: 0px; margin-bottom: 12px; text-transform: none; }
        .kpi-card h2 { margin-top: 10px; font-weight: 900; font-size: 32px; }
        
        .chart-box { background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid #222; margin-bottom: 30px; }
        .table-dark { background-color: var(--bg-card); }
        .table-dark th { background-color: #000; border-bottom: 2px solid var(--c-red) !important; color: #ffffff !important; font-weight: bold !important; padding: 15px; font-size: 14px; }
        .table-dark td { border-bottom: 1px solid #222; vertical-align: middle; padding: 15px; font-size: 14px; }
        .badge-studio { background: #222; color: var(--c-gold); border: 1px solid var(--c-gold); padding: 5px 10px; border-radius: 4px; font-size: 12px; }

        /* MENU JADWAL TAYANG */
        .movie-card { background: var(--bg-card); border-radius: 15px; overflow: hidden; border: 1px solid #222; position: relative; display: flex; flex-direction: column; height: 100%; transition: 0.3s;}
        .movie-card:hover { border-color: var(--c-red); transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        .movie-poster { width: 100%; height: 320px; object-fit: cover; border-bottom: 2px solid var(--c-red); background-color: #222; }
        .advance-badge { position: absolute; top: 15px; left: -5px; background: #00a896; color: white; padding: 5px 15px; font-size: 12px; font-weight: bold; border-radius: 0 15px 15px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.5); z-index: 2; }
        .advance-badge::after { content: ''; position: absolute; bottom: -5px; left: 0; border-top: 5px solid #00796b; border-left: 5px solid transparent; }
        .movie-info { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .movie-title { font-size: 18px; font-weight: 900; color: white; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;}
        .movie-tags span { background: #222; color: #ccc; font-size: 11px; padding: 4px 8px; border-radius: 4px; margin-right: 5px; font-weight: bold; border: 1px solid #333;}
        .movie-tags span.rating { color: var(--c-gold); border-color: var(--c-gold); }
        .studio-name { font-size: 14px; font-weight: bold; color: var(--c-red); margin-top: 15px; border-top: 1px solid #222; padding-top: 15px;}
        .studio-name.vvip { color: var(--c-gold); }
        .studio-price { float: right; color: white; }
        .showtime-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 15px; }
        .showtime-btn { background: transparent; border: 1px solid #444; color: white; border-radius: 6px; padding: 8px 0; text-align: center; font-weight: bold; cursor: pointer; transition: 0.2s; font-size: 13px; }
        .showtime-btn:hover { background: #00a896; border-color: #00a896; color: white; }
        .release-date { font-size: 14px; font-weight: bold; color: #00a896; margin-top: 15px; border-top: 1px solid #222; padding-top: 15px; }

        /* DENAH KURSI BIOSKOP */
        .screen-cinema { background: linear-gradient(to bottom, #555, transparent); height: 45px; margin: 0 auto 50px; border-top: 4px solid var(--c-gold); border-radius: 50% 50% 0 0 / 100% 100% 0 0; text-align: center; color: #fff; font-weight: bold; padding-top: 10px; width: 85%; box-shadow: 0 -15px 30px rgba(255,255,255,0.05); letter-spacing: 5px;}
        .seat-row { display: flex; justify-content: center; gap: 8px; margin-bottom: 10px; }
        .seat { width: 35px; height: 35px; background: #222; border-radius: 5px 5px 12px 12px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; color: #fff; cursor: pointer; transition: 0.2s; border: 1px solid #333; user-select: none;}
        .seat:hover:not(.sold) { background: #444; border-color: #666; transform: translateY(-2px); }
        .seat.selected { background: var(--c-red); color: white; border-color: #ff0a16; box-shadow: 0 0 12px rgba(229,9,20,0.6); }
        .seat.sold { background: #0c0c0c; color: #1a1a1a; cursor: not-allowed; border-color: #111; }
        .seat.vvip-seat { width: 50px; height: 50px; background: #1a1a1a; border: 1px solid var(--c-gold); border-radius: 8px 8px 15px 15px; font-size: 14px; color: var(--c-gold); }
        .seat.vvip-seat:hover:not(.sold) { background: #2a2a2a; box-shadow: 0 0 10px rgba(212,175,55,0.3); }
        .seat.vvip-seat.selected { background: var(--c-gold); color: #000; box-shadow: 0 0 20px rgba(212,175,55,0.8); border-color: #fff;}
        .seat.vvip-seat.sold { background: #0c0c0c; border-color: #333; color: #333; }
        .seat-gap { width: 40px; } 
        .booking-panel { background: #111; padding: 20px 30px; border-top: 2px solid var(--c-red); position: sticky; bottom: 0; display: flex; justify-content: space-between; align-items: center; z-index: 10; border-radius: 12px; box-shadow: 0 -10px 20px rgba(0,0,0,0.5); margin-top: 20px;}

        .view-section { display: none; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* SPOTIFY MUSIC PLAYER STYLE (INLINE DI HEADER) */
        .spotify-player {
            width: 330px;
            background-color: rgba(24, 24, 24, 0.95); 
            border-radius: 10px; 
            border: 1px solid #333;
            display: flex; 
            flex-direction: column; 
            padding: 10px 15px; 
            color: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            margin: 0 20px;
        }
        .track-cover { width: 40px; height: 40px; border-radius: 6px; margin-right: 12px; object-fit: cover; }
        .track-title { font-size: 13px; font-weight: bold; color: #fff; margin-bottom: 0px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .track-artist { font-size: 11px; color: #b3b3b3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .control-buttons { display: flex; align-items: center; justify-content: center; gap: 20px; color: #b3b3b3; font-size: 14px; margin-bottom: 5px; }
        .control-buttons i { cursor: pointer; transition: 0.2s; }
        .control-buttons i:hover { color: #fff; }
        .play-pause-btn { 
            width: 25px; height: 25px; background-color: #fff; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; color: #000; cursor: default;
        }
        .play-pause-btn i { cursor: default; font-size: 10px; }
        .playback-bar { display: flex; align-items: center; width: 100%; gap: 10px; font-size: 10px; color: #a7a7a7; font-weight: bold; }
        .progress-bar-container { flex-grow: 1; height: 4px; background-color: #535353; border-radius: 2px; position: relative; overflow: hidden; }
        .progress-bar { height: 100%; background-color: #1ed760; width: 0%; border-radius: 2px; transition: width 0.5s linear;}

        /* TABS UNTUK PENGATURAN */
        .nav-tabs { border-bottom: 1px solid #333; }
        .nav-tabs .nav-link { color: var(--text-mut); border: none; font-weight: bold; padding: 10px 20px; }
        .nav-tabs .nav-link:hover { color: #fff; }
        .nav-tabs .nav-link.active { background: transparent; color: var(--c-red); border-bottom: 2px solid var(--c-red); }
    </style>
</head>
<body>

    <div id="bg-music-player" style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;"></div>

    <div class="sidebar">
        <div class="brand-logo"><i class="fa-solid fa-film"></i> CINEPLEX</div>
        
        <div class="text-center mb-4 border-bottom border-secondary pb-3 mx-3">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nama_user); ?>&background=random" class="rounded-circle mb-2" width="50">
            <div class="text-white fw-bold"><?php echo htmlspecialchars($nama_user); ?></div>
            <span class="badge <?php echo $role == 'admin' ? 'bg-danger' : 'bg-secondary'; ?> mt-1"><?php echo strtoupper($role); ?></span>
        </div>

        <?php if($role == 'admin'): ?>
            <a class="menu-item active" onclick="switchView('view-dashboard', this)"><i class="fa-solid fa-chart-line"></i> Dasbor Utama</a>
            
            <div class="text-muted small fw-bold px-4 mt-4 mb-2" style="font-size: 11px;">KASIR & TRANSAKSI</div>
            <a class="menu-item" onclick="switchView('view-studio', this)"><i class="fa-solid fa-ticket"></i> Kasir Tiket Manual</a>
            <a class="menu-item" onclick="switchView('view-fnb', this)"><i class="fa-solid fa-burger"></i> Kasir Makanan (F&B)</a>
            
            <div class="text-muted small fw-bold px-4 mt-4 mb-2" style="font-size: 11px;">MANAJEMEN KONTEN</div>
            <a class="menu-item" onclick="switchView('view-manajemen-film', this)"><i class="fa-solid fa-film"></i> Kelola Film</a>
            <a class="menu-item" onclick="switchView('view-penjadwalan', this)"><i class="fa-solid fa-calendar-days"></i> Jadwal Tayang</a>
            <a class="menu-item" onclick="switchView('view-kelola-studio', this)"><i class="fa-solid fa-couch"></i> Kelola Studio & Kursi</a>
            <a class="menu-item" onclick="switchView('view-promo', this)"><i class="fa-solid fa-tags"></i> Harga & Promosi</a>
            
            <div class="text-muted small fw-bold px-4 mt-4 mb-2" style="font-size: 11px;">DATA & LAPORAN</div>
            <a class="menu-item" onclick="switchView('view-pesanan', this)"><i class="fa-solid fa-receipt"></i> Riwayat Transaksi</a>
            <a class="menu-item" onclick="switchView('view-laporan', this)"><i class="fa-solid fa-file-invoice-dollar"></i> Laporan Keuangan</a>
            <a class="menu-item" onclick="switchView('view-member', this)"><i class="fa-solid fa-users"></i> Pengguna & Akses</a>
            
            <div class="text-muted small fw-bold px-4 mt-4 mb-2" style="font-size: 11px;">SISTEM</div>
            <a class="menu-item" onclick="switchView('view-pengaturan', this)"><i class="fa-solid fa-gears"></i> Konfigurasi Sistem</a>
        <?php else: ?>
            <a class="menu-item active" onclick="switchView('view-jadwal', this)"><i class="fa-solid fa-calendar-days"></i> Jadwal Tayang Film</a>
        <?php endif; ?>
        
        <a href="?logout=true" class="menu-item text-danger mt-5 mb-5"><i class="fa-solid fa-power-off"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div style="flex: 1;">
                <h3 class="mb-0 fw-bold text-white" id="page-title"><?php echo $role == 'admin' ? 'Dasbor Utama' : 'Jadwal Tayang Film'; ?></h3>
                <span class="text-danger small fw-bold"><i class="fa-solid fa-circle text-danger me-1" style="animation: pulse 1s infinite;"></i> CINEPLEX SYSTEM</span>
            </div>

            <div class="spotify-player">
                <div class="d-flex align-items-center mb-2">
                    <img id="sp-cover" src="https://i.ytimg.com/vi/gpi25y0hLr8/mqdefault.jpg" alt="Cover" class="track-cover">
                    <div style="flex-grow: 1; overflow: hidden;">
                        <div id="sp-title" class="track-title">Way Too Self Aware</div>
                        <div id="sp-artist" class="track-artist">Ian Asher</div>
                    </div>
                    <i class="fa-solid fa-heart ms-2" style="color: #1ed760; font-size: 14px;"></i>
                </div>
                <div class="d-flex flex-column align-items-center w-100">
                    <div class="control-buttons">
                        <i class="fa-solid fa-shuffle" style="color: #1ed760;"></i>
                        <i class="fa-solid fa-backward-step" onclick="prevTrack()" title="Lagu Sebelumnya"></i>
                        <div class="play-pause-btn"><i id="play-icon" class="fa-solid fa-play"></i></div>
                        <i class="fa-solid fa-forward-step" onclick="nextTrack()" title="Lagu Selanjutnya"></i>
                        <i class="fa-solid fa-repeat" style="color: #1ed760;"></i>
                    </div>
                    <div class="playback-bar">
                        <span id="music-current-time">0:00</span>
                        <div class="progress-bar-container"><div id="music-progress" class="progress-bar"></div></div>
                        <span id="music-total-time">0:00</span>
                    </div>
                </div>
            </div>

            <div class="text-end" style="flex: 1;">
                <div class="time-display" id="live-time">00:00:00</div>
                <div class="date-display" id="live-date">Memuat Tanggal...</div>
            </div>
        </div>

        <?php if($role == 'admin'): ?>
        <div id="view-dashboard" class="view-section" style="display: block;">
            <div class="filter-group">
                <button class="btn-filter active" onclick="setFilter('today', this)">Hari Ini</button>
                <button class="btn-filter" onclick="setFilter('weekly', this)">Mingguan</button>
                <button class="btn-filter" onclick="setFilter('monthly', this)">Bulanan</button>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="alert bg-dark border border-warning text-warning m-0 p-2" style="font-size:13px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <b>Peringatan:</b> Stok Popcorn Caramel < 10 Pcs
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert bg-dark border border-danger text-danger m-0 p-2" style="font-size:13px;">
                        <i class="fa-solid fa-bell"></i> <b>Notifikasi:</b> 2 Pembayaran online butuh konfirmasi
                    </div>
                </div>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card"><h6>Pendapatan Kinerja <span id="lbl-time1" class="text-white fw-bold"></span></h6><h2 style="color:var(--c-gold);" id="kpi-rev">Rp 0</h2></div>
                <div class="kpi-card"><h6>Tiket & Pengunjung</h6><h2 class="text-white" id="kpi-vis">0 Orang</h2></div>
                <div class="kpi-card"><h6>Total Transaksi</h6><h2 class="text-white" id="kpi-trx">0 TRX</h2></div>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-lg-8"><div class="chart-box h-100"><h6 class="text-white fw-bold mb-4">GRAFIK PERKEMBANGAN STUDIO</h6><div style="height:250px;"><canvas id="studioChart"></canvas></div></div></div>
                <div class="col-lg-4"><div class="chart-box h-100"><h6 class="text-white fw-bold mb-4">FILM TERLARIS</h6><div style="height:250px;"><canvas id="filmChart"></canvas></div></div></div>
            </div>
        </div>

        <div id="view-manajemen-film" class="view-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold m-0 text-white">Kelola Katalog Film</h4>
                <button class="btn btn-danger fw-bold"><i class="fa-solid fa-plus"></i> Tambah Film Baru</button>
            </div>
            <div class="chart-box p-0 overflow-hidden">
                <table class="table table-dark table-hover m-0">
                    <thead><tr><th>Poster</th><th>Judul Film</th><th>Durasi</th><th>Genre</th><th>Batas Usia</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr><td><img src="img/fast.jpg" width="40" height="50" style="object-fit:cover; border-radius:4px;"></td><td class="fw-bold">FAST X</td><td>2h 21m</td><td>Action</td><td><span class="badge bg-warning text-dark">R13+</span></td><td><span class="badge bg-success">Sedang Tayang</span></td><td><button class="btn btn-sm btn-outline-light"><i class="fa-solid fa-pen"></i></button></td></tr>
                        <tr><td><img src="img/sekawanlimo2.jpg" width="40" height="50" style="object-fit:cover; border-radius:4px;"></td><td class="fw-bold">SEKAWAN LIMO 2</td><td>2h 2m</td><td>Comedy/Horror</td><td><span class="badge bg-warning text-dark">R13+</span></td><td><span class="badge bg-info text-dark">Akan Datang</span></td><td><button class="btn btn-sm btn-outline-light"><i class="fa-solid fa-pen"></i></button></td></tr>
                        <tr><td><img src="img/avengers.jpg" width="40" height="50" style="object-fit:cover; border-radius:4px; background:#333;"></td><td class="fw-bold text-muted">AVENGERS: ENDGAME</td><td>3h 1m</td><td>Action/Sci-Fi</td><td><span class="badge bg-warning text-dark">R13+</span></td><td><span class="badge bg-secondary">Arsip / Selesai</span></td><td><button class="btn btn-sm btn-outline-light"><i class="fa-solid fa-pen"></i></button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="view-penjadwalan" class="view-section">
            <h4 class="fw-bold mb-4 text-white">Jadwal Tayangan Bioskop</h4>
            <div class="row mb-3">
                <div class="col-md-3"><input type="date" class="form-control bg-dark text-white border-secondary"></div>
                <div class="col-md-3"><button class="btn btn-outline-danger w-100 fw-bold">Filter Tanggal</button></div>
            </div>
            <div class="chart-box p-0 overflow-hidden">
                <table class="table table-dark table-hover m-0">
                    <thead><tr><th>Jam Mulai - Selesai</th><th>Film</th><th>Studio</th><th>Kapasitas Tersedia</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr><td>12:15 - 14:36</td><td class="fw-bold">FAST X</td><td>Studio 1</td><td><span class="text-success fw-bold">85 / 100 Kursi</span></td><td><button class="btn btn-sm btn-outline-warning">Blokir / Edit</button></td></tr>
                        <tr><td>13:30 - 16:13</td><td class="fw-bold">MISSION: IMPOSSIBLE</td><td>VVIP Premiere</td><td><span class="text-danger fw-bold">2 / 20 Kursi</span></td><td><button class="btn btn-sm btn-outline-warning">Blokir / Edit</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="view-kelola-studio" class="view-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold m-0 text-white">Manajemen Studio & Desain Kursi</h4>
                <button class="btn btn-danger fw-bold"><i class="fa-solid fa-plus"></i> Tambah Studio</button>
            </div>
            <div class="row g-4">
                <div class="col-md-4"><div class="kpi-card" style="border-top-color: #00a896;">
                    <h5 class="fw-bold text-white mb-1"><i class="fa-solid fa-desktop me-2"></i> Studio 1 (Reguler)</h5>
                    <p class="text-mut small mb-3">100 Kursi (Baris A - J)</p>
                    <button class="btn btn-sm btn-outline-light w-100">Atur Denah Kursi</button>
                </div></div>
                <div class="col-md-4"><div class="kpi-card" style="border-top-color: var(--c-gold);">
                    <h5 class="fw-bold text-warning mb-1"><i class="fa-solid fa-crown me-2"></i> VVIP Premiere</h5>
                    <p class="text-mut small mb-3">20 Kursi Sofa (Baris A - D)</p>
                    <button class="btn btn-sm btn-outline-light w-100">Atur Denah Kursi</button>
                </div></div>
            </div>
        </div>

        <div id="view-promo" class="view-section">
            <h4 class="fw-bold mb-4 text-white">Pengaturan Harga, Diskon & Promosi</h4>
            <div class="row g-4 mb-4">
                <div class="col-md-6"><div class="chart-box h-100 mb-0">
                    <h6 class="text-white fw-bold mb-3">Harga Dasar (Base Price)</h6>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2"><span>Senin - Kamis (Reguler)</span><span class="fw-bold text-gold">Rp 40.000</span></div>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2"><span>Jumat - Minggu (Reguler)</span><span class="fw-bold text-gold">Rp 50.000</span></div>
                    <div class="d-flex justify-content-between pb-2 mb-2"><span>Tiket VVIP (Semua Hari)</span><span class="fw-bold text-gold">Rp 120.000</span></div>
                    <button class="btn btn-sm btn-outline-light mt-2">Ubah Tarif Tiket</button>
                </div></div>
                <div class="col-md-6"><div class="chart-box h-100 mb-0">
                    <h6 class="text-white fw-bold mb-3">Kupon Aktif</h6>
                    <div class="alert bg-dark border-secondary p-2 mb-2 d-flex justify-content-between align-items-center">
                        <div><b class="text-danger">MABAR20</b><br><small class="text-muted">Diskon 20% Pelajar</small></div>
                        <span class="badge bg-success">Aktif</span>
                    </div>
                    <button class="btn btn-sm btn-danger mt-2">+ Buat Kode Promo</button>
                </div></div>
            </div>
        </div>

        <div id="view-pesanan" class="view-section">
            <h4 class="fw-bold mb-4 text-white">Semua Data Transaksi Tiket</h4>
            <div class="chart-box p-0 overflow-hidden">
                <table class="table table-dark table-hover m-0">
                    <thead><tr><th>No. Pesanan</th><th>Tgl Beli</th><th>Pelanggan</th><th>Film (Studio)</th><th>Kursi</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr><td>#TRX-9901</td><td>20 Jun 2026</td><td>Ahmad Fathur</td><td>FAST X (Studio 1)</td><td>G4, G5</td><td>Rp 80.000</td><td><span class="badge bg-success">Berhasil</span></td><td><button class="btn btn-sm btn-outline-info"><i class="fa-solid fa-eye"></i></button></td></tr>
                        <tr><td>#TRX-9902</td><td>20 Jun 2026</td><td>Siti Nurbaya</td><td>OPPENHEIMER (Studio 3)</td><td>C1</td><td>Rp 45.000</td><td><span class="badge bg-secondary">Digunakan</span></td><td><button class="btn btn-sm btn-outline-info"><i class="fa-solid fa-eye"></i></button></td></tr>
                        <tr><td>#TRX-9903</td><td>20 Jun 2026</td><td>Anonim (Kasir)</td><td>JOHN WICK (Studio 2)</td><td>A1, A2</td><td>Rp 80.000</td><td><span class="badge bg-danger">Dibatalkan</span></td><td><button class="btn btn-sm btn-outline-info"><i class="fa-solid fa-eye"></i></button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="view-member" class="view-section">
            <div class="chart-box" style="background: #111; border: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold m-0 text-white">Manajemen Pengguna & Staf</h4>
                        <p class="text-muted small m-0">Atur hak akses staf dan data pelanggan bioskop.</p>
                    </div>
                    <button class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#tambahMemberModal">+ Tambah User</button>
                </div>
                <table class="table table-dark table-hover">
                    <thead><tr><th>ID / Username</th><th>Nama Lengkap</th><th>Peran (Role) / Tingkat</th><th>Info / Poin</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody id="member-table-body">
                        <tr><td class="text-white fw-bold">admin</td><td class="text-white fw-bold">Administrator Utama</td><td><span class="badge bg-danger fw-bold">ADMIN PUSAT</span></td><td class="text-muted">Akses Penuh</td><td class="text-success fw-bold">Aktif</td><td class="text-center">-</td></tr>
                        <tr><td class="text-white fw-bold">kasir01</td><td class="text-white fw-bold">Budi Kasir</td><td><span class="badge bg-info text-dark fw-bold">STAF KASIR</span></td><td class="text-muted">Akses Transaksi</td><td class="text-success fw-bold">Aktif</td><td class="text-center"><button class="btn btn-sm btn-outline-light"><i class="fa-solid fa-pen"></i></button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="view-laporan" class="view-section">
            <div class="chart-box text-center py-5" style="border: none; background: #111;">
                <i class="fa-solid fa-file-invoice-dollar mb-4" style="font-size: 60px; color: #fff;"></i>
                <h3 class="fw-bold text-white">Modul Laporan Keuangan Khusus</h3>
                <p class="text-white fw-bold mb-5">Unduh rekapan penjualan tiket, performa film, dan transaksi dibatalkan.</p>
                <button id="btn-export-pdf" class="btn btn-outline-danger me-3 px-4 py-2 fw-bold" onclick="exportPDF()"><i class="fa-solid fa-file-pdf"></i> Export Laporan Utama (PDF)</button>
                <button class="btn btn-outline-success px-4 py-2 fw-bold"><i class="fa-solid fa-file-excel"></i> Rekap F&B (Excel)</button>
            </div>
        </div>

        <div id="view-pengaturan" class="view-section">
            <h4 class="fw-bold mb-4 text-white">Konfigurasi Sistem Bioskop</h4>
            <div class="chart-box p-0">
                <ul class="nav nav-tabs px-3 pt-3" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-profil" type="button">Profil Bioskop</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-notif" type="button">Notifikasi & Web</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button">Log Audit Sistem</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-legal" type="button">Keamanan & Legal</button></li>
                </ul>
                <div class="tab-content p-4 text-white" id="myTabContent">
                    <div class="tab-pane fade show active" id="tab-profil">
                        <h6 class="fw-bold text-danger">Identitas Perusahaan</h6>
                        <input type="text" class="form-control bg-dark text-white border-secondary mb-3" value="CINEPLEX HQ INDONESIA">
                        <textarea class="form-control bg-dark text-white border-secondary mb-3" rows="3">Jl. Panglima Sudirman No.1, Pusat Kota, Surabaya</textarea>
                        <button class="btn btn-sm btn-outline-light">Simpan Perubahan</button>
                    </div>
                    <div class="tab-pane fade" id="tab-notif">
                        <p class="text-muted">Atur banner halaman depan dan template pesan tiket (Email/WA).</p>
                        <button class="btn btn-sm btn-outline-info">Edit Banner Web</button>
                    </div>
                    <div class="tab-pane fade" id="tab-audit">
                        <p class="text-muted small">Catatan jejak aktivitas semua staf & perubahan sistem.</p>
                        <div class="alert bg-dark text-light border-secondary py-2 m-0" style="font-size:12px; font-family:monospace;">
                            [20:15 WIB] <b>admin</b> login ke dalam sistem.<br>
                            [20:20 WIB] <b>admin</b> mengubah harga dasar Studio 1 menjadi Rp 40.000.<br>
                            [21:10 WIB] <b>kasir01</b> membatalkan transaksi #TRX-9903.
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-legal">
                        <p class="text-muted">Atur kata sandi, batas waktu bayar (misal 15 menit), dan teks invoice.</p>
                        <input type="number" class="form-control bg-dark text-white border-secondary mb-3 w-25" value="15" placeholder="Batas Waktu Bayar (Menit)">
                        <button class="btn btn-sm btn-outline-danger">Ubah Syarat & Ketentuan Web</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-studio" class="view-section">
            <h5 class="text-white fw-bold mb-4">Kasir Pembelian Tiket Langsung (Pilih Studio):</h5>
            <div class="row g-4">
                <script>
                    for(let i=1; i<=7; i++) {
                        document.write(`<div class="col-md-3"><div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid var(--c-red); cursor: pointer; transition: 0.2s;" onclick="bukaBooking('Studio ${i}', 'TIKET REGULER MANUAL', '-', 40000, false)"><h4 class="fw-bold text-white mb-3 mt-2">Studio ${i}</h4><div class="badge bg-danger fw-bold mb-3 px-3 py-2" style="border-radius:20px; font-size:13px;">Pilih Kursi <i class="fa-solid fa-arrow-right ms-1"></i></div></div></div>`);
                    }
                </script>
                <div class="col-md-3"><div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid var(--c-gold); box-shadow: 0 0 15px rgba(212,175,55,0.1); cursor: pointer; transition: 0.2s;" onclick="bukaBooking('VVIP Premiere', 'TIKET VVIP MANUAL', '-', 120000, true)"><h4 class="fw-bold text-warning mb-3 mt-2"><i class="fa-solid fa-crown"></i> VVIP Premiere</h4><div class="badge text-dark fw-bold mb-3 px-3 py-2" style="background: var(--c-gold); border-radius:20px; font-size:13px;">Pilih Kursi VIP <i class="fa-solid fa-arrow-right ms-1"></i></div></div></div>
            </div>
        </div>

        <div id="view-fnb" class="view-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="text-white fw-bold m-0">Konter Snack & Minuman (F&B)</h4>
                    <p class="text-muted m-0 small">Klik tombol 'Manajemen Stok' untuk menambah item baru.</p>
                </div>
                <div>
                    <button class="btn btn-outline-light btn-sm fw-bold me-2"><i class="fa-solid fa-boxes-stacked"></i> Manajemen Stok F&B</button>
                    <span class="badge bg-warning text-dark p-2 fw-bold">Mode Kasir</span>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-3"><div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid var(--c-gold);"><i class="fa-solid fa-cookie-bite" style="font-size: 40px; color: var(--c-gold); margin-bottom: 15px;"></i><h5 class="fw-bold text-white mb-2">Popcorn Caramel</h5><p class="text-mut mb-3">Rp 35.000</p><button class="btn btn-outline-warning w-100 fw-bold" onclick="tambahFnb('Popcorn Caramel', 35000)">+ Tambah ke Bill</button></div></div>
                <div class="col-md-3"><div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid var(--c-gold);"><i class="fa-solid fa-bowl-food" style="font-size: 40px; color: var(--c-gold); margin-bottom: 15px;"></i><h5 class="fw-bold text-white mb-2">Popcorn Asin</h5><p class="text-mut mb-3">Rp 30.000</p><button class="btn btn-outline-warning w-100 fw-bold" onclick="tambahFnb('Popcorn Asin', 30000)">+ Tambah ke Bill</button></div></div>
                <div class="col-md-3"><div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid #00a896;"><i class="fa-solid fa-mug-hot" style="font-size: 40px; color: #00a896; margin-bottom: 15px;"></i><h5 class="fw-bold text-white mb-2">Coca Cola (L)</h5><p class="text-mut mb-3">Rp 20.000</p><button class="btn btn-outline-info w-100 fw-bold" onclick="tambahFnb('Coca Cola (L)', 20000)">+ Tambah ke Bill</button></div></div>
                <div class="col-md-3"><div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid #00a896;"><i class="fa-solid fa-bottle-water" style="font-size: 40px; color: #00a896; margin-bottom: 15px;"></i><h5 class="fw-bold text-white mb-2">Air Mineral</h5><p class="text-mut mb-3">Rp 10.000</p><button class="btn btn-outline-info w-100 fw-bold" onclick="tambahFnb('Air Mineral', 10000)">+ Tambah ke Bill</button></div></div>
            </div>
            <div class="booking-panel mt-5">
                <div><h6 class="text-white fw-bold mb-1">Rincian Bill: <span id="fnb-list" class="text-mut ms-2" style="font-weight: normal; font-size: 14px;">Belum ada pesanan</span></h6></div>
                <div class="text-end d-flex align-items-center gap-4"><div class="text-end"><h6 class="text-white fw-bold mb-1">Total Bayar</h6><h3 class="text-gold fw-bold m-0" id="fnb-total">Rp 0</h3></div><button class="btn px-4 py-3 fw-bold" onclick="prosesFnb()" style="background: var(--c-gold); color: black; border-radius: 10px;">BAYAR PESANAN</button></div>
            </div>
        </div>

        <div class="modal fade" id="tambahMemberModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background-color: #151515; border: 1px solid #333;">
              <div class="modal-header border-secondary"><h5 class="modal-title text-white fw-bold">Tambah Akun Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
              <div class="modal-body">
                <div class="mb-3"><label class="text-white fw-bold small mb-2">Nama Lengkap</label><input type="text" id="inputNamaMember" class="form-control bg-dark text-white border-secondary"></div>
                <div class="mb-3"><label class="text-white fw-bold small mb-2">Role / Tingkat Akses</label><select id="inputTingkatMember" class="form-select bg-dark text-white border-secondary"><option value="Bronze">Pelanggan - Bronze</option><option value="Silver">Pelanggan - Silver</option><option value="Gold">Pelanggan - Gold</option><option value="Kasir">Staf - Kasir Khusus</option></select></div>
              </div>
              <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger fw-bold" style="background: #e50914;" onclick="simpanMemberBaru()">Buat Akun</button></div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div id="view-jadwal" class="view-section" style="display: <?php echo $role == 'customer' ? 'block' : 'none'; ?>;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-white fw-bold m-0">Sedang Tayang di Cineplex</h4>
                <div class="d-flex gap-2">
                    <span class="badge bg-danger p-2" style="font-size: 13px;">Cinema XXI</span>
                    <span class="badge text-dark p-2" style="font-size: 13px; background: var(--c-gold);">The Premiere</span>
                </div>
            </div>
            <div class="row g-4" id="jadwal-container"></div>

            <div class="mt-5 pt-4 border-top border-secondary">
                <h4 class="text-white fw-bold mb-4">Akan Tayang (Coming Soon)</h4>
                <div class="row g-4" id="coming-soon-container"></div>
            </div>
        </div>

        <div id="view-booking" class="view-section">
            <button class="btn btn-outline-light fw-bold mb-4" onclick="switchView('<?php echo $role == 'admin' ? 'view-studio' : 'view-jadwal'; ?>', null)">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </button>
            <div class="d-flex justify-content-between align-items-end mb-3">
                <div>
                    <h2 class="fw-bold text-white mb-1" id="book-title">Studio 1</h2>
                    <p class="text-white fw-bold m-0">Pilih tempat duduk Anda. Lorong berada di bagian tengah.</p>
                </div>
                <h5 class="text-gold fw-bold m-0" id="book-price">Rp 40.000 / Tiket</h5>
            </div>
            <div class="chart-box" style="background: #050505; border: 1px solid #1a1a1a; overflow-x: auto; padding-top: 40px; border-radius: 20px;">
                <div class="screen-cinema">LAYAR UTAMA</div>
                <div id="seat-map" style="min-width: 600px; padding-bottom: 20px;"></div>
                <div class="d-flex justify-content-center gap-5 mt-5 border-top border-secondary pt-4 pb-2">
                    <div class="d-flex align-items-center text-white fw-bold small"><div class="seat me-2" style="width:25px;height:25px;cursor:default;"></div> Tersedia</div>
                    <div class="d-flex align-items-center text-white fw-bold small"><div class="seat selected me-2" style="width:25px;height:25px;cursor:default;"></div> Pilihan Anda</div>
                    <div class="d-flex align-items-center text-white fw-bold small"><div class="seat sold me-2" style="width:25px;height:25px;cursor:default;"></div> Terisi / Dibooking</div>
                </div>
            </div>
            <div class="booking-panel">
                <div>
                    <h6 class="text-white fw-bold mb-1">Total Tiket Terpilih: <span id="lbl-count" class="text-gold fw-bold fs-5 ms-2">0</span></h6>
                    <small class="text-danger fw-bold" id="lbl-seats">Belum ada kursi yang dipilih</small>
                </div>
                <div class="text-end d-flex align-items-center gap-4">
                    <div class="text-end">
                        <h6 class="text-white fw-bold mb-1">Total Pembayaran</h6>
                        <h3 class="text-gold fw-bold m-0" id="lbl-total">Rp 0</h3>
                    </div>
                    <button class="btn btn-danger px-4 py-3 fw-bold" onclick="prosesTiket()" style="background: #e50914; border-radius: 10px;">PROSES TIKET <i class="fa-solid fa-ticket ms-2"></i></button>
                </div>
            </div>
        </div>

    </div>

    <div class="modal fade" id="trailerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-white fw-bold" id="trailerTitle">Trailer Film</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="ratio ratio-16x9">
                        <iframe id="trailerIframe" src="" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // SCRIPT YOUTUBE IFRAME API UNTUK PLAYLIST MUSIK SPOTIFY
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

        var bgMusicPlayer;
        var isMusicPlaying = false;
        var wasPlayingBeforeTrailer = false;
        var currentTrackIndex = 0;

        // DAFTAR LAGU PLAYLIST SESUAI PERMINTAAN TERBARU
        const musicPlaylist = [
            { title: "Way Too Self Aware", artist: "Ian Asher", id: "gpi25y0hLr8" },
            { title: "Take Me (To The Moon)", artist: "Ian Asher", id: "tNHXDNaNqsU" },
            { title: "The Spectre", artist: "Alan Walker", id: "wJnBTPUQS5A" },
            { title: "Who I Am", artist: "Alan Walker, Putri Ariani, Peder Elias", id: "ccu6JuC21rk" }
        ];

        function onYouTubeIframeAPIReady() {
            bgMusicPlayer = new YT.Player('bg-music-player', {
                videoId: musicPlaylist[0].id,
                playerVars: { 'autoplay': 0, 'controls': 0, 'showinfo': 0 },
                events: {
                    'onStateChange': function(event) {
                        if(event.data == YT.PlayerState.PLAYING) {
                            isMusicPlaying = true;
                            document.getElementById('play-icon').classList.replace('fa-play', 'fa-pause');
                        } else if(event.data == YT.PlayerState.PAUSED) {
                            isMusicPlaying = false;
                            document.getElementById('play-icon').classList.replace('fa-pause', 'fa-play');
                        } else if(event.data == YT.PlayerState.ENDED) {
                            nextTrack(); // Otomatis lanjut ke lagu berikutnya
                        }
                    }
                }
            });
        }

        window.addEventListener('click', function initAudio() {
            if(!isMusicPlaying && bgMusicPlayer && typeof bgMusicPlayer.playVideo === 'function') {
                bgMusicPlayer.playVideo();
                window.removeEventListener('click', initAudio);
            }
        });

        setInterval(() => {
            if(isMusicPlaying && bgMusicPlayer && bgMusicPlayer.getCurrentTime) {
                let current = bgMusicPlayer.getCurrentTime();
                let duration = bgMusicPlayer.getDuration();
                if(duration > 0) {
                    let pct = (current / duration) * 100;
                    document.getElementById('music-progress').style.width = pct + '%';
                    let cMin = Math.floor(current / 60); let cSec = Math.floor(current % 60).toString().padStart(2, '0');
                    document.getElementById('music-current-time').innerText = cMin + ':' + cSec;
                    let dMin = Math.floor(duration / 60); let dSec = Math.floor(duration % 60).toString().padStart(2, '0');
                    document.getElementById('music-total-time').innerText = dMin + ':' + dSec;
                }
            }
        }, 1000);

        function loadTrack(index) {
            let track = musicPlaylist[index];
            document.getElementById('sp-title').innerText = track.title;
            document.getElementById('sp-artist').innerText = track.artist;
            document.getElementById('sp-cover').src = 'https://i.ytimg.com/vi/' + track.id + '/mqdefault.jpg';
            if(bgMusicPlayer && typeof bgMusicPlayer.loadVideoById === 'function') { bgMusicPlayer.loadVideoById(track.id); }
        }
        function nextTrack() { currentTrackIndex++; if(currentTrackIndex >= musicPlaylist.length) currentTrackIndex = 0; loadTrack(currentTrackIndex); }
        function prevTrack() { currentTrackIndex--; if(currentTrackIndex < 0) currentTrackIndex = musicPlaylist.length - 1; loadTrack(currentTrackIndex); }

        // GLOBAL JS MENU
        function switchView(viewId, element) {
            if(element) {
                document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
                element.classList.add('active');
                document.getElementById('page-title').innerText = element.innerText.trim();
            }
            document.querySelectorAll('.view-section').forEach(el => el.style.display = 'none');
            document.getElementById(viewId).style.display = 'block';
        }

        const days = ['MINGGU', 'SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'];
        const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        setInterval(() => {
            const now = new Date();
            document.getElementById('live-time').innerText = now.toLocaleTimeString('id-ID', { hour12: false }) + " WIB";
            document.getElementById('live-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
        }, 1000);

        // LOGIKA TRAILER PINTAR
        function playTrailer(judul, ytId) {
            document.getElementById('trailerTitle').innerText = 'Trailer: ' + judul;
            document.getElementById('trailerIframe').src = 'https://www.youtube.com/embed/' + ytId + '?autoplay=1';
            if(isMusicPlaying && bgMusicPlayer && typeof bgMusicPlayer.pauseVideo === 'function') { 
                wasPlayingBeforeTrailer = true; bgMusicPlayer.pauseVideo(); 
            } else { wasPlayingBeforeTrailer = false; }
            var trailerModal = new bootstrap.Modal(document.getElementById('trailerModal'));
            trailerModal.show();
        }

        document.getElementById('trailerModal').addEventListener('hidden.bs.modal', function () { 
            document.getElementById('trailerIframe').src = ''; 
            if(wasPlayingBeforeTrailer && bgMusicPlayer && typeof bgMusicPlayer.playVideo === 'function') { bgMusicPlayer.playVideo(); }
        });

        // DATA JADWAL
        const jadwalData = [
            { judul: "FAST X", poster: "img/fast.jpg", durasi: "2h 21m", rating: "13+", tipe: "2D", studio: "Studio 1", harga: 40000, jam: ["12:15", "14:40", "17:05", "19:30"], advance: false, trailer: "32RAq6LSotU" },
            { judul: "JOHN WICK: CHAPTER 4", poster: "img/johnwick.jpg", durasi: "2h 49m", rating: "17+", tipe: "2D", studio: "Studio 2", harga: 40000, jam: ["12:00", "15:10", "18:20", "21:30"], advance: false, trailer: "qEVUtrk8_B4" },
            { judul: "OPPENHEIMER", poster: "img/oppenheimer.jpg", durasi: "3h 0m", rating: "13+", tipe: "2D", studio: "Studio 3", harga: 45000, jam: ["13:00", "16:30", "20:00"], advance: false, trailer: "uYPbbksJxIg" },
            { judul: "THE SUPER MARIO BROS", poster: "img/mariobros.jpg", durasi: "1h 32m", rating: "SU", tipe: "2D", studio: "Studio 4", harga: 45000, jam: ["12:30", "14:30", "16:30", "18:30"], advance: false, trailer: "TnGl01FkMMo" },
            { judul: "EVIL DEAD RISE", poster: "img/evildead.jpg", durasi: "1h 36m", rating: "17+", tipe: "2D", studio: "Studio 5", harga: 50000, jam: ["13:15", "15:20", "17:30", "19:40"], advance: false, trailer: "smTK_AehAPQ" },
            { judul: "GUARDIANS OF THE GALAXY 3", poster: "img/gotg3.jpg", durasi: "2h 30m", rating: "13+", tipe: "2D", studio: "Studio 6", harga: 50000, jam: ["12:45", "15:45", "18:45", "21:45"], advance: false, trailer: "u3V5KDHRQvk" },
            { judul: "SPIDER-MAN: ACROSS THE SPIDER-VERSE", poster: "img/spiderman.jpg", durasi: "2h 20m", rating: "SU", tipe: "2D", studio: "Studio 7", harga: 55000, jam: ["12:10", "14:50", "17:30", "20:10"], advance: false, trailer: "cqGjhVJWtEg" },
            { judul: "MISSION: IMPOSSIBLE - DEAD RECKONING", poster: "img/missionimpossible.jpg", durasi: "2h 43m", rating: "13+", tipe: "2D", studio: "VVIP Premiere", harga: 120000, jam: ["13:30", "17:00", "20:30"], advance: true, trailer: "2m1drlOZSDw" }
        ];

        const akanTayangData = [
            { judul: "SEKAWAN LIMO 2: GUNUNG KAWI", poster: "img/sekawanlimo2.jpg", durasi: "2h 2m", rating: "13+", tipe: "2D", tanggal: "Tayang: 27 Mei 2026", advance: true, trailer: "sF2zHn5Bf2E" },
            { judul: "BADUT GENDONG", poster: "img/badutgendong.jpg", durasi: "1h 41m", rating: "17+", tipe: "2D", tanggal: "Tayang: 27 Mei 2026", advance: false, trailer: "_d_1rL3iW0k" },
            { judul: "CHILDREN OF HEAVEN", poster: "img/childrenofheaven.jpg", durasi: "1h 37m", rating: "SU", tipe: "2D", tanggal: "Tayang: 27 Mei 2026", advance: false, trailer: "5RqJmCusLqM" },
            { judul: "STAR WARS: MANDALORIAN & GROGU", poster: "img/starwars.jpg", durasi: "2h 12m", rating: "13+", tipe: "2D", tanggal: "Tayang: 20 Mei 2026", advance: false, trailer: "aZgBdZpQ6gQ" },
            { judul: "MOBILE SUIT GUNDAM HATHAWAY", poster: "img/gundam.jpg", durasi: "1h 49m", rating: "13+", tipe: "2D", tanggal: "Tayang: 29 Mei 2026", advance: false, trailer: "P1C9509Z59Y" },
            { judul: "COLONY", poster: "img/colony.jpg", durasi: "2h 2m", rating: "13+", tipe: "2D", tanggal: "Tayang: 3 Jun 2026", advance: true, trailer: "2LqzF5WauAw" },
            { judul: "MONSTER PABRIK RAMBUT", poster: "img/monsterpabrikrambut.jpg", durasi: "1h 36m", rating: "17+", tipe: "2D", tanggal: "Tayang: 4 Jun 2026", advance: true, trailer: "X2m-08cOAbc" }
        ];

        function renderJadwal() {
            const containerNow = document.getElementById('jadwal-container');
            containerNow.innerHTML = '';
            jadwalData.forEach(m => {
                let badgeAdvance = m.advance ? `<div class="advance-badge">Advance ticket sales</div>` : '';
                let studioClass = m.advance ? 'studio-name vvip' : 'studio-name';
                let iconStudio = m.advance ? '<i class="fa-solid fa-crown me-1"></i>' : '<i class="fa-solid fa-desktop me-1"></i>';
                let jamHtml = '';
                m.jam.forEach(j => { let isVvipParam = m.advance ? 'true' : 'false'; jamHtml += `<div class="showtime-btn" onclick="bukaBooking('${m.studio}', '${m.judul}', '${j}', ${m.harga}, ${isVvipParam})">${j}</div>`; });

                containerNow.innerHTML += `<div class="col-md-3"><div class="movie-card">${badgeAdvance}<img src="${m.poster}" class="movie-poster" alt="${m.judul}"><div class="movie-info"><div class="movie-title">${m.judul}</div><div class="movie-tags"><span>${m.durasi}</span><span class="rating">${m.rating}</span><span>${m.tipe}</span></div><button class="btn btn-sm btn-outline-danger w-100 mt-3 fw-bold" onclick="playTrailer('${m.judul}', '${m.trailer}')"><i class="fa-solid fa-play me-1"></i> Lihat Trailer</button><div class="${studioClass}">${iconStudio} ${m.studio}<span class="studio-price">Rp ${new Intl.NumberFormat('id-ID').format(m.harga)}</span></div><div class="showtime-grid">${jamHtml}</div></div></div></div>`;
            });

            const containerSoon = document.getElementById('coming-soon-container');
            containerSoon.innerHTML = '';
            akanTayangData.forEach(m => {
                let badgeAdvance = m.advance ? `<div class="advance-badge">Advance ticket sales</div>` : '';
                containerSoon.innerHTML += `<div class="col-md-3"><div class="movie-card">${badgeAdvance}<img src="${m.poster}" class="movie-poster" alt="${m.judul}"><div class="movie-info"><div class="movie-title">${m.judul}</div><div class="movie-tags"><span>${m.durasi}</span><span class="rating">${m.rating}</span><span>${m.tipe}</span></div><button class="btn btn-sm btn-outline-danger w-100 mt-3 fw-bold" onclick="playTrailer('${m.judul}', '${m.trailer}')"><i class="fa-solid fa-play me-1"></i> Lihat Trailer</button><div class="release-date"><i class="fa-solid fa-calendar-check me-2"></i> ${m.tanggal}</div></div></div></div>`;
            });
        }
        renderJadwal();

        let hargaSaatIni = 0; let kursiDipilih = []; let currentJudulFilm = ''; let currentNamaStudio = '';
        function bukaBooking(studio, judul, jam, harga, isVvip) {
            hargaSaatIni = harga; kursiDipilih = []; currentNamaStudio = studio; currentJudulFilm = judul;
            document.getElementById('book-title').innerText = `${studio} - ${judul} (${jam})`; document.getElementById('book-price').innerText = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(harga) + ' / Tiket'; updatePanelBooking(); document.querySelectorAll('.view-section').forEach(el => el.style.display = 'none'); document.getElementById('view-booking').style.display = 'block';

            const seatMap = document.getElementById('seat-map'); seatMap.innerHTML = ''; const barisHuruf = isVvip ? ['A','B','C','D'] : ['A','B','C','D','E','F','G','H']; const kolomPerSisi = isVvip ? 4 : 7; 
            barisHuruf.forEach(baris => {
                let htmlBaris = `<div class="seat-row">`;
                for(let i=1; i<=kolomPerSisi; i++) { let idKursi = baris + i; let sudahTerjual = Math.random() < 0.15; let kelasKursi = isVvip ? 'seat vvip-seat' : 'seat'; if(sudahTerjual) kelasKursi += ' sold'; htmlBaris += `<div class="${kelasKursi}" id="${idKursi}" onclick="pilihKursi(this, '${idKursi}')">${idKursi}</div>`; }
                htmlBaris += `<div class="seat-gap"></div>`;
                for(let i=kolomPerSisi+1; i<=kolomPerSisi*2; i++) { let idKursi = baris + i; let sudahTerjual = Math.random() < 0.15; let kelasKursi = isVvip ? 'seat vvip-seat' : 'seat'; if(sudahTerjual) kelasKursi += ' sold'; htmlBaris += `<div class="${kelasKursi}" id="${idKursi}" onclick="pilihKursi(this, '${idKursi}')">${idKursi}</div>`; }
                htmlBaris += `</div>`; seatMap.innerHTML += htmlBaris;
            });
        }

        function pilihKursi(elemenKursi, idKursi) {
            if(elemenKursi.classList.contains('sold')) return;
            if(elemenKursi.classList.contains('selected')) { elemenKursi.classList.remove('selected'); kursiDipilih = kursiDipilih.filter(k => k !== idKursi); } else { elemenKursi.classList.add('selected'); kursiDipilih.push(idKursi); }
            kursiDipilih.sort(); updatePanelBooking();
        }

        function updatePanelBooking() {
            const jumlahTiket = kursiDipilih.length; document.getElementById('lbl-count').innerText = jumlahTiket;
            if(jumlahTiket > 0) { document.getElementById('lbl-seats').innerText = "Kursi: " + kursiDipilih.join(', '); document.getElementById('lbl-seats').className = 'text-gold fw-bold'; } else { document.getElementById('lbl-seats').innerText = 'Belum ada kursi yang dipilih'; document.getElementById('lbl-seats').className = 'text-danger fw-bold'; }
            document.getElementById('lbl-total').innerText = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(jumlahTiket * hargaSaatIni);
        }

        function prosesTiket() {
            if(kursiDipilih.length === 0) { alert("Pilih minimal 1 kursi terlebih dahulu!"); return; }
            const btnProses = document.querySelector('.booking-panel .btn-danger'); btnProses.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> MENYIMPAN...'; btnProses.disabled = true;
            const payload = { judul: currentJudulFilm, studio: currentNamaStudio, qty: kursiDipilih.length, total: kursiDipilih.length * hargaSaatIni };
            fetch('index.php?action=buy_ticket', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(res => res.json()).then(data => {
                btnProses.innerHTML = 'PROSES TIKET <i class="fa-solid fa-ticket ms-2"></i>'; btnProses.disabled = false;
                if(data.success) {
                    alert(`TRANSAKSI BERHASIL!\nTiket dicetak untuk kursi: ${kursiDipilih.join(', ')}.\nTotal Pembayaran: Rp ${new Intl.NumberFormat('id-ID').format(payload.total)}`);
                    let defaultView = '<?php echo $role == 'admin' ? 'view-dashboard' : 'view-jadwal'; ?>'; let menuIndex = '<?php echo $role == 'admin' ? '.menu-item:nth-child(1)' : '.menu-item:nth-child(1)'; ?>'; switchView(defaultView, document.querySelector(menuIndex)); 
                } else { alert("Gagal memproses tiket! Penyebab: " + data.error); }
            }).catch(err => { btnProses.innerHTML = 'PROSES TIKET <i class="fa-solid fa-ticket ms-2"></i>'; btnProses.disabled = false; alert("Terjadi kesalahan jaringan."); });
        }

        // ==========================================
        // JS KHUSUS ADMIN
        // ==========================================
        <?php if($role == 'admin'): ?>
        function simpanMemberBaru() { alert("Simulasi: Akun baru berhasil ditambahkan ke sistem."); var modalInstance = bootstrap.Modal.getInstance(document.getElementById('tambahMemberModal')); modalInstance.hide(); }
        
        let fnbKeranjang = []; let fnbTotalHarga = 0;
        function tambahFnb(nama, harga) { fnbKeranjang.push(nama); fnbTotalHarga += harga; document.getElementById('fnb-list').innerText = fnbKeranjang.join(', '); document.getElementById('fnb-total').innerText = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(fnbTotalHarga); }
        function prosesFnb() { if(fnbKeranjang.length === 0) { alert("Silakan tambah Makanan terlebih dahulu!"); return; } alert(`PEMESANAN SNACK BERHASIL!\nItem: ${fnbKeranjang.join(', ')}\nTotal Pembayaran: Rp ${new Intl.NumberFormat('id-ID').format(fnbTotalHarga)}`); fnbKeranjang = []; fnbTotalHarga = 0; document.getElementById('fnb-list').innerText = 'Belum ada pesanan'; document.getElementById('fnb-total').innerText = 'Rp 0'; }

        function exportPDF() {
            const btn = document.getElementById('btn-export-pdf'); const originalText = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses PDF...'; btn.disabled = true;
            fetch(`index.php?action=get_data&time=${activeTimeframe}&limit=all`).then(r => r.json()).then(d => {
                if(!d.success) { alert("Gagal mengambil data laporan dari server."); btn.innerHTML = originalText; btn.disabled = false; return; }
                const { jsPDF } = window.jspdf; const doc = new jsPDF();
                doc.setFontSize(18); doc.setTextColor(229, 9, 20); doc.setFont(undefined, 'bold'); doc.text("LAPORAN KEUANGAN CINEPLEX HQ", 14, 22); doc.setFontSize(11); doc.setTextColor(100, 100, 100); doc.setFont(undefined, 'normal'); let lbl = document.getElementById('lbl-time1').innerText || '(Hari Ini)'; doc.text("Periode Laporan: " + lbl, 14, 30); doc.text("Tanggal Dicetak: " + new Date().toLocaleString('id-ID'), 14, 36); doc.text("Total Transaksi: " + d.kpi.rows + " TRX", 14, 42); let tableData = []; d.table.forEach((r, index) => { let jam = new Date(r.waktu_transaksi).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}); let tgl = new Date(r.waktu_transaksi).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}); tableData.push([ index + 1, `${tgl} ${jam}`, r.judul_film, r.nama_tipe, r.jumlah_tiket + " Tkt", "Rp " + new Intl.NumberFormat('id-ID').format(r.total_pendapatan) ]); }); tableData.push([ "", "", "", "TOTAL KESELURUHAN", d.kpi.visitor + " Tkt", "Rp " + new Intl.NumberFormat('id-ID').format(d.kpi.rev) ]);
                doc.autoTable({ startY: 50, head: [['No', 'Waktu Transaksi', 'Judul Film', 'Studio', 'Tiket', 'Pendapatan']], body: tableData, theme: 'grid', headStyles: { fillColor: [229, 9, 20], textColor: [255, 255, 255], fontStyle: 'bold' }, footStyles: { fillColor: [34, 34, 34] }, didParseCell: function (data) { var rows = data.table.body; if (data.row.index === rows.length - 1) { data.cell.styles.fontStyle = 'bold'; data.cell.styles.fillColor = [20, 20, 20]; data.cell.styles.textColor = (data.column.index >= 3) ? [212, 175, 55] : [255, 255, 255]; } } }); doc.save(`Laporan_Keuangan_Cineplex_${activeTimeframe}.pdf`); btn.innerHTML = originalText; btn.disabled = false;
            }).catch(err => { alert("Terjadi kesalahan."); btn.innerHTML = originalText; btn.disabled = false; });
        }

        let cStudio = null; let cFilm = null; let activeTimeframe = 'today';
        function setFilter(time, btn) { activeTimeframe = time; document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active')); if(btn) btn.classList.add('active'); let lbl = ''; if(time === 'today') lbl = '(Hari Ini)'; else if(time === 'weekly') lbl = '(7 Hari)'; else if(time === 'monthly') lbl = '(1 Bulan)'; else if(time === 'yearly') lbl = '(1 Tahun)'; else if(time === '5years') lbl = '(5 Tahun)'; document.getElementById('lbl-time1').innerText = lbl; fetchData(); }
        function fetchData() {
            fetch(`index.php?action=get_data&time=${activeTimeframe}`).then(r => r.json()).then(d => {
                if(!d.success) return; document.getElementById('kpi-rev').innerText = new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(d.kpi.rev); document.getElementById('kpi-vis').innerText = d.kpi.visitor + " Orang"; document.getElementById('kpi-trx').innerText = d.kpi.rows + " TRX"; const tbody = document.getElementById('table-body'); tbody.innerHTML = '';
                d.table.forEach(r => { let jam = new Date(r.waktu_transaksi).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}); let tgl = new Date(r.waktu_transaksi).toLocaleDateString('id-ID', {day:'2-digit', month:'short'}); tbody.innerHTML += `<tr><td class="text-white fw-bold">${tgl} ${jam}</td><td class="text-white fw-bold">${r.judul_film}</td><td><span class="badge-studio fw-bold text-white">${r.nama_tipe}</span></td><td class="text-white fw-bold"><i class="fa-solid fa-location-dot text-danger"></i> ${r.kota_cabang}</td><td class="text-white fw-bold">${r.jumlah_tiket} Tkt</td><td class="text-gold fw-bold">Rp ${new Intl.NumberFormat('id-ID').format(r.total_pendapatan)}</td></tr>`; });
                let stLab = d.chart_studio.map(x=>x.nama_tipe); let stVal = d.chart_studio.map(x=>x.total); if(cStudio) { cStudio.data.labels=stLab; cStudio.data.datasets[0].data=stVal; cStudio.update(); } else { cStudio = new Chart(document.getElementById('studioChart'), { type: 'bar', data: { labels: stLab, datasets: [{ label: 'Pendapatan', data: stVal, backgroundColor: '#e50914', borderRadius: 4 }] }, options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{grid:{color:'#222'}}, x:{grid:{display:false}} } } }); }
                let flLab = d.chart_film.map(x=>x.judul_film); let flVal = d.chart_film.map(x=>x.v); if(cFilm) { cFilm.data.labels=flLab; cFilm.data.datasets[0].data=flVal; cFilm.update(); } else { cFilm = new Chart(document.getElementById('filmChart'), { type: 'doughnut', data: { labels: flLab, datasets: [{ data: flVal, backgroundColor: ['#e50914','#d4af37','#333','#555','#111','#888'], borderWidth: 2, borderColor: '#151515' }] }, options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'right', labels:{color:'#888'}}} } }); }
            });
        }
        fetchData(); setInterval(fetchData, 2000); 
        <?php endif; ?>
    </script>
</body>
</html>
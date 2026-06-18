<?php
// ==========================================
// BACK-END API & DASHBOARD
// ==========================================
session_start();
if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

$host = "db"; $user = "root"; $pass = "rootsecurepwd123"; $db = "cinema_dw";

// API BARU UNTUK MEMPROSES PEMBELIAN TIKET ASLI DARI UI
if (isset($_GET['action']) && $_GET['action'] == 'buy_ticket') {
    header('Content-Type: application/json');
    try {
        $conn = new mysqli($host, $user, $pass, $db);
        $data = json_decode(file_get_contents('php://input'), true);
        
        $judul = $conn->real_escape_string($data['judul']);
        $studio = $conn->real_escape_string($data['studio']);
        $qty = (int)$data['qty'];
        $total = (int)$data['total'];
        
        // Cari ID Film berdasarkan judul, jika tidak ketemu set default
        $res_film = $conn->query("SELECT id_film FROM dim_film WHERE judul_film LIKE '%$judul%' LIMIT 1");
        $id_film = ($res_film->num_rows > 0) ? $res_film->fetch_assoc()['id_film'] : rand(1,6);

        // Cari ID Tipe Studio berdasarkan nama, jika tidak ketemu set default
        $res_tipe = $conn->query("SELECT id_tipe FROM dim_tipe_tiket WHERE nama_tipe LIKE '%$studio%' LIMIT 1");
        $id_tipe = ($res_tipe->num_rows > 0) ? $res_tipe->fetch_assoc()['id_tipe'] : rand(1,8);

        // Simpan ke database
        $conn->query("INSERT INTO fakta_penjualan (id_film, id_cabang, id_tipe, jumlah_tiket, total_pendapatan, waktu_transaksi) 
                      VALUES ($id_film, 1, $id_tipe, $qty, $total, NOW())");

        echo json_encode(["success" => true]);
    } catch (Exception $e) { 
        echo json_encode(["success" => false, "error" => $e->getMessage()]); 
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_data') {
    header('Content-Type: application/json');
    $out = ["success"=>true, "kpi"=>["rev"=>0, "visitor"=>0, "rows"=>0], "table"=>[], "chart_studio"=>[], "chart_film"=>[]];
    
    try {
        $conn = new mysqli($host, $user, $pass, $db);
        
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

        // Cek apakah request dari tombol Export PDF (Limit dihapus agar semua data terunduh)
        $limit_clause = "LIMIT 15";
        if (isset($_GET['limit']) && $_GET['limit'] == 'all') {
            $limit_clause = ""; 
        }

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
    <title>CinePlex HQ - Executive Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        :root { --bg-dark: #0a0a0a; --bg-card: #151515; --c-red: #e50914; --c-gold: #d4af37; --text-main: #ffffff; --text-mut: #888; }
        body { background-color: var(--bg-dark); color: var(--text-main); font-family: 'Segoe UI', Tahoma, sans-serif; display: flex; overflow: hidden; }
        
        /* SIDEBAR */
        .sidebar { width: 260px; background-color: #000; height: 100vh; padding: 20px 0; border-right: 1px solid #222; overflow-y: auto; }
        .brand-logo { color: var(--c-red); font-size: 24px; font-weight: 900; text-align: center; margin-bottom: 40px; letter-spacing: 1px;}
        .menu-item { padding: 15px 25px; color: var(--text-mut); text-decoration: none; display: block; font-weight: 600; transition: 0.3s; cursor: pointer; border-left: 4px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(229,9,20,0.1); color: var(--c-red); border-left: 4px solid var(--c-red); }
        .menu-item i { width: 30px; }

        .main-content { flex: 1; padding: 30px; height: 100vh; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 20px; margin-bottom: 30px; }
        .time-display { font-size: 24px; font-weight: 800; color: var(--c-gold); letter-spacing: 2px;}
        .date-display { font-size: 14px; color: var(--text-mut); text-transform: uppercase; font-weight: bold; }

        /* UI COMPONENTS */
        .filter-group { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .btn-filter { background: var(--bg-card); color: var(--text-mut); border: 1px solid #333; padding: 8px 20px; border-radius: 30px; font-size: 14px; font-weight: bold; transition: 0.3s; white-space: nowrap; }
        .btn-filter.active { background: var(--c-red); color: white; border-color: var(--c-red); box-shadow: 0 0 15px rgba(229,9,20,0.4);}
        
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid #222; border-top: 3px solid var(--c-red); }
        .kpi-card h6 { color: #ffffff !important; font-size: 16px; font-weight: bold !important; letter-spacing: 0px; margin-bottom: 12px; text-transform: none; }
        .kpi-card h2 { margin-top: 10px; font-weight: 900; font-size: 32px; }
        
        .chart-box { background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid #222; margin-bottom: 30px; }
        .table-dark { background-color: var(--bg-card); }
        .table-dark th { background-color: #000; border-bottom: 2px solid var(--c-red) !important; color: #ffffff !important; font-weight: bold !important; padding: 15px; font-size: 15px; }
        .table-dark td { border-bottom: 1px solid #222; vertical-align: middle; padding: 15px; }
        .badge-studio { background: #222; color: var(--c-gold); border: 1px solid var(--c-gold); padding: 5px 10px; border-radius: 4px; font-size: 12px; }

        /* MENU JADWAL TAYANG & AKAN TAYANG */
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
    </style>
    <script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//157.245.202.71:3081/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '1']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
</head>
<body>

    <div class="sidebar">
        <div class="brand-logo"><i class="fa-solid fa-film"></i> CINEPLEX HQ</div>
        <a class="menu-item active" onclick="switchView('view-dashboard', this)"><i class="fa-solid fa-chart-line"></i> Live Dashboard</a>
        <a class="menu-item" onclick="switchView('view-jadwal', this)"><i class="fa-solid fa-calendar-days"></i> Jadwal Tayang</a>
        <a class="menu-item" onclick="switchView('view-laporan', this)"><i class="fa-solid fa-file-invoice-dollar"></i> Laporan Keuangan</a>
        <a class="menu-item" onclick="switchView('view-studio', this)"><i class="fa-solid fa-couch"></i> Manajemen Studio</a>
        <a class="menu-item" onclick="switchView('view-member', this)"><i class="fa-solid fa-users"></i> Data Member</a>
        <a href="?logout=true" class="menu-item text-danger mt-5"><i class="fa-solid fa-power-off"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h3 class="mb-0 fw-bold text-white" id="page-title">Live Dashboard</h3>
                <span class="text-danger small fw-bold"><i class="fa-solid fa-circle text-danger me-1" style="animation: pulse 1s infinite;"></i> CINEPLEX SYSTEM</span>
            </div>
            <div class="text-end">
                <div class="time-display" id="live-time">00:00:00</div>
                <div class="date-display" id="live-date">Memuat Tanggal...</div>
            </div>
        </div>

        <div id="view-dashboard" class="view-section" style="display: block;">
            <div class="filter-group">
                <button class="btn-filter active" onclick="setFilter('today', this)">Pendapatan Hari Ini</button>
                <button class="btn-filter" onclick="setFilter('weekly', this)">7 Hari Terakhir</button>
                <button class="btn-filter" onclick="setFilter('monthly', this)">1 Bulan Terakhir</button>
                <button class="btn-filter" onclick="setFilter('yearly', this)">1 Tahun Terakhir</button>
                <button class="btn-filter" onclick="setFilter('5years', this)">5 Tahun Terakhir</button>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card"><h6>Total Pendapatan <span id="lbl-time1" class="text-white fw-bold"></span></h6><h2 style="color:var(--c-gold);" id="kpi-rev">Rp 0</h2></div>
                <div class="kpi-card"><h6>Total Pengunjung (Tiket)</h6><h2 class="text-white" id="kpi-vis">0 Orang</h2></div>
                <div class="kpi-card"><h6>Total Transaksi</h6><h2 class="text-white" id="kpi-trx">0 TRX</h2></div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8"><div class="chart-box h-100"><h6 class="text-white fw-bold mb-4">PENDAPATAN STUDIO</h6><div style="height:250px;"><canvas id="studioChart"></canvas></div></div></div>
                <div class="col-lg-4"><div class="chart-box h-100"><h6 class="text-white fw-bold mb-4">FILM TERLARIS</h6><div style="height:250px;"><canvas id="filmChart"></canvas></div></div></div>
            </div>

            <div class="chart-box">
                <h6 class="text-white fw-bold mb-3">RIWAYAT TIKET TERJUAL TERAKHIR</h6>
                <table class="table table-dark table-borderless table-hover">
                    <thead><tr><th>Waktu</th><th>Judul Film</th><th>Studio</th><th>Lokasi</th><th>Qty</th><th>Pendapatan</th></tr></thead>
                    <tbody id="table-body"></tbody>
                </table>
            </div>
        </div>

        <div id="view-jadwal" class="view-section">
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

        <div id="view-laporan" class="view-section">
            <div class="chart-box text-center py-5" style="border: none; background: #111;">
                <i class="fa-solid fa-file-invoice-dollar mb-4" style="font-size: 60px; color: #fff;"></i>
                <h3 class="fw-bold text-white">Modul Laporan Keuangan</h3>
                <p class="text-white fw-bold mb-5">Cetak laporan laba rugi, rekapan tiket, dan total pendapatan secara detail.</p>
                
                <button id="btn-export-pdf" class="btn btn-outline-danger me-3 px-4 py-2 fw-bold" onclick="exportPDF()">
                    <i class="fa-solid fa-file-pdf"></i> Export PDF Laporan
                </button>
            </div>
        </div>

        <div id="view-studio" class="view-section">
            <h5 class="text-white fw-bold mb-4">Pilih studio untuk mengatur kursi dan memesan tiket:</h5>
            <div class="row g-4">
                <script>
                    for(let i=1; i<=7; i++) {
                        document.write(`
                        <div class="col-md-3">
                            <div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid var(--c-red); cursor: pointer; transition: 0.2s;" onclick="bukaBooking('Studio ${i}', 'TIKET REGULER MANUAL', '-', 40000, false)" onmouseover="this.style.background='#1a1a1a'; this.style.transform='translateY(-5px)';" onmouseout="this.style.background='#111'; this.style.transform='translateY(0)';">
                                <h4 class="fw-bold text-white mb-3 mt-2">Studio ${i}</h4>
                                <div class="badge bg-danger fw-bold mb-3 px-3 py-2" style="border-radius:20px; font-size:13px;">Pesan Tiket <i class="fa-solid fa-arrow-right ms-1"></i></div>
                            </div>
                        </div>`);
                    }
                </script>
                <div class="col-md-3">
                    <div class="kpi-card text-center" style="background: #111; border-color: #222; border-top: 3px solid var(--c-gold); box-shadow: 0 0 15px rgba(212,175,55,0.1); cursor: pointer; transition: 0.2s;" onclick="bukaBooking('VVIP Premiere', 'TIKET VVIP MANUAL', '-', 120000, true)" onmouseover="this.style.background='#1a1a1a'; this.style.transform='translateY(-5px)';" onmouseout="this.style.background='#111'; this.style.transform='translateY(0)';">
                        <h4 class="fw-bold text-warning mb-3 mt-2"><i class="fa-solid fa-crown"></i> VVIP Premiere</h4>
                        <div class="badge text-dark fw-bold mb-3 px-3 py-2" style="background: var(--c-gold); border-radius:20px; font-size:13px;">Pesan Tiket VIP <i class="fa-solid fa-arrow-right ms-1"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-booking" class="view-section">
            <button class="btn btn-outline-light fw-bold mb-4" onclick="switchView('view-jadwal', document.querySelector('.menu-item:nth-child(2)'))">
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
                    <button class="btn btn-danger px-4 py-3 fw-bold" onclick="prosesTiket()" style="background: #e50914; border-radius: 10px;">PROSES TIKET <i class="fa-solid fa-print ms-2"></i></button>
                </div>
            </div>
        </div>

        <div id="view-member" class="view-section">
            <div class="chart-box" style="background: #111; border: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold m-0 text-white">Database Cineplex Member</h5>
                    <button class="btn btn-danger fw-bold" style="background: #e50914; border: none;" data-bs-toggle="modal" data-bs-target="#tambahMemberModal">
                        + Tambah Member
                    </button>
                </div>
                <table class="table table-dark table-hover">
                    <thead><tr><th>ID Member</th><th>Nama</th><th>Tingkat</th><th>Poin Reward</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody id="member-table-body"></tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal fade" id="tambahMemberModal" tabindex="-1" aria-labelledby="tambahMemberModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: #151515; border: 1px solid #333;">
          <div class="modal-header" style="border-bottom: 1px solid #333;">
            <h5 class="modal-title text-white fw-bold" id="tambahMemberModalLabel">Tambah Member Baru</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label class="text-white fw-bold small mb-2">Nama Lengkap</label>
                <input type="text" id="inputNamaMember" class="form-control bg-dark text-white fw-bold border-secondary" placeholder="Masukkan nama member...">
            </div>
            <div class="mb-3">
                <label class="text-white fw-bold small mb-2">Tingkat Membership</label>
                <select id="inputTingkatMember" class="form-select bg-dark text-white fw-bold border-secondary">
                    <option value="Bronze">Bronze (Pemula)</option>
                    <option value="Silver">Silver (Menengah)</option>
                    <option value="Gold">Gold (VIP)</option>
                </select>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid #333;">
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger fw-bold" style="background: #e50914;" onclick="simpanMemberBaru()">Simpan Member</button>
          </div>
        </div>
      </div>
    </div>

    <script>
        // LOGIKA GANTI MENU STANDAR
        function switchView(viewId, element) {
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            if(element) element.classList.add('active');
            if(element) document.getElementById('page-title').innerText = element.innerText.trim();
            document.querySelectorAll('.view-section').forEach(el => el.style.display = 'none');
            document.getElementById(viewId).style.display = 'block';
        }

        // JAM REALTIME
        const days = ['MINGGU', 'SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'];
        const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        setInterval(() => {
            const now = new Date();
            document.getElementById('live-time').innerText = now.toLocaleTimeString('id-ID', { hour12: false }) + " WIB";
            document.getElementById('live-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
        }, 1000);

        // ===============================================
        // FUNGSI EKSPOR PDF LAPORAN KEUANGAN
        // ===============================================
        function exportPDF() {
            const btn = document.getElementById('btn-export-pdf');
            const originalText = btn.innerHTML;
            
            // Ubah tombol jadi loading
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses PDF...';
            btn.disabled = true;

            // Panggil API dengan parameter limit=all agar semua data terunduh
            fetch(`index.php?action=get_data&time=${activeTimeframe}&limit=all`)
                .then(r => r.json())
                .then(d => {
                    if(!d.success) {
                        alert("Gagal mengambil data laporan dari server.");
                        btn.innerHTML = originalText; btn.disabled = false; return;
                    }

                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();

                    // --- 1. Desain Header Laporan ---
                    doc.setFontSize(18);
                    doc.setTextColor(229, 9, 20); // Merah Cineplex
                    doc.setFont(undefined, 'bold');
                    doc.text("LAPORAN KEUANGAN CINEPLEX HQ", 14, 22);

                    doc.setFontSize(11);
                    doc.setTextColor(100, 100, 100); // Abu-abu
                    doc.setFont(undefined, 'normal');
                    
                    // Ambil rentang waktu yang sedang aktif di dashboard
                    let lbl = document.getElementById('lbl-time1').innerText || '(Hari Ini)';
                    doc.text("Periode Laporan: " + lbl, 14, 30);
                    doc.text("Tanggal Dicetak: " + new Date().toLocaleString('id-ID'), 14, 36);
                    doc.text("Total Transaksi: " + d.kpi.rows + " TRX", 14, 42);

                    // --- 2. Siapkan Data Tabel ---
                    let tableData = [];
                    d.table.forEach((r, index) => {
                        let jam = new Date(r.waktu_transaksi).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
                        let tgl = new Date(r.waktu_transaksi).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
                        
                        tableData.push([
                            index + 1,
                            `${tgl} ${jam}`,
                            r.judul_film,
                            r.nama_tipe,
                            r.jumlah_tiket + " Tkt",
                            "Rp " + new Intl.NumberFormat('id-ID').format(r.total_pendapatan)
                        ]);
                    });

                    // --- 3. Tambahkan Baris TOTAL KESELURUHAN Paling Bawah ---
                    tableData.push([
                        "", "", "", "TOTAL KESELURUHAN", 
                        d.kpi.visitor + " Tkt", 
                        "Rp " + new Intl.NumberFormat('id-ID').format(d.kpi.rev)
                    ]);

                    // --- 4. Render Tabel ke PDF ---
                    doc.autoTable({
                        startY: 50,
                        head: [['No', 'Waktu Transaksi', 'Judul Film', 'Studio', 'Tiket', 'Pendapatan']],
                        body: tableData,
                        theme: 'grid',
                        headStyles: { fillColor: [229, 9, 20], textColor: [255, 255, 255], fontStyle: 'bold' }, // Header Merah
                        footStyles: { fillColor: [34, 34, 34] },
                        didParseCell: function (data) {
                            var rows = data.table.body;
                            // Logika khusus mewarnai baris terakhir (Total Keseluruhan)
                            if (data.row.index === rows.length - 1) { 
                                data.cell.styles.fontStyle = 'bold';
                                data.cell.styles.fillColor = [20, 20, 20]; // Background hitam
                                if(data.column.index >= 3) {
                                    data.cell.styles.textColor = [212, 175, 55]; // Warna Teks Emas (Gold)
                                } else {
                                    data.cell.styles.textColor = [255, 255, 255]; // Teks Putih
                                }
                            }
                        }
                    });

                    // --- 5. Download File PDF ---
                    doc.save(`Laporan_Keuangan_Cineplex_${activeTimeframe}.pdf`);
                    
                    // Kembalikan tombol seperti semula
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                })
                .catch(err => {
                    alert("Terjadi kesalahan sistem saat mengekspor PDF.");
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }


        // ===============================================
        // LOGIKA JADWAL TAYANG (POSTER FILM LOCAL)
        // ===============================================
        const jadwalData = [
            { judul: "FAST X", poster: "img/fast.jpg", durasi: "2h 21m", rating: "13+", tipe: "2D", studio: "Studio 1", harga: 40000, jam: ["12:15", "14:40", "17:05", "19:30"], advance: false },
            { judul: "JOHN WICK: CHAPTER 4", poster: "img/johnwick.jpg", durasi: "2h 49m", rating: "17+", tipe: "2D", studio: "Studio 2", harga: 40000, jam: ["12:00", "15:10", "18:20", "21:30"], advance: false },
            { judul: "OPPENHEIMER", poster: "img/oppenheimer.jpg", durasi: "3h 0m", rating: "13+", tipe: "2D", studio: "Studio 3", harga: 45000, jam: ["13:00", "16:30", "20:00"], advance: false },
            { judul: "THE SUPER MARIO BROS", poster: "img/mariobros.jpg", durasi: "1h 32m", rating: "SU", tipe: "2D", studio: "Studio 4", harga: 45000, jam: ["12:30", "14:30", "16:30", "18:30"], advance: false },
            { judul: "EVIL DEAD RISE", poster: "img/evildead.jpg", durasi: "1h 36m", rating: "17+", tipe: "2D", studio: "Studio 5", harga: 50000, jam: ["13:15", "15:20", "17:30", "19:40"], advance: false },
            { judul: "GUARDIANS OF THE GALAXY 3", poster: "img/gotg3.jpg", durasi: "2h 30m", rating: "13+", tipe: "2D", studio: "Studio 6", harga: 50000, jam: ["12:45", "15:45", "18:45", "21:45"], advance: false },
            { judul: "SPIDER-MAN: ACROSS THE SPIDER-VERSE", poster: "img/spiderman.jpg", durasi: "2h 20m", rating: "SU", tipe: "2D", studio: "Studio 7", harga: 55000, jam: ["12:10", "14:50", "17:30", "20:10"], advance: false },
            { judul: "MISSION: IMPOSSIBLE - DEAD RECKONING", poster: "img/missionimpossible.jpg", durasi: "2h 43m", rating: "13+", tipe: "2D", studio: "VVIP Premiere", harga: 120000, jam: ["13:30", "17:00", "20:30"], advance: true }
        ];

        const akanTayangData = [
            { judul: "SEKAWAN LIMO 2: GUNUNG KAWI", poster: "img/sekawanlimo2.jpg", durasi: "2h 2m", rating: "13+", tipe: "2D", tanggal: "Tayang: 27 Mei 2026", advance: true },
            { judul: "BADUT GENDONG", poster: "img/badutgendong.jpg", durasi: "1h 41m", rating: "17+", tipe: "2D", tanggal: "Tayang: 27 Mei 2026", advance: false },
            { judul: "CHILDREN OF HEAVEN", poster: "img/childrenofheaven.jpg", durasi: "1h 37m", rating: "SU", tipe: "2D", tanggal: "Tayang: 27 Mei 2026", advance: false },
            { judul: "STAR WARS: MANDALORIAN & GROGU", poster: "img/starwars.jpg", durasi: "2h 12m", rating: "13+", tipe: "2D", tanggal: "Tayang: 20 Mei 2026", advance: false },
            { judul: "MOBILE SUIT GUNDAM HATHAWAY", poster: "img/gundam.jpg", durasi: "1h 49m", rating: "13+", tipe: "2D", tanggal: "Tayang: 29 Mei 2026", advance: false },
            { judul: "COLONY", poster: "img/colony.jpg", durasi: "2h 2m", rating: "13+", tipe: "2D", tanggal: "Tayang: 3 Jun 2026", advance: true },
            { judul: "MONSTER PABRIK RAMBUT", poster: "img/monsterpabrikrambut.jpg", durasi: "1h 36m", rating: "17+", tipe: "2D", tanggal: "Tayang: 4 Jun 2026", advance: true }
        ];

        function renderJadwal() {
            const containerNow = document.getElementById('jadwal-container');
            containerNow.innerHTML = '';
            jadwalData.forEach(m => {
                let badgeAdvance = m.advance ? `<div class="advance-badge">Advance ticket sales</div>` : '';
                let studioClass = m.advance ? 'studio-name vvip' : 'studio-name';
                let iconStudio = m.advance ? '<i class="fa-solid fa-crown me-1"></i>' : '<i class="fa-solid fa-desktop me-1"></i>';
                
                let jamHtml = '';
                m.jam.forEach(j => {
                    let isVvipParam = m.advance ? 'true' : 'false';
                    jamHtml += `<div class="showtime-btn" onclick="bukaBooking('${m.studio}', '${m.judul}', '${j}', ${m.harga}, ${isVvipParam})">${j}</div>`;
                });

                containerNow.innerHTML += `
                <div class="col-md-3">
                    <div class="movie-card">
                        ${badgeAdvance}
                        <img src="${m.poster}" class="movie-poster" alt="${m.judul}">
                        <div class="movie-info">
                            <div class="movie-title">${m.judul}</div>
                            <div class="movie-tags">
                                <span>${m.durasi}</span><span class="rating">${m.rating}</span><span>${m.tipe}</span>
                            </div>
                            <div class="${studioClass}">
                                ${iconStudio} ${m.studio}
                                <span class="studio-price">Rp ${new Intl.NumberFormat('id-ID').format(m.harga)}</span>
                            </div>
                            <div class="showtime-grid">${jamHtml}</div>
                        </div>
                    </div>
                </div>`;
            });

            const containerSoon = document.getElementById('coming-soon-container');
            containerSoon.innerHTML = '';
            akanTayangData.forEach(m => {
                let badgeAdvance = m.advance ? `<div class="advance-badge">Advance ticket sales</div>` : '';
                containerSoon.innerHTML += `
                <div class="col-md-3">
                    <div class="movie-card">
                        ${badgeAdvance}
                        <img src="${m.poster}" class="movie-poster" alt="${m.judul}">
                        <div class="movie-info">
                            <div class="movie-title">${m.judul}</div>
                            <div class="movie-tags">
                                <span>${m.durasi}</span><span class="rating">${m.rating}</span><span>${m.tipe}</span>
                            </div>
                            <div class="release-date">
                                <i class="fa-solid fa-calendar-check me-2"></i> ${m.tanggal}
                            </div>
                        </div>
                    </div>
                </div>`;
            });
        }
        renderJadwal();

        // LOGIKA MEMBER
        let membersData = [
            { id: '#MBR-9012', nama: 'Ahmad Fathur', tingkat: 'Gold', badgeColor: 'bg-warning text-dark', poin: '1,200 Pts', status: 'Aktif', statusColor: 'text-success' },
            { id: '#MBR-9013', nama: 'Siti Nurbaya', tingkat: 'Silver', badgeColor: 'bg-secondary text-white', poin: '450 Pts', status: 'Aktif', statusColor: 'text-success' }
        ];

        function renderMembers() {
            const tbody = document.getElementById('member-table-body');
            tbody.innerHTML = '';
            if(membersData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-white fw-bold">Data Member Kosong</td></tr>';
                return;
            }
            membersData.forEach((m, index) => {
                tbody.innerHTML += `<tr>
                    <td class="text-white fw-bold">${m.id}</td>
                    <td class="text-white fw-bold">${m.nama}</td>
                    <td><span class="badge ${m.badgeColor} fw-bold" style="border-radius:4px; padding: 5px 10px;">${m.tingkat}</span></td>
                    <td class="text-white fw-bold">${m.poin}</td>
                    <td class="${m.statusColor} fw-bold">${m.status}</td>
                    <td class="text-center"><button class="btn btn-sm btn-outline-danger fw-bold" onclick="deleteMember(${index})"><i class="fa-solid fa-trash"></i> Hapus</button></td>
                </tr>`;
            });
        }

        function deleteMember(index) {
            if (confirm(`Hapus member ${membersData[index].nama}?`)) { membersData.splice(index, 1); renderMembers(); }
        }

        function simpanMemberBaru() {
            const nama = document.getElementById('inputNamaMember').value;
            const tingkat = document.getElementById('inputTingkatMember').value;
            if(nama.trim() === '') { alert("Nama member wajib diisi!"); return; }
            const randomId = '#MBR-' + Math.floor(Math.random() * 9000 + 1000);
            let badgeStyle = '', poinAwal = '';
            if(tingkat === 'Bronze') { badgeStyle = 'bg-dark border border-secondary text-white'; poinAwal = '50 Pts'; }
            if(tingkat === 'Silver') { badgeStyle = 'bg-secondary text-white'; poinAwal = '150 Pts'; }
            if(tingkat === 'Gold')   { badgeStyle = 'bg-warning text-dark'; poinAwal = '500 Pts'; }

            membersData.unshift({ id: randomId, nama: nama, tingkat: tingkat, badgeColor: badgeStyle, poin: poinAwal, status: 'Aktif', statusColor: 'text-success' });
            renderMembers();
            var modalInstance = bootstrap.Modal.getInstance(document.getElementById('tambahMemberModal'));
            modalInstance.hide();
            document.getElementById('inputNamaMember').value = '';
        }
        renderMembers();

        // LOGIKA DENAH KURSI & TRANSAKSI
        let hargaSaatIni = 0; 
        let kursiDipilih = [];
        let currentJudulFilm = '';
        let currentNamaStudio = '';

        function bukaBooking(studio, judul, jam, harga, isVvip) {
            hargaSaatIni = harga; 
            kursiDipilih = []; 
            currentNamaStudio = studio;
            currentJudulFilm = judul;

            document.getElementById('book-title').innerText = `${studio} - ${judul} (${jam})`;
            document.getElementById('book-price').innerText = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(harga) + ' / Tiket';
            updatePanelBooking(); 
            
            document.querySelectorAll('.view-section').forEach(el => el.style.display = 'none');
            document.getElementById('view-booking').style.display = 'block';

            const seatMap = document.getElementById('seat-map'); seatMap.innerHTML = '';
            const barisHuruf = isVvip ? ['A','B','C','D'] : ['A','B','C','D','E','F','G','H'];
            const kolomPerSisi = isVvip ? 4 : 7; 
            
            barisHuruf.forEach(baris => {
                let htmlBaris = `<div class="seat-row">`;
                for(let i=1; i<=kolomPerSisi; i++) {
                    let idKursi = baris + i; let sudahTerjual = Math.random() < 0.15; 
                    let kelasKursi = isVvip ? 'seat vvip-seat' : 'seat';
                    if(sudahTerjual) kelasKursi += ' sold';
                    htmlBaris += `<div class="${kelasKursi}" id="${idKursi}" onclick="pilihKursi(this, '${idKursi}')">${idKursi}</div>`;
                }
                htmlBaris += `<div class="seat-gap"></div>`;
                for(let i=kolomPerSisi+1; i<=kolomPerSisi*2; i++) {
                    let idKursi = baris + i; let sudahTerjual = Math.random() < 0.15;
                    let kelasKursi = isVvip ? 'seat vvip-seat' : 'seat';
                    if(sudahTerjual) kelasKursi += ' sold';
                    htmlBaris += `<div class="${kelasKursi}" id="${idKursi}" onclick="pilihKursi(this, '${idKursi}')">${idKursi}</div>`;
                }
                htmlBaris += `</div>`; seatMap.innerHTML += htmlBaris;
            });
        }

        function pilihKursi(elemenKursi, idKursi) {
            if(elemenKursi.classList.contains('sold')) return;
            if(elemenKursi.classList.contains('selected')) {
                elemenKursi.classList.remove('selected');
                kursiDipilih = kursiDipilih.filter(k => k !== idKursi);
            } else {
                elemenKursi.classList.add('selected'); kursiDipilih.push(idKursi);
            }
            kursiDipilih.sort(); updatePanelBooking();
        }

        function updatePanelBooking() {
            const jumlahTiket = kursiDipilih.length;
            document.getElementById('lbl-count').innerText = jumlahTiket;
            if(jumlahTiket > 0) {
                document.getElementById('lbl-seats').innerText = "Kursi: " + kursiDipilih.join(', ');
                document.getElementById('lbl-seats').className = 'text-gold fw-bold';
            } else {
                document.getElementById('lbl-seats').innerText = 'Belum ada kursi yang dipilih';
                document.getElementById('lbl-seats').className = 'text-danger fw-bold';
            }
            document.getElementById('lbl-total').innerText = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(jumlahTiket * hargaSaatIni);
        }

        function prosesTiket() {
            if(kursiDipilih.length === 0) { alert("Pilih minimal 1 kursi terlebih dahulu!"); return; }
            
            const btnProses = document.querySelector('.booking-panel .btn-danger');
            btnProses.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> MENYIMPAN...';
            btnProses.disabled = true;

            // Siapkan data untuk dikirim ke database
            const payload = {
                judul: currentJudulFilm,
                studio: currentNamaStudio,
                qty: kursiDipilih.length,
                total: kursiDipilih.length * hargaSaatIni
            };

            // Kirim ke API PHP
            fetch('index.php?action=buy_ticket', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                btnProses.innerHTML = 'PROSES TIKET <i class="fa-solid fa-print ms-2"></i>';
                btnProses.disabled = false;
                
                if(data.success) {
                    alert(`TRANSAKSI BERHASIL!\nTiket dicetak untuk kursi: ${kursiDipilih.join(', ')}.\nTotal Pendapatan bertambah: Rp ${new Intl.NumberFormat('id-ID').format(payload.total)}`);
                    
                    fetchData(); // Tarik data terbaru dari database
                    switchView('view-dashboard', document.querySelector('.menu-item:nth-child(1)')); // Pindah layar ke Dashboard untuk melihat grafik naik!
                } else {
                    alert("Gagal memproses tiket! Penyebab: " + data.error);
                }
            })
            .catch(err => {
                btnProses.innerHTML = 'PROSES TIKET <i class="fa-solid fa-print ms-2"></i>';
                btnProses.disabled = false;
                alert("Terjadi kesalahan jaringan.");
            });
        }

        // LOGIKA FILTER DAN FETCH TABEL (DASHBOARD)
        let cStudio = null; let cFilm = null;
        let activeTimeframe = 'today';

        function setFilter(time, btn) {
            activeTimeframe = time;
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            if(btn) btn.classList.add('active');
            
            let lbl = '';
            if(time === 'today') lbl = '(Hari Ini)';
            else if(time === 'weekly') lbl = '(7 Hari)';
            else if(time === 'monthly') lbl = '(1 Bulan)';
            else if(time === 'yearly') lbl = '(1 Tahun)';
            else if(time === '5years') lbl = '(5 Tahun)';
            
            document.getElementById('lbl-time1').innerText = lbl;
            fetchData();
        }

        function fetchData() {
            fetch(`index.php?action=get_data&time=${activeTimeframe}`)
                .then(r => r.json())
                .then(d => {
                    if(!d.success) return;
                    document.getElementById('kpi-rev').innerText = new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(d.kpi.rev);
                    document.getElementById('kpi-vis').innerText = d.kpi.visitor + " Orang";
                    document.getElementById('kpi-trx').innerText = d.kpi.rows + " TRX";

                    const tbody = document.getElementById('table-body');
                    tbody.innerHTML = '';
                    d.table.forEach(r => {
                        let jam = new Date(r.waktu_transaksi).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
                        let tgl = new Date(r.waktu_transaksi).toLocaleDateString('id-ID', {day:'2-digit', month:'short'});
                        
                        tbody.innerHTML += `<tr>
                            <td class="text-white fw-bold">${tgl} ${jam}</td>
                            <td class="text-white fw-bold">${r.judul_film}</td>
                            <td><span class="badge-studio fw-bold text-white">${r.nama_tipe}</span></td>
                            <td class="text-white fw-bold"><i class="fa-solid fa-location-dot text-danger"></i> ${r.kota_cabang}</td>
                            <td class="text-white fw-bold">${r.jumlah_tiket} Tkt</td>
                            <td class="text-gold fw-bold">Rp ${new Intl.NumberFormat('id-ID').format(r.total_pendapatan)}</td>
                        </tr>`;
                    });

                    let stLab = d.chart_studio.map(x=>x.nama_tipe);
                    let stVal = d.chart_studio.map(x=>x.total);
                    if(cStudio) { cStudio.data.labels=stLab; cStudio.data.datasets[0].data=stVal; cStudio.update(); }
                    else {
                        cStudio = new Chart(document.getElementById('studioChart'), {
                            type: 'bar',
                            data: { labels: stLab, datasets: [{ label: 'Pendapatan', data: stVal, backgroundColor: '#e50914', borderRadius: 4 }] },
                            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{grid:{color:'#222'}}, x:{grid:{display:false}} } }
                        });
                    }

                    let flLab = d.chart_film.map(x=>x.judul_film);
                    let flVal = d.chart_film.map(x=>x.v);
                    if(cFilm) { cFilm.data.labels=flLab; cFilm.data.datasets[0].data=flVal; cFilm.update(); }
                    else {
                        cFilm = new Chart(document.getElementById('filmChart'), {
                            type: 'doughnut',
                            data: { labels: flLab, datasets: [{ data: flVal, backgroundColor: ['#e50914','#d4af37','#333','#555','#111','#888'], borderWidth: 2, borderColor: '#151515' }] },
                            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'right', labels:{color:'#888'}}} }
                        });
                    }
                });
        }

        fetchData();
        setInterval(fetchData, 3000); 
    </script>
</body>
</html>

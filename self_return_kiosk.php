<?php

global $sysconf;
use SLiMS\DB;

$db = DB::getInstance();
date_default_timezone_set('Asia/Jakarta');
$db->exec("SET time_zone = '+07:00'");

if (($_GET['key'] ?? '') !== $TOKEN) {
    http_response_code(403);
    die('ACCESS DENIED');
}

// ==========================================
// BLOK FUNGSI: LOG SISTEM & PENGHITUNG DENDA
// ==========================================
function sys_log($db,$member,$action,$msg){
    $stmt=$db->prepare("INSERT INTO system_log (log_type,id,log_location,sub_module,action,log_msg,log_date) VALUES ('system', ?, 'kiosk', 'self_return', ?, ?, NOW())");
    $stmt->execute([$member,$action,$msg]);
}

function countLateWorkingDays($db,$due,$return){
    $start=strtotime($due);
    $end=strtotime($return);
    $stmt=$db->query("SELECT holiday_date FROM holiday");
    $holidays=$stmt->fetchAll(PDO::FETCH_COLUMN);
    $late=0;
    while($start<$end){
        $start=strtotime("+1 day",$start);
        $date=date('Y-m-d',$start);
        $day=date('N',$start);
        if($day==6||$day==7) continue; // Lewati Sabtu & Minggu
        if(in_array($date,$holidays)) continue; // Lewati Hari Libur Nasional
        $late++;
    }
    return $late;
}

// ==========================================
// BLOK HANDLER: PROSES DATA DARI SCANNER
// ==========================================
if($_SERVER['REQUEST_METHOD']=='POST'){
    header('Content-Type: application/json');
    $mode=$_POST['mode'] ?? '';

    // 1. SIMPAN REVIEW BINTANG
    if($mode == 'save_review'){
        $stmt = $db->prepare("INSERT INTO book_review (member_id, biblio_id, rating, review_text, review_date) VALUES (?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([$_POST['member_id'], $_POST['biblio_id'], $_POST['rating'], $_POST['review_text']]);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // 2. CEK MEMBER & AMBIL DAFTAR BUKU YANG DIPINJAM
    if($mode=='member'){
        // Menerapkan Super Cleaner untuk ID Member
        $member = preg_replace('/[\x00-\x1F\x7F]/', '', trim($_POST['member'] ?? ''));

        $stmt=$db->prepare("SELECT member_id,member_name FROM member WHERE member_id=?");
        $stmt->execute([$member]);
        $m=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$m){
            echo json_encode(['ok'=>false, 'msg'=>'Member tidak ditemukan']); exit;
        }

        // Ambil rincian buku yang sedang dipinjam (bukan cuma angka/jumlahnya)
        $stmtLoan = $db->prepare("SELECT b.title, l.due_date FROM loan l JOIN item i ON i.item_code=l.item_code JOIN biblio b ON b.biblio_id=i.biblio_id WHERE l.member_id=? AND l.is_return=0");
        $stmtLoan->execute([$member]);
        $loans = $stmtLoan->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok'=>true, 'member_id'=>$m['member_id'], 'member_name'=>$m['member_name'], 'loans'=>$loans]);
        exit;
    }

    // 3. PROSES PENGEMBALIAN BUKU (LEFT JOIN & SUPER CLEANER)
    if($mode=='return'){
        // Menerapkan Super Cleaner untuk mencegah input karakter gaib dari scanner
        $member = preg_replace('/[\x00-\x1F\x7F]/', '', trim($_POST['member'] ?? ''));
        $item = preg_replace('/[\x00-\x1F\x7F]/', '', trim($_POST['item'] ?? ''));
        $today = date('Y-m-d');

        // LEFT JOIN: Tetap bisa mengembalikan buku meskipun data master katalog terhapus
        $stmt=$db->prepare("
            SELECT l.*, b.title, b.biblio_id 
            FROM loan l 
            LEFT JOIN item i ON i.item_code=l.item_code 
            LEFT JOIN biblio b ON b.biblio_id=i.biblio_id 
            WHERE l.member_id=? AND l.item_code=? AND l.is_return=0
        ");
        $stmt->execute([$member,$item]);
        $loan=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$loan){
            echo json_encode(['ok'=>false, 'msg'=>'Buku tidak ditemukan dalam daftar pinjaman Anda.']); exit;
        }

        $late=0;
        if($today>$loan['due_date']){ $late=countLateWorkingDays($db,$loan['due_date'],$today); }
        $fine=$late*1000; // Denda Rp 1.000 per hari (sesuaikan jika perlu)

        $stmt=$db->prepare("UPDATE loan SET is_return=1, is_lent=0, return_date=?, last_update=NOW() WHERE loan_id=?");
        $stmt->execute([$today,$loan['loan_id']]);

        $title = $loan['title'] ?? 'Judul Tidak Ditemukan (Data Master Hilang)';
        sys_log($db,$member,'return',"Return item $item ($title) by $member");

        echo json_encode(['ok'=>true, 'title'=>$title, 'biblio_id'=>$loan['biblio_id'] ?? 0, 'return_date'=>$today, 'late_days'=>$late, 'fine'=>$fine]);
        exit;
    }
}

$baseURL = rtrim($sysconf['baseurl'] ?? '', '/') . '/';
$logo = $baseURL ? $baseURL.'images/default/logo.png?v='.time() : '../../images/default/logo.png?v='.time();
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengembalian Mandiri - <?= $sysconf['library_name'] ?? '' ?></title>
    <style>
        body { background:#1565d8; color:white; font-family:Arial, sans-serif; text-align:center; margin:0; padding:20px; user-select: none; }
        .hidden { display:none !important; }
        .logo { width:120px; margin-top:10px; }
        
        /* Input Styling */
        input { width:650px; max-width:90%; padding:22px; font-size:28px; border-radius:12px; border:none; margin:20px auto; text-align:center; display:block; }
        input:disabled { background: #e0e0e0; cursor: not-allowed; }
        .btn { padding:15px 30px; font-size:20px; cursor:pointer; border-radius:12px; border:none; font-weight:bold; background: #ff5252; color: white; margin: 15px; }
        .btn:hover { background: #ff1744; }

        /* Running Text */
        .running-container { width: 750px; max-width: 90%; margin: 10px auto; overflow: hidden; background: rgba(255,255,255,0.12); border-radius: 10px; padding: 10px 0; }
        .running-text { display: inline-block; white-space: nowrap; font-size: 18px; font-weight: bold; color: #ffd54f; padding-left: 100%; animation: runningText 35s linear infinite; }
        @keyframes runningText { from { transform: translateX(0); } to { transform: translateX(-100%); } }
        
        /* Informasi & Timer */
        .info-box { font-size:22px; margin:15px auto; padding:20px; background:rgba(0,0,0,0.2); border-radius:15px; width: 700px; max-width: 90%; text-align:left; }
        .result-box { margin-top:20px; font-size:24px; padding:15px; background:rgba(255,255,255,0.1); border-radius:10px; }
        .countdown-text { font-size: 20px; font-weight: bold; color: #ffff99; margin-top: 15px; }

        /* CSS Modal Review */
        #reviewModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 999999; align-items: center; justify-content: center; }
        .modal-box { background: white; color: #333; padding: 40px; border-radius: 20px; width: 650px; }
        .stars { font-size: 60px; margin: 20px 0; color: #ddd; }
        .stars span { cursor: pointer; padding: 0 5px; }
        .stars span.active { color: #ffca08; }
        .p-btn { background:#f0f0f0; border:1px solid #ddd; padding:15px; width: 30%; cursor:pointer; border-radius:10px; margin:5px; font-weight:bold; font-size: 14px;}
        .p-btn:hover { background: #1565d8; color: white; }
    </style>
</head>
<body>

<img src="<?= $logo ?>" class="logo">
<h2><?= $sysconf['library_name'] ?? 'Perpustakaan' ?></h2>
<h1>Layanan Pengembalian Mandiri</h1>

<div class="running-container">
    <div class="running-text">📌 Petunjuk Penggunaan : Scan Kartu Anggota • Periksa Daftar Pinjaman • Scan Barcode Buku • Tunggu Konfirmasi • Jika ada denda hubungi petugas</div>
</div>

<div id="state1">
    <input id="memberInput" placeholder="SCAN KARTU ANGGOTA" autocomplete="off">
    <div id="cdState1" class="countdown-text hidden"></div>
    <div id="mError" style="color:#ff5252; font-size:24px; font-weight:bold; margin-top:15px;"></div>
</div>

<div id="state2" class="hidden">
    <div id="mInfo" class="info-box"></div>
    
    <input id="itemInput" placeholder="SCAN BARCODE BUKU" autocomplete="off">
    
    <div id="cdState2" class="countdown-text hidden"></div>
    <div id="resBox"></div>
    
    <button class="btn" onclick="resetAll()">⬅ SELESAI / KELUAR</button>
</div>

<div id="reviewModal">
    <div class="modal-box">
        <h2>Buku Berhasil Dikembalikan!</h2>
        <p>Bantu kami dengan menilai kondisi buku tersebut:</p>
        <div class="stars" id="starContainer">
            <span data-v="1">★</span><span data-v="2">★</span><span data-v="3">★</span><span data-v="4">★</span><span data-v="5">★</span>
        </div>
        <div id="phraseArea" class="hidden">
            <div class="phrase-container" id="phraseList" style="display:flex; justify-content:center; flex-wrap:wrap;"></div>
        </div>
        <div style="margin-top:20px;">
            <button class="btn" style="background:#ddd; color:#333; margin:0;" onclick="closeModal()">Lewati (Tutup)</button>
        </div>
        <div id="cdState3" class="countdown-text" style="color: #ff9800;"></div>
    </div>
</div>

<script>
let curMember = null;
let curBiblio = null;
let isProcessing = false; // Flag Anti Double-Scan
const KIOSK_PASSWORD = "M@jub3rs@m@";

// ==========================================
// BLOK JAVASCRIPT: TIMER VISUAL GLOBAL
// ==========================================
let visualTimerInterval;
function startVisualTimer(seconds, elementId, timeoutAction) {
    clearInterval(visualTimerInterval);
    let timeLeft = seconds;
    const el = document.getElementById(elementId);
    
    if(el) { el.classList.remove("hidden"); el.innerHTML = `⏳ Sesi akan ditutup dalam <b>${timeLeft}</b> detik...`; }

    visualTimerInterval = setInterval(() => {
        timeLeft--;
        if(el) el.innerHTML = `⏳ Sesi akan ditutup dalam <b>${timeLeft}</b> detik...`;
        if (timeLeft <= 0) {
            clearInterval(visualTimerInterval);
            if(timeoutAction === 'resetAll') resetAll();
            else if(timeoutAction === 'closeModal') closeModal();
        }
    }, 1000);
}
function clearVisualTimer() {
    clearInterval(visualTimerInterval);
    document.getElementById("cdState1").classList.add("hidden");
    document.getElementById("cdState2").classList.add("hidden");
}

// ==========================================
// BLOK JAVASCRIPT: KIOSK PROTEKSI & AUTO-FOCUS
// ==========================================
async function enterFullscreen() {
    if(!document.fullscreenElement) { try { await document.documentElement.requestFullscreen(); } catch(e){} }
}
document.addEventListener("contextmenu", e => e.preventDefault()); // Blokir klik kanan
document.addEventListener("fullscreenchange", () => {
    if(!document.fullscreenElement) {
        let pass = prompt("Masukkan password untuk keluar dari mode kiosk:");
        if(pass === KIOSK_PASSWORD) { alert("Mode kiosk dinonaktifkan"); } 
        else { enterFullscreen(); }
    }
});

// Fitur Auto-Focus Persisten: Kursor akan selalu dipaksa masuk ke input yang benar
document.addEventListener("click", (e) => {
    enterFullscreen();
    if (document.getElementById("reviewModal").style.display === "flex") return; // Abaikan jika pop-up review aktif
    
    if (!document.getElementById("state1").classList.contains("hidden")) {
        document.getElementById("memberInput").focus();
    } else if (!document.getElementById("state2").classList.contains("hidden")) {
        if (e.target.tagName !== "BUTTON") { document.getElementById("itemInput").focus(); }
    }
});

function resetAll() {
    clearVisualTimer();
    isProcessing = false;
    curMember = null; curBiblio = null;
    
    document.getElementById("memberInput").value = "";
    document.getElementById("memberInput").disabled = false;
    document.getElementById("itemInput").value = "";
    document.getElementById("itemInput").disabled = false;
    
    document.getElementById("mError").innerHTML = "";
    document.getElementById("mInfo").innerHTML = "";
    document.getElementById("resBox").innerHTML = "";
    
    document.getElementById("state2").classList.add("hidden");
    document.getElementById("state1").classList.remove("hidden");
    
    closeModal();
    document.getElementById("memberInput").focus();
}

// ==========================================
// BLOK JAVASCRIPT: HANDLER SCAN MEMBER
// ==========================================
document.getElementById("memberInput").onkeydown = async (e) => {
    if(e.key === "Enter") {
        if(isProcessing) return; // Anti Double-Scan Aktif
        const val = e.target.value.trim();
        if(!val) return;
        
        isProcessing = true;
        e.target.disabled = true; 
        
        const fd = new FormData(); fd.append("mode", "member"); fd.append("member", val);
        const res = await fetch(window.location.href, { method: "POST", body: fd });
        const j = await res.json();
        
        if(j.ok) {
            curMember = j.member_id;
            
            // Generate HTML Daftar Pinjaman Aktif
            let html = `<div style="color:#ffd54f; margin-bottom:10px;"><b>Halo, ${j.member_name} (ID: ${j.member_id})</b></div>`;
            if(j.loans.length > 0){
                html += `📚 <b>Buku yang sedang Anda pinjam:</b><ul style="padding-left:20px; margin:10px 0; font-size:18px;">`;
                j.loans.forEach(loan => {
                    html += `<li style="padding:4px 0; border-bottom:1px solid rgba(255,255,255,0.2);">
                                ${loan.title} <br><small style="color:#a0ffae;">Jatuh Tempo: ${loan.due_date}</small>
                             </li>`;
                });
                html += `</ul>`;
            } else {
                html += `<div style='color:#a0ffae;'>Anda tidak memiliki pinjaman aktif saat ini.</div>`;
            }

            document.getElementById("mInfo").innerHTML = html;
            
            // Pindah ke State 2
            document.getElementById("state1").classList.add("hidden");
            document.getElementById("state2").classList.remove("hidden");
            startVisualTimer(45, 'cdState2', 'resetAll'); // Timer 45 detik untuk antre scan buku
            document.getElementById("itemInput").focus();
        } else {
            document.getElementById("mError").innerHTML = j.msg;
            setTimeout(() => { document.getElementById("mError").innerHTML = ""; document.getElementById("memberInput").value = ""; document.getElementById("memberInput").focus(); }, 2000);
        }
        
        isProcessing = false;
        e.target.disabled = false;
        if(j.ok) { e.target.value = ""; }
    }
};

// ==========================================
// BLOK JAVASCRIPT: HANDLER SCAN BUKU (ITEM)
// ==========================================
document.getElementById("itemInput").onkeydown = async (e) => {
    if(e.key === "Enter") {
        if(isProcessing) return; // Anti Double-Scan Aktif
        const itemVal = e.target.value.trim();
        if(!itemVal) return;
        
        isProcessing = true;
        e.target.disabled = true;
        
        const fd = new FormData(); fd.append("mode", "return"); fd.append("member", curMember); fd.append("item", itemVal);
        const res = await fetch(window.location.href, { method: "POST", body: fd });
        const j = await res.json();
        
        if(j.ok) {
            curBiblio = j.biblio_id;
            
            // Generate HTML Hasil Pengembalian
            let resHtml = `<div class="result-box" style="border-left: 5px solid #00ff99;">
                            <span style="color:#00ff99; font-weight:bold;">✅ Berhasil Dikembalikan</span><br>
                            Judul: <b>${j.title}</b><br>`;
            if(j.late_days > 0) {
                resHtml += `<span style="color:#ff5252;">Terlambat: ${j.late_days} Hari | Denda: Rp ${j.fine.toLocaleString('id-ID')}</span>`;
            } else {
                resHtml += `<span style="color:#a0ffae;">Status: Tepat Waktu</span>`;
            }
            resHtml += `</div>`;
            
            document.getElementById("resBox").innerHTML = resHtml;
            openModal(); // Buka Pop-up Review Buku
        } else {
            document.getElementById("resBox").innerHTML = `<div class="result-box" style="border-left: 5px solid #ff5252; color:#ff5252;">❌ ${j.msg}</div>`;
            startVisualTimer(45, 'cdState2', 'resetAll'); // Lanjutkan timer jika gagal
        }
        
        isProcessing = false;
        e.target.disabled = false;
        e.target.value = "";
    }
};

// ==========================================
// BLOK JAVASCRIPT: LOGIKA POP-UP REVIEW
// ==========================================
const reviewTexts = {
    1: ["Sangat Rusak", "Halaman Banyak Hilang", "Tidak Layak Baca"],
    2: ["Kondisi Buruk", "Banyak Coretan", "Sampul Lepas"],
    3: ["Kondisi Standar", "Cukup Terawat", "Informasi Biasa"],
    4: ["Kondisi Bagus", "Isi Menarik", "Sangat Membantu"],
    5: ["Sangat Terawat", "Materi Luar Biasa", "Sangat Direkomendasikan"]
};

function openModal() {
    clearVisualTimer(); // Hentikan timer belakang layar
    document.getElementById("reviewModal").style.display = "flex";
    document.querySelectorAll(".stars span").forEach(s => s.classList.remove("active"));
    document.getElementById("phraseArea").classList.add("hidden");
    startVisualTimer(15, 'cdState3', 'closeModal'); // Beri waktu 15 detik untuk isi rating
}

document.querySelectorAll(".stars span").forEach(star => {
    star.onclick = function(e) {
        e.stopPropagation();
        const val = this.getAttribute("data-v");
        document.querySelectorAll(".stars span").forEach((s, i) => s.classList.toggle("active", i < val));
        document.getElementById("phraseArea").classList.remove("hidden");
        const list = document.getElementById("phraseList");
        list.innerHTML = "";
        
        startVisualTimer(15, 'cdState3', 'closeModal'); // Reset timer modal jika ada interaksi
        
        let ph = reviewTexts[val];
        ph.forEach(txt => {
            const b = document.createElement("button"); b.className = "p-btn"; b.innerText = txt;
            b.onclick = (e) => {
                e.stopPropagation();
                const fdr = new FormData(); fdr.append("mode", "save_review"); fdr.append("member_id", curMember); fdr.append("biblio_id", curBiblio); fdr.append("rating", val); fdr.append("review_text", txt);
                fetch(window.location.href, { method: "POST", body: fdr });
                closeModal();
            };
            list.appendChild(b);
        });
    };
});

function closeModal() {
    document.getElementById("reviewModal").style.display = "none";
    if(!document.getElementById("state2").classList.contains("hidden")) {
        // Refresh daftar pinjaman di latar belakang
        document.getElementById("memberInput").value = curMember;
        document.getElementById("memberInput").dispatchEvent(new KeyboardEvent('keydown', {'key': 'Enter'}));
        
        startVisualTimer(45, 'cdState2', 'resetAll'); // Kembali antre 45 detik untuk buku berikutnya
        document.getElementById("itemInput").focus();
    }
}

window.onload = () => { enterFullscreen(); document.getElementById("memberInput").focus(); };
</script>
</body>
</html>

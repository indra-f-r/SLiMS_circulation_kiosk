<?php
use SLiMS\DB;

global $sysconf;
$db = DB::getInstance();

date_default_timezone_set('Asia/Jakarta');
$db->exec("SET time_zone = '+07:00'");

if (($_GET['key'] ?? '') !== $TOKEN) {
    http_response_code(403);
    die('ACCESS DENIED');
}

// ==========================================
// BLOK FUNGSI: LOG SISTEM
// ==========================================
function sys_log($db,$member,$action,$msg){
    $stmt=$db->prepare("INSERT INTO system_log (log_type,id,log_location,sub_module,action,log_msg,log_date) VALUES ('system', ?, 'kiosk', 'self_extend', ?, ?, NOW())");
    $stmt->execute([$member,$action,$msg]);
}

// ==========================================
// BLOK HANDLER: PROSES DATA DARI SCANNER
// ==========================================
if($_SERVER['REQUEST_METHOD']=='POST'){
    header('Content-Type: application/json');
    $mode=$_POST['mode'] ?? '';

    // 1. CEK MEMBER & AMBIL DETAIL PINJAMAN
    if($mode=='member'){
        // Super Cleaner ID
        $member = preg_replace('/[\x00-\x1F\x7F]/', '', trim($_POST['member'] ?? ''));

        $stmt=$db->prepare("SELECT member_id, member_name FROM member WHERE member_id=?");
        $stmt->execute([$member]);
        $m=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$m){
            echo json_encode(['ok'=>false,'msg'=>'Member tidak ditemukan']); exit;
        }

        // Ambil rincian buku yang sedang dipinjam (Krusial untuk perpanjangan)
        $stmtLoan = $db->prepare("SELECT b.title, l.due_date FROM loan l JOIN item i ON i.item_code=l.item_code JOIN biblio b ON b.biblio_id=i.biblio_id WHERE l.member_id=? AND l.is_return=0");
        $stmtLoan->execute([$member]);
        $loans = $stmtLoan->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok'=>true,
            'member_id'=>$m['member_id'],
            'member_name'=>$m['member_name'],
            'loans'=>$loans
        ]);
        exit;
    }

    // 2. PROSES PERPANJANGAN (EXTEND) DENGAN LEFT JOIN
    if($mode=='extend'){
        $member = preg_replace('/[\x00-\x1F\x7F]/', '', trim($_POST['member'] ?? ''));
        $item = preg_replace('/[\x00-\x1F\x7F]/', '', trim($_POST['item'] ?? ''));
        $today=date('Y-m-d');

        // Menggunakan LEFT JOIN agar aman jika master data hilang
        $stmt=$db->prepare("
            SELECT l.loan_id, l.due_date, l.renewed, b.title, t.loan_periode 
            FROM loan l 
            LEFT JOIN item i ON i.item_code=l.item_code 
            LEFT JOIN biblio b ON b.biblio_id=i.biblio_id 
            LEFT JOIN member m ON m.member_id=l.member_id 
            LEFT JOIN mst_member_type t ON t.member_type_id=m.member_type_id 
            WHERE l.member_id=? AND l.item_code=? AND l.is_return=0
        ");
        $stmt->execute([$member,$item]);
        $loan=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$loan){
            echo json_encode(['ok'=>false,'msg'=>'Buku tidak ditemukan dalam daftar pinjaman Anda']); exit;
        }

        // Cek Jatuh Tempo
        if($today > $loan['due_date']){
            sys_log($db,$member,'extend_denied',"Overdue - item $item");
            echo json_encode(['ok'=>false,'msg'=>'TERLAMBAT JATUH TEMPO<br>Silakan hubungi petugas']); exit;
        }

        // Cek Batas Perpanjangan
        if($loan['renewed'] >= 1){
            sys_log($db,$member,'extend_denied',"Already extended - item $item");
            echo json_encode(['ok'=>false,'msg'=>'SUDAH PERNAH DIPERPANJANG<br>Buku harus dikembalikan']); exit;
        }

        $old_due = $loan['due_date'];
        $periode = $loan['loan_periode'] ?? 7; // Default 7 hari jika data member type hilang
        $new_due = date('Y-m-d', strtotime("+".$periode." days", strtotime($old_due)));

        // Update database
        $stmt=$db->prepare("UPDATE loan SET due_date=?, renewed=renewed+1, last_update=NOW() WHERE loan_id=?");
        $stmt->execute([$new_due,$loan['loan_id']]);

        $title = $loan['title'] ?? 'Judul Tidak Ditemukan';
        sys_log($db,$member,'extend',"Extend item $item from $old_due to $new_due");

        echo json_encode(['ok'=>true, 'title'=>$title, 'old_due'=>$old_due, 'new_due'=>$new_due]);
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
    <title>Perpanjangan Mandiri - <?= $sysconf['library_name'] ?? '' ?></title>
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
    </style>
</head>
<body>

<img src="<?= $logo ?>" class="logo">
<h2><?= $sysconf['library_name'] ?? 'Perpustakaan' ?></h2>
<h1>Layanan Perpanjangan Mandiri</h1>

<div class="running-container">
    <div class="running-text">📌 Petunjuk Penggunaan : Scan Kartu Anggota • Periksa Daftar Pinjaman • Scan Barcode Buku • Tunggu Konfirmasi • Jika buku sudah pernah diperpanjang atau terlambat, hubungi petugas</div>
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

<script>
let curMember = null;
let isProcessing = false; 
const KIOSK_PASSWORD = "[YOUR_PASSWORD]";

// ==========================================
// BLOK JAVASCRIPT: TIMER VISUAL GLOBAL
// ==========================================
let visualTimerInterval;
function startVisualTimer(seconds, elementId) {
    clearInterval(visualTimerInterval);
    let timeLeft = seconds;
    const el = document.getElementById(elementId);
    
    if(el) { el.classList.remove("hidden"); el.innerHTML = `⏳ Sesi akan ditutup dalam <b>${timeLeft}</b> detik...`; }

    visualTimerInterval = setInterval(() => {
        timeLeft--;
        if(el) el.innerHTML = `⏳ Sesi akan ditutup dalam <b>${timeLeft}</b> detik...`;
        if (timeLeft <= 0) {
            clearInterval(visualTimerInterval);
            resetAll();
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
document.addEventListener("contextmenu", e => e.preventDefault()); 
document.addEventListener("fullscreenchange", () => {
    if(!document.fullscreenElement) {
        let pass = prompt("Masukkan password untuk keluar dari mode kiosk:");
        if(pass === KIOSK_PASSWORD) { alert("Mode kiosk dinonaktifkan"); } 
        else { enterFullscreen(); }
    }
});

// Auto-Focus Persisten
document.addEventListener("click", (e) => {
    enterFullscreen();
    if (!document.getElementById("state1").classList.contains("hidden")) {
        document.getElementById("memberInput").focus();
    } else if (!document.getElementById("state2").classList.contains("hidden")) {
        if (e.target.tagName !== "BUTTON") { document.getElementById("itemInput").focus(); }
    }
});

function resetAll() {
    clearVisualTimer();
    isProcessing = false;
    curMember = null;
    
    document.getElementById("memberInput").value = "";
    document.getElementById("memberInput").disabled = false;
    document.getElementById("itemInput").value = "";
    document.getElementById("itemInput").disabled = false;
    
    document.getElementById("mError").innerHTML = "";
    document.getElementById("mInfo").innerHTML = "";
    document.getElementById("resBox").innerHTML = "";
    
    document.getElementById("state2").classList.add("hidden");
    document.getElementById("state1").classList.remove("hidden");
    
    document.getElementById("memberInput").focus();
}

// ==========================================
// BLOK JAVASCRIPT: HANDLER SCAN MEMBER
// ==========================================
document.getElementById("memberInput").onkeydown = async (e) => {
    if(e.key === "Enter") {
        if(isProcessing) return; 
        const val = e.target.value.trim();
        if(!val) return;
        
        isProcessing = true;
        e.target.disabled = true; 
        
        const fd = new FormData(); fd.append("mode", "member"); fd.append("member", val);
        const res = await fetch(window.location.href, { method: "POST", body: fd });
        const j = await res.json();
        
        if(j.ok) {
            curMember = j.member_id;
            
            let html = `<div style="color:#ffd54f; margin-bottom:10px;"><b>Halo, ${j.member_name} (ID: ${j.member_id})</b></div>`;
            if(j.loans.length > 0){
                html += `📚 <b>Daftar buku yang sedang Anda pinjam:</b><ul style="padding-left:20px; margin:10px 0; font-size:18px;">`;
                j.loans.forEach(loan => {
                    html += `<li style="padding:4px 0; border-bottom:1px solid rgba(255,255,255,0.2);">
                                ${loan.title} <br><small style="color:#a0ffae;">Jatuh Tempo Saat Ini: ${loan.due_date}</small>
                             </li>`;
                });
                html += `</ul>`;
            } else {
                html += `<div style='color:#ff5252;'>Anda tidak memiliki pinjaman aktif untuk diperpanjang.</div>`;
            }

            document.getElementById("mInfo").innerHTML = html;
            
            document.getElementById("state1").classList.add("hidden");
            document.getElementById("state2").classList.remove("hidden");
            startVisualTimer(45, 'cdState2'); 
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
// BLOK JAVASCRIPT: HANDLER SCAN BUKU
// ==========================================
document.getElementById("itemInput").onkeydown = async (e) => {
    if(e.key === "Enter") {
        if(isProcessing) return; 
        const itemVal = e.target.value.trim();
        if(!itemVal) return;
        
        isProcessing = true;
        e.target.disabled = true;
        
        const fd = new FormData(); fd.append("mode", "extend"); fd.append("member", curMember); fd.append("item", itemVal);
        const res = await fetch(window.location.href, { method: "POST", body: fd });
        const j = await res.json();
        
        if(j.ok) {
            let resHtml = `<div class="result-box" style="border-left: 5px solid #00ff99;">
                            <span style="color:#00ff99; font-weight:bold;">✅ Berhasil Diperpanjang</span><br>
                            Judul: <b>${j.title}</b><br>
                            <span style="color:#a0ffae;">Jatuh Tempo Baru: ${j.new_due}</span>
                           </div>`;
            
            document.getElementById("resBox").innerHTML = resHtml;
            startVisualTimer(45, 'cdState2'); // Reset timer agar pemustaka bisa baca dulu
        } else {
            document.getElementById("resBox").innerHTML = `<div class="result-box" style="border-left: 5px solid #ff5252; color:#ff5252;">❌ <b>GAGAL:</b> ${j.msg}</div>`;
            startVisualTimer(45, 'cdState2'); 
        }
        
        isProcessing = false;
        e.target.disabled = false;
        e.target.value = "";
        e.target.focus();
    }
};

window.onload = () => { enterFullscreen(); document.getElementById("memberInput").focus(); };
</script>
</body>
</html>

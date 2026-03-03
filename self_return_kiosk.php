<?php

use SLiMS\DB;

$db = DB::getInstance();

date_default_timezone_set('Asia/Jakarta');
$db->exec("SET time_zone = '+07:00'");

$TOKEN='z0Z6olm5RMred0XoksEliwk4CSTL5TZwomNd4d4X4veOB3zFj3u1jMLlEjgXLLvKFHqQwmUDir4iVGfNsLvtetmG6sb9xMGup4FXgqguE4u17TAhjlRODnevsI8junWUSQH6N9DjSWkhkHsVqw2kMERa1yPfQoeyZYI2QCXPP3p7PzykH2iWDmcojSuc2eLxqD7T4xHyyoBKz8G3kA5T7UmzEANJNl9IsDEXfBR38OM32Nq093iTlX3KDFnVCs4stffRFNaAdEnxMXVLWJzQ8OnT4HzzMOcrQBCcz2c5CrXvHEiiIH7KrUQ1ZDxWH1NtHE3RciZq9uNWhQjqO41lPFmXTKkLdK0t2C1Tpr0YCcGwTYweeVoLEY3R81lLnN0B31EGKfI48MDGsgH8BJwVqnuyEsSPyKd55EHegC68YdF2Zi7xQdAHJjdtTvNFnAiLSau6HsmG2f9J3uweynLptbBWHpnZqE2D7i4M0B6h9yGTFuMmVa4xOESe8pNUH9tJ';

if(($_GET['key'] ?? '') !== $TOKEN){
    http_response_code(403);
    die('ACCESS DENIED');
}

/* ================= LOG ================= */

function sys_log($db,$member,$action,$msg){
    $stmt=$db->prepare("
        INSERT INTO system_log
        (log_type,id,log_location,sub_module,action,log_msg,log_date)
        VALUES
        ('system', ?, 'opac', 'self_return', ?, ?, NOW())
    ");
    $stmt->execute([$member,$action,$msg]);
}

/* ================= HITUNG TERLAMBAT (TIDAK HITUNG SABTU/MINGGU/HOLIDAY) ================= */

function countLateWorkingDays($db,$due,$return){

    $start=strtotime($due);
    $end=strtotime($return);
    $late=0;

    while($start < $end){

        $start=strtotime("+1 day",$start);
        $date=date('Y-m-d',$start);
        $day=date('N',$start); // 6=Sabtu 7=Minggu

        if($day==6 || $day==7) continue;

        $stmt=$db->prepare("SELECT COUNT(*) FROM holiday WHERE holiday_date=?");
        $stmt->execute([$date]);

        if($stmt->fetchColumn()>0) continue;

        $late++;
    }

    return $late;
}

/* ================= AJAX ================= */

if($_SERVER['REQUEST_METHOD']=='POST'){

    header('Content-Type: application/json');
    $mode=$_POST['mode'] ?? '';

    /* ===== CEK MEMBER ===== */

    if($mode=='member'){

        $member=$_POST['member'] ?? '';

        $stmt=$db->prepare("
            SELECT member_id,member_name
            FROM member
            WHERE member_id=?
        ");
        $stmt->execute([$member]);
        $m=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$m){
            echo json_encode(['ok'=>false,'msg'=>'Member tidak ditemukan']);
            exit;
        }

        $stmt=$db->prepare("
            SELECT COUNT(*)
            FROM loan
            WHERE member_id=? AND is_return=0
        ");
        $stmt->execute([$member]);
        $count=$stmt->fetchColumn();

        echo json_encode([
            'ok'=>true,
            'member_id'=>$m['member_id'],
            'member_name'=>$m['member_name'],
            'loan_count'=>$count
        ]);
        exit;
    }

    /* ===== PROSES RETURN ===== */

    if($mode=='return'){

        $member=$_POST['member'] ?? '';
        $item=$_POST['item'] ?? '';
        $today=date('Y-m-d');

        $stmt=$db->prepare("
            SELECT l.*,b.title
            FROM loan l
            JOIN item i ON i.item_code=l.item_code
            JOIN biblio b ON b.biblio_id=i.biblio_id
            WHERE l.member_id=? 
            AND l.item_code=? 
            AND l.is_return=0
        ");
        $stmt->execute([$member,$item]);
        $loan=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$loan){
            echo json_encode(['ok'=>false,'msg'=>'Data pinjaman tidak ditemukan']);
            exit;
        }

        $late=0;
        if($today > $loan['due_date']){
            $late=countLateWorkingDays($db,$loan['due_date'],$today);
        }

        $fine=$late*1000;

        $stmt=$db->prepare("
            UPDATE loan
            SET is_return=1,
                is_lent=0,
                return_date=?,
                last_update=NOW()
            WHERE loan_id=?
        ");
        $stmt->execute([$today,$loan['loan_id']]);

        sys_log($db,$member,'return',"Member $member return item $item");

        echo json_encode([
            'ok'=>true,
            'title'=>$loan['title'],
            'return_date'=>$today,
            'late_days'=>$late,
            'fine'=>$fine
        ]);
        exit;
    }
}

$baseURL=rtrim($sysconf['baseurl'],'/').'/';
$logo=$baseURL.'images/default/logo.png?v='.time();
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengembalian Mandiri</title>

<style>
body{
  background:#1565d8;
  color:white;
  font-family:Arial;
  text-align:center;
  margin:0;
}

.logo{width:130px;margin-top:25px}

input{
  width:450px;
  padding:18px;
  font-size:22px;
  border-radius:12px;
  border:none;
  margin:12px;
}

.info{margin-top:15px;font-size:20px;color:#ffd54f}
.result{margin-top:20px;font-size:20px;color:#ffd54f}
.countdown{margin-top:10px;color:#ffff99}

/* ================= RUNNING TEXT ================= */

.running-container{
  width:700px;
  margin:15px auto 10px auto;
  overflow:hidden;
  background:rgba(255,255,255,0.12);
  border-radius:10px;
  padding:8px 0;
}

.running-text{
  display:inline-block;
  white-space:nowrap;
  font-size:18px;
  font-weight:bold;
  color:#ffd54f;
  padding-left:100%;
  animation:runningText 40s linear infinite;
}

@keyframes runningText{
  from{
    transform:translateX(0);
  }
  to{
    transform:translateX(-100%);
  }
}
</style>
</head>

<body>

<img src="<?= $logo ?>" class="logo">
<h1><?= $sysconf['library_name'] ?></h1>
<h2>Layanan Pengembalian Mandiri</h2>

<div class="running-container">
  <div class="running-text">
    📌 Petunjuk Penggunaan :
    &nbsp;&nbsp;&nbsp;
    1. Siapkan Kartu Anggota
    &nbsp;&nbsp;•&nbsp;&nbsp;
    2. Scan Barcode/QRCode Kartu Anggota
    &nbsp;&nbsp;•&nbsp;&nbsp;
    3. Perhatikan status peminjaman
    &nbsp;&nbsp;•&nbsp;&nbsp;
    4. Scan Barcode/QRCode Buku/APE yang Akan Dikembalikan
    &nbsp;&nbsp;•&nbsp;&nbsp;
    5. Perhatikan Status Pengembalian
    &nbsp;&nbsp;•&nbsp;&nbsp;
    6. Jika ada denda, silakan konfirmasi dengan petugas
    &nbsp;&nbsp;&nbsp;
    7. Ulangi jika meminjam lebih dari 1 buku/APE, Pastikan Tidak Ada Buku/APE yang Tertinggal
    &nbsp;&nbsp;&nbsp;📚
  </div>
</div>

<input id="member" placeholder="SCAN KARTU ANGGOTA">
<br>
<input id="item" placeholder="SCAN QRCODE/BARCODE BUKU/APE" disabled>

<div id="memberInfo" class="info"></div>
<div id="result" class="result"></div>
<div id="countdown" class="countdown"></div>

<script>

const m=document.getElementById('member');
const i=document.getElementById('item');
const memberInfo=document.getElementById('memberInfo');
const result=document.getElementById('result');
const countdown=document.getElementById('countdown');

let timer=null;
let interval=null;

/* ================= RESET ================= */

function resetAll(){
    m.disabled=false;
    i.disabled=true;
    m.value='';
    i.value='';
    memberInfo.innerHTML='';
    result.innerHTML='';
    countdown.innerHTML='';
    m.focus();
}

/* ================= COUNTDOWN ================= */

function startCountdown(){

    clearTimeout(timer);
    clearInterval(interval);

    let sec=5;
    countdown.innerHTML="Reset dalam "+sec+" detik";

    interval=setInterval(()=>{
        sec--;
        countdown.innerHTML="Reset dalam "+sec+" detik";
        if(sec<=0){
            clearInterval(interval);
        }
    },1000);

    timer=setTimeout(resetAll,5000);
}

/* ================= SCAN MEMBER ================= */

m.addEventListener('keydown',async function(e){

    if(e.key!='Enter') return;
    if(m.value.trim()=='') return;

    const fd=new FormData();
    fd.append('mode','member');
    fd.append('member',m.value);

    const res=await fetch('?key=<?= $TOKEN ?>',{method:'POST',body:fd});
    const j=await res.json();

    if(!j.ok){
        memberInfo.innerHTML=j.msg;
        startCountdown();
        return;
    }

    memberInfo.innerHTML=
        "Nama : "+j.member_name+"<br>"+
        "ID : "+j.member_id+"<br>"+
        "Pinjaman aktif : "+j.loan_count+" buku";

    m.disabled=true;
    i.disabled=false;
    i.focus();

    startCountdown();
});

/* ================= SCAN ITEM ================= */

i.addEventListener('keydown',async function(e){

    if(e.key!='Enter') return;
    if(i.value.trim()=='') return;

    clearTimeout(timer);
    clearInterval(interval);
    countdown.innerHTML='';

    const fd=new FormData();
    fd.append('mode','return');
    fd.append('member',m.value);
    fd.append('item',i.value);

    const res=await fetch('?key=<?= $TOKEN ?>',{method:'POST',body:fd});
    const j=await res.json();

    if(!j.ok){
        result.innerHTML=j.msg;
        startCountdown();
        return;
    }

    let html="Pengembalian Berhasil<br>";
    html+="Judul : "+j.title+"<br>";
    html+="Tanggal Kembali : "+j.return_date+"<br>";

    if(j.late_days>0){
        html+="Terlambat : "+j.late_days+" hari<br>";
        html+="Denda : Rp "+j.fine.toLocaleString();
    }else{
        html+="Status : Tepat waktu";
    }

    result.innerHTML=html;

    i.value='';
    i.focus();

    startCountdown();
});

/* ================= SECURITY ================= */

// disable klik kanan
document.addEventListener('contextmenu',e=>e.preventDefault());

// blokir ESC & F11
document.addEventListener('keydown',function(e){
    if(e.key==="F11"||e.key==="Escape"){
        e.preventDefault();
    }
});

/* ================= AUTO FULLSCREEN ON FIRST CLICK ================= */

async function enterFullscreen(){
    if(!document.fullscreenElement){
        try{
            await document.documentElement.requestFullscreen();
        }catch(err){
            console.log("Fullscreen gagal:",err);
        }
    }
}

document.addEventListener('click',function(){
    enterFullscreen();
},{ once:true });

/* ================= LOCK FULLSCREEN ================= */

const KIOSK_PASSWORD="M@juB3r$4m@";

document.addEventListener("fullscreenchange",function(){

    if(!document.fullscreenElement){

        let pass=prompt("Masukkan password untuk keluar dari mode kiosk:");

        if(pass===KIOSK_PASSWORD){
            alert("Mode kiosk dinonaktifkan");
        }else{
            enterFullscreen();
        }

    }

});


// ================= BLOKIR KLIK DI LUAR AREA INPUT =================

document.addEventListener('mousedown', function(e){

    const isMemberField = e.target === m;
    const isItemField   = e.target === i;

    if(!isMemberField && !isItemField){

        e.preventDefault();

        // jika member belum scan → paksa fokus ke scan kartu
        if(!m.disabled){
            m.focus();
        }
        // jika sudah scan kartu → paksa fokus ke scan buku
        else{
            i.focus();
        }
    }

});
</script>

</body>
</html>
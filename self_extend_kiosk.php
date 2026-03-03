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
        ('system', ?, 'kiosk', 'self_extend', ?, ?, NOW())
    ");
    $stmt->execute([$member,$action,$msg]);
}

/* ================= AJAX ================= */

if($_SERVER['REQUEST_METHOD']=='POST'){

    header('Content-Type: application/json');
    $mode=$_POST['mode'] ?? '';

    /* ===== CEK MEMBER ===== */

    if($mode=='member'){

        $member=$_POST['member'] ?? '';

        $stmt=$db->prepare("
            SELECT m.member_id,m.member_name,m.member_type_id,
                   t.loan_periode
            FROM member m
            JOIN mst_member_type t ON m.member_type_id=t.member_type_id
            WHERE m.member_id=?
        ");
        $stmt->execute([$member]);
        $m=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$m){
            echo json_encode(['ok'=>false,'msg'=>'Member tidak ditemukan']);
            exit;
        }

        $stmt=$db->prepare("
            SELECT COUNT(*) FROM loan
            WHERE member_id=? AND is_return=0
        ");
        $stmt->execute([$member]);
        $count=$stmt->fetchColumn();

        echo json_encode([
            'ok'=>true,
            'member_id'=>$m['member_id'],
            'member_name'=>$m['member_name'],
            'loan_count'=>$count,
            'loan_periode'=>$m['loan_periode']
        ]);
        exit;
    }

    /* ===== PROSES EXTEND ===== */

    if($mode=='extend'){

        $member=$_POST['member'] ?? '';
        $item=$_POST['item'] ?? '';
        $today=date('Y-m-d');

        $stmt=$db->prepare("
            SELECT l.loan_id,l.due_date,l.renewed,b.title,
                   t.loan_periode
            FROM loan l
            JOIN item i ON i.item_code=l.item_code
            JOIN biblio b ON b.biblio_id=i.biblio_id
            JOIN member m ON m.member_id=l.member_id
            JOIN mst_member_type t ON t.member_type_id=m.member_type_id
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

        /* ==== VALIDASI ==== */

        if($today > $loan['due_date']){
            sys_log($db,$member,'extend_denied',
                "Overdue - item $item");
            echo json_encode([
                'ok'=>false,
                'msg'=>'Tidak Bisa Diperpanjang<br>Silakan hubungi petugas'
            ]);
            exit;
        }

        if($loan['renewed'] >= 1){
            sys_log($db,$member,'extend_denied',
                "Already extended - item $item");
            echo json_encode([
                'ok'=>false,
                'msg'=>'Tidak Bisa Diperpanjang<br>Silakan hubungi petugas'
            ]);
            exit;
        }

        /* ==== HITUNG DUE DATE BARU ==== */

        $old_due=$loan['due_date'];
        $new_due=date('Y-m-d',
            strtotime("+".$loan['loan_periode']." days",
            strtotime($old_due))
        );

        /* ==== UPDATE ==== */

        $stmt=$db->prepare("
            UPDATE loan
            SET due_date=?,
                renewed=renewed+1,
                last_update=NOW()
            WHERE loan_id=?
        ");
        $stmt->execute([$new_due,$loan['loan_id']]);

        sys_log($db,$member,'extend',
            "Extend item $item from $old_due to $new_due");

        echo json_encode([
            'ok'=>true,
            'title'=>$loan['title'],
            'old_due'=>$old_due,
            'new_due'=>$new_due
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
<title>Perpanjangan Mandiri</title>

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
</style>
</head>

<body>

<img src="<?= $logo ?>" class="logo">
<h1><?= $sysconf['library_name'] ?></h1>
<h2>Layanan Perpanjangan Mandiri</h2>

<input id="member" placeholder="SCAN KARTU ANGGOTA">
<br>
<input id="item" placeholder="SCAN QRCODE/BARCODE BUKU" disabled>

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

/* ================= FULLSCREEN AUTO ================= */

async function enterFullscreen(){
    if(!document.fullscreenElement){
        try{
            await document.documentElement.requestFullscreen();
        }catch(err){
            console.log("Fullscreen gagal:",err);
        }
    }
}

window.addEventListener('load',()=>{
    enterFullscreen();
    m.focus();
});

/* ================= RESET ================= */

function resetAll(){
    clearTimeout(timer);
    clearInterval(interval);

    m.disabled=false;
    i.disabled=true;
    m.value='';
    i.value='';
    memberInfo.innerHTML='';
    result.innerHTML='';
    countdown.innerHTML='';
    m.focus();
}

/* ================= COUNTDOWN 5 DETIK ================= */

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

    // 🔥 langsung mulai countdown setelah scan kartu
    startCountdown();
});

/* ================= SCAN ITEM ================= */

i.addEventListener('keydown',async function(e){

    if(e.key!='Enter') return;
    if(i.value.trim()=='') return;

    clearTimeout(timer);
    clearInterval(interval);

    const fd=new FormData();
    fd.append('mode','extend');
    fd.append('member',m.value);
    fd.append('item',i.value);

    const res=await fetch('?key=<?= $TOKEN ?>',{method:'POST',body:fd});
    const j=await res.json();

    if(!j.ok){
        result.innerHTML=j.msg;
        i.value='';
        i.focus();
        startCountdown();
        return;
    }

    let html="Perpanjangan Berhasil<br>";
    html+="Judul : "+j.title+"<br>";
    html+="Jatuh Tempo Lama : "+j.old_due+"<br>";
    html+="Jatuh Tempo Baru : "+j.new_due;

    result.innerHTML=html;

    i.value='';
    i.focus();

    // 🔥 countdown juga setelah sukses
    startCountdown();
});

/* ================= BLOKIR KLIK KANAN ================= */

document.addEventListener('contextmenu',e=>e.preventDefault());

/* ================= BLOKIR ESC & F11 ================= */

document.addEventListener('keydown',function(e){
    if(e.key==="F11"||e.key==="Escape"){
        e.preventDefault();
    }
});

/* ================= BLOKIR KLIK DI LUAR FIELD ================= */

document.addEventListener('mousedown', function(e){

    const isMemberField = e.target === m;
    const isItemField   = e.target === i;

    if(!isMemberField && !isItemField){

        e.preventDefault();

        if(!m.disabled){
            m.focus();
        }else{
            i.focus();
        }
    }

});

</script>
</body>
</html>
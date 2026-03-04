<?php

use SLiMS\DB;

$db = DB::getInstance();

date_default_timezone_set('Asia/Jakarta');
$db->exec("SET time_zone = '+07:00'");

if (($_GET['key'] ?? '') !== $TOKEN) {
    http_response_code(403);
    die('ACCESS DENIED');
}

function sys_log($db,$member,$action,$msg){
    $stmt=$db->prepare("
        INSERT INTO system_log
        (log_type,id,log_location,sub_module,action,log_msg,log_date)
        VALUES
        ('system', ?, 'kiosk', 'self_extend', ?, ?, NOW())
    ");
    $stmt->execute([$member,$action,$msg]);
}

if($_SERVER['REQUEST_METHOD']=='POST'){

    header('Content-Type: application/json');

    $mode=$_POST['mode'] ?? '';

    if($mode=='member'){

        $member=trim($_POST['member'] ?? '');

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

    if($mode=='extend'){

        $member=trim($_POST['member'] ?? '');
        $item=trim($_POST['item'] ?? '');
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

        if($today > $loan['due_date']){
            sys_log($db,$member,'extend_denied',"Overdue - item $item");
            echo json_encode([
                'ok'=>false,
                'msg'=>'Tidak Bisa Diperpanjang<br>Silakan hubungi petugas'
            ]);
            exit;
        }

        if($loan['renewed'] >= 1){
            sys_log($db,$member,'extend_denied',"Already extended - item $item");
            echo json_encode([
                'ok'=>false,
                'msg'=>'Tidak Bisa Diperpanjang<br>Silakan hubungi petugas'
            ]);
            exit;
        }

        $old_due=$loan['due_date'];

        $new_due=date(
            'Y-m-d',
            strtotime("+".$loan['loan_periode']." days",strtotime($old_due))
        );

        $stmt=$db->prepare("
            UPDATE loan
            SET due_date=?,
                renewed=renewed+1,
                last_update=NOW()
            WHERE loan_id=?
        ");
        $stmt->execute([$new_due,$loan['loan_id']]);

        sys_log($db,$member,'extend',"Extend item $item from $old_due to $new_due");

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

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

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

.logo{
  width:130px;
  margin-top:25px;
}

input{
  width:700px;
  max-width:90%;
  padding:20px;
  font-size:24px;
  border-radius:12px;
  border:none;
  margin:14px;
  text-align:center;
}

.info{
  margin-top:15px;
  font-size:20px;
  color:#ffd54f;
}

.result{
  margin-top:20px;
  font-size:20px;
  color:#ffd54f;
}

.countdown{
  margin-top:10px;
  color:#ffff99;
}

.ok{
  color:#00ff99;
  font-weight:bold;
}

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
  from{transform:translateX(0);}
  to{transform:translateX(-100%);}
}

</style>
</head>

<body>

<img src="<?= $logo ?>" class="logo">

<h1><?= $sysconf['library_name'] ?></h1>
<h2>Layanan Perpanjangan Mandiri</h2>

<div class="running-container">
<div class="running-text">
📌 Petunjuk Penggunaan :
&nbsp;&nbsp;&nbsp;
1. Siapkan Kartu Anggota
&nbsp;&nbsp;•&nbsp;&nbsp;
2. Scan Barcode/QRCode Kartu Anggota
&nbsp;&nbsp;•&nbsp;&nbsp;
3. Scan Barcode/QRCode Buku yang Akan Diperpanjang
&nbsp;&nbsp;•&nbsp;&nbsp;
4. Perhatikan Status Perpanjangan
&nbsp;&nbsp;•&nbsp;&nbsp;
5. Jika buku sudah pernah diperpanjang atau melewati jatuh tempo
&nbsp;&nbsp;•&nbsp;&nbsp;
Silakan hubungi petugas
&nbsp;&nbsp;&nbsp;📚
</div>
</div>

<input id="member" placeholder="MASUKKAN MEMBER ID / SCAN KARTU ANGGOTA">
<br>
<input id="item" placeholder="MASUKKAN ITEM ID / SCAN BARCODE BUKU" disabled>

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
let scanMemberTimer=null;
let scanItemTimer=null;

async function enterFullscreen(){
    if(!document.fullscreenElement){
        try{
            await document.documentElement.requestFullscreen();
        }catch(e){}
    }
}

window.addEventListener('load',()=>{
    enterFullscreen();
    m.focus();
});

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

function startCountdown(){

    clearTimeout(timer);
    clearInterval(interval);

    let sec=10;

    countdown.innerHTML="Reset dalam "+sec+" detik";

    interval=setInterval(()=>{

        sec--;
        countdown.innerHTML="Reset dalam "+sec+" detik";

        if(sec<=0){
            clearInterval(interval);
        }

    },1000);

    timer=setTimeout(resetAll,10000);
}

async function processMember(){

    if(m.value.trim()==='') return;

    const fd=new FormData();
    fd.append('mode','member');
    fd.append('member',m.value);

    const res=await fetch(window.location.href,{method:'POST',body:fd});
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
}

async function processItem(){

    if(i.value.trim()==='') return;

    clearTimeout(timer);
    clearInterval(interval);

    const fd=new FormData();
    fd.append('mode','extend');
    fd.append('member',m.value);
    fd.append('item',i.value);

    const res=await fetch(window.location.href,{method:'POST',body:fd});
    const j=await res.json();

    if(!j.ok){
        result.innerHTML=j.msg;
        i.value='';
        i.focus();
        startCountdown();
        return;
    }

    let html="<span class='ok'>Perpanjangan Berhasil</span><br>";
    html+="Judul : "+j.title+"<br>";
    html+="Jatuh Tempo Lama : "+j.old_due+"<br>";
    html+="Jatuh Tempo Baru : "+j.new_due;

    result.innerHTML=html;

    i.value='';
    i.focus();

    startCountdown();
}

m.addEventListener('input',function(){

    clearTimeout(scanMemberTimer);

    scanMemberTimer=setTimeout(()=>{
        processMember();
    },150);

});

m.addEventListener('keydown',function(e){
    if(e.key==='Enter'){
        e.preventDefault();
        processMember();
    }
});

i.addEventListener('input',function(){

    clearTimeout(scanItemTimer);

    scanItemTimer=setTimeout(()=>{
        processItem();
    },150);

});

i.addEventListener('keydown',function(e){
    if(e.key==='Enter'){
        e.preventDefault();
        processItem();
    }
});

document.addEventListener('contextmenu',e=>e.preventDefault());

document.addEventListener('keydown',function(e){
    if(e.key==="F11"||e.key==="Escape"){
        e.preventDefault();
    }
});

document.addEventListener('mousedown',function(e){

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
const KIOSK_PASSWORD="M@jub3rs@m@";

async function enterFullscreen(){

    const el=document.documentElement;

    if(!document.fullscreenElement){

        try{
            await el.requestFullscreen();
        }catch(e){}

    }

}

document.addEventListener('click',function(){

    if(!document.fullscreenElement){
        enterFullscreen();
    }

});

document.addEventListener("fullscreenchange",()=>{

    if(!document.fullscreenElement){

        let pass=prompt("Masukkan password untuk keluar dari mode kiosk:");

        if(pass===KIOSK_PASSWORD){

            alert("Mode kiosk dinonaktifkan");

        }else{

            alert("Password salah. Kembali ke mode kiosk.");

            enterFullscreen();

        }

    }

});

document.addEventListener("contextmenu",e=>e.preventDefault());

document.addEventListener("keydown",function(e){

    if(e.key==="F11"||e.key==="Escape"){

        e.preventDefault();

        alert("Mode kiosk aktif");

        enterFullscreen();

    }

});
</script>

</body>
</html>
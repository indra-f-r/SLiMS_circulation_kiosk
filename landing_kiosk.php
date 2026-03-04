<?php

use SLiMS\DB;

$db = DB::getInstance();

date_default_timezone_set('Asia/Jakarta');
$db->exec("SET time_zone = '+07:00'");

if (($_GET['key'] ?? '') !== $TOKEN) {
    http_response_code(403);
    die('ACCESS DENIED');
}

if($_SERVER['REQUEST_METHOD']=='POST'){

    header('Content-Type: application/json');

    $member=trim($_POST['member'] ?? '');

    $stmt=$db->prepare("
        SELECT member_id,member_name
        FROM member
        WHERE member_id=?
    ");
    $stmt->execute([$member]);
    $m=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$m){
        echo json_encode([
            'ok'=>false,
            'msg'=>'Member tidak ditemukan'
        ]);
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

$baseURL=rtrim($sysconf['baseurl'],'/').'/';
$logo=$baseURL.'images/default/logo.png?v='.time();

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

?>

<!DOCTYPE html>
<html>
<head>

<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Layanan Mandiri</title>

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
  margin-top:10px;
  font-size:20px;
  color:#ffd54f;
}

.buttons{
  margin-top:25px;
}

.btn{
  width:500px;
  max-width:90%;
  padding:22px;
  margin:12px auto;
  font-size:26px;
  border-radius:12px;
  border:none;
  cursor:pointer;
  font-weight:bold;
}

.btn:disabled{
  background:#cccccc;
  color:#666;
  cursor:not-allowed;
}

.btn.active{
  background:white;
  color:#1565d8;
}

.btn.active:hover{
  background:#ffd54f;
}

.countdown{
  margin-top:15px;
  color:#ffff99;
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
<h2>Layanan Mandiri Perpustakaan</h2>

<div class="running-container">
<div class="running-text">
📌 Scan Kartu Anggota atau masukkan Member ID untuk memulai layanan
</div>
</div>

<input id="member" placeholder="SCAN KARTU / MASUKKAN MEMBER ID">

<div id="memberInfo" class="info"></div>

<div class="buttons">

<button class="btn" id="btnReturn" disabled>
📚 PENGEMBALIAN MANDIRI
</button>

<button class="btn" id="btnExtend" disabled>
🔄 PERPANJANGAN MANDIRI
</button>

</div>

<div id="countdown" class="countdown"></div>

<script>

const m=document.getElementById('member');
const info=document.getElementById('memberInfo');
const btnReturn=document.getElementById('btnReturn');
const btnExtend=document.getElementById('btnExtend');
const countdown=document.getElementById('countdown');

let timer=null;
let interval=null;
let typingTimer=null;
let scanTimer=null;
let memberID=null;

function resetAll(){

    clearTimeout(timer);
    clearInterval(interval);

    m.value='';
    memberID=null;

    info.innerHTML='';
    countdown.innerHTML='';

    btnReturn.disabled=true;
    btnExtend.disabled=true;

    btnReturn.classList.remove("active");
    btnExtend.classList.remove("active");

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
    fd.append('member',m.value);

    const res=await fetch(window.location.href,{method:'POST',body:fd});
    const j=await res.json();

    if(!j.ok){

        info.innerHTML=j.msg;
        startCountdown();
        return;
    }

    memberID=j.member_id;

    info.innerHTML=
        "Nama : "+j.member_name+"<br>"+
        "ID : "+j.member_id+"<br>"+
        "Pinjaman aktif : "+j.loan_count+" buku";

    btnReturn.disabled=false;
    btnExtend.disabled=false;

    btnReturn.classList.add("active");
    btnExtend.classList.add("active");

    startCountdown();
}

m.addEventListener('input',function(){

    clearTimeout(scanTimer);
    clearTimeout(typingTimer);

    scanTimer=setTimeout(()=>{
        processMember();
    },150);

    typingTimer=setTimeout(()=>{
        resetAll();
    },5000);

});

m.addEventListener('keydown',function(e){

    if(e.key==='Enter'){
        e.preventDefault();
        processMember();
    }

});

btnReturn.onclick=function(){

    if(!memberID) return;

    window.location.href="?p=kiosk_return&key=<?= $_GET['key'] ?>&member="+encodeURIComponent(memberID);

};

btnExtend.onclick=function(){

    if(!memberID) return;

    window.location.href="?p=kiosk_extend&key=<?= $_GET['key'] ?>&member="+encodeURIComponent(memberID);

};

const KIOSK_PASSWORD="M@jub3rs@m@";

async function enterFullscreen(){

    const el=document.documentElement;

    if(!document.fullscreenElement){

        try{
            await el.requestFullscreen();
        }catch(e){}

    }

}

window.addEventListener('load',()=>{
    enterFullscreen();
    m.focus();
});

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

document.addEventListener('mousedown',function(e){

    const isInput = e.target === m;
    const isButton = e.target.classList.contains('btn');

    if(!isInput && !isButton){

        e.preventDefault();
        m.focus();

    }

});

</script>

</body>
</html>
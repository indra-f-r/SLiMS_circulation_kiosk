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


// ================= SYSTEM LOG =================

function sys_log($db,$member,$action,$msg){

$stmt=$db->prepare("
INSERT INTO system_log
(log_type,id,log_location,sub_module,action,log_msg,log_date)
VALUES
('system', ?, 'opac', 'self_circulation', ?, ?, NOW())
");

$stmt->execute([$member,$action,$msg]);

}



// ================= AJAX =================

if($_SERVER['REQUEST_METHOD']=='POST'){

header('Content-Type: application/json');

$mode=$_POST['mode'] ?? '';
$member=$_POST['member'] ?? '';
$item=$_POST['item'] ?? '';

$today=date('Y-m-d');


// ================= MEMBER =================

if($mode=='member'){

$stmt=$db->prepare("
SELECT m.member_id,m.member_name,
mt.loan_limit,mt.loan_periode
FROM member m
LEFT JOIN mst_member_type mt
ON mt.member_type_id=m.member_type_id
WHERE m.member_id=?
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
'member_id'=>$member,
'member_name'=>$m['member_name'],
'limit'=>$m['loan_limit'],
'periode'=>$m['loan_periode'],
'count'=>$count

]);

exit;

}



// ================= ITEM =================

if($mode=='item'){


$stmt=$db->prepare("
SELECT b.title,
m.member_name,
mt.loan_limit,
mt.loan_periode
FROM item i
JOIN biblio b ON b.biblio_id=i.biblio_id
JOIN member m ON m.member_id=?
JOIN mst_member_type mt ON mt.member_type_id=m.member_type_id
WHERE i.item_code=?
");

$stmt->execute([$member,$item]);
$data=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$data){

echo json_encode(['ok'=>false,'msg'=>'Item tidak ditemukan']);
exit;

}

$title=$data['title'];
$name=$data['member_name'];
$limit=$data['loan_limit'];
$periode=$data['loan_periode'];



// cek loan aktif

$stmt=$db->prepare("
SELECT *
FROM loan
WHERE item_code=? AND is_return=0
");

$stmt->execute([$item]);
$loan=$stmt->fetch(PDO::FETCH_ASSOC);



// ================= RETURN =================

if($loan && $loan['member_id']==$member){

$return=$today;

$stmt=$db->prepare("
UPDATE loan
SET is_return=1,
is_lent=0,
return_date=?,
last_update=NOW()
WHERE loan_id=?
");

$stmt->execute([$return,$loan['loan_id']]);


$late=0;

if($return>$loan['due_date']){

$late=floor((strtotime($return)-strtotime($loan['due_date']))/86400);

}

$status=$late>0
?
"TERLAMBAT ($late hari)"
:
"Tepat waktu";


// LOG
sys_log($db,$member,'return',"Member $member return item $item ($title)");



$stmt=$db->prepare("
SELECT COUNT(*)
FROM loan
WHERE member_id=? AND is_return=0
");

$stmt->execute([$member]);
$count=$stmt->fetchColumn();

echo json_encode([

'ok'=>true,
'action'=>'RETURN',

'title'=>$title,
'member_name'=>$name,
'member_id'=>$member,

'loan_date'=>$loan['loan_date'],
'due_date'=>$loan['due_date'],
'return_date'=>$return,

'status'=>$status,

'count'=>$count,
'limit'=>$limit

]);

exit;

}



// ================= BORROW =================

if($loan){

echo json_encode(['ok'=>false,'msg'=>'Buku sedang dipinjam member lain']);
exit;

}



$stmt=$db->prepare("
SELECT COUNT(*)
FROM loan
WHERE member_id=? AND is_return=0
");

$stmt->execute([$member]);
$count=$stmt->fetchColumn();


if($count >= $limit){

echo json_encode(['ok'=>false,'msg'=>"Limit $limit buku tercapai"]);
exit;

}


$due=date('Y-m-d',strtotime("+$periode days"));

$stmt=$db->prepare("
INSERT INTO loan
(item_code,member_id,loan_date,due_date,is_lent,is_return,input_date,last_update)
VALUES
(?,?,?,?,1,0,NOW(),NOW())
");

$stmt->execute([$item,$member,$today,$due]);


// LOG
sys_log($db,$member,'borrow',"Member $member borrow item $item ($title)");

$count++;

echo json_encode([

'ok'=>true,
'action'=>'BORROW',

'title'=>$title,
'member_name'=>$name,
'member_id'=>$member,

'loan_date'=>$today,
'due_date'=>$due,

'count'=>$count,
'limit'=>$limit

]);

exit;

}

}



// ================= UI =================

$baseURL=rtrim($sysconf['baseurl'],'/').'/';
$logo=$baseURL.'images/default/logo.png?v='.time();

?>

<!DOCTYPE html>
<html>

<head>

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Kiosk Perpustakaan</title>

<style>

body{
  background:#1565d8;
  color:white;
  font-family:Arial;
  text-align:center;
  margin:0;
}

/* logo tetap tengah */
.logo{
  width:130px;
  margin-top:25px;
}

/* input tetap tengah */
input{
  width:450px;
  padding:20px;
  font-size:26px;
  border-radius:12px;
  border:none;
  margin:12px;
}

/* tombol tetap tengah */
button{
  padding:16px 24px;
  font-size:20px;
  border-radius:12px;
  border:none;
  background:#ff4444;
  color:white;
  cursor:pointer;
}

/* info member atas tetap tengah */
.info{
  font-size:26px;
  font-weight:bold;
  margin-top:15px;
  text-align:center;
}

/* ========================= */
/* RESULT CONTAINER */
/* ========================= */


/* container hasil */
.result{
  margin-top:20px;
  font-size:30px;
  display:inline-block;
  text-align:left;
  min-width:600px;
}

/* setiap baris */
.row{
  margin:2px 0;
}

/* label */
.label{
  display:inline-block;
  width:240px;
  font-size:20px;
  font-weight:bold;
  color:white;
}

/* titik dua */
.colon{
  display:inline-block;
  width:20px;
  font-size:20px;
  font-weight:bold;
  text-align:center;
}

/* value */
.value{
  display:inline-block;
  font-size:20px;
  font-weight:bold;
  color:#ffd54f;
}

/* jumlah buku */
.count{

  margin-top:20px;
  text-align:center;
  font-size:20px;
  font-weight:bold;

}

/* countdown */
.countdown{

  text-align:center;

  font-size:22px;
  margin-top:15px;

  color:#ffff99;

}

/* error */
.err{

  text-align:center;

  font-size:24px;
  color:#ff8080;

}

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

  animation:runningText 30s linear infinite;

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

<h2>Layanan Sirkulasi Mandiri</h2>

<div class="running-container">
  <div class="running-text">
    📌 Petunjuk Penggunaan :
    &nbsp;&nbsp;&nbsp;
    1. Siapkan Kartu Anggota
    &nbsp;&nbsp;•&nbsp;&nbsp;
    2. Siapkan buku / APE yang akan dipinjam
    &nbsp;&nbsp;•&nbsp;&nbsp;
    3. Scan Barcode / QR Code pada Kartu Anggota
    &nbsp;&nbsp;•&nbsp;&nbsp;
    4. Scan Barcode / QR Code pada Buku / APE
    &nbsp;&nbsp;•&nbsp;&nbsp;
    5. Perhatikan tanggal pengembalian
    &nbsp;&nbsp;&nbsp;📚
  </div>
</div>


<div>

<input id="member" placeholder="SCAN KARTU">
<button onclick="resetMember()">RESET</button>

</div>



<div>

<input id="item" placeholder="SCAN BUKU" disabled>
<button onclick="resetItem()">RESET</button>

</div>


<div id="info" class="info"></div>

<div id="result" class="result"></div>

<div id="countdown" class="countdown"></div>



<script>

let member='';
let limit=0;

let timer=null;
let countdownTimer=null;

const m=document.getElementById('member');
const i=document.getElementById('item');

const result=document.getElementById('result');
const info=document.getElementById('info');
const countdown=document.getElementById('countdown');


// ================= LOGOUT =================

function logout(){

member='';

m.disabled=false;
i.disabled=true;

m.value='';
i.value='';

result.innerHTML='';
info.innerHTML='';
countdown.innerHTML='';

m.focus();

}



// ================= TIMER =================

function resetTimer(){

clearTimeout(timer);
clearInterval(countdownTimer);

let seconds=5;

countdown.innerHTML="Reset otomatis dalam "+seconds+" detik";

countdownTimer=setInterval(()=>{

seconds--;

countdown.innerHTML="Reset otomatis dalam "+seconds+" detik";

if(seconds<=0){

clearInterval(countdownTimer);

}

},1000);

timer=setTimeout(logout,5000);

}



// ================= RESET BUTTON =================

function resetMember(){

logout();

}


function resetItem(){

i.value='';
i.focus();

}



// ================= FOCUS =================

m.focus();



// ================= SCAN MEMBER =================

m.addEventListener('keydown',async e=>{

if(e.key!='Enter')return;

const fd=new FormData();

fd.append('mode','member');
fd.append('member',m.value);

const res=await fetch('?key=<?= $TOKEN ?>',{method:'POST',body:fd});
const j=await res.json();

if(!j.ok){

result.innerHTML='<div class="err">'+j.msg+'</div>';
return;

}

member=j.member_id;
limit=j.limit;

m.disabled=true;
i.disabled=false;

i.focus();

info.innerHTML =
    j.member_name +
    '<br>' + j.member_id +
    '<br>Peminjaman : ' + j.count + ' / ' + limit;

resetTimer();

});



// ================= SCAN ITEM =================

i.addEventListener('keydown',async e=>{

if(e.key!='Enter')return;

const fd=new FormData();

fd.append('mode','item');
fd.append('member',member);
fd.append('item',i.value);

const res=await fetch('?key=<?= $TOKEN ?>',{method:'POST',body:fd});
const j=await res.json();

if(!j.ok){

result.innerHTML='<div class="err">'+j.msg+'</div>';

}else{

let html='';

// terjemahan action ke Bahasa Indonesia
let actionText='';

if(j.action==='BORROW'){
    actionText='PEMINJAMAN';
}
else if(j.action==='RETURN'){
    actionText='PENGEMBALIAN';
}
else{
    actionText=j.action;
}

html+='<div class="ok">'+actionText+' BERHASIL</div>';

html+='<div class="row"><span class="label">Judul</span><span class="colon">:</span><span class="value">'+j.title+'</span></div>';

html+='<div class="row"><span class="label">Nama Member</span><span class="colon">:</span><span class="value">'+j.member_name+'</span></div>';

html+='<div class="row"><span class="label">Member ID</span><span class="colon">:</span><span class="value">'+j.member_id+'</span></div>';

html+='<div class="row"><span class="label">Tanggal Pinjam</span><span class="colon">:</span><span class="value">'+j.loan_date+'</span></div>';

html+='<div class="row"><span class="label">Batas Pengembalian</span><span class="colon">:</span><span class="value">'+j.due_date+'</span></div>';

if(j.return_date){

html+='<div class="row"><span class="label">Tanggal Kembali</span><span class="colon">:</span><span class="value">'+j.return_date+'</span></div>';

html+='<div class="row"><span class="label">Status</span><span class="colon">:</span><span class="value">'+j.status+'</span></div>';

}



html+='<div class="count">'+j.count+'/'+j.limit+' buku dipinjam</div>';

result.innerHTML=html;

info.innerHTML =
    j.member_name +
    '<br>' + j.member_id +
    '<br>Peminjaman : ' + j.count + '/' + j.limit;


}

i.value='';

resetTimer();

});



// ================= FULLSCREEN LOCK =================

document.addEventListener('click',()=>{

document.documentElement.requestFullscreen();

});

// ================= FORCE FOCUS ENGINE =================

// fungsi paksa fokus ke scan kartu
function forceFocusMember(){

    if(!member){   // hanya jika belum login member
        m.focus();
    }

}

// jalankan tiap 500ms
setInterval(forceFocusMember,500);


// jika user klik area manapun
document.addEventListener('click',forceFocusMember);


// jika fullscreen berubah
document.addEventListener('fullscreenchange',forceFocusMember);


// jika window aktif kembali
window.addEventListener('focus',forceFocusMember);


// jika keyboard ditekan tanpa fokus
document.addEventListener('keydown',function(e){

    if(!member){
        m.focus();
    }

});

// ================= AUTO FULLSCREEN =================

async function enterFullscreen(){

    const el = document.documentElement;

    if(!document.fullscreenElement){

        try{
            await el.requestFullscreen();
        }catch(e){
            console.log("Fullscreen blocked by browser");
        }

    }

}

// jalankan saat load
window.addEventListener('load',()=>{

    setTimeout(enterFullscreen,500);

});

// ================= FULLSCREEN LOCK =================

const KIOSK_PASSWORD = "M@jub3rs@m@"; // ganti dengan password Anda


document.addEventListener("fullscreenchange",()=>{

    if(!document.fullscreenElement){

        let pass = prompt("Masukkan password untuk keluar dari mode kiosk:");

        if(pass === KIOSK_PASSWORD){

            alert("Mode kiosk dinonaktifkan");

        }else{

            alert("Password salah. Kembali ke mode kiosk.");

            enterFullscreen();

        }

    }

});

document.addEventListener("contextmenu",e=>e.preventDefault());

// blokir ESC dan F11
document.addEventListener("keydown",function(e){

    if(e.key === "F11" || e.key === "Escape"){

        e.preventDefault();

        alert("Mode kiosk aktif");

        enterFullscreen();

    }

});
</script>

</body>
</html>

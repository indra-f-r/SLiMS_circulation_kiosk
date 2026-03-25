<?php
/**
 * Plugin Name: Layanan Pengembalian dan Perpanjangan Mandiri
 * Plugin URI: https://github.com/indra-f-r/SLiMS_circulation_kiosk
 * Description: Plugin Layanan Pengembalian dan Perpanjangan Mandiri
 * Version: 1.8.0
 * Author: Indra Febriana Rulliawan (indra.f.rulliawan@gmail.com)
 * Author URI: digilib.wacanateknologi.id
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

use SLiMS\Plugins;

global $dbs;

/* 
|--------------------------------------------------------------------------
| CEK DAN BUAT TABEL JIKA BELUM ADA 
|--------------------------------------------------------------------------
*/
$check = $dbs->query("SHOW TABLES LIKE 'book_review'");
if ($check->num_rows == 0) {
    $create = "CREATE TABLE `book_review` (
      `review_id` int(11) NOT NULL AUTO_INCREMENT,
      `member_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
      `biblio_id` int(11) NOT NULL,
      `rating` int(11) NOT NULL,
      `review_text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `review_date` datetime NOT NULL,
      PRIMARY KEY (`review_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $dbs->query($create);
}

/*
|--------------------------------------------------------------------------
| KIOSK ROUTE INTERCEPTOR
|--------------------------------------------------------------------------
| Plugin tetap bisa ON/OFF
| Tidak menggunakan template OPAC
| Tidak menggunakan registerMenu
| Fullscreen kiosk murni
*/

Plugins::hook(Plugins::CONTENT_BEFORE_LOAD, function () {

    $page = $_GET['p'] ?? '';
    $key  = $_GET['key'] ?? '';

    // Token otentikasi
    $TOKEN = '[YOUR_TOKEN]';

    /*
    |--------------------------------------------------------------------------
    | SELF EXTEND (Perpanjangan)
    |--------------------------------------------------------------------------
    */
    if ($page === 'kiosk_extend') {

        if ($key !== $TOKEN) {
            http_response_code(403);
            die('ACCESS DENIED');
        }

        require __DIR__ . '/self_extend_kiosk.php';
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SELF RETURN (Pengembalian)
    |--------------------------------------------------------------------------
    */
    if ($page === 'kiosk_return') {

        if ($key !== $TOKEN) {
            http_response_code(403);
            die('ACCESS DENIED');
        }

        require __DIR__ . '/self_return_kiosk.php';
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FULL KIOSK (Sirkulasi Lengkap)
    |--------------------------------------------------------------------------
    */
    if ($page === 'kiosk') {

        if ($key !== $TOKEN) {
            http_response_code(403);
            die('ACCESS DENIED');
        }

        require __DIR__ . '/circulation_kiosk.php';
        exit;
    }

});

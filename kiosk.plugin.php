<?php
/**
 * Plugin Name: Layanan Pengembalian dan Perpanjangan Mandiri
 * Plugin URI: https://github.com/indra-f-r/SLiMS_circulation_kiosk
 * Description: Plugin Layanan Pengembalian dan Perpanjangan Mandiri
 * Version: 1.5.0
 * Author: Indra Febriana Rulliawan (indra.f.rulliawan@gmail.com)
 * Author URI: digilib.wacanateknologi.id
 */


defined('INDEX_AUTH') OR die('Direct access not allowed!');

use SLiMS\Plugins;

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

    $TOKEN = 'z0Z6olm5RMred0XoksEliwk4CSTL5TZwomNd4d4X4veOB3zFj3u1jMLlEjgXLLvKFHqQwmUDir4iVGfNsLvtetmG6sb9xMGup4FXgqguE4u17TAhjlRODnevsI8junWUSQH6N9DjSWkhkHsVqw2kMERa1yPfQoeyZYI2QCXPP3p7PzykH2iWDmcojSuc2eLxqD7T4xHyyoBKz8G3kA5T7UmzEANJNl9IsDEXfBR38OM32Nq093iTlX3KDFnVCs4stffRFNaAdEnxMXVLWJzQ8OnT4HzzMOcrQBCcz2c5CrXvHEiiIH7KrUQ1ZDxWH1NtHE3RciZq9uNWhQjqO41lPFmXTKkLdK0t2C1Tpr0YCcGwTYweeVoLEY3R81lLnN0B31EGKfI48MDGsgH8BJwVqnuyEsSPyKd55EHegC68YdF2Zi7xQdAHJjdtTvNFnAiLSau6HsmG2f9J3uweynLptbBWHpnZqE2D7i4M0B6h9yGTFuMmVa4xOESe8pNUH9tJ';

    /*
    |--------------------------------------------------------------------------
    | SELF EXTEND
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
    | SELF RETURN
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


});
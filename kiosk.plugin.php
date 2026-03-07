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

    $TOKEN = 'YOUR TOKEN';

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

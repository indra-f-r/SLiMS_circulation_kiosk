<?php
/**
 * Plugin Name: Circulation Kiosk
 * Description: Plugin Sirkulasi Mandiri SLiMS (Full Kiosk Mode)
 * Version: 1.2.0
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
    /*
    |--------------------------------------------------------------------------
    |TOKEN
    |--------------------------------------------------------------------------
    | Generate Token menggunakan online token generator
    | Di sesuaikan dengan Kebutuhan
    */
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

        /*
    |--------------------------------------------------------------------------
    | FULL KIOSK
    |--------------------------------------------------------------------------
    */
    if ($page === 'kiosk_circulation') {

        if ($key !== $TOKEN) {
            http_response_code(403);
            die('ACCESS DENIED');
        }

        require __DIR__ . '/circulation_kiosk.php';
        exit;
    }



});


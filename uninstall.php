<?php
if( ! defined('WP_UNINSTALL_PLUGIN') ) exit;


// очищаем базу данных
delete_option( 'youtube_twitch_parser_settings' );

global $wpdb;
$wpdb->query( "DELETE FROM  `wp_postmeta` WHERE  `meta_key` IN ( '_youtube_id_channel', '_twitch_id_channel' )" );


// удаляем папки где хранятся списки каналов
function removeDirectory($dir) {
    if ($objs = glob($dir."/*")) {
       foreach($objs as $obj) {
         is_dir($obj) ? removeDirectory($obj) : unlink($obj);
       }
    }
    rmdir($dir);
}
$upload = wp_upload_dir();
$upload_dir = $upload['basedir'];
$upload_dir = $upload_dir . '/youtube-twitch-parser-cache';

removeDirectory( $upload_dir );


// не даем по настоящему удалить плагин
// die();

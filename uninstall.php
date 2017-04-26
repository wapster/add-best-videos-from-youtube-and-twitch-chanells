<?php
if( ! defined('WP_UNINSTALL_PLUGIN') ) exit;


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
$upload_dir = $upload_dir . '/youtube-twitch-parser';

removeDirectory( $upload_dir );


// не даем по настоящему удалить плагин
// die();

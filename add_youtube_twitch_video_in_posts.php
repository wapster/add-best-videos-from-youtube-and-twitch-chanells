<?php
/*
Plugin Name: Add Youtube and Twitch video in your posts
Plugin URI: r0ma.ru
Description: Добавляет в посты видео с избранных каналов Youtube'а и Twitch'а.
Author: r0ma.ru
Author URI: http://r0ma.ru
Version: 0.1


my api key

AIzaSyBsZfBUcC14ni0ZZYmkKr6oZKFkkK554ag

*/

function debug_aytv($arr) {
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

// Действия при активации плагина
register_activation_hook( __FILE__, 'aytvp_activation' );
function aytvp_activation() {
}


// Создаем страницу плагина
add_action( 'admin_menu', 'aytv_create_menu' );
function aytv_create_menu() {
    add_options_page( 'Add YouTube and Twitch video in posts', 'Youtube / Twitch', 'manage_options', 'aytvp-main-menu', 'aytvp_settings');
}

// страница настроек плагина
function aytvp_settings() {
    echo "<div>основная страница</div>";
}



// Добавляем метабоксы
add_action('add_meta_boxes', 'aytv_add_custom_boxes');
function aytv_add_custom_boxes() {
    add_meta_box( 'aytv_meta_box_youtube', 'Youtube', 'meta_box_youtube_id_chanell', 'post', 'side', 'high', null );
    add_meta_box( 'aytv_meta_box_twitch', 'Twitch', 'meta_box_twitch_id_chanell', 'post', 'side', 'high', null );
}


/**
*
* YOUTUBE META BOX
*
**/
/* HTML код блока */
function meta_box_youtube_id_chanell() {
	wp_nonce_field( plugin_basename(__FILE__), 'aytv_youtube_noncename' );
    global $post;
	echo '<label for="youtube_id_chanell">' . "Укажите id YOUTUBE канала" . '</label>';
	echo '<input type="text" id= "youtube_id_chanell" name="youtube-id-chanell" value="' . get_post_meta( $post->ID, _youtube_id_chanell, true ) . '" size="25" />';
}

/* Сохраняем данные, когда пост сохраняется */
function save_youtube_id_chanell( $post_id ) {
	// проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
	if ( ! wp_verify_nonce( $_POST['aytv_youtube_noncename'], plugin_basename(__FILE__) ) )
		return $post_id;

	// проверяем, если это автосохранение ничего не делаем с данными нашей формы.
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return $post_id;

	// проверяем разрешено ли пользователю указывать эти данные
	if ( 'page' == $_POST['post_type'] && ! current_user_can( 'edit_page', $post_id ) ) {
		  return $post_id;
	} elseif( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	// Убедимся что поле установлено.
	if ( ! isset( $_POST['youtube-id-chanell'] ) )
		return;

	// Все ОК. Теперь, нужно найти и сохранить данные
	// Очищаем значение поля input.
	$my_data = sanitize_text_field( $_POST['youtube-id-chanell'] );

	// Обновляем данные в базе данных.
	update_post_meta( $post_id, '_youtube_id_chanell', $my_data );
}
add_action( 'save_post', 'save_youtube_id_chanell' );



/**
*
* TWITCH META BOX
*
**/
/* HTML код блока */
function meta_box_twitch_id_chanell() {
	wp_nonce_field( plugin_basename(__FILE__), 'aytv_twitch_noncename' );
    global $post;
	echo '<label for="twitch_id_chanell">' . "Укажите id TWITCH канала" . '</label>';
	echo '<input type="text" id="twitch_id_chanell" name="twitch-id-chanell" value="' . get_post_meta( $post->ID, _twitch_id_chanell, true ) . '" size="25" />';
}

/* Сохраняем данные, когда пост сохраняется */
function save_twitch_id_chanell( $post_id ) {
	// проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
	if ( ! wp_verify_nonce( $_POST['aytv_twitch_noncename'], plugin_basename(__FILE__) ) )
		return $post_id;

	// проверяем, если это автосохранение ничего не делаем с данными нашей формы.
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return $post_id;

	// проверяем разрешено ли пользователю указывать эти данные
	if ( 'page' == $_POST['post_type'] && ! current_user_can( 'edit_page', $post_id ) ) {
		  return $post_id;
	} elseif( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	// Убедимся что поле установлено.
	if ( ! isset( $_POST['twitch-id-chanell'] ) )
		return;

	// Все ОК. Теперь, нужно найти и сохранить данные
	// Очищаем значение поля input.
	$my_data = sanitize_text_field( $_POST['twitch-id-chanell'] );

	// Обновляем данные в базе данных.
	update_post_meta( $post_id, '_twitch_id_chanell', $my_data );
}
add_action( 'save_post', 'save_twitch_id_chanell' );

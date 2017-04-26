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

$options = get_option( 'youtube_twitch_parser_settings', '' );
define( 'YOUTUBE_CATEGORY', $options['youtube_category'] );
define( 'YOUTUBE_APIKEY', $options['youtube_apikey'] );
define( 'TWITCH_CATEGORY', $options['twitch_category'] );
define( 'TWITCH_APIKEY', $options['twitch_apikey'] );

// url до папки с плагином
define( 'YT_PLUGIN_DIR', plugin_dir_url(__FILE__) );


// url папки с каналами Youtube
$upload = wp_upload_dir();
$upload_dir = $upload['basedir'];
$upload_dir_youtube = $upload_dir . '/youtube-twitch-parser/youtube_channels';
define( 'YOUTUBE_CHANNELS_DIR', $upload_dir_youtube );

// url папки с каналами Twitch
$upload = wp_upload_dir();
$upload_dir = $upload['basedir'];
$upload_dir_twitch = $upload_dir . '/youtube-twitch-parser/twitch_channels';
define( 'TWITCH_CHANNELS_DIR', $upload_dir_twitch );



// Действия при активации плагина
register_activation_hook( __FILE__, 'aytvp_activation' );
function aytvp_activation() {


    if ( get_option( 'youtube_twitch_parser_settings', '' ) == '' ) {
        $options = [
            'youtube_category' => '',
            'youtube_apikey' => '',
            'twitch_category' => '',
            'twitch_apikey' => '',
        ];
        // wp_unslash( $options );
        update_option( 'youtube_twitch_parser_settings', $options, true );
    }

    //  создаем папки для хранения id каналов
    // СОЗДАЕМ СТРУКТУРУ ПАПОК ДЛЯ ХРАНЕНИЯ ДАННЫХ
	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];

    // Создаем общую папку `bik` в wp-content/uploads
    $upload_dir = $upload_dir . '/youtube-twitch-parser';

	if ( ! wp_mkdir_p( $upload_dir ) ) {
		echo "Не удалось создать директорию <pre>youtube-twitch-parser</pre>";
        exit();
	}

	// Создаем пустой файл index.php чтобы исключить просмотр директории
	file_put_contents( $upload_dir.'/index.php', '<?php // Silence is golden.' );

    // Создаем директорию для youtube каналов
    wp_mkdir_p( $upload_dir . '/youtube-channels' );

    // Создаем директорию для twitch каналов
    wp_mkdir_p( $upload_dir . '/twitch-channels' );
}





// функция получения id канала по id поста к которому относится канал
function get_id_youtube_channel_from_post_id() {
    global $post;
    $id_chanell = get_post_meta( $post->ID, _youtube_id_chanell, true );
    return $id_chanell;
}

// функция получения id канала по id поста к которому относится канал
function get_id_twitch_channel_from_post_id() {
    global $post;
    $id_chanell = get_post_meta( $post->ID, _twitch_id_chanell, true );
    return $id_chanell;
}


// Поиск в директории true/false
// folder может быть == youtube-channels или twitch-channels
function search_in_dir ( $folder, $file ) {
    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'] . '/youtube-twitch-parser' ;
    $dir = $upload_dir . '/' . $folder;
    $file_list = scandir( $dir );
    return ( in_array( $file, $file_list ) ) ? true : false;
}


function youtube_parsing( $channel_id, $cnt = 3) {
    // специальный адрес, отвечающий за выдачу фида
    $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet'
         . '&channelId=' . $channel_id
         . '&order=viewCount'    // упорядочивать по кол-ву просмотров
         . '&maxResults=' . $cnt  // за раз получать не более $cnt результатов
        //  . '&fields=items/id/videoId'  // нам нужны только идентификаторы видео
         . '&key=' . YOUTUBE_APIKEY;

    $buf = file_get_contents( $url );

    // декодируем JSON данные
    $json = json_decode($buf, true);

    $arr = array();

    # если данных нет —> на выход
    if ( empty( $json['items'] ) ) return $arr;

    foreach( $json['items'] as $v ) {
        // $t = array(
            // 'video_id' => $v['id']['videoId'], #id
            // 'title' => $v['snippet']['title'], # название
            // 'desc'  => $v['snippet']['description'], # описание
            // 'url'   => $v['snippet']['id']['videoId'], # адрес
        // );
        // получаем только id лучших видео
        $t = $v['id']['videoId'];
        $arr[] = $t;
    }

    return $arr;
}


add_filter( 'the_content', 'add_video_to_content' );
function add_video_to_content( $content ) {
    // global $post;
    // echo $post->ID;

    if ( in_category( YOUTUBE_CATEGORY ) ) {
        $channel_id = get_id_youtube_channel_from_post_id();
        $is_file_video = search_in_dir( 'youtube-channels',  $channel_id. '.txt');

        $file = YOUTUBE_CHANNELS_DIR . '/' . $channel_id . ".txt";

        $links = youtube_parsing( $channel_id );
        foreach ($links as $id_video) {
            //  https://www.youtube.com/watch?v=O8W4P2Gre_g
            $link .= "<iframe width='640' height='360' src='https://www.youtube.com/embed/$id_video?rel=0&amp;controls=0&amp;showinfo=0' frameborder='0' allowfullscreen></iframe><br>";
            // $link .= 'https://www.youtube.com/watch?v=' . $id_video . '</br>';
        }

        $out = $content . $link;
        // $out .= '<br>';
        // $out .= YOUTUBE_CHANNELS_DIR;

    	return $out;
    }

    if ( in_category( TWITCH_CATEGORY ) ) {
    	$out = $content . "<p>TWITCH_CATEGORY TWITCH_CATEGORY TWITCH_CATEGORY</p>";
    	return $out;
    }
}




// Создаем страницу плагина
add_action( 'admin_menu', 'aytv_create_menu' );
function aytv_create_menu() {
    add_options_page( 'Настройки YouTube and Twitch video in posts', 'Youtube / Twitch', 'manage_options', 'aytvp-main-menu', 'aytvp_settings');
}

// страница настроек плагина
function aytvp_settings() {


    $x = youtube_parsing( 'UCvBGdmck9fnkFa1jWucN87Q' );
    debug_aytv( $x );

    $channel_id = 'UCvBGdmck9fnkFa1jWucN87Q';
    $is_file_video = search_in_dir( 'youtube-channels',  $channel_id. '.txt');

    // если файл есть
    if ( $is_file_video ) {
        // проверяем дату файла и сравниваем со значением кэша
        // $file = YOUTUBE_CHANNELS_DIR . '/' . $channel_id . ".txt";

        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/youtube-twitch-parser/youtube-channels' ;
        $file = $dir . "/" . $channel_id . ".txt";
        debug_aytv( file_get_contents( $file ) );

        // если кэш устарел
        if ( time() - @filemtime( $file ) >= 3600 ) {
            // обновляем данные в файле, т.е ПАРСИМ Youtube
            echo "кэш устарел";
        } else echo "кэш ok";

    } else { // если файла нет
        // парсим Youtube = получаем список id видео
        $id_video_array = youtube_parsing( $channel_id );
        $to_text = implode( ' | ', $id_video_array );

        // создаем файл и записываем данные
        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/youtube-twitch-parser/youtube-channels' ;
        $file = $dir . "/" . $channel_id . ".txt";
        file_put_contents( $file, $to_text);
        chmod( $file, 0777 );

        // этот массив передаем в контент страницы

    }



    // Сохраняем настройки в базу
    if ( !empty($_POST['do_save']) ) {
        $options = [
            'youtube_category' => $_POST['id_youtube_category'],
            'youtube_apikey' => $_POST['youtube_apikey'],
            'twitch_category' => $_POST['id_twitch_category'],
            'twitch_apikey' => $_POST['twitch_apikey']
        ];
        // wp_unslash( $options );
        update_option( 'youtube_twitch_parser_settings', $options, true );
    }


    // debug_aytv( get_option( 'youtube_twitch_parser_settings', '' ) );

    // получаем массив с настройками
    $options = get_option( 'youtube_twitch_parser_settings', '' );

    ?>

    <style>
        .div-settings {
            width: 45%;
            /*height: 100px;*/
            background-color: #E6E9EB;
            border-radius: 5px;
            padding: 10px;
        }
    </style>

    <h3>НАСТРОЙКИ ПЛАГИНА</h3>
    <div class="div-settings">
        <form action="" method="POST">
        <table border="0" cellspacing="0" cellpadding="10">
            <tr>
                <td>
                    <strong>Youtube</strong>
                </td>
            </tr>

            <tr>
                <td>
                    <label for="youtube">ID категории Youtube (цифра)</label><br>
                    <input id="youtube" type="number" min="1" name="id_youtube_category" value="<?php echo $options['youtube_category']; ?>" autocomplete="off">
                </td>

                <td>
                    <label for="youtubeapikey">Youtube API key</label><br>
                    <input id="youtubeapikey" type="text" name="youtube_apikey" value="<?php echo $options['youtube_apikey']; ?>" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Twitch</strong>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="twitch">ID категории Twitch (цифра)</label><br>
                    <input id="twitch" type="number" min="1" name="id_twitch_category" value="<?php echo $options['twitch_category']; ?>" autocomplete="off">
                </td>

                <td>
                    <label for="twitchapikey">Twitch API key</label><br>
                    <input id="twitchapikey" type="text" name="twitch_apikey" value="<?php echo $options['twitch_apikey']; ?>" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" name="do_save" value="Сохранить">
                </td>
            </tr>
        </table>
        </form>
    </div>

<?php
} // aytvp_settings()



// Добавляем метабоксы
add_action('add_meta_boxes', 'aytv_add_custom_boxes');
function aytv_add_custom_boxes() {
    // $youtube_category = get_option( 'aytv_youtube_category', '' );
    // $twitch_category = get_option( 'aytv_twitch_category', '' );

    // if ( in_category( $youtube_category ) ) {
        add_meta_box( 'aytv_meta_box_youtube', 'Youtube', 'meta_box_youtube_id_chanell', 'post', 'side', 'high', null );
    // }
    // if ( in_category( $twitch_category ) ) {
        add_meta_box( 'aytv_meta_box_twitch', 'Twitch', 'meta_box_twitch_id_chanell', 'post', 'side', 'high', null );
    // }
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

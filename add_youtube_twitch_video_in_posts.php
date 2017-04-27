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


// ini_set( display_errors, 0);

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
define( 'COUNT_VIDEO', $options['count_video'] );
define( 'PARSING_PERIOD', $options['parsing_period'] );

define( 'WIDTH_VIDEO', $options['width_video'] );
define( 'HEIGHT_VIDEO', $options['height_video'] );

// url до папки с плагином
define( 'YT_PLUGIN_DIR', plugin_dir_url(__FILE__) );

// Действия при активации плагина
register_activation_hook( __FILE__, 'aytvp_activation' );
function aytvp_activation() {


    if ( get_option( 'youtube_twitch_parser_settings', '' ) == '' ) {
        $options = [
            'youtube_category' => '',
            'youtube_apikey'   => '',
            'twitch_category'  => '',
            'twitch_apikey'    => '',
            'count_video'      => 2,
            'parsing_period'   => 604800,
            'width_video'      => 320,
            'height_video'     => 280,
        ];
        // wp_unslash( $options );
        update_option( 'youtube_twitch_parser_settings', $options, true );
    }

    //  создаем папки для хранения id каналов
    // СОЗДАЕМ СТРУКТУРУ ПАПОК ДЛЯ ХРАНЕНИЯ ДАННЫХ
	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];

    // Создаем общую папку `bik` в wp-content/uploads
    $upload_dir = $upload_dir . '/youtube-twitch-parser-cache';

	if ( ! wp_mkdir_p( $upload_dir ) ) {
		echo "Не удалось создать директорию <pre>youtube-twitch-parser-cache</pre>";
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
    $id_channel = get_post_meta( $post->ID, _youtube_id_channel, true );
    return $id_channel;
}

// функция получения id канала по id поста к которому относится канал
function get_id_twitch_channel_from_post_id() {
    global $post;
    $id_channel = get_post_meta( $post->ID, _twitch_id_channel, true );
    return $id_channel;
}


// Поиск в директории true/false
// folder может быть == youtube-channels или twitch-channels
function search_in_dir ( $folder, $file ) {
    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'] . '/youtube-twitch-parser-cache' ;
    $dir = $upload_dir . '/' . $folder;
    $file_list = scandir( $dir );
    return ( in_array( $file, $file_list ) ) ? true : false;
}


// Парсим канал на Youtube и пишем данные в файл
function youtube_parsing( $channel_id, $cnt = COUNT_VIDEO ) {
    // специальный адрес, отвечающий за выдачу фида
    $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet'
         . '&channelId=' . $channel_id
         . '&order=viewCount'    // упорядочивать по кол-ву просмотров
         . '&maxResults=' . $cnt  // за раз получать не более $cnt результатов
         . '&fields=items/id/videoId'  // нам нужны только идентификаторы видео
         . '&key=' . YOUTUBE_APIKEY;

    $buf = file_get_contents( $url );

    // декодируем JSON данные
    $json = json_decode($buf, true);

    //  если данных нет —> увеличиваем счетчик
    // нужно, когда youtube отдает пустой массив
    if ( count( $json['items'] ) < $cnt )  {
        $cnt++;
        $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet'
             . '&channelId=' . $channel_id
             . '&order=viewCount'    // упорядочивать по кол-ву просмотров
             . '&maxResults=' . $cnt  // за раз получать не более $cnt результатов
             . '&fields=items/id/videoId'  // нам нужны только идентификаторы видео
             . '&key=' . YOUTUBE_APIKEY;
        $buf = file_get_contents( $url );
        $json = json_decode( $buf, true );
    }

    $arr = array();
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

    $to_text = implode( "\r\n", $arr );

    // создаем файл и записываем данные
    $upload = wp_upload_dir();
    $dir = $upload['basedir'] . '/youtube-twitch-parser-cache/youtube-channels' ;
    $file = $dir . "/" . $channel_id . ".txt";
    file_put_contents( $file, $to_text);
    chmod( $file, 0777 );

    $arr = file( $file, FILE_SKIP_EMPTY_LINES );
    $video_array = array_diff( $arr, array('', 0, null) );

    return $video_array;
}




// ПАРСИМ TWITCH
function twitch_parsing( $channel_id, $cnt = COUNT_VIDEO ) {

    $url = 'https://api.twitch.tv/kraken/channels/'
         . $channel_id
         . '/videos?limit=' . $cnt
         . '&sort=views'
         . '&client_id=' . TWITCH_APIKEY;

    $buf = file_get_contents( $url );
    // декодируем JSON данные
    $json = json_decode($buf, true);

    $arr = array();
    foreach( $json['videos'] as $v ) {
        // получаем только id видео
        $t = $v['_id'];
        $arr[] = $t;
    }

    $to_text = implode( "\r\n", $arr );

    // создаем файл и записываем данные
    $upload = wp_upload_dir();
    $dir = $upload['basedir'] . '/youtube-twitch-parser-cache/twitch-channels' ;
    $file = $dir . "/" . $channel_id . ".txt";
    file_put_contents( $file, $to_text);
    chmod( $file, 0777 );

    $arr = file( $file, FILE_SKIP_EMPTY_LINES );
    $video_array = array_diff( $arr, array('', 0, null) );

    return $video_array;
}


// html код для вставки в пост
function render_youtube_video( $arr ) {
    $div = '<h1>Популярные видео на канале</h1><hr>';
    foreach ( $arr as $id_video ) {
        $div .= "<span style='padding-bottom: 25px; padding-right: 25px;'>
        <iframe width='" . WIDTH_VIDEO . "' height=' " . HEIGHT_VIDEO . "'
        src='https://www.youtube.com/embed/$id_video?rel=0&amp;controls=0&amp;showinfo=0'
        frameborder='0' allowfullscreen>
        </iframe>
        </span>";
    }
    $div .= '<hr>';
    return $div;
}

// html код для вставки в пост
function render_twitch_video( $arr ) {
    $div = '<h1>Популярные видео на канале</h1><hr>';
    foreach ( $arr as $id_video ) {
        $div .=
        "<span style='padding-bottom: 25px; padding-right: 25px;'>
        <iframe src='https://player.twitch.tv/?video=$id_video&autoplay=false'
            frameborder='0' allowfullscreen='true' scrolling='no'
            height='" . HEIGHT_VIDEO . "' width='" . WIDTH_VIDEO . "'>
        </iframe>
        </span>";
    }
    $div .= '<hr>';
    return $div;
}



// ДОБАВЛЯЕМ ВИДЕО К КОНТЕНТУ ПОСТА
add_filter( 'the_content', 'add_video_to_content' );
function add_video_to_content( $content ) {
    // global $post;
    // echo $post->ID;

    if ( in_category( YOUTUBE_CATEGORY ) ) {
        // получаем id канала
        $channel_id = get_id_youtube_channel_from_post_id();

        // проверяем наличие файла
        $is_file_video = search_in_dir( 'youtube-channels',  $channel_id. '.txt');

        // если файл есть
        if ( $is_file_video ) {

            $upload = wp_upload_dir();
            $dir = $upload['basedir'] . '/youtube-twitch-parser-cache/youtube-channels' ;
            $file = $dir . "/" . $channel_id . ".txt";

            // проверяем дату файла и сравниваем со значением кэша
            // если кэш устарел
            // PARSING_PERIOD
            if ( time() - @filemtime( $file ) > PARSING_PERIOD ) {
                // обновляем данные в файле, т.е ПАРСИМ Youtube
                // и пишем в файл, который есть
                $arr = youtube_parsing( $channel_id );
                $out = $content . render_youtube_video( $arr );
            } else {
                // отдаем данные из кэша,
                $arr = youtube_parsing( $channel_id );
                $out = $content . render_youtube_video( $arr );
            }

        } else { // если файла нет
            $arr = youtube_parsing( $channel_id );
            $out = $content . render_youtube_video( $arr );
        }
        return $out;
    }


    if ( in_category( TWITCH_CATEGORY ) ) {
        // получаем id канала
        $channel_id = get_id_twitch_channel_from_post_id();

        // проверяем наличие файла
        $is_file_video = search_in_dir( 'twitch-channels',  $channel_id. '.txt');

        // если файл есть
        if ( $is_file_video ) {

            $upload = wp_upload_dir();
            $dir = $upload['basedir'] . '/youtube-twitch-parser-cache/twitch-channels' ;
            $file = $dir . "/" . $channel_id . ".txt";

            // проверяем дату файла и сравниваем со значением кэша
            // если кэш устарел
            // PARSING_PERIOD
            if ( time() - @filemtime( $file ) > PARSING_PERIOD ) {
                // обновляем данные в файле, т.е ПАРСИМ Youtube
                // и пишем в файл, который есть
                $arr = twitch_parsing( $channel_id );
                $out = $content . render_twitch_video( $arr );
            } else {
                // отдаем данные из кэша,
                $arr = twitch_parsing( $channel_id );
                $out = $content . render_twitch_video( $arr );
            }

        } else { // если файла нет
            $arr = twitch_parsing( $channel_id );
            $out = $content . render_twitch_video( $arr );
        }
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

    // Сохраняем настройки в базу
    if ( !empty($_POST['do_save']) ) {
        $options = [
            'youtube_category'   => $_POST['id_youtube_category'],
            'youtube_apikey'     => $_POST['youtube_apikey'],
            'twitch_category'    => $_POST['id_twitch_category'],
            'twitch_apikey'      => $_POST['twitch_apikey'],
            'count_video'        => $_POST['count_video'],
            'parsing_period'     => $_POST['parsing_period'],
            'width_video'        => $_POST['width_video'],
            'height_video'       => $_POST['height_video'],
        ];
        $result = update_option( 'youtube_twitch_parser_settings', $options, true );
        if ( $result ) {
            echo "<h1 style='color: green;'>Настройки успeшно сохранились</h1><hr>";
        }

    }

    // получаем массив с настройками
    $options = get_option( 'youtube_twitch_parser_settings', '' );


    // формируем список категорий сайта
    $args = array(
    	'type'         => 'post',
    	'child_of'     => 0,
    	'parent'       => '',
    	'orderby'      => 'name',
    	'order'        => 'ASC',
    	'hide_empty'   => 0,
    	'hierarchical' => 1,
    	'exclude'      => '',
    	'include'      => '',
    	'number'       => 0,
    	'taxonomy'     => 'category',
    	'pad_counts'   => false,
    );
    $categories = get_categories( $args );
    if( $categories ) {
    	foreach( $categories as $key ){
    		// Данные в объекте $cat
    		$cat[$key->term_id] = $key->name;
    	}
    }



    ?>

    <style>
        .div-settings {
            width: 55%;
            /*height: 100px;*/
            background-color: #E6E9EB;
            border-radius: 5px;
            padding: 10px;
        }
        table td .max-width {
            width: 50px;
        }
    </style>

    <h3>НАСТРОЙКИ ПЛАГИНА</h3>
    <div class="div-settings">
        <form action="" method="POST">
        <table border="0" cellspacing="0" cellpadding="10">
            <tr>
                <td class="max-width">
                    <strong>Youtube</strong>
                </td>
            </tr>

            <tr>
                <td class="max-width">
                    <label for="youtube">Категория Youtube</label><br>
                    <!-- <input id="youtube" type="number" min="1" name="id_youtube_category" value="<?php echo $options['youtube_category']; ?>" autocomplete="off"> -->
                    <select name="id_youtube_category">
                        <option value="" disabled="">-------</option>
                        <?php foreach ($cat as $cat_id => $name): ?>
                            <?php if ( $options['youtube_category'] == $cat_id) { ?>
                                <option value="<?php echo $cat_id; ?>" selected=""><?php echo $name;?></option>
                            <?php } else { ?>
                            <option value="<?php echo $cat_id; ?>"><?php echo $name;?></option>
                            <?php } ?>
                        <?php endforeach; ?>>
                    </select>
                </td>

                <td class="max-width">
                    <label for="youtubeapikey">Youtube API key</label><br>
                    <input id="youtubeapikey" size="35" type="text" name="youtube_apikey" value="<?php echo $options['youtube_apikey']; ?>" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Twitch</strong>
                </td>
            </tr>
            <tr>
                <td class="max-width">
                    <label for="twitch">Категория Twitch</label><br>
                    <select name="id_twitch_category">
                        <option value="" disabled="">-------</option>
                        <?php foreach ($cat as $cat_id => $name): ?>
                            <?php if ( $options['twitch_category'] == $cat_id) { ?>
                                <option value="<?php echo $cat_id; ?>" selected=""><?php echo $name;?></option>
                            <?php } else { ?>
                            <option value="<?php echo $cat_id; ?>"><?php echo $name;?></option>
                            <?php } ?>
                        <?php endforeach; ?>>
                    </select>
                </td>

                <td>
                    <label for="twitchapikey">Twitch API key</label><br>
                    <input id="twitchapikey" size="35" type="text" name="twitch_apikey" value="<?php echo $options['twitch_apikey']; ?>" autocomplete="off">
                </td>
            </tr>

            <tr>
                <td class="max-width">
                    <label for="count-video">Кол-во видео<br> на странице</label><br>
                    <input id="count-video" type="number" min="1" max="100" name="count_video" title="от 1 до 100" value="<?php echo $options['count_video']; ?>">
                </td>

                <td><br>
                    <label for="">Ширина и высота превью</label><br>
                    <input type="number" size="5" min="120" max="1080" name="width_video" value="<?php echo $options['width_video']; ?>"> x
                    <input type="number" size="5" min="120" max="1080" name="height_video" value="<?php echo $options['height_video']; ?>">
                </td>

            <tr>
                <td>
                    <label for="">Как часто парсим</label><br>
                    <select name="parsing_period" id="">
                    <?php
                        if ( 604800 == $options['parsing_period'] ) {
                            echo "<option value='604800' selected>раз в неделю</option>";
                            echo "<option value='2592000'>раз в месяц</option>";
                        } elseif ( 2592000 == $options['parsing_period'] ) {
                            echo "<option value='604800'>раз в неделю</option>";
                            echo "<option value='2592000' selected>раз в месяц</option>";
                        }
                    ?>
                </select>
                </td>
            </tr>


            <tr>
                <td>
                    <p><input type="submit" name="do_save" value="Сохранить"></p>
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
    add_meta_box( 'aytv_meta_box_youtube', 'Youtube', 'meta_box_youtube_id_channel', 'post', 'side', 'high', null );
    add_meta_box( 'aytv_meta_box_twitch', 'Twitch', 'meta_box_twitch_id_channel', 'post', 'side', 'high', null );
}


/**
*
* YOUTUBE META BOX
*
**/
/* HTML код блока */
function meta_box_youtube_id_channel() {
	wp_nonce_field( plugin_basename(__FILE__), 'aytv_youtube_noncename' );
    global $post;
	echo '<label for="youtube_id_channel">' . "Укажите id YOUTUBE канала" . '</label>';
	echo '<input type="text" id= "youtube_id_channel" autocomplete="off" name="youtube-id-channel" value="' . get_post_meta( $post->ID, _youtube_id_channel, true ) . '" size="25" />';
}

/* Сохраняем данные, когда пост сохраняется */
function save_youtube_id_channel( $post_id ) {
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
	if ( ! isset( $_POST['youtube-id-channel'] ) )
		return;

	// Все ОК. Теперь, нужно найти и сохранить данные
	// Очищаем значение поля input.
	$my_data = sanitize_text_field( $_POST['youtube-id-channel'] );
    if ( !empty( $my_data ) ) {
        // Обновляем данные в базе данных.
        update_post_meta( $post_id, '_youtube_id_channel', $my_data );
    }
}
add_action( 'save_post', 'save_youtube_id_channel' );



/**
*
* TWITCH META BOX
*
**/
/* HTML код блока */
function meta_box_twitch_id_channel() {
	wp_nonce_field( plugin_basename(__FILE__), 'aytv_twitch_noncename' );
    global $post;
	echo '<label for="twitch_id_channel">' . "Укажите id TWITCH канала" . '</label>';
	echo '<input type="text" id="twitch_id_channel" autocomplete="off" name="twitch-id-channel" value="' . get_post_meta( $post->ID, _twitch_id_channel, true ) . '" size="25" />';
}

/* Сохраняем данные, когда пост сохраняется */
function save_twitch_id_channel( $post_id ) {
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
	if ( ! isset( $_POST['twitch-id-channel'] ) )
		return;

	// Все ОК. Теперь, нужно найти и сохранить данные
	// Очищаем значение поля input.
	$my_data = sanitize_text_field( $_POST['twitch-id-channel'] );

    if ( !empty( $my_data ) ) {
        // Обновляем данные в базе данных.
        update_post_meta( $post_id, '_twitch_id_channel', $my_data );
    }
}
add_action( 'save_post', 'save_twitch_id_channel' );

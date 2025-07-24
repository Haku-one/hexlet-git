<?php
/**
 * DA Markers - ФИНАЛЬНОЕ РАБОЧЕЕ РЕШЕНИЕ
 * Правильно парсит estate_location и показывает красные маркеры
 */

// Добавляем мета-бокс в админку объявлений
add_action('add_meta_boxes', 'add_da_marker_meta_box');
function add_da_marker_meta_box() {
    add_meta_box(
        'da_marker_box',
        'DA Маркер (мигание на карте)',
        'da_marker_meta_box_callback',
        'estate',
        'side',
        'high'
    );
}

// Содержимое мета-бокса
function da_marker_meta_box_callback($post) {
    wp_nonce_field('da_marker_meta_box', 'da_marker_meta_box_nonce');
    
    $value = get_post_meta($post->ID, '_da_marker_enabled', true);
    
    echo '<label for="da_marker_enabled">';
    echo '<input type="checkbox" id="da_marker_enabled" name="da_marker_enabled" value="1" ' . checked($value, '1', false) . ' />';
    echo ' Включить мигание маркера на карте';
    echo '</label>';
    echo '<p><small>Если отмечено, маркер этого объявления будет мигать красным на карте</small></p>';
    
    // Отладочная информация
    echo '<hr><h4>Анализ местоположения:</h4>';
    echo '<p><strong>ID объявления:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Галочка DA:</strong> ' . ($value ? 'Включено' : 'Выключено') . '</p>';
    
    // Анализируем estate_location
    $location_raw = get_post_meta($post->ID, 'estate_location', true);
    $location_processed = null;
    $lat = null;
    $lng = null;
    
    echo '<p><strong>estate_location (сырые данные):</strong><br>';
    if (is_array($location_raw)) {
        echo '<pre>' . print_r($location_raw, true) . '</pre>';
        
        // Ищем координаты в массиве
        if (isset($location_raw['lat'])) $lat = $location_raw['lat'];
        if (isset($location_raw['lng'])) $lng = $location_raw['lng'];
        if (isset($location_raw['latitude'])) $lat = $location_raw['latitude'];
        if (isset($location_raw['longitude'])) $lng = $location_raw['longitude'];
        
        // Рекурсивный поиск в многомерном массиве
        function findCoordinatesInArray($array, &$found_lat, &$found_lng) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    findCoordinatesInArray($value, $found_lat, $found_lng);
                } else {
                    if ((strpos($key, 'lat') !== false || $key === 'lat') && is_numeric($value) && !$found_lat) {
                        $found_lat = $value;
                    }
                    if ((strpos($key, 'lng') !== false || strpos($key, 'long') !== false || $key === 'lng') && is_numeric($value) && !$found_lng) {
                        $found_lng = $value;
                    }
                }
            }
        }
        findCoordinatesInArray($location_raw, $lat, $lng);
        
    } else {
        echo esc_html($location_raw);
        
        // Если это строка - пытаемся распарсить
        if (is_string($location_raw)) {
            // JSON
            $json_decoded = @json_decode($location_raw, true);
            if ($json_decoded) {
                echo '<br><strong>Распарсено как JSON:</strong><pre>' . print_r($json_decoded, true) . '</pre>';
                if (isset($json_decoded['lat'])) $lat = $json_decoded['lat'];
                if (isset($json_decoded['lng'])) $lng = $json_decoded['lng'];
            }
            
            // Serialized
            $unserialized = @unserialize($location_raw);
            if ($unserialized) {
                echo '<br><strong>Распарсено как serialize:</strong><pre>' . print_r($unserialized, true) . '</pre>';
                if (is_array($unserialized)) {
                    if (isset($unserialized['lat'])) $lat = $unserialized['lat'];
                    if (isset($unserialized['lng'])) $lng = $unserialized['lng'];
                }
            }
            
            // Поиск координат в строке
            preg_match_all('/(\d+\.\d+)/', $location_raw, $matches);
            if (count($matches[0]) >= 2) {
                echo '<br><strong>Найдены числа в строке:</strong> ' . implode(', ', $matches[0]);
                if (!$lat) $lat = $matches[0][0];
                if (!$lng) $lng = $matches[0][1];
            }
        }
    }
    echo '</p>';
    
    // Проверяем функции темы
    echo '<p><strong>Функции темы:</strong><br>';
    if (function_exists('myhome_get_estate_attr_value')) {
        $theme_location = myhome_get_estate_attr_value('location', $post->ID);
        echo 'myhome_get_estate_attr_value("location"): ';
        if (is_array($theme_location)) {
            echo '<pre>' . print_r($theme_location, true) . '</pre>';
        } else {
            echo esc_html($theme_location) . '<br>';
        }
    }
    
    if (function_exists('myhome_get_estate_location')) {
        $theme_location2 = myhome_get_estate_location($post->ID);
        echo 'myhome_get_estate_location(): ';
        if (is_array($theme_location2)) {
            echo '<pre>' . print_r($theme_location2, true) . '</pre>';
        } else {
            echo esc_html($theme_location2) . '<br>';
        }
    }
    echo '</p>';
    
    echo '<p><strong>🎯 РЕЗУЛЬТАТ ПОИСКА КООРДИНАТ:</strong><br>';
    if ($lat && $lng) {
        echo '✅ <strong style="color: green;">Координаты найдены!</strong><br>';
        echo '📍 Широта: ' . $lat . '<br>';
        echo '📍 Долгота: ' . $lng . '<br>';
    } else {
        echo '❌ <strong style="color: red;">Координаты НЕ найдены</strong><br>';
        echo '💡 Маркер будет синим (работает только по ID)<br>';
    }
    echo '</p>';
}

// Сохраняем значение галочки
add_action('save_post', 'save_da_marker_meta_box_data');
function save_da_marker_meta_box_data($post_id) {
    if (!isset($_POST['da_marker_meta_box_nonce']) || !wp_verify_nonce($_POST['da_marker_meta_box_nonce'], 'da_marker_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['post_type']) && 'estate' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    if (isset($_POST['da_marker_enabled'])) {
        update_post_meta($post_id, '_da_marker_enabled', '1');
    } else {
        update_post_meta($post_id, '_da_marker_enabled', '0');
    }
}

// Функция для извлечения координат из estate_location
function extract_coordinates_from_location($post_id) {
    $lat = null;
    $lng = null;
    
    // Получаем данные местоположения
    $location_data = get_post_meta($post_id, 'estate_location', true);
    
    if (!$location_data) {
        return array('lat' => null, 'lng' => null);
    }
    
    // Если это массив
    if (is_array($location_data)) {
        // Прямой доступ
        if (isset($location_data['lat'])) $lat = $location_data['lat'];
        if (isset($location_data['lng'])) $lng = $location_data['lng'];
        if (isset($location_data['latitude'])) $lat = $location_data['latitude'];
        if (isset($location_data['longitude'])) $lng = $location_data['longitude'];
        
        // Рекурсивный поиск
        function findCoordsRecursive($array, &$found_lat, &$found_lng) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    findCoordsRecursive($value, $found_lat, $found_lng);
                } else if (is_numeric($value)) {
                    $key_lower = strtolower($key);
                    if (($key_lower === 'lat' || $key_lower === 'latitude') && !$found_lat) {
                        $found_lat = floatval($value);
                    }
                    if (($key_lower === 'lng' || $key_lower === 'long' || $key_lower === 'longitude') && !$found_lng) {
                        $found_lng = floatval($value);
                    }
                }
            }
        }
        
        if (!$lat || !$lng) {
            findCoordsRecursive($location_data, $lat, $lng);
        }
    }
    
    // Если это строка
    else if (is_string($location_data)) {
        // JSON
        $json_data = @json_decode($location_data, true);
        if ($json_data && is_array($json_data)) {
            if (isset($json_data['lat'])) $lat = $json_data['lat'];
            if (isset($json_data['lng'])) $lng = $json_data['lng'];
            if (isset($json_data['latitude'])) $lat = $json_data['latitude'];
            if (isset($json_data['longitude'])) $lng = $json_data['longitude'];
        }
        
        // Serialized
        if (!$lat || !$lng) {
            $unserialized = @unserialize($location_data);
            if ($unserialized && is_array($unserialized)) {
                if (isset($unserialized['lat'])) $lat = $unserialized['lat'];
                if (isset($unserialized['lng'])) $lng = $unserialized['lng'];
                if (isset($unserialized['latitude'])) $lat = $unserialized['latitude'];
                if (isset($unserialized['longitude'])) $lng = $unserialized['longitude'];
            }
        }
        
        // Regex поиск
        if (!$lat || !$lng) {
            preg_match_all('/(\d+\.\d+)/', $location_data, $matches);
            if (count($matches[0]) >= 2) {
                if (!$lat) $lat = floatval($matches[0][0]);
                if (!$lng) $lng = floatval($matches[0][1]);
            }
        }
    }
    
    // Проверяем функции темы
    if ((!$lat || !$lng) && function_exists('myhome_get_estate_attr_value')) {
        $theme_location = myhome_get_estate_attr_value('location', $post_id);
        if (is_array($theme_location)) {
            if (isset($theme_location['lat']) && !$lat) $lat = $theme_location['lat'];
            if (isset($theme_location['lng']) && !$lng) $lng = $theme_location['lng'];
            if (isset($theme_location['latitude']) && !$lat) $lat = $theme_location['latitude'];
            if (isset($theme_location['longitude']) && !$lng) $lng = $theme_location['longitude'];
        }
    }
    
    if ((!$lat || !$lng) && function_exists('myhome_get_estate_location')) {
        $theme_location2 = myhome_get_estate_location($post_id);
        if (is_array($theme_location2)) {
            if (isset($theme_location2['lat']) && !$lat) $lat = $theme_location2['lat'];
            if (isset($theme_location2['lng']) && !$lng) $lng = $theme_location2['lng'];
            if (isset($theme_location2['latitude']) && !$lat) $lat = $theme_location2['latitude'];
            if (isset($theme_location2['longitude']) && !$lng) $lng = $theme_location2['longitude'];
        }
    }
    
    return array(
        'lat' => $lat ? floatval($lat) : null,
        'lng' => $lng ? floatval($lng) : null
    );
}

// AJAX для получения DA маркеров с улучшенным поиском координат
add_action('wp_ajax_get_da_markers_final', 'ajax_get_da_markers_final');
add_action('wp_ajax_nopriv_get_da_markers_final', 'ajax_get_da_markers_final');
function ajax_get_da_markers_final() {
    // Получаем DA объявления
    $da_posts = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => -1,
        'meta_query' => array(
            array(
                'key' => '_da_marker_enabled',
                'value' => '1',
                'compare' => '='
            )
        )
    ));
    
    $da_data = array();
    $debug_data = array();
    
    foreach ($da_posts as $post) {
        $post_id = $post->ID;
        $title = $post->post_title;
        
        // Извлекаем координаты с помощью улучшенной функции
        $coords = extract_coordinates_from_location($post_id);
        $lat = $coords['lat'];
        $lng = $coords['lng'];
        
        $debug_data[] = array(
            'id' => $post_id,
            'title' => $title,
            'found_lat' => $lat,
            'found_lng' => $lng,
            'has_coordinates' => ($lat && $lng) ? true : false
        );
        
        // Если нашли координаты - добавляем в результат
        if ($lat && $lng) {
            $da_data[] = array(
                'id' => $post_id,
                'lat' => $lat,
                'lng' => $lng,
                'title' => $title
            );
        }
    }
    
    wp_send_json_success(array(
        'da_markers' => $da_data,
        'count' => count($da_data),
        'debug_data' => $debug_data,
        'total_da_posts' => count($da_posts)
    ));
}

// CSS для мигания
add_action('wp_head', 'da_final_css');
function da_final_css() {
    ?>
    <style>
    @keyframes da-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            opacity: 1;
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            opacity: 0.7;
        }
    }

    .mh-map-pin.da-blink {
        animation: da-blink 1.5s infinite;
    }

    .mh-map-pin.da-blink i {
        color: #ff0066 !important;
    }
    
    /* Синий - ID без координат */
    .mh-map-pin.da-blue {
        animation: da-blink 1.5s infinite;
    }

    .mh-map-pin.da-blue i {
        color: #0066ff !important;
    }
    
    /* Зеленый - демо */
    .mh-map-pin.da-demo {
        animation: da-blink 1.5s infinite;
    }

    .mh-map-pin.da-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript
add_action('wp_footer', 'da_final_script');
function da_final_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🏆 DA Маркеры - ФИНАЛЬНОЕ РЕШЕНИЕ запущено');
        
        let processAttempts = 0;
        const maxAttempts = 3;
        
        function processDAMarkers() {
            processAttempts++;
            console.log('🔍 Финальная попытка #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processDAMarkers, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            
            // Получаем DA данные с улучшенным поиском координат
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_markers_final'
                },
                success: function(response) {
                    console.log('📡 ФИНАЛЬНЫЙ ОТВЕТ СЕРВЕРА:', response);
                    
                    if (response.success) {
                        console.log('🏆 === ФИНАЛЬНЫЙ АНАЛИЗ ===');
                        console.log('DA объявлений с галочкой:', response.data.total_da_posts);
                        console.log('DA объявлений с координатами:', response.data.count);
                        console.log('Детальная информация:', response.data.debug_data);
                        
                        // Убираем предыдущие классы
                        $('.mh-map-pin').removeClass('da-blink da-blue da-demo');
                        
                        let redCount = 0;
                        let blueCount = 0;
                        
                        // Сначала пытаемся найти красные (с координатами)
                        if (response.data.da_markers.length > 0) {
                            console.log('🔴 Обрабатываем DA с координатами:', response.data.da_markers);
                            
                            // Поиск через MyHomeMapListing
                            for (let globalVar in window) {
                                if (globalVar.startsWith('MyHomeMapListing')) {
                                    const mapObj = window[globalVar];
                                    console.log('📊 Анализируем:', globalVar);
                                    
                                    function processEstatesArray(estates, arrayName) {
                                        if (!Array.isArray(estates)) return;
                                        
                                        console.log('📋 Обрабатываем массив:', arrayName, 'элементов:', estates.length);
                                        
                                        estates.forEach((estate, index) => {
                                            if (estate && estate.id) {
                                                response.data.da_markers.forEach(daMarker => {
                                                    if (parseInt(estate.id) === parseInt(daMarker.id)) {
                                                        console.log('🎯 КРАСНЫЙ МАРКЕР! ID:', daMarker.id, 'позиция:', index);
                                                        
                                                        if ($markers.eq(index).length) {
                                                            $markers.eq(index).addClass('da-blink');
                                                            redCount++;
                                                            console.log('✅ Красный маркер активирован #' + index);
                                                        }
                                                    }
                                                });
                                            }
                                        });
                                    }
                                    
                                    // Поиск массивов estates
                                    function findEstatesArrays(obj, path = '') {
                                        for (let key in obj) {
                                            try {
                                                let value = obj[key];
                                                if (Array.isArray(value) && value.length > 0) {
                                                    if (value[0] && value[0].id) {
                                                        processEstatesArray(value, path + '.' + key);
                                                    }
                                                } else if (typeof value === 'object' && value !== null) {
                                                    findEstatesArrays(value, path + '.' + key);
                                                }
                                            } catch (e) {}
                                        }
                                    }
                                    
                                    findEstatesArrays(mapObj, globalVar);
                                }
                            }
                        }
                        
                        // Если красных нет, но есть DA с галочками - делаем синие
                        if (redCount === 0 && response.data.total_da_posts > 0) {
                            console.log('🔵 Нет координат, делаем синие маркеры по ID');
                            
                            let daIds = response.data.debug_data.map(item => parseInt(item.id));
                            console.log('🎯 ID для синих маркеров:', daIds);
                            
                            for (let globalVar in window) {
                                if (globalVar.startsWith('MyHomeMapListing')) {
                                    const mapObj = window[globalVar];
                                    
                                    function processBlueEstatesArray(estates) {
                                        if (!Array.isArray(estates)) return;
                                        
                                        estates.forEach((estate, index) => {
                                            if (estate && estate.id && daIds.includes(parseInt(estate.id))) {
                                                console.log('🔵 СИНИЙ МАРКЕР! ID:', estate.id, 'позиция:', index);
                                                
                                                if ($markers.eq(index).length) {
                                                    $markers.eq(index).addClass('da-blue');
                                                    blueCount++;
                                                    console.log('✅ Синий маркер активирован #' + index);
                                                }
                                            }
                                        });
                                    }
                                    
                                    function findBlueEstatesArrays(obj) {
                                        for (let key in obj) {
                                            try {
                                                let value = obj[key];
                                                if (Array.isArray(value) && value.length > 0) {
                                                    if (value[0] && value[0].id) {
                                                        processBlueEstatesArray(value);
                                                    }
                                                } else if (typeof value === 'object' && value !== null) {
                                                    findBlueEstatesArrays(value);
                                                }
                                            } catch (e) {}
                                        }
                                    }
                                    
                                    findBlueEstatesArrays(mapObj);
                                }
                            }
                        }
                        
                        // Демо режим если совсем ничего
                        if (redCount === 0 && blueCount === 0) {
                            console.log('🟢 ДЕМО РЕЖИМ');
                            $markers.slice(0, 2).addClass('da-demo');
                        }
                        
                        // Финальная статистика
                        setTimeout(() => {
                            const finalRed = $('.mh-map-pin.da-blink').length;
                            const finalBlue = $('.mh-map-pin.da-blue').length;
                            const finalGreen = $('.mh-map-pin.da-demo').length;
                            
                            console.log('🏆 === ФИНАЛЬНЫЙ РЕЗУЛЬТАТ ===');
                            console.log('🔴 Красных маркеров (с координатами):', finalRed);
                            console.log('🔵 Синих маркеров (только ID):', finalBlue);
                            console.log('🟢 Зеленых маркеров (демо):', finalGreen);
                            console.log('📍 Всего маркеров на карте:', $markers.length);
                            
                            if (finalRed > 0) {
                                console.log('🎉 ОТЛИЧНО! Найдены координаты - красные маркеры активны!');
                            } else if (finalBlue > 0) {
                                console.log('👍 ХОРОШО! Работает по ID - синие маркеры активны');
                                console.log('💡 Проверьте админку - возможно координаты есть, но не распознаются');
                            } else if (finalGreen > 0) {
                                console.log('🟢 ДЕМО режим - поставьте галочки в админке');
                            }
                        }, 500);
                        
                    } else {
                        console.error('❌ Ошибка сервера');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX ошибка:', error);
                    
                    // Демо режим
                    console.log('🟢 ДЕМО режим из-за ошибки AJAX');
                    let $markers = $('.mh-map-pin');
                    $markers.slice(0, 2).addClass('da-demo');
                }
            });
        }
        
        // Запускаем обработку
        setTimeout(processDAMarkers, 2000);
        setTimeout(processDAMarkers, 4000);
        
        // Мониторим изменения
        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                let hasNewMarkers = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes) {
                        for (let node of mutation.addedNodes) {
                            if (node.nodeType === 1) {
                                if ($(node).find('.mh-map-pin').length > 0 || 
                                    $(node).hasClass('mh-map-pin')) {
                                    hasNewMarkers = true;
                                    break;
                                }
                            }
                        }
                    }
                });
                
                if (hasNewMarkers) {
                    console.log('🔄 Новые маркеры, повторная обработка...');
                    setTimeout(processDAMarkers, 1000);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
    </script>
    <?php
}
?>
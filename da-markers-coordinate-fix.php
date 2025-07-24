<?php
/**
 * DA Markers - ИСПРАВЛЕНИЕ КООРДИНАТ
 * Находим где реально хранятся координаты + работающее решение
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
    echo '<hr><h4>Отладка координат:</h4>';
    echo '<p><strong>ID объявления:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Галочка DA:</strong> ' . ($value ? 'Включено' : 'Выключено') . '</p>';
    
    // Поиск координат во всех возможных мета-полях
    $coord_fields = [
        'myhome_lat', 'myhome_lng',
        '_myhome_lat', '_myhome_lng',
        'latitude', 'longitude',
        '_latitude', '_longitude',
        'lat', 'lng',
        '_lat', '_lng',
        'estate_location', '_estate_location',
        'property_location', '_property_location'
    ];
    
    echo '<p><strong>Поиск координат в мета-полях:</strong></p>';
    echo '<ul>';
    foreach ($coord_fields as $field) {
        $value_field = get_post_meta($post->ID, $field, true);
        if ($value_field) {
            echo '<li><strong>' . $field . ':</strong> ' . $value_field . '</li>';
        }
    }
    echo '</ul>';
    
    // Все мета-поля
    $all_meta = get_post_meta($post->ID);
    echo '<p><strong>Все мета-поля (первые 20):</strong></p>';
    echo '<ul style="max-height: 200px; overflow-y: scroll; font-size: 11px;">';
    $count = 0;
    foreach ($all_meta as $key => $values) {
        if ($count++ > 20) break;
        $display_value = is_array($values) ? $values[0] : $values;
        if (strlen($display_value) > 100) {
            $display_value = substr($display_value, 0, 100) . '...';
        }
        echo '<li><strong>' . $key . ':</strong> ' . esc_html($display_value) . '</li>';
    }
    echo '</ul>';
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

// AJAX для получения DA маркеров с поиском координат
add_action('wp_ajax_get_da_markers_with_coords', 'ajax_get_da_markers_with_coords');
add_action('wp_ajax_nopriv_get_da_markers_with_coords', 'ajax_get_da_markers_with_coords');
function ajax_get_da_markers_with_coords() {
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
        
        // Ищем координаты во всех возможных полях
        $coord_fields = [
            'myhome_lat', 'myhome_lng',
            '_myhome_lat', '_myhome_lng',
            'latitude', 'longitude',
            '_latitude', '_longitude',
            'lat', 'lng',
            '_lat', '_lng'
        ];
        
        $lat = null;
        $lng = null;
        $found_fields = array();
        
        // Поиск координат
        foreach ($coord_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            if ($value) {
                $found_fields[$field] = $value;
                
                if (strpos($field, 'lat') !== false && !$lat) {
                    $lat = $value;
                }
                if (strpos($field, 'lng') !== false && !$lng) {
                    $lng = $value;
                }
                if (strpos($field, 'longitude') !== false && !$lng) {
                    $lng = $value;
                }
            }
        }
        
        // Проверяем location поля (могут содержать JSON или сериализованные данные)
        $location_fields = ['estate_location', '_estate_location', 'property_location', '_property_location'];
        foreach ($location_fields as $field) {
            $location_data = get_post_meta($post_id, $field, true);
            if ($location_data) {
                $found_fields[$field] = $location_data;
                
                // Пытаемся распарсить как JSON
                $json_data = @json_decode($location_data, true);
                if ($json_data) {
                    if (isset($json_data['lat']) && !$lat) $lat = $json_data['lat'];
                    if (isset($json_data['lng']) && !$lng) $lng = $json_data['lng'];
                    if (isset($json_data['latitude']) && !$lat) $lat = $json_data['latitude'];
                    if (isset($json_data['longitude']) && !$lng) $lng = $json_data['longitude'];
                }
                
                // Пытаемся распарсить как сериализованные данные
                $unserialized = @unserialize($location_data);
                if ($unserialized && is_array($unserialized)) {
                    if (isset($unserialized['lat']) && !$lat) $lat = $unserialized['lat'];
                    if (isset($unserialized['lng']) && !$lng) $lng = $unserialized['lng'];
                    if (isset($unserialized['latitude']) && !$lat) $lat = $unserialized['latitude'];
                    if (isset($unserialized['longitude']) && !$lng) $lng = $unserialized['longitude'];
                }
                
                // Поиск координат в строке
                if (is_string($location_data)) {
                    preg_match_all('/(\d+\.\d+)/', $location_data, $matches);
                    if (count($matches[0]) >= 2) {
                        if (!$lat) $lat = $matches[0][0];
                        if (!$lng) $lng = $matches[0][1];
                    }
                }
            }
        }
        
        $debug_data[] = array(
            'id' => $post_id,
            'title' => $title,
            'found_lat' => $lat,
            'found_lng' => $lng,
            'found_fields' => $found_fields
        );
        
        // Если нашли координаты - добавляем в результат
        if ($lat && $lng) {
            $da_data[] = array(
                'id' => $post_id,
                'lat' => floatval($lat),
                'lng' => floatval($lng),
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

// Простой CSS для мигания
add_action('wp_head', 'da_coord_fix_css');
function da_coord_fix_css() {
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
    
    /* Демо режим - зеленый */
    .mh-map-pin.da-demo {
        animation: da-blink 1.5s infinite;
    }

    .mh-map-pin.da-demo i {
        color: #00ff66 !important;
    }
    
    /* Альтернативный метод - синий */
    .mh-map-pin.da-alt {
        animation: da-blink 1.5s infinite;
    }

    .mh-map-pin.da-alt i {
        color: #0066ff !important;
    }
    </style>
    <?php
}

// JavaScript с поиском координат
add_action('wp_footer', 'da_coord_fix_script');
function da_coord_fix_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🔧 DA Маркеры - ИСПРАВЛЕНИЕ КООРДИНАТ запущено');
        
        let processAttempts = 0;
        const maxAttempts = 3;
        
        function processDAMarkers() {
            processAttempts++;
            console.log('🔍 Попытка поиска координат #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processDAMarkers, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            
            // Получаем DA данные с поиском координат
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_markers_with_coords'
                },
                success: function(response) {
                    console.log('📡 ОТВЕТ ПОИСКА КООРДИНАТ:', response);
                    
                    if (response.success) {
                        console.log('🔍 === АНАЛИЗ КООРДИНАТ ===');
                        console.log('DA объявлений с галочкой:', response.data.total_da_posts);
                        console.log('DA объявлений с координатами:', response.data.count);
                        console.log('Детали поиска координат:', response.data.debug_data);
                        
                        // Убираем предыдущие классы
                        $('.mh-map-pin').removeClass('da-blink da-demo da-alt');
                        
                        let foundCount = 0;
                        
                        if (response.data.da_markers.length > 0) {
                            console.log('✅ Найдены DA с координатами:', response.data.da_markers);
                            
                            // Ищем маркеры через MyHomeMapListing
                            for (let globalVar in window) {
                                if (globalVar.startsWith('MyHomeMapListing')) {
                                    const mapObj = window[globalVar];
                                    console.log('📊 Анализируем объект карты:', globalVar, mapObj);
                                    
                                    function findEstatesInObject(obj, path = '') {
                                        for (let key in obj) {
                                            try {
                                                let value = obj[key];
                                                if (Array.isArray(value) && value.length > 0) {
                                                    if (value[0] && (value[0].id || value[0].lat || value[0].lng)) {
                                                        console.log('📋 Массив с данными:', path + '.' + key);
                                                        console.log('📋 Образец элементов:', value.slice(0, 3));
                                                        
                                                        // Сопоставляем по ID
                                                        value.forEach((estate, index) => {
                                                            if (estate && estate.id) {
                                                                response.data.da_markers.forEach(daMarker => {
                                                                    if (parseInt(estate.id) === parseInt(daMarker.id)) {
                                                                        console.log('🎯 НАЙДЕН DA МАРКЕР ПО ID!', daMarker.id, 'позиция:', index);
                                                                        
                                                                        if ($markers.eq(index).length) {
                                                                            $markers.eq(index).addClass('da-blink');
                                                                            foundCount++;
                                                                            console.log('✅ Красный маркер активирован #' + index);
                                                                        }
                                                                    }
                                                                });
                                                            }
                                                        });
                                                        
                                                        // Если не нашли по ID, пробуем по координатам
                                                        if (foundCount === 0) {
                                                            value.forEach((estate, index) => {
                                                                if (estate && estate.lat && estate.lng) {
                                                                    response.data.da_markers.forEach(daMarker => {
                                                                        if (Math.abs(parseFloat(estate.lat) - daMarker.lat) < 0.001 && 
                                                                            Math.abs(parseFloat(estate.lng) - daMarker.lng) < 0.001) {
                                                                            console.log('🎯 НАЙДЕН DA МАРКЕР ПО КООРДИНАТАМ!', daMarker.id, 'позиция:', index);
                                                                            
                                                                            if ($markers.eq(index).length) {
                                                                                $markers.eq(index).addClass('da-blink');
                                                                                foundCount++;
                                                                                console.log('✅ Красный маркер активирован #' + index);
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    }
                                                } else if (typeof value === 'object' && value !== null) {
                                                    findEstatesInObject(value, path + '.' + key);
                                                }
                                            } catch (e) {
                                                // Игнорируем ошибки
                                            }
                                        }
                                    }
                                    
                                    findEstatesInObject(mapObj, globalVar);
                                }
                            }
                            
                        } else {
                            console.log('⚠️ DA объявления с координатами не найдены');
                            
                            // Но у нас есть DA с галочками - пробуем альтернативный метод
                            if (response.data.total_da_posts > 0) {
                                console.log('🔄 Альтернативный метод: поиск по ID без координат');
                                
                                // Получаем ID объявлений с галочками
                                let daIds = response.data.debug_data.map(item => parseInt(item.id));
                                console.log('🎯 ID с галочками DA:', daIds);
                                
                                // Ищем эти ID в структуре карты
                                for (let globalVar in window) {
                                    if (globalVar.startsWith('MyHomeMapListing')) {
                                        const mapObj = window[globalVar];
                                        
                                        function findByDaIds(obj, path = '') {
                                            for (let key in obj) {
                                                try {
                                                    let value = obj[key];
                                                    if (Array.isArray(value) && value.length > 0) {
                                                        if (value[0] && value[0].id) {
                                                            console.log('📋 Проверяем массив:', path + '.' + key);
                                                            
                                                            value.forEach((estate, index) => {
                                                                if (estate && estate.id && daIds.includes(parseInt(estate.id))) {
                                                                    console.log('🎯 НАЙДЕН DA ID!', estate.id, 'позиция:', index);
                                                                    
                                                                    if ($markers.eq(index).length) {
                                                                        $markers.eq(index).addClass('da-alt');
                                                                        foundCount++;
                                                                        console.log('✅ Синий маркер активирован #' + index);
                                                                    }
                                                                }
                                                            });
                                                        }
                                                    } else if (typeof value === 'object' && value !== null) {
                                                        findByDaIds(value, path + '.' + key);
                                                    }
                                                } catch (e) {
                                                    // Игнорируем ошибки
                                                }
                                            }
                                        }
                                        
                                        findByDaIds(mapObj, globalVar);
                                    }
                                }
                            }
                        }
                        
                        // Демо режим если ничего не найдено
                        if (foundCount === 0) {
                            console.log('🟢 ДЕМО РЕЖИМ: зеленые маркеры');
                            $markers.slice(0, 2).addClass('da-demo');
                            foundCount = 2;
                        }
                        
                        // Финальная статистика
                        setTimeout(() => {
                            const redFound = $('.mh-map-pin.da-blink').length;
                            const blueFound = $('.mh-map-pin.da-alt').length;
                            const greenFound = $('.mh-map-pin.da-demo').length;
                            
                            console.log('📊 === ФИНАЛЬНЫЕ РЕЗУЛЬТАТЫ ===');
                            console.log('🔴 Красных маркеров (с координатами):', redFound);
                            console.log('🔵 Синих маркеров (только ID):', blueFound);
                            console.log('🟢 Зеленых маркеров (демо):', greenFound);
                            console.log('📍 Всего маркеров на карте:', $markers.length);
                            
                            if (redFound > 0) {
                                console.log('🎉 ИДЕАЛЬНО! Красные маркеры = DA с координатами');
                            } else if (blueFound > 0) {
                                console.log('👍 ХОРОШО! Синие маркеры = DA без координат');
                                console.log('💡 Добавьте координаты в объявления для красных маркеров');
                            } else if (greenFound > 0) {
                                console.log('🟢 ДЕМО режим активен');
                                console.log('💡 Поставьте галочки в админке на нужных объявлениях');
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
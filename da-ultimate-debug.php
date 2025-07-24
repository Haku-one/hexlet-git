<?php
/**
 * DA Markers - МАКСИМАЛЬНАЯ ДИАГНОСТИКА
 * Полный анализ estate, координат и маркеров
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
    echo '<p><small>МАКСИМАЛЬНАЯ ДИАГНОСТИКА</small></p>';
    
    // ПОЛНЫЙ АНАЛИЗ КООРДИНАТ
    echo '<hr><h4>🔍 ПОЛНЫЙ АНАЛИЗ КООРДИНАТ:</h4>';
    echo '<p><strong>ID объявления:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Галочка DA:</strong> ' . ($value ? 'Включено' : 'Выключено') . '</p>';
    
    // Получаем ВСЕ мета поля
    $all_meta = get_post_meta($post->ID);
    
    echo '<h5>Поиск координат во ВСЕХ мета-полях:</h5>';
    $coord_fields = array();
    
    foreach ($all_meta as $key => $values) {
        $value_str = is_array($values) ? print_r($values, true) : $values;
        
        // Ищем паттерны координат
        if (preg_match('/\d+\.\d+/', $value_str)) {
            if (strpos($key, 'lat') !== false || strpos($key, 'lng') !== false || 
                strpos($key, 'location') !== false || strpos($key, 'coord') !== false ||
                strpos($key, 'map') !== false) {
                $coord_fields[$key] = $values;
            }
        }
    }
    
    if (!empty($coord_fields)) {
        echo '<div style="background: #f0f8ff; padding: 10px; border: 1px solid #0073aa;">';
        foreach ($coord_fields as $field => $value) {
            echo '<p><strong>' . $field . ':</strong> ';
            if (is_array($value)) {
                echo '<pre style="font-size: 11px;">' . print_r($value, true) . '</pre>';
            } else {
                echo $value;
            }
            echo '</p>';
        }
        echo '</div>';
    } else {
        echo '<p style="color: red;">❌ Поля с координатами не найдены!</p>';
    }
    
    // Извлекаем координаты разными способами
    $extracted_coords = array();
    
    // Способ 1: estate_location
    $location_data = get_post_meta($post->ID, 'estate_location', true);
    if (is_array($location_data) && isset($location_data['lat']) && isset($location_data['lng'])) {
        $extracted_coords['estate_location'] = array(
            'lat' => $location_data['lat'],
            'lng' => $location_data['lng']
        );
    }
    
    // Способ 2: myhome_lat/lng
    $myhome_lat = get_post_meta($post->ID, 'myhome_lat', true);
    $myhome_lng = get_post_meta($post->ID, 'myhome_lng', true);
    if ($myhome_lat && $myhome_lng) {
        $extracted_coords['myhome'] = array(
            'lat' => $myhome_lat,
            'lng' => $myhome_lng
        );
    }
    
    // Способ 3: Поиск во всех полях
    foreach ($all_meta as $key => $values) {
        if (is_array($values)) {
            foreach ($values as $value) {
                if (is_string($value) || is_array($value)) {
                    $value_str = is_array($value) ? serialize($value) : $value;
                    
                    // Паттерны для поиска координат
                    if (preg_match_all('/(\d+\.\d{4,})/', $value_str, $matches)) {
                        $numbers = $matches[1];
                        if (count($numbers) >= 2) {
                            // Проверяем, что это похоже на координаты (широта 50-60, долгота 30-50 для России)
                            foreach ($numbers as $i => $num) {
                                if (isset($numbers[$i+1])) {
                                    $num1 = floatval($num);
                                    $num2 = floatval($numbers[$i+1]);
                                    
                                    if (($num1 >= 50 && $num1 <= 70 && $num2 >= 20 && $num2 <= 180) ||
                                        ($num2 >= 50 && $num2 <= 70 && $num1 >= 20 && $num1 <= 180)) {
                                        $extracted_coords[$key . '_parsed'] = array(
                                            'lat' => $num1 >= 50 && $num1 <= 70 ? $num1 : $num2,
                                            'lng' => $num1 >= 50 && $num1 <= 70 ? $num2 : $num1
                                        );
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    echo '<h5>✅ ИЗВЛЕЧЁННЫЕ КООРДИНАТЫ:</h5>';
    if (!empty($extracted_coords)) {
        foreach ($extracted_coords as $source => $coords) {
            echo '<div style="background: #e7f3ff; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa;">';
            echo '<strong>' . $source . ':</strong><br>';
            echo 'Широта: ' . $coords['lat'] . '<br>';
            echo 'Долгота: ' . $coords['lng'];
            echo '</div>';
        }
    } else {
        echo '<p style="color: red;">❌ Координаты не извлечены!</p>';
    }
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

// AJAX для получения ВСЕХ данных
add_action('wp_ajax_get_da_ultimate_debug', 'ajax_get_da_ultimate_debug');
add_action('wp_ajax_nopriv_get_da_ultimate_debug', 'ajax_get_da_ultimate_debug');
function ajax_get_da_ultimate_debug() {
    // Получаем ВСЕ estate объявления
    $all_estates = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    $debug_data = array(
        'total_estates' => count($all_estates),
        'da_estates' => array(),
        'all_estates_sample' => array(),
        'coordinate_sources' => array(),
        'meta_fields_analysis' => array()
    );
    
    $coord_sources = array();
    
    foreach ($all_estates as $estate) {
        $is_da = get_post_meta($estate->ID, '_da_marker_enabled', true) === '1';
        $all_meta = get_post_meta($estate->ID);
        
        // Анализируем все мета поля для первых 5 объявлений
        if (count($debug_data['all_estates_sample']) < 5) {
            $estate_meta = array();
            foreach ($all_meta as $key => $values) {
                $value = is_array($values) && count($values) === 1 ? $values[0] : $values;
                if (is_array($value) || strpos($key, 'location') !== false || 
                    strpos($key, 'lat') !== false || strpos($key, 'lng') !== false ||
                    strpos($key, 'coord') !== false || strpos($key, 'map') !== false) {
                    $estate_meta[$key] = $value;
                }
            }
            
            $debug_data['all_estates_sample'][] = array(
                'id' => $estate->ID,
                'title' => $estate->post_title,
                'is_da' => $is_da,
                'meta_fields' => $estate_meta
            );
        }
        
        // Ищем координаты разными способами
        $coords_found = array();
        
        // Способ 1: estate_location
        $location_data = get_post_meta($estate->ID, 'estate_location', true);
        if (is_array($location_data) && isset($location_data['lat']) && isset($location_data['lng'])) {
            $coords_found['estate_location'] = array(
                'lat' => floatval($location_data['lat']),
                'lng' => floatval($location_data['lng'])
            );
        }
        
        // Способ 2: myhome поля
        $myhome_lat = get_post_meta($estate->ID, 'myhome_lat', true);
        $myhome_lng = get_post_meta($estate->ID, 'myhome_lng', true);
        if ($myhome_lat && $myhome_lng) {
            $coords_found['myhome'] = array(
                'lat' => floatval($myhome_lat),
                'lng' => floatval($myhome_lng)
            );
        }
        
        // Способ 3: Поиск паттернов во всех полях
        foreach ($all_meta as $key => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    if (is_string($value) && preg_match_all('/(\d+\.\d{4,})/', $value, $matches)) {
                        $numbers = $matches[1];
                        if (count($numbers) >= 2) {
                            $num1 = floatval($numbers[0]);
                            $num2 = floatval($numbers[1]);
                            
                            if (($num1 >= 50 && $num1 <= 70 && $num2 >= 20 && $num2 <= 180) ||
                                ($num2 >= 50 && $num2 <= 70 && $num1 >= 20 && $num1 <= 180)) {
                                $coords_found[$key . '_pattern'] = array(
                                    'lat' => $num1 >= 50 && $num1 <= 70 ? $num1 : $num2,
                                    'lng' => $num1 >= 50 && $num1 <= 70 ? $num2 : $num1,
                                    'raw' => $value
                                );
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // Собираем статистику источников координат
        foreach ($coords_found as $source => $data) {
            if (!isset($coord_sources[$source])) {
                $coord_sources[$source] = 0;
            }
            $coord_sources[$source]++;
        }
        
        // Если это DA объявление
        if ($is_da) {
            $debug_data['da_estates'][] = array(
                'id' => $estate->ID,
                'title' => $estate->post_title,
                'coordinates' => $coords_found,
                'coords_count' => count($coords_found)
            );
        }
    }
    
    $debug_data['coordinate_sources'] = $coord_sources;
    
    wp_send_json_success($debug_data);
}

// CSS для мигания
add_action('wp_head', 'da_ultimate_debug_css');
function da_ultimate_debug_css() {
    ?>
    <style>
    @keyframes da-ultimate-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            transform: scale(1.1);
        }
    }

    .mh-map-pin.da-ultimate-found {
        animation: da-ultimate-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-ultimate-found i {
        color: #ff0066 !important;
    }

    .mh-map-pin.da-ultimate-demo {
        animation: da-ultimate-blink 1.5s infinite;
    }

    .mh-map-pin.da-ultimate-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript - максимальная диагностика
add_action('wp_footer', 'da_ultimate_debug_script');
function da_ultimate_debug_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🔬 DA МАКСИМАЛЬНАЯ ДИАГНОСТИКА - запущено');
        
        let debugData = null;
        let processAttempts = 0;
        const maxAttempts = 3;
        
        // Получаем данные с сервера
        function fetchDebugData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_ultimate_debug'
                }
            });
        }
        
        // Анализ DOM маркеров
        function analyzeMarkers() {
            const $markers = $('.mh-map-pin');
            console.log('📍 === АНАЛИЗ МАРКЕРОВ ===');
            console.log('Всего маркеров:', $markers.length);
            
            $markers.each(function(index) {
                const $marker = $(this);
                console.log('🔍 Маркер #' + index + ':');
                
                // Анализируем родительские элементы
                let $parent = $marker;
                for (let i = 0; i < 10; i++) {
                    $parent = $parent.parent();
                    if ($parent.length === 0) break;
                    
                    const html = $parent.html();
                    const style = $parent.attr('style') || '';
                    
                    // Ищем координаты в HTML
                    const coordMatches = html ? html.match(/(\d+\.\d{4,})/g) : null;
                    if (coordMatches && coordMatches.length >= 2) {
                        console.log('  📍 Координаты в HTML (уровень ' + i + '):', coordMatches);
                    }
                    
                    // Ищем координаты в стилях
                    const styleMatches = style.match(/(\d+\.\d+)/g);
                    if (styleMatches && styleMatches.length >= 2) {
                        console.log('  🎨 Числа в стилях (уровень ' + i + '):', styleMatches);
                    }
                    
                    // Проверяем атрибуты
                    const attrs = $parent[0] ? $parent[0].attributes : null;
                    if (attrs) {
                        Array.from(attrs).forEach(attr => {
                            if (attr.value && /\d+\.\d{4,}/.test(attr.value)) {
                                console.log('  🏷️ Координаты в атрибуте ' + attr.name + ':', attr.value);
                            }
                        });
                    }
                }
            });
        }
        
        // Анализ глобальных объектов карты
        function analyzeMapObjects() {
            console.log('🗺️ === АНАЛИЗ ОБЪЕКТОВ КАРТЫ ===');
            
            for (let globalVar in window) {
                if (globalVar.startsWith('MyHomeMapListing')) {
                    const mapObj = window[globalVar];
                    console.log('🔍 Объект карты:', globalVar);
                    
                    // Рекурсивный поиск массивов с координатами
                    function findArraysWithCoords(obj, path = '', depth = 0) {
                        if (depth > 3) return; // Ограничиваем глубину
                        
                        try {
                            for (let key in obj) {
                                const value = obj[key];
                                const currentPath = path ? path + '.' + key : key;
                                
                                if (Array.isArray(value) && value.length > 0) {
                                    const firstItem = value[0];
                                    if (firstItem && (firstItem.lat || firstItem.lng || 
                                                    (firstItem.position && typeof firstItem.position === 'object'))) {
                                        console.log('  📋 Массив с координатами:', currentPath, 'элементов:', value.length);
                                        
                                        // Показываем первые несколько элементов
                                        value.slice(0, 3).forEach((item, idx) => {
                                            let lat, lng;
                                            if (item.lat && item.lng) {
                                                lat = item.lat;
                                                lng = item.lng;
                                            } else if (item.position && item.position.lat && item.position.lng) {
                                                lat = item.position.lat();
                                                lng = item.position.lng();
                                            }
                                            
                                            if (lat && lng) {
                                                console.log('    [' + idx + ']', 'lat:', lat, 'lng:', lng, 'id:', item.id || item.estate_id || 'нет');
                                            }
                                        });
                                    }
                                } else if (typeof value === 'object' && value !== null) {
                                    findArraysWithCoords(value, currentPath, depth + 1);
                                }
                            }
                        } catch (e) {
                            // Игнорируем ошибки доступа
                        }
                    }
                    
                    findArraysWithCoords(mapObj);
                }
            }
        }
        
        // Основная функция обработки
        function processUltimateDebug() {
            processAttempts++;
            console.log('🔄 Попытка диагностики #' + processAttempts);
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processUltimateDebug, 2000);
                }
                return;
            }
            
            console.log('📊 === ДАННЫЕ С СЕРВЕРА ===');
            console.log('Всего estate объявлений:', debugData.total_estates);
            console.log('DA объявлений:', debugData.da_estates.length);
            console.log('Источники координат:', debugData.coordinate_sources);
            
            console.log('📋 === ОБРАЗЦЫ ESTATE ===');
            debugData.all_estates_sample.forEach(estate => {
                console.log('Estate #' + estate.id + ' (' + estate.title + ')');
                console.log('  DA:', estate.is_da);
                console.log('  Мета поля:', estate.meta_fields);
            });
            
            console.log('🎯 === DA ОБЪЯВЛЕНИЯ ===');
            debugData.da_estates.forEach(estate => {
                console.log('DA Estate #' + estate.id + ' (' + estate.title + ')');
                console.log('  Найдено источников координат:', estate.coords_count);
                console.log('  Координаты:', estate.coordinates);
            });
            
            // Анализируем маркеры и карту
            analyzeMarkers();
            analyzeMapObjects();
            
            // Пытаемся связать координаты с маркерами
            console.log('🔗 === ПОПЫТКА СВЯЗАТЬ КООРДИНАТЫ С МАРКЕРАМИ ===');
            
            let foundMatches = 0;
            $markers.removeClass('da-ultimate-found da-ultimate-demo');
            
            debugData.da_estates.forEach(daEstate => {
                Object.keys(daEstate.coordinates).forEach(source => {
                    const coords = daEstate.coordinates[source];
                    console.log('🔍 Ищем маркер для DA #' + daEstate.id + ' (' + source + '):', coords.lat, coords.lng);
                    
                    // Метод 1: Поиск в HTML контенте
                    $markers.each(function(index) {
                        const $marker = $(this);
                        let $parent = $marker;
                        
                        for (let i = 0; i < 5; i++) {
                            $parent = $parent.parent();
                            if ($parent.length === 0) break;
                            
                            const html = $parent.html() || '';
                            const latStr = coords.lat.toString();
                            const lngStr = coords.lng.toString();
                            
                            // Проверяем точное совпадение или частичное (первые 8 символов)
                            if ((html.includes(latStr) && html.includes(lngStr)) ||
                                (html.includes(latStr.substring(0, 8)) && html.includes(lngStr.substring(0, 8)))) {
                                
                                if (!$marker.hasClass('da-ultimate-found')) {
                                    $marker.addClass('da-ultimate-found');
                                    foundMatches++;
                                    console.log('✅ НАЙДЕН! Маркер #' + index + ' для DA #' + daEstate.id + ' (метод HTML)');
                                }
                                return false; // break
                            }
                        }
                    });
                    
                    // Метод 2: Поиск через глобальные объекты карты
                    for (let globalVar in window) {
                        if (globalVar.startsWith('MyHomeMapListing')) {
                            const mapObj = window[globalVar];
                            
                            function searchInMapObj(obj, path = '') {
                                try {
                                    for (let key in obj) {
                                        const value = obj[key];
                                        
                                        if (Array.isArray(value)) {
                                            value.forEach((item, idx) => {
                                                let itemLat, itemLng;
                                                
                                                if (item && item.lat && item.lng) {
                                                    itemLat = parseFloat(item.lat);
                                                    itemLng = parseFloat(item.lng);
                                                } else if (item && item.position && item.position.lat && item.position.lng) {
                                                    itemLat = item.position.lat();
                                                    itemLng = item.position.lng();
                                                }
                                                
                                                if (itemLat && itemLng) {
                                                    const latDiff = Math.abs(itemLat - coords.lat);
                                                    const lngDiff = Math.abs(itemLng - coords.lng);
                                                    
                                                    if (latDiff < 0.0001 && lngDiff < 0.0001) {
                                                        const $targetMarker = $markers.eq(idx);
                                                        if ($targetMarker.length && !$targetMarker.hasClass('da-ultimate-found')) {
                                                            $targetMarker.addClass('da-ultimate-found');
                                                            foundMatches++;
                                                            console.log('✅ НАЙДЕН! Маркер #' + idx + ' для DA #' + daEstate.id + ' (метод карта)');
                                                            console.log('   Путь:', path + '.' + key + '[' + idx + ']');
                                                            console.log('   Координаты совпали:', itemLat, itemLng);
                                                        }
                                                    }
                                                }
                                            });
                                        } else if (typeof value === 'object' && value !== null && path.split('.').length < 3) {
                                            searchInMapObj(value, path + '.' + key);
                                        }
                                    }
                                } catch (e) {
                                    // Игнорируем ошибки
                                }
                            }
                            
                            searchInMapObj(mapObj, globalVar);
                        }
                    }
                });
            });
            
            // Демо режим если ничего не найдено
            if (foundMatches === 0) {
                console.log('🟡 Демо режим - связь не установлена');
                $markers.slice(0, 1).addClass('da-ultimate-demo');
            }
            
            // Финальная статистика
            setTimeout(() => {
                const finalFound = $('.mh-map-pin.da-ultimate-found').length;
                const finalDemo = $('.mh-map-pin.da-ultimate-demo').length;
                
                console.log('🏁 === ИТОГОВАЯ СТАТИСТИКА ===');
                console.log('🔴 Найденных маркеров:', finalFound);
                console.log('🟢 Демо маркеров:', finalDemo);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('🎯 DA объявлений в базе:', debugData.da_estates.length);
                
                if (finalFound > 0) {
                    console.log('🎉 УСПЕХ! Связь установлена!');
                } else {
                    console.log('❌ ПРОБЛЕМА: Не удалось связать координаты с маркерами');
                    console.log('💡 ВОЗМОЖНЫЕ ПРИЧИНЫ:');
                    console.log('   1. Координаты не точно совпадают');
                    console.log('   2. Маркеры рендерятся по-другому');
                    console.log('   3. Координаты хранятся в другом формате');
                    console.log('   4. MarkerClusterer изменяет структуру DOM');
                }
            }, 1000);
        }
        
        // Запуск
        fetchDebugData().done(function(response) {
            if (response.success) {
                debugData = response.data;
                console.log('📡 Получены отладочные данные');
                
                setTimeout(processUltimateDebug, 3000);
                
            } else {
                console.log('❌ Ошибка получения данных:', response);
            }
        }).fail(function(xhr, status, error) {
            console.log('❌ AJAX ошибка:', error);
        });
    });
    </script>
    <?php
}
?>
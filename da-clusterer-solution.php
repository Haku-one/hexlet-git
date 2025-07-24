<?php
/**
 * DA Markers - РАБОТА С MARKERCLUSTERER
 * Решение для карт с MarkerClusterer
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
    echo '<p><small>Работает с MarkerClusterer</small></p>';
    
    // Извлекаем координаты из estate_location
    $location_data = get_post_meta($post->ID, 'estate_location', true);
    $lat = null;
    $lng = null;
    
    if (is_array($location_data)) {
        $lat = isset($location_data['lat']) ? $location_data['lat'] : null;
        $lng = isset($location_data['lng']) ? $location_data['lng'] : null;
    }
    
    echo '<hr><h4>Информация для отладки:</h4>';
    echo '<p><strong>ID:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Галочка:</strong> ' . ($value ? 'Включено' : 'Выключено') . '</p>';
    if ($lat && $lng) {
        echo '<p><strong>Координаты:</strong> ' . $lat . ', ' . $lng . '</p>';
        echo '<p style="color: green;">✅ Координаты найдены</p>';
    } else {
        echo '<p style="color: red;">❌ Координаты не найдены</p>';
        echo '<p><small>estate_location: ' . print_r($location_data, true) . '</small></p>';
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

// AJAX для получения DA координат
add_action('wp_ajax_get_da_clusterer_data', 'ajax_get_da_clusterer_data');
add_action('wp_ajax_nopriv_get_da_clusterer_data', 'ajax_get_da_clusterer_data');
function ajax_get_da_clusterer_data() {
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
    
    foreach ($da_posts as $post) {
        $location_data = get_post_meta($post->ID, 'estate_location', true);
        
        if (is_array($location_data) && isset($location_data['lat']) && isset($location_data['lng'])) {
            $da_data[] = array(
                'id' => $post->ID,
                'lat' => floatval($location_data['lat']),
                'lng' => floatval($location_data['lng']),
                'title' => $post->post_title,
                // Добавляем дополнительные поля для поиска
                'address' => get_post_meta($post->ID, 'estate_address', true),
                'slug' => $post->post_name
            );
        }
    }
    
    wp_send_json_success(array(
        'da_properties' => $da_data,
        'count' => count($da_data),
        'timestamp' => current_time('timestamp')
    ));
}

// CSS для мигания
add_action('wp_head', 'da_clusterer_css');
function da_clusterer_css() {
    ?>
    <style>
    @keyframes da-clusterer-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066) drop-shadow(0 0 20px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 15px #ff0066) drop-shadow(0 0 30px #ff0066);
            transform: scale(1.1);
        }
    }

    /* Для обычных маркеров */
    .mh-map-pin.da-clusterer-active {
        animation: da-clusterer-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-clusterer-active i {
        color: #ff0066 !important;
    }

    /* Для кластеров */
    .cluster-marker.da-clusterer-active {
        animation: da-clusterer-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    /* Демо режим */
    .mh-map-pin.da-demo {
        animation: da-clusterer-blink 1.5s infinite;
    }

    .mh-map-pin.da-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript - работа с MarkerClusterer
add_action('wp_footer', 'da_clusterer_script');
function da_clusterer_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🔥 DA Маркеры - CLUSTERER РЕШЕНИЕ запущено');
        
        let daData = [];
        let processAttempts = 0;
        const maxAttempts = 5;
        
        // Получаем DA данные с сервера
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_clusterer_data'
                }
            });
        }
        
        // Поиск маркеров через MarkerClusterer
        function findMarkersInClusterer() {
            console.log('🔍 Поиск в MarkerClusterer объектах...');
            
            for (let globalVar in window) {
                if (globalVar.startsWith('MyHomeMapListing')) {
                    const mapObj = window[globalVar];
                    console.log('🗺️ Анализируем:', globalVar, mapObj);
                    
                    // Ищем MarkerClusterer
                    if (mapObj && typeof mapObj === 'object') {
                        // Рекурсивный поиск кластера
                        function findClustererRecursive(obj, path = '') {
                            for (let key in obj) {
                                try {
                                    const value = obj[key];
                                    
                                    // Ищем MarkerClusterer или похожий объект
                                    if (value && typeof value === 'object') {
                                        // Проверяем на наличие маркеров
                                        if (value.markers && Array.isArray(value.markers)) {
                                            console.log('📍 Найден массив маркеров:', path + '.' + key, 'количество:', value.markers.length);
                                            processMarkersArray(value.markers, path + '.' + key);
                                        }
                                        
                                        // Ищем другие массивы с координатами
                                        if (Array.isArray(value) && value.length > 0) {
                                            const firstItem = value[0];
                                            if (firstItem && (firstItem.lat || firstItem.lng || firstItem.position)) {
                                                console.log('📍 Массив с координатами:', path + '.' + key, 'элементов:', value.length);
                                                processCoordinatesArray(value, path + '.' + key);
                                            }
                                        }
                                        
                                        // Продолжаем поиск вглубь
                                        if (path.split('.').length < 3) { // Ограничиваем глубину
                                            findClustererRecursive(value, path + '.' + key);
                                        }
                                    }
                                } catch (e) {
                                    // Игнорируем ошибки доступа
                                }
                            }
                        }
                        
                        findClustererRecursive(mapObj, globalVar);
                    }
                }
            }
        }
        
        // Обработка массива маркеров
        function processMarkersArray(markers, source) {
            console.log('🎯 Обработка массива маркеров из:', source);
            
            markers.forEach((marker, index) => {
                try {
                    let markerLat, markerLng, markerId;
                    
                    // Извлекаем координаты из разных форматов
                    if (marker.position) {
                        markerLat = marker.position.lat();
                        markerLng = marker.position.lng();
                    } else if (marker.lat && marker.lng) {
                        markerLat = parseFloat(marker.lat);
                        markerLng = parseFloat(marker.lng);
                    } else if (marker.getPosition) {
                        const pos = marker.getPosition();
                        markerLat = pos.lat();
                        markerLng = pos.lng();
                    }
                    
                    // Извлекаем ID
                    markerId = marker.id || marker.estate_id || marker.property_id;
                    
                    if (markerLat && markerLng) {
                        // Сравниваем с DA координатами
                        daData.forEach(daProperty => {
                            const latDiff = Math.abs(markerLat - daProperty.lat);
                            const lngDiff = Math.abs(markerLng - daProperty.lng);
                            
                            if (latDiff < 0.0001 && lngDiff < 0.0001) {
                                console.log('🎯 СОВПАДЕНИЕ НАЙДЕНО!');
                                console.log('📍 DA Property:', daProperty);
                                console.log('📍 Marker:', {lat: markerLat, lng: markerLng, index: index, id: markerId});
                                
                                // Пытаемся найти DOM элемент маркера
                                activateMarkerInDOM(index, markerLat, markerLng, daProperty.id);
                            }
                        });
                    }
                } catch (e) {
                    console.log('⚠️ Ошибка обработки маркера:', e);
                }
            });
        }
        
        // Обработка массива координат
        function processCoordinatesArray(coords, source) {
            console.log('📍 Обработка координат из:', source);
            
            coords.forEach((item, index) => {
                try {
                    let lat, lng, id;
                    
                    if (item.lat && item.lng) {
                        lat = parseFloat(item.lat);
                        lng = parseFloat(item.lng);
                        id = item.id || item.estate_id;
                    }
                    
                    if (lat && lng) {
                        daData.forEach(daProperty => {
                            if (Math.abs(lat - daProperty.lat) < 0.0001 && 
                                Math.abs(lng - daProperty.lng) < 0.0001) {
                                console.log('🎯 КООРДИНАТЫ СОВПАЛИ!', daProperty.id, 'позиция:', index);
                                activateMarkerInDOM(index, lat, lng, daProperty.id);
                            }
                        });
                    }
                } catch (e) {
                    console.log('⚠️ Ошибка обработки координат:', e);
                }
            });
        }
        
        // Активация маркера в DOM
        function activateMarkerInDOM(markerIndex, lat, lng, propertyId) {
            console.log('🎨 Активация маркера в DOM:', {index: markerIndex, lat: lat, lng: lng, id: propertyId});
            
            let $markers = $('.mh-map-pin');
            let activated = false;
            
            // Метод 1: По индексу
            if ($markers.eq(markerIndex).length) {
                $markers.eq(markerIndex).addClass('da-clusterer-active');
                console.log('✅ Активирован по индексу:', markerIndex);
                activated = true;
            }
            
            // Метод 2: Поиск по атрибутам
            if (!activated) {
                $markers.each(function(index) {
                    const $marker = $(this);
                    const $parent = $marker.closest('[style*="position"]');
                    
                    if ($parent.length) {
                        const style = $parent.attr('style') || '';
                        // Ищем координаты в стилях позиционирования
                        if (style.includes(lat.toString().substring(0, 8)) || 
                            style.includes(lng.toString().substring(0, 8))) {
                            $marker.addClass('da-clusterer-active');
                            console.log('✅ Активирован по стилям:', index);
                            activated = true;
                        }
                    }
                });
            }
            
            // Метод 3: Поиск по HTML содержимому
            if (!activated) {
                $markers.each(function(index) {
                    const $marker = $(this);
                    const $container = $marker.closest('[data-lat], [data-lng]');
                    
                    if ($container.length) {
                        const dataLat = parseFloat($container.attr('data-lat'));
                        const dataLng = parseFloat($container.attr('data-lng'));
                        
                        if (Math.abs(dataLat - lat) < 0.0001 && Math.abs(dataLng - lng) < 0.0001) {
                            $marker.addClass('da-clusterer-active');
                            console.log('✅ Активирован по data атрибутам:', index);
                            activated = true;
                        }
                    }
                });
            }
            
            return activated;
        }
        
        // Основная функция обработки
        function processDAMarkersWithClusterer() {
            processAttempts++;
            console.log('🔄 Попытка #' + processAttempts);
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processDAMarkersWithClusterer, 2000);
                }
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 DA объявлений для поиска:', daData.length);
            
            // Убираем предыдущие классы
            $markers.removeClass('da-clusterer-active da-demo');
            
            // Ищем маркеры в clusterer
            findMarkersInClusterer();
            
            // Проверяем результат через секунду
            setTimeout(() => {
                const activeMarkers = $('.mh-map-pin.da-clusterer-active').length;
                
                if (activeMarkers === 0 && daData.length > 0) {
                    console.log('🟡 Маркеры не найдены, пробуем прямой поиск по координатам...');
                    
                    // Прямой поиск по координатам в DOM
                    $markers.each(function(index) {
                        const $marker = $(this);
                        
                        // Ищем координаты в родительских элементах
                        let $parent = $marker;
                        for (let i = 0; i < 5; i++) {
                            $parent = $parent.parent();
                            if ($parent.length === 0) break;
                            
                            const html = $parent.html() || '';
                            
                            daData.forEach(daProperty => {
                                if (html.includes(daProperty.lat.toString()) && 
                                    html.includes(daProperty.lng.toString())) {
                                    $marker.addClass('da-clusterer-active');
                                    console.log('✅ Найден в HTML:', daProperty.id, 'индекс:', index);
                                }
                            });
                        }
                    });
                }
                
                // Финальная проверка
                setTimeout(() => {
                    const finalActive = $('.mh-map-pin.da-clusterer-active').length;
                    const finalDemo = $('.mh-map-pin.da-demo').length;
                    
                    if (finalActive === 0 && daData.length > 0) {
                        console.log('🟢 Демо режим - активируем первый маркер');
                        $markers.slice(0, 1).addClass('da-demo');
                    }
                    
                    console.log('🏁 === ИТОГОВАЯ СТАТИСТИКА ===');
                    console.log('🔴 Активных DA маркеров:', finalActive);
                    console.log('🟢 Демо маркеров:', finalDemo);
                    console.log('📍 Всего маркеров:', $markers.length);
                    console.log('🎯 DA объявлений в базе:', daData.length);
                    
                    if (finalActive > 0) {
                        console.log('🎉 УСПЕХ! DA маркеры найдены и активированы!');
                    }
                }, 1000);
                
            }, 1000);
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daData = response.data.da_properties;
                console.log('📡 Получены DA данные:', daData);
                
                // Запускаем обработку с задержкой
                setTimeout(processDAMarkersWithClusterer, 3000);
                
                // Мониторим изменения DOM
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
                            console.log('🔄 Новые маркеры обнаружены');
                            setTimeout(processDAMarkersWithClusterer, 1500);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
            } else {
                console.log('⚠️ Нет DA данных, демо режим');
                setTimeout(() => {
                    $('.mh-map-pin').slice(0, 1).addClass('da-demo');
                }, 3000);
            }
        }).fail(function() {
            console.log('❌ Ошибка загрузки DA данных');
        });
    });
    </script>
    <?php
}
?>
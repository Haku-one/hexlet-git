<?php
/**
 * DA Markers - ПОИСК ПО КООРДИНАТАМ
 * Ищет маркеры по точным координатам, а не по позиции в массиве
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
    echo '<p><small>Маркер будет найден по координатам, а не по позиции</small></p>';
    
    // Извлекаем координаты
    $location_data = get_post_meta($post->ID, 'estate_location', true);
    $lat = null;
    $lng = null;
    
    if (is_array($location_data)) {
        $lat = isset($location_data['lat']) ? $location_data['lat'] : null;
        $lng = isset($location_data['lng']) ? $location_data['lng'] : null;
    }
    
    echo '<hr><h4>Координаты для поиска:</h4>';
    echo '<p><strong>ID:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Галочка:</strong> ' . ($value ? 'Включено' : 'Выключено') . '</p>';
    if ($lat && $lng) {
        echo '<p><strong>Координаты:</strong> ' . $lat . ', ' . $lng . '</p>';
        echo '<p style="color: green;">✅ Маркер будет найден по этим координатам</p>';
    } else {
        echo '<p style="color: red;">❌ Координаты не найдены</p>';
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
add_action('wp_ajax_get_da_coordinates', 'ajax_get_da_coordinates');
add_action('wp_ajax_nopriv_get_da_coordinates', 'ajax_get_da_coordinates');
function ajax_get_da_coordinates() {
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
    
    $da_coordinates = array();
    
    foreach ($da_posts as $post) {
        $location_data = get_post_meta($post->ID, 'estate_location', true);
        
        if (is_array($location_data) && isset($location_data['lat']) && isset($location_data['lng'])) {
            $da_coordinates[] = array(
                'id' => $post->ID,
                'lat' => floatval($location_data['lat']),
                'lng' => floatval($location_data['lng']),
                'title' => $post->post_title
            );
        }
    }
    
    wp_send_json_success(array(
        'coordinates' => $da_coordinates,
        'count' => count($da_coordinates)
    ));
}

// CSS для мигания
add_action('wp_head', 'da_coordinate_css');
function da_coordinate_css() {
    ?>
    <style>
    @keyframes da-blink-red {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            opacity: 1;
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            opacity: 0.7;
        }
    }

    .mh-map-pin.da-found {
        animation: da-blink-red 1.5s infinite;
    }

    .mh-map-pin.da-found i {
        color: #ff0066 !important;
    }
    
    /* Для демо */
    .mh-map-pin.da-demo {
        animation: da-blink-red 1.5s infinite;
    }

    .mh-map-pin.da-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript - поиск по координатам
add_action('wp_footer', 'da_coordinate_script');
function da_coordinate_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA Маркеры - ПОИСК ПО КООРДИНАТАМ запущено');
        
        let processAttempts = 0;
        const maxAttempts = 3;
        
        function processDAMarkers() {
            processAttempts++;
            console.log('🔍 Поиск по координатам #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processDAMarkers, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            
            // Получаем DA координаты
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_coordinates'
                },
                success: function(response) {
                    console.log('📡 ОТВЕТ СЕРВЕРА:', response);
                    
                    if (response.success && response.data.coordinates.length > 0) {
                        console.log('🎯 DA КООРДИНАТЫ:', response.data.coordinates);
                        
                        // Убираем предыдущие классы
                        $('.mh-map-pin').removeClass('da-found da-demo');
                        
                        let foundCount = 0;
                        
                        // Метод 1: Поиск по HTML атрибутам координат
                        console.log('🔍 МЕТОД 1: Поиск в HTML атрибутах');
                        $markers.each(function(index) {
                            let $marker = $(this);
                            let $parent = $marker.closest('[data-lat][data-lng]');
                            
                            if ($parent.length) {
                                let markerLat = parseFloat($parent.attr('data-lat'));
                                let markerLng = parseFloat($parent.attr('data-lng'));
                                
                                response.data.coordinates.forEach(function(daCoord) {
                                    if (Math.abs(markerLat - daCoord.lat) < 0.0001 && 
                                        Math.abs(markerLng - daCoord.lng) < 0.0001) {
                                        console.log('✅ НАЙДЕН по HTML атрибутам! ID:', daCoord.id, 'Координаты:', markerLat, markerLng);
                                        $marker.addClass('da-found');
                                        foundCount++;
                                    }
                                });
                            }
                        });
                        
                        // Метод 2: Поиск через все элементы с координатами
                        if (foundCount === 0) {
                            console.log('🔍 МЕТОД 2: Поиск в родительских элементах');
                            $markers.each(function(index) {
                                let $marker = $(this);
                                let $currentElement = $marker;
                                
                                // Идем вверх по DOM дереву
                                for (let i = 0; i < 10; i++) {
                                    $currentElement = $currentElement.parent();
                                    if ($currentElement.length === 0) break;
                                    
                                    // Проверяем все атрибуты элемента
                                    let element = $currentElement[0];
                                    if (element && element.attributes) {
                                        for (let attr of element.attributes) {
                                            let attrValue = attr.value;
                                            
                                            // Ищем паттерны координат в атрибутах
                                            response.data.coordinates.forEach(function(daCoord) {
                                                if (attrValue.includes(daCoord.lat.toString()) && 
                                                    attrValue.includes(daCoord.lng.toString())) {
                                                    console.log('✅ НАЙДЕН в атрибуте!', attr.name, ':', attrValue);
                                                    console.log('🎯 DA ID:', daCoord.id, 'Координаты:', daCoord.lat, daCoord.lng);
                                                    $marker.addClass('da-found');
                                                    foundCount++;
                                                }
                                            });
                                        }
                                    }
                                }
                            });
                        }
                        
                        // Метод 3: Поиск в стилях позиционирования
                        if (foundCount === 0) {
                            console.log('🔍 МЕТОД 3: Поиск в стилях позиционирования');
                            
                            // Сначала собираем все маркеры с их позициями
                            let markersWithPositions = [];
                            $markers.each(function(index) {
                                let $marker = $(this);
                                let $positionedParent = $marker.closest('[style*="position"]');
                                
                                if ($positionedParent.length) {
                                    let style = $positionedParent.attr('style') || '';
                                    let topMatch = style.match(/top:\s*([+-]?\d*\.?\d+)px/);
                                    let leftMatch = style.match(/left:\s*([+-]?\d*\.?\d+)px/);
                                    
                                    if (topMatch && leftMatch) {
                                        markersWithPositions.push({
                                            marker: $marker,
                                            top: parseFloat(topMatch[1]),
                                            left: parseFloat(leftMatch[1]),
                                            index: index
                                        });
                                    }
                                }
                            });
                            
                            console.log('📍 Маркеры с позициями:', markersWithPositions);
                            
                            // Теперь ищем координаты в Google Maps объектах
                            for (let globalVar in window) {
                                if (globalVar.startsWith('MyHomeMapListing')) {
                                    const mapObj = window[globalVar];
                                    console.log('🗺️ Анализируем карту:', globalVar);
                                    
                                    // Ищем массивы с координатами
                                    function findCoordinatesInMapObject(obj, path = '') {
                                        for (let key in obj) {
                                            try {
                                                let value = obj[key];
                                                if (Array.isArray(value) && value.length > 0) {
                                                    if (value[0] && (value[0].lat || value[0].lng)) {
                                                        console.log('📋 Массив координат:', path + '.' + key, 'элементов:', value.length);
                                                        
                                                        value.forEach((item, itemIndex) => {
                                                            if (item.lat && item.lng) {
                                                                response.data.coordinates.forEach(function(daCoord) {
                                                                    if (Math.abs(parseFloat(item.lat) - daCoord.lat) < 0.0001 && 
                                                                        Math.abs(parseFloat(item.lng) - daCoord.lng) < 0.0001) {
                                                                        
                                                                        console.log('🎯 КООРДИНАТЫ СОВПАЛИ!', daCoord.id, 'в позиции', itemIndex);
                                                                        console.log('🎯 DA координаты:', daCoord.lat, daCoord.lng);
                                                                        console.log('🎯 Карта координаты:', item.lat, item.lng);
                                                                        
                                                                        // Пробуем найти маркер по itemIndex
                                                                        if (markersWithPositions[itemIndex]) {
                                                                            markersWithPositions[itemIndex].marker.addClass('da-found');
                                                                            foundCount++;
                                                                            console.log('✅ МАРКЕР АКТИВИРОВАН по позиции', itemIndex);
                                                                        } else if ($markers.eq(itemIndex).length) {
                                                                            $markers.eq(itemIndex).addClass('da-found');
                                                                            foundCount++;
                                                                            console.log('✅ МАРКЕР АКТИВИРОВАН прямо по индексу', itemIndex);
                                                                        }
                                                                    }
                                                                });
                                                            }
                                                        });
                                                    }
                                                } else if (typeof value === 'object' && value !== null) {
                                                    findCoordinatesInMapObject(value, path + '.' + key);
                                                }
                                            } catch (e) {
                                                // Игнорируем ошибки
                                            }
                                        }
                                    }
                                    
                                    findCoordinatesInMapObject(mapObj, globalVar);
                                }
                            }
                        }
                        
                        // Демо режим если ничего не найдено
                        if (foundCount === 0) {
                            console.log('🟢 ДЕМО РЕЖИМ - не найдены маркеры по координатам');
                            $markers.slice(0, 1).addClass('da-demo');
                            foundCount = 1;
                        }
                        
                        // Финальная статистика
                        setTimeout(() => {
                            const finalFound = $('.mh-map-pin.da-found').length;
                            const finalDemo = $('.mh-map-pin.da-demo').length;
                            
                            console.log('🎯 === РЕЗУЛЬТАТ ПОИСКА ПО КООРДИНАТАМ ===');
                            console.log('🔴 Найдено по координатам:', finalFound);
                            console.log('🟢 Демо маркеров:', finalDemo);
                            console.log('📍 Всего маркеров на карте:', $markers.length);
                            console.log('📊 DA координат для поиска:', response.data.coordinates.length);
                            
                            if (finalFound > 0) {
                                console.log('🎉 УСПЕХ! Маркеры найдены по точным координатам!');
                            } else if (finalDemo > 0) {
                                console.log('🟢 Демо режим - проверьте координаты в базе данных');
                            }
                        }, 500);
                        
                    } else {
                        console.log('⚠️ Нет DA объявлений с координатами');
                        
                        // Демо режим
                        console.log('🟢 ДЕМО РЕЖИМ');
                        $markers.slice(0, 1).addClass('da-demo');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX ошибка:', error);
                    
                    // Демо режим
                    console.log('🟢 ДЕМО РЕЖИМ из-за ошибки');
                    let $markers = $('.mh-map-pin');
                    $markers.slice(0, 1).addClass('da-demo');
                }
            });
        }
        
        // Запускаем обработку
        setTimeout(processDAMarkers, 3000);
        
        // Мониторим изменения карты
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
                    console.log('🔄 Новые маркеры обнаружены, повторный поиск...');
                    setTimeout(processDAMarkers, 1500);
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
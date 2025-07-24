<?php
/**
 * DA Markers - ФИНАЛЬНОЕ РЕШЕНИЕ
 * Поиск по ID объявления в results.estates
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
    echo '<p><small>Поиск по ID в results.estates</small></p>';
    
    // Показываем информацию о координатах
    $location_data = get_post_meta($post->ID, 'estate_location', true);
    $lat = null;
    $lng = null;
    
    if (is_array($location_data) && isset($location_data['lat']) && isset($location_data['lng'])) {
        $lat = $location_data['lat'];
        $lng = $location_data['lng'];
    }
    
    echo '<hr><h4>Информация:</h4>';
    echo '<p><strong>ID объявления:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Статус DA:</strong> ' . ($value ? '🟢 Включено' : '⚪ Выключено') . '</p>';
    if ($lat && $lng) {
        echo '<p><strong>Координаты:</strong> ' . $lat . ', ' . $lng . '</p>';
        echo '<p style="color: green;">✅ Готово к поиску на карте</p>';
    } else {
        echo '<p style="color: orange;">⚠️ Координаты не найдены в estate_location</p>';
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

// AJAX для получения DA объявлений
add_action('wp_ajax_get_da_final_data', 'ajax_get_da_final_data');
add_action('wp_ajax_nopriv_get_da_final_data', 'ajax_get_da_final_data');
function ajax_get_da_final_data() {
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
                'id' => intval($post->ID),
                'lat' => floatval($location_data['lat']),
                'lng' => floatval($location_data['lng']),
                'title' => $post->post_title
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
add_action('wp_head', 'da_final_css');
function da_final_css() {
    ?>
    <style>
    @keyframes da-final-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066) drop-shadow(0 0 20px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 15px #ff0066) drop-shadow(0 0 30px #ff0066);
            transform: scale(1.15);
        }
    }

    .mh-map-pin.da-final-active {
        animation: da-final-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-final-active i {
        color: #ff0066 !important;
    }

    .mh-map-pin.da-final-demo {
        animation: da-final-blink 1.5s infinite;
    }

    .mh-map-pin.da-final-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript - поиск по ID в results.estates
add_action('wp_footer', 'da_final_script');
function da_final_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA ФИНАЛЬНОЕ РЕШЕНИЕ - поиск по ID запущено');
        
        let daProperties = [];
        let processAttempts = 0;
        const maxAttempts = 5;
        
        // Получаем DA данные с сервера
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_final_data'
                }
            });
        }
        
        // Основная функция поиска
        function findDAMarkers() {
            processAttempts++;
            console.log('🔄 Попытка поиска #' + processAttempts);
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(findDAMarkers, 2000);
                }
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 DA объявлений для поиска:', daProperties.length);
            
            // Убираем предыдущие классы
            $markers.removeClass('da-final-active da-final-demo');
            
            let foundMarkers = 0;
            
            // Ищем глобальный объект карты
            for (let globalVar in window) {
                if (globalVar.startsWith('MyHomeMapListing')) {
                    const mapObj = window[globalVar];
                    console.log('🗺️ Анализируем объект карты:', globalVar);
                    
                    // Ищем массив results.estates
                    if (mapObj && mapObj.results && mapObj.results.estates && Array.isArray(mapObj.results.estates)) {
                        const estates = mapObj.results.estates;
                        console.log('🏠 Найден массив estates:', estates.length, 'элементов');
                        
                        // Создаем карту ID -> позиция в массиве estates
                        const estateIdToIndex = {};
                        estates.forEach((estate, index) => {
                            if (estate.id) {
                                estateIdToIndex[estate.id] = index;
                            }
                        });
                        
                        console.log('📋 Карта ID -> индекс создана');
                        
                        // Ищем наши DA объявления
                        daProperties.forEach(daProperty => {
                            console.log('🔍 Ищем DA объявление ID:', daProperty.id, 'координаты:', daProperty.lat, daProperty.lng);
                            
                            // Проверяем, есть ли это ID в массиве estates
                            if (estateIdToIndex.hasOwnProperty(daProperty.id)) {
                                const estateIndex = estateIdToIndex[daProperty.id];
                                const estateData = estates[estateIndex];
                                
                                console.log('✅ Найдено в estates[' + estateIndex + ']:', estateData);
                                
                                // Теперь пытаемся найти соответствующий маркер
                                // Методы поиска маркера:
                                
                                // Метод 1: По индексу (если маркеры идут в том же порядке)
                                const $markerByIndex = $markers.eq(estateIndex);
                                if ($markerByIndex.length && !$markerByIndex.hasClass('da-final-active')) {
                                    $markerByIndex.addClass('da-final-active');
                                    foundMarkers++;
                                    console.log('✅ НАЙДЕН ПО ИНДЕКСУ! Маркер #' + estateIndex + ' для DA #' + daProperty.id);
                                    return; // Переходим к следующему DA объявлению
                                }
                                
                                // Метод 2: Поиск по координатам в HTML
                                let found = false;
                                $markers.each(function(markerIndex) {
                                    if (found) return;
                                    
                                    const $marker = $(this);
                                    let $parent = $marker;
                                    
                                    // Ищем координаты в родительских элементах
                                    for (let i = 0; i < 5 && !found; i++) {
                                        $parent = $parent.parent();
                                        if ($parent.length === 0) break;
                                        
                                        const html = $parent.html() || '';
                                        const latStr = daProperty.lat.toString();
                                        const lngStr = daProperty.lng.toString();
                                        
                                        // Ищем точные или частичные совпадения координат
                                        if ((html.includes(latStr) && html.includes(lngStr)) ||
                                            (html.includes(latStr.substring(0, 8)) && html.includes(lngStr.substring(0, 8)))) {
                                            
                                            if (!$marker.hasClass('da-final-active')) {
                                                $marker.addClass('da-final-active');
                                                foundMarkers++;
                                                console.log('✅ НАЙДЕН ПО КООРДИНАТАМ! Маркер #' + markerIndex + ' для DA #' + daProperty.id);
                                                found = true;
                                            }
                                        }
                                    }
                                });
                                
                                // Метод 3: Поиск по ID в HTML/атрибутах
                                if (!found) {
                                    $markers.each(function(markerIndex) {
                                        if (found) return;
                                        
                                        const $marker = $(this);
                                        let $parent = $marker;
                                        
                                        for (let i = 0; i < 5 && !found; i++) {
                                            $parent = $parent.parent();
                                            if ($parent.length === 0) break;
                                            
                                            const html = $parent.html() || '';
                                            const idStr = daProperty.id.toString();
                                            
                                            // Ищем ID объявления в HTML
                                            if (html.includes('estate-' + idStr) || 
                                                html.includes('property-' + idStr) ||
                                                html.includes('listing-' + idStr) ||
                                                html.includes('"' + idStr + '"')) {
                                                
                                                if (!$marker.hasClass('da-final-active')) {
                                                    $marker.addClass('da-final-active');
                                                    foundMarkers++;
                                                    console.log('✅ НАЙДЕН ПО ID! Маркер #' + markerIndex + ' для DA #' + daProperty.id);
                                                    found = true;
                                                }
                                            }
                                            
                                            // Проверяем атрибуты
                                            const attrs = $parent[0] ? $parent[0].attributes : null;
                                            if (attrs) {
                                                Array.from(attrs).forEach(attr => {
                                                    if (attr.value && attr.value.includes(idStr)) {
                                                        if (!$marker.hasClass('da-final-active')) {
                                                            $marker.addClass('da-final-active');
                                                            foundMarkers++;
                                                            console.log('✅ НАЙДЕН ПО АТРИБУТУ! Маркер #' + markerIndex + ' для DA #' + daProperty.id);
                                                            console.log('   Атрибут:', attr.name, '=', attr.value);
                                                            found = true;
                                                        }
                                                    }
                                                });
                                            }
                                        }
                                    });
                                }
                                
                            } else {
                                console.log('⚠️ DA объявление ID ' + daProperty.id + ' НЕ НАЙДЕНО в массиве estates');
                            }
                        });
                        
                        break; // Прекращаем поиск глобальных объектов
                    }
                }
            }
            
            // Демо режим если ничего не найдено
            if (foundMarkers === 0) {
                console.log('🟡 Демо режим - активируем первый маркер');
                $markers.slice(0, 1).addClass('da-final-demo');
                foundMarkers = 1;
            }
            
            // Финальная статистика
            setTimeout(() => {
                const finalActive = $('.mh-map-pin.da-final-active').length;
                const finalDemo = $('.mh-map-pin.da-final-demo').length;
                
                console.log('🏁 === ИТОГОВАЯ СТАТИСТИКА ===');
                console.log('🔴 Найденных DA маркеров:', finalActive);
                console.log('🟢 Демо маркеров:', finalDemo);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('🎯 DA объявлений в базе:', daProperties.length);
                
                if (finalActive > 0) {
                    console.log('🎉 УСПЕХ! DA маркеры найдены и активированы!');
                } else if (daProperties.length > 0) {
                    console.log('❌ ПРОБЛЕМА: DA объявления есть, но маркеры не найдены');
                    console.log('💡 Возможные причины:');
                    console.log('   1. ID не совпадает с estates массивом');
                    console.log('   2. Координаты не найдены в DOM');
                    console.log('   3. Структура маркеров отличается');
                } else {
                    console.log('ℹ️ Нет DA объявлений для поиска');
                }
            }, 500);
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления:', daProperties);
                
                // Запускаем поиск с задержкой
                setTimeout(findDAMarkers, 3000);
                
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
                        
                        if (hasNewMarkers && daProperties.length > 0) {
                            console.log('🔄 Новые маркеры обнаружены, повторный поиск...');
                            setTimeout(findDAMarkers, 1500);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
            } else {
                console.log('⚠️ Нет DA объявлений в базе');
                setTimeout(() => {
                    $('.mh-map-pin').slice(0, 1).addClass('da-final-demo');
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
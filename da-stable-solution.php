<?php
/**
 * DA Markers - СТАБИЛЬНОЕ РЕШЕНИЕ
 * Поиск маркеров по HTML содержимому, а не по позиции
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
    echo '<p><small>Поиск по HTML содержимому</small></p>';
    
    // Показываем информацию
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
        echo '<p style="color: green;">✅ Готово к поиску</p>';
    } else {
        echo '<p style="color: orange;">⚠️ Координаты не найдены</p>';
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
add_action('wp_ajax_get_da_stable_data', 'ajax_get_da_stable_data');
add_action('wp_ajax_nopriv_get_da_stable_data', 'ajax_get_da_stable_data');
function ajax_get_da_stable_data() {
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
        'count' => count($da_data)
    ));
}

// CSS для мигания
add_action('wp_head', 'da_stable_css');
function da_stable_css() {
    ?>
    <style>
    @keyframes da-stable-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066) drop-shadow(0 0 20px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 15px #ff0066) drop-shadow(0 0 30px #ff0066);
            transform: scale(1.15);
        }
    }

    .mh-map-pin.da-stable-active {
        animation: da-stable-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-stable-active i {
        color: #ff0066 !important;
    }

    .mh-map-pin.da-stable-demo {
        animation: da-stable-blink 1.5s infinite;
    }

    .mh-map-pin.da-stable-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript - стабильный поиск по содержимому
add_action('wp_footer', 'da_stable_script');
function da_stable_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🔒 DA СТАБИЛЬНОЕ РЕШЕНИЕ - поиск по содержимому');
        
        let daProperties = [];
        let stableMarkers = new Map(); // Запоминаем найденные маркеры
        let searchAttempts = 0;
        
        // Получаем DA данные
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_stable_data'
                }
            });
        }
        
        // Функция поиска маркеров по содержимому
        function findMarkersStable() {
            searchAttempts++;
            console.log('🔍 Стабильный поиск #' + searchAttempts);
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (searchAttempts < 10) {
                    setTimeout(findMarkersStable, 1000);
                }
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 DA объявлений:', daProperties.length);
            
            // Убираем старые классы
            $markers.removeClass('da-stable-active da-stable-demo');
            
            let foundCount = 0;
            
            // Для каждого DA объявления ищем маркер
            daProperties.forEach(daProperty => {
                const searchPatterns = [
                    daProperty.id.toString(),
                    'estate-' + daProperty.id,
                    'property-' + daProperty.id,
                    'listing-' + daProperty.id,
                    '"id":' + daProperty.id,
                    '"' + daProperty.id + '"',
                    'data-id="' + daProperty.id + '"',
                    'id="' + daProperty.id + '"'
                ];
                
                const coordPatterns = [
                    daProperty.lat.toString(),
                    daProperty.lng.toString(),
                    daProperty.lat.toFixed(6),
                    daProperty.lng.toFixed(6),
                    daProperty.lat.toFixed(4),
                    daProperty.lng.toFixed(4)
                ];
                
                console.log('🔍 Ищем DA #' + daProperty.id + ' (' + daProperty.title + ')');
                console.log('   Паттерны ID:', searchPatterns);
                console.log('   Координаты:', coordPatterns);
                
                let found = false;
                
                // Проходим по всем маркерам
                $markers.each(function(index) {
                    if (found) return;
                    
                    const $marker = $(this);
                    
                    // Поиск в HTML содержимом всех родительских элементов
                    let $searchElement = $marker;
                    for (let level = 0; level < 8 && !found; level++) {
                        $searchElement = $searchElement.parent();
                        if ($searchElement.length === 0) break;
                        
                        const html = $searchElement.html() || '';
                        const outerHTML = $searchElement[0] ? $searchElement[0].outerHTML : '';
                        const textContent = $searchElement.text() || '';
                        
                        // Ищем по ID паттернам
                        for (let pattern of searchPatterns) {
                            if (html.includes(pattern) || outerHTML.includes(pattern)) {
                                console.log('✅ НАЙДЕН ПО ID! Маркер #' + index + ' -> DA #' + daProperty.id);
                                console.log('   Уровень:', level, 'Паттерн:', pattern);
                                $marker.addClass('da-stable-active');
                                foundCount++;
                                found = true;
                                
                                // Запоминаем найденный маркер
                                stableMarkers.set(daProperty.id, {
                                    marker: $marker,
                                    index: index,
                                    level: level,
                                    pattern: pattern
                                });
                                break;
                            }
                        }
                        
                        // Если не найдено по ID, ищем по координатам
                        if (!found) {
                            let coordMatches = 0;
                            for (let coord of coordPatterns) {
                                if (html.includes(coord) || outerHTML.includes(coord)) {
                                    coordMatches++;
                                }
                            }
                            
                            // Если найдены обе координаты
                            if (coordMatches >= 2) {
                                console.log('✅ НАЙДЕН ПО КООРДИНАТАМ! Маркер #' + index + ' -> DA #' + daProperty.id);
                                console.log('   Уровень:', level, 'Совпадений координат:', coordMatches);
                                $marker.addClass('da-stable-active');
                                foundCount++;
                                found = true;
                                
                                stableMarkers.set(daProperty.id, {
                                    marker: $marker,
                                    index: index,
                                    level: level,
                                    pattern: 'coordinates'
                                });
                            }
                        }
                        
                        // Проверяем атрибуты элемента
                        if (!found && $searchElement[0] && $searchElement[0].attributes) {
                            Array.from($searchElement[0].attributes).forEach(attr => {
                                if (found) return;
                                
                                for (let pattern of searchPatterns) {
                                    if (attr.value && attr.value.includes(pattern)) {
                                        console.log('✅ НАЙДЕН ПО АТРИБУТУ! Маркер #' + index + ' -> DA #' + daProperty.id);
                                        console.log('   Атрибут:', attr.name + '="' + attr.value + '"');
                                        $marker.addClass('da-stable-active');
                                        foundCount++;
                                        found = true;
                                        
                                        stableMarkers.set(daProperty.id, {
                                            marker: $marker,
                                            index: index,
                                            level: level,
                                            pattern: attr.name + '=' + pattern
                                        });
                                        break;
                                    }
                                }
                            });
                        }
                    }
                });
                
                if (!found) {
                    console.log('❌ НЕ НАЙДЕН маркер для DA #' + daProperty.id);
                }
            });
            
            // Применяем сохранённые маркеры (если DOM изменился)
            stableMarkers.forEach((markerData, daId) => {
                if (markerData.marker.length && !markerData.marker.hasClass('da-stable-active')) {
                    markerData.marker.addClass('da-stable-active');
                    console.log('🔄 ВОССТАНОВЛЕН маркер для DA #' + daId);
                }
            });
            
            // Демо режим
            if (foundCount === 0 && daProperties.length > 0) {
                console.log('🟡 Демо режим - активируем первый маркер');
                $markers.slice(0, 1).addClass('da-stable-demo');
            }
            
            // Статистика
            setTimeout(() => {
                const activeMarkers = $('.mh-map-pin.da-stable-active').length;
                const demoMarkers = $('.mh-map-pin.da-stable-demo').length;
                
                console.log('🏁 === СТАБИЛЬНАЯ СТАТИСТИКА ===');
                console.log('🔴 Активных DA маркеров:', activeMarkers);
                console.log('🟢 Демо маркеров:', demoMarkers);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('🎯 DA объявлений:', daProperties.length);
                console.log('💾 Сохранённых маркеров:', stableMarkers.size);
                
                if (activeMarkers > 0) {
                    console.log('🎉 УСПЕХ! Маркеры найдены стабильно!');
                    
                    // Показываем детали найденных маркеров
                    stableMarkers.forEach((data, id) => {
                        console.log('📌 DA #' + id + ' -> Маркер #' + data.index + ' (паттерн: ' + data.pattern + ')');
                    });
                }
            }, 300);
        }
        
        // Функция для повторного применения классов при изменении DOM
        function reapplyStableMarkers() {
            if (stableMarkers.size === 0) return;
            
            const $markers = $('.mh-map-pin');
            let reapplied = 0;
            
            // Убираем все классы
            $markers.removeClass('da-stable-active');
            
            // Пытаемся найти сохранённые маркеры заново
            stableMarkers.forEach((markerData, daId) => {
                let found = false;
                
                $markers.each(function(index) {
                    if (found) return;
                    
                    const $marker = $(this);
                    let $parent = $marker;
                    
                    // Ищем по тому же паттерну
                    for (let i = 0; i <= markerData.level && !found; i++) {
                        $parent = $parent.parent();
                        if ($parent.length === 0) break;
                        
                        const html = $parent.html() || '';
                        if (html.includes(markerData.pattern) || 
                            (markerData.pattern.includes('=') && $parent[0] && $parent[0].outerHTML && $parent[0].outerHTML.includes(markerData.pattern.split('=')[1]))) {
                            
                            $marker.addClass('da-stable-active');
                            reapplied++;
                            found = true;
                            
                            // Обновляем сохранённые данные
                            stableMarkers.set(daId, {
                                ...markerData,
                                marker: $marker,
                                index: index
                            });
                        }
                    }
                });
            });
            
            if (reapplied > 0) {
                console.log('🔄 Повторно применено маркеров:', reapplied);
            }
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления:', daProperties);
                
                // Первый поиск
                setTimeout(findMarkersStable, 2000);
                
                // Мониторим изменения DOM с умным повтором
                if (window.MutationObserver) {
                    let debounceTimer;
                    
                    const observer = new MutationObserver(function(mutations) {
                        let hasMarkerChanges = false;
                        
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes || mutation.removedNodes) {
                                for (let node of [...(mutation.addedNodes || []), ...(mutation.removedNodes || [])]) {
                                    if (node.nodeType === 1) {
                                        if ($(node).find('.mh-map-pin').length > 0 || 
                                            $(node).hasClass('mh-map-pin')) {
                                            hasMarkerChanges = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        });
                        
                        if (hasMarkerChanges) {
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(() => {
                                console.log('🔄 Изменения маркеров обнаружены');
                                reapplyStableMarkers();
                                
                                // Если не удалось восстановить, ищем заново
                                setTimeout(() => {
                                    if ($('.mh-map-pin.da-stable-active').length === 0 && daProperties.length > 0) {
                                        console.log('🔍 Повторный поиск после изменений DOM');
                                        findMarkersStable();
                                    }
                                }, 500);
                            }, 300);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
            } else {
                console.log('⚠️ Нет DA объявлений');
            }
        });
    });
    </script>
    <?php
}
?>
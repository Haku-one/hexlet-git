<?php
/**
 * DA Markers - ПРОСТОЕ РЕШЕНИЕ
 * Добавляем галочку в админку + простое мигание
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

// AJAX для получения ID объявлений с включенным DA маркером
add_action('wp_ajax_get_da_marker_ids', 'ajax_get_da_marker_ids');
add_action('wp_ajax_nopriv_get_da_marker_ids', 'ajax_get_da_marker_ids');
function ajax_get_da_marker_ids() {
    $da_posts = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => -1,
        'meta_query' => array(
            array(
                'key' => '_da_marker_enabled',
                'value' => '1',
                'compare' => '='
            )
        ),
        'fields' => 'ids'
    ));
    
    $da_data = array();
    foreach ($da_posts as $post_id) {
        $lat = get_post_meta($post_id, 'myhome_lat', true);
        $lng = get_post_meta($post_id, 'myhome_lng', true);
        $title = get_the_title($post_id);
        
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
        'count' => count($da_data)
    ));
}

// Простой CSS для мигания
add_action('wp_head', 'da_simple_css');
function da_simple_css() {
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
    </style>
    <?php
}

// Простой JavaScript
add_action('wp_footer', 'da_simple_script');
function da_simple_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA Маркеры - простое решение запущено');
        
        let processAttempts = 0;
        const maxAttempts = 10;
        
        function processDAMarkers() {
            processAttempts++;
            console.log('🔍 Попытка #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processDAMarkers, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            
            // Получаем DA объявления
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_marker_ids'
                },
                success: function(response) {
                    if (response.success && response.data.da_markers.length > 0) {
                        console.log('✅ Найдено DA объявлений:', response.data.count);
                        console.log('📊 DA данные:', response.data.da_markers);
                        
                        // Убираем предыдущие классы
                        $('.mh-map-pin').removeClass('da-blink');
                        
                        let foundCount = 0;
                        
                        // Ищем маркеры через глобальный объект карты
                        for (let globalVar in window) {
                            if (globalVar.startsWith('MyHomeMapListing')) {
                                const mapObj = window[globalVar];
                                console.log('📊 Найден объект карты:', globalVar);
                                
                                // Ищем массивы с данными
                                function findEstatesArray(obj, path = '') {
                                    for (let key in obj) {
                                        try {
                                            let value = obj[key];
                                            if (Array.isArray(value) && value.length > 0) {
                                                // Проверяем, похож ли массив на данные объявлений
                                                if (value[0] && (value[0].id || value[0].lat || value[0].lng)) {
                                                    console.log('📋 Найден массив:', path + '.' + key, value);
                                                    
                                                    // Сопоставляем с маркерами
                                                    value.forEach((estate, index) => {
                                                        if (estate && estate.id) {
                                                            // Проверяем, есть ли это объявление в DA списке
                                                            response.data.da_markers.forEach(daMarker => {
                                                                if (parseInt(estate.id) === parseInt(daMarker.id)) {
                                                                    console.log('🎯 Найден DA маркер!', daMarker.id, 'индекс:', index);
                                                                    
                                                                    if ($markers.eq(index).length) {
                                                                        $markers.eq(index).addClass('da-blink');
                                                                        foundCount++;
                                                                        console.log('✅ Активирован маркер #' + index);
                                                                    }
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            } else if (typeof value === 'object' && value !== null) {
                                                findEstatesArray(value, path + '.' + key);
                                            }
                                        } catch (e) {
                                            // Игнорируем ошибки
                                        }
                                    }
                                }
                                
                                findEstatesArray(mapObj, globalVar);
                            }
                        }
                        
                        // Если ничего не найдено автоматически, пробуем по координатам
                        if (foundCount === 0) {
                            console.log('🔍 Поиск по координатам...');
                            
                            // Ищем координаты DA в HTML
                            const pageHTML = document.documentElement.innerHTML;
                            
                            response.data.da_markers.forEach((daMarker, daIndex) => {
                                // Ищем координаты в HTML
                                const latStr = daMarker.lat.toString();
                                const lngStr = daMarker.lng.toString();
                                
                                if (pageHTML.includes(latStr) && pageHTML.includes(lngStr)) {
                                    console.log('🎯 Найдены координаты DA в HTML:', daMarker.id);
                                    
                                    // Активируем маркер по индексу (простое предположение)
                                    if (daIndex < $markers.length) {
                                        $markers.eq(daIndex).addClass('da-blink');
                                        foundCount++;
                                        console.log('✅ Активирован маркер по координатам #' + daIndex);
                                    }
                                }
                            });
                        }
                        
                        // Финальная статистика
                        setTimeout(() => {
                            const actualFound = $('.mh-map-pin.da-blink').length;
                            console.log('📊 === РЕЗУЛЬТАТЫ ===');
                            console.log('Найдено и активировано DA маркеров:', actualFound);
                            console.log('Всего маркеров на карте:', $markers.length);
                            console.log('DA объявлений в базе:', response.data.count);
                            
                            if (actualFound > 0) {
                                console.log('🎉 УСПЕХ! DA маркеры мигают!');
                            } else {
                                console.log('⚠️ DA маркеры не найдены автоматически');
                                
                                // Запасной вариант - активируем первые маркеры
                                if (response.data.count > 0) {
                                    $markers.slice(0, Math.min(response.data.count, 3)).addClass('da-blink');
                                    console.log('🔄 Активированы первые ' + Math.min(response.data.count, 3) + ' маркера(ов)');
                                }
                            }
                        }, 500);
                        
                    } else {
                        console.log('❌ DA объявления не найдены в базе');
                    }
                },
                error: function() {
                    console.log('❌ Ошибка получения DA данных');
                }
            });
        }
        
        // Запускаем обработку
        setTimeout(processDAMarkers, 2000);
        setTimeout(processDAMarkers, 4000);
        setTimeout(processDAMarkers, 6000);
        
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
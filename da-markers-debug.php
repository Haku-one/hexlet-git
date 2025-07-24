<?php
/**
 * DA Markers - ОТЛАДОЧНАЯ ВЕРСИЯ
 * Показывает детали работы + демо режим
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
    echo '<hr><h4>Отладка:</h4>';
    echo '<p><strong>ID объявления:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Текущее значение галочки:</strong> ' . ($value ? 'Включено' : 'Выключено') . '</p>';
    
    // Координаты
    $lat = get_post_meta($post->ID, 'myhome_lat', true);
    $lng = get_post_meta($post->ID, 'myhome_lng', true);
    echo '<p><strong>Координаты:</strong> ' . ($lat && $lng ? $lat . ', ' . $lng : 'Не указаны') . '</p>';
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

// AJAX для получения ID объявлений с включенным DA маркером + отладка
add_action('wp_ajax_get_da_marker_ids_debug', 'ajax_get_da_marker_ids_debug');
add_action('wp_ajax_nopriv_get_da_marker_ids_debug', 'ajax_get_da_marker_ids_debug');
function ajax_get_da_marker_ids_debug() {
    // Получаем все объявления estate для отладки
    $all_estates = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => 10, // Берем первые 10 для отладки
        'post_status' => 'publish'
    ));
    
    $debug_info = array();
    foreach ($all_estates as $post) {
        $da_enabled = get_post_meta($post->ID, '_da_marker_enabled', true);
        $lat = get_post_meta($post->ID, 'myhome_lat', true);
        $lng = get_post_meta($post->ID, 'myhome_lng', true);
        
        $debug_info[] = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'da_enabled' => $da_enabled,
            'has_coordinates' => !empty($lat) && !empty($lng),
            'lat' => $lat,
            'lng' => $lng
        );
    }
    
    // Получаем объявления с включенным DA
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
        'count' => count($da_data),
        'debug_info' => $debug_info,
        'total_estates' => count($all_estates)
    ));
}

// Простой CSS для мигания
add_action('wp_head', 'da_debug_css');
function da_debug_css() {
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
    
    /* Демо режим - другой цвет */
    .mh-map-pin.da-demo {
        animation: da-blink 1.5s infinite;
    }

    .mh-map-pin.da-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// Отладочный JavaScript
add_action('wp_footer', 'da_debug_script');
function da_debug_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🔧 DA Маркеры - ОТЛАДОЧНАЯ ВЕРСИЯ запущена');
        
        let processAttempts = 0;
        const maxAttempts = 5;
        
        function processDAMarkers() {
            processAttempts++;
            console.log('🔍 Отладочная попытка #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(processDAMarkers, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            
            // Получаем отладочную информацию
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_marker_ids_debug'
                },
                success: function(response) {
                    console.log('📡 ПОЛНЫЙ ОТВЕТ СЕРВЕРА:', response);
                    
                    if (response.success) {
                        console.log('🔧 === ОТЛАДОЧНАЯ ИНФОРМАЦИЯ ===');
                        console.log('Всего объявлений estate:', response.data.total_estates);
                        console.log('DA объявлений с галочкой:', response.data.count);
                        console.log('Детали первых 10 объявлений:', response.data.debug_info);
                        
                        if (response.data.da_markers.length > 0) {
                            console.log('✅ Найдены DA объявления:', response.data.da_markers);
                            
                            // Убираем предыдущие классы
                            $('.mh-map-pin').removeClass('da-blink da-demo');
                            
                            // Пробуем найти маркеры
                            let foundCount = 0;
                            
                            // Ищем через глобальные объекты
                            for (let globalVar in window) {
                                if (globalVar.startsWith('MyHomeMapListing')) {
                                    const mapObj = window[globalVar];
                                    console.log('📊 Анализируем:', globalVar, mapObj);
                                    
                                    // Ищем массивы с данными
                                    function findEstatesArray(obj, path = '') {
                                        for (let key in obj) {
                                            try {
                                                let value = obj[key];
                                                if (Array.isArray(value) && value.length > 0) {
                                                    // Проверяем первый элемент массива
                                                    if (value[0] && (value[0].id || value[0].lat || value[0].lng)) {
                                                        console.log('📋 Найден массив данных:', path + '.' + key);
                                                        console.log('📋 Первые 3 элемента:', value.slice(0, 3));
                                                        
                                                        // Сопоставляем с DA маркерами
                                                        value.forEach((estate, index) => {
                                                            if (estate && estate.id) {
                                                                response.data.da_markers.forEach(daMarker => {
                                                                    if (parseInt(estate.id) === parseInt(daMarker.id)) {
                                                                        console.log('🎯 НАЙДЕН DA МАРКЕР!', daMarker.id, 'позиция в массиве:', index);
                                                                        
                                                                        if ($markers.eq(index).length) {
                                                                            $markers.eq(index).addClass('da-blink');
                                                                            foundCount++;
                                                                            console.log('✅ Активирован маркер #' + index, 'для объявления', daMarker.id);
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
                            
                            console.log('📊 Результат автоматического поиска:', foundCount, 'маркеров активировано');
                            
                        } else {
                            console.log('⚠️ DA объявления не найдены в базе');
                            console.log('💡 Возможные причины:');
                            console.log('1. Галочки не поставлены ни на одном объявлении');
                            console.log('2. Галочки поставлены, но объявления не имеют координат');
                            console.log('3. Проблема с сохранением мета-полей');
                            
                            // ДЕМО РЕЖИМ - активируем первые 2 маркера зеленым цветом
                            console.log('🔄 Активируем ДЕМО РЕЖИМ (зеленые маркеры)');
                            $markers.slice(0, 2).addClass('da-demo');
                            console.log('✅ ДЕМО: Активированы первые 2 маркера зеленым цветом');
                        }
                        
                        // Финальная статистика
                        setTimeout(() => {
                            const daFound = $('.mh-map-pin.da-blink').length;
                            const demoFound = $('.mh-map-pin.da-demo').length;
                            
                            console.log('📊 === ФИНАЛЬНАЯ СТАТИСТИКА ===');
                            console.log('Красных DA маркеров:', daFound);
                            console.log('Зеленых ДЕМО маркеров:', demoFound);
                            console.log('Всего маркеров на карте:', $markers.length);
                            
                            if (daFound > 0) {
                                console.log('🎉 УСПЕХ! Красные DA маркеры работают!');
                            } else if (demoFound > 0) {
                                console.log('🟢 ДЕМО режим активен (зеленые маркеры)');
                                console.log('💡 Чтобы включить красные маркеры:');
                                console.log('1. Зайдите в админку WordPress');
                                console.log('2. Откройте любое объявление (тип estate)');
                                console.log('3. Найдите справа блок "DA Маркер"');
                                console.log('4. Поставьте галочку и сохраните');
                            } else {
                                console.log('❌ Ни один маркер не активирован');
                            }
                        }, 500);
                        
                    } else {
                        console.error('❌ Ошибка ответа сервера');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX ошибка:', error);
                    console.error('❌ Статус:', status);
                    console.error('❌ Ответ:', xhr.responseText);
                    
                    // В случае ошибки активируем демо
                    console.log('🔄 Ошибка AJAX, активируем ДЕМО РЕЖИМ');
                    let $markers = $('.mh-map-pin');
                    $markers.slice(0, 2).addClass('da-demo');
                    console.log('✅ ДЕМО: Активированы первые 2 маркера зеленым цветом');
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
<?php
/**
 * DA Markers - ТОЧНОЕ РЕШЕНИЕ
 * Простой поиск без резервов и случайностей
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
    echo '<p><small>ТОЧНОЕ решение без резервов</small></p>';
    
    echo '<hr><h4>Информация:</h4>';
    echo '<p><strong>ID:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Заголовок:</strong> ' . $post->post_title . '</p>';
    echo '<p><strong>Статус:</strong> ' . ($value ? '🟢 Включено' : '⚪ Выключено') . '</p>';
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
add_action('wp_ajax_get_da_exact', 'ajax_get_da_exact');
add_action('wp_ajax_nopriv_get_da_exact', 'ajax_get_da_exact');
function ajax_get_da_exact() {
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
        $da_data[] = array(
            'id' => intval($post->ID),
            'title' => $post->post_title
        );
    }
    
    wp_send_json_success(array(
        'da_properties' => $da_data,
        'count' => count($da_data)
    ));
}

// CSS для мигания
add_action('wp_head', 'da_exact_css');
function da_exact_css() {
    ?>
    <style>
    @keyframes da-exact-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            transform: scale(1.2);
        }
    }

    .mh-map-pin.da-exact-found {
        animation: da-exact-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-exact-found i {
        color: #ff0066 !important;
    }
    </style>
    <?php
}

// JavaScript - ТОЧНЫЙ поиск
add_action('wp_footer', 'da_exact_script');
function da_exact_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA ТОЧНОЕ РЕШЕНИЕ - запущено');
        
        let daProperties = [];
        let foundMarkers = [];
        
        // Получаем DA данные
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_exact'
                }
            });
        }
        
        // ТОЧНЫЙ поиск маркеров
        function findExactMarkers() {
            console.log('🔍 ТОЧНЫЙ поиск маркеров');
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены');
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 DA объявлений:', daProperties.length);
            
            // Убираем все классы
            $markers.removeClass('da-exact-found');
            foundMarkers = [];
            
            // Для каждого DA объявления
            daProperties.forEach(daProperty => {
                console.log('🔍 Ищем: "' + daProperty.title + '"');
                
                let foundMarker = null;
                let bestMatch = null;
                let bestDistance = Infinity;
                
                // Ищем элементы с этим заголовком
                const searchText = daProperty.title;
                
                // Поиск точного текста в DOM
                $('*').each(function() {
                    const $element = $(this);
                    const elementText = $element.text().trim();
                    
                    // ТОЧНОЕ совпадение текста
                    if (elementText === searchText) {
                        console.log('✅ ТОЧНОЕ совпадение найдено в элементе:', $element[0].tagName);
                        
                        // Ищем ближайший маркер
                        const elementPos = $element.offset();
                        if (elementPos) {
                            $markers.each(function() {
                                const $marker = $(this);
                                const markerPos = $marker.offset();
                                
                                if (markerPos) {
                                    const distance = Math.sqrt(
                                        Math.pow(elementPos.left - markerPos.left, 2) + 
                                        Math.pow(elementPos.top - markerPos.top, 2)
                                    );
                                    
                                    if (distance < bestDistance) {
                                        bestDistance = distance;
                                        bestMatch = $marker;
                                    }
                                }
                            });
                        }
                    }
                });
                
                // Активируем найденный маркер
                if (bestMatch && bestMatch.length) {
                    bestMatch.addClass('da-exact-found');
                    const markerIndex = $markers.index(bestMatch);
                    foundMarkers.push({
                        marker: bestMatch,
                        index: markerIndex,
                        title: daProperty.title,
                        distance: Math.round(bestDistance)
                    });
                    
                    console.log('🎯 АКТИВИРОВАН маркер #' + markerIndex + ' для "' + daProperty.title + '" (расстояние: ' + Math.round(bestDistance) + 'px)');
                } else {
                    console.log('❌ НЕ НАЙДЕН маркер для "' + daProperty.title + '"');
                }
            });
            
            // Финальная статистика
            setTimeout(() => {
                const activeMarkers = $('.mh-map-pin.da-exact-found').length;
                
                console.log('🏁 === ТОЧНАЯ СТАТИСТИКА ===');
                console.log('🔴 Найденных DA маркеров:', activeMarkers);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('🎯 DA объявлений в базе:', daProperties.length);
                
                if (activeMarkers > 0) {
                    console.log('🎉 УСПЕХ! Найдены ТОЧНЫЕ совпадения!');
                    foundMarkers.forEach(data => {
                        console.log('📌 "' + data.title + '" -> Маркер #' + data.index + ' (расстояние: ' + data.distance + 'px)');
                    });
                } else {
                    console.log('❌ ТОЧНЫЕ совпадения НЕ НАЙДЕНЫ');
                    console.log('💡 Возможные причины:');
                    console.log('   1. Заголовок в базе не совпадает с заголовком на карте');
                    console.log('   2. Инфобоксы скрыты или не загружены');
                    console.log('   3. Текст находится в недоступном элементе');
                }
            }, 100);
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления:', daProperties);
                
                // Первый поиск
                setTimeout(findExactMarkers, 3000);
                
                // Повторный поиск при изменениях (БЕЗ избыточности)
                if (window.MutationObserver) {
                    let searchTimeout;
                    
                    const observer = new MutationObserver(function(mutations) {
                        let hasMarkerChanges = false;
                        
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes) {
                                for (let node of mutation.addedNodes) {
                                    if (node.nodeType === 1 && 
                                        ($(node).find('.mh-map-pin').length > 0 || $(node).hasClass('mh-map-pin'))) {
                                        hasMarkerChanges = true;
                                        break;
                                    }
                                }
                            }
                        });
                        
                        if (hasMarkerChanges) {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(() => {
                                console.log('🔄 Изменения маркеров, повторный ТОЧНЫЙ поиск');
                                findExactMarkers();
                            }, 1000);
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
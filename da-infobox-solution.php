<?php
/**
 * DA Markers - ПОИСК В ИНФОБОКСАХ
 * Ищет заголовки только в видимых инфобоксах маркеров
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
    echo '<p><small>Поиск ТОЛЬКО в инфобоксах маркеров</small></p>';
    
    echo '<hr><h4>Информация:</h4>';
    echo '<p><strong>ID:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Заголовок:</strong> ' . $post->post_title . '</p>';
    echo '<p><strong>Статус:</strong> ' . ($value ? '🟢 Включено' : '⚪ Выключено') . '</p>';
    
    if ($value) {
        echo '<p style="color: green;">✅ Поиск в инфобоксах маркеров</p>';
        echo '<p><small>Система будет кликать по маркерам и искать заголовок в инфобоксах</small></p>';
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
add_action('wp_ajax_get_da_infobox', 'ajax_get_da_infobox');
add_action('wp_ajax_nopriv_get_da_infobox', 'ajax_get_da_infobox');
function ajax_get_da_infobox() {
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
add_action('wp_head', 'da_infobox_css');
function da_infobox_css() {
    ?>
    <style>
    @keyframes da-infobox-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            transform: scale(1.2);
        }
    }

    .mh-map-pin.da-infobox-found {
        animation: da-infobox-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-infobox-found i {
        color: #ff0066 !important;
    }
    </style>
    <?php
}

// JavaScript - поиск в инфобоксах
add_action('wp_footer', 'da_infobox_script');
function da_infobox_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('📋 DA ПОИСК В ИНФОБОКСАХ - запущено');
        
        let daProperties = [];
        let foundMarkers = [];
        let searchInProgress = false;
        
        // Получаем DA данные
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_infobox'
                }
            });
        }
        
        // Поиск в инфобоксах маркеров
        function searchInInfoboxes() {
            if (searchInProgress) {
                console.log('🔄 Поиск уже выполняется...');
                return;
            }
            
            searchInProgress = true;
            console.log('🔍 ПОИСК В ИНФОБОКСАХ маркеров');
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены');
                searchInProgress = false;
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 DA объявлений для поиска:', daProperties.length);
            
            // Убираем все классы
            $markers.removeClass('da-infobox-found');
            foundMarkers = [];
            
            let currentMarkerIndex = 0;
            
            // Функция для проверки одного маркера
            function checkNextMarker() {
                if (currentMarkerIndex >= $markers.length) {
                    // Закончили проверку всех маркеров
                    console.log('🏁 Проверка всех маркеров завершена');
                    showFinalResults();
                    searchInProgress = false;
                    return;
                }
                
                const $currentMarker = $markers.eq(currentMarkerIndex);
                console.log('🔍 Проверяем маркер #' + currentMarkerIndex);
                
                // Кликаем на маркер чтобы показать инфобокс
                $currentMarker.trigger('click');
                
                // Ждем появления инфобокса
                setTimeout(() => {
                    // Ищем видимые инфобоксы
                    const $visibleInfoboxes = $('.infoBox:visible, .mh-map-infobox:visible, [class*="infobox"]:visible').filter(':visible');
                    
                    console.log('📋 Видимых инфобоксов:', $visibleInfoboxes.length);
                    
                    let markerMatched = false;
                    
                    // Проверяем каждый видимый инфобокс
                    $visibleInfoboxes.each(function() {
                        if (markerMatched) return;
                        
                        const $infobox = $(this);
                        const infoboxText = $infobox.text();
                        
                        console.log('📋 Текст в инфобоксе:', infoboxText.substring(0, 100) + '...');
                        
                        // Проверяем совпадение с DA объявлениями
                        daProperties.forEach(daProperty => {
                            if (markerMatched) return;
                            
                            if (infoboxText.includes(daProperty.title)) {
                                console.log('✅ НАЙДЕНО СОВПАДЕНИЕ!');
                                console.log('🎯 Маркер #' + currentMarkerIndex + ' содержит: "' + daProperty.title + '"');
                                
                                $currentMarker.addClass('da-infobox-found');
                                foundMarkers.push({
                                    marker: $currentMarker,
                                    index: currentMarkerIndex,
                                    title: daProperty.title
                                });
                                
                                markerMatched = true;
                            }
                        });
                    });
                    
                    if (!markerMatched) {
                        console.log('❌ Маркер #' + currentMarkerIndex + ' - нет совпадений');
                    }
                    
                    // Скрываем инфобокс (кликаем в другое место)
                    $('body').trigger('click');
                    
                    // Переходим к следующему маркеру
                    currentMarkerIndex++;
                    setTimeout(checkNextMarker, 500); // Пауза между проверками
                    
                }, 300); // Время ожидания появления инфобокса
            }
            
            // Начинаем проверку
            checkNextMarker();
        }
        
        // Показать финальные результаты
        function showFinalResults() {
            const activeMarkers = $('.mh-map-pin.da-infobox-found').length;
            
            console.log('🏁 === РЕЗУЛЬТАТЫ ПОИСКА В ИНФОБОКСАХ ===');
            console.log('🔴 Найденных DA маркеров:', activeMarkers);
            console.log('📍 Всего маркеров проверено:', $('.mh-map-pin').length);
            console.log('🎯 DA объявлений в базе:', daProperties.length);
            
            if (activeMarkers > 0) {
                console.log('🎉 УСПЕХ! Найдены маркеры с DA объявлениями!');
                foundMarkers.forEach(data => {
                    console.log('📌 Маркер #' + data.index + ' -> "' + data.title + '"');
                });
            } else {
                console.log('❌ DA объявления НЕ НАЙДЕНЫ в инфобоксах');
                console.log('💡 Возможные причины:');
                console.log('   1. Заголовки в инфобоксах отличаются от заголовков в базе');
                console.log('   2. Инфобоксы не появляются при клике');
                console.log('   3. Инфобоксы имеют другую структуру');
            }
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления для поиска в инфобоксах:', daProperties);
                
                // Запускаем поиск с задержкой
                setTimeout(() => {
                    console.log('🚀 Начинаем поиск в инфобоксах маркеров...');
                    searchInInfoboxes();
                }, 3000);
                
                // Повторный поиск при значительных изменениях карты
                if (window.MutationObserver) {
                    let lastMarkerCount = 0;
                    let debounceTimer;
                    
                    const observer = new MutationObserver(function(mutations) {
                        const currentMarkerCount = $('.mh-map-pin').length;
                        
                        // Запускаем поиск только при значительном изменении количества маркеров
                        if (Math.abs(currentMarkerCount - lastMarkerCount) > 2) {
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(() => {
                                if (!searchInProgress) {
                                    console.log('🔄 Значительные изменения маркеров (' + lastMarkerCount + ' -> ' + currentMarkerCount + '), повторный поиск...');
                                    lastMarkerCount = currentMarkerCount;
                                    searchInInfoboxes();
                                }
                            }, 2000);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                    
                    lastMarkerCount = $('.mh-map-pin').length;
                }
                
            } else {
                console.log('⚠️ Нет DA объявлений для поиска');
            }
        });
    });
    </script>
    <?php
}
?>
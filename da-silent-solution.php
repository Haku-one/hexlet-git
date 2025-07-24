<?php
/**
 * DA Markers - НЕЗАМЕТНЫЙ ПОИСК
 * Ищет данные маркеров незаметно для пользователя
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
    echo '<p><small>Незаметный поиск для пользователя</small></p>';
    
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
add_action('wp_ajax_get_da_silent', 'ajax_get_da_silent');
add_action('wp_ajax_nopriv_get_da_silent', 'ajax_get_da_silent');
function ajax_get_da_silent() {
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
add_action('wp_head', 'da_silent_css');
function da_silent_css() {
    ?>
    <style>
    @keyframes da-silent-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            transform: scale(1.2);
        }
    }

    .mh-map-pin.da-silent-found {
        animation: da-silent-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-silent-found i {
        color: #ff0066 !important;
    }
    
    /* Скрываем временные инфобоксы */
    .da-temp-hidden {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    </style>
    <?php
}

// JavaScript - незаметный поиск
add_action('wp_footer', 'da_silent_script');
function da_silent_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🤫 DA НЕЗАМЕТНЫЙ ПОИСК - запущено');
        
        let daProperties = [];
        let foundMarkers = [];
        let searchInProgress = false;
        
        // Получаем DA данные
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_silent'
                }
            });
        }
        
        // Незаметный поиск в маркерах
        function silentSearch() {
            if (searchInProgress) {
                console.log('🔄 Поиск уже выполняется...');
                return;
            }
            
            searchInProgress = true;
            console.log('🤫 НЕЗАМЕТНЫЙ поиск маркеров');
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены');
                searchInProgress = false;
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 DA объявлений для поиска:', daProperties.length);
            
            // Убираем все классы
            $markers.removeClass('da-silent-found');
            foundMarkers = [];
            
            let currentMarkerIndex = 0;
            
            // Функция для незаметной проверки одного маркера
            function checkMarkerSilently() {
                if (currentMarkerIndex >= $markers.length) {
                    console.log('🏁 Незаметная проверка завершена');
                    showSilentResults();
                    searchInProgress = false;
                    return;
                }
                
                const $currentMarker = $markers.eq(currentMarkerIndex);
                console.log('🤫 Незаметно проверяем маркер #' + currentMarkerIndex);
                
                // Запоминаем текущее состояние инфобоксов
                const $existingInfoboxes = $('.infoBox, .mh-map-infobox');
                const originalDisplay = {};
                $existingInfoboxes.each(function(i) {
                    originalDisplay[i] = $(this).css('display');
                });
                
                // Скрываем все существующие инфобоксы ВРЕМЕННО
                $existingInfoboxes.addClass('da-temp-hidden');
                
                // Эмулируем hover вместо click (менее заметно)
                $currentMarker.trigger('mouseenter');
                
                // Очень короткая задержка для появления инфобокса
                setTimeout(() => {
                    // Ищем новые видимые инфобоксы (исключая скрытые нами)
                    const $newInfoboxes = $('.infoBox, .mh-map-infobox').not('.da-temp-hidden').filter(':visible');
                    
                    let markerMatched = false;
                    
                    if ($newInfoboxes.length > 0) {
                        console.log('🤫 Найден инфобокс для маркера #' + currentMarkerIndex);
                        
                        $newInfoboxes.each(function() {
                            if (markerMatched) return;
                            
                            const $infobox = $(this);
                            const infoboxText = $infobox.text();
                            
                            // Проверяем совпадение с DA объявлениями
                            daProperties.forEach(daProperty => {
                                if (markerMatched) return;
                                
                                if (infoboxText.includes(daProperty.title)) {
                                    console.log('✅ НЕЗАМЕТНО НАЙДЕНО: Маркер #' + currentMarkerIndex + ' -> "' + daProperty.title + '"');
                                    
                                    $currentMarker.addClass('da-silent-found');
                                    foundMarkers.push({
                                        marker: $currentMarker,
                                        index: currentMarkerIndex,
                                        title: daProperty.title
                                    });
                                    
                                    markerMatched = true;
                                }
                            });
                        });
                        
                        // Скрываем новый инфобокс немедленно
                        $newInfoboxes.addClass('da-temp-hidden');
                    }
                    
                    // Убираем hover
                    $currentMarker.trigger('mouseleave');
                    
                    // Восстанавливаем оригинальное состояние всех инфобоксов
                    setTimeout(() => {
                        $existingInfoboxes.removeClass('da-temp-hidden');
                        $('.infoBox, .mh-map-infobox').removeClass('da-temp-hidden');
                        
                        // Переходим к следующему маркеру
                        currentMarkerIndex++;
                        setTimeout(checkMarkerSilently, 100); // Очень быстро
                        
                    }, 50); // Минимальная задержка для восстановления
                    
                }, 100); // Минимальное время для появления инфобокса
            }
            
            // Начинаем незаметную проверку
            checkMarkerSilently();
        }
        
        // Альтернативный метод - поиск в глобальных данных карты
        function searchInGlobalData() {
            console.log('🔍 Поиск в глобальных данных карты...');
            
            const $markers = $('.mh-map-pin');
            let foundCount = 0;
            
            // Ищем в MyHomeMapListing объектах
            for (let globalVar in window) {
                if (globalVar.startsWith('MyHomeMapListing')) {
                    const mapObj = window[globalVar];
                    console.log('🗺️ Анализируем:', globalVar);
                    
                    if (mapObj && mapObj.results && mapObj.results.estates) {
                        const estates = mapObj.results.estates;
                        console.log('🏠 Найдено объявлений:', estates.length);
                        
                        // Проверяем каждое объявление
                        estates.forEach((estate, index) => {
                            if (estate.post_title || estate.title || estate.name) {
                                const estateTitle = estate.post_title || estate.title || estate.name;
                                
                                daProperties.forEach(daProperty => {
                                    if (estateTitle.includes(daProperty.title) || daProperty.title.includes(estateTitle)) {
                                        console.log('✅ НАЙДЕНО В ДАННЫХ: "' + daProperty.title + '" в позиции ' + index);
                                        
                                        // Активируем маркер по позиции (если есть)
                                        if ($markers.eq(index).length) {
                                            $markers.eq(index).addClass('da-silent-found');
                                            foundCount++;
                                            
                                            foundMarkers.push({
                                                marker: $markers.eq(index),
                                                index: index,
                                                title: daProperty.title
                                            });
                                        }
                                    }
                                });
                            }
                        });
                    }
                }
            }
            
            if (foundCount === 0) {
                console.log('🤫 Переходим к незаметному поиску через hover...');
                silentSearch();
            } else {
                console.log('✅ Найдено через глобальные данные:', foundCount);
                showSilentResults();
            }
        }
        
        // Показать результаты
        function showSilentResults() {
            const activeMarkers = $('.mh-map-pin.da-silent-found').length;
            
            console.log('🏁 === РЕЗУЛЬТАТЫ НЕЗАМЕТНОГО ПОИСКА ===');
            console.log('🔴 Найденных DA маркеров:', activeMarkers);
            console.log('📍 Всего маркеров на карте:', $('.mh-map-pin').length);
            console.log('🎯 DA объявлений в базе:', daProperties.length);
            
            if (activeMarkers > 0) {
                console.log('🎉 УСПЕХ! Найдены DA маркеры незаметно!');
                foundMarkers.forEach(data => {
                    console.log('📌 Маркер #' + data.index + ' -> "' + data.title + '"');
                });
            } else {
                console.log('❌ DA объявления не найдены');
            }
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления для незаметного поиска:', daProperties);
                
                // Сначала пробуем поиск в глобальных данных
                setTimeout(() => {
                    console.log('🚀 Начинаем незаметный поиск...');
                    searchInGlobalData();
                }, 3000);
                
                // Мониторим изменения (реже запускаемся)
                if (window.MutationObserver) {
                    let lastMarkerCount = $('.mh-map-pin').length;
                    let debounceTimer;
                    
                    const observer = new MutationObserver(function(mutations) {
                        const currentMarkerCount = $('.mh-map-pin').length;
                        
                        if (Math.abs(currentMarkerCount - lastMarkerCount) > 3) {
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(() => {
                                if (!searchInProgress) {
                                    console.log('🤫 Значительные изменения маркеров, повторный незаметный поиск...');
                                    lastMarkerCount = currentMarkerCount;
                                    $('.mh-map-pin').removeClass('da-silent-found');
                                    searchInGlobalData();
                                }
                            }, 3000); // Большая пауза
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
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
<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ФИНАЛЬНАЯ ВЕРСИЯ
 * =====================================
 */

// Добавляем CSS стили для мигания
add_action('wp_head', function() {
    ?>
    <style type="text/css">
    /* DA маркеры - мигание */
    @keyframes da-blink {
        0% { 
            opacity: 1; 
            transform: scale(1);
            filter: drop-shadow(0 0 8px #ff0000);
        }
        50% { 
            opacity: 0.4; 
            transform: scale(1.4);
            filter: drop-shadow(0 0 25px #ff0000);
        }
        100% { 
            opacity: 1; 
            transform: scale(1);
            filter: drop-shadow(0 0 8px #ff0000);
        }
    }

    /* Применяем анимацию к маркерам с классом da-marker-blink */
    .mh-map-pin.da-marker-blink {
        animation: da-blink 2.5s infinite ease-in-out !important;
        z-index: 9999 !important;
        position: relative !important;
        background-color: rgba(255, 0, 0, 0.15) !important;
        border: 3px solid #ff0000 !important;
        border-radius: 50% !important;
        box-shadow: 0 0 15px rgba(255, 0, 0, 0.6) !important;
    }

    /* Делаем иконку внутри маркера красной */
    .mh-map-pin.da-marker-blink i.flaticon-pin {
        color: #ff0000 !important;
        text-shadow: 0 0 5px rgba(255, 0, 0, 0.8) !important;
    }

    /* Дополнительные стили для выделения */
    .mh-map-pin.da-marker-blink::before {
        content: '';
        position: absolute;
        top: -5px;
        left: -5px;
        right: -5px;
        bottom: -5px;
        border: 2px solid rgba(255, 0, 0, 0.5);
        border-radius: 50%;
        animation: da-pulse 3s infinite ease-in-out;
    }

    @keyframes da-pulse {
        0%, 100% { 
            transform: scale(1);
            opacity: 0.7;
        }
        50% { 
            transform: scale(1.2);
            opacity: 0.3;
        }
    }
    </style>
    <?php
});

// AJAX для получения полных данных DA маркеров (с названиями)
add_action('wp_ajax_get_da_markers_full', 'ajax_get_da_markers_full');
add_action('wp_ajax_nopriv_get_da_markers_full', 'ajax_get_da_markers_full');

function ajax_get_da_markers_full() {
    // Получаем DA объявления
    $da_properties = get_posts(array(
        'post_type' => 'estate',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'spetspredlozheniya',
                'field' => 'slug',
                'terms' => 'da'
            )
        )
    ));

    $markers = array();
    foreach ($da_properties as $property) {
        $latitude = get_post_meta($property->ID, 'myhome_lat', true);
        $longitude = get_post_meta($property->ID, 'myhome_lng', true);
        $address = get_post_meta($property->ID, 'myhome_property_address', true);
        
        $markers[] = array(
            'id' => $property->ID,
            'title' => $property->post_title,
            'slug' => $property->post_name,
            'latitude' => floatval($latitude),
            'longitude' => floatval($longitude),
            'address' => $address,
            'name' => $property->post_title // Дублируем для совместимости
        );
    }

    wp_send_json_success(array(
        'markers' => $markers,
        'count' => count($markers)
    ));
}

// Добавляем JavaScript для мигания маркеров - ФИНАЛЬНАЯ ВЕРСИЯ
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - ФИНАЛЬНАЯ ВЕРСИЯ загружена');
            
            var daPropertyIds = [];
            var daPropertyData = [];
            var processedMarkers = new Set();
            
            // Получаем полные данные DA объявлений
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_markers_full'
                },
                success: function(response) {
                    if (response.success && response.data.markers.length > 0) {
                        console.log('✅ Найдено DA объявлений: ' + response.data.count);
                        
                        daPropertyData = response.data.markers;
                        daPropertyIds = daPropertyData.map(function(marker) {
                            return parseInt(marker.id);
                        });
                        
                        console.log('DA Property IDs:', daPropertyIds);
                        console.log('DA Property Data:', daPropertyData);
                        
                        // Запускаем финальную систему
                        initFinalSystem();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function initFinalSystem() {
                
                // Функция для проверки, является ли маркер DA объявлением
                function isDAMarker(propertyId) {
                    if (!propertyId) return false;
                    return daPropertyIds.indexOf(parseInt(propertyId)) !== -1;
                }
                
                // Функция для применения стиля мигания
                function applyBlinkStyle(element, id, method) {
                    if (!element) return false;
                    
                    var $element = $(element);
                    if (!$element.hasClass('da-marker-blink')) {
                        $element.addClass('da-marker-blink');
                        console.log('✨ Применен стиль мигания (метод: ' + method + ', ID: ' + id + ')');
                        return true;
                    }
                    return false;
                }
                
                // МЕТОД 1: Прямое сопоставление по порядку (самый простой)
                function method1_DirectMapping() {
                    console.log('🔄 МЕТОД 1: Прямое сопоставление по порядку');
                    
                    setTimeout(function() {
                        var $markers = $('.mh-map-pin');
                        console.log('Найдено маркеров в DOM:', $markers.length);
                        
                        // Применяем стили к первым N маркерам, где N = количество DA объявлений
                        var applied = 0;
                        for (var i = 0; i < Math.min($markers.length, daPropertyIds.length); i++) {
                            if (applyBlinkStyle($markers[i], 'unknown_' + i, 'direct_mapping')) {
                                applied++;
                            }
                        }
                        
                        console.log('Метод 1: Применено стилей:', applied);
                        
                        if (applied === 0) {
                            setTimeout(method2_CoordinateMatching, 2000);
                        } else {
                            console.log('✅ Метод 1 успешен!');
                        }
                    }, 2000);
                }
                
                // МЕТОД 2: Сопоставление по координатам
                function method2_CoordinateMatching() {
                    console.log('🔄 МЕТОД 2: Сопоставление по координатам');
                    
                    // Этот метод работает, если в window есть данные с координатами
                    if (window.MyHome && window.MyHome.api) {
                        // Пробуем запросить данные напрямую через нужный endpoint
                        var apiUrl = window.MyHome.api.replace('/estates', '') + '/map-data'; // пробуем альтернативный endpoint
                        
                        $.ajax({
                            url: apiUrl,
                            type: 'GET',
                            success: function(data) {
                                console.log('📥 Получены данные через альтернативный API:', data);
                                processApiData(data, 'alternative_api');
                            },
                            error: function() {
                                console.log('⚠️ Альтернативный API не сработал');
                                setTimeout(method3_TitleMatching, 2000);
                            }
                        });
                    } else {
                        setTimeout(method3_TitleMatching, 2000);
                    }
                }
                
                // МЕТОД 3: Сопоставление по названиям через DOM
                function method3_TitleMatching() {
                    console.log('🔄 МЕТОД 3: Сопоставление по названиям');
                    
                    var $markers = $('.mh-map-pin');
                    var applied = 0;
                    
                    // Ищем названия объявлений в DOM элементах карты
                    $markers.each(function(index) {
                        var $marker = $(this);
                        var $parent = $marker.closest('[data-title], [title]');
                        var markerTitle = $parent.data('title') || $parent.attr('title') || '';
                        
                        if (markerTitle) {
                            console.log('Найдено название маркера #' + index + ':', markerTitle);
                            
                            // Ищем совпадения с DA объявлениями
                            daPropertyData.forEach(function(daProp) {
                                if (daProp.title.toLowerCase().indexOf(markerTitle.toLowerCase()) !== -1 ||
                                    markerTitle.toLowerCase().indexOf(daProp.title.toLowerCase()) !== -1) {
                                    
                                    console.log('🎯 Найдено совпадение названий:', markerTitle, '<->', daProp.title);
                                    if (applyBlinkStyle($marker[0], daProp.id, 'title_matching')) {
                                        applied++;
                                    }
                                }
                            });
                        }
                    });
                    
                    console.log('Метод 3: Применено стилей:', applied);
                    
                    if (applied === 0) {
                        setTimeout(method4_HtmlParsing, 2000);
                    } else {
                        console.log('✅ Метод 3 успешен!');
                    }
                }
                
                // МЕТОД 4: Парсинг HTML для поиска данных
                function method4_HtmlParsing() {
                    console.log('🔄 МЕТОД 4: Парсинг HTML');
                    
                    var scriptTags = document.getElementsByTagName('script');
                    var applied = 0;
                    
                    for (var i = 0; i < scriptTags.length; i++) {
                        var scriptContent = scriptTags[i].innerHTML;
                        
                        // Ищем JSON данные в скриптах
                        if (scriptContent.indexOf('estates') !== -1 || scriptContent.indexOf('properties') !== -1) {
                            console.log('🔍 Найден потенциальный скрипт с данными:', scriptContent.substring(0, 200) + '...');
                            
                            // Пытаемся извлечь JSON данные
                            try {
                                var matches = scriptContent.match(/(\[.*\])/g);
                                if (matches) {
                                    matches.forEach(function(match) {
                                        try {
                                            var data = JSON.parse(match);
                                            if (Array.isArray(data) && data.length > 0 && data[0].id) {
                                                console.log('📋 Найдены данные в скрипте:', data);
                                                applied += processApiData(data, 'html_parsing');
                                            }
                                        } catch(e) {
                                            // Не JSON
                                        }
                                    });
                                }
                            } catch(e) {
                                // Ошибка парсинга
                            }
                        }
                    }
                    
                    console.log('Метод 4: Применено стилей:', applied);
                    
                    if (applied === 0) {
                        setTimeout(method5_IntervalChecking, 2000);
                    } else {
                        console.log('✅ Метод 4 успешен!');
                    }
                }
                
                // МЕТОД 5: Интервальная проверка и принудительное применение
                function method5_IntervalChecking() {
                    console.log('🔄 МЕТОД 5: Интервальная проверка');
                    
                    var attempts = 0;
                    var maxAttempts = 10;
                    
                    var checkInterval = setInterval(function() {
                        attempts++;
                        
                        // Проверяем изменения в DOM
                        var $newMarkers = $('.mh-map-pin:not(.da-processed)');
                        if ($newMarkers.length > 0) {
                            console.log('🔍 Найдены новые маркеры:', $newMarkers.length);
                            $newMarkers.addClass('da-processed');
                            
                            // Применяем стили по порядку
                            var applied = 0;
                            $newMarkers.slice(0, daPropertyIds.length).each(function(index) {
                                if (applyBlinkStyle(this, 'interval_' + index, 'interval_checking')) {
                                    applied++;
                                }
                            });
                            
                            if (applied > 0) {
                                console.log('✅ Метод 5 успешен! Применено:', applied);
                                clearInterval(checkInterval);
                                return;
                            }
                        }
                        
                        if (attempts >= maxAttempts) {
                            console.log('🚨 Все методы исчерпаны, применяем принудительное решение');
                            forceApplyStyles();
                            clearInterval(checkInterval);
                        }
                    }, 3000);
                }
                
                // Принудительное применение стилей (последняя мера)
                function forceApplyStyles() {
                    console.log('🚨 ПРИНУДИТЕЛЬНОЕ ПРИМЕНЕНИЕ СТИЛЕЙ');
                    
                    var $allMarkers = $('.mh-map-pin');
                    var applied = 0;
                    
                    // Применяем к первым N маркерам
                    $allMarkers.slice(0, daPropertyIds.length).each(function(index) {
                        if (applyBlinkStyle(this, 'force_' + index, 'force_apply')) {
                            applied++;
                        }
                    });
                    
                    console.log('🚨 Принудительно применено стилей:', applied);
                    
                    if (applied > 0) {
                        console.log('✅ ЗАДАЧА ВЫПОЛНЕНА! DA маркеры мигают красным цветом');
                    } else {
                        console.log('❌ Не удалось применить стили ни одним способом');
                    }
                }
                
                // Обработка данных API (универсальная функция)
                function processApiData(data, source) {
                    console.log('🏠 Обработка данных из источника:', source);
                    
                    var applied = 0;
                    var $markers = $('.mh-map-pin');
                    
                    if (Array.isArray(data)) {
                        data.forEach(function(item, index) {
                            if (item && item.id && isDAMarker(item.id)) {
                                var $marker = $markers.eq(index);
                                if ($marker.length && applyBlinkStyle($marker[0], item.id, source)) {
                                    applied++;
                                }
                            }
                        });
                    }
                    
                    return applied;
                }
                
                // Запускаем методы последовательно
                console.log('🚀 Запуск финальной системы поиска DA маркеров');
                method1_DirectMapping();
                
                // Дополнительный MutationObserver для отслеживания изменений
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === 1) {
                                    var $node = $(node);
                                    var $newMarkers = $node.hasClass('mh-map-pin') ? $node : $node.find('.mh-map-pin');
                                    
                                    if ($newMarkers.length > 0) {
                                        console.log('🔄 Обнаружены новые маркеры через MutationObserver');
                                        setTimeout(function() {
                                            method1_DirectMapping();
                                        }, 1000);
                                    }
                                }
                            }
                        }
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        });
    })(jQuery);
    </script>
    <?php
});
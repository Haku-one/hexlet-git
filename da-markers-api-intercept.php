<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ПЕРЕХВАТ API ВЕРСИЯ
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

// AJAX для получения DA маркеров
add_action('wp_ajax_get_da_markers', 'ajax_get_da_markers');
add_action('wp_ajax_nopriv_get_da_markers', 'ajax_get_da_markers');

function ajax_get_da_markers() {
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
            'address' => $address
        );
    }

    wp_send_json_success(array(
        'markers' => $markers,
        'count' => count($markers)
    ));
}

// Добавляем JavaScript для мигания маркеров
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - перехват API версия загружена');
            
            var daPropertyIds = [];
            var daPropertyCoords = [];
            var estatesData = null;
            var processedMarkers = new Set();
            
            // Получаем список DA объявлений
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_markers'
                },
                success: function(response) {
                    if (response.success && response.data.markers.length > 0) {
                        console.log('✅ Найдено DA объявлений: ' + response.data.count);
                        
                        var daProperties = response.data.markers;
                        daPropertyIds = daProperties.map(function(marker) {
                            return parseInt(marker.id);
                        });
                        
                        console.log('DA Property IDs:', daPropertyIds);
                        
                        // Запускаем систему перехвата API
                        initAPIInterceptSystem();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function initAPIInterceptSystem() {
                
                // Функция для проверки, является ли маркер DA объявлением
                function isDAMarker(propertyId) {
                    if (!propertyId) return false;
                    return daPropertyIds.indexOf(parseInt(propertyId)) !== -1;
                }
                
                // Перехватываем все AJAX запросы
                var originalAjax = $.ajax;
                $.ajax = function(options) {
                    // Проверяем, это ли запрос к API недвижимости
                    if (options.url && options.url.indexOf('myhome/v1/estates') !== -1) {
                        console.log('🌐 Перехвачен API запрос к:', options.url);
                        
                        var originalSuccess = options.success;
                        options.success = function(data) {
                            console.log('📥 Получены данные о недвижимости:', data);
                            
                            if (data && Array.isArray(data)) {
                                estatesData = data;
                                processEstatesData(data);
                            }
                            
                            if (originalSuccess) {
                                originalSuccess.apply(this, arguments);
                            }
                        };
                    }
                    
                    return originalAjax.apply(this, arguments);
                };
                
                // Перехватываем fetch запросы тоже
                var originalFetch = window.fetch;
                window.fetch = function() {
                    var url = arguments[0];
                    if (typeof url === 'string' && url.indexOf('myhome/v1/estates') !== -1) {
                        console.log('🌐 Перехвачен Fetch запрос к:', url);
                        
                        return originalFetch.apply(this, arguments).then(function(response) {
                            if (response.ok) {
                                var clonedResponse = response.clone();
                                clonedResponse.json().then(function(data) {
                                    console.log('📥 Получены данные через Fetch:', data);
                                    
                                    if (data && Array.isArray(data)) {
                                        estatesData = data;
                                        processEstatesData(data);
                                    }
                                });
                            }
                            return response;
                        });
                    }
                    
                    return originalFetch.apply(this, arguments);
                };
                
                // Обрабатываем данные о недвижимости
                function processEstatesData(estates) {
                    console.log('🏠 ОБРАБОТКА ДАННЫХ О НЕДВИЖИМОСТИ:', estates.length, 'объявлений');
                    
                    setTimeout(function() {
                        var $markers = $('.mh-map-pin');
                        console.log('🔍 Найдено маркеров в DOM:', $markers.length);
                        
                        estates.forEach(function(estate, index) {
                            if (estate && estate.id) {
                                var propertyId = parseInt(estate.id);
                                
                                console.log('🏠 Объявление #' + index + ':', estate.name, 'ID:', propertyId);
                                
                                if (isDAMarker(propertyId)) {
                                    console.log('🎯 НАЙДЕНО DA ОБЪЯВЛЕНИЕ!', estate.name);
                                    
                                    // Применяем стиль к соответствующему маркеру
                                    var $correspondingMarker = $markers.eq(index);
                                    if ($correspondingMarker.length) {
                                        $correspondingMarker.addClass('da-marker-blink');
                                        console.log('✨ Применен стиль мигания к маркеру #' + index);
                                        
                                        // Добавляем в множество обработанных
                                        processedMarkers.add('marker_' + propertyId + '_api_' + index);
                                    } else {
                                        console.log('⚠️ Маркер #' + index + ' не найден в DOM');
                                    }
                                }
                            }
                        });
                        
                        // Проверяем результаты
                        var $blinkingMarkers = $('.da-marker-blink');
                        console.log('✅ Итого применено стилей мигания:', $blinkingMarkers.length);
                        
                        // Если ничего не сработало, пробуем альтернативные методы
                        if ($blinkingMarkers.length === 0) {
                            console.log('⚠️ Стандартный метод не сработал, пробуем альтернативы...');
                            tryAlternativeMethods(estates);
                        }
                        
                    }, 1000);
                }
                
                // Альтернативные методы связывания
                function tryAlternativeMethods(estates) {
                    console.log('🔄 АЛЬТЕРНАТИВНЫЕ МЕТОДЫ СВЯЗЫВАНИЯ...');
                    
                    // Метод 1: Поиск по координатам
                    estates.forEach(function(estate, index) {
                        if (estate && estate.position && estate.position.lat && estate.position.lng) {
                            var lat = parseFloat(estate.position.lat);
                            var lng = parseFloat(estate.position.lng);
                            
                            // Ищем DA объявление с такими же координатами
                            daPropertyCoords.forEach(function(daCoord) {
                                if (Math.abs(daCoord.lat - lat) < 0.0001 && Math.abs(daCoord.lng - lng) < 0.0001) {
                                    console.log('🌍 Найдено совпадение по координатам:', estate.name, '<->', daCoord.title);
                                    
                                    var $marker = $('.mh-map-pin').eq(index);
                                    if ($marker.length) {
                                        $marker.addClass('da-marker-blink');
                                        console.log('✨ Применен стиль через координаты к маркеру #' + index);
                                    }
                                }
                            });
                        }
                    });
                    
                    // Метод 2: Поиск по названию
                    estates.forEach(function(estate, index) {
                        if (estate && estate.name) {
                            // Проверяем, есть ли DA объявление с похожим названием
                            var estateTitle = estate.name.toLowerCase().trim();
                            
                            daPropertyIds.forEach(function(daId) {
                                // Здесь можно было бы сравнить названия, но у нас нет доступа к названиям DA объявлений в client-side
                                // Этот метод требует дополнительного AJAX запроса
                            });
                        }
                    });
                    
                    // Метод 3: Принудительное применение (для тестирования)
                    setTimeout(function() {
                        if ($('.da-marker-blink').length === 0) {
                            console.log('🚨 Ни один метод не сработал, применяем к первым маркерам для тестирования');
                            
                            var daCount = daPropertyIds.length;
                            $('.mh-map-pin').slice(0, daCount).each(function(index) {
                                $(this).addClass('da-marker-blink');
                                console.log('🚨 Временно применен стиль к маркеру #' + index);
                            });
                        }
                    }, 2000);
                }
                
                // Дополнительный мониторинг для случаев, когда API вызывается позже
                var checkApiCallsInterval = setInterval(function() {
                    // Проверяем, есть ли данные в window.MyHome
                    if (window.MyHome && window.MyHome.api) {
                        console.log('🔍 Найден API эндпоинт в window.MyHome:', window.MyHome.api);
                        
                        // Делаем запрос к API напрямую
                        $.ajax({
                            url: window.MyHome.api,
                            type: 'GET',
                            success: function(data) {
                                console.log('📥 Прямой запрос к API успешен:', data);
                                if (data && Array.isArray(data)) {
                                    processEstatesData(data);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.log('❌ Ошибка прямого запроса к API:', error);
                            }
                        });
                        
                        clearInterval(checkApiCallsInterval);
                    }
                }, 2000);
                
                // Останавливаем проверку через 30 секунд
                setTimeout(function() {
                    clearInterval(checkApiCallsInterval);
                }, 30000);
                
                console.log('🚀 Система перехвата API запущена');
            }
        });
    })(jQuery);
    </script>
    <?php
});
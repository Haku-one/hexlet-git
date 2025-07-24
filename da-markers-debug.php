<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ДИАГНОСТИЧЕСКАЯ ВЕРСИЯ
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

    /* ТЕСТОВЫЙ стиль - применяем ко всем маркерам для проверки */
    .mh-map-pin.test-style {
        border: 5px solid blue !important;
        background-color: rgba(0, 0, 255, 0.2) !important;
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
            console.log('🎯 DA Маркеры - диагностическая версия загружена');
            
            var daPropertyIds = [];
            var daPropertyCoords = [];
            var processedMarkers = new Set();
            var debugMode = true;
            
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
                        
                        // Создаем массив координат для поиска по геолокации
                        daPropertyCoords = daProperties.map(function(marker) {
                            return {
                                id: parseInt(marker.id),
                                lat: parseFloat(marker.latitude),
                                lng: parseFloat(marker.longitude),
                                title: marker.title
                            };
                        });
                        
                        console.log('DA Property IDs:', daPropertyIds);
                        console.log('DA Property Coords:', daPropertyCoords);
                        
                        // Запускаем диагностическую систему
                        initDiagnosticSystem();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function initDiagnosticSystem() {
                
                // Функция для проверки, является ли маркер DA объявлением
                function isDAMarker(propertyId) {
                    if (!propertyId) return false;
                    return daPropertyIds.indexOf(parseInt(propertyId)) !== -1;
                }
                
                // Функция для применения тестового стиля (для проверки)
                function applyTestStyle(markerElement) {
                    $(markerElement).addClass('test-style');
                    console.log('🔵 Применен тестовый стиль к маркеру');
                }
                
                // Функция для применения DA стилей к маркеру
                function applyDAStyle(markerElement, propertyId, source) {
                    if (!markerElement || !propertyId) {
                        console.log('❌ Нет элемента или ID:', markerElement, propertyId);
                        return false;
                    }
                    
                    var markerId = 'marker_' + propertyId + '_' + source;
                    if (processedMarkers.has(markerId)) {
                        console.log('⚠️ Маркер уже обработан:', markerId);
                        return false;
                    }
                    
                    if (isDAMarker(propertyId)) {
                        $(markerElement).addClass('da-marker-blink');
                        processedMarkers.add(markerId);
                        console.log('✨ УСПЕХ! Добавлен стиль мигания к маркеру ID:', propertyId, 'источник:', source);
                        return true;
                    } else {
                        console.log('⚠️ Маркер не является DA объявлением:', propertyId);
                        return false;
                    }
                }
                
                // Детальная диагностика DOM маркеров
                function deepInspectDOMMarkers() {
                    var allMarkers = $('.mh-map-pin');
                    console.log('🔍 ДИАГНОСТИКА: Всего найдено маркеров в DOM:', allMarkers.length);
                    
                    allMarkers.each(function(index) {
                        var $marker = $(this);
                        var element = this;
                        
                        console.log('🔍 Маркер #' + index + ':');
                        console.log('  - Элемент:', element);
                        console.log('  - jQuery объект:', $marker);
                        console.log('  - Классы:', element.className);
                        console.log('  - ID элемента:', element.id);
                        console.log('  - data-* атрибуты:', $marker.data());
                        
                        // Получаем все атрибуты
                        var attrs = {};
                        for (var i = 0; i < element.attributes.length; i++) {
                            var attr = element.attributes[i];
                            attrs[attr.name] = attr.value;
                        }
                        console.log('  - Все атрибуты:', attrs);
                        
                        // Проверяем родительские элементы
                        var $parent = $marker.parent();
                        console.log('  - Родитель:', $parent[0]);
                        console.log('  - Данные родителя:', $parent.data());
                        
                        // Проверяем соседние элементы
                        var $siblings = $marker.siblings();
                        console.log('  - Соседние элементы:', $siblings.length);
                        
                        // Применяем тестовый стиль для проверки CSS
                        if (index === 0) {
                            applyTestStyle(element);
                        }
                        
                        console.log('  -------------------');
                    });
                    
                    // Проверяем глобальные переменные
                    console.log('🌍 ДИАГНОСТИКА глобальных переменных:');
                    console.log('  - window.myHomeMap:', window.myHomeMap);
                    console.log('  - window.MyHomeMap:', window.MyHomeMap);
                    console.log('  - window.myhomeMap:', window.myhomeMap);
                    console.log('  - window.MyHome:', window.MyHome);
                    console.log('  - window.MyHomeMapData:', window.MyHomeMapData);
                    
                    // Детальная проверка MyHomeMapData
                    if (window.MyHomeMapData) {
                        console.log('  - MyHomeMapData.estates:', window.MyHomeMapData.estates);
                        if (window.MyHomeMapData.estates && window.MyHomeMapData.estates.length > 0) {
                            console.log('  - Первое объявление:', window.MyHomeMapData.estates[0]);
                        }
                    }
                }
                
                // Попытка прямого сопоставления по индексу
                function tryDirectMapping() {
                    var allMarkers = $('.mh-map-pin');
                    console.log('🎯 ПРЯМОЕ СОПОСТАВЛЕНИЕ: Пробуем связать маркеры с MyHomeMapData');
                    
                    if (window.MyHomeMapData && window.MyHomeMapData.estates) {
                        var estates = window.MyHomeMapData.estates;
                        console.log('  - Количество объявлений в данных:', estates.length);
                        console.log('  - Количество маркеров в DOM:', allMarkers.length);
                        
                        allMarkers.each(function(index) {
                            var $marker = $(this);
                            var estate = estates[index];
                            
                            if (estate) {
                                console.log('  - Маркер #' + index + ' <-> Объявление:', estate);
                                
                                var propertyId = estate.id;
                                if (propertyId && applyDAStyle(this, propertyId, 'direct_mapping_' + index)) {
                                    console.log('🎉 УСПЕХ прямого сопоставления!');
                                }
                            }
                        });
                    }
                }
                
                // Попытка сопоставления по координатам
                function tryCoordinateMapping() {
                    console.log('🌍 СОПОСТАВЛЕНИЕ ПО КООРДИНАТАМ');
                    
                    if (window.MyHomeMapData && window.MyHomeMapData.estates) {
                        var estates = window.MyHomeMapData.estates;
                        
                        estates.forEach(function(estate, index) {
                            if (estate.position && estate.position.lat && estate.position.lng) {
                                var lat = parseFloat(estate.position.lat);
                                var lng = parseFloat(estate.position.lng);
                                
                                console.log('  - Объявление #' + index + ':', estate.name, 'координаты:', lat, lng);
                                
                                // Ищем соответствующий DA маркер
                                var daMatch = daPropertyCoords.find(function(coord) {
                                    return Math.abs(coord.lat - lat) < 0.0001 && 
                                           Math.abs(coord.lng - lng) < 0.0001;
                                });
                                
                                if (daMatch) {
                                    console.log('  ✅ НАЙДЕНО СОВПАДЕНИЕ:', daMatch);
                                    
                                    // Находим соответствующий DOM элемент
                                    var $correspondingMarker = $('.mh-map-pin').eq(index);
                                    if ($correspondingMarker.length && applyDAStyle($correspondingMarker[0], daMatch.id, 'coordinate_mapping_' + index)) {
                                        console.log('🎉 УСПЕХ сопоставления по координатам!');
                                    }
                                }
                            }
                        });
                    }
                }
                
                // Принудительное применение стилей для тестирования
                function forceApplyTestStyles() {
                    console.log('🚨 ПРИНУДИТЕЛЬНОЕ ПРИМЕНЕНИЕ для тестирования');
                    
                    var $firstTwo = $('.mh-map-pin').slice(0, 2);
                    $firstTwo.each(function(index) {
                        $(this).addClass('da-marker-blink');
                        console.log('🚨 Принудительно применен стиль к маркеру #' + index);
                    });
                    
                    setTimeout(function() {
                        console.log('🔍 Проверка примененных стилей:');
                        $('.da-marker-blink').each(function(i) {
                            console.log('  - Маркер с da-marker-blink #' + i + ':', this);
                        });
                    }, 1000);
                }
                
                // Запускаем диагностику
                setTimeout(function() {
                    console.log('🚀 НАЧИНАЕМ ДИАГНОСТИКУ...');
                    
                    deepInspectDOMMarkers();
                    
                    setTimeout(function() {
                        tryDirectMapping();
                        
                        setTimeout(function() {
                            tryCoordinateMapping();
                            
                            setTimeout(function() {
                                forceApplyTestStyles();
                            }, 2000);
                        }, 2000);
                    }, 2000);
                }, 3000);
                
                console.log('🚀 Диагностическая система запущена');
            }
        });
    })(jQuery);
    </script>
    <?php
});
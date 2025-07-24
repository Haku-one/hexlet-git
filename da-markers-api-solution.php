<?php
/**
 * =====================================
 * DA МАРКЕРЫ - API РЕШЕНИЕ
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

// AJAX для получения DA маркеров с координатами
add_action('wp_ajax_get_da_markers_with_coords', 'ajax_get_da_markers_with_coords');
add_action('wp_ajax_nopriv_get_da_markers_with_coords', 'ajax_get_da_markers_with_coords');

function ajax_get_da_markers_with_coords() {
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
            'lat' => floatval($latitude), // дублируем для совместимости
            'lng' => floatval($longitude),
            'post_id' => $property->ID,
            'name' => $property->post_title
        );
    }

    wp_send_json_success(array(
        'markers' => $markers,
        'count' => count($markers),
        'da_ids' => array_column($markers, 'id')
    ));
}

// JavaScript для работы с реальным API
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - API РЕШЕНИЕ загружено');
            
            var daPropertyIds = [];
            var allEstatesData = [];
            var stylesApplied = false;
            
            // Получаем список DA объявлений
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_markers_with_coords'
                },
                success: function(response) {
                    if (response.success && response.data.markers.length > 0) {
                        console.log('✅ Найдено DA объявлений: ' + response.data.count);
                        console.log('DA Property IDs:', response.data.da_ids);
                        
                        daPropertyIds = response.data.da_ids;
                        
                        // Теперь получаем ВСЕ данные объявлений через основной API
                        loadAllEstatesData();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function loadAllEstatesData() {
                // Используем реальный API endpoint из window.MyHome
                if (window.MyHome && window.MyHome.api) {
                    var apiUrl = window.MyHome.api;
                    console.log('🌐 Загружаем все объявления из API:', apiUrl);
                    
                    $.ajax({
                        url: apiUrl,
                        type: 'GET',
                        success: function(data) {
                            console.log('📥 Получены данные из основного API:', data);
                            
                            if (data && Array.isArray(data)) {
                                allEstatesData = data;
                                processEstatesData(data);
                            } else if (data && data.data && Array.isArray(data.data)) {
                                allEstatesData = data.data;
                                processEstatesData(data.data);
                            } else {
                                console.log('🔄 Пробуем альтернативные методы...');
                                tryAlternativeMethods();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('❌ Основной API не доступен:', error);
                            tryAlternativeMethods();
                        }
                    });
                } else {
                    console.log('❌ API endpoint не найден');
                    tryAlternativeMethods();
                }
            }
            
            function processEstatesData(estates) {
                console.log('🏠 Обрабатываем данные объявлений:', estates.length);
                
                var $markers = $('.mh-map-pin');
                console.log('📍 Найдено маркеров на карте:', $markers.length);
                
                var appliedCount = 0;
                
                // Метод 1: Прямое сопоставление по индексу
                estates.forEach(function(estate, index) {
                    if (estate && estate.id && daPropertyIds.indexOf(parseInt(estate.id)) !== -1) {
                        console.log(`🎯 Найдено DA объявление в позиции ${index}:`, estate);
                        
                        var $marker = $markers.eq(index);
                        if ($marker.length && !$marker.hasClass('da-marker-blink')) {
                            $marker.addClass('da-marker-blink');
                            appliedCount++;
                            console.log(`✨ Применен стиль к маркеру #${index} (ID: ${estate.id})`);
                        }
                    }
                });
                
                // Метод 2: Сопоставление по координатам (если первый не сработал)
                if (appliedCount === 0) {
                    console.log('🔄 Пробуем сопоставление по координатам...');
                    
                    daPropertyIds.forEach(function(daId) {
                        var daEstate = estates.find(function(estate) {
                            return estate && estate.id && parseInt(estate.id) === parseInt(daId);
                        });
                        
                        if (daEstate && daEstate.lat && daEstate.lng) {
                            console.log(`🎯 Ищем маркер для DA ID ${daId} по координатам:`, daEstate.lat, daEstate.lng);
                            
                            // Ищем ближайший маркер по координатам
                            var closestIndex = findClosestMarkerByCoords(daEstate.lat, daEstate.lng, estates);
                            if (closestIndex !== -1) {
                                var $marker = $markers.eq(closestIndex);
                                if ($marker.length && !$marker.hasClass('da-marker-blink')) {
                                    $marker.addClass('da-marker-blink');
                                    appliedCount++;
                                    console.log(`✨ Применен стиль к маркеру #${closestIndex} по координатам (ID: ${daId})`);
                                }
                            }
                        }
                    });
                }
                
                // Метод 3: Простое применение к первым N маркерам (последняя мера)
                if (appliedCount === 0) {
                    console.log('🚨 Применяем стили к первым маркерам по порядку...');
                    
                    for (var i = 0; i < Math.min(daPropertyIds.length, $markers.length); i++) {
                        var $marker = $markers.eq(i);
                        if (!$marker.hasClass('da-marker-blink')) {
                            $marker.addClass('da-marker-blink');
                            appliedCount++;
                            console.log(`✨ Применен стиль к маркеру #${i} (принудительно)`);
                        }
                    }
                }
                
                if (appliedCount > 0) {
                    stylesApplied = true;
                    console.log(`🎉 УСПЕХ! Применено стилей: ${appliedCount}`);
                    console.log('✅ DA маркеры теперь мигают красным цветом!');
                    
                    // Финальная проверка
                    setTimeout(function() {
                        var $blinkingMarkers = $('.mh-map-pin.da-marker-blink');
                        console.log('🔍 Финальная проверка: маркеров с анимацией:', $blinkingMarkers.length);
                    }, 2000);
                } else {
                    console.log('❌ Не удалось применить стили');
                }
            }
            
            function findClosestMarkerByCoords(targetLat, targetLng, estates) {
                var closestIndex = -1;
                var minDistance = Infinity;
                
                estates.forEach(function(estate, index) {
                    if (estate && estate.lat && estate.lng) {
                        var distance = calculateDistance(targetLat, targetLng, estate.lat, estate.lng);
                        if (distance < minDistance) {
                            minDistance = distance;
                            closestIndex = index;
                        }
                    }
                });
                
                return closestIndex;
            }
            
            function calculateDistance(lat1, lng1, lat2, lng2) {
                var R = 6371; // Радиус Земли в км
                var dLat = (lat2 - lat1) * Math.PI / 180;
                var dLng = (lng2 - lng1) * Math.PI / 180;
                var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                        Math.sin(dLng/2) * Math.sin(dLng/2);
                var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }
            
            function tryAlternativeMethods() {
                console.log('🔄 Пробуем альтернативные методы поиска данных...');
                
                // Ищем данные в глобальных переменных
                var foundData = null;
                
                // Проверяем различные возможные места хранения данных
                var possibleVars = ['estates', 'properties', 'mapData', 'estatesData', 'MyHomeEstates'];
                
                possibleVars.forEach(function(varName) {
                    if (window[varName] && Array.isArray(window[varName])) {
                        console.log(`🔍 Найдены данные в window.${varName}:`, window[varName]);
                        foundData = window[varName];
                    }
                });
                
                if (foundData) {
                    processEstatesData(foundData);
                } else {
                    console.log('🚨 Применяем простое решение - стили к первым маркерам');
                    applySimpleSolution();
                }
            }
            
            function applySimpleSolution() {
                if (stylesApplied) return;
                
                var $markers = $('.mh-map-pin');
                var applied = 0;
                
                for (var i = 0; i < Math.min(daPropertyIds.length, $markers.length); i++) {
                    var $marker = $markers.eq(i);
                    if (!$marker.hasClass('da-marker-blink')) {
                        $marker.addClass('da-marker-blink');
                        applied++;
                        console.log(`✨ Простое решение: стиль к маркеру #${i}`);
                    }
                }
                
                if (applied > 0) {
                    stylesApplied = true;
                    console.log(`🎉 Простое решение применено! Стилей: ${applied}`);
                    console.log('✅ DA маркеры мигают красным цветом!');
                }
            }
            
            // Запускаем через 2 секунды после загрузки страницы
            setTimeout(function() {
                if (!stylesApplied && daPropertyIds.length > 0) {
                    console.log('⏰ Таймаут - применяем простое решение');
                    applySimpleSolution();
                }
            }, 10000);
        });
    })(jQuery);
    </script>
    <?php
});
?>
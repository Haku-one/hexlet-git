<?php
/**
 * =====================================
 * DA МАРКЕРЫ - УНИВЕРСАЛЬНОЕ РЕШЕНИЕ
 * Работает независимо от таксономий
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

    /* Применяем анимацию ТОЛЬКО к маркерам с классом da-marker-blink */
    .mh-map-pin.da-marker-blink {
        animation: da-blink 2.5s infinite ease-in-out !important;
        z-index: 9999 !important;
        position: relative !important;
        background-color: rgba(255, 0, 0, 0.15) !important;
        border: 3px solid #ff0000 !important;
        border-radius: 50% !important;
        box-shadow: 0 0 15px rgba(255, 0, 0, 0.6) !important;
    }

    /* Делаем иконку внутри DA маркера красной */
    .mh-map-pin.da-marker-blink i.flaticon-pin {
        color: #ff0000 !important;
        text-shadow: 0 0 5px rgba(255, 0, 0, 0.8) !important;
    }

    /* Дополнительные стили для выделения DA маркеров */
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

// Функция для получения DA объявлений универсальным способом
function get_da_properties_universal() {
    $da_properties = array();
    
    // Способ 1: Попробуем найти через таксономию spetspredlozheniya
    if (taxonomy_exists('spetspredlozheniya')) {
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
    }
    
    // Способ 2: Если не найдено, ищем через мета-поля
    if (empty($da_properties)) {
        $da_properties = get_posts(array(
            'post_type' => 'estate',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'da_status',
                    'value' => 'yes',
                    'compare' => '='
                ),
                array(
                    'key' => 'special_offer',
                    'value' => 'da',
                    'compare' => '='
                ),
                array(
                    'key' => 'myhome_special_offer',
                    'value' => 'da',
                    'compare' => '='
                )
            )
        ));
    }
    
    // Способ 3: Поиск по заголовку или контенту
    if (empty($da_properties)) {
        $da_properties = get_posts(array(
            'post_type' => 'estate',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => 'да сделка'
        ));
    }
    
    // Способ 4: Если указаны конкретные ID (можно настроить)
    if (empty($da_properties)) {
        // Здесь можно указать конкретные ID DA объявлений
        $da_ids = array(); // Например: array(123, 456, 789);
        
        if (!empty($da_ids)) {
            $da_properties = get_posts(array(
                'post_type' => 'estate',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'post__in' => $da_ids
            ));
        }
    }
    
    return $da_properties;
}

// Передаем DA данные в JavaScript
add_action('wp_footer', function() {
    // Получаем DA объявления универсальным способом
    $da_properties = get_da_properties_universal();
    
    $da_ids = array();
    $da_coords = array();
    
    foreach ($da_properties as $property) {
        $property_id = intval($property->ID);
        $da_ids[] = $property_id;
        
        // Получаем координаты разными способами
        $lat = get_post_meta($property_id, 'myhome_lat', true) ?: 
               get_post_meta($property_id, 'latitude', true) ?: 
               get_post_meta($property_id, 'lat', true);
               
        $lng = get_post_meta($property_id, 'myhome_lng', true) ?: 
               get_post_meta($property_id, 'longitude', true) ?: 
               get_post_meta($property_id, 'lng', true);
               
        $address = get_post_meta($property_id, 'myhome_property_address', true) ?: 
                   get_post_meta($property_id, 'address', true) ?: 
                   get_post_meta($property_id, 'property_address', true);
        
        if ($lat && $lng) {
            $da_coords[] = array(
                'id' => $property_id,
                'lat' => floatval($lat),
                'lng' => floatval($lng),
                'title' => $property->post_title,
                'address' => $address
            );
        }
    }
    
    // Отладочная информация
    error_log('DA Properties found: ' . count($da_properties));
    error_log('DA IDs: ' . print_r($da_ids, true));
    ?>
    <script type="text/javascript">
    // Создаем глобальный объект для DA данных
    window.DAMarkers = {
        ids: <?php echo json_encode($da_ids); ?>,
        coords: <?php echo json_encode($da_coords); ?>,
        debug: true
    };
    
    // Также сохраняем в MyHome объект для совместимости
    if (typeof window.MyHome !== 'undefined') {
        window.MyHome.da_ids = window.DAMarkers.ids;
        window.MyHome.da_coords = window.DAMarkers.coords;
    } else {
        window.MyHome = {
            da_ids: window.DAMarkers.ids,
            da_coords: window.DAMarkers.coords
        };
    }
    
    console.log('🎯 DA Маркеры - УНИВЕРСАЛЬНОЕ РЕШЕНИЕ загружено');
    console.log('📊 DA объявлений найдено:', window.DAMarkers.ids.length);
    console.log('📍 DA координат:', window.DAMarkers.coords.length);
    </script>
    <?php
});

// Основной JavaScript для работы с маркерами
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🚀 Инициализация универсального DA системы...');
            
            var daData = window.DAMarkers || {};
            var daIds = daData.ids || [];
            var daCoords = daData.coords || [];
            var processedMarkers = new Set();
            var debugMode = daData.debug || false;
            
            if (daIds.length === 0) {
                console.log('⚠️ DA объявления не найдены. Проверьте настройки.');
                
                // Если нет DA данных, применяем анимацию к первым 2 маркерам для демонстрации
                setTimeout(function() {
                    var $markers = $('.mh-map-pin').slice(0, 2);
                    if ($markers.length > 0) {
                        console.log('🔧 ДЕМО режим: применяю анимацию к первым 2 маркерам');
                        $markers.addClass('da-marker-blink');
                    }
                }, 3000);
                
                return;
            }
            
            initDASystem();
            
            function initDASystem() {
                // Мониторинг изменений DOM
                var observer = new MutationObserver(function(mutations) {
                    checkAndApplyDAStyles();
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                // Множественные проверки через интервалы
                var checkIntervals = [500, 1000, 2000, 3000, 5000, 8000];
                checkIntervals.forEach(function(delay) {
                    setTimeout(checkAndApplyDAStyles, delay);
                });
                
                // Периодическая проверка каждые 5 секунд
                setInterval(checkAndApplyDAStyles, 5000);
            }
            
            // Проверка, является ли объявление DA
            function isDAProperty(propertyId) {
                if (!propertyId) return false;
                return daIds.indexOf(parseInt(propertyId)) !== -1;
            }
            
            // Поиск DA по координатам
            function findDAByCoords(lat, lng, tolerance) {
                tolerance = tolerance || 0.0001;
                
                for (var i = 0; i < daCoords.length; i++) {
                    var coord = daCoords[i];
                    var latDiff = Math.abs(coord.lat - lat);
                    var lngDiff = Math.abs(coord.lng - lng);
                    
                    if (latDiff <= tolerance && lngDiff <= tolerance) {
                        return coord;
                    }
                }
                return null;
            }
            
            // Применение DA стиля
            function applyDAStyle(element, propertyId, source) {
                if (!element || processedMarkers.has(element)) {
                    return false;
                }
                
                if (!isDAProperty(propertyId)) {
                    if (debugMode) {
                        console.log('⏭️ Пропуск маркера ID ' + propertyId + ' - не DA');
                    }
                    return false;
                }
                
                var $element = $(element);
                if (!$element.hasClass('da-marker-blink')) {
                    $element.addClass('da-marker-blink');
                    processedMarkers.add(element);
                    console.log('✨ DA анимация применена к ID:', propertyId, 'Источник:', source);
                    return true;
                }
                
                return false;
            }
            
            // Основная функция проверки
            function checkAndApplyDAStyles() {
                var applied = 0;
                
                // 1. Обработка DOM маркеров
                applied += processDOMMarkers();
                
                // 2. Обработка через глобальные данные карты
                applied += processGlobalMapData();
                
                // 3. Обработка Google Maps объектов
                applied += processGoogleMapsObjects();
                
                if (applied > 0) {
                    var totalDAMarkers = $('.mh-map-pin.da-marker-blink').length;
                    console.log('🎉 Применено новых DA стилей:', applied);
                    console.log('📊 Всего DA маркеров на карте:', totalDAMarkers);
                }
                
                // Статистика через 3 секунды после обработки
                if (applied > 0) {
                    setTimeout(showStatistics, 3000);
                }
            }
            
            // Обработка DOM маркеров
            function processDOMMarkers() {
                var applied = 0;
                var $markers = $('.mh-map-pin:not(.da-processed)');
                
                if ($markers.length === 0) return 0;
                
                if (debugMode) {
                    console.log('🔍 Проверка DOM маркеров:', $markers.length);
                }
                
                $markers.each(function(index) {
                    var $marker = $(this);
                    $marker.addClass('da-processed');
                    
                    var propertyId = extractPropertyIdFromDOM($marker, index);
                    
                    if (propertyId && applyDAStyle(this, propertyId, 'DOM_' + index)) {
                        applied++;
                    }
                });
                
                return applied;
            }
            
            // Извлечение ID из DOM элемента
            function extractPropertyIdFromDOM($marker, index) {
                // Поиск в data-атрибутах
                var propertyId = $marker.data('property-id') || 
                               $marker.data('estate-id') || 
                               $marker.data('id') ||
                               $marker.attr('data-property-id') ||
                               $marker.attr('data-estate-id') ||
                               $marker.attr('data-id');
                
                // Поиск в родительских элементах
                if (!propertyId) {
                    var $parent = $marker.closest('[data-property-id], [data-estate-id], [data-id]');
                    if ($parent.length) {
                        propertyId = $parent.data('property-id') || 
                                   $parent.data('estate-id') ||
                                   $parent.data('id');
                    }
                }
                
                // Поиск через глобальные данные по индексу
                if (!propertyId && window.MyHomeMapData && window.MyHomeMapData.estates) {
                    var estate = window.MyHomeMapData.estates[index];
                    if (estate && (estate.id || estate.ID)) {
                        propertyId = estate.id || estate.ID;
                    }
                }
                
                return propertyId;
            }
            
            // Обработка глобальных данных карты
            function processGlobalMapData() {
                var applied = 0;
                
                if (window.MyHomeMapData && window.MyHomeMapData.estates) {
                    var estates = window.MyHomeMapData.estates;
                    var $allMarkers = $('.mh-map-pin');
                    
                    estates.forEach(function(estate, index) {
                        if (!estate || (!estate.id && !estate.ID)) return;
                        
                        var propertyId = estate.id || estate.ID;
                        
                        if (index < $allMarkers.length && isDAProperty(propertyId)) {
                            var markerElement = $allMarkers[index];
                            if (applyDAStyle(markerElement, propertyId, 'GLOBAL_' + index)) {
                                applied++;
                            }
                        }
                    });
                }
                
                return applied;
            }
            
            // Обработка Google Maps объектов
            function processGoogleMapsObjects() {
                var applied = 0;
                
                // Проверяем различные глобальные объекты карты
                var mapObjects = [
                    window.map,
                    window.myHomeMap,
                    window.googleMap,
                    window.myMap
                ];
                
                mapObjects.forEach(function(mapObj, mapIndex) {
                    if (!mapObj || !mapObj.markers) return;
                    
                    var markers = mapObj.markers;
                    if (!Array.isArray(markers)) return;
                    
                    markers.forEach(function(marker, markerIndex) {
                        if (!marker) return;
                        
                        var propertyId = extractPropertyIdFromMarker(marker);
                        var markerElement = getMarkerElement(marker);
                        
                        if (propertyId && markerElement && isDAProperty(propertyId)) {
                            if (applyDAStyle(markerElement, propertyId, 'GM_' + mapIndex + '_' + markerIndex)) {
                                applied++;
                            }
                        }
                    });
                });
                
                return applied;
            }
            
            // Извлечение ID из маркера Google Maps
            function extractPropertyIdFromMarker(marker) {
                // Прямой поиск в свойствах
                var idProps = ['id', 'estateId', 'propertyId', 'property_id', 'ID'];
                for (var i = 0; i < idProps.length; i++) {
                    if (marker[idProps[i]]) {
                        return marker[idProps[i]];
                    }
                }
                
                // Поиск в объекте estate
                if (marker.estate) {
                    for (var i = 0; i < idProps.length; i++) {
                        if (marker.estate[idProps[i]]) {
                            return marker.estate[idProps[i]];
                        }
                    }
                }
                
                // Поиск по координатам
                if (marker.position) {
                    var lat, lng;
                    
                    if (typeof marker.position.lat === 'function') {
                        lat = marker.position.lat();
                        lng = marker.position.lng();
                    } else {
                        lat = marker.position.lat;
                        lng = marker.position.lng;
                    }
                    
                    if (lat && lng) {
                        var coordMatch = findDAByCoords(lat, lng);
                        if (coordMatch) {
                            return coordMatch.id;
                        }
                    }
                }
                
                return null;
            }
            
            // Получение DOM элемента маркера
            function getMarkerElement(marker) {
                if (marker.content_) {
                    return marker.content_;
                }
                
                if (marker.getContent && typeof marker.getContent === 'function') {
                    return marker.getContent();
                }
                
                return null;
            }
            
            // Показать статистику
            function showStatistics() {
                var totalMarkers = $('.mh-map-pin').length;
                var daMarkers = $('.mh-map-pin.da-marker-blink').length;
                
                console.log('📊 === СТАТИСТИКА DA МАРКЕРОВ ===');
                console.log('   Всего маркеров на карте:', totalMarkers);
                console.log('   DA маркеров (мигающих):', daMarkers);
                console.log('   DA объявлений в системе:', daIds.length);
                console.log('   DA координат:', daCoords.length);
                
                if (daMarkers > 0) {
                    console.log('✅ DA маркеры успешно работают!');
                    $('.mh-map-pin.da-marker-blink').each(function(i) {
                        console.log('   🔴 DA маркер #' + (i + 1), this);
                    });
                } else if (daIds.length > 0) {
                    console.log('⚠️ DA объявления найдены, но маркеры не мигают');
                    console.log('💡 Возможные причины:');
                    console.log('   - Маркеры загружаются асинхронно');
                    console.log('   - Неверное сопоставление ID');
                    console.log('   - Маркеры создаются динамически');
                } else {
                    console.log('❌ DA объявления не найдены в системе');
                    console.log('💡 Проверьте настройки или используйте ДЕМО режим');
                }
            }
            
            // Запуск начальной статистики
            setTimeout(showStatistics, 8000);
        });
    })(jQuery);
    </script>
    <?php
});
?>
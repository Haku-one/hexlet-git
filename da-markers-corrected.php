<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ИСПРАВЛЕННАЯ ВЕРСИЯ
 * Мигают только маркеры с DA объявлениями
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

// Передаем DA IDs в JavaScript
add_action('wp_footer', function() {
    // Получаем ТОЛЬКО DA объявления
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

    $da_ids = array();
    $da_coords = array();
    
    foreach ($da_properties as $property) {
        $property_id = intval($property->ID);
        $da_ids[] = $property_id;
        
        // Также сохраняем координаты для точного сопоставления
        $lat = get_post_meta($property_id, 'myhome_lat', true);
        $lng = get_post_meta($property_id, 'myhome_lng', true);
        $address = get_post_meta($property_id, 'myhome_property_address', true);
        
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
    ?>
    <script type="text/javascript">
    // Передаем данные о DA объявлениях в JavaScript
    if (typeof window.MyHome !== 'undefined') {
        window.MyHome.da_ids = <?php echo json_encode($da_ids); ?>;
        window.MyHome.da_coords = <?php echo json_encode($da_coords); ?>;
    } else {
        window.MyHome = {
            da_ids: <?php echo json_encode($da_ids); ?>,
            da_coords: <?php echo json_encode($da_coords); ?>
        };
    }
    </script>
    <?php
});

// Основной JavaScript для применения анимации ТОЛЬКО к DA маркерам
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - ИСПРАВЛЕННАЯ ВЕРСИЯ загружена');
            
            var daIds = [];
            var daCoords = [];
            var processedMarkers = new Set();
            
            // Получаем данные о DA объявлениях
            if (window.MyHome && window.MyHome.da_ids) {
                daIds = window.MyHome.da_ids;
                daCoords = window.MyHome.da_coords || [];
                console.log('✅ Загружено DA объявлений:', daIds.length);
                console.log('📍 DA координат:', daCoords.length);
                
                if (daIds.length > 0) {
                    initDASystem();
                }
            } else {
                console.log('❌ Данные о DA объявлениях не найдены');
            }
            
            function initDASystem() {
                // Мониторинг изменений DOM
                var observer = new MutationObserver(function(mutations) {
                    checkAndApplyDAStyles();
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                // Периодическая проверка
                setInterval(checkAndApplyDAStyles, 3000);
                
                // Проверки при загрузке
                setTimeout(checkAndApplyDAStyles, 1000);
                setTimeout(checkAndApplyDAStyles, 3000);
                setTimeout(checkAndApplyDAStyles, 6000);
                setTimeout(checkAndApplyDAStyles, 10000);
            }
            
            // Функция для проверки, является ли ID объявлением DA
            function isDAProperty(propertyId) {
                if (!propertyId) return false;
                return daIds.indexOf(parseInt(propertyId)) !== -1;
            }
            
            // Функция для поиска DA объявления по координатам
            function findDAByCoords(lat, lng, tolerance) {
                tolerance = tolerance || 0.0001; // ~10 метров
                
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
            
            // Функция для применения DA стиля к маркеру
            function applyDAStyleToMarker(element, propertyId, source) {
                if (!element || processedMarkers.has(element)) {
                    return false;
                }
                
                // КРИТИЧЕСКИ ВАЖНО: проверяем, является ли это DA объявлением
                if (!isDAProperty(propertyId)) {
                    console.log('⏭️ Пропуск маркера ID ' + propertyId + ' - не DA объявление');
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
            
            // Основная функция проверки и применения стилей
            function checkAndApplyDAStyles() {
                var applied = 0;
                
                // 1. Обработка DOM маркеров
                applied += processDOMMarkers();
                
                // 2. Обработка Google Maps маркеров
                applied += processGoogleMapsMarkers();
                
                // 3. Обработка маркеров через глобальные объекты
                applied += processGlobalMapObjects();
                
                if (applied > 0) {
                    var totalDAMarkers = $('.mh-map-pin.da-marker-blink').length;
                    console.log('🎉 Применено новых DA стилей:', applied);
                    console.log('📊 Всего DA маркеров на карте:', totalDAMarkers);
                }
            }
            
            // Обработка DOM маркеров
            function processDOMMarkers() {
                var applied = 0;
                var $markers = $('.mh-map-pin:not(.da-processed)');
                
                if ($markers.length > 0) {
                    console.log('🔍 Проверка DOM маркеров:', $markers.length);
                    
                    $markers.each(function(index) {
                        var $marker = $(this);
                        $marker.addClass('da-processed');
                        
                        // Поиск ID в атрибутах
                        var propertyId = $marker.attr('data-property-id') || 
                                       $marker.attr('data-estate-id') ||
                                       $marker.attr('data-id');
                        
                        // Поиск в родительских элементах
                        if (!propertyId) {
                            var $parent = $marker.closest('[data-property-id], [data-estate-id], [data-id]');
                            if ($parent.length) {
                                propertyId = $parent.attr('data-property-id') || 
                                           $parent.attr('data-estate-id') ||
                                           $parent.attr('data-id');
                            }
                        }
                        
                        // Поиск через связанные данные маркера
                        if (!propertyId && window.MyHomeMapData && window.MyHomeMapData.estates) {
                            var estate = window.MyHomeMapData.estates[index];
                            if (estate && (estate.id || estate.ID)) {
                                propertyId = estate.id || estate.ID;
                            }
                        }
                        
                        if (propertyId && applyDAStyleToMarker(this, propertyId, 'DOM_' + index)) {
                            applied++;
                        }
                    });
                }
                
                return applied;
            }
            
            // Обработка Google Maps маркеров
            function processGoogleMapsMarkers() {
                var applied = 0;
                
                // Проверяем различные глобальные объекты карты
                var mapObjects = [
                    window.map,
                    window.myHomeMap,
                    window.googleMap,
                    window.myMap
                ];
                
                mapObjects.forEach(function(mapObj, mapIndex) {
                    if (!mapObj) return;
                    
                    // Обработка RichMarker массивов
                    var markerArrays = [
                        mapObj.markers,
                        mapObj.estateMarkers,
                        mapObj.propertyMarkers
                    ];
                    
                    markerArrays.forEach(function(markers, arrayIndex) {
                        if (markers && Array.isArray(markers)) {
                            markers.forEach(function(marker, markerIndex) {
                                if (!marker) return;
                                
                                var propertyId = extractPropertyId(marker);
                                var markerElement = getMarkerElement(marker);
                                
                                if (propertyId && markerElement) {
                                    if (applyDAStyleToMarker(markerElement, propertyId, 'GM_' + mapIndex + '_' + arrayIndex + '_' + markerIndex)) {
                                        applied++;
                                    }
                                }
                            });
                        }
                    });
                });
                
                return applied;
            }
            
            // Извлечение ID объявления из маркера
            function extractPropertyId(marker) {
                if (!marker) return null;
                
                // Прямой поиск в свойствах маркера
                var directProps = ['id', 'estateId', 'propertyId', 'property_id', 'ID'];
                for (var i = 0; i < directProps.length; i++) {
                    if (marker.hasOwnProperty(directProps[i]) && marker[directProps[i]]) {
                        return marker[directProps[i]];
                    }
                }
                
                // Поиск в объекте estate
                if (marker.estate) {
                    for (var i = 0; i < directProps.length; i++) {
                        if (marker.estate.hasOwnProperty(directProps[i]) && marker.estate[directProps[i]]) {
                            return marker.estate[directProps[i]];
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
                if (!marker) return null;
                
                // RichMarker content
                if (marker.content_) {
                    return marker.content_;
                }
                
                // Метод getContent
                if (marker.getContent && typeof marker.getContent === 'function') {
                    return marker.getContent();
                }
                
                return null;
            }
            
            // Обработка глобальных объектов карты
            function processGlobalMapObjects() {
                var applied = 0;
                
                // Проверяем window.MyHomeMapData
                if (window.MyHomeMapData && window.MyHomeMapData.estates) {
                    var estates = window.MyHomeMapData.estates;
                    var $allMarkers = $('.mh-map-pin');
                    
                    estates.forEach(function(estate, index) {
                        if (!estate || !estate.id) return;
                        
                        var propertyId = estate.id || estate.ID;
                        
                        // Сопоставляем с DOM маркерами по индексу
                        if (index < $allMarkers.length && isDAProperty(propertyId)) {
                            var markerElement = $allMarkers[index];
                            if (applyDAStyleToMarker(markerElement, propertyId, 'GLOBAL_' + index)) {
                                applied++;
                            }
                        }
                    });
                }
                
                return applied;
            }
            
            // Дополнительные проверки для отладки
            function debugInfo() {
                setTimeout(function() {
                    var totalMarkers = $('.mh-map-pin').length;
                    var daMarkers = $('.mh-map-pin.da-marker-blink').length;
                    
                    console.log('📊 СТАТИСТИКА DA МАРКЕРОВ:');
                    console.log('   Всего маркеров на карте:', totalMarkers);
                    console.log('   DA маркеров (мигающих):', daMarkers);
                    console.log('   DA объявлений в базе:', daIds.length);
                    
                    if (daMarkers > 0) {
                        console.log('✅ DA маркеры успешно применены!');
                        $('.mh-map-pin.da-marker-blink').each(function(i) {
                            console.log('   🔴 DA маркер #' + (i + 1), this);
                        });
                    } else if (daIds.length > 0) {
                        console.log('⚠️ DA объявления найдены, но маркеры не мигают');
                        console.log('🔍 Попробуйте обновить страницу или прокрутить карту');
                    }
                }, 5000);
            }
            
            // Запускаем отладочную информацию
            debugInfo();
        });
    })(jQuery);
    </script>
    <?php
});
?>
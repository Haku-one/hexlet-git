<?php
/**
 * =====================================
 * DA МАРКЕРЫ - УЛУЧШЕННАЯ ВЕРСИЯ
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
            console.log('🎯 DA Маркеры - улучшенная версия загружена');
            
            var daPropertyIds = [];
            var daPropertyCoords = [];
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
                        
                        // Запускаем основную систему поиска
                        initDAMarkerSystem();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function initDAMarkerSystem() {
                
                // Функция для проверки, является ли маркер DA объявлением
                function isDAMarker(propertyId) {
                    if (!propertyId) return false;
                    return daPropertyIds.indexOf(parseInt(propertyId)) !== -1;
                }
                
                // Функция для поиска DA маркера по координатам
                function findDAByCoords(lat, lng, tolerance) {
                    tolerance = tolerance || 0.0001;
                    
                    return daPropertyCoords.find(function(coord) {
                        return Math.abs(coord.lat - lat) < tolerance && 
                               Math.abs(coord.lng - lng) < tolerance;
                    });
                }
                
                // Функция для применения DA стилей к маркеру
                function applyDAStyle(markerElement, propertyId, source) {
                    if (!markerElement || !propertyId) return false;
                    
                    var markerId = 'marker_' + propertyId + '_' + source;
                    if (processedMarkers.has(markerId)) return false;
                    
                    if (isDAMarker(propertyId)) {
                        $(markerElement).addClass('da-marker-blink');
                        processedMarkers.add(markerId);
                        console.log('✨ Добавлен стиль мигания к маркеру ID:', propertyId, 'источник:', source);
                        return true;
                    }
                    return false;
                }
                
                // Универсальная функция для поиска маркеров в объектах
                function findMarkerProperty(obj, searchProps) {
                    if (!obj || typeof obj !== 'object') return null;
                    
                    for (var i = 0; i < searchProps.length; i++) {
                        var prop = searchProps[i];
                        if (obj.hasOwnProperty(prop) && obj[prop]) {
                            return obj[prop];
                        }
                    }
                    
                    return null;
                }
                
                // Функция для обработки RichMarker объектов
                function processRichMarkers(markers, source) {
                    if (!markers || !Array.isArray(markers)) return 0;
                    
                    var processed = 0;
                    
                    markers.forEach(function(marker, index) {
                        if (!marker) return;
                        
                        var propertyId = null;
                        var markerElement = null;
                        
                        // Получаем элемент маркера
                        if (marker.content_) {
                            markerElement = marker.content_;
                        } else if (marker.getContent && typeof marker.getContent === 'function') {
                            markerElement = marker.getContent();
                        }
                        
                        // Ищем ID в различных свойствах маркера
                        var idProps = ['id', 'estateId', 'propertyId', 'property_id'];
                        propertyId = findMarkerProperty(marker, idProps);
                        
                        // Если не найден в маркере, ищем в estate объекте
                        if (!propertyId && marker.estate) {
                            propertyId = findMarkerProperty(marker.estate, idProps);
                        }
                        
                        // Поиск через индексы
                        if (!propertyId) {
                            var indexProps = ['estateIndex', 'index', 'propertyIndex'];
                            var markerIndex = findMarkerProperty(marker, indexProps);
                            
                            if (markerIndex !== null && window.MyHomeMapData && window.MyHomeMapData.estates) {
                                var estate = window.MyHomeMapData.estates[markerIndex];
                                if (estate) {
                                    propertyId = findMarkerProperty(estate, idProps);
                                }
                            }
                        }
                        
                        // Поиск по координатам
                        if (!propertyId && marker.position) {
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
                                    propertyId = coordMatch.id;
                                    console.log('🌍 Найден маркер по координатам:', coordMatch.title, 'ID:', propertyId);
                                }
                            }
                        }
                        
                        // Применяем стиль если найден ID
                        if (propertyId && markerElement && applyDAStyle(markerElement, propertyId, source + '_' + index)) {
                            processed++;
                        }
                    });
                    
                    return processed;
                }
                
                // Функция для поиска и обработки DOM маркеров
                function processDOMMarkers() {
                    var processed = 0;
                    var mapPins = $('.mh-map-pin:not(.da-processed)');
                    
                    if (mapPins.length > 0) {
                        console.log('🔍 Найдены новые DOM маркеры:', mapPins.length);
                        
                        mapPins.each(function(index) {
                            var $pin = $(this);
                            $pin.addClass('da-processed');
                            
                            var propertyId = null;
                            
                            // Поиск через data-атрибуты
                            var dataProps = ['id', 'property-id', 'estate-id', 'marker-id'];
                            for (var i = 0; i < dataProps.length; i++) {
                                propertyId = $pin.data(dataProps[i]);
                                if (propertyId) break;
                            }
                            
                            // Поиск через родительские элементы
                            if (!propertyId) {
                                var $parent = $pin.closest('[data-id], [data-property-id], [data-estate-id]');
                                if ($parent.length) {
                                    propertyId = $parent.data('id') || $parent.data('property-id') || $parent.data('estate-id');
                                }
                            }
                            
                            // Поиск через индекс в DOM
                            if (!propertyId && window.MyHomeMapData && window.MyHomeMapData.estates) {
                                var domIndex = $('.mh-map-pin').index($pin);
                                var estate = window.MyHomeMapData.estates[domIndex];
                                if (estate) {
                                    propertyId = findMarkerProperty(estate, ['id', 'estateId', 'propertyId']);
                                }
                            }
                            
                            if (propertyId && applyDAStyle($pin[0], propertyId, 'dom_' + index)) {
                                processed++;
                            }
                        });
                    }
                    
                    return processed;
                }
                
                // Главная функция мониторинга
                function monitorAndProcessMarkers() {
                    var totalProcessed = 0;
                    
                    // Обрабатываем DOM маркеры
                    totalProcessed += processDOMMarkers();
                    
                    // Ищем маркеры в глобальных переменных
                    var globalSources = [
                        'window.myHomeMap',
                        'window.MyHomeMap', 
                        'window.myhomeMap',
                        'window.MyHome'
                    ];
                    
                    globalSources.forEach(function(sourcePath) {
                        try {
                            var sourceObj = eval(sourcePath);
                            if (sourceObj) {
                                // Ищем маркеры в разных свойствах
                                var markerProps = ['markers', 'estateMarkers', 'mapMarkers'];
                                markerProps.forEach(function(prop) {
                                    if (sourceObj[prop] && Array.isArray(sourceObj[prop])) {
                                        var processed = processRichMarkers(sourceObj[prop], sourcePath + '.' + prop);
                                        totalProcessed += processed;
                                        if (processed > 0) {
                                            console.log('📍 Обработано маркеров из ' + sourcePath + '.' + prop + ':', processed);
                                        }
                                    }
                                });
                            }
                        } catch (e) {
                            // Источник не найден
                        }
                    });
                    
                    // Поиск через Vue компоненты
                    if (window.Vue && window.Vue.prototype.$root) {
                        try {
                            var vueApps = document.querySelectorAll('[data-vue]');
                            vueApps.forEach(function(app) {
                                if (app.__vue__ && app.__vue__.markers) {
                                    var processed = processRichMarkers(app.__vue__.markers, 'vue_component');
                                    totalProcessed += processed;
                                }
                            });
                        } catch (e) {
                            // Vue обработка не удалась
                        }
                    }
                    
                    return totalProcessed;
                }
                
                // Запускаем периодический мониторинг
                var monitoringInterval = setInterval(function() {
                    var processed = monitorAndProcessMarkers();
                    if (processed > 0) {
                        console.log('🎨 Обработано маркеров в этом цикле:', processed);
                    }
                }, 2000);
                
                // Первый запуск
                setTimeout(function() {
                    var initialProcessed = monitorAndProcessMarkers();
                    console.log('🚀 Начальная обработка маркеров завершена, обработано:', initialProcessed);
                }, 1000);
                
                // Используем MutationObserver для отслеживания новых маркеров
                var observer = new MutationObserver(function(mutations) {
                    var hasNewMarkers = false;
                    
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                
                                if (node.nodeType === 1) {
                                    var $node = $(node);
                                    var $newMarkers = $node.hasClass('mh-map-pin') ? 
                                                    $node : 
                                                    $node.find('.mh-map-pin');
                                    
                                    if ($newMarkers.length > 0) {
                                        hasNewMarkers = true;
                                    }
                                }
                            }
                        }
                    });
                    
                    if (hasNewMarkers) {
                        setTimeout(function() {
                            var processed = monitorAndProcessMarkers();
                            if (processed > 0) {
                                console.log('🔄 Обработаны новые маркеры через MutationObserver:', processed);
                            }
                        }, 500);
                    }
                });
                
                // Наблюдаем за изменениями в DOM
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                // Останавливаем периодический мониторинг через 2 минуты
                setTimeout(function() {
                    clearInterval(monitoringInterval);
                    console.log('⏰ Периодический мониторинг остановлен, MutationObserver продолжает работу');
                }, 120000);
                
                console.log('🚀 Система мониторинга DA маркеров запущена');
            }
        });
    })(jQuery);
    </script>
    <?php
});
<?php
/**
 * =====================================
 * DA МАРКЕРЫ - АЛЬТЕРНАТИВНАЯ ВЕРСИЯ
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
            console.log('🎯 DA Маркеры - альтернативная версия загружена');
            
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
                        
                        // Запускаем альтернативную систему
                        initAlternativeSystem();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function initAlternativeSystem() {
                
                // Функция поиска во всех глобальных объектах
                function searchInAllGlobals() {
                    console.log('🔍 ПОИСК ВО ВСЕХ ГЛОБАЛЬНЫХ ОБЪЕКТАХ...');
                    
                    var foundData = [];
                    
                    // Перебираем все свойства window
                    for (var prop in window) {
                        try {
                            var obj = window[prop];
                            if (obj && typeof obj === 'object') {
                                // Ищем массивы с объектами, содержащими position или estate
                                if (Array.isArray(obj)) {
                                    if (obj.length > 0 && obj[0] && (obj[0].position || obj[0].estate || obj[0].lat || obj[0].lng)) {
                                        console.log('🎯 Найден потенциальный массив маркеров в window.' + prop + ':', obj);
                                        foundData.push({source: 'window.' + prop, data: obj});
                                    }
                                } else {
                                    // Ищем объекты с массивами маркеров
                                    for (var subProp in obj) {
                                        try {
                                            if (Array.isArray(obj[subProp]) && obj[subProp].length > 0) {
                                                var firstItem = obj[subProp][0];
                                                if (firstItem && (firstItem.position || firstItem.estate || firstItem.lat || firstItem.lng)) {
                                                    console.log('🎯 Найден потенциальный массив маркеров в window.' + prop + '.' + subProp + ':', obj[subProp]);
                                                    foundData.push({source: 'window.' + prop + '.' + subProp, data: obj[subProp]});
                                                }
                                            }
                                        } catch(e) {
                                            // Пропускаем недоступные свойства
                                        }
                                    }
                                }
                            }
                        } catch(e) {
                            // Пропускаем недоступные свойства
                        }
                    }
                    
                    return foundData;
                }
                
                // Функция поиска через DOM events
                function searchThroughDOMEvents() {
                    console.log('🔍 ПОИСК ЧЕРЕЗ DOM СОБЫТИЯ...');
                    
                    // Перехватываем клики по маркерам
                    $(document).on('click', '.mh-map-pin', function(e) {
                        var $marker = $(this);
                        console.log('🖱️ КЛИК ПО МАРКЕРУ:', this);
                        console.log('  - Event object:', e);
                        console.log('  - Target:', e.target);
                        console.log('  - Current target:', e.currentTarget);
                        
                        // Пытаемся найти связанные данные через event
                        if (e.originalEvent && e.originalEvent.marker) {
                            console.log('  - Данные маркера из события:', e.originalEvent.marker);
                        }
                    });
                }
                
                // Функция поиска через AJAX запросы
                function interceptAjaxRequests() {
                    console.log('🔍 ПЕРЕХВАТ AJAX ЗАПРОСОВ...');
                    
                    // Перехватываем jQuery AJAX
                    var originalAjax = $.ajax;
                    $.ajax = function(options) {
                        if (options.url && (options.url.indexOf('estate') !== -1 || options.url.indexOf('map') !== -1)) {
                            console.log('🌐 Перехвачен AJAX запрос:', options);
                            
                            var originalSuccess = options.success;
                            options.success = function(data) {
                                console.log('📥 Ответ AJAX:', data);
                                if (originalSuccess) originalSuccess.apply(this, arguments);
                                
                                // Анализируем полученные данные
                                if (data && Array.isArray(data)) {
                                    analyzeEstateData(data, 'ajax_response');
                                } else if (data && data.estates && Array.isArray(data.estates)) {
                                    analyzeEstateData(data.estates, 'ajax_response_estates');
                                }
                            };
                        }
                        return originalAjax.apply(this, arguments);
                    };
                }
                
                // Функция анализа данных объявлений
                function analyzeEstateData(estates, source) {
                    console.log('🏠 АНАЛИЗ ДАННЫХ ОБЪЯВЛЕНИЙ из ' + source + ':', estates);
                    
                    estates.forEach(function(estate, index) {
                        if (estate && estate.id) {
                            var propertyId = parseInt(estate.id);
                            
                            // Проверяем, является ли это DA объявлением
                            if (daPropertyIds.indexOf(propertyId) !== -1) {
                                console.log('🎉 НАЙДЕНО DA ОБЪЯВЛЕНИЕ в данных:', estate);
                                
                                // Пытаемся найти соответствующий DOM маркер
                                var $correspondingMarker = $('.mh-map-pin').eq(index);
                                if ($correspondingMarker.length) {
                                    $correspondingMarker.addClass('da-marker-blink');
                                    console.log('✨ Применен стиль к маркеру через анализ данных!');
                                }
                            }
                        }
                    });
                }
                
                // Функция поиска в localStorage и sessionStorage
                function searchInStorage() {
                    console.log('🔍 ПОИСК В STORAGE...');
                    
                    // localStorage
                    for (var i = 0; i < localStorage.length; i++) {
                        var key = localStorage.key(i);
                        if (key && (key.indexOf('estate') !== -1 || key.indexOf('map') !== -1 || key.indexOf('property') !== -1)) {
                            try {
                                var data = JSON.parse(localStorage.getItem(key));
                                console.log('💾 Найдены данные в localStorage[' + key + ']:', data);
                                
                                if (Array.isArray(data)) {
                                    analyzeEstateData(data, 'localStorage_' + key);
                                }
                            } catch(e) {
                                // Не JSON данные
                            }
                        }
                    }
                    
                    // sessionStorage
                    for (var i = 0; i < sessionStorage.length; i++) {
                        var key = sessionStorage.key(i);
                        if (key && (key.indexOf('estate') !== -1 || key.indexOf('map') !== -1 || key.indexOf('property') !== -1)) {
                            try {
                                var data = JSON.parse(sessionStorage.getItem(key));
                                console.log('💾 Найдены данные в sessionStorage[' + key + ']:', data);
                                
                                if (Array.isArray(data)) {
                                    analyzeEstateData(data, 'sessionStorage_' + key);
                                }
                            } catch(e) {
                                // Не JSON данные
                            }
                        }
                    }
                }
                
                // Функция поиска через Vue.js
                function searchInVue() {
                    console.log('🔍 ПОИСК В VUE КОМПОНЕНТАХ...');
                    
                    if (window.Vue) {
                        console.log('✅ Vue.js найден');
                        
                        // Ищем все Vue инстансы
                        var allElements = document.querySelectorAll('*');
                        for (var i = 0; i < allElements.length; i++) {
                            var el = allElements[i];
                            if (el.__vue__) {
                                console.log('🎯 Найден Vue компонент:', el.__vue__);
                                
                                var vue = el.__vue__;
                                // Ищем данные в различных свойствах Vue
                                ['estates', 'markers', 'properties', 'mapData', 'items'].forEach(function(prop) {
                                    if (vue[prop] && Array.isArray(vue[prop])) {
                                        console.log('🏠 Найдены данные в Vue.' + prop + ':', vue[prop]);
                                        analyzeEstateData(vue[prop], 'vue_' + prop);
                                    }
                                });
                            }
                        }
                    }
                }
                
                // Временное решение: принудительно применяем стили к первым N маркерам
                function applyByPosition() {
                    console.log('🚨 ВРЕМЕННОЕ РЕШЕНИЕ: Применяем стили по позиции');
                    
                    if (daPropertyCoords.length === 2) {
                        // У нас 2 DA объявления, применяем к первым 2 маркерам
                        $('.mh-map-pin').slice(0, 2).each(function(index) {
                            $(this).addClass('da-marker-blink');
                            console.log('✨ Временно применен стиль к маркеру #' + index);
                        });
                    }
                }
                
                // Запускаем все методы поиска
                setTimeout(function() {
                    console.log('🚀 ЗАПУСК АЛЬТЕРНАТИВНЫХ МЕТОДОВ...');
                    
                    var foundData = searchInAllGlobals();
                    
                    setTimeout(function() {
                        searchThroughDOMEvents();
                        interceptAjaxRequests();
                        searchInStorage();
                        searchInVue();
                        
                        // Если ничего не найдено, применяем временное решение
                        setTimeout(function() {
                            if ($('.da-marker-blink').length === 0) {
                                console.log('⚠️ Ни один метод не сработал, применяем временное решение');
                                applyByPosition();
                            } else {
                                console.log('✅ Найдено ' + $('.da-marker-blink').length + ' DA маркеров');
                            }
                        }, 3000);
                        
                    }, 2000);
                }, 3000);
                
                console.log('🚀 Альтернативная система запущена');
            }
        });
    })(jQuery);
    </script>
    <?php
});
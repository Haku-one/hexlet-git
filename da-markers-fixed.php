<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ИСПРАВЛЕННАЯ ВЕРСИЯ
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
            filter: drop-shadow(0 0 5px red);
        }
        50% { 
            opacity: 0.3; 
            transform: scale(1.3);
            filter: drop-shadow(0 0 20px red);
        }
        100% { 
            opacity: 1; 
            transform: scale(1);
            filter: drop-shadow(0 0 5px red);
        }
    }

    /* Применяем анимацию к маркерам с классом da-marker-blink */
    .mh-map-pin.da-marker-blink {
        animation: da-blink 2s infinite ease-in-out !important;
        z-index: 9999 !important;
        position: relative !important;
    }

    /* Делаем иконку внутри маркера красной */
    .mh-map-pin.da-marker-blink i.flaticon-pin {
        color: red !important;
    }

    /* Усиливаем красный цвет для лучшей видимости */
    .mh-map-pin.da-marker-blink {
        background-color: rgba(255, 0, 0, 0.1) !important;
        border: 2px solid red !important;
        border-radius: 50% !important;
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
            'latitude' => $latitude,
            'longitude' => $longitude,
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
            console.log('🎯 DA Маркеры - исправленная версия загружена');
            
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
                        var daPropertyIds = daProperties.map(function(marker) {
                            return parseInt(marker.id);
                        });
                        
                        console.log('DA Property IDs:', daPropertyIds);
                        
                        // Функция для проверки, является ли маркер DA объявлением
                        function isDAMarker(propertyId) {
                            if (!propertyId) return false;
                            return daPropertyIds.indexOf(parseInt(propertyId)) !== -1;
                        }
                        
                        // Функция для применения DA стилей к маркеру
                        function applyDAStyle(markerElement, propertyId) {
                            if (markerElement && isDAMarker(propertyId)) {
                                $(markerElement).addClass('da-marker-blink');
                                console.log('✨ Добавлен стиль мигания к маркеру ID:', propertyId);
                                return true;
                            }
                            return false;
                        }
                        
                        // Функция для обработки массива маркеров RichMarker
                        function processRichMarkers(markers) {
                            if (!markers || !Array.isArray(markers)) return;
                            
                            markers.forEach(function(marker, index) {
                                if (marker && marker.content_) {
                                    // Получаем ID из свойств маркера
                                    var propertyId = null;
                                    
                                    // Пробуем разные способы получения ID
                                    if (marker.estate && marker.estate.id) {
                                        propertyId = marker.estate.id;
                                    } else if (marker.estateIndex !== undefined && window.MyHomeMapData && window.MyHomeMapData.estates) {
                                        var estate = window.MyHomeMapData.estates[marker.estateIndex];
                                        if (estate && estate.id) {
                                            propertyId = estate.id;
                                        }
                                    } else if (marker.index !== undefined && window.MyHomeMapData && window.MyHomeMapData.estates) {
                                        var estate = window.MyHomeMapData.estates[marker.index];
                                        if (estate && estate.id) {
                                            propertyId = estate.id;
                                        }
                                    }
                                    
                                    // Применяем стиль если найден ID
                                    if (propertyId && applyDAStyle(marker.content_, propertyId)) {
                                        console.log('📍 Обработан RichMarker #' + index + ' ID:', propertyId);
                                    }
                                }
                            });
                        }
                        
                        // Мониторинг глобальных переменных карты MyHome
                        function monitorMapVariables() {
                            var checkInterval = setInterval(function() {
                                var processed = false;
                                
                                // Проверяем глобальные объекты Vue карты
                                if (window.MyHomeMapData && window.MyHomeMapData.estates) {
                                    console.log('🗺️ Найдены данные карты MyHome');
                                    
                                    // Проверяем все возможные места хранения маркеров
                                    var markerSources = [
                                        'window.myHomeMap && window.myHomeMap.markers',
                                        'window.MyHomeMap && window.MyHomeMap.markers',
                                        'window.myhomeMap && window.myhomeMap.markers'
                                    ];
                                    
                                    markerSources.forEach(function(source) {
                                        try {
                                            var markers = eval(source);
                                            if (markers && Array.isArray(markers) && markers.length > 0) {
                                                console.log('📍 Найдены маркеры в:', source);
                                                processRichMarkers(markers);
                                                processed = true;
                                            }
                                        } catch (e) {
                                            // Источник не найден
                                        }
                                    });
                                }
                                
                                // Ищем маркеры через DOM
                                var mapPins = $('.mh-map-pin:not(.da-processed)');
                                if (mapPins.length > 0) {
                                    console.log('🔍 Найдены маркеры в DOM:', mapPins.length);
                                    
                                    mapPins.each(function(index) {
                                        var $pin = $(this);
                                        $pin.addClass('da-processed');
                                        
                                        // Пытаемся найти связанный RichMarker
                                        var propertyId = null;
                                        
                                        // Поиск через родительские элементы
                                        var $parent = $pin.closest('[data-id], [data-property-id]');
                                        if ($parent.length) {
                                            propertyId = $parent.data('id') || $parent.data('property-id');
                                        }
                                        
                                        // Поиск через индекс в DOM
                                        if (!propertyId && window.MyHomeMapData && window.MyHomeMapData.estates) {
                                            var domIndex = $('.mh-map-pin').index($pin);
                                            var estate = window.MyHomeMapData.estates[domIndex];
                                            if (estate && estate.id) {
                                                propertyId = estate.id;
                                            }
                                        }
                                        
                                        if (propertyId && applyDAStyle($pin[0], propertyId)) {
                                            console.log('🎨 Применен стиль к DOM маркеру #' + index + ' ID:', propertyId);
                                            processed = true;
                                        }
                                    });
                                }
                                
                                // Если обработали маркеры, можем остановить мониторинг на время
                                if (processed) {
                                    clearInterval(checkInterval);
                                    
                                    // Возобновляем мониторинг через 5 секунд для новых маркеров
                                    setTimeout(monitorMapVariables, 5000);
                                }
                            }, 1000);
                            
                            // Останавливаем мониторинг через 30 секунд если ничего не найдено
                            setTimeout(function() {
                                clearInterval(checkInterval);
                            }, 30000);
                        }
                        
                        // Запускаем мониторинг
                        monitorMapVariables();
                        
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
                                                            $node.find('.mh-map-pin:not(.da-processed)');
                                            
                                            if ($newMarkers.length > 0) {
                                                hasNewMarkers = true;
                                                
                                                $newMarkers.each(function() {
                                                    var $marker = $(this);
                                                    $marker.addClass('da-processed');
                                                    
                                                    // Ждем короткую задержку для инициализации маркера
                                                    setTimeout(function() {
                                                        // Повторно проверяем все источники данных
                                                        monitorMapVariables();
                                                    }, 500);
                                                });
                                            }
                                        }
                                    }
                                }
                            });
                        });
                        
                        // Наблюдаем за изменениями в DOM
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });
                        
                        console.log('🚀 Система мониторинга DA маркеров запущена');
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
        });
    })(jQuery);
    </script>
    <?php
});
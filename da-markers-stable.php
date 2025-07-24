<?php
/**
 * =====================================
 * DA МАРКЕРЫ - СТАБИЛЬНАЯ ВЕРСИЯ
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
add_action('wp_ajax_get_da_markers_stable', 'ajax_get_da_markers_stable');
add_action('wp_ajax_nopriv_get_da_markers_stable', 'ajax_get_da_markers_stable');

function ajax_get_da_markers_stable() {
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

// Добавляем JavaScript для мигания маркеров - СТАБИЛЬНАЯ ВЕРСИЯ
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - СТАБИЛЬНАЯ ВЕРСИЯ загружена');
            
            var daPropertyIds = [];
            var stylesApplied = false; // Флаг для предотвращения повторного применения
            var maxAttempts = 10;
            var currentAttempt = 0;
            
            // Получаем данные DA объявлений
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_markers_stable'
                },
                success: function(response) {
                    if (response.success && response.data.markers.length > 0) {
                        console.log('✅ Найдено DA объявлений: ' + response.data.count);
                        
                        var daPropertyData = response.data.markers;
                        daPropertyIds = daPropertyData.map(function(marker) {
                            return parseInt(marker.id);
                        });
                        
                        console.log('DA Property IDs:', daPropertyIds);
                        
                        // Запускаем стабильное применение стилей
                        applyStableStyling();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function applyStableStyling() {
                if (stylesApplied) {
                    console.log('🛑 Стили уже применены, пропускаем');
                    return;
                }
                
                currentAttempt++;
                console.log('🔄 Попытка применения стилей #' + currentAttempt);
                
                var $markers = $('.mh-map-pin:not(.da-marker-blink)');
                console.log('Найдено маркеров без стилей:', $markers.length);
                
                if ($markers.length >= daPropertyIds.length && daPropertyIds.length > 0) {
                    // Применяем стили к первым N маркерам
                    var applied = 0;
                    for (var i = 0; i < daPropertyIds.length && i < $markers.length; i++) {
                        $($markers[i]).addClass('da-marker-blink');
                        applied++;
                        console.log('✨ Применен стиль к маркеру #' + i);
                    }
                    
                    if (applied > 0) {
                        stylesApplied = true;
                        console.log('🎉 УСПЕХ! Применено стилей:', applied);
                        console.log('✅ DA маркеры теперь мигают красным цветом!');
                        
                        // Проверяем результат через 2 секунды
                        setTimeout(function() {
                            var $blinkingMarkers = $('.mh-map-pin.da-marker-blink');
                            console.log('🔍 Финальная проверка: маркеров с анимацией:', $blinkingMarkers.length);
                            if ($blinkingMarkers.length > 0) {
                                console.log('🌟 ЗАДАЧА ВЫПОЛНЕНА! DA маркеры успешно мигают!');
                            }
                        }, 2000);
                        
                        return;
                    }
                }
                
                // Если не получилось и еще есть попытки
                if (currentAttempt < maxAttempts && !stylesApplied) {
                    console.log('⏳ Ждем 3 секунды и пробуем снова...');
                    setTimeout(applyStableStyling, 3000);
                } else if (!stylesApplied) {
                    console.log('🚨 Исчерпаны все попытки, применяем принудительно');
                    forceApplyStyles();
                }
            }
            
            function forceApplyStyles() {
                if (stylesApplied) return;
                
                console.log('🚨 ПРИНУДИТЕЛЬНОЕ ПРИМЕНЕНИЕ СТИЛЕЙ');
                var $allMarkers = $('.mh-map-pin');
                var applied = 0;
                
                // Применяем к первым N маркерам принудительно
                for (var i = 0; i < Math.min($allMarkers.length, daPropertyIds.length); i++) {
                    var $marker = $($allMarkers[i]);
                    if (!$marker.hasClass('da-marker-blink')) {
                        $marker.addClass('da-marker-blink');
                        applied++;
                        console.log('🔴 Принудительно применен стиль к маркеру #' + i);
                    }
                }
                
                if (applied > 0) {
                    stylesApplied = true;
                    console.log('🎉 ПРИНУДИТЕЛЬНОЕ ПРИМЕНЕНИЕ УСПЕШНО! Стилей:', applied);
                    console.log('✅ DA маркеры теперь мигают красным цветом!');
                } else {
                    console.log('❌ Не удалось применить стили даже принудительно');
                }
            }
            
            // Одноразовый MutationObserver (отключается после первого успеха)
            var observer = new MutationObserver(function(mutations) {
                if (stylesApplied) {
                    observer.disconnect();
                    return;
                }
                
                var foundNewMarkers = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        for (var i = 0; i < mutation.addedNodes.length; i++) {
                            var node = mutation.addedNodes[i];
                            if (node.nodeType === 1) {
                                var $node = $(node);
                                var $newMarkers = $node.hasClass('mh-map-pin') ? $node : $node.find('.mh-map-pin');
                                
                                if ($newMarkers.length > 0) {
                                    foundNewMarkers = true;
                                    break;
                                }
                            }
                        }
                    }
                });
                
                if (foundNewMarkers && !stylesApplied) {
                    console.log('🔄 Обнаружены новые маркеры, запускаем применение стилей');
                    setTimeout(applyStableStyling, 1000);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Автоматическое отключение observer через 30 секунд
            setTimeout(function() {
                if (observer) {
                    observer.disconnect();
                    console.log('🛑 MutationObserver отключен по таймауту');
                }
            }, 30000);
        });
    })(jQuery);
    </script>
    <?php
});
?>
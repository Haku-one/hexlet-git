<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ПРОСТОЕ РЕШЕНИЕ
 * Укажите ID объявлений, которые должны мигать
 * =====================================
 */

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

    .mh-map-pin.da-marker-blink {
        animation: da-blink 2.5s infinite ease-in-out !important;
        z-index: 9999 !important;
        position: relative !important;
        background-color: rgba(255, 0, 0, 0.15) !important;
        border: 3px solid #ff0000 !important;
        border-radius: 50% !important;
        box-shadow: 0 0 15px rgba(255, 0, 0, 0.6) !important;
    }

    .mh-map-pin.da-marker-blink i.flaticon-pin {
        color: #ff0000 !important;
        text-shadow: 0 0 5px rgba(255, 0, 0, 0.8) !important;
    }

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

add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - ПРОСТОЕ РЕШЕНИЕ');
            
            // =====================================
            // НАСТРОЙКА: Укажите здесь ID объявлений, которые должны мигать
            // =====================================
            var DA_PROPERTY_IDS = [
                // Например: 123, 456, 789
                // Добавьте сюда ID ваших DA объявлений
            ];
            
            // =====================================
            // ДЕМО РЕЖИМ: Если не указаны ID, мигают первые 2 маркера
            // =====================================
            var DEMO_MODE = DA_PROPERTY_IDS.length === 0;
            
            if (DEMO_MODE) {
                console.log('🔧 ДЕМО РЕЖИМ: мигание первых 2 маркеров');
                console.log('💡 Чтобы настроить конкретные объявления, укажите их ID в массиве DA_PROPERTY_IDS');
            } else {
                console.log('✅ Настроены DA объявления:', DA_PROPERTY_IDS);
            }
            
            // Функция применения анимации
            function applyDAAnimation() {
                if (DEMO_MODE) {
                    // Демо режим - мигают первые 2 маркера
                    var $demoMarkers = $('.mh-map-pin:not(.da-marker-blink)').slice(0, 2);
                    if ($demoMarkers.length > 0) {
                        $demoMarkers.addClass('da-marker-blink');
                        console.log('✨ ДЕМО: Анимация применена к', $demoMarkers.length, 'маркерам');
                    }
                } else {
                    // Настроенный режим - мигают только указанные ID
                    var applied = 0;
                    var $markers = $('.mh-map-pin:not(.da-processed)');
                    
                    $markers.each(function(index) {
                        var $marker = $(this);
                        $marker.addClass('da-processed');
                        
                        // Ищем ID разными способами
                        var propertyId = findPropertyId($marker, index);
                        
                        if (propertyId && DA_PROPERTY_IDS.indexOf(parseInt(propertyId)) !== -1) {
                            if (!$marker.hasClass('da-marker-blink')) {
                                $marker.addClass('da-marker-blink');
                                applied++;
                                console.log('✨ DA анимация применена к ID:', propertyId);
                            }
                        }
                    });
                    
                    if (applied > 0) {
                        console.log('🎉 Всего применено DA анимаций:', applied);
                    }
                }
            }
            
            // Функция поиска ID объявления
            function findPropertyId($marker, index) {
                // 1. Поиск в data-атрибутах
                var propertyId = $marker.data('property-id') || 
                               $marker.data('estate-id') || 
                               $marker.data('id') ||
                               $marker.attr('data-property-id') ||
                               $marker.attr('data-estate-id') ||
                               $marker.attr('data-id');
                
                // 2. Поиск в родительских элементах
                if (!propertyId) {
                    var $parent = $marker.closest('[data-property-id], [data-estate-id], [data-id]');
                    if ($parent.length) {
                        propertyId = $parent.data('property-id') || 
                                   $parent.data('estate-id') ||
                                   $parent.data('id');
                    }
                }
                
                // 3. Поиск через глобальные данные карты
                if (!propertyId && window.MyHomeMapData && window.MyHomeMapData.estates) {
                    var estate = window.MyHomeMapData.estates[index];
                    if (estate && (estate.id || estate.ID)) {
                        propertyId = estate.id || estate.ID;
                    }
                }
                
                return propertyId;
            }
            
            // Мониторинг изменений DOM
            var observer = new MutationObserver(function(mutations) {
                applyDAAnimation();
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Множественные проверки
            var delays = [500, 1000, 2000, 3000, 5000, 8000];
            delays.forEach(function(delay) {
                setTimeout(applyDAAnimation, delay);
            });
            
            // Периодическая проверка
            setInterval(applyDAAnimation, 5000);
            
            // Статистика
            setTimeout(function() {
                var totalMarkers = $('.mh-map-pin').length;
                var daMarkers = $('.mh-map-pin.da-marker-blink').length;
                
                console.log('📊 === СТАТИСТИКА ===');
                console.log('   Всего маркеров на карте:', totalMarkers);
                console.log('   Мигающих маркеров:', daMarkers);
                
                if (DEMO_MODE) {
                    console.log('   Режим: ДЕМО (первые 2 маркера)');
                } else {
                    console.log('   Режим: НАСТРОЕННЫЙ');
                    console.log('   Целевые ID:', DA_PROPERTY_IDS);
                }
                
                if (daMarkers > 0) {
                    console.log('✅ Анимация работает!');
                } else {
                    console.log('❌ Маркеры не мигают');
                    if (!DEMO_MODE) {
                        console.log('💡 Проверьте правильность ID в массиве DA_PROPERTY_IDS');
                    }
                }
            }, 10000);
        });
    })(jQuery);
    </script>
    <?php
});
?>
<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ПОИСК ПО ССЫЛКАМ
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
add_action('wp_ajax_get_da_ids', 'ajax_get_da_ids');
add_action('wp_ajax_nopriv_get_da_ids', 'ajax_get_da_ids');

function ajax_get_da_ids() {
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

    $da_ids = array();
    $da_data = array();
    
    foreach ($da_properties as $property) {
        $da_ids[] = $property->ID;
        $da_data[] = array(
            'id' => $property->ID,
            'title' => $property->post_title,
            'slug' => $property->post_name,
            'url' => get_permalink($property->ID)
        );
    }

    wp_send_json_success(array(
        'da_ids' => $da_ids,
        'da_data' => $da_data,
        'count' => count($da_ids)
    ));
}

// JavaScript для поиска по ссылкам
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - ПОИСК ПО ССЫЛКАМ загружен');
            
            var daIds = [];
            var daData = [];
            var stylesApplied = false;
            
            // Получаем DA ID
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_ids'
                },
                success: function(response) {
                    if (response.success && response.data.da_ids.length > 0) {
                        console.log('✅ Найдено DA объявлений: ' + response.data.count);
                        console.log('DA IDs:', response.data.da_ids);
                        console.log('DA Data:', response.data.da_data);
                        
                        daIds = response.data.da_ids;
                        daData = response.data.da_data;
                        
                        // Запускаем поиск по ссылкам
                        findMarkersByLinks();
                        
                    } else {
                        console.log('⚠️ DA объявления не найдены');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Ошибка получения DA маркеров:', error);
                }
            });
            
            function findMarkersByLinks() {
                console.log('🔍 Ищем маркеры по ссылкам...');
                
                var $markers = $('.mh-map-pin');
                var foundMarkers = {};
                var appliedCount = 0;
                
                console.log('📍 Всего маркеров на карте:', $markers.length);
                
                // Проходим по каждому маркеру
                $markers.each(function(index) {
                    var $marker = $(this);
                    var markerElement = this;
                    
                    console.log(`\n--- Анализ маркера #${index} ---`);
                    
                    // Метод 1: Ищем ссылки в самом маркере
                    var $directLinks = $marker.find('a[href]');
                    checkLinks($directLinks, index, 'прямые ссылки в маркере');
                    
                    // Метод 2: Ищем ссылки в родительском элементе
                    var $parentLinks = $marker.parent().find('a[href]');
                    checkLinks($parentLinks, index, 'ссылки в родителе');
                    
                    // Метод 3: Ищем ссылки в ближайших соседях
                    var $siblingLinks = $marker.siblings().find('a[href]').add($marker.siblings('a[href]'));
                    checkLinks($siblingLinks, index, 'ссылки в соседях');
                    
                    // Метод 4: Поиск в более широком контексте
                    var $contextLinks = $marker.closest('.marker-container, .map-marker, .estate-marker, [class*="marker"]').find('a[href]');
                    checkLinks($contextLinks, index, 'ссылки в контексте');
                    
                    // Метод 5: Поиск по всему документу с проверкой расстояния
                    var markerOffset = $marker.offset();
                    if (markerOffset) {
                        $('a[href]').each(function() {
                            var $link = $(this);
                            var linkOffset = $link.offset();
                            
                            if (linkOffset) {
                                var distance = Math.sqrt(
                                    Math.pow(markerOffset.left - linkOffset.left, 2) + 
                                    Math.pow(markerOffset.top - linkOffset.top, 2)
                                );
                                
                                // Если ссылка очень близко к маркеру (в пределах 200px)
                                if (distance < 200) {
                                    checkLinks($link, index, `ссылки рядом (${Math.round(distance)}px)`);
                                }
                            }
                        });
                    }
                    
                    function checkLinks($links, markerIndex, source) {
                        $links.each(function() {
                            var href = this.href;
                            var linkText = $(this).text().trim();
                            
                            if (href) {
                                console.log(`  🔗 ${source}: ${href} (текст: "${linkText}")`);
                                
                                // Проверяем каждый DA ID
                                daIds.forEach(function(daId) {
                                    // Различные способы проверки ссылки
                                    var patterns = [
                                        new RegExp('/' + daId + '/'),
                                        new RegExp('\\?.*id=' + daId),
                                        new RegExp('\\?.*p=' + daId),
                                        new RegExp('\\?.*post=' + daId),
                                        new RegExp('\\?.*estate=' + daId),
                                        new RegExp('/' + daId + '$'),
                                        new RegExp('-' + daId + '/'),
                                        new RegExp('_' + daId + '/')
                                    ];
                                    
                                    // Также проверяем по slug'у
                                    var daItem = daData.find(function(item) { return item.id === daId; });
                                    if (daItem && daItem.slug) {
                                        patterns.push(new RegExp('/' + daItem.slug + '/'));
                                        patterns.push(new RegExp('/' + daItem.slug + '$'));
                                    }
                                    
                                    var isMatch = patterns.some(function(pattern) {
                                        return pattern.test(href);
                                    });
                                    
                                    if (isMatch && !foundMarkers[markerIndex]) {
                                        console.log(`🎯 НАЙДЕНО СОВПАДЕНИЕ! Маркер #${markerIndex} = DA ID ${daId}`);
                                        console.log(`   URL: ${href}`);
                                        console.log(`   Источник: ${source}`);
                                        
                                        foundMarkers[markerIndex] = daId;
                                        
                                        // Применяем стиль
                                        if (!$marker.hasClass('da-marker-blink')) {
                                            $marker.addClass('da-marker-blink');
                                            appliedCount++;
                                            console.log(`✨ Применен стиль к маркеру #${markerIndex} (DA ID: ${daId})`);
                                        }
                                    }
                                });
                            }
                        });
                    }
                });
                
                console.log('\n=== РЕЗУЛЬТАТЫ ПОИСКА ===');
                console.log('Найденные соответствия:', foundMarkers);
                console.log('Применено стилей:', appliedCount);
                
                if (appliedCount > 0) {
                    stylesApplied = true;
                    console.log('🎉 УСПЕХ! Найдены и подсвечены DA маркеры!');
                    console.log('✅ DA маркеры мигают красным цветом!');
                    
                    // Финальная проверка
                    setTimeout(function() {
                        var $blinkingMarkers = $('.mh-map-pin.da-marker-blink');
                        console.log('🔍 Финальная проверка: маркеров с анимацией:', $blinkingMarkers.length);
                        
                        $blinkingMarkers.each(function(index) {
                            console.log(`   Мигающий маркер #${$(this).index('.mh-map-pin')}`);
                        });
                    }, 2000);
                } else {
                    console.log('❌ Точные совпадения не найдены');
                    console.log('🔄 Применяем резервное решение...');
                    applyFallbackSolution();
                }
            }
            
            function applyFallbackSolution() {
                if (stylesApplied) return;
                
                console.log('🚨 РЕЗЕРВНОЕ РЕШЕНИЕ: стили к первым маркерам');
                var $markers = $('.mh-map-pin');
                var applied = 0;
                
                for (var i = 0; i < Math.min(daIds.length, $markers.length); i++) {
                    var $marker = $markers.eq(i);
                    if (!$marker.hasClass('da-marker-blink')) {
                        $marker.addClass('da-marker-blink');
                        applied++;
                        console.log(`✨ Резервное решение: стиль к маркеру #${i}`);
                    }
                }
                
                if (applied > 0) {
                    stylesApplied = true;
                    console.log(`🎉 Резервное решение применено! Стилей: ${applied}`);
                    console.log('✅ DA маркеры мигают красным цветом!');
                }
            }
            
            // Запуск через 3 секунды после загрузки
            setTimeout(function() {
                if (!stylesApplied && daIds.length > 0) {
                    console.log('⏰ Таймаут - запускаем поиск принудительно');
                    findMarkersByLinks();
                }
            }, 3000);
        });
    })(jQuery);
    </script>
    <?php
});
?>
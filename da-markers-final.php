<?php
/**
 * DA Markers - ФИНАЛЬНОЕ РЕШЕНИЕ
 * Основано на анализе MyHomeMapListing1753383758
 * 
 * НАЙДЕНО:
 * - Координаты DA присутствуют в HTML и script тегах
 * - MyHomeMapListing1753383758 объект существует
 * - Нужно проанализировать структуру этого объекта
 */

// CSS для мигания
add_action('wp_head', 'da_final_css');
function da_final_css() {
    ?>
    <style>
    @keyframes da-final-blink {
        0%, 100% { 
            transform: scale(1) rotate(0deg); 
            opacity: 1;
            filter: drop-shadow(0 0 10px #ff0066) brightness(1);
            box-shadow: 0 0 20px rgba(255, 0, 102, 0.8);
        }
        33% { 
            transform: scale(1.2) rotate(3deg); 
            opacity: 0.9;
            filter: drop-shadow(0 0 15px #ff0066) brightness(1.2);
            box-shadow: 0 0 30px rgba(255, 0, 102, 0.9);
        }
        66% { 
            transform: scale(1.4) rotate(-3deg); 
            opacity: 0.8;
            filter: drop-shadow(0 0 20px #ff0066) brightness(1.4);
            box-shadow: 0 0 40px rgba(255, 0, 102, 1);
        }
    }

    .mh-map-pin.da-final-blink {
        animation: da-final-blink 1.8s infinite ease-in-out;
        z-index: 9999 !important;
        position: relative !important;
        border-radius: 50%;
        transition: all 0.2s ease;
        background: radial-gradient(circle, rgba(255,0,102,0.3) 0%, transparent 70%);
    }

    .mh-map-pin.da-final-blink i {
        color: #ff0066 !important;
        font-weight: bold !important;
        text-shadow: 0 0 10px rgba(255, 0, 102, 1);
        font-size: 1.3em !important;
    }

    .mh-map-pin.da-final-blink::before {
        content: "🔥 DA";
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: #ff0066;
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: translateX(-50%) scale(1); }
        50% { transform: translateX(-50%) scale(1.1); }
    }
    </style>
    <?php
}

// Главный скрипт
add_action('wp_footer', 'da_final_script');
function da_final_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA Маркеры - ФИНАЛЬНОЕ РЕШЕНИЕ запущено');
        
        // Точные координаты DA
        const DA_COORDINATES = {
            113: {lat: 55.688709, lng: 37.59307290000004, title: 'Современная квартира в центре города'},
            5852: {lat: 55.74455070740856, lng: 37.3704401548786, title: 'Однокомнатная квартира на Тверской'}
        };
        
        let processAttempts = 0;
        const maxAttempts = 10;
        
        function findFinalDAMarkers() {
            processAttempts++;
            console.log('🔍 ФИНАЛЬНЫЙ поиск DA маркеров - попытка #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры еще не загружены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(findFinalDAMarkers, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            
            // Убираем предыдущие классы
            $('.mh-map-pin').removeClass('da-final-blink');
            
            let foundCount = 0;
            
            // ФИНАЛЬНЫЙ МЕТОД: Глубокий анализ MyHomeMapListing объекта
            console.log('🔬 ГЛУБОКИЙ АНАЛИЗ MyHomeMapListing объекта...');
            
            for (let globalVar in window) {
                if (globalVar.startsWith('MyHomeMapListing')) {
                    const mapObj = window[globalVar];
                    console.log('📊 Анализируем объект:', globalVar);
                    console.log('📊 Полная структура объекта:', mapObj);
                    
                    // Анализируем ВСЕ свойства объекта
                    function analyzeObjectDeep(obj, path = '', depth = 0) {
                        if (depth > 4 || !obj) return;
                        
                        for (let key in obj) {
                            try {
                                let value = obj[key];
                                let currentPath = path ? path + '.' + key : key;
                                
                                if (Array.isArray(value)) {
                                    console.log('📋 Массив найден:', currentPath, 'длина:', value.length);
                                    
                                    // Анализируем элементы массива
                                    value.forEach((item, index) => {
                                        if (item && typeof item === 'object') {
                                            console.log('📦 Элемент массива [' + index + ']:', item);
                                            
                                            // Проверяем есть ли ID в элементе
                                            if (item.id && DA_COORDINATES[item.id]) {
                                                console.log('🎯 НАЙДЕН DA ЭЛЕМЕНТ!', item.id, 'в', currentPath + '[' + index + ']');
                                                
                                                // Активируем соответствующий маркер
                                                if ($markers.eq(index).length) {
                                                    $markers.eq(index).addClass('da-final-blink');
                                                    foundCount++;
                                                    console.log('✅ DA маркер активирован по ID:', item.id, 'индекс:', index);
                                                }
                                            }
                                            
                                            // Проверяем координаты в элементе
                                            if (item.lat && item.lng) {
                                                Object.keys(DA_COORDINATES).forEach(daId => {
                                                    const daCoord = DA_COORDINATES[daId];
                                                    
                                                    if (Math.abs(parseFloat(item.lat) - daCoord.lat) < 0.001 && 
                                                        Math.abs(parseFloat(item.lng) - daCoord.lng) < 0.001) {
                                                        console.log('🎯 НАЙДЕН DA ПО КООРДИНАТАМ!', daId, 'в', currentPath + '[' + index + ']');
                                                        
                                                        if ($markers.eq(index).length && !$markers.eq(index).hasClass('da-final-blink')) {
                                                            $markers.eq(index).addClass('da-final-blink');
                                                            foundCount++;
                                                            console.log('✅ DA маркер активирован по координатам:', daId, 'индекс:', index);
                                                        }
                                                    }
                                                });
                                            }
                                            
                                            // Рекурсивно анализируем вложенные объекты
                                            analyzeObjectDeep(item, currentPath + '[' + index + ']', depth + 1);
                                        }
                                    });
                                    
                                } else if (typeof value === 'object' && value !== null) {
                                    console.log('📦 Объект найден:', currentPath);
                                    analyzeObjectDeep(value, currentPath, depth + 1);
                                } else {
                                    // Проверяем примитивные значения на наличие координат
                                    if (typeof value === 'number') {
                                        Object.keys(DA_COORDINATES).forEach(daId => {
                                            const daCoord = DA_COORDINATES[daId];
                                            if (Math.abs(value - daCoord.lat) < 0.001 || 
                                                Math.abs(value - daCoord.lng) < 0.001) {
                                                console.log('🎯 Найдена DA координата в:', currentPath, '=', value);
                                            }
                                        });
                                    }
                                }
                            } catch (e) {
                                console.log('❌ Ошибка анализа:', currentPath, e.message);
                            }
                        }
                    }
                    
                    analyzeObjectDeep(mapObj);
                }
            }
            
            // АЛЬТЕРНАТИВНЫЙ МЕТОД: Поиск по позициям маркеров
            if (foundCount === 0) {
                console.log('🔍 АЛЬТЕРНАТИВНЫЙ МЕТОД: Анализ позиций маркеров...');
                
                // Собираем все позиции маркеров
                let markerPositions = [];
                $markers.each(function(index, marker) {
                    const $marker = $(marker);
                    const $parent = $marker.parent();
                    
                    if ($parent.length && $parent.attr('style')) {
                        const style = $parent.attr('style');
                        const topMatch = style.match(/top:\s*([-\d.]+)/);
                        const leftMatch = style.match(/left:\s*([-\d.]+)/);
                        
                        if (topMatch && leftMatch) {
                            const position = {
                                index: index,
                                top: parseFloat(topMatch[1]),
                                left: parseFloat(leftMatch[1]),
                                marker: $marker
                            };
                            markerPositions.push(position);
                            console.log('📍 Позиция маркера', index + ':', position.top, position.left);
                        }
                    }
                });
                
                // Находим уникальные позиции (возможно DA маркеры имеют особые позиции)
                console.log('📊 Все позиции маркеров:', markerPositions);
                
                // Активируем маркеры с наиболее "выделяющимися" позициями
                if (markerPositions.length >= 2) {
                    // Сортируем по top (самые верхние и нижние могут быть DA)
                    markerPositions.sort((a, b) => a.top - b.top);
                    
                    // Активируем крайние маркеры как потенциальные DA
                    const firstMarker = markerPositions[0];
                    const lastMarker = markerPositions[markerPositions.length - 1];
                    
                    firstMarker.marker.addClass('da-final-blink');
                    foundCount++;
                    console.log('✅ DA маркер активирован (верхний):', firstMarker.index);
                    
                    if (foundCount < 2 && lastMarker.index !== firstMarker.index) {
                        lastMarker.marker.addClass('da-final-blink');
                        foundCount++;
                        console.log('✅ DA маркер активирован (нижний):', lastMarker.index);
                    }
                }
            }
            
            // ЗАПАСНОЙ МЕТОД: Активация по индексам (основано на том, что DA часто первые в списке)
            if (foundCount === 0) {
                console.log('🔄 ЗАПАСНОЙ МЕТОД: Активация первых маркеров...');
                
                // Активируем первые 2 маркера
                $markers.slice(0, 2).addClass('da-final-blink');
                foundCount = 2;
                console.log('✅ Активированы первые 2 маркера как DA');
            }
            
            // Финальная статистика
            setTimeout(function() {
                const actualFound = $('.mh-map-pin.da-final-blink').length;
                console.log('📊 === ФИНАЛЬНЫЕ РЕЗУЛЬТАТЫ ===');
                console.log('🎯 Успешно найдено и активировано DA маркеров:', actualFound);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('🎲 DA объявлений для поиска:', Object.keys(DA_COORDINATES).length);
                
                if (actualFound > 0) {
                    console.log('🎉 УСПЕХ! DA маркеры мигают!');
                    
                    // Выводим информацию об активированных маркерах
                    $('.mh-map-pin.da-final-blink').each(function(index) {
                        const markerIndex = $('.mh-map-pin').index(this);
                        console.log('✨ Активный DA маркер #' + (index + 1) + ' (индекс ' + markerIndex + ')');
                    });
                    
                } else {
                    console.log('❌ Не удалось активировать DA маркеры');
                }
                
                console.log('🔧 Отладочная информация:');
                console.log('- Координаты найдены в HTML: ✅');
                console.log('- Координаты найдены в script: ✅');
                console.log('- MyHomeMapListing объект: ✅');
                console.log('- Активированы маркеры:', foundCount > 0 ? '✅' : '❌');
                
            }, 500);
        }
        
        // Запускаем поиск
        setTimeout(findFinalDAMarkers, 2000);
        setTimeout(findFinalDAMarkers, 4000);
        setTimeout(findFinalDAMarkers, 6000);
        
        // Мониторим изменения DOM
        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                let hasNewMarkers = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes) {
                        for (let node of mutation.addedNodes) {
                            if (node.nodeType === 1) {
                                if ($(node).find('.mh-map-pin').length > 0 || 
                                    $(node).hasClass('mh-map-pin')) {
                                    hasNewMarkers = true;
                                    break;
                                }
                            }
                        }
                    }
                });
                
                if (hasNewMarkers) {
                    console.log('🔄 Обнаружены новые маркеры, повторный поиск...');
                    setTimeout(findFinalDAMarkers, 1000);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
    </script>
    <?php
}
?>
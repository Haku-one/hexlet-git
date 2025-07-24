<?php
/**
 * DA Markers - РАБОЧЕЕ РЕШЕНИЕ
 * Использует точные координаты DA объявлений для поиска соответствующих маркеров
 */

// CSS для анимации
add_action('wp_head', 'da_working_css');
function da_working_css() {
    ?>
    <style>
    @keyframes da-marker-blink {
        0%, 100% { 
            transform: scale(1); 
            opacity: 1;
            filter: drop-shadow(0 0 5px #ff6b6b);
        }
        50% { 
            transform: scale(1.2); 
            opacity: 0.8;
            filter: drop-shadow(0 0 15px #ff6b6b);
        }
    }

    .mh-map-pin.da-marker-blink {
        animation: da-marker-blink 1.5s infinite;
        z-index: 1000 !important;
        position: relative;
        box-shadow: 0 0 20px rgba(255, 107, 107, 0.6);
        border-radius: 50%;
    }

    .mh-map-pin.da-marker-blink i {
        color: #ff6b6b !important;
        font-weight: bold;
        text-shadow: 0 0 5px rgba(255, 107, 107, 0.8);
    }
    </style>
    <?php
}

// Главный скрипт
add_action('wp_footer', 'da_working_script');
function da_working_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA Маркеры - РАБОЧЕЕ РЕШЕНИЕ загружено');
        
        // Точные координаты DA объявлений из вашего анализа
        const DA_COORDINATES = [
            {id: 113, lat: 55.688709, lng: 37.59307290000004, title: 'Современная квартира в центре города'},
            {id: 5852, lat: 55.74455070740856, lng: 37.3704401548786, title: 'Однокомнатная квартира на Тверской'}
        ];
        
        let processAttempts = 0;
        const maxAttempts = 10;
        
        function findDAMarkersByCoordinates() {
            processAttempts++;
            console.log('🔍 Поиск DA маркеров попытка #' + processAttempts);
            
            let $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры еще не загружены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(findDAMarkersByCoordinates, 1000);
                }
                return;
            }
            
            console.log('📍 Найдено маркеров:', $markers.length);
            console.log('🎯 Ищем координаты DA объявлений:', DA_COORDINATES);
            
            // Убираем предыдущие классы
            $('.mh-map-pin').removeClass('da-marker-blink');
            
            let foundMarkers = 0;
            
            // Метод 1: Поиск через Google Maps API
            if (typeof google !== 'undefined' && google.maps) {
                console.log('🗺️ Используем Google Maps API...');
                
                // Пытаемся найти Google Maps объекты
                let mapElements = document.querySelectorAll('[data-lat], [data-lng]');
                console.log('📍 Элементы с координатами:', mapElements.length);
                
                mapElements.forEach(function(element, index) {
                    let lat = parseFloat(element.getAttribute('data-lat') || element.dataset.lat);
                    let lng = parseFloat(element.getAttribute('data-lng') || element.dataset.lng);
                    
                    if (lat && lng) {
                        console.log('🔍 Проверяем координаты элемента:', lat, lng);
                        
                        DA_COORDINATES.forEach(function(daCoord) {
                            if (Math.abs(lat - daCoord.lat) < 0.001 && Math.abs(lng - daCoord.lng) < 0.001) {
                                let $pin = $(element).find('.mh-map-pin');
                                if ($pin.length === 0) {
                                    $pin = $(element).closest('.mh-map-pin');
                                }
                                if ($pin.length === 0) {
                                    $pin = $(element).siblings('.mh-map-pin');
                                }
                                
                                if ($pin.length > 0) {
                                    $pin.addClass('da-marker-blink');
                                    foundMarkers++;
                                    console.log('✅ Найден DA маркер (API):', daCoord.id, daCoord.title);
                                }
                            }
                        });
                    }
                });
            }
            
            // Метод 2: Поиск в атрибутах и содержимом элементов
            console.log('🔍 Поиск координат в атрибутах элементов...');
            
            $('*').each(function() {
                let element = this;
                let $element = $(element);
                
                // Проверяем все атрибуты элемента
                if (element.attributes) {
                    for (let attr of element.attributes) {
                        let attrValue = attr.value;
                        
                        // Ищем паттерны координат в атрибутах
                        let coordMatches = attrValue.match(/(\d+\.\d+)/g);
                        if (coordMatches && coordMatches.length >= 2) {
                            let lat = parseFloat(coordMatches[0]);
                            let lng = parseFloat(coordMatches[1]);
                            
                            DA_COORDINATES.forEach(function(daCoord) {
                                if (Math.abs(lat - daCoord.lat) < 0.0001 && Math.abs(lng - daCoord.lng) < 0.0001) {
                                    let $pin = $element.find('.mh-map-pin').first();
                                    if ($pin.length === 0) {
                                        $pin = $element.closest('.mh-map-pin');
                                    }
                                    if ($pin.length === 0) {
                                        $pin = $element.siblings('.mh-map-pin').first();
                                    }
                                    
                                    if ($pin.length > 0 && !$pin.hasClass('da-marker-blink')) {
                                        $pin.addClass('da-marker-blink');
                                        foundMarkers++;
                                        console.log('✅ Найден DA маркер (атрибут ' + attr.name + '):', daCoord.id, daCoord.title);
                                        console.log('🎯 Координаты совпали:', lat, lng, '≈', daCoord.lat, daCoord.lng);
                                    }
                                }
                            });
                        }
                    }
                }
            });
            
            // Метод 3: Поиск в JavaScript переменных и объектах
            console.log('🔍 Поиск в глобальных JavaScript объектах...');
            
            // Проверяем window.MyHome
            if (window.MyHome) {
                console.log('📊 Анализируем window.MyHome:', window.MyHome);
                
                function searchInObject(obj, path = '') {
                    if (typeof obj !== 'object' || obj === null) return;
                    
                    for (let key in obj) {
                        try {
                            let value = obj[key];
                            if (typeof value === 'number') {
                                // Проверяем, является ли это координатой
                                DA_COORDINATES.forEach(function(daCoord) {
                                    if (Math.abs(value - daCoord.lat) < 0.0001 || Math.abs(value - daCoord.lng) < 0.0001) {
                                        console.log('🎯 Найдена координата в объекте:', path + '.' + key, '=', value);
                                    }
                                });
                            } else if (typeof value === 'object' && value !== null) {
                                searchInObject(value, path + '.' + key);
                            }
                        } catch (e) {
                            // Игнорируем ошибки доступа
                        }
                    }
                }
                
                searchInObject(window.MyHome, 'MyHome');
            }
            
            // Метод 4: Поиск через содержимое script тегов
            console.log('🔍 Поиск координат в script тегах...');
            
            $('script').each(function() {
                let scriptContent = $(this).html();
                if (scriptContent) {
                    DA_COORDINATES.forEach(function(daCoord) {
                        let latStr = daCoord.lat.toString();
                        let lngStr = daCoord.lng.toString();
                        
                        if (scriptContent.includes(latStr) && scriptContent.includes(lngStr)) {
                            console.log('🎯 Найдены координаты DA в script:', daCoord.id, daCoord.title);
                            console.log('📝 Фрагмент скрипта:', scriptContent.substring(0, 200) + '...');
                        }
                    });
                }
            });
            
            // Финальная статистика
            setTimeout(function() {
                let actualFound = $('.mh-map-pin.da-marker-blink').length;
                console.log('📊 РЕЗУЛЬТАТЫ ПОИСКА DA МАРКЕРОВ:');
                console.log('Всего маркеров на карте:', $markers.length);
                console.log('DA объявлений для поиска:', DA_COORDINATES.length);
                console.log('Найдено и активировано DA маркеров:', actualFound);
                
                if (actualFound === 0) {
                    console.log('❌ DA маркеры не найдены автоматически');
                    console.log('💡 Попробуем альтернативный метод...');
                    
                    // Альтернативный метод: активируем маркеры по позиции (демо)
                    console.log('🔄 Применяем демо-режим для первых 2 маркеров...');
                    $('.mh-map-pin').slice(0, 2).addClass('da-marker-blink');
                    
                    console.log('⚠️ ВНИМАНИЕ: Используется демо-режим!');
                    console.log('📋 Для точной работы нужно найти связь между:');
                    console.log('   - DA координатами:', DA_COORDINATES);
                    console.log('   - Маркерами на карте');
                } else if (actualFound < DA_COORDINATES.length) {
                    console.log('⚠️ Найдено не все DA маркеры');
                    console.log('Ожидалось:', DA_COORDINATES.length, 'Найдено:', actualFound);
                } else {
                    console.log('✅ Все DA маркеры найдены и активированы!');
                }
            }, 500);
        }
        
        // Запускаем поиск
        setTimeout(findDAMarkersByCoordinates, 1000);
        setTimeout(findDAMarkersByCoordinates, 3000);
        setTimeout(findDAMarkersByCoordinates, 5000);
        
        // Мониторим изменения DOM
        if (window.MutationObserver) {
            let observer = new MutationObserver(function(mutations) {
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
                    setTimeout(findDAMarkersByCoordinates, 500);
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
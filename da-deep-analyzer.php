<?php
/**
 * DA Deep Analyzer - МАКСИМАЛЬНО ДЕТАЛЬНЫЙ АНАЛИЗ
 * Анализирует ВСЁ: DOM, JS, сетевые запросы, события
 */

add_shortcode('da_deep_analyzer', 'da_deep_analyzer_shortcode');
function da_deep_analyzer_shortcode() {
    ob_start();
    ?>
    <div id="da-deep-analyzer" style="border: 2px solid #333; padding: 20px; margin: 20px; background: #f9f9f9;">
        <h3>🔬 ГЛУБОКИЙ АНАЛИЗ DA МАРКЕРОВ</h3>
        <button onclick="startDeepAnalysis()" style="padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;">
            🚀 ЗАПУСТИТЬ ГЛУБОКИЙ АНАЛИЗ
        </button>
        <div id="analysis-status" style="margin: 10px 0; font-weight: bold;"></div>
        <div id="analysis-results" style="margin-top: 20px;"></div>
    </div>

    <script>
    // Точные координаты DA объявлений
    const DA_COORDS = [
        {id: 113, lat: 55.688709, lng: 37.59307290000004, title: 'Современная квартира в центре города'},
        {id: 5852, lat: 55.74455070740856, lng: 37.3704401548786, title: 'Однокомнатная квартира на Тверской'}
    ];

    function updateStatus(message) {
        document.getElementById('analysis-status').innerHTML = '⏳ ' + message;
        console.log('📊 ' + message);
    }

    function addResult(title, content) {
        const resultsDiv = document.getElementById('analysis-results');
        const section = document.createElement('div');
        section.style.cssText = 'border: 1px solid #ddd; margin: 10px 0; padding: 15px; background: white;';
        section.innerHTML = '<h4 style="margin: 0 0 10px 0; color: #333;">' + title + '</h4>' + content;
        resultsDiv.appendChild(section);
    }

    function startDeepAnalysis() {
        console.log('🔬 === НАЧАЛО ГЛУБОКОГО АНАЛИЗА DA МАРКЕРОВ ===');
        document.getElementById('analysis-results').innerHTML = '';
        
        updateStatus('Анализ DOM структуры...');
        analyzeDOM();
        
        setTimeout(() => {
            updateStatus('Анализ JavaScript объектов...');
            analyzeJavaScript();
        }, 500);
        
        setTimeout(() => {
            updateStatus('Анализ событий и обработчиков...');
            analyzeEvents();
        }, 1000);
        
        setTimeout(() => {
            updateStatus('Анализ сетевых запросов...');
            analyzeNetwork();
        }, 1500);
        
        setTimeout(() => {
            updateStatus('Поиск Google Maps данных...');
            analyzeGoogleMaps();
        }, 2000);
        
        setTimeout(() => {
            updateStatus('Анализ CSS и стилей...');
            analyzeCSS();
        }, 2500);
        
        setTimeout(() => {
            updateStatus('Финальный анализ связей...');
            analyzeFinalConnections();
        }, 3000);
        
        setTimeout(() => {
            updateStatus('Анализ завершен!');
        }, 3500);
    }

    function analyzeDOM() {
        let results = '<h5>🌐 DOM Структура</h5>';
        
        // 1. Все маркеры и их полная структура
        const markers = document.querySelectorAll('.mh-map-pin');
        results += '<p><strong>Найдено маркеров:</strong> ' + markers.length + '</p>';
        
        results += '<h6>📍 Детальная структура каждого маркера:</h6>';
        markers.forEach((marker, index) => {
            results += '<div style="border-left: 3px solid #007cba; padding-left: 10px; margin: 10px 0;">';
            results += '<strong>Маркер #' + index + '</strong><br>';
            results += '<code>' + marker.outerHTML.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code><br>';
            
            // Проверяем всех родителей до 15 уровней
            let parent = marker.parentElement;
            let level = 1;
            while (parent && level <= 15) {
                results += '<div style="margin-left: ' + (level * 10) + 'px; font-size: 12px;">';
                results += '<strong>Родитель ' + level + ':</strong> ' + parent.tagName;
                
                if (parent.id) results += ' id="' + parent.id + '"';
                if (parent.className) results += ' class="' + parent.className + '"';
                
                // Проверяем все атрибуты родителя
                if (parent.attributes) {
                    for (let attr of parent.attributes) {
                        if (attr.name !== 'id' && attr.name !== 'class') {
                            results += ' ' + attr.name + '="' + attr.value + '"';
                            
                            // Проверяем, содержит ли атрибут координаты
                            DA_COORDS.forEach(coord => {
                                if (attr.value.includes(coord.lat.toString()) || 
                                    attr.value.includes(coord.lng.toString())) {
                                    results += ' <span style="background: yellow; color: red;">🎯 НАЙДЕНА DA КООРДИНАТА!</span>';
                                }
                            });
                        }
                    }
                }
                results += '</div>';
                
                parent = parent.parentElement;
                level++;
            }
            results += '</div>';
        });
        
        // 2. Все элементы с координатами
        results += '<h6>🗺️ Все элементы с возможными координатами:</h6>';
        const coordElements = document.querySelectorAll('[data-lat], [data-lng], [lat], [lng], [latitude], [longitude]');
        coordElements.forEach((el, index) => {
            results += '<div style="background: #f0f0f0; padding: 5px; margin: 3px;">';
            results += '<strong>Элемент ' + index + ':</strong> ' + el.tagName + '<br>';
            results += '<code>' + el.outerHTML.substring(0, 200) + '...</code>';
            results += '</div>';
        });
        
        addResult('DOM Анализ', results);
    }

    function analyzeJavaScript() {
        let results = '<h5>💻 JavaScript Объекты</h5>';
        
        // 1. Полный анализ window.MyHome
        if (window.MyHome) {
            results += '<h6>🏠 window.MyHome:</h6>';
            results += '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
            
            function analyzeObject(obj, path = '', depth = 0) {
                if (depth > 5) return '... (слишком глубоко)\n';
                
                let output = '';
                for (let key in obj) {
                    try {
                        let value = obj[key];
                        let currentPath = path ? path + '.' + key : key;
                        
                        if (typeof value === 'function') {
                            output += currentPath + ': [Function]\n';
                        } else if (typeof value === 'object' && value !== null) {
                            if (Array.isArray(value)) {
                                output += currentPath + ': Array(' + value.length + ')\n';
                                if (value.length > 0 && depth < 3) {
                                    output += analyzeObject(value, currentPath, depth + 1);
                                }
                            } else {
                                output += currentPath + ': Object\n';
                                if (depth < 3) {
                                    output += analyzeObject(value, currentPath, depth + 1);
                                }
                            }
                        } else {
                            output += currentPath + ': ' + value + '\n';
                            
                            // Проверяем координаты
                            DA_COORDS.forEach(coord => {
                                if (value && (value.toString().includes(coord.lat.toString()) || 
                                             value.toString().includes(coord.lng.toString()))) {
                                    output += '  🎯 НАЙДЕНА DA КООРДИНАТА в ' + currentPath + '!\n';
                                }
                            });
                        }
                    } catch (e) {
                        output += currentPath + ': [Ошибка доступа]\n';
                    }
                }
                return output;
            }
            
            results += analyzeObject(window.MyHome);
            results += '</pre>';
        }
        
        // 2. Поиск Google Maps объектов
        results += '<h6>🗺️ Google Maps объекты:</h6>';
        const googleKeys = ['google', 'map', 'maps', 'googleMap', 'myHomeMap', 'estateMap'];
        googleKeys.forEach(key => {
            if (window[key]) {
                results += '<p><strong>' + key + ':</strong> найден</p>';
                if (window[key].markers) {
                    results += '<p>  - markers: Array(' + window[key].markers.length + ')</p>';
                }
            } else {
                results += '<p><strong>' + key + ':</strong> не найден</p>';
            }
        });
        
        // 3. Все глобальные переменные
        results += '<h6>🌍 Все глобальные переменные:</h6>';
        results += '<div style="max-height: 200px; overflow: auto; background: #f9f9f9; padding: 10px;">';
        for (let key in window) {
            try {
                if (typeof window[key] === 'object' && window[key] !== null && 
                    !key.startsWith('webkit') && !key.startsWith('chrome')) {
                    results += key + ': ' + typeof window[key] + '<br>';
                }
            } catch (e) {
                // Игнорируем ошибки доступа
            }
        }
        results += '</div>';
        
        addResult('JavaScript Анализ', results);
    }

    function analyzeEvents() {
        let results = '<h5>⚡ События и Обработчики</h5>';
        
        // 1. События на маркерах
        const markers = document.querySelectorAll('.mh-map-pin');
        results += '<h6>📍 События на маркерах:</h6>';
        
        markers.forEach((marker, index) => {
            results += '<p><strong>Маркер ' + index + ':</strong></p>';
            
            // Проверяем стандартные события
            const events = ['click', 'mouseover', 'mouseout', 'mousedown', 'mouseup'];
            events.forEach(eventType => {
                // Пытаемся получить обработчики событий
                const listeners = getEventListeners ? getEventListeners(marker) : null;
                if (listeners && listeners[eventType]) {
                    results += '  - ' + eventType + ': ' + listeners[eventType].length + ' обработчиков<br>';
                }
            });
        });
        
        // 2. Мутации DOM
        results += '<h6>🔄 Мониторинг изменений DOM:</h6>';
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                console.log('🔄 DOM изменение:', mutation.type, mutation.target);
            });
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true });
        results += '<p>✅ Мониторинг активирован</p>';
        
        addResult('События и Обработчики', results);
    }

    function analyzeNetwork() {
        let results = '<h5>🌐 Сетевые Запросы</h5>';
        
        // Перехватываем XMLHttpRequest и fetch
        results += '<h6>📡 Мониторинг AJAX запросов:</h6>';
        
        // Сохраняем оригинальные функции
        const originalXHR = XMLHttpRequest.prototype.open;
        const originalFetch = window.fetch;
        
        // Перехватываем XHR
        XMLHttpRequest.prototype.open = function(method, url) {
            console.log('📡 XHR запрос:', method, url);
            results += '<p>XHR: ' + method + ' ' + url + '</p>';
            
            this.addEventListener('load', function() {
                if (this.responseText) {
                    DA_COORDS.forEach(coord => {
                        if (this.responseText.includes(coord.lat.toString()) || 
                            this.responseText.includes(coord.lng.toString())) {
                            console.log('🎯 НАЙДЕНА DA КООРДИНАТА в XHR ответе!', url);
                            results += '<p style="background: yellow;">🎯 DA координата в ответе: ' + url + '</p>';
                        }
                    });
                }
            });
            
            return originalXHR.apply(this, arguments);
        };
        
        // Перехватываем fetch
        window.fetch = function() {
            console.log('📡 Fetch запрос:', arguments[0]);
            results += '<p>Fetch: ' + arguments[0] + '</p>';
            
            return originalFetch.apply(this, arguments).then(response => {
                if (response.ok) {
                    response.clone().text().then(text => {
                        DA_COORDS.forEach(coord => {
                            if (text.includes(coord.lat.toString()) || 
                                text.includes(coord.lng.toString())) {
                                console.log('🎯 НАЙДЕНА DA КООРДИНАТА в Fetch ответе!', arguments[0]);
                                results += '<p style="background: yellow;">🎯 DA координата в ответе: ' + arguments[0] + '</p>';
                            }
                        });
                    });
                }
                return response;
            });
        };
        
        results += '<p>✅ Перехват запросов активирован</p>';
        
        addResult('Сетевые Запросы', results);
    }

    function analyzeGoogleMaps() {
        let results = '<h5>🗺️ Google Maps Анализ</h5>';
        
        if (typeof google !== 'undefined' && google.maps) {
            results += '<p>✅ Google Maps API доступен</p>';
            
            // Ищем все карты на странице
            const mapDivs = document.querySelectorAll('div[style*="position"], .map, #map, [id*="map"], [class*="map"]');
            results += '<h6>🗺️ Потенциальные контейнеры карт (' + mapDivs.length + '):</h6>';
            
            mapDivs.forEach((div, index) => {
                results += '<div style="border: 1px solid #ccc; padding: 5px; margin: 5px;">';
                results += '<strong>Контейнер ' + index + ':</strong><br>';
                results += 'ID: ' + (div.id || 'нет') + '<br>';
                results += 'Class: ' + (div.className || 'нет') + '<br>';
                results += 'Размеры: ' + div.offsetWidth + 'x' + div.offsetHeight + '<br>';
                
                // Проверяем, есть ли в этом div маркеры
                const markersInDiv = div.querySelectorAll('.mh-map-pin');
                results += 'Маркеров внутри: ' + markersInDiv.length + '<br>';
                
                results += '</div>';
            });
            
            // Пытаемся найти экземпляры карт
            results += '<h6>🎯 Поиск экземпляров Google Maps:</h6>';
            
            // Проверяем глобальные переменные
            const mapVars = ['map', 'googleMap', 'myHomeMap', 'estateMap', 'mapInstance'];
            mapVars.forEach(varName => {
                if (window[varName] && window[varName].getCenter) {
                    results += '<p>✅ Найдена карта: ' + varName + '</p>';
                    try {
                        const center = window[varName].getCenter();
                        results += '  Центр: ' + center.lat() + ', ' + center.lng() + '<br>';
                        
                        // Проверяем маркеры карты
                        if (window[varName].markers) {
                            results += '  Маркеров: ' + window[varName].markers.length + '<br>';
                        }
                    } catch (e) {
                        results += '  Ошибка доступа к карте<br>';
                    }
                }
            });
            
        } else {
            results += '<p>❌ Google Maps API недоступен</p>';
        }
        
        addResult('Google Maps Анализ', results);
    }

    function analyzeCSS() {
        let results = '<h5>🎨 CSS и Стили</h5>';
        
        // Анализируем стили маркеров
        const markers = document.querySelectorAll('.mh-map-pin');
        results += '<h6>📍 Стили маркеров:</h6>';
        
        markers.forEach((marker, index) => {
            const computedStyle = window.getComputedStyle(marker);
            results += '<div style="border: 1px solid #ddd; padding: 5px; margin: 5px;">';
            results += '<strong>Маркер ' + index + ':</strong><br>';
            results += 'Position: ' + computedStyle.position + '<br>';
            results += 'Z-index: ' + computedStyle.zIndex + '<br>';
            results += 'Transform: ' + computedStyle.transform + '<br>';
            results += 'Top: ' + computedStyle.top + '<br>';
            results += 'Left: ' + computedStyle.left + '<br>';
            results += '</div>';
        });
        
        addResult('CSS Анализ', results);
    }

    function analyzeFinalConnections() {
        let results = '<h5>🔗 Финальный Анализ Связей</h5>';
        
        results += '<h6>🎯 Сводка поиска DA координат:</h6>';
        
        DA_COORDS.forEach((coord, index) => {
            results += '<div style="border: 2px solid #007cba; padding: 10px; margin: 10px;">';
            results += '<strong>DA Объявление ' + coord.id + '</strong><br>';
            results += 'Координаты: ' + coord.lat + ', ' + coord.lng + '<br>';
            results += 'Название: ' + coord.title + '<br>';
            
            // Ищем эти координаты везде
            let found = false;
            
            // В HTML
            if (document.documentElement.innerHTML.includes(coord.lat.toString())) {
                results += '✅ Широта найдена в HTML<br>';
                found = true;
            }
            if (document.documentElement.innerHTML.includes(coord.lng.toString())) {
                results += '✅ Долгота найдена в HTML<br>';
                found = true;
            }
            
            if (!found) {
                results += '❌ Координаты НЕ найдены в HTML<br>';
            }
            
            results += '</div>';
        });
        
        // Рекомендации
        results += '<h6>💡 Рекомендации:</h6>';
        results += '<ul>';
        results += '<li>Проверьте консоль браузера на предмет AJAX запросов</li>';
        results += '<li>Координаты могут загружаться динамически</li>';
        results += '<li>Возможно, нужно дождаться полной загрузки карты</li>';
        results += '<li>Проверьте Network вкладку в DevTools</li>';
        results += '</ul>';
        
        addResult('Финальный Анализ', results);
    }
    </script>

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
    return ob_get_clean();
}
?>
<?php
/**
 * DA Markers - РАБОТА С КЛАСТЕРАМИ
 * Обрабатывает кластеризованные маркеры и разворачивает их для поиска
 */

// Добавляем мета-бокс в админку объявлений
add_action('add_meta_boxes', 'add_da_cluster_meta_box');
function add_da_cluster_meta_box() {
    add_meta_box(
        'da_cluster_box',
        'DA Маркер (работа с кластерами)',
        'da_cluster_meta_box_callback',
        'estate',
        'side',
        'high'
    );
}

// Содержимое мета-бокса
function da_cluster_meta_box_callback($post) {
    wp_nonce_field('da_cluster_meta_box', 'da_cluster_meta_box_nonce');
    
    $value = get_post_meta($post->ID, '_da_cluster_enabled', true);
    
    echo '<label for="da_cluster_enabled">';
    echo '<input type="checkbox" id="da_cluster_enabled" name="da_cluster_enabled" value="1" ' . checked($value, '1', false) . ' />';
    echo ' Включить мигание маркера (с кластерами)';
    echo '</label>';
    echo '<p><small>Автоматически разворачивает кластеры</small></p>';
    
    echo '<hr><h4>Информация:</h4>';
    echo '<p><strong>ID:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Заголовок:</strong> ' . $post->post_title . '</p>';
    echo '<p><strong>Статус:</strong> ' . ($value ? '🟢 Включено' : '⚪ Выключено') . '</p>';
}

// Сохраняем значение галочки
add_action('save_post', 'save_da_cluster_meta_box_data');
function save_da_cluster_meta_box_data($post_id) {
    if (!isset($_POST['da_cluster_meta_box_nonce']) || !wp_verify_nonce($_POST['da_cluster_meta_box_nonce'], 'da_cluster_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['post_type']) && 'estate' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    if (isset($_POST['da_cluster_enabled'])) {
        update_post_meta($post_id, '_da_cluster_enabled', '1');
    } else {
        update_post_meta($post_id, '_da_cluster_enabled', '0');
    }
}

// AJAX для получения DA объявлений
add_action('wp_ajax_get_da_cluster', 'ajax_get_da_cluster');
add_action('wp_ajax_nopriv_get_da_cluster', 'ajax_get_da_cluster');
function ajax_get_da_cluster() {
    $da_posts = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => -1,
        'meta_query' => array(
            array(
                'key' => '_da_cluster_enabled',
                'value' => '1',
                'compare' => '='
            )
        )
    ));
    
    $da_data = array();
    
    foreach ($da_posts as $post) {
        $da_data[] = array(
            'id' => intval($post->ID),
            'title' => $post->post_title
        );
    }
    
    wp_send_json_success(array(
        'da_properties' => $da_data,
        'count' => count($da_data)
    ));
}

// CSS для мигания и кластеров
add_action('wp_head', 'da_cluster_css');
function da_cluster_css() {
    ?>
    <style>
    @keyframes da-cluster-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            transform: scale(1.2);
        }
    }

    .mh-map-pin.da-cluster-found {
        animation: da-cluster-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-cluster-found i {
        color: #ff0066 !important;
    }
    
    /* Скрываем временные инфобоксы */
    .da-temp-hidden {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    
    /* Подсветка активных кластеров */
    .da-cluster-processing {
        filter: drop-shadow(0 0 5px #00ff66) !important;
        animation: pulse 0.5s ease-in-out !important;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    </style>
    <?php
}

// JavaScript - работа с кластерами
add_action('wp_footer', 'da_cluster_script');
function da_cluster_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🌟 DA КЛАСТЕРНЫЙ ПОИСК - запущено');
        
        let daProperties = [];
        let foundMarkers = [];
        let searchInProgress = false;
        let currentZoomLevel = null;
        
        // Получаем DA данные
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_cluster'
                }
            });
        }
        
        // Находим все кластеры на карте
        function findClusters() {
            const clusters = [];
            
            // Ищем кластеры по разным селекторам
            const clusterSelectors = [
                '[style*="cluster"]',           // По background-image содержащему cluster
                '[style*="background-image"]',  // Все элементы с background
                'div[style*="position: absolute"][style*="cursor: pointer"]' // По стилям позиционирования
            ];
            
            clusterSelectors.forEach(selector => {
                $(selector).each(function() {
                    const $el = $(this);
                    const style = $el.attr('style') || '';
                    const text = $el.text().trim();
                    
                    // Проверяем что это кластер (содержит число и background-image с cluster)
                    if (style.includes('cluster') && /^\d+$/.test(text)) {
                        const clusterSize = parseInt(text);
                        if (clusterSize > 1) {
                            clusters.push({
                                element: $el,
                                size: clusterSize,
                                text: text
                            });
                        }
                    }
                });
            });
            
            // Удаляем дубликаты
            const uniqueClusters = [];
            clusters.forEach(cluster => {
                const exists = uniqueClusters.some(unique => 
                    unique.text === cluster.text && 
                    Math.abs(cluster.element.offset().top - unique.element.offset().top) < 10
                );
                if (!exists) {
                    uniqueClusters.push(cluster);
                }
            });
            
            return uniqueClusters;
        }
        
        // Разворачиваем кластер (кликаем по нему)
        function expandCluster(cluster) {
            return new Promise((resolve) => {
                console.log('📦 Разворачиваем кластер:', cluster.text, 'маркеров');
                
                const $clusterEl = cluster.element;
                
                // Подсвечиваем кластер
                $clusterEl.addClass('da-cluster-processing');
                
                // Запоминаем текущее количество маркеров
                const beforeMarkers = $('.mh-map-pin').length;
                
                // Кликаем по кластеру
                $clusterEl.trigger('click');
                
                // Ждем появления новых маркеров
                let checkAttempts = 0;
                const maxAttempts = 20;
                
                function checkForNewMarkers() {
                    const afterMarkers = $('.mh-map-pin').length;
                    checkAttempts++;
                    
                    if (afterMarkers > beforeMarkers || checkAttempts >= maxAttempts) {
                        console.log('✅ Кластер развернут:', beforeMarkers, '->', afterMarkers, 'маркеров');
                        $clusterEl.removeClass('da-cluster-processing');
                        resolve(afterMarkers - beforeMarkers);
                    } else {
                        setTimeout(checkForNewMarkers, 200);
                    }
                }
                
                setTimeout(checkForNewMarkers, 500);
            });
        }
        
        // Незаметный поиск в маркерах (оптимизированный)
        function silentSearchMarkers() {
            return new Promise((resolve) => {
                console.log('🤫 НЕЗАМЕТНЫЙ поиск в маркерах');
                
                const $markers = $('.mh-map-pin');
                if ($markers.length === 0) {
                    console.log('⏳ Маркеры не найдены');
                    resolve(0);
                    return;
                }
                
                console.log('📍 Маркеров для проверки:', $markers.length);
                
                let foundCount = 0;
                let currentIndex = 0;
                
                function checkNextMarker() {
                    if (currentIndex >= $markers.length) {
                        console.log('🏁 Проверка маркеров завершена, найдено:', foundCount);
                        resolve(foundCount);
                        return;
                    }
                    
                    const $marker = $markers.eq(currentIndex);
                    
                    // Скрываем все существующие инфобоксы
                    $('.infoBox, .mh-map-infobox').addClass('da-temp-hidden');
                    
                    // Быстрый hover
                    $marker.trigger('mouseenter');
                    
                    setTimeout(() => {
                        // Ищем новые инфобоксы
                        const $newInfoboxes = $('.infoBox, .mh-map-infobox').not('.da-temp-hidden').filter(':visible');
                        
                        if ($newInfoboxes.length > 0) {
                            const infoboxText = $newInfoboxes.first().text();
                            
                            // Проверяем совпадение с DA объявлениями
                            const matched = daProperties.some(daProperty => {
                                if (infoboxText.includes(daProperty.title)) {
                                    console.log('✅ НАЙДЕНО: Маркер #' + currentIndex + ' -> "' + daProperty.title + '"');
                                    $marker.addClass('da-cluster-found');
                                    foundMarkers.push({
                                        marker: $marker,
                                        index: currentIndex,
                                        title: daProperty.title
                                    });
                                    foundCount++;
                                    return true;
                                }
                                return false;
                            });
                            
                            // Скрываем инфобокс немедленно
                            $newInfoboxes.addClass('da-temp-hidden');
                        }
                        
                        $marker.trigger('mouseleave');
                        
                        // Восстанавливаем инфобоксы
                        setTimeout(() => {
                            $('.infoBox, .mh-map-infobox').removeClass('da-temp-hidden');
                            currentIndex++;
                            setTimeout(checkNextMarker, 50); // Быстрый переход
                        }, 25);
                        
                    }, 80); // Минимальное время для инфобокса
                }
                
                checkNextMarker();
            });
        }
        
        // Основной процесс поиска
        async function startClusterSearch() {
            if (searchInProgress) {
                console.log('🔄 Поиск уже выполняется...');
                return;
            }
            
            searchInProgress = true;
            console.log('🚀 === НАЧИНАЕМ КЛАСТЕРНЫЙ ПОИСК ===');
            
            try {
                // Сбрасываем предыдущие результаты
                $('.mh-map-pin').removeClass('da-cluster-found');
                foundMarkers = [];
                
                // 1. Ищем кластеры
                const clusters = findClusters();
                console.log('📦 Найдено кластеров:', clusters.length);
                
                if (clusters.length > 0) {
                    // 2. Разворачиваем кластеры по очереди
                    for (let i = 0; i < clusters.length; i++) {
                        const cluster = clusters[i];
                        await expandCluster(cluster);
                        
                        // Ждем стабилизации карты
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                    
                    // Ждем окончательной стабилизации
                    await new Promise(resolve => setTimeout(resolve, 2000));
                }
                
                // 3. Ищем в маркерах
                const foundCount = await silentSearchMarkers();
                
                // 4. Показываем результаты
                console.log('🏁 === РЕЗУЛЬТАТЫ КЛАСТЕРНОГО ПОИСКА ===');
                console.log('📦 Обработано кластеров:', clusters.length);
                console.log('📍 Всего маркеров на карте:', $('.mh-map-pin').length);
                console.log('🔴 Найдено DA маркеров:', foundCount);
                console.log('🎯 DA объявлений в базе:', daProperties.length);
                
                if (foundCount > 0) {
                    console.log('🎉 УСПЕХ! Найдены DA маркеры в кластерах!');
                    foundMarkers.forEach(data => {
                        console.log('📌 Маркер #' + data.index + ' -> "' + data.title + '"');
                    });
                } else {
                    console.log('❌ DA объявления не найдены в кластерах');
                }
                
            } catch (error) {
                console.error('❌ Ошибка кластерного поиска:', error);
            } finally {
                searchInProgress = false;
            }
        }
        
        // Мониторинг изменений карты
        function setupMapMonitoring() {
            if (!window.MutationObserver) return;
            
            let debounceTimer;
            let lastClusterCount = 0;
            let lastMarkerCount = 0;
            
            const observer = new MutationObserver(function(mutations) {
                const currentClusters = findClusters().length;
                const currentMarkers = $('.mh-map-pin').length;
                
                // Проверяем значительные изменения
                if (Math.abs(currentClusters - lastClusterCount) > 1 || 
                    Math.abs(currentMarkers - lastMarkerCount) > 5) {
                    
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        if (!searchInProgress) {
                            console.log('🔄 Изменения на карте, повторный поиск...');
                            console.log('📦 Кластеры:', lastClusterCount, '->', currentClusters);
                            console.log('📍 Маркеры:', lastMarkerCount, '->', currentMarkers);
                            
                            lastClusterCount = currentClusters;
                            lastMarkerCount = currentMarkers;
                            
                            startClusterSearch();
                        }
                    }, 3000); // Пауза для стабилизации
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style']
            });
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления для кластерного поиска:', daProperties);
                
                // Начинаем поиск после загрузки карты
                setTimeout(() => {
                    startClusterSearch();
                }, 4000);
                
                // Настраиваем мониторинг
                setupMapMonitoring();
                
            } else {
                console.log('⚠️ Нет DA объявлений для поиска');
            }
        });
    });
    </script>
    <?php
}
?>
<?php
/**
 * DA Markers - ПОИСК ПО ЗАГОЛОВКАМ
 * Ищет маркеры по совпадению title в инфобоксах
 */

// Добавляем мета-бокс в админку объявлений
add_action('add_meta_boxes', 'add_da_marker_meta_box');
function add_da_marker_meta_box() {
    add_meta_box(
        'da_marker_box',
        'DA Маркер (мигание на карте)',
        'da_marker_meta_box_callback',
        'estate',
        'side',
        'high'
    );
}

// Содержимое мета-бокса
function da_marker_meta_box_callback($post) {
    wp_nonce_field('da_marker_meta_box', 'da_marker_meta_box_nonce');
    
    $value = get_post_meta($post->ID, '_da_marker_enabled', true);
    
    echo '<label for="da_marker_enabled">';
    echo '<input type="checkbox" id="da_marker_enabled" name="da_marker_enabled" value="1" ' . checked($value, '1', false) . ' />';
    echo ' Включить мигание маркера на карте';
    echo '</label>';
    echo '<p><small>Поиск по заголовку в инфобоксе</small></p>';
    
    echo '<hr><h4>Информация:</h4>';
    echo '<p><strong>ID объявления:</strong> ' . $post->ID . '</p>';
    echo '<p><strong>Заголовок:</strong> ' . $post->post_title . '</p>';
    echo '<p><strong>Статус DA:</strong> ' . ($value ? '🟢 Включено' : '⚪ Выключено') . '</p>';
    
    if ($value) {
        echo '<p style="color: green;">✅ Маркер будет найден по заголовку</p>';
        echo '<p><small>На карте система найдёт инфобокс с заголовком:<br><strong>"' . esc_html($post->post_title) . '"</strong></small></p>';
    }
}

// Сохраняем значение галочки
add_action('save_post', 'save_da_marker_meta_box_data');
function save_da_marker_meta_box_data($post_id) {
    if (!isset($_POST['da_marker_meta_box_nonce']) || !wp_verify_nonce($_POST['da_marker_meta_box_nonce'], 'da_marker_meta_box')) {
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

    if (isset($_POST['da_marker_enabled'])) {
        update_post_meta($post_id, '_da_marker_enabled', '1');
    } else {
        update_post_meta($post_id, '_da_marker_enabled', '0');
    }
}

// AJAX для получения DA объявлений с заголовками
add_action('wp_ajax_get_da_titles', 'ajax_get_da_titles');
add_action('wp_ajax_nopriv_get_da_titles', 'ajax_get_da_titles');
function ajax_get_da_titles() {
    $da_posts = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => -1,
        'meta_query' => array(
            array(
                'key' => '_da_marker_enabled',
                'value' => '1',
                'compare' => '='
            )
        )
    ));
    
    $da_data = array();
    
    foreach ($da_posts as $post) {
        $da_data[] = array(
            'id' => intval($post->ID),
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'url' => get_permalink($post->ID)
        );
    }
    
    wp_send_json_success(array(
        'da_properties' => $da_data,
        'count' => count($da_data)
    ));
}

// CSS для мигания
add_action('wp_head', 'da_title_css');
function da_title_css() {
    ?>
    <style>
    @keyframes da-title-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066) drop-shadow(0 0 20px #ff0066);
            transform: scale(1);
            opacity: 1;
        }
        50% { 
            filter: drop-shadow(0 0 15px #ff0066) drop-shadow(0 0 30px #ff0066);
            transform: scale(1.2);
            opacity: 0.8;
        }
    }

    .mh-map-pin.da-title-active {
        animation: da-title-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-title-active i {
        color: #ff0066 !important;
    }

    .mh-map-pin.da-title-demo {
        animation: da-title-blink 1.5s infinite;
    }

    .mh-map-pin.da-title-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript - поиск по заголовкам
add_action('wp_footer', 'da_title_script');
function da_title_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('📝 DA ПОИСК ПО ЗАГОЛОВКАМ - запущено');
        
        let daProperties = [];
        let foundMarkers = new Map();
        let searchAttempts = 0;
        
        // Получаем DA данные
        function fetchDAData() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_titles'
                }
            });
        }
        
        // Функция нормализации текста для сравнения
        function normalizeText(text) {
            return text.toLowerCase()
                      .replace(/[^\w\s\u0400-\u04FF]/g, '') // Убираем спецсимволы, оставляем кириллицу
                      .replace(/\s+/g, ' ')
                      .trim();
        }
        
        // Функция поиска маркеров по заголовкам
        function findMarkersByTitles() {
            searchAttempts++;
            console.log('🔍 Поиск по заголовкам #' + searchAttempts);
            
            const $markers = $('.mh-map-pin');
            const $infoBoxes = $('.infoBox, .mh-map-infobox, [class*="infobox"], [class*="info-box"]');
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('📋 Инфобоксов найдено:', $infoBoxes.length);
            console.log('🎯 DA объявлений для поиска:', daProperties.length);
            
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (searchAttempts < 10) {
                    setTimeout(findMarkersByTitles, 1000);
                }
                return;
            }
            
            // Убираем предыдущие классы
            $markers.removeClass('da-title-active da-title-demo');
            foundMarkers.clear();
            
            let activatedCount = 0;
            
            // Для каждого DA объявления
            daProperties.forEach(daProperty => {
                const daTitle = normalizeText(daProperty.title);
                console.log('🔍 Ищем DA: "' + daProperty.title + '"');
                console.log('   Нормализованный: "' + daTitle + '"');
                
                let found = false;
                
                // Метод 1: Поиск в инфобоксах
                $infoBoxes.each(function(infoIndex) {
                    if (found) return;
                    
                    const $infoBox = $(this);
                    const infoText = $infoBox.text() || '';
                    const infoHTML = $infoBox.html() || '';
                    
                    // Ищем заголовки в инфобоксе
                    const titles = [];
                    
                    // Ищем в h1-h6, .title, [title], и других элементах с заголовками
                    $infoBox.find('h1, h2, h3, h4, h5, h6, .title, .name, [title], a[title]').each(function() {
                        const $el = $(this);
                        const text = $el.text().trim();
                        const titleAttr = $el.attr('title');
                        
                        if (text) titles.push(text);
                        if (titleAttr) titles.push(titleAttr);
                    });
                    
                    // Также ищем в ссылках
                    $infoBox.find('a').each(function() {
                        const $link = $(this);
                        const linkText = $link.text().trim();
                        const linkTitle = $link.attr('title');
                        const linkHref = $link.attr('href');
                        
                        if (linkText) titles.push(linkText);
                        if (linkTitle) titles.push(linkTitle);
                        
                        // Проверяем URL на совпадение
                        if (linkHref && daProperty.url && linkHref.includes(daProperty.slug)) {
                            titles.push(daProperty.title);
                        }
                    });
                    
                    console.log('   📋 Заголовки в инфобоксе #' + infoIndex + ':', titles);
                    
                    // Проверяем совпадения
                    for (let title of titles) {
                        const normalizedTitle = normalizeText(title);
                        
                        // Точное совпадение
                        if (normalizedTitle === daTitle) {
                            console.log('✅ ТОЧНОЕ СОВПАДЕНИЕ! "' + title + '"');
                            found = true;
                            break;
                        }
                        
                        // Частичное совпадение (75% и больше)
                        const similarity = calculateSimilarity(normalizedTitle, daTitle);
                        if (similarity >= 0.75) {
                            console.log('✅ ЧАСТИЧНОЕ СОВПАДЕНИЕ (' + Math.round(similarity * 100) + '%): "' + title + '"');
                            found = true;
                            break;
                        }
                    }
                    
                    if (found) {
                        // Теперь ищем ближайший маркер к этому инфобоксу
                        const $nearestMarker = findNearestMarker($infoBox, $markers);
                        if ($nearestMarker && $nearestMarker.length) {
                            $nearestMarker.addClass('da-title-active');
                            activatedCount++;
                            
                            const markerIndex = $markers.index($nearestMarker);
                            console.log('🎯 АКТИВИРОВАН маркер #' + markerIndex + ' для DA "' + daProperty.title + '"');
                            
                            foundMarkers.set(daProperty.id, {
                                marker: $nearestMarker,
                                index: markerIndex,
                                title: daProperty.title
                            });
                        }
                    }
                });
                
                // Метод 2: Поиск по всему DOM (если не найдено в инфобоксах)
                if (!found) {
                    console.log('🔍 Поиск по всему DOM для "' + daProperty.title + '"');
                    
                    // Ищем элементы содержащие заголовок
                    const searchSelectors = [
                        '*:contains("' + daProperty.title + '")',
                        '[title*="' + daProperty.title + '"]',
                        '[alt*="' + daProperty.title + '"]',
                        'a[href*="' + daProperty.slug + '"]'
                    ];
                    
                    for (let selector of searchSelectors) {
                        try {
                            $(selector).each(function() {
                                if (found) return;
                                
                                const $element = $(this);
                                const $nearestMarker = findNearestMarker($element, $markers);
                                
                                if ($nearestMarker && $nearestMarker.length && !$nearestMarker.hasClass('da-title-active')) {
                                    $nearestMarker.addClass('da-title-active');
                                    activatedCount++;
                                    found = true;
                                    
                                    const markerIndex = $markers.index($nearestMarker);
                                    console.log('✅ НАЙДЕН в DOM! Маркер #' + markerIndex + ' для "' + daProperty.title + '"');
                                    
                                    foundMarkers.set(daProperty.id, {
                                        marker: $nearestMarker,
                                        index: markerIndex,
                                        title: daProperty.title
                                    });
                                }
                            });
                        } catch (e) {
                            // Игнорируем ошибки селекторов
                        }
                    }
                }
                
                if (!found) {
                    console.log('❌ НЕ НАЙДЕН маркер для "' + daProperty.title + '"');
                }
            });
            
            // Демо режим если ничего не найдено
            if (activatedCount === 0 && daProperties.length > 0) {
                console.log('🟡 Демо режим - активируем первый маркер');
                $markers.slice(0, 1).addClass('da-title-demo');
            }
            
            // Статистика
            setTimeout(() => {
                const activeMarkers = $('.mh-map-pin.da-title-active').length;
                const demoMarkers = $('.mh-map-pin.da-title-demo').length;
                
                console.log('🏁 === СТАТИСТИКА ПОИСКА ПО ЗАГОЛОВКАМ ===');
                console.log('🔴 Найденных DA маркеров:', activeMarkers);
                console.log('🟢 Демо маркеров:', demoMarkers);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('🎯 DA объявлений:', daProperties.length);
                console.log('💾 Сохранённых совпадений:', foundMarkers.size);
                
                if (activeMarkers > 0) {
                    console.log('🎉 УСПЕХ! Маркеры найдены по заголовкам!');
                    foundMarkers.forEach((data, id) => {
                        console.log('📌 DA "' + data.title + '" -> Маркер #' + data.index);
                    });
                }
            }, 300);
        }
        
        // Функция поиска ближайшего маркера к элементу
        function findNearestMarker($element, $markers) {
            if ($markers.length === 0) return null;
            
            // Получаем позицию элемента
            const elementPos = $element.offset();
            if (!elementPos) return $markers.first();
            
            let nearestMarker = null;
            let minDistance = Infinity;
            
            $markers.each(function() {
                const $marker = $(this);
                const markerPos = $marker.offset();
                
                if (markerPos) {
                    const distance = Math.sqrt(
                        Math.pow(elementPos.left - markerPos.left, 2) + 
                        Math.pow(elementPos.top - markerPos.top, 2)
                    );
                    
                    if (distance < minDistance) {
                        minDistance = distance;
                        nearestMarker = $marker;
                    }
                }
            });
            
            return nearestMarker || $markers.first();
        }
        
        // Функция расчёта схожести строк
        function calculateSimilarity(str1, str2) {
            const longer = str1.length > str2.length ? str1 : str2;
            const shorter = str1.length > str2.length ? str2 : str1;
            
            if (longer.length === 0) return 1.0;
            
            const distance = levenshteinDistance(longer, shorter);
            return (longer.length - distance) / longer.length;
        }
        
        // Расстояние Левенштейна
        function levenshteinDistance(str1, str2) {
            const matrix = [];
            
            for (let i = 0; i <= str2.length; i++) {
                matrix[i] = [i];
            }
            
            for (let j = 0; j <= str1.length; j++) {
                matrix[0][j] = j;
            }
            
            for (let i = 1; i <= str2.length; i++) {
                for (let j = 1; j <= str1.length; j++) {
                    if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                        matrix[i][j] = matrix[i - 1][j - 1];
                    } else {
                        matrix[i][j] = Math.min(
                            matrix[i - 1][j - 1] + 1,
                            matrix[i][j - 1] + 1,
                            matrix[i - 1][j] + 1
                        );
                    }
                }
            }
            
            return matrix[str2.length][str1.length];
        }
        
        // Запуск
        fetchDAData().done(function(response) {
            if (response.success && response.data.da_properties) {
                daProperties = response.data.da_properties;
                console.log('📡 Получены DA объявления для поиска по заголовкам:', daProperties);
                
                // Первый поиск
                setTimeout(findMarkersByTitles, 2000);
                
                // Мониторим изменения DOM
                if (window.MutationObserver) {
                    let debounceTimer;
                    
                    const observer = new MutationObserver(function(mutations) {
                        let hasChanges = false;
                        
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes || mutation.removedNodes) {
                                for (let node of [...(mutation.addedNodes || []), ...(mutation.removedNodes || [])]) {
                                    if (node.nodeType === 1) {
                                        if ($(node).find('.mh-map-pin, .infoBox').length > 0 || 
                                            $(node).hasClass('mh-map-pin') || 
                                            $(node).hasClass('infoBox')) {
                                            hasChanges = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        });
                        
                        if (hasChanges) {
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(() => {
                                console.log('🔄 Изменения маркеров/инфобоксов обнаружены, повторный поиск...');
                                findMarkersByTitles();
                            }, 500);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
            } else {
                console.log('⚠️ Нет DA объявлений для поиска');
            }
        });
    });
    </script>
    <?php
}
?>
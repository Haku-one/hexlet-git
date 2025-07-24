<?php
/**
 * ТЕСТОВЫЙ ШОРТКОД ДЛЯ АНАЛИЗА DA ДАННЫХ
 * Использование: [da_test]
 */

// Регистрируем шорткод
add_shortcode('da_test', 'da_test_shortcode');

function da_test_shortcode() {
    ob_start();
    ?>
    
    <div id="da-test-results" style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 2px solid #333;">
        <h3>🔍 Тестирование DA данных...</h3>
        <div id="da-test-output">Загрузка...</div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        console.log('🔍 === DA ТЕСТ ЗАПУЩЕН ===');
        
        var testResults = {
            timestamp: new Date().toLocaleString(),
            page_url: window.location.href,
            markers_found: [],
            global_objects: {},
            estate_posts: [],
            taxonomies: [],
            meta_fields: [],
            errors: []
        };

        // Функция для безопасного получения данных
        function safeGet(obj, path, defaultValue) {
            try {
                var result = obj;
                var pathArray = path.split('.');
                for (var i = 0; i < pathArray.length; i++) {
                    result = result[pathArray[i]];
                    if (result === undefined || result === null) {
                        return defaultValue;
                    }
                }
                return result;
            } catch (e) {
                return defaultValue;
            }
        }

        // 1. Анализ маркеров на странице
        function analyzeMarkers() {
            console.log('🔍 Анализ маркеров...');
            
            var $markers = $('.mh-map-pin');
            console.log('Найдено маркеров:', $markers.length);
            
            $markers.each(function(index) {
                var $marker = $(this);
                var markerData = {
                    index: index,
                    html: $marker[0].outerHTML,
                    text: $marker.text(),
                    attributes: {},
                    data_attributes: {},
                    classes: $marker.attr('class'),
                    parent_attributes: {}
                };
                
                // Собираем все атрибуты
                $.each(this.attributes, function() {
                    if(this.specified) {
                        markerData.attributes[this.name] = this.value;
                        if (this.name.startsWith('data-')) {
                            markerData.data_attributes[this.name] = this.value;
                        }
                    }
                });
                
                // Собираем атрибуты родителя
                var $parent = $marker.parent();
                if ($parent.length) {
                    $.each($parent[0].attributes, function() {
                        if(this.specified && this.name.startsWith('data-')) {
                            markerData.parent_attributes[this.name] = this.value;
                        }
                    });
                }
                
                testResults.markers_found.push(markerData);
            });
        }

        // 2. Анализ глобальных объектов
        function analyzeGlobalObjects() {
            console.log('🔍 Анализ глобальных объектов...');
            
            var globalChecks = [
                'MyHome', 'MyHomeMapData', 'myhome_localized', 
                'map', 'myHomeMap', 'googleMap', 'myMap',
                'estate_data', 'properties_data', 'markers_data'
            ];
            
            globalChecks.forEach(function(varName) {
                if (typeof window[varName] !== 'undefined') {
                    try {
                        testResults.global_objects[varName] = JSON.parse(JSON.stringify(window[varName]));
                    } catch (e) {
                        testResults.global_objects[varName] = 'Error: ' + e.message;
                    }
                }
            });
        }

        // 3. AJAX запрос для получения estate постов
        function getEstatePosts() {
            console.log('🔍 Получение estate постов...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'da_test_get_posts'
                },
                success: function(response) {
                    testResults.estate_posts = response;
                    checkTaxonomies();
                },
                error: function(xhr, status, error) {
                    testResults.errors.push('AJAX Error: ' + error);
                    displayResults();
                }
            });
        }

        // 4. AJAX запрос для проверки таксономий
        function checkTaxonomies() {
            console.log('🔍 Проверка таксономий...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'da_test_get_taxonomies'
                },
                success: function(response) {
                    testResults.taxonomies = response;
                    getMetaFields();
                },
                error: function(xhr, status, error) {
                    testResults.errors.push('Taxonomy Error: ' + error);
                    displayResults();
                }
            });
        }

        // 5. AJAX запрос для получения мета-полей
        function getMetaFields() {
            console.log('🔍 Получение мета-полей...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'da_test_get_meta'
                },
                success: function(response) {
                    testResults.meta_fields = response;
                    displayResults();
                },
                error: function(xhr, status, error) {
                    testResults.errors.push('Meta Error: ' + error);
                    displayResults();
                }
            });
        }

        // 6. Отображение результатов
        function displayResults() {
            console.log('📊 === РЕЗУЛЬТАТЫ ТЕСТА ===', testResults);
            
            var output = '<h3>📊 Результаты анализа</h3>';
            output += '<p><strong>Время:</strong> ' + testResults.timestamp + '</p>';
            output += '<p><strong>Страница:</strong> ' + testResults.page_url + '</p>';
            
            // Маркеры
            output += '<h4>📍 Маркеры (' + testResults.markers_found.length + ')</h4>';
            if (testResults.markers_found.length > 0) {
                output += '<pre style="background: white; padding: 10px; max-height: 200px; overflow-y: auto;">';
                output += JSON.stringify(testResults.markers_found, null, 2);
                output += '</pre>';
            } else {
                output += '<p style="color: red;">Маркеры не найдены!</p>';
            }
            
            // Глобальные объекты
            output += '<h4>🌍 Глобальные объекты</h4>';
            output += '<pre style="background: white; padding: 10px; max-height: 200px; overflow-y: auto;">';
            output += JSON.stringify(testResults.global_objects, null, 2);
            output += '</pre>';
            
            // Estate посты
            output += '<h4>🏠 Estate посты</h4>';
            output += '<pre style="background: white; padding: 10px; max-height: 200px; overflow-y: auto;">';
            output += JSON.stringify(testResults.estate_posts, null, 2);
            output += '</pre>';
            
            // Таксономии
            output += '<h4>📂 Таксономии</h4>';
            output += '<pre style="background: white; padding: 10px; max-height: 200px; overflow-y: auto;">';
            output += JSON.stringify(testResults.taxonomies, null, 2);
            output += '</pre>';
            
            // Мета-поля
            output += '<h4>🔧 Мета-поля</h4>';
            output += '<pre style="background: white; padding: 10px; max-height: 200px; overflow-y: auto;">';
            output += JSON.stringify(testResults.meta_fields, null, 2);
            output += '</pre>';
            
            // Ошибки
            if (testResults.errors.length > 0) {
                output += '<h4 style="color: red;">❌ Ошибки</h4>';
                output += '<ul>';
                testResults.errors.forEach(function(error) {
                    output += '<li style="color: red;">' + error + '</li>';
                });
                output += '</ul>';
            }
            
            // Инструкции
            output += '<h4>📋 Инструкции</h4>';
            output += '<p>Скопируйте всю информацию выше и отправьте разработчику для создания правильного кода.</p>';
            
            $('#da-test-output').html(output);
        }

        // Запуск тестирования
        setTimeout(function() {
            analyzeMarkers();
            analyzeGlobalObjects();
            getEstatePosts();
        }, 2000); // Ждем 2 секунды для загрузки карты
    });
    </script>

    <?php
    return ob_get_clean();
}

// AJAX обработчик для получения estate постов
add_action('wp_ajax_da_test_get_posts', 'da_test_get_posts');
add_action('wp_ajax_nopriv_da_test_get_posts', 'da_test_get_posts');

function da_test_get_posts() {
    $result = array();
    
    // Пробуем разные способы получения estate постов
    $attempts = array(
        array('post_type' => 'estate', 'posts_per_page' => 10),
        array('post_type' => 'property', 'posts_per_page' => 10),
        array('post_type' => 'listing', 'posts_per_page' => 10),
    );
    
    foreach ($attempts as $attempt) {
        $posts = get_posts($attempt);
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $post_data = array(
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'status' => $post->post_status,
                    'meta' => array(),
                    'terms' => array()
                );
                
                // Получаем все мета-поля
                $all_meta = get_post_meta($post->ID);
                foreach ($all_meta as $key => $value) {
                    if (strpos($key, 'lat') !== false || 
                        strpos($key, 'lng') !== false || 
                        strpos($key, 'address') !== false ||
                        strpos($key, 'da') !== false ||
                        strpos($key, 'special') !== false) {
                        $post_data['meta'][$key] = $value[0];
                    }
                }
                
                // Получаем все термины
                $taxonomies = get_object_taxonomies($post->post_type);
                foreach ($taxonomies as $taxonomy) {
                    $terms = wp_get_post_terms($post->ID, $taxonomy);
                    if (!empty($terms)) {
                        $post_data['terms'][$taxonomy] = $terms;
                    }
                }
                
                $result[] = $post_data;
            }
            break; // Если нашли посты, прекращаем поиск
        }
    }
    
    wp_send_json($result);
}

// AJAX обработчик для получения таксономий
add_action('wp_ajax_da_test_get_taxonomies', 'da_test_get_taxonomies');
add_action('wp_ajax_nopriv_da_test_get_taxonomies', 'da_test_get_taxonomies');

function da_test_get_taxonomies() {
    $result = array();
    
    $post_types = array('estate', 'property', 'listing');
    
    foreach ($post_types as $post_type) {
        if (post_type_exists($post_type)) {
            $taxonomies = get_object_taxonomies($post_type, 'objects');
            foreach ($taxonomies as $taxonomy) {
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy->name,
                    'hide_empty' => false,
                    'number' => 20
                ));
                
                $result[$taxonomy->name] = array(
                    'label' => $taxonomy->label,
                    'post_type' => $post_type,
                    'terms' => $terms
                );
            }
        }
    }
    
    wp_send_json($result);
}

// AJAX обработчик для получения мета-полей
add_action('wp_ajax_da_test_get_meta', 'da_test_get_meta');
add_action('wp_ajax_nopriv_da_test_get_meta', 'da_test_get_meta');

function da_test_get_meta() {
    global $wpdb;
    
    // Ищем все мета-ключи, связанные с недвижимостью
    $meta_keys = $wpdb->get_results("
        SELECT DISTINCT meta_key, COUNT(*) as count 
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type IN ('estate', 'property', 'listing')
        AND (
            meta_key LIKE '%lat%' OR 
            meta_key LIKE '%lng%' OR 
            meta_key LIKE '%address%' OR
            meta_key LIKE '%da%' OR
            meta_key LIKE '%special%' OR
            meta_key LIKE '%offer%' OR
            meta_key LIKE '%coord%' OR
            meta_key LIKE '%location%'
        )
        GROUP BY meta_key
        ORDER BY count DESC
        LIMIT 50
    ");
    
    wp_send_json($meta_keys);
}
?>
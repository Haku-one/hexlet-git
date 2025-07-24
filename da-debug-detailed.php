<?php
/**
 * DA Debug - Detailed Analysis
 * Получить точную информацию о DA объявлениях и их координатах
 */

add_shortcode('da_debug_detailed', 'da_debug_detailed_shortcode');
function da_debug_detailed_shortcode() {
    ob_start();
    ?>
    <div id="da-debug-detailed">
        <h3>🔍 Детальный анализ DA объявлений</h3>
        <button onclick="startDetailedAnalysis()">Запустить анализ</button>
        <div id="debug-results"></div>
    </div>

    <script>
    function startDetailedAnalysis() {
        console.log('🔍 Запуск детального анализа DA данных...');
        
        // Получаем DA данные
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'da_debug_get_detailed_data'
            },
            success: function(response) {
                if (response.success) {
                    console.log('📊 DA объявления с координатами:', response.data);
                    analyzeMarkersWithCoordinates(response.data);
                } else {
                    console.error('❌ Ошибка получения DA данных');
                }
            }
        });
    }
    
    function analyzeMarkersWithCoordinates(daData) {
        let $markers = jQuery('.mh-map-pin');
        console.log('🗺️ Анализ маркеров с координатами...');
        console.log('Найдено маркеров:', $markers.length);
        console.log('DA объявлений:', daData.da_properties.length);
        
        let analysis = {
            markers: [],
            da_properties: daData.da_properties,
            matches: []
        };
        
        // Анализируем каждый маркер
        $markers.each(function(index, element) {
            let $marker = jQuery(element);
            let markerInfo = {
                index: index,
                html: element.outerHTML,
                position: $marker.offset(),
                parent_chain: []
            };
            
            // Проходим по всем родительским элементам
            let $parent = $marker.parent();
            let depth = 0;
            while ($parent.length && depth < 10) {
                let parentInfo = {
                    tag: $parent.prop('tagName'),
                    classes: $parent.attr('class') || '',
                    id: $parent.attr('id') || '',
                    attributes: {}
                };
                
                // Собираем все атрибуты
                if ($parent[0].attributes) {
                    for (let attr of $parent[0].attributes) {
                        parentInfo.attributes[attr.name] = attr.value;
                    }
                }
                
                markerInfo.parent_chain.push(parentInfo);
                $parent = $parent.parent();
                depth++;
            }
            
            analysis.markers.push(markerInfo);
        });
        
        // Ищем совпадения координат
        analysis.da_properties.forEach(function(daProp, daIndex) {
            if (daProp.lat && daProp.lng) {
                let propLat = parseFloat(daProp.lat);
                let propLng = parseFloat(daProp.lng);
                
                analysis.markers.forEach(function(marker, markerIndex) {
                    // Ищем координаты в атрибутах родительских элементов
                    marker.parent_chain.forEach(function(parent, parentDepth) {
                        for (let [attrName, attrValue] of Object.entries(parent.attributes)) {
                            // Проверяем различные форматы координат
                            if (attrName.includes('lat') || attrName.includes('lng') || 
                                attrName.includes('coord') || attrName.includes('position')) {
                                
                                let coordMatch = attrValue.toString().match(/(-?\d+\.?\d*)/g);
                                if (coordMatch && coordMatch.length >= 2) {
                                    let markerLat = parseFloat(coordMatch[0]);
                                    let markerLng = parseFloat(coordMatch[1]);
                                    
                                    if (Math.abs(markerLat - propLat) < 0.001 && 
                                        Math.abs(markerLng - propLng) < 0.001) {
                                        analysis.matches.push({
                                            da_property: daProp,
                                            marker_index: markerIndex,
                                            parent_depth: parentDepth,
                                            attribute: attrName,
                                            coordinates: {
                                                da: {lat: propLat, lng: propLng},
                                                marker: {lat: markerLat, lng: markerLng}
                                            }
                                        });
                                    }
                                }
                            }
                        }
                    });
                });
            }
        });
        
        console.log('🎯 РЕЗУЛЬТАТЫ ДЕТАЛЬНОГО АНАЛИЗА:');
        console.log('Совпадений найдено:', analysis.matches.length);
        console.log('Полный анализ:', analysis);
        
        // Показываем результаты в DOM
        let resultsHtml = '<h4>📊 Результаты анализа:</h4>';
        resultsHtml += '<p><strong>DA объявлений:</strong> ' + analysis.da_properties.length + '</p>';
        resultsHtml += '<p><strong>Маркеров на карте:</strong> ' + analysis.markers.length + '</p>';
        resultsHtml += '<p><strong>Точных совпадений:</strong> ' + analysis.matches.length + '</p>';
        
        if (analysis.matches.length > 0) {
            resultsHtml += '<h5>✅ Найденные совпадения:</h5>';
            analysis.matches.forEach(function(match, index) {
                resultsHtml += '<div style="border: 1px solid #ccc; padding: 10px; margin: 5px;">';
                resultsHtml += '<strong>Совпадение #' + (index + 1) + '</strong><br>';
                resultsHtml += 'DA объявление ID: ' + match.da_property.id + '<br>';
                resultsHtml += 'Маркер индекс: ' + match.marker_index + '<br>';
                resultsHtml += 'Атрибут: ' + match.attribute + '<br>';
                resultsHtml += 'Координаты DA: ' + match.coordinates.da.lat + ', ' + match.coordinates.da.lng + '<br>';
                resultsHtml += 'Координаты маркера: ' + match.coordinates.marker.lat + ', ' + match.coordinates.marker.lng + '<br>';
                resultsHtml += '</div>';
            });
        } else {
            resultsHtml += '<h5>❌ Точных совпадений не найдено</h5>';
            resultsHtml += '<p>Возможные причины:</p>';
            resultsHtml += '<ul>';
            resultsHtml += '<li>Координаты хранятся в другом формате</li>';
            resultsHtml += '<li>Используется другой способ связи маркеров с объявлениями</li>';
            resultsHtml += '<li>Координаты не точно совпадают</li>';
            resultsHtml += '</ul>';
            
            // Показываем образцы DA объявлений
            resultsHtml += '<h5>📋 Образцы DA объявлений:</h5>';
            analysis.da_properties.forEach(function(prop, index) {
                if (index < 3) { // Показываем первые 3
                    resultsHtml += '<div style="border: 1px solid #ddd; padding: 5px; margin: 3px;">';
                    resultsHtml += '<strong>ID:</strong> ' + prop.id + '<br>';
                    resultsHtml += '<strong>Название:</strong> ' + prop.title + '<br>';
                    resultsHtml += '<strong>Координаты:</strong> ' + prop.lat + ', ' + prop.lng + '<br>';
                    resultsHtml += '<strong>Адрес:</strong> ' + (prop.address || 'не указан') + '<br>';
                    resultsHtml += '</div>';
                }
            });
        }
        
        jQuery('#debug-results').html(resultsHtml);
        
        // Если найдены совпадения, применяем мигание
        if (analysis.matches.length > 0) {
            analysis.matches.forEach(function(match) {
                jQuery('.mh-map-pin').eq(match.marker_index).addClass('da-marker-blink');
            });
            console.log('✨ Применено мигание к ' + analysis.matches.length + ' маркерам');
        }
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
        animation: da-marker-blink 2s infinite;
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

// AJAX handler для получения детальных DA данных
add_action('wp_ajax_da_debug_get_detailed_data', 'da_debug_get_detailed_data');
add_action('wp_ajax_nopriv_da_debug_get_detailed_data', 'da_debug_get_detailed_data');
function da_debug_get_detailed_data() {
    // Получаем DA объявления
    $da_posts = get_posts(array(
        'post_type' => 'estate',
        'numberposts' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'spetspredlozheniya',
                'field' => 'slug',
                'terms' => 'da'
            )
        )
    ));
    
    $da_properties = array();
    
    foreach ($da_posts as $post) {
        // Получаем все возможные мета-поля с координатами
        $all_meta = get_post_meta($post->ID);
        
        $property = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => substr($post->post_content, 0, 200),
            'meta_fields' => array()
        );
        
        // Собираем все мета-поля
        foreach ($all_meta as $key => $values) {
            if (is_array($values) && count($values) == 1) {
                $property['meta_fields'][$key] = $values[0];
            } else {
                $property['meta_fields'][$key] = $values;
            }
        }
        
        // Пытаемся найти координаты в разных полях
        $possible_lat_fields = ['myhome_lat', '_myhome_lat', 'latitude', '_latitude', 'lat', '_lat'];
        $possible_lng_fields = ['myhome_lng', '_myhome_lng', 'longitude', '_longitude', 'lng', '_lng'];
        
        $lat = null;
        $lng = null;
        
        foreach ($possible_lat_fields as $field) {
            if (isset($property['meta_fields'][$field]) && $property['meta_fields'][$field]) {
                $lat = $property['meta_fields'][$field];
                break;
            }
        }
        
        foreach ($possible_lng_fields as $field) {
            if (isset($property['meta_fields'][$field]) && $property['meta_fields'][$field]) {
                $lng = $property['meta_fields'][$field];
                break;
            }
        }
        
        // Проверяем estate_location и _estate_location
        foreach (['estate_location', '_estate_location'] as $location_field) {
            if (isset($property['meta_fields'][$location_field])) {
                $location_data = $property['meta_fields'][$location_field];
                
                // Если это сериализованные данные
                if (is_string($location_data)) {
                    $unserialized = @unserialize($location_data);
                    if ($unserialized !== false) {
                        if (isset($unserialized['lat'])) $lat = $unserialized['lat'];
                        if (isset($unserialized['lng'])) $lng = $unserialized['lng'];
                        if (isset($unserialized['latitude'])) $lat = $unserialized['latitude'];
                        if (isset($unserialized['longitude'])) $lng = $unserialized['longitude'];
                    }
                    
                    // Или JSON
                    $json_data = @json_decode($location_data, true);
                    if ($json_data !== null) {
                        if (isset($json_data['lat'])) $lat = $json_data['lat'];
                        if (isset($json_data['lng'])) $lng = $json_data['lng'];
                        if (isset($json_data['latitude'])) $lat = $json_data['latitude'];
                        if (isset($json_data['longitude'])) $lng = $json_data['longitude'];
                    }
                    
                    // Или просто координаты через запятую
                    if (preg_match('/(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/', $location_data, $matches)) {
                        $lat = $matches[1];
                        $lng = $matches[2];
                    }
                }
            }
        }
        
        $property['lat'] = $lat;
        $property['lng'] = $lng;
        $property['address'] = isset($property['meta_fields']['myhome_property_address']) 
            ? $property['meta_fields']['myhome_property_address'] : '';
        
        $da_properties[] = $property;
    }
    
    wp_send_json_success(array(
        'da_properties' => $da_properties,
        'count' => count($da_properties)
    ));
}
?>
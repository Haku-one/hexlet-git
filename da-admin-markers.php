<?php
/**
 * DA Markers - АДМИНКА ДЛЯ УПРАВЛЕНИЯ МАРКЕРАМИ
 * Отдельная система управления подсветкой маркеров
 */

// Создаем отдельную таблицу для маркеров
register_activation_hook(__FILE__, 'da_create_markers_table');
function da_create_markers_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'da_markers';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        marker_index int(11) NOT NULL,
        marker_name varchar(255) DEFAULT '',
        is_active tinyint(1) DEFAULT 0,
        lat decimal(10, 8) DEFAULT NULL,
        lng decimal(11, 8) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY marker_index (marker_index)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Добавляем меню в админку
add_action('admin_menu', 'da_markers_admin_menu');
function da_markers_admin_menu() {
    add_menu_page(
        'DA Маркеры',
        'DA Маркеры',
        'manage_options',
        'da-markers',
        'da_markers_admin_page',
        'dashicons-location-alt',
        30
    );
}

// Страница админки
function da_markers_admin_page() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'da_markers';
    
    // Обработка сохранения
    if (isset($_POST['save_markers'])) {
        // Сначала деактивируем все маркеры
        $wpdb->update($table_name, array('is_active' => 0), array());
        
        // Активируем выбранные
        if (isset($_POST['active_markers']) && is_array($_POST['active_markers'])) {
            foreach ($_POST['active_markers'] as $marker_index) {
                $marker_index = intval($marker_index);
                
                // Проверяем, существует ли запись
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE marker_index = %d", 
                    $marker_index
                ));
                
                if ($existing) {
                    // Обновляем существующую запись
                    $wpdb->update(
                        $table_name,
                        array('is_active' => 1),
                        array('marker_index' => $marker_index)
                    );
                } else {
                    // Создаем новую запись
                    $wpdb->insert(
                        $table_name,
                        array(
                            'marker_index' => $marker_index,
                            'marker_name' => 'Маркер #' . $marker_index,
                            'is_active' => 1
                        )
                    );
                }
            }
        }
        
        echo '<div class="notice notice-success"><p>Настройки маркеров сохранены!</p></div>';
    }
    
    // Обработка обновления координат
    if (isset($_POST['update_coordinates'])) {
        if (isset($_POST['marker_coordinates']) && is_array($_POST['marker_coordinates'])) {
            foreach ($_POST['marker_coordinates'] as $marker_index => $coords) {
                if (!empty($coords['lat']) && !empty($coords['lng'])) {
                    $marker_index = intval($marker_index);
                    $lat = floatval($coords['lat']);
                    $lng = floatval($coords['lng']);
                    $name = sanitize_text_field($coords['name']);
                    
                    // Проверяем, существует ли запись
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE marker_index = %d", 
                        $marker_index
                    ));
                    
                    if ($existing) {
                        $wpdb->update(
                            $table_name,
                            array(
                                'lat' => $lat,
                                'lng' => $lng,
                                'marker_name' => $name
                            ),
                            array('marker_index' => $marker_index)
                        );
                    } else {
                        $wpdb->insert(
                            $table_name,
                            array(
                                'marker_index' => $marker_index,
                                'marker_name' => $name,
                                'lat' => $lat,
                                'lng' => $lng,
                                'is_active' => 0
                            )
                        );
                    }
                }
            }
        }
        
        echo '<div class="notice notice-success"><p>Координаты маркеров обновлены!</p></div>';
    }
    
    // Получаем активные маркеры
    $active_markers = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY marker_index"
    );
    
    // Получаем все маркеры
    $all_markers = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY marker_index"
    );
    
    ?>
    <div class="wrap">
        <h1>🎯 Управление DA Маркерами</h1>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
            <h3>📋 Инструкция:</h3>
            <ol>
                <li><strong>Откройте страницу с картой</strong> в новой вкладке</li>
                <li><strong>Откройте консоль браузера</strong> (F12 → Console)</li>
                <li><strong>Найдите сообщение</strong> "📍 Маркеров на карте: X"</li>
                <li><strong>Выберите номера маркеров</strong> которые должны мигать (начинается с 0)</li>
                <li><strong>Отметьте галочки</strong> ниже для нужных маркеров</li>
                <li><strong>Сохраните настройки</strong></li>
            </ol>
        </div>

        <!-- Быстрые настройки -->
        <div class="postbox" style="margin: 20px 0;">
            <div class="postbox-header">
                <h2>⚡ Быстрые настройки</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <form method="post">
                    <h3>Выберите маркеры для подсветки:</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin: 20px 0;">
                        <?php for ($i = 0; $i < 50; $i++): ?>
                            <?php 
                            $is_active = false;
                            foreach ($active_markers as $marker) {
                                if ($marker->marker_index == $i) {
                                    $is_active = true;
                                    break;
                                }
                            }
                            ?>
                            <label style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; <?php echo $is_active ? 'background: #e7f3ff; border-color: #0073aa;' : ''; ?>">
                                <input type="checkbox" name="active_markers[]" value="<?php echo $i; ?>" <?php checked($is_active); ?>>
                                Маркер #<?php echo $i; ?>
                            </label>
                        <?php endfor; ?>
                    </div>
                    
                    <p>
                        <input type="submit" name="save_markers" class="button-primary" value="💾 Сохранить настройки маркеров">
                        <button type="button" onclick="selectNone()" class="button">❌ Снять все</button>
                        <button type="button" onclick="selectFirst5()" class="button">🎯 Первые 5</button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Текущие активные маркеры -->
        <?php if (!empty($active_markers)): ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2>🔴 Активные маркеры (<?php echo count($active_markers); ?>)</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Название</th>
                            <th>Координаты</th>
                            <th>Статус</th>
                            <th>Обновлено</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_markers as $marker): ?>
                        <tr>
                            <td><strong>#<?php echo $marker->marker_index; ?></strong></td>
                            <td><?php echo esc_html($marker->marker_name); ?></td>
                            <td>
                                <?php if ($marker->lat && $marker->lng): ?>
                                    <?php echo $marker->lat; ?>, <?php echo $marker->lng; ?>
                                <?php else: ?>
                                    <span style="color: #999;">Не указаны</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="color: green;">🟢 Активен</span></td>
                            <td><?php echo $marker->updated_at; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Управление координатами -->
        <div class="postbox">
            <div class="postbox-header">
                <h2>📍 Управление координатами маркеров</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <p><em>Опционально: можете указать координаты для более точного поиска маркеров</em></p>
                
                <form method="post">
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th>Маркер</th>
                                <th>Название</th>
                                <th>Широта</th>
                                <th>Долгота</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 10; $i++): ?>
                                <?php 
                                $existing_marker = null;
                                foreach ($all_markers as $marker) {
                                    if ($marker->marker_index == $i) {
                                        $existing_marker = $marker;
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><strong>Маркер #<?php echo $i; ?></strong></td>
                                    <td>
                                        <input type="text" 
                                               name="marker_coordinates[<?php echo $i; ?>][name]" 
                                               value="<?php echo $existing_marker ? esc_attr($existing_marker->marker_name) : 'Маркер #' . $i; ?>"
                                               placeholder="Название маркера"
                                               style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="marker_coordinates[<?php echo $i; ?>][lat]" 
                                               value="<?php echo $existing_marker ? $existing_marker->lat : ''; ?>"
                                               step="0.0000001" 
                                               placeholder="55.7558"
                                               style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="marker_coordinates[<?php echo $i; ?>][lng]" 
                                               value="<?php echo $existing_marker ? $existing_marker->lng : ''; ?>"
                                               step="0.0000001" 
                                               placeholder="37.6176"
                                               style="width: 100%;">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    
                    <p>
                        <input type="submit" name="update_coordinates" class="button-secondary" value="📍 Обновить координаты">
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="postbox">
            <div class="postbox-header">
                <h2>📊 Статистика</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php
                $total_markers = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $active_count = count($active_markers);
                $with_coords = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE lat IS NOT NULL AND lng IS NOT NULL");
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: #f1f1f1; border-radius: 8px;">
                        <h3 style="margin: 0; color: #0073aa;"><?php echo $active_count; ?></h3>
                        <p style="margin: 5px 0 0 0;">Активные маркеры</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f1f1f1; border-radius: 8px;">
                        <h3 style="margin: 0; color: #135e96;"><?php echo $total_markers; ?></h3>
                        <p style="margin: 5px 0 0 0;">Всего в базе</p>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f1f1f1; border-radius: 8px;">
                        <h3 style="margin: 0; color: #d63638;"><?php echo $with_coords; ?></h3>
                        <p style="margin: 5px 0 0 0;">С координатами</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function selectNone() {
        document.querySelectorAll('input[name="active_markers[]"]').forEach(cb => cb.checked = false);
    }
    
    function selectFirst5() {
        selectNone();
        for (let i = 0; i < 5; i++) {
            const cb = document.querySelector('input[name="active_markers[]"][value="' + i + '"]');
            if (cb) cb.checked = true;
        }
    }
    </script>
    <?php
}

// AJAX для получения активных маркеров
add_action('wp_ajax_get_da_admin_markers', 'ajax_get_da_admin_markers');
add_action('wp_ajax_nopriv_get_da_admin_markers', 'ajax_get_da_admin_markers');
function ajax_get_da_admin_markers() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'da_markers';
    
    $active_markers = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY marker_index"
    );
    
    $markers_data = array();
    foreach ($active_markers as $marker) {
        $markers_data[] = array(
            'index' => intval($marker->marker_index),
            'name' => $marker->marker_name,
            'lat' => $marker->lat ? floatval($marker->lat) : null,
            'lng' => $marker->lng ? floatval($marker->lng) : null
        );
    }
    
    wp_send_json_success(array(
        'markers' => $markers_data,
        'count' => count($markers_data),
        'timestamp' => current_time('timestamp')
    ));
}

// CSS для мигания
add_action('wp_head', 'da_admin_markers_css');
function da_admin_markers_css() {
    ?>
    <style>
    @keyframes da-admin-blink {
        0%, 100% { 
            filter: drop-shadow(0 0 10px #ff0066);
            transform: scale(1);
        }
        50% { 
            filter: drop-shadow(0 0 20px #ff0066);
            transform: scale(1.1);
        }
    }

    .mh-map-pin.da-admin-active {
        animation: da-admin-blink 1.5s infinite;
        z-index: 9999 !important;
    }

    .mh-map-pin.da-admin-active i {
        color: #ff0066 !important;
    }

    /* Демо режим */
    .mh-map-pin.da-admin-demo {
        animation: da-admin-blink 1.5s infinite;
    }

    .mh-map-pin.da-admin-demo i {
        color: #00ff66 !important;
    }
    </style>
    <?php
}

// JavaScript для активации маркеров
add_action('wp_footer', 'da_admin_markers_script');
function da_admin_markers_script() {
    if (!is_page() && !is_front_page()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('🎯 DA Админ Маркеры - запущено');
        
        let activeMarkers = [];
        let processAttempts = 0;
        const maxAttempts = 5;
        
        // Получаем активные маркеры из админки
        function fetchActiveMarkers() {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_da_admin_markers'
                }
            });
        }
        
        // Активация маркеров по индексам
        function activateMarkersByIndex() {
            processAttempts++;
            console.log('🔄 Попытка активации #' + processAttempts);
            
            const $markers = $('.mh-map-pin');
            if ($markers.length === 0) {
                console.log('⏳ Маркеры не найдены...');
                if (processAttempts < maxAttempts) {
                    setTimeout(activateMarkersByIndex, 2000);
                }
                return;
            }
            
            console.log('📍 Маркеров на карте:', $markers.length);
            console.log('🎯 Активных маркеров в админке:', activeMarkers.length);
            
            // Убираем предыдущие классы
            $markers.removeClass('da-admin-active da-admin-demo');
            
            let activatedCount = 0;
            
            // Активируем маркеры по индексам
            activeMarkers.forEach(function(markerData) {
                const index = markerData.index;
                const $targetMarker = $markers.eq(index);
                
                if ($targetMarker.length) {
                    $targetMarker.addClass('da-admin-active');
                    activatedCount++;
                    console.log('✅ Активирован маркер #' + index + ' (' + markerData.name + ')');
                } else {
                    console.log('⚠️ Маркер #' + index + ' не найден (всего маркеров: ' + $markers.length + ')');
                }
            });
            
            // Если координаты указаны, попробуем найти по координатам
            if (activatedCount === 0 && activeMarkers.length > 0) {
                console.log('🔍 Поиск по координатам...');
                
                activeMarkers.forEach(function(markerData) {
                    if (markerData.lat && markerData.lng) {
                        // Ищем маркер по координатам в HTML
                        $markers.each(function(index) {
                            const $marker = $(this);
                            let $parent = $marker;
                            
                            for (let i = 0; i < 5; i++) {
                                $parent = $parent.parent();
                                if ($parent.length === 0) break;
                                
                                const html = $parent.html() || '';
                                const hasLat = html.includes(markerData.lat.toString());
                                const hasLng = html.includes(markerData.lng.toString());
                                
                                if (hasLat && hasLng) {
                                    $marker.addClass('da-admin-active');
                                    activatedCount++;
                                    console.log('✅ Найден по координатам: ' + markerData.name + ' (индекс ' + index + ')');
                                    return false; // break
                                }
                            }
                        });
                    }
                });
            }
            
            // Демо режим если ничего не активировано
            if (activatedCount === 0) {
                if (activeMarkers.length > 0) {
                    console.log('🟡 Демо режим - маркеры из админки не найдены');
                    console.log('💡 Проверьте номера маркеров в админке (Начинаются с 0)');
                } else {
                    console.log('🟢 Демо режим - нет активных маркеров в админке');
                }
                $markers.slice(0, 1).addClass('da-admin-demo');
                activatedCount = 1;
            }
            
            // Финальная статистика
            setTimeout(() => {
                const finalActive = $('.mh-map-pin.da-admin-active').length;
                const finalDemo = $('.mh-map-pin.da-admin-demo').length;
                
                console.log('🏁 === ИТОГОВАЯ СТАТИСТИКА ===');
                console.log('🔴 Активированных маркеров:', finalActive);
                console.log('🟢 Демо маркеров:', finalDemo);
                console.log('📍 Всего маркеров на карте:', $markers.length);
                console.log('⚙️ Настроек в админке:', activeMarkers.length);
                
                if (finalActive > 0) {
                    console.log('🎉 УСПЕХ! Маркеры активированы через админку!');
                    console.log('⚙️ Управление: WordPress Админка → DA Маркеры');
                }
            }, 500);
        }
        
        // Запуск
        fetchActiveMarkers().done(function(response) {
            if (response.success && response.data.markers) {
                activeMarkers = response.data.markers;
                console.log('📡 Получены настройки маркеров:', activeMarkers);
                
                // Запускаем активацию с задержкой
                setTimeout(activateMarkersByIndex, 3000);
                
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
                        
                        if (hasNewMarkers && activeMarkers.length > 0) {
                            console.log('🔄 Новые маркеры обнаружены, повторная активация...');
                            setTimeout(activateMarkersByIndex, 1500);
                        }
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
            } else {
                console.log('⚠️ Нет активных маркеров в админке');
                console.log('⚙️ Перейдите в WordPress Админка → DA Маркеры для настройки');
                
                setTimeout(() => {
                    $('.mh-map-pin').slice(0, 1).addClass('da-admin-demo');
                }, 3000);
            }
        }).fail(function() {
            console.log('❌ Ошибка загрузки настроек маркеров');
        });
    });
    </script>
    <?php
}
?>
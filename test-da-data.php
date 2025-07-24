<?php
/**
 * ТЕСТОВЫЙ ФАЙЛ ДЛЯ ПРОВЕРКИ DA ДАННЫХ
 */

// Проверяем существование поста типа estate
add_action('wp_footer', function() {
    ?>
    <script>
    console.log('=== ТЕСТ DA ДАННЫХ ===');
    
    // Проверяем существующие посты estate
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'test_estate_posts'
        },
        success: function(response) {
            console.log('🏠 Estate посты:', response);
        }
    });
    
    // Проверяем таксономии
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'test_taxonomies'
        },
        success: function(response) {
            console.log('📂 Таксономии:', response);
        }
    });
    </script>
    <?php
});

// AJAX обработчики для тестирования
add_action('wp_ajax_test_estate_posts', 'test_estate_posts');
add_action('wp_ajax_nopriv_test_estate_posts', 'test_estate_posts');

function test_estate_posts() {
    // Проверяем все estate посты
    $estate_posts = get_posts(array(
        'post_type' => 'estate',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => array()
    ));

    $result = array(
        'total_found' => count($estate_posts),
        'posts' => array()
    );

    foreach ($estate_posts as $post) {
        $post_terms = wp_get_post_terms($post->ID, get_object_taxonomies('estate'));
        
        $result['posts'][] = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'terms' => $post_terms
        );
    }

    wp_send_json($result);
}

add_action('wp_ajax_test_taxonomies', 'test_taxonomies');
add_action('wp_ajax_nopriv_test_taxonomies', 'test_taxonomies');

function test_taxonomies() {
    // Получаем все таксономии для estate
    $taxonomies = get_object_taxonomies('estate', 'objects');
    
    $result = array(
        'estate_taxonomies' => array(),
        'spetspredlozheniya_exists' => taxonomy_exists('spetspredlozheniya'),
        'all_post_types' => get_post_types(array('public' => true))
    );

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy->name,
            'hide_empty' => false
        ));
        
        $result['estate_taxonomies'][$taxonomy->name] = array(
            'label' => $taxonomy->label,
            'terms' => $terms
        );
    }

    wp_send_json($result);
}

// Альтернативный способ получения DA объявлений
add_action('wp_footer', function() {
    ?>
    <script>
    // Проверяем альтернативные способы получения DA данных
    console.log('🔍 Проверяем альтернативные источники DA данных...');
    
    // Проверяем глобальные переменные
    if (typeof window.MyHomeMapData !== 'undefined') {
        console.log('📊 MyHomeMapData:', window.MyHomeMapData);
    }
    
    if (typeof window.MyHome !== 'undefined') {
        console.log('🏠 MyHome:', window.MyHome);
    }
    
    // Проверяем мета-поля
    if (typeof window.myhome_localized !== 'undefined') {
        console.log('🌍 myhome_localized:', window.myhome_localized);
    }
    
    // Поиск DA в контенте
    setTimeout(function() {
        var daElements = jQuery('[data-da], [class*="da"], [id*="da"]');
        if (daElements.length > 0) {
            console.log('🔍 Найдены элементы с DA:', daElements);
        }
        
        // Проверяем маркеры
        var markers = jQuery('.mh-map-pin');
        console.log('📍 Найдено маркеров на странице:', markers.length);
        
        markers.each(function(index) {
            var $marker = jQuery(this);
            var attrs = {};
            
            // Собираем все атрибуты
            jQuery.each(this.attributes, function() {
                if(this.specified) {
                    attrs[this.name] = this.value;
                }
            });
            
            console.log('📍 Маркер #' + index + ':', {
                element: this,
                attributes: attrs,
                text: $marker.text(),
                html: $marker.html()
            });
        });
    }, 2000);
    </script>
    <?php
});
?>
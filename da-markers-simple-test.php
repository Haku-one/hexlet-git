<?php
/**
 * =====================================
 * DA МАРКЕРЫ - ПРОСТОЙ ТЕСТ
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

// Простой JavaScript для тестирования
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            console.log('🎯 DA Маркеры - простой тест загружен');
            
            setTimeout(function() {
                console.log('🚨 ТЕСТИРОВАНИЕ: Применяем стили ко всем маркерам');
                
                var $allMarkers = $('.mh-map-pin');
                console.log('Найдено маркеров:', $allMarkers.length);
                
                $allMarkers.each(function(index) {
                    $(this).addClass('da-marker-blink');
                    console.log('✨ Применен стиль к маркеру #' + index);
                });
                
                setTimeout(function() {
                    var $blinkingMarkers = $('.da-marker-blink');
                    console.log('🔍 Маркеров с анимацией:', $blinkingMarkers.length);
                    
                    if ($blinkingMarkers.length > 0) {
                        console.log('✅ CSS анимация работает!');
                    } else {
                        console.log('❌ CSS анимация не работает!');
                    }
                }, 1000);
                
            }, 3000);
        });
    })(jQuery);
    </script>
    <?php
});
?>
<?php
// Скрипт для проверки версии Bootstrap и jQuery
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bootstrap Version Check</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Проверка версии Bootstrap и jQuery</h1>
        <div id="version-info" class="alert alert-info mt-3">
            Загрузка информации...
        </div>
        
        <hr>
        
        <h3>Проверка модального окна</h3>
        <button id="modalTest" class="btn btn-primary">Открыть модальное окно</button>
        
        <div id="alert-area" class="mt-3"></div>
    </div>
    
    <!-- Тестовое модальное окно -->
    <div class="modal" id="testModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Тестовое модальное окно</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Это тестовое модальное окно для проверки совместимости.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                    <button type="button" class="btn btn-primary" id="testModalOk">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключаем JavaScript библиотеки -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var info = '<strong>jQuery:</strong> ' + $.fn.jquery + '<br>';
            
            // Проверка версии Bootstrap
            if ($.fn.tooltip && $.fn.tooltip.Constructor && $.fn.tooltip.Constructor.VERSION) {
                info += '<strong>Bootstrap:</strong> ' + $.fn.tooltip.Constructor.VERSION + '<br>';
            } else if ($.fn.modal && $.fn.modal.Constructor && $.fn.modal.Constructor.VERSION) {
                info += '<strong>Bootstrap:</strong> ' + $.fn.modal.Constructor.VERSION + '<br>';
            } else {
                info += '<strong>Bootstrap:</strong> Версия не определена<br>';
            }
            
            // Проверка методов Bootstrap
            info += '<strong>Доступные методы:</strong><br>';
            info += '- modal: ' + (typeof $.fn.modal === 'function' ? 'Да' : 'Нет') + '<br>';
            info += '- tooltip: ' + (typeof $.fn.tooltip === 'function' ? 'Да' : 'Нет') + '<br>';
            info += '- popover: ' + (typeof $.fn.popover === 'function' ? 'Да' : 'Нет') + '<br>';
            
            // Проверяем наличие разных способов активации модального окна
            info += '<strong>Методы модальных окон:</strong><br>';
            try {
                if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                    info += '- bootstrap.Modal: Да (Bootstrap 5+)<br>';
                } else {
                    info += '- bootstrap.Modal: Нет<br>';
                }
            } catch (e) {
                info += '- bootstrap.Modal: Ошибка (' + e.message + ')<br>';
            }
            
            // Выводим информацию
            $('#version-info').html(info);
            
            // Тестирование модального окна
            $('#modalTest').on('click', function() {
                try {
                    $('#testModal').modal('show');
                    showAlert('success', 'Модальное окно открыто методом modal("show")');
                } catch (e) {
                    showAlert('danger', 'Ошибка при открытии модального окна: ' + e.message);
                    
                    // Пробуем альтернативный способ
                    try {
                        var modal = new bootstrap.Modal(document.getElementById('testModal'));
                        modal.show();
                        showAlert('success', 'Модальное окно открыто через конструктор bootstrap.Modal');
                    } catch (e2) {
                        showAlert('danger', 'Ошибка при использовании bootstrap.Modal: ' + e2.message);
                    }
                }
            });
            
            // Кнопка OK в модальном окне
            $('#testModalOk').on('click', function() {
                $('#testModal').modal('hide');
                showAlert('success', 'Кнопка OK в модальном окне нажата');
            });
            
            // Функция для показа уведомлений
            function showAlert(type, message) {
                var alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show">' +
                              message +
                              '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                              '<span aria-hidden="true">&times;</span></button></div>');
                
                $('#alert-area').append(alert);
                
                // Автоматически скрываем через 5 секунд
                setTimeout(function() {
                    alert.alert('close');
                }, 5000);
            }
        });
    </script>
</body>
</html> 
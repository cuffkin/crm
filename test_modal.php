<?php
// Тестовый скрипт для проверки работы модального окна
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест модального окна</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
        }
        .btn {
            margin: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Тестирование модального окна</h1>
        
        <button id="testStandardBtn" class="btn btn-primary">
            Проверить стандартное Bootstrap окно
        </button>
        
        <button id="testCustomBtn" class="btn btn-success">
            Проверить наше пользовательское окно
        </button>
        
        <hr>
        
        <div id="result" class="alert alert-info mt-3" style="display:none;">
            Здесь будет отображаться результат
        </div>
    </div>
    
    <!-- Стандартное Bootstrap модальное окно для теста -->
    <div class="modal fade" id="standardModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Стандартное модальное окно</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Это стандартное Bootstrap модальное окно для проверки.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                    <button type="button" class="btn btn-primary" id="standardModalConfirm">Подтвердить</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery first, then Bootstrap JS -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    
    <!-- Подключаем наш файл с функцией модального окна -->
    <script src="js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            // Проверка версии Bootstrap и jQuery
            console.log('jQuery version:', $.fn.jquery);
            console.log('Bootstrap version:', (typeof $.fn.modal.Constructor.VERSION !== 'undefined') ? 
                        $.fn.modal.Constructor.VERSION : 'unknown');
            
            // Проверка стандартного Bootstrap модального окна
            $('#testStandardBtn').on('click', function() {
                console.log('Открытие стандартного модального окна');
                $('#standardModal').modal('show');
            });
            
            $('#standardModalConfirm').on('click', function() {
                $('#standardModal').modal('hide');
                showResult('Стандартное модальное окно: нажата кнопка "Подтвердить"');
            });
            
            // Проверка нашего пользовательского модального окна
            $('#testCustomBtn').on('click', function() {
                console.log('Запуск пользовательского модального окна');
                
                showConfirmModal(
                    'Пользовательское окно', 
                    'Проверка работы модального окна подтверждения. Вы хотите продолжить?',
                    function() {
                        // Колбэк для кнопки "Подтвердить"
                        showResult('Пользовательское модальное окно: нажата кнопка "Подтвердить"');
                    },
                    function() {
                        // Колбэк для кнопки "Отмена"
                        showResult('Пользовательское модальное окно: нажата кнопка "Отмена"');
                    }
                );
            });
            
            // Функция для отображения результата
            function showResult(message) {
                $('#result').text(message).show();
                setTimeout(function() {
                    $('#result').fadeOut(1000);
                }, 3000);
            }
        });
    </script>
</body>
</html> 
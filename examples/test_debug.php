<?php
/**
 * Тестовый файл для демонстрации дебаг панели
 */

require_once 'autoload.php';

use Imy\Core\Core;
use Imy\Core\Debug;
use Imy\Core\DBSelect;

// Инициализируем приложение
Core::init('config');

// Добавляем некоторые логи
Debug::log('Начало выполнения тестового скрипта', 'info');
Debug::addHeader('X-Test-Header', 'Debug Panel Test');

// Добавляем тестовые данные для демонстрации
$_GET['test_param'] = 'test_value';
$_POST['form_data'] = 'submitted';
$_COOKIE['session_id'] = 'abc123';

// Симулируем работу с базой данных
try {
    // Пример SELECT запроса
    $users = DBSelect::factory('users')
        ->select('id', 'name', 'email')
        ->where('active', 1)
        ->limit(5);
    
    // Симулируем выполнение запроса (в реальности здесь будет fetchAssocAll())
    Debug::log('Выполнен SELECT запрос для получения пользователей', 'info');
    
    // Пример COUNT запроса
    $count = DBSelect::factory('users')
        ->count('id');
    
    Debug::log('Выполнен COUNT запрос', 'info');
    
    // Пример JOIN запроса
    $posts = DBSelect::factory('posts')
        ->select('posts.title', 'posts.content', 'users.name as author')
        ->join('users', 'INNER')
        ->on('posts.user_id', 'users.id')
        ->where('posts.published', 1)
        ->limit(3);
    
    Debug::log('Выполнен JOIN запрос для получения постов', 'info');
    
    // Добавляем предупреждение
    Debug::log('Это тестовое предупреждение', 'warning');
    
} catch (Exception $e) {
    Debug::log('Ошибка при работе с БД: ' . $e->getMessage(), 'error');
}

Debug::log('Тестовый скрипт завершен', 'info');

// Выводим HTML страницу
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест дебаг панели IMY Core</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007cba;
            padding-bottom: 10px;
        }
        .feature {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #007cba;
            border-radius: 4px;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Тест дебаг панели IMY Core</h1>
        
        <p>Эта страница демонстрирует работу дебаг панели, аналогичной Laravel Debugbar.</p>
        
        <div class="note">
            <strong>📌 Внимание:</strong> В левом нижнем углу должна появиться круглая иконка 🔧. 
            Кликните по ней, чтобы открыть дебаг панель с табами. Если иконка не видна, проверьте настройки в файле конфигурации.
        </div>
        
        <h2>🚀 Возможности дебаг панели:</h2>
        
        <div class="feature">
            <h3>📊 Таб "Обзор"</h3>
            <ul>
                <li>Время выполнения скрипта</li>
                <li>Использование памяти (текущее и пиковое)</li>
                <li>Количество SQL запросов</li>
                <li>Общее время выполнения SQL запросов</li>
                <li>Количество соединений с БД</li>
            </ul>
        </div>
        
        <div class="feature">
            <h3>🗄️ Таб "SQL"</h3>
            <ul>
                <li>Полный текст каждого запроса</li>
                <li>Время выполнения каждого запроса с цветовой индикацией</li>
                <li>Имя соединения с БД</li>
                <li>Номер запроса в последовательности</li>
            </ul>
        </div>
        
        <div class="feature">
            <h3>🌐 Таб "Переменные"</h3>
            <ul>
                <li><strong>GET параметры</strong> - все $_GET данные</li>
                <li><strong>POST параметры</strong> - все $_POST данные</li>
                <li><strong>COOKIE</strong> - все $_COOKIE данные</li>
                <li><strong>SERVER переменные</strong> - важные $_SERVER данные</li>
                <li><strong>HTTP заголовки</strong> - кастомные заголовки</li>
            </ul>
        </div>
        
        <div class="feature">
            <h3>📝 Таб "Логи"</h3>
            <ul>
                <li>Ручное логирование через <code>Debug::log()</code></li>
                <li>Различные уровни логирования (info, warning, error)</li>
                <li>Временные метки для каждого лога</li>
                <li>Цветовая индикация по типам сообщений</li>
            </ul>
        </div>
        
        <div class="feature">
            <h3>⚡ Таб "Производительность"</h3>
            <ul>
                <li><strong>PHP информация</strong> - версия, SAPI, лимиты</li>
                <li><strong>Сервер</strong> - ПО, IP, порт, HTTPS</li>
                <li><strong>Статистика</strong> - файлы, классы, функции, константы</li>
                <li><strong>Системные настройки</strong> - часовой пояс, лимиты</li>
            </ul>
        </div>
        
        <div class="feature">
            <h3>📁 Таб "Файлы"</h3>
            <ul>
                <li><strong>Подключенные файлы</strong> - все include/require</li>
                <li><strong>Размеры файлов</strong> - размер каждого файла</li>
                <li><strong>Даты изменения</strong> - когда файл был изменен</li>
                <li><strong>Общая статистика</strong> - количество и общий размер</li>
            </ul>
        </div>
        
        <div class="feature">
            <h3>⚠️ Таб "Ошибки"</h3>
            <ul>
                <li><strong>PHP ошибки</strong> - последние ошибки выполнения</li>
                <li><strong>Информация об ошибках</strong> - файл, строка, тип</li>
                <li><strong>Отслеживание проблем</strong> - для быстрой диагностики</li>
            </ul>
        </div>
        
        <h2>⚙️ Настройка</h2>
        
        <p>Для включения дебаг панели добавьте в конфигурацию:</p>
        
        <pre><code>'debug' => [
    'enabled' => true,  // включить/выключить дебаг панель
]</code></pre>
        
        <h2>💡 Использование в коде</h2>
        
        <pre><code>use Imy\Core\Debug;

// Логирование сообщений
Debug::log('Информационное сообщение', 'info');
Debug::log('Предупреждение', 'warning');
Debug::log('Ошибка', 'error');

// Добавление HTTP заголовков
Debug::addHeader('Custom-Header', 'Value');</code></pre>
        
        <div class="note">
            <strong>✨ Автоматическое логирование:</strong> Все SQL запросы и соединения с БД 
            логируются автоматически без дополнительного кода.
        </div>
    </div>
</body>
</html>

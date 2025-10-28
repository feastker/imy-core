<?php
/**
 * Тестовый файл для демонстрации поддержки DML запросов (INSERT, UPDATE, DELETE)
 */

require_once 'autoload.php';

use Imy\Core\Debug;
use Imy\Core\IndexAnalyzer;

// Включаем дебаг панель
Debug::init();

// Добавляем некоторые логи
Debug::log('Начало тестирования поддержки DML запросов', 'info');

// Тестовые DML запросы для демонстрации новых возможностей
$dml_queries = [
    // INSERT запросы
    [
        'sql' => "INSERT INTO users (name, email, active, created_at) VALUES ('John Doe', 'john@example.com', 1, NOW())",
        'type' => 'INSERT',
        'description' => 'Простой INSERT с основными колонками'
    ],
    [
        'sql' => "INSERT INTO posts (user_id, title, content, published, created_at) VALUES (1, 'Test Post', 'Content here', 1, NOW())",
        'type' => 'INSERT',
        'description' => 'INSERT с внешним ключом и булевыми полями'
    ],
    [
        'sql' => "INSERT INTO users (name, email, active) SELECT name, email, 1 FROM temp_users WHERE verified = 1",
        'type' => 'INSERT',
        'description' => 'INSERT ... SELECT с подзапросом'
    ],
    
    // UPDATE запросы
    [
        'sql' => "UPDATE users SET active = 0 WHERE last_login < '2023-01-01'",
        'type' => 'UPDATE',
        'description' => 'UPDATE с WHERE условием по дате'
    ],
    [
        'sql' => "UPDATE posts SET published = 1, updated_at = NOW() WHERE user_id = 1 AND created_at > '2023-01-01'",
        'type' => 'UPDATE',
        'description' => 'UPDATE с множественными SET и сложным WHERE'
    ],
    [
        'sql' => "UPDATE users u JOIN profiles p ON u.id = p.user_id SET u.last_login = NOW() WHERE p.status = 'active'",
        'type' => 'UPDATE',
        'description' => 'UPDATE с JOIN'
    ],
    [
        'sql' => "UPDATE posts SET views = views + 1 WHERE id IN (1, 2, 3, 4, 5) ORDER BY created_at DESC LIMIT 10",
        'type' => 'UPDATE',
        'description' => 'UPDATE с IN условием и ORDER BY'
    ],
    
    // DELETE запросы
    [
        'sql' => "DELETE FROM users WHERE active = 0 AND last_login < '2022-01-01'",
        'type' => 'DELETE',
        'description' => 'DELETE с множественными WHERE условиями'
    ],
    [
        'sql' => "DELETE FROM posts WHERE user_id NOT IN (SELECT id FROM users WHERE active = 1)",
        'type' => 'DELETE',
        'description' => 'DELETE с подзапросом в WHERE'
    ],
    [
        'sql' => "DELETE p FROM posts p JOIN users u ON p.user_id = u.id WHERE u.status = 'banned'",
        'type' => 'DELETE',
        'description' => 'DELETE с JOIN'
    ],
    [
        'sql' => "DELETE FROM comments WHERE post_id IN (1, 2, 3) AND created_at < '2023-01-01' ORDER BY created_at ASC LIMIT 100",
        'type' => 'DELETE',
        'description' => 'DELETE с IN, датой и LIMIT'
    ],
    
    // Смешанные запросы
    [
        'sql' => "INSERT INTO user_stats (user_id, post_count, comment_count) SELECT u.id, COUNT(p.id), COUNT(c.id) FROM users u LEFT JOIN posts p ON u.id = p.user_id LEFT JOIN comments c ON u.id = c.user_id GROUP BY u.id",
        'type' => 'INSERT',
        'description' => 'Сложный INSERT с агрегацией и JOIN'
    ],
    [
        'sql' => "UPDATE posts p SET featured = 1 WHERE p.id IN (SELECT post_id FROM (SELECT post_id, COUNT(*) as comment_count FROM comments GROUP BY post_id HAVING comment_count > 10) as popular_posts)",
        'type' => 'UPDATE',
        'description' => 'UPDATE с подзапросом и агрегацией'
    ]
];

// Симулируем выполнение DML запросов
foreach ($dml_queries as $i => $query_data) {
    // Симулируем время выполнения
    $start_time = microtime(true);
    usleep(rand(500, 1500)); // Симулируем задержку
    $execution_time = microtime(true) - $start_time;
    
    // Логируем запрос через Debug (это автоматически вызовет анализ индексов)
    Debug::logQuery($query_data['sql'], $execution_time, 'default');
    
    Debug::log("Выполнен {$query_data['type']} запрос #" . ($i + 1) . ": {$query_data['description']}", 'info');
}

// Добавляем дополнительную информацию
Debug::log('Тестирование DML запросов завершено', 'info');
Debug::addHeader('X-Test-DML-Support', 'Completed');

// Получаем статистику анализа
$index_stats = Debug::getIndexStats();
$missing_recommendations = Debug::getIndexRecommendations();
$all_recommendations = Debug::getAllIndexRecommendations();

echo "<h1>Тест поддержки DML запросов (INSERT, UPDATE, DELETE)</h1>";
echo "<p>Проанализировано запросов: " . $index_stats['analyzed_queries'] . "</p>";
echo "<p>Всего рекомендаций: " . $index_stats['total_recommendations'] . "</p>";
echo "<p>Недостающие индексы: " . $index_stats['missing_recommendations'] . "</p>";
echo "<p>Существующие индексы: " . $index_stats['existing_indexes_count'] . "</p>";
echo "<p>Высокий приоритет (недостающие): " . $index_stats['high_priority_missing'] . "</p>";
echo "<p>Средний приоритет (недостающие): " . $index_stats['medium_priority_missing'] . "</p>";

if (!empty($missing_recommendations)) {
    echo "<h2>Рекомендации по индексам для DML запросов:</h2>";
    
    // Группируем по типу запроса
    $grouped_recommendations = [];
    foreach ($missing_recommendations as $recommendation) {
        $reason = $recommendation['reason'];
        $type = 'SELECT';
        
        if (strpos($reason, 'INSERT') !== false) {
            $type = 'INSERT';
        } elseif (strpos($reason, 'UPDATE') !== false) {
            $type = 'UPDATE';
        } elseif (strpos($reason, 'DELETE') !== false) {
            $type = 'DELETE';
        }
        
        $grouped_recommendations[$type][] = $recommendation;
    }
    
    foreach ($grouped_recommendations as $query_type => $recommendations) {
        $type_icon = $query_type === 'INSERT' ? '➕' : ($query_type === 'UPDATE' ? '✏️' : ($query_type === 'DELETE' ? '🗑️' : '🔍'));
        
        echo "<h3>{$type_icon} {$query_type} запросы</h3>";
        
        foreach ($recommendations as $recommendation) {
            $priority_icon = $recommendation['priority'] === 'high' ? '🔴' : ($recommendation['priority'] === 'medium' ? '🟡' : '🟢');
            
            echo "<div style='border: 1px solid #ff6b6b; margin: 10px 0; padding: 10px; background: #ffe6e6;'>";
            echo "<h4>" . $priority_icon . " " . $recommendation['table'] . " (" . $recommendation['priority'] . " приоритет)</h4>";
            echo "<p><strong>Колонки:</strong> " . $recommendation['columns'] . "</p>";
            echo "<p><strong>Тип:</strong> " . ($recommendation['type'] === 'composite' ? 'Составной' : 'Одиночный') . "</p>";
            echo "<p><strong>Причина:</strong> " . $recommendation['reason'] . "</p>";
            echo "<p><strong>Использований:</strong> " . $recommendation['usage_count'] . "</p>";
            echo "<p><strong>SQL:</strong> <code>" . $recommendation['sql'] . "</code></p>";
            echo "</div>";
        }
    }
} else {
    echo "<p>Рекомендации по индексам не найдены.</p>";
}

echo "<h2>Новые возможности для DML запросов:</h2>";
echo "<ul>";
echo "<li>✅ <strong>INSERT запросы</strong> - анализ колонок для проверки уникальности и внешних ключей</li>";
echo "<li>✅ <strong>UPDATE запросы</strong> - анализ WHERE условий (высокий приоритет) и SET колонок (низкий приоритет)</li>";
echo "<li>✅ <strong>DELETE запросы</strong> - анализ WHERE условий для быстрого поиска записей</li>";
echo "<li>✅ <strong>INSERT ... SELECT</strong> - анализ подзапросов в INSERT</li>";
echo "<li>✅ <strong>UPDATE с JOIN</strong> - анализ JOIN условий в UPDATE</li>";
echo "<li>✅ <strong>DELETE с JOIN</strong> - анализ JOIN условий в DELETE</li>";
echo "<li>✅ <strong>Приоритизация</strong> - WHERE условия в UPDATE/DELETE имеют высокий приоритет</li>";
echo "<li>✅ <strong>Специфичные причины</strong> - детальные объяснения для каждого типа DML запроса</li>";
echo "</ul>";

echo "<h2>Типы анализируемых DML конструкций:</h2>";
echo "<h3>INSERT запросы:</h3>";
echo "<ul>";
echo "<li>Простой INSERT с VALUES</li>";
echo "<li>INSERT с указанием колонок</li>";
echo "<li>INSERT ... SELECT с подзапросами</li>";
echo "<li>INSERT с агрегацией и JOIN</li>";
echo "</ul>";

echo "<h3>UPDATE запросы:</h3>";
echo "<ul>";
echo "<li>Простой UPDATE с WHERE</li>";
echo "<li>UPDATE с множественными SET</li>";
echo "<li>UPDATE с JOIN условиями</li>";
echo "<li>UPDATE с подзапросами в WHERE</li>";
echo "<li>UPDATE с ORDER BY и LIMIT</li>";
echo "</ul>";

echo "<h3>DELETE запросы:</h3>";
echo "<ul>";
echo "<li>Простой DELETE с WHERE</li>";
echo "<li>DELETE с подзапросами</li>";
echo "<li>DELETE с JOIN условиями</li>";
echo "<li>DELETE с ORDER BY и LIMIT</li>";
echo "</ul>";

echo "<p><em>Откройте панель отладки (иконка в левом нижнем углу) и перейдите на таб 'Индексы' для подробного просмотра результатов анализа DML запросов.</em></p>";
?>

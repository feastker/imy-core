#!/usr/bin/env php
<?php
/**
 * Скрипт для тестирования подключения к базе данных
 * 
 * Использование:
 *   php test_connection.php
 */

require_once __DIR__ . '/autoload.php';

use Imy\Core\DB;
use Imy\Core\Config;

echo "=== Тест подключения к базе данных ===\n\n";

// Проверяем наличие конфигурации
try {
    $config = Config::get('db.default');
    
    if (!$config) {
        echo "❌ ОШИБКА: Конфигурация базы данных не найдена.\n";
        echo "   Создайте файл config.php на основе config_sample.php\n\n";
        exit(1);
    }
    
    echo "✓ Конфигурация загружена\n";
    echo "  Драйвер: " . ($config['driver'] ?? 'mysql (по умолчанию)') . "\n";
    echo "  Хост: " . ($config['host'] ?? 'не указан') . "\n";
    echo "  База данных: " . ($config['dbname'] ?? $config['name'] ?? 'не указана') . "\n";
    echo "  Пользователь: " . ($config['user'] ?? 'не указан') . "\n\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА при загрузке конфигурации: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Пытаемся подключиться
try {
    echo "Попытка подключения...\n";
    $db = DB::getInstance('default');
    
    echo "✓ Подключение установлено\n\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА подключения: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Выполняем тестовый запрос
try {
    echo "Выполнение тестового запроса...\n";
    $stmt = $db->query("SELECT VERSION() as version, DATABASE() as database, USER() as user");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ Запрос выполнен успешно\n\n";
        echo "Информация о сервере:\n";
        echo "  Версия: " . $result['version'] . "\n";
        echo "  База данных: " . $result['database'] . "\n";
        echo "  Пользователь: " . $result['user'] . "\n\n";
        
        // Определяем тип СУБД
        $version = strtolower($result['version']);
        if (strpos($version, 'mariadb') !== false) {
            echo "  Тип СУБД: MariaDB ✓\n";
        } else {
            echo "  Тип СУБД: MySQL ✓\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА при выполнении запроса: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Проверяем возможность создания таблицы
try {
    echo "\nПроверка прав доступа...\n";
    
    // Пытаемся создать тестовую таблицу
    $db->exec("CREATE TABLE IF NOT EXISTS `_imy_test` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `test_field` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "✓ Создание таблиц: OK\n";
    
    // Пытаемся вставить данные
    $db->exec("INSERT INTO `_imy_test` (`test_field`) VALUES ('test')");
    echo "✓ Вставка данных: OK\n";
    
    // Пытаемся прочитать данные
    $stmt = $db->query("SELECT * FROM `_imy_test` LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ Чтение данных: OK\n";
    }
    
    // Пытаемся обновить данные
    $db->exec("UPDATE `_imy_test` SET `test_field` = 'updated' WHERE `id` = " . $result['id']);
    echo "✓ Обновление данных: OK\n";
    
    // Пытаемся удалить данные
    $db->exec("DELETE FROM `_imy_test` WHERE `id` = " . $result['id']);
    echo "✓ Удаление данных: OK\n";
    
    // Удаляем тестовую таблицу
    $db->exec("DROP TABLE `_imy_test`");
    echo "✓ Удаление таблиц: OK\n";
    
} catch (Exception $e) {
    echo "⚠ ПРЕДУПРЕЖДЕНИЕ: Не все операции доступны\n";
    echo "  " . $e->getMessage() . "\n";
    
    // Пытаемся очистить тестовую таблицу, если она была создана
    try {
        $db->exec("DROP TABLE IF EXISTS `_imy_test`");
    } catch (Exception $e) {
        // Игнорируем ошибку
    }
}

echo "\n=== Тест завершен успешно ===\n";
echo "ORM готов к работе с вашей базой данных!\n\n";

exit(0);


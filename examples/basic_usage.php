<?php
/**
 * Примеры базового использования Imy Core ORM
 * 
 * Перед запуском убедитесь, что:
 * 1. Создан файл config.php на основе config_sample.php
 * 2. База данных доступна и настроена
 */

require_once __DIR__ . '/../autoload.php';

use Imy\Core\DB;
use Imy\Core\DBSelect;
use Imy\Core\DBManager;

// ============================================================================
// Пример 1: Простой SELECT запрос
// ============================================================================

echo "=== Пример 1: SELECT запрос ===\n";

try {
    $users = DBSelect::factory('users')
        ->select('id', 'name', 'email')
        ->where('status', 1)
        ->orderBy('created_at', 'DESC')
        ->limit(10)
        ->fetchAssocAll();
    
    echo "Найдено пользователей: " . count($users) . "\n";
    
    foreach ($users as $user) {
        echo "  - {$user['name']} ({$user['email']})\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 2: INSERT - вставка новой записи
// ============================================================================

echo "=== Пример 2: INSERT ===\n";

try {
    $result = DBManager::factory('users')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('status', 1)
        ->set('created_at', date('Y-m-d H:i:s'))
        ->insert();
    
    echo "Запись создана с ID: " . $result->lastId() . "\n";
    echo "Затронуто строк: " . $result->rowsAffected() . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 3: UPDATE - обновление записи
// ============================================================================

echo "=== Пример 3: UPDATE ===\n";

try {
    $affected = DBManager::factory('users')
        ->where('email', 'john@example.com')
        ->set('name', 'John Smith')
        ->set('updated_at', date('Y-m-d H:i:s'))
        ->update();
    
    echo "Обновлено записей: " . $affected . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 4: DELETE - удаление записи
// ============================================================================

echo "=== Пример 4: DELETE ===\n";

try {
    $affected = DBManager::factory('users')
        ->where('email', 'john@example.com')
        ->delete();
    
    echo "Удалено записей: " . $affected . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 5: JOIN запросы
// ============================================================================

echo "=== Пример 5: JOIN ===\n";

try {
    $orders = DBSelect::factory('orders o')
        ->select('o.id', 'o.total', 'u.name as customer_name', 'u.email')
        ->join('users u', 'LEFT')
        ->on('u.id', 'o.user_id')
        ->where('o.status', 'completed')
        ->orderBy('o.created_at', 'DESC')
        ->limit(5)
        ->fetchAssocAll();
    
    echo "Найдено заказов: " . count($orders) . "\n";
    
    foreach ($orders as $order) {
        echo "  Заказ #{$order['id']}: {$order['customer_name']} - {$order['total']} руб.\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 6: Сложные условия WHERE
// ============================================================================

echo "=== Пример 6: Сложные условия WHERE ===\n";

try {
    $users = DBSelect::factory('users')
        ->whereOpen()
            ->where('status', 1)
            ->where('role', 'admin')
        ->whereClose()
        ->orWhereOpen()
            ->where('status', 1)
            ->where('role', 'moderator')
        ->whereClose()
        ->fetchAssocAll();
    
    echo "Найдено администраторов и модераторов: " . count($users) . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 7: Агрегатные функции
// ============================================================================

echo "=== Пример 7: Агрегатные функции ===\n";

try {
    // COUNT
    $count = DBSelect::factory('users')
        ->where('status', 1)
        ->count();
    
    echo "Всего активных пользователей: " . $count . "\n";
    
    // MAX
    $maxId = DBSelect::factory('users')->max('id');
    echo "Максимальный ID: " . $maxId . "\n";
    
    // Пользовательские агрегатные функции
    $stats = DBSelect::factory('orders')
        ->select('COUNT(*) as total_orders', 'SUM(total) as total_amount', 'AVG(total) as avg_amount')
        ->where('status', 'completed')
        ->fetchAssoc();
    
    echo "Статистика заказов:\n";
    echo "  Всего заказов: " . $stats['total_orders'] . "\n";
    echo "  Сумма: " . number_format($stats['total_amount'], 2) . " руб.\n";
    echo "  Средний чек: " . number_format($stats['avg_amount'], 2) . " руб.\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 8: GROUP BY и HAVING
// ============================================================================

echo "=== Пример 8: GROUP BY и HAVING ===\n";

try {
    $userOrders = DBSelect::factory('orders')
        ->select('user_id', 'COUNT(*) as order_count', 'SUM(total) as total_spent')
        ->where('status', 'completed')
        ->groupBy('user_id')
        ->having('COUNT(*)', 5, '>')
        ->orderBy('total_spent', 'DESC')
        ->fetchAssocAll();
    
    echo "Пользователи с более чем 5 заказами:\n";
    
    foreach ($userOrders as $row) {
        echo "  Пользователь #{$row['user_id']}: {$row['order_count']} заказов на сумму {$row['total_spent']} руб.\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 9: Транзакции
// ============================================================================

echo "=== Пример 9: Транзакции ===\n";

try {
    $db = DB::getInstance();
    
    $db->beginTransaction();
    
    try {
        // Создаем заказ
        $orderResult = DBManager::factory('orders')
            ->set('user_id', 1)
            ->set('total', 1000)
            ->set('status', 'pending')
            ->insert();
        
        $orderId = $orderResult->lastId();
        
        // Добавляем товары в заказ
        DBManager::factory('order_items')
            ->set('order_id', $orderId)
            ->set('product_id', 10)
            ->set('quantity', 2)
            ->set('price', 500)
            ->insert();
        
        // Обновляем баланс пользователя
        DBManager::factory('users')
            ->where('id', 1)
            ->decrement('balance', 1000)
            ->update();
        
        $db->commit();
        
        echo "Транзакция выполнена успешно. Заказ #{$orderId} создан.\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "Транзакция отменена: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 10: Работа с несколькими базами данных
// ============================================================================

echo "=== Пример 10: Множественные подключения ===\n";

try {
    // Используем подключение по умолчанию
    $users = DBSelect::factory('users', 'default')
        ->limit(5)
        ->fetchAssocAll();
    
    echo "Пользователей в основной БД: " . count($users) . "\n";
    
    // Используем дополнительное подключение (если настроено)
    // $logs = DBSelect::factory('logs', 'logging')
    //     ->limit(5)
    //     ->fetchAssocAll();
    
    // echo "Записей в БД логов: " . count($logs) . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 11: INSERT с ON DUPLICATE KEY UPDATE
// ============================================================================

echo "=== Пример 11: INSERT с ON DUPLICATE KEY UPDATE ===\n";

try {
    $result = DBManager::factory('user_stats')
        ->set('user_id', 1)
        ->set('visits', 1)
        ->set('last_visit', date('Y-m-d H:i:s'))
        ->onDuplicateUpdate(true)
        ->increment('visits', 1)
        ->set('last_visit', date('Y-m-d H:i:s'))
        ->insert();
    
    echo "Статистика обновлена\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 12: Прямой SQL запрос
// ============================================================================

echo "=== Пример 12: Прямой SQL запрос ===\n";

try {
    $db = DB::getInstance();
    
    // SELECT запрос
    $stmt = $db->query("SELECT VERSION() as version, DATABASE() as db");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Версия БД: " . $result['version'] . "\n";
    echo "Текущая база: " . $result['db'] . "\n";
    
    // Запрос с параметрами (prepared statement)
    $stmt = $db->query(
        "SELECT * FROM users WHERE email = ? AND status = ?",
        ['user@example.com', 1]
    );
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Найден пользователь: " . $user['name'] . "\n";
    } else {
        echo "Пользователь не найден\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== Примеры завершены ===\n";


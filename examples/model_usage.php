<?php
/**
 * Примеры работы с моделями в Imy Core ORM
 */

require_once __DIR__ . '/../autoload.php';

use Imy\Core\Model;

// ============================================================================
// Определение модели
// ============================================================================

class User extends Model
{
    protected $table = 'users';
    protected $database = 'default';  // Опционально, по умолчанию 'default'
    protected $primary = 'id';        // Опционально, по умолчанию 'id'
}

class Order extends Model
{
    protected $table = 'orders';
    
    /**
     * Получить заказы пользователя
     */
    public function getByUserId($userId)
    {
        return $this->get()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->fetchAll();
    }
    
    /**
     * Получить завершенные заказы
     */
    public function getCompleted()
    {
        return $this->get()
            ->where('status', 'completed')
            ->orderBy('created_at', 'DESC')
            ->fetchAll();
    }
}

// ============================================================================
// Пример 1: Создание новой записи
// ============================================================================

echo "=== Пример 1: Создание пользователя ===\n";

try {
    $user = (new User())->factory();
    $user->setValue('name', 'Ivan Petrov');
    $user->setValue('email', 'ivan@example.com');
    $user->setValue('status', 1);
    $user->setValue('created_at', date('Y-m-d H:i:s'));
    
    $userId = $user->save();
    
    if ($userId) {
        echo "Пользователь создан с ID: {$userId}\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 2: Получение записи по ID
// ============================================================================

echo "=== Пример 2: Получение пользователя по ID ===\n";

try {
    $user = (new User())->getById(1);
    
    if ($user) {
        echo "ID: {$user->id}\n";
        echo "Имя: {$user->name}\n";
        echo "Email: {$user->email}\n";
        echo "Статус: {$user->status}\n";
    } else {
        echo "Пользователь не найден\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 3: Поиск по полям
// ============================================================================

echo "=== Пример 3: Поиск пользователя по email ===\n";

try {
    $user = (new User())->getByFields(['email' => 'ivan@example.com']);
    
    if ($user) {
        echo "Найден пользователь: {$user->name}\n";
    } else {
        echo "Пользователь не найден\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 4: Обновление записи
// ============================================================================

echo "=== Пример 4: Обновление пользователя ===\n";

try {
    $user = (new User())->getById(1);
    
    if ($user) {
        $user->setValue('name', 'Ivan Ivanov');
        $user->setValue('status', 2);
        $user->setValue('updated_at', date('Y-m-d H:i:s'));
        
        $result = $user->save();
        
        if ($result) {
            echo "Пользователь обновлен\n";
        }
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 5: Массовое обновление полей
// ============================================================================

echo "=== Пример 5: Массовое обновление ===\n";

try {
    $user = (new User())->getById(1);
    
    if ($user) {
        $user->setValues([
            'name' => 'Peter Sidorov',
            'email' => 'peter@example.com',
            'status' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $user->save();
        
        echo "Несколько полей обновлено\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 6: Копирование записи
// ============================================================================

echo "=== Пример 6: Копирование пользователя ===\n";

try {
    $user = (new User())->getById(1);
    
    if ($user) {
        $newUserId = $user->copy([
            'email' => 'copy_' . $user->email,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "Создана копия пользователя с ID: {$newUserId}\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 7: Удаление записи
// ============================================================================

echo "=== Пример 7: Удаление пользователя ===\n";

try {
    $user = (new User())->getByFields(['email' => 'copy_peter@example.com']);
    
    if ($user) {
        $result = $user->delete();
        
        if ($result) {
            echo "Пользователь удален\n";
        }
    } else {
        echo "Пользователь не найден\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 8: Работа с Query Builder через модель
// ============================================================================

echo "=== Пример 8: Query Builder ===\n";

try {
    // Получение DBSelect через модель
    $users = (new User())->get()
        ->where('status', 1)
        ->where('created_at', date('Y-m-d', strtotime('-30 days')), '>')
        ->orderBy('name', 'ASC')
        ->limit(10)
        ->fetchAll();
    
    echo "Найдено активных пользователей за последние 30 дней: " . count($users) . "\n";
    
    foreach ($users as $user) {
        echo "  - {$user->name} ({$user->email})\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 9: Пользовательские методы модели
// ============================================================================

echo "=== Пример 9: Пользовательские методы ===\n";

try {
    $order = new Order();
    
    // Получить заказы пользователя
    $userOrders = $order->getByUserId(1);
    echo "Заказов пользователя #1: " . count($userOrders) . "\n";
    
    // Получить завершенные заказы
    $completedOrders = $order->getCompleted();
    echo "Всего завершенных заказов: " . count($completedOrders) . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 10: Работа с составными первичными ключами
// ============================================================================

class UserRole extends Model
{
    protected $table = 'user_roles';
    protected $primary = ['user_id', 'role_id'];  // Составной первичный ключ
}

echo "=== Пример 10: Составной первичный ключ ===\n";

try {
    $userRole = (new UserRole())->factory();
    $userRole->setValue('user_id', 1);
    $userRole->setValue('role_id', 2);
    $userRole->setValue('created_at', date('Y-m-d H:i:s'));
    
    $result = $userRole->save();
    
    if ($result) {
        echo "Связь пользователя с ролью создана\n";
    }
    
    // Обновление с составным ключом
    $userRole = (new UserRole())->getByFields([
        'user_id' => 1,
        'role_id' => 2
    ]);
    
    if ($userRole) {
        $userRole->setValue('updated_at', date('Y-m-d H:i:s'));
        $userRole->save();
        echo "Связь обновлена\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 11: Работа с несколькими базами данных
// ============================================================================

class AnalyticsLog extends Model
{
    protected $table = 'logs';
    protected $database = 'analytics';  // Используем отдельную БД для логов
}

echo "=== Пример 11: Работа с разными БД ===\n";

try {
    // Работа с основной БД
    $user = (new User())->getById(1);
    echo "Пользователь из основной БД: {$user->name}\n";
    
    // Работа с БД аналитики (если настроена)
    // $log = (new AnalyticsLog())->factory();
    // $log->setValue('action', 'user_login');
    // $log->setValue('user_id', $user->id);
    // $log->setValue('created_at', date('Y-m-d H:i:s'));
    // $log->save();
    
    // echo "Лог записан в БД аналитики\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 12: Получение DBManager из модели
// ============================================================================

echo "=== Пример 12: Массовые операции через DBManager ===\n";

try {
    $user = new User();
    
    // Массовое обновление
    $affected = $user->getDBManager()
        ->where('status', 0)
        ->where('created_at', date('Y-m-d', strtotime('-90 days')), '<')
        ->set('status', -1)  // Архивируем старых неактивных пользователей
        ->update();
    
    echo "Архивировано пользователей: {$affected}\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Пример 13: Отладка запросов
// ============================================================================

echo "=== Пример 13: Отладка SQL запросов ===\n";

try {
    $user = new User();
    
    // Получаем DBSelect
    $query = $user->get()
        ->where('status', 1)
        ->where('email', '%@example.com', 'LIKE')
        ->orderBy('created_at', 'DESC')
        ->limit(10);
    
    // Выводим SQL без выполнения
    echo "SQL запрос:\n";
    echo $query->toString() . "\n";
    
    // Или используем die для отладки
    // $users = $query->fetch(true);  // true = выведет SQL и остановит выполнение
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== Примеры работы с моделями завершены ===\n";


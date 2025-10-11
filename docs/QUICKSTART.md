# Быстрый старт с Imy Core

Это краткое руководство поможет вам начать работу с Imy Core ORM за 5 минут.

## 1. Установка и настройка

### Шаг 1: Создайте конфигурацию

```bash
cp config_sample.php config.php
```

### Шаг 2: Отредактируйте config.php

```php
<?php
return [
    'db' => [
        'default' => [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'port'     => 3306,
            'dbname'   => 'your_database',
            'user'     => 'your_username',
            'password' => 'your_password',
            'charset'  => 'utf8mb4'
        ]
    ]
];
```

### Шаг 3: Проверьте подключение

```bash
php test_connection.php
```

Если вы видите `✓ Подключение установлено` - всё готово к работе!

## 2. Первые запросы

### SELECT

```php
use Imy\Core\DBSelect;

// Получить всех пользователей
$users = DBSelect::factory('users')->fetchAssocAll();

// С условиями
$activeUsers = DBSelect::factory('users')
    ->where('status', 1)
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->fetchAssocAll();

// Одна запись
$user = DBSelect::factory('users')
    ->where('id', 1)
    ->fetchAssoc();
```

### INSERT

```php
use Imy\Core\DBManager;

$result = DBManager::factory('users')
    ->set('name', 'John Doe')
    ->set('email', 'john@example.com')
    ->set('status', 1)
    ->insert();

echo "Новый ID: " . $result->lastId();
```

### UPDATE

```php
$affected = DBManager::factory('users')
    ->where('id', 1)
    ->set('name', 'Jane Doe')
    ->set('status', 2)
    ->update();

echo "Обновлено записей: " . $affected;
```

### DELETE

```php
$affected = DBManager::factory('users')
    ->where('id', 1)
    ->delete();

echo "Удалено записей: " . $affected;
```

## 3. Работа с моделями

### Создание модели

```php
use Imy\Core\Model;

class User extends Model
{
    protected $table = 'users';
}
```

### Использование модели

```php
// Создание
$user = (new User())->factory();
$user->setValue('name', 'John Doe');
$user->setValue('email', 'john@example.com');
$userId = $user->save();

// Чтение
$user = (new User())->getById($userId);
echo $user->name; // John Doe

// Обновление
$user->setValue('name', 'Jane Doe');
$user->save();

// Удаление
$user->delete();

// Поиск
$user = (new User())->getByFields(['email' => 'john@example.com']);
```

## 4. Продвинутые запросы

### JOIN

```php
$orders = DBSelect::factory('orders o')
    ->select('o.*', 'u.name as customer_name')
    ->join('users u', 'LEFT')
    ->on('u.id', 'o.user_id')
    ->where('o.status', 'completed')
    ->fetchAssocAll();
```

### GROUP BY и агрегатные функции

```php
$stats = DBSelect::factory('orders')
    ->select('user_id', 'COUNT(*) as total_orders', 'SUM(amount) as total_spent')
    ->where('status', 'completed')
    ->groupBy('user_id')
    ->having('COUNT(*)', 5, '>')
    ->fetchAssocAll();
```

### Транзакции

```php
use Imy\Core\DB;

$db = DB::getInstance();

$db->beginTransaction();

try {
    DBManager::factory('users')
        ->where('id', 1)
        ->set('balance', 100)
        ->update();
    
    DBManager::factory('transactions')
        ->set('user_id', 1)
        ->set('amount', 100)
        ->insert();
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo "Ошибка: " . $e->getMessage();
}
```

## 5. Полезные возможности

### Подсчет записей

```php
$count = DBSelect::factory('users')
    ->where('status', 1)
    ->count();
```

### Сложные условия WHERE

```php
$users = DBSelect::factory('users')
    ->whereOpen()
        ->where('role', 'admin')
        ->where('status', 1)
    ->whereClose()
    ->orWhereOpen()
        ->where('role', 'moderator')
        ->where('status', 1)
    ->whereClose()
    ->fetchAssocAll();
```

### Инкремент/Декремент

```php
DBManager::factory('users')
    ->where('id', 1)
    ->increment('visits', 1)  // Увеличить на 1
    ->update();

DBManager::factory('users')
    ->where('id', 1)
    ->decrement('balance', 100)  // Уменьшить на 100
    ->update();
```

### ON DUPLICATE KEY UPDATE

```php
DBManager::factory('user_stats')
    ->set('user_id', 1)
    ->set('visits', 1)
    ->onDuplicateUpdate(true)
    ->increment('visits', 1)
    ->insert();
```

## 6. Отладка

### Просмотр SQL

```php
$query = DBSelect::factory('users')
    ->where('status', 1);

echo $query->toString();
// Вывод: SELECT * FROM `users` WHERE `status` = '1'
```

### Остановка с выводом SQL

```php
// Выведет SQL и остановит выполнение
$users = DBSelect::factory('users')
    ->where('status', 1)
    ->fetch(true);  // true = debug mode
```

## 7. Множественные базы данных

### Настройка

```php
'db' => [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'dbname'   => 'main_db',
        'user'     => 'user1',
        'password' => 'pass1'
    ],
    'logs' => [
        'driver'   => 'mariadb',
        'host'     => 'logs.server.com',
        'dbname'   => 'logs_db',
        'user'     => 'user2',
        'password' => 'pass2'
    ]
]
```

### Использование

```php
// Основная БД
$users = DBSelect::factory('users', 'default')->fetchAssocAll();

// БД логов
$logs = DBSelect::factory('logs', 'logs')->fetchAssocAll();

// В моделях
class Log extends Model
{
    protected $table = 'logs';
    protected $database = 'logs';
}
```

## 8. Различия MySQL и MariaDB

Оба драйвера полностью совместимы для стандартных операций:

```php
// MySQL
'driver' => 'mysql'

// MariaDB (использует тот же PDO драйвер)
'driver' => 'mariadb'
```

Оба драйвера полностью совместимы для стандартных SQL операций

## 9. Примеры кода

В директории `examples/` находятся полные рабочие примеры:

```bash
# Базовые операции
php examples/basic_usage.php

# Работа с моделями
php examples/model_usage.php
```

## 10. Дополнительная информация

### Документация

- [README.md](../README.md) - Полная документация
- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Руководство по миграции
- [UPGRADE.md](UPGRADE.md) - Обновление с предыдущих версий

### Структура таблиц

Пример создания таблицы:

```sql
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `status` tinyint(1) DEFAULT 1,
    `created_at` datetime DEFAULT NULL,
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Лучшие практики

1. **Используйте индексы** для часто запрашиваемых полей
2. **Выбирайте только нужные поля** вместо `SELECT *`
3. **Используйте транзакции** для связанных операций
4. **Применяйте prepared statements** (автоматически в ORM)
5. **Используйте utf8mb4** для полной поддержки Unicode

### Производительность

```php
// ✓ Хорошо
$users = DBSelect::factory('users')
    ->select('id', 'name', 'email')  // Только нужные поля
    ->where('status', 1)
    ->setIndex('idx_status')         // Принудительный индекс
    ->limit(100)
    ->fetchAssocAll();

// ✗ Плохо
$users = DBSelect::factory('users')
    ->fetchAssocAll();  // Выбор всех полей без условий
```

## Готово!

Теперь вы знаете основы работы с Imy Core ORM. Изучите примеры и документацию для более глубокого понимания возможностей фреймворка.

### Следующие шаги

1. Изучите примеры в `examples/`
2. Прочитайте [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) для подробной информации
3. Создайте свои модели для ваших таблиц
4. Начните разработку вашего приложения!

---

Удачи в разработке! 🚀


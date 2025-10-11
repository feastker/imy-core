# Руководство по поддержке MySQL и MariaDB

## Обзор изменений

Начиная с версии 1.0, ORM Imy Core явно поддерживает работу как с MySQL, так и с MariaDB. Хотя оба драйвера используют один и тот же PDO драйвер (`mysql`), теперь вы можете явно указать тип базы данных в конфигурации.

## Что изменилось

### 1. Файл конфигурации

**Старый формат:**
```php
'db' => [
    'default' => [
        'host'     => 'localhost',
        'user'     => 'root',
        'password' => '',
        'name'     => 'mydb'
    ]
]
```

**Новый формат:**
```php
'db' => [
    'default' => [
        'driver'   => 'mysql',      // Явно указываем драйвер
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'mydb',       // 'name' изменен на 'dbname' (стандарт PDO)
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4'
    ]
]
```

### 2. Ключевые изменения в параметрах

- Добавлен параметр `driver` (по умолчанию: `mysql`)
- Параметр `name` переименован в `dbname` (соответствует стандарту PDO DSN)
- Добавлен параметр `port` (по умолчанию: 3306)
- Добавлен параметр `charset` (по умолчанию: `utf8`)

### 3. Обратная совместимость

⚠️ **Важно:** Старый формат конфигурации с параметром `name` больше не поддерживается. Вам нужно обновить конфигурацию и использовать `dbname`.

## Миграция существующих проектов

### Шаг 1: Обновите файл конфигурации

Откройте ваш файл `config.php` и обновите секцию `db`:

```php
// Было:
'default' => [
    'host'     => 'localhost',
    'user'     => 'dbuser',
    'password' => 'dbpass',
    'name'     => 'mydb'
]

// Стало:
'default' => [
    'driver'   => 'mysql',       // или 'mariadb'
    'host'     => 'localhost',
    'port'     => 3306,
    'dbname'   => 'mydb',        // Изменено с 'name' на 'dbname'
    'user'     => 'dbuser',
    'password' => 'dbpass',
    'charset'  => 'utf8mb4'
]
```

### Шаг 2: Обновите библиотеку Imy Core

Замените файл `DB.php` на новую версию, которая включает поддержку множественных драйверов.

### Шаг 3: Тестирование

После обновления протестируйте ваше приложение:

```php
// Проверьте подключение
$db = \Imy\Core\DB::getInstance();
$result = $db->query("SELECT 1")->fetch();

// Проверьте базовые операции
$users = \Imy\Core\DBSelect::factory('users')->limit(1)->fetchAssoc();
```

## Примеры конфигурации

### MySQL 8.0

```php
'db' => [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'myapp',
        'user'     => 'root',
        'password' => 'password',
        'charset'  => 'utf8mb4'
    ]
]
```

### MariaDB 10.x

```php
'db' => [
    'default' => [
        'driver'   => 'mariadb',    // Можно указать явно, хотя 'mysql' тоже работает
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'myapp',
        'user'     => 'root',
        'password' => 'password',
        'charset'  => 'utf8mb4'
    ]
]
```

### С SSL соединением

```php
'db' => [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'db.example.com',
        'port'     => 3306,
        'dbname'   => 'secure_db',
        'user'     => 'appuser',
        'password' => 'securepass',
        'charset'  => 'utf8mb4',
        'ca'       => '/path/to/ca-cert.pem'
    ]
]
```

### Множественные подключения

```php
'db' => [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'dbname'   => 'main_db',
        'user'     => 'root',
        'password' => 'pass',
        'charset'  => 'utf8mb4'
    ],
    'reporting' => [
        'driver'   => 'mariadb',
        'host'     => 'reports.example.com',
        'dbname'   => 'reports_db',
        'user'     => 'reporter',
        'password' => 'reportpass',
        'charset'  => 'utf8mb4'
    ]
]
```

Использование в коде:

```php
// Подключение по умолчанию
$users = DBSelect::factory('users')->fetchAssocAll();

// Использование именованного подключения
$reports = DBSelect::factory('reports', 'reporting')->fetchAssocAll();
```

## Различия между MySQL и MariaDB

Хотя MySQL и MariaDB в основном совместимы, есть некоторые различия:

### Функции, специфичные для MariaDB

- `JSON_DETAILED()` - расширенная обработка JSON
- `REGEXP_REPLACE()` - замена по регулярному выражению
- Улучшенная производительность для некоторых операций

### Функции, специфичные для MySQL 8.0+

- Оконные функции (window functions)
- CTE (Common Table Expressions)
- `JSON_TABLE()` - преобразование JSON в таблицу

### Рекомендации

- Используйте `driver: 'mysql'` для универсальности (работает с обеими СУБД)
- Используйте `driver: 'mariadb'` только если хотите явно указать, что используете MariaDB
- Избегайте использования специфичных для конкретной СУБД функций, если планируете переключаться между ними

## Устранение проблем

### Ошибка: "could not find driver"

**Решение:** Убедитесь, что PDO MySQL расширение установлено:

```bash
# Проверка установленных расширений
php -m | grep pdo_mysql

# Установка расширения (Ubuntu/Debian)
sudo apt-get install php-mysql

# Установка расширения (CentOS/RHEL)
sudo yum install php-mysqlnd
```

### Ошибка: "Access denied for user"

**Решение:** Проверьте права доступа пользователя к базе данных:

```sql
-- Создание пользователя
CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';

-- Предоставление прав
GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

### Проблемы с кодировкой

**Решение:** Убедитесь, что используете `utf8mb4`:

```php
'charset' => 'utf8mb4'  // Поддержка всех символов Unicode, включая эмодзи
```

## Дополнительная информация

- [Документация MySQL](https://dev.mysql.com/doc/)
- [Документация MariaDB](https://mariadb.com/kb/en/)
- [PHP PDO Документация](https://www.php.net/manual/en/book.pdo.php)

## Поддержка

Если у вас возникли проблемы с миграцией, пожалуйста, создайте issue в репозитории проекта.


# Imy Core v.1.0

PHP-фреймворк с простым ORM для работы с базами данных.

## Поддерживаемые базы данных

ORM поддерживает работу с:
- **MySQL** (версии 5.7+, 8.0+)
- **MariaDB** (версии 10.2+)

Оба драйвера используют PDO и полностью совместимы между собой.

## Настройка подключения к базе данных

Создайте файл `config.php` на основе `config_sample.php`:

```php
<?php

return [
    'db' => [
        'default' => [
            'driver'   => 'mysql',      // 'mysql' или 'mariadb' (оба используют один драйвер PDO)
            'host'     => 'localhost',
            'port'     => 3306,
            'dbname'   => 'your_database',
            'user'     => 'your_username',
            'password' => 'your_password',
            'charset'  => 'utf8mb4',
        ]
    ]
];
```

### Параметры подключения

- `driver` - драйвер БД: `mysql` или `mariadb` (по умолчанию: `mysql`)
- `host` - хост сервера БД
- `port` - порт (по умолчанию: 3306)
- `dbname` - имя базы данных
- `user` - имя пользователя
- `password` - пароль
- `charset` - кодировка (рекомендуется `utf8mb4`)
- `persistent` - использовать постоянные соединения (опционально, по умолчанию: `false`)
- `ca` - путь к SSL-сертификату для защищенного соединения (опционально)

### Множественные подключения

Вы можете настроить несколько подключений к разным базам данных:

```php
'db' => [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'dbname'   => 'main_db',
        // ...
    ],
    'analytics' => [
        'driver'   => 'mariadb',
        'host'     => 'analytics.example.com',
        'dbname'   => 'analytics_db',
        // ...
    ]
]
```

## Использование

### Базовые запросы

```php
// SELECT
$users = DBSelect::factory('users')
    ->where('status', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->fetchAssocAll();

// INSERT
DBManager::factory('users')
    ->set('name', 'John Doe')
    ->set('email', 'john@example.com')
    ->insert();

// UPDATE
DBManager::factory('users')
    ->where('id', 1)
    ->set('status', 1)
    ->update();

// DELETE
DBManager::factory('users')
    ->where('id', 1)
    ->delete();
```

### Работа с Models

```php
class User extends \Imy\Core\Model
{
    protected $table = 'users';
}

// Получение записи
$user = (new User())->getById(1);

// Создание записи
$user = (new User())->factory();
$user->setValue('name', 'John Doe');
$user->setValue('email', 'john@example.com');
$user->save();

// Обновление записи
$user->setValue('status', 1);
$user->save();

// Удаление
$user->delete();
```

## Быстрый старт

```bash
# 1. Создайте конфигурацию
cp config_sample.php config.php
# Отредактируйте config.php

# 2. Проверьте подключение
php test_connection.php

# 3. Изучите примеры
php examples/basic_usage.php
php examples/model_usage.php
php examples/debug_usage.php
php examples/test_debug.php
```

## 🔧 Дебаг панель

IMY Core включает в себя мощную дебаг панель, аналогичную Laravel Debugbar:

- **🔧 Удобная иконка** в левом нижнем углу для быстрого доступа
- **📊 Табы для организации информации** - Обзор, SQL, Переменные, Логи, Производительность, Файлы, Ошибки
- **🗄️ Автоматическое логирование SQL запросов** с цветовой индикацией времени выполнения
- **🌐 Полная информация о переменных** - GET/POST/COOKIE/SERVER данные
- **🔗 Подсчет соединений с БД** для анализа производительности
- **💾 Мониторинг памяти** (текущее и пиковое использование)
- **📝 Ручное логирование** с различными уровнями (info, warning, error)
- **⚡ Детальная информация о производительности** - PHP, сервер, статистика
- **📁 Анализ подключенных файлов** - размеры, даты, общая статистика
- **⚠️ Отслеживание ошибок** - PHP ошибки с детальной информацией
- **🎨 Современный интерфейс** с адаптивной раскладкой и плавными анимациями

### Включение дебаг панели

```php
// В config.php
'debug' => [
    'enabled' => true,  // включить/выключить дебаг панель
]
```

### Использование

```php
use Imy\Core\Debug;

// Логирование
Debug::log('Информация', 'info');
Debug::log('Предупреждение', 'warning');
Debug::log('Ошибка', 'error');

// HTTP заголовки
Debug::addHeader('X-Custom-Header', 'Value');
```

📖 [Подробная документация по дебаг панели](docs/DEBUG_PANEL.md)

## Документация

- 📚 [Быстрый старт](docs/QUICKSTART.md) - начните работу за 5 минут
- 📖 [Руководство по миграции](docs/MIGRATION_GUIDE.md) - подробное руководство
- ⬆️ [Обновление](docs/UPGRADE.md) - обновление с предыдущих версий
- 📋 [История изменений](docs/CHANGELOG.md) - список всех изменений
- 📝 [Сводка изменений](docs/SUMMARY.md) - краткая сводка обновлений

## Примеры

Рабочие примеры кода находятся в директории `examples/`:
- `basic_usage.php` - базовые операции с БД
- `model_usage.php` - работа с моделями ORM
- `debug_usage.php` - пример использования дебаг панели
- `test_debug.php` - полная демонстрация дебаг панели
- См. [examples/README.md](examples/README.md) для подробностей

## Миграции

Запуск миграций:
```bash
php migrator.php
```

## Требования

- PHP 7.4 или выше
- PDO расширение
- MySQL 5.7+ или MariaDB 10.2+

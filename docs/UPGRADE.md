# Руководство по обновлению Imy Core

## Обновление до версии 1.0.1

### Критические изменения (Breaking Changes)

#### 1. Изменение формата конфигурации базы данных

**Было (версия 1.0.0):**
```php
'db' => [
    'default' => [
        'host'     => 'localhost',
        'user'     => 'username',
        'password' => 'password',
        'name'     => 'database_name'  // ← Старый параметр
    ]
]
```

**Стало (версия 1.0.1):**
```php
'db' => [
    'default' => [
        'driver'   => 'mysql',           // ← Новый параметр (опционально)
        'host'     => 'localhost',
        'port'     => 3306,              // ← Новый параметр (опционально)
        'dbname'   => 'database_name',   // ← Изменено с 'name' на 'dbname'
        'user'     => 'username',
        'password' => 'password',
        'charset'  => 'utf8mb4'          // ← Новый параметр (опционально)
    ]
]
```

### Шаги обновления

#### Шаг 1: Обновите файл DB.php

Замените файл `DB.php` на новую версию:

```bash
# Сделайте резервную копию
cp DB.php DB.php.backup

# Обновите файл (скопируйте новую версию)
```

#### Шаг 2: Обновите конфигурацию

Откройте ваш файл `config.php` и обновите секцию `db`:

**Минимальная конфигурация:**
```php
'db' => [
    'default' => [
        'host'     => 'localhost',
        'dbname'   => 'your_database',  // Изменено с 'name'
        'user'     => 'your_user',
        'password' => 'your_password'
    ]
]
```

**Рекомендуемая конфигурация:**
```php
'db' => [
    'default' => [
        'driver'   => 'mysql',          // Явно указываем драйвер
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'your_database',
        'user'     => 'your_user',
        'password' => 'your_password',
        'charset'  => 'utf8mb4'         // Поддержка всех символов Unicode
    ]
]
```

#### Шаг 3: Проверьте подключение

Запустите тестовый скрипт для проверки:

```bash
php test_connection.php
```

Ожидаемый вывод:
```
=== Тест подключения к базе данных ===

✓ Конфигурация загружена
  Драйвер: mysql
  Хост: localhost
  База данных: your_database
  Пользователь: your_user

Попытка подключения...
✓ Подключение установлено

...
=== Тест завершен успешно ===
```

#### Шаг 4: Тестируйте приложение

После обновления протестируйте все функции вашего приложения:

```php
// Проверьте базовые операции
$users = DBSelect::factory('users')->limit(1)->fetchAssoc();

if ($users) {
    echo "✓ SELECT работает\n";
}

// Проверьте транзакции
$db = DB::getInstance();
$db->beginTransaction();
// ... ваш код ...
$db->commit();
```

### Что нового

#### Явная поддержка драйверов

Теперь можно явно указать драйвер базы данных:

```php
'driver' => 'mysql'    // для MySQL
'driver' => 'mariadb'  // для MariaDB (использует тот же PDO драйвер)
```

#### Стандартные DSN параметры

Теперь поддерживаются стандартные параметры PDO DSN:

- `dbname` вместо `name`
- `port` для указания порта
- `charset` для кодировки

#### Улучшенная обработка параметров

Служебные параметры (driver, user, password, charset, persistent, ca) автоматически исключаются из DSN строки.

### Совместимость

#### Обратная совместимость

⚠️ **ВНИМАНИЕ:** Параметр `name` больше не поддерживается. Необходимо обновить конфигурацию на `dbname`.

#### Поддержка PHP

- **Минимальная версия:** PHP 7.4
- **Рекомендуемая версия:** PHP 8.0+

#### Поддержка баз данных

- MySQL 5.7+
- MySQL 8.0+
- MariaDB 10.2+
- MariaDB 10.5+

### Решение проблем

#### Проблема: "config for db not found"

**Причина:** Конфигурация не загружена или файл config.php отсутствует.

**Решение:**
```bash
# Проверьте наличие файла
ls -la config.php

# Создайте на основе примера
cp config_sample.php config.php
# Отредактируйте config.php
```

#### Проблема: "SQLSTATE[HY000] [1049] Unknown database"

**Причина:** Указанная база данных не существует или неверное имя.

**Решение:**
```sql
-- Создайте базу данных
CREATE DATABASE your_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Проблема: "Access denied for user"

**Причина:** Неверные учетные данные или недостаточно прав.

**Решение:**
```sql
-- Проверьте пользователя
SELECT User, Host FROM mysql.user WHERE User = 'your_user';

-- Предоставьте права
GRANT ALL PRIVILEGES ON your_database.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Проблема: "could not find driver"

**Причина:** PDO MySQL расширение не установлено.

**Решение:**
```bash
# Ubuntu/Debian
sudo apt-get install php-mysql
sudo systemctl restart apache2  # или php-fpm

# CentOS/RHEL
sudo yum install php-mysqlnd
sudo systemctl restart httpd

# Проверка
php -m | grep pdo_mysql
```

### Откат (Rollback)

Если после обновления возникли проблемы:

#### 1. Восстановите старый файл DB.php

```bash
cp DB.php.backup DB.php
```

#### 2. Верните старую конфигурацию

```php
'db' => [
    'default' => [
        'host'     => 'localhost',
        'user'     => 'username',
        'password' => 'password',
        'name'     => 'database_name'  // Старый формат
    ]
]
```

⚠️ **Внимание:** После отката новые возможности (явная поддержка драйверов) будут недоступны.

### Миграция множественных подключений

Если у вас настроено несколько подключений:

**Было:**
```php
'db' => [
    'default' => [
        'host' => 'localhost',
        'name' => 'main_db',
        'user' => 'user1',
        'password' => 'pass1'
    ],
    'logs' => [
        'host' => 'logs.server.com',
        'name' => 'logs_db',
        'user' => 'user2',
        'password' => 'pass2'
    ]
]
```

**Стало:**
```php
'db' => [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'dbname'   => 'main_db',
        'user'     => 'user1',
        'password' => 'pass1',
        'charset'  => 'utf8mb4'
    ],
    'logs' => [
        'driver'   => 'mariadb',  // Можно использовать разные драйверы
        'host'     => 'logs.server.com',
        'dbname'   => 'logs_db',
        'user'     => 'user2',
        'password' => 'pass2',
        'charset'  => 'utf8mb4'
    ]
]
```

### Дополнительные ресурсы

- [README.md](../README.md) - Основная документация
- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Подробное руководство по миграции
- [CHANGELOG.md](CHANGELOG.md) - Полный список изменений

### Получение помощи

Если у вас возникли проблемы при обновлении:

1. Проверьте [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)
2. Изучите примеры в директории `examples/`
3. Запустите `test_connection.php` для диагностики
4. Создайте issue в репозитории проекта

### Контрольный список обновления

- [ ] Создана резервная копия DB.php
- [ ] Создана резервная копия config.php
- [ ] Обновлен файл DB.php
- [ ] Обновлена конфигурация (name → dbname)
- [ ] Добавлены новые параметры (driver, port, charset)
- [ ] Запущен test_connection.php
- [ ] Протестированы базовые операции (SELECT, INSERT, UPDATE, DELETE)
- [ ] Протестированы транзакции
- [ ] Протестировано приложение в целом

---

**Версия документа:** 1.0.1  
**Дата обновления:** 2025-10-11


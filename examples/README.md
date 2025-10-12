# Примеры использования Imy Core ORM

Эта директория содержит примеры использования различных возможностей Imy Core ORM.

## Перед запуском примеров

1. **Настройте подключение к базе данных**
   
   Создайте файл `config.php` в корневой директории проекта на основе `config_sample.php`:
   
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

2. **Создайте необходимые таблицы**
   
   Для работы примеров вам понадобятся следующие таблицы:
   
   ```sql
   -- Таблица пользователей
   CREATE TABLE `users` (
       `id` int(11) NOT NULL AUTO_INCREMENT,
       `name` varchar(255) NOT NULL,
       `email` varchar(255) NOT NULL,
       `status` tinyint(1) DEFAULT 1,
       `balance` decimal(10,2) DEFAULT 0,
       `created_at` datetime DEFAULT NULL,
       `updated_at` datetime DEFAULT NULL,
       PRIMARY KEY (`id`),
       UNIQUE KEY `email` (`email`),
       KEY `idx_status` (`status`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- Таблица заказов
   CREATE TABLE `orders` (
       `id` int(11) NOT NULL AUTO_INCREMENT,
       `user_id` int(11) NOT NULL,
       `total` decimal(10,2) NOT NULL,
       `status` varchar(50) DEFAULT 'pending',
       `created_at` datetime DEFAULT NULL,
       `updated_at` datetime DEFAULT NULL,
       PRIMARY KEY (`id`),
       KEY `idx_user_id` (`user_id`),
       KEY `idx_status` (`status`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- Таблица позиций заказов
   CREATE TABLE `order_items` (
       `id` int(11) NOT NULL AUTO_INCREMENT,
       `order_id` int(11) NOT NULL,
       `product_id` int(11) NOT NULL,
       `quantity` int(11) NOT NULL,
       `price` decimal(10,2) NOT NULL,
       PRIMARY KEY (`id`),
       KEY `idx_order_id` (`order_id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- Таблица статистики пользователей
   CREATE TABLE `user_stats` (
       `user_id` int(11) NOT NULL,
       `visits` int(11) DEFAULT 0,
       `last_visit` datetime DEFAULT NULL,
       PRIMARY KEY (`user_id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- Таблица ролей пользователей
   CREATE TABLE `user_roles` (
       `user_id` int(11) NOT NULL,
       `role_id` int(11) NOT NULL,
       `created_at` datetime DEFAULT NULL,
       `updated_at` datetime DEFAULT NULL,
       PRIMARY KEY (`user_id`, `role_id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

3. **Проверьте подключение**
   
   Запустите тестовый скрипт для проверки подключения:
   
   ```bash
   php ../test_connection.php
   ```

## Доступные примеры

### basic_usage.php

Демонстрирует базовые операции с базой данных:

- SELECT запросы
- INSERT операции
- UPDATE операции
- DELETE операции
- JOIN запросы
- Сложные условия WHERE
- Агрегатные функции (COUNT, SUM, AVG, MAX)
- GROUP BY и HAVING
- Транзакции
- Работа с несколькими базами данных
- INSERT с ON DUPLICATE KEY UPDATE
- Прямые SQL запросы

**Запуск:**
```bash
php basic_usage.php
```

### model_usage.php

Демонстрирует работу с моделями ORM:

- Определение моделей
- Создание новых записей
- Получение записей по ID
- Поиск по полям
- Обновление записей
- Массовое обновление полей
- Копирование записей
- Удаление записей
- Query Builder через модели
- Пользовательские методы моделей
- Составные первичные ключи
- Работа с несколькими базами данных
- Массовые операции через DBManager
- Отладка SQL запросов

**Запуск:**
```bash
php model_usage.php
```

### debug_usage.php

Демонстрирует использование дебаг панели:

- Включение дебаг панели
- Логирование сообщений
- Добавление HTTP заголовков
- Работа с SQL запросами
- Отображение информации о производительности

**Запуск:**
```bash
php debug_usage.php
```

### test_debug.php

Полная демонстрация возможностей дебаг панели:

- Все 7 табов дебаг панели
- Информация о производительности
- Анализ подключенных файлов
- Отслеживание ошибок
- Интерактивный интерфейс

**Запуск:**
```bash
php test_debug.php
```

## Структура примеров

Каждый пример содержит:

- Подробные комментарии
- Обработку ошибок
- Примеры вывода
- Пояснения к коду

## Модификация примеров

Вы можете свободно модифицировать примеры под свои нужды:

1. Измените названия таблиц в соответствии с вашей схемой БД
2. Добавьте свои поля и условия
3. Экспериментируйте с различными методами ORM

## Дополнительная информация

### Полезные ссылки

- [README.md](../README.md) - Основная документация
- [Быстрый старт](../docs/QUICKSTART.md) - руководство по быстрому старту
- [Руководство по миграции](../docs/MIGRATION_GUIDE.md) - подробное руководство
- [История изменений](../docs/CHANGELOG.md) - список всех изменений

### Отладка

Для отладки SQL запросов используйте:

```php
// Вывод SQL без выполнения
echo $query->toString();

// Вывод SQL и остановка выполнения
$result = $query->fetch(true);  // или ->update(true) / ->delete(true)
```

### Производительность

Для оптимизации запросов:

1. Используйте индексы
2. Выбирайте только необходимые поля
3. Используйте limit для больших выборок
4. Применяйте EXPLAIN для анализа запросов

```php
// Оптимизированный запрос
$users = DBSelect::factory('users')
    ->select('id', 'name', 'email')  // только нужные поля
    ->where('status', 1)
    ->setIndex('idx_status')          // принудительный индекс
    ->limit(100)
    ->fetchAssocAll();
```

## Поддержка

Если у вас возникли проблемы с примерами:

1. Проверьте настройки подключения к БД
2. Убедитесь, что все таблицы созданы
3. Проверьте права доступа пользователя БД
4. Посмотрите логи ошибок PHP

## Лицензия

Примеры предоставляются "как есть" для демонстрации возможностей Imy Core ORM.


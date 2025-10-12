<?php
/**
 * Пример использования дебаг панели
 */

require_once '../autoload.php';

use Imy\Core\Core;
use Imy\Core\Debug;
use Imy\Core\DBSelect;
use Imy\Core\Model;

// Инициализируем приложение
Core::init('config');

// Пример использования дебага в коде
Debug::log('Начало выполнения скрипта', 'info');
Debug::addHeader('Custom-Header', 'Debug Value');

// Пример работы с базой данных
try {
    // Создаем запрос
    $users = DBSelect::factory('users')
        ->select('id', 'name', 'email')
        ->where('active', 1)
        ->limit(10);
    
    $user_list = $users->fetchAssocAll();
    
    Debug::log('Получено пользователей: ' . count($user_list), 'info');
    
    // Еще один запрос
    $count = DBSelect::factory('users')
        ->count('id');
    
    Debug::log('Общее количество пользователей: ' . $count, 'info');
    
    // Пример с JOIN
    $posts = DBSelect::factory('posts')
        ->select('posts.title', 'posts.content', 'users.name as author')
        ->join('users', 'INNER')
        ->on('posts.user_id', 'users.id')
        ->where('posts.published', 1)
        ->limit(5);
    
    $post_list = $posts->fetchAssocAll();
    
    Debug::log('Получено постов: ' . count($post_list), 'info');
    
} catch (Exception $e) {
    Debug::log('Ошибка при работе с БД: ' . $e->getMessage(), 'error');
}

Debug::log('Скрипт завершен', 'info');

// Выводим простую страницу
echo '<h1>Пример использования дебаг панели</h1>';
echo '<p>Откройте консоль разработчика или посмотрите внизу страницы - там должна появиться дебаг панель.</p>';
echo '<p>В панели вы увидите:</p>';
echo '<ul>';
echo '<li>Время выполнения скрипта</li>';
echo '<li>Использование памяти</li>';
echo '<li>Список всех SQL запросов с временем выполнения</li>';
echo '<li>Количество соединений с базой данных</li>';
echo '<li>HTTP заголовки</li>';
echo '<li>Логи приложения</li>';
echo '</ul>';

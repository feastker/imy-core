<?php

namespace Imy\Core;

class Debug
{
    private static $queries = [];
    private static $connections = 0;
    private static $start_time;
    private static $memory_start;
    private static $enabled = false;
    private static $headers = [];
    private static $logs = [];
    private static $request_data = [];
    private static $performance_data = [];
    private static $errors = [];
    private static $includes = [];
    
    public static function init()
    {
        self::$start_time = microtime(true);
        self::$memory_start = memory_get_usage();
        self::$enabled = true;
        
        // Собираем данные о запросе
        self::collectRequestData();
        
        // Собираем данные о производительности
        self::collectPerformanceData();
        
        // Собираем данные об ошибках
        self::collectErrorData();
        
        // Собираем данные о подключенных файлах
        self::collectIncludeData();
        
        // Регистрируем shutdown функцию для вывода панели
        register_shutdown_function([self::class, 'renderDebugPanel']);
    }
    
    private static function collectRequestData()
    {
        self::$request_data = [
            'GET' => $_GET,
            'POST' => $_POST,
            'COOKIE' => $_COOKIE,
            'SERVER' => array_filter($_SERVER, function($key) {
                // Фильтруем только важные SERVER переменные
                return in_array($key, [
                    'REQUEST_METHOD', 'REQUEST_URI', 'HTTP_HOST', 'HTTP_USER_AGENT',
                    'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING',
                    'HTTP_CONNECTION', 'HTTP_UPGRADE_INSECURE_REQUESTS', 'HTTP_CACHE_CONTROL',
                    'HTTP_PRAGMA', 'HTTP_DNT', 'HTTP_REFERER', 'HTTP_X_FORWARDED_FOR',
                    'HTTP_X_FORWARDED_PROTO', 'HTTP_X_REAL_IP', 'SERVER_NAME', 'SERVER_PORT',
                    'SERVER_PROTOCOL', 'REQUEST_TIME', 'REQUEST_TIME_FLOAT', 'REMOTE_ADDR',
                    'REMOTE_PORT', 'HTTPS', 'SCRIPT_NAME', 'PATH_INFO', 'QUERY_STRING'
                ]);
            }, ARRAY_FILTER_USE_KEY)
        ];
    }
    
    private static function collectPerformanceData()
    {
        self::$performance_data = [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'date_timezone' => date_default_timezone_get(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'load_time' => microtime(true) - self::$start_time,
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'included_files_count' => count(get_included_files()),
            'declared_classes_count' => count(get_declared_classes()),
            'declared_functions_count' => count(get_defined_functions()['user']),
            'declared_constants_count' => count(get_defined_constants()),
        ];
    }
    
    private static function collectErrorData()
    {
        self::$errors = [
            'php_errors' => error_get_last(),
            'warnings' => [],
            'notices' => [],
            'deprecated' => [],
        ];
    }
    
    private static function collectIncludeData()
    {
        $included_files = get_included_files();
        self::$includes = [
            'total_files' => count($included_files),
            'files' => array_map(function($file) {
                return [
                    'path' => $file,
                    'size' => file_exists($file) ? filesize($file) : 0,
                    'modified' => file_exists($file) ? filemtime($file) : 0,
                ];
            }, $included_files),
            'total_size' => array_sum(array_map(function($file) {
                return file_exists($file) ? filesize($file) : 0;
            }, $included_files)),
        ];
    }
    
    public static function logQuery($sql, $time, $connection_name = 'default')
    {
        if (!self::$enabled) return;
        
        self::$queries[] = [
            'sql' => $sql,
            'time' => $time,
            'connection' => $connection_name,
            'timestamp' => microtime(true)
        ];
    }
    
    public static function incrementConnections()
    {
        if (!self::$enabled) return;
        
        self::$connections++;
    }
    
    public static function addHeader($name, $value)
    {
        if (!self::$enabled) return;
        
        self::$headers[$name] = $value;
    }
    
    public static function log($message, $level = 'info')
    {
        if (!self::$enabled) return;
        
        self::$logs[] = [
            'message' => $message,
            'level' => $level,
            'timestamp' => microtime(true)
        ];
    }
    
    public static function getTotalTime()
    {
        return microtime(true) - self::$start_time;
    }
    
    public static function getTotalMemory()
    {
        return memory_get_usage() - self::$memory_start;
    }
    
    public static function getPeakMemory()
    {
        return memory_get_peak_usage();
    }
    
    public static function getQueries()
    {
        return self::$queries;
    }
    
    public static function getConnections()
    {
        return self::$connections;
    }
    
    public static function getHeaders()
    {
        return self::$headers;
    }
    
    public static function getLogs()
    {
        return self::$logs;
    }
    
    public static function getRequestData()
    {
        return self::$request_data;
    }
    
    public static function getPerformanceData()
    {
        return self::$performance_data;
    }
    
    public static function getErrors()
    {
        return self::$errors;
    }
    
    public static function getIncludes()
    {
        return self::$includes;
    }
    
    public static function renderDebugPanel()
    {
        if (!self::$enabled || Core::$ajax) return;
        
        $total_time = self::getTotalTime();
        $total_memory = self::getTotalMemory();
        $peak_memory = self::getPeakMemory();
        $queries = self::getQueries();
        $connections = self::getConnections();
        $headers = self::getHeaders();
        $logs = self::getLogs();
        $request_data = self::getRequestData();
        $performance_data = self::getPerformanceData();
        $errors = self::getErrors();
        $includes = self::getIncludes();
        
        $queries_time = array_sum(array_column($queries, 'time'));
        
        echo self::renderDebugHTML($total_time, $total_memory, $peak_memory, $queries, $queries_time, $connections, $headers, $logs, $request_data, $performance_data, $errors, $includes);
    }
    
    private static function renderDebugHTML($total_time, $total_memory, $peak_memory, $queries, $queries_time, $connections, $headers, $logs, $request_data, $performance_data, $errors, $includes)
    {
        $debug_id = 'imy-debug-' . uniqid();
        
        $html = '
        <!-- Дебаг иконка -->
        <div id="' . $debug_id . '-icon" style="
            position: fixed;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #007cba, #0056b3);
            border-radius: 8px 0 0 0;
            cursor: pointer;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 -2px 8px rgba(0, 124, 186, 0.3);
            transition: all 0.3s ease;
            font-size: 16px;
            color: white;
            font-weight: bold;
        " onmouseover="this.style.transform=\'scale(1.05)\'; this.style.boxShadow=\'0 -4px 12px rgba(0, 124, 186, 0.4)\'" onmouseout="this.style.transform=\'scale(1)\'; this.style.boxShadow=\'0 -2px 8px rgba(0, 124, 186, 0.3)\'" onclick="toggleDebugPanel(\'' . $debug_id . '\')">
            🔧
        </div>
        
        <!-- Основная панель -->
        <div id="' . $debug_id . '" style="
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e1e1e, #2d2d2d);
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
            font-size: 13px;
            z-index: 9999;
            height: 400px;
            overflow: hidden;
            border-top: 3px solid #007cba;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            display: none;
            resize: vertical;
            min-height: 200px;
            max-height: 80vh;
        ">
            <div style="
                background: linear-gradient(135deg, #007cba, #0056b3);
                padding: 12px 20px;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            " onclick="toggleDebugPanel(\'' . $debug_id . '\')">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 16px;">🔧</span>
                    <strong style="font-size: 16px;">IMY Debug Panel</strong>
                </div>
                <div style="display: flex; gap: 20px; font-size: 12px; opacity: 0.9;">
                    <span>⏱️ ' . number_format($total_time * 1000, 2) . 'ms</span>
                    <span>💾 ' . self::formatBytes($total_memory) . '</span>
                    <span>📈 ' . self::formatBytes($peak_memory) . '</span>
                    <span>🗄️ ' . count($queries) . ' (' . number_format($queries_time * 1000, 2) . 'ms)</span>
                    <span>🔗 ' . $connections . '</span>
                </div>
            </div>
            
            <div id="' . $debug_id . '-content" style="display: none; padding: 0; max-height: 60vh; overflow: hidden;">
                <!-- Табы -->
                <div class="debug-tabs">
                    <button class="debug-tab active" onclick="switchDebugTab(\'' . $debug_id . '\', \'overview\')">
                        <span class="debug-tab-icon">📊</span>
                        Обзор
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'queries\')">
                        <span class="debug-tab-icon">🗄️</span>
                        SQL (' . count($queries) . ')
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'request\')">
                        <span class="debug-tab-icon">🌐</span>
                        Переменные
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'logs\')">
                        <span class="debug-tab-icon">📝</span>
                        Логи (' . count($logs) . ')
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'performance\')">
                        <span class="debug-tab-icon">⚡</span>
                        Производительность
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'files\')">
                        <span class="debug-tab-icon">📁</span>
                        Файлы (' . $includes['total_files'] . ')
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'errors\')">
                        <span class="debug-tab-icon">⚠️</span>
                        Ошибки
                    </button>
                </div>
                
                <!-- Контент табов -->
                <div class="debug-tab-content">
                    <!-- Обзор -->
                    <div id="' . $debug_id . '-tab-overview" class="debug-tab-panel active">
                        <div class="debug-overview-grid">
                            <div class="debug-overview-card">
                                <div class="debug-overview-icon">⏱️</div>
                                <div class="debug-overview-content">
                                    <div class="debug-overview-label">Время выполнения</div>
                                    <div class="debug-overview-value">' . number_format($total_time * 1000, 2) . 'ms</div>
                                </div>
                            </div>
                            <div class="debug-overview-card">
                                <div class="debug-overview-icon">💾</div>
                                <div class="debug-overview-content">
                                    <div class="debug-overview-label">Память</div>
                                    <div class="debug-overview-value">' . self::formatBytes($total_memory) . '</div>
                                </div>
                            </div>
                            <div class="debug-overview-card">
                                <div class="debug-overview-icon">📈</div>
                                <div class="debug-overview-content">
                                    <div class="debug-overview-label">Пик памяти</div>
                                    <div class="debug-overview-value">' . self::formatBytes($peak_memory) . '</div>
                                </div>
                            </div>
                            <div class="debug-overview-card">
                                <div class="debug-overview-icon">🗄️</div>
                                <div class="debug-overview-content">
                                    <div class="debug-overview-label">SQL запросов</div>
                                    <div class="debug-overview-value">' . count($queries) . '</div>
                                </div>
                            </div>
                            <div class="debug-overview-card">
                                <div class="debug-overview-icon">⏰</div>
                                <div class="debug-overview-content">
                                    <div class="debug-overview-label">Время SQL</div>
                                    <div class="debug-overview-value">' . number_format($queries_time * 1000, 2) . 'ms</div>
                                </div>
                            </div>
                            <div class="debug-overview-card">
                                <div class="debug-overview-icon">🔗</div>
                                <div class="debug-overview-content">
                                    <div class="debug-overview-label">Соединений</div>
                                    <div class="debug-overview-value">' . $connections . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SQL запросы -->
                    <div id="' . $debug_id . '-tab-queries" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">';
        
        if (empty($queries)) {
            $html .= '<div class="debug-empty">Нет SQL запросов</div>';
        } else {
            foreach ($queries as $i => $query) {
                $time_color = $query['time'] > 0.1 ? '#ff6b6b' : ($query['time'] > 0.05 ? '#ffa726' : '#4caf50');
                $html .= '<div class="debug-query">
                    <div class="debug-query-header">
                        <span class="debug-query-number">#' . ($i + 1) . '</span>
                        <span class="debug-query-time" style="color: ' . $time_color . ';">' . number_format($query['time'] * 1000, 2) . 'ms</span>
                        <span class="debug-query-connection">' . $query['connection'] . '</span>
                    </div>
                    <div class="debug-query-sql">' . htmlspecialchars($query['sql']) . '</div>
                </div>';
            }
        }
        
        $html .= '</div>
                    </div>
                    
                    <!-- Данные запроса -->
                    <div id="' . $debug_id . '-tab-request" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">
                            <div class="debug-request-sections">';
        
        // GET данные
        if (!empty($request_data['GET'])) {
            $html .= '<div class="debug-request-section">
                <div class="debug-request-header">GET параметры</div>
                <div class="debug-request-content">';
            foreach ($request_data['GET'] as $key => $value) {
                $html .= '<div class="debug-request-item">
                    <span class="debug-request-key">' . htmlspecialchars($key) . '</span>
                    <span class="debug-request-value">' . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . '</span>
                </div>';
            }
            $html .= '</div></div>';
        }
        
        // POST данные
        if (!empty($request_data['POST'])) {
            $html .= '<div class="debug-request-section">
                <div class="debug-request-header">POST параметры</div>
                <div class="debug-request-content">';
            foreach ($request_data['POST'] as $key => $value) {
                $html .= '<div class="debug-request-item">
                    <span class="debug-request-key">' . htmlspecialchars($key) . '</span>
                    <span class="debug-request-value">' . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . '</span>
                </div>';
            }
            $html .= '</div></div>';
        }
        
        // COOKIE данные
        if (!empty($request_data['COOKIE'])) {
            $html .= '<div class="debug-request-section">
                <div class="debug-request-header">COOKIE</div>
                <div class="debug-request-content">';
            foreach ($request_data['COOKIE'] as $key => $value) {
                $html .= '<div class="debug-request-item">
                    <span class="debug-request-key">' . htmlspecialchars($key) . '</span>
                    <span class="debug-request-value">' . htmlspecialchars($value) . '</span>
                </div>';
            }
            $html .= '</div></div>';
        }
        
        // SERVER данные
        if (!empty($request_data['SERVER'])) {
            $html .= '<div class="debug-request-section">
                <div class="debug-request-header">SERVER переменные</div>
                <div class="debug-request-content">';
            foreach ($request_data['SERVER'] as $key => $value) {
                $html .= '<div class="debug-request-item">
                    <span class="debug-request-key">' . htmlspecialchars($key) . '</span>
                    <span class="debug-request-value">' . htmlspecialchars($value) . '</span>
                </div>';
            }
            $html .= '</div></div>';
        }
        
        // HTTP заголовки
        if (!empty($headers)) {
            $html .= '<div class="debug-request-section">
                <div class="debug-request-header">HTTP заголовки</div>
                <div class="debug-request-content">';
            foreach ($headers as $name => $value) {
                $html .= '<div class="debug-request-item">
                    <span class="debug-request-key">' . htmlspecialchars($name) . '</span>
                    <span class="debug-request-value">' . htmlspecialchars($value) . '</span>
                </div>';
            }
            $html .= '</div></div>';
        }
        
        $html .= '</div>
                        </div>
                    </div>
                    
                    <!-- Логи -->
                    <div id="' . $debug_id . '-tab-logs" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">';
        
        if (empty($logs)) {
            $html .= '<div class="debug-empty">Нет логов</div>';
        } else {
            foreach ($logs as $log) {
                $color = '#fff';
                $icon = 'ℹ️';
                switch ($log['level']) {
                    case 'error':
                        $color = '#ff6b6b';
                        $icon = '❌';
                        break;
                    case 'warning':
                        $color = '#ffa726';
                        $icon = '⚠️';
                        break;
                    case 'info':
                        $color = '#42a5f5';
                        $icon = 'ℹ️';
                        break;
                }
                $html .= '<div class="debug-log-item" style="color: ' . $color . ';">
                    <span class="debug-log-icon">' . $icon . '</span>
                    <span class="debug-log-level">[' . $log['level'] . ']</span>
                    <span class="debug-log-message">' . htmlspecialchars($log['message']) . '</span>
                </div>';
            }
        }
        
        $html .= '</div>
                    </div>
                    
                    <!-- Производительность -->
                    <div id="' . $debug_id . '-tab-performance" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">
                            <div class="debug-performance-grid">
                                <div class="debug-performance-section">
                                    <div class="debug-performance-header">PHP Информация</div>
                                    <div class="debug-performance-content">
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Версия PHP:</span>
                                            <span class="debug-performance-value">' . $performance_data['php_version'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">SAPI:</span>
                                            <span class="debug-performance-value">' . $performance_data['php_sapi'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Лимит памяти:</span>
                                            <span class="debug-performance-value">' . $performance_data['memory_limit'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Время выполнения:</span>
                                            <span class="debug-performance-value">' . $performance_data['max_execution_time'] . 's</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Часовой пояс:</span>
                                            <span class="debug-performance-value">' . $performance_data['date_timezone'] . '</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="debug-performance-section">
                                    <div class="debug-performance-header">Сервер</div>
                                    <div class="debug-performance-content">
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Серверное ПО:</span>
                                            <span class="debug-performance-value">' . htmlspecialchars($performance_data['server_software']) . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">IP адрес:</span>
                                            <span class="debug-performance-value">' . $performance_data['remote_addr'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Порт:</span>
                                            <span class="debug-performance-value">' . $performance_data['server_port'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">HTTPS:</span>
                                            <span class="debug-performance-value">' . ($performance_data['https'] ? 'Да' : 'Нет') . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Метод:</span>
                                            <span class="debug-performance-value">' . $performance_data['http_method'] . '</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="debug-performance-section">
                                    <div class="debug-performance-header">Статистика</div>
                                    <div class="debug-performance-content">
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Подключенных файлов:</span>
                                            <span class="debug-performance-value">' . $performance_data['included_files_count'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Классов:</span>
                                            <span class="debug-performance-value">' . $performance_data['declared_classes_count'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Функций:</span>
                                            <span class="debug-performance-value">' . $performance_data['declared_functions_count'] . '</span>
                                        </div>
                                        <div class="debug-performance-item">
                                            <span class="debug-performance-label">Констант:</span>
                                            <span class="debug-performance-value">' . $performance_data['declared_constants_count'] . '</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Файлы -->
                    <div id="' . $debug_id . '-tab-files" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">
                            <div class="debug-files-header">
                                <span>Всего файлов: ' . $includes['total_files'] . '</span>
                                <span>Общий размер: ' . self::formatBytes($includes['total_size']) . '</span>
                            </div>
                            <div class="debug-files-list">';
        
        foreach ($includes['files'] as $file) {
            $file_name = basename($file['path']);
            $file_size = self::formatBytes($file['size']);
            $file_modified = date('Y-m-d H:i:s', $file['modified']);
            
            $html .= '<div class="debug-file-item">
                <div class="debug-file-name">' . htmlspecialchars($file_name) . '</div>
                <div class="debug-file-info">
                    <span class="debug-file-size">' . $file_size . '</span>
                    <span class="debug-file-date">' . $file_modified . '</span>
                </div>
                <div class="debug-file-path">' . htmlspecialchars($file['path']) . '</div>
            </div>';
        }
        
        $html .= '</div>
                        </div>
                    </div>
                    
                    <!-- Ошибки -->
                    <div id="' . $debug_id . '-tab-errors" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">';
        
        if ($errors['php_errors']) {
            $html .= '<div class="debug-error-section">
                <div class="debug-error-header">Последняя PHP ошибка</div>
                <div class="debug-error-content">
                    <div class="debug-error-item">
                        <span class="debug-error-type">' . $errors['php_errors']['type'] . '</span>
                        <span class="debug-error-message">' . htmlspecialchars($errors['php_errors']['message']) . '</span>
                    </div>
                    <div class="debug-error-file">' . htmlspecialchars($errors['php_errors']['file']) . ':' . $errors['php_errors']['line'] . '</div>
                </div>
            </div>';
        } else {
            $html .= '<div class="debug-empty">Нет ошибок</div>';
        }
        
        $html .= '</div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* Табы */
        .debug-tabs {
            display: flex;
            background: linear-gradient(135deg, #1e1e1e, #2d2d2d);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            width: fit-content;
            margin: 0 auto;
        }
        
        .debug-tab {
            background: transparent;
            border: none;
            color: #aaa;
            padding: 12px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        
        .debug-tab:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        
        .debug-tab.active {
            background: linear-gradient(135deg, #007cba, #0056b3);
            color: #fff;
            border-bottom-color: #fff;
        }
        
        .debug-tab-icon {
            font-size: 14px;
        }
        
        .debug-tab-content {
            background: #2d2d2d;
            height: calc(100% - 60px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .debug-tab-panel {
            display: none;
            height: 100%;
            overflow-y: auto;
            flex: 1;
        }
        
        .debug-tab-panel.active {
            display: block;
        }
        
        .debug-tab-content-inner {
            padding: 20px;
            height: 100%;
            overflow-y: auto;
        }
        
        /* Обзор */
        .debug-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .debug-overview-card {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.2s ease;
        }
        
        .debug-overview-card:hover {
            transform: translateY(-2px);
        }
        
        .debug-overview-icon {
            font-size: 24px;
            min-width: 40px;
            text-align: center;
        }
        
        .debug-overview-content {
            flex: 1;
        }
        
        .debug-overview-label {
            color: #aaa;
            font-size: 11px;
            margin-bottom: 4px;
        }
        
        .debug-overview-value {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
        }
        
        /* Данные запроса */
        .debug-request-sections {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .debug-request-section {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .debug-request-header {
            background: linear-gradient(135deg, #007cba, #0056b3);
            padding: 12px 16px;
            font-weight: 600;
            color: #fff;
            font-size: 13px;
        }
        
        .debug-request-content {
            padding: 16px;
        }
        
        .debug-request-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .debug-request-item:last-child {
            border-bottom: none;
        }
        
        .debug-request-key {
            color: #007cba;
            font-weight: 600;
            font-size: 11px;
            min-width: 120px;
        }
        
        .debug-request-value {
            color: #fff;
            font-size: 11px;
            word-break: break-all;
            text-align: right;
        }
        
        .debug-section {
            background: linear-gradient(135deg, #2d2d2d, #3d3d3d);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .debug-header {
            background: linear-gradient(135deg, #007cba, #0056b3);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: white;
        }
        
        .debug-icon {
            font-size: 16px;
        }
        
        .debug-content {
            padding: 16px;
            background: #2d2d2d;
        }
        
        .debug-scrollable {
            max-height: 250px;
            overflow-y: auto;
        }
        
        .debug-scrollable::-webkit-scrollbar {
            width: 6px;
        }
        
        .debug-scrollable::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 3px;
        }
        
        .debug-scrollable::-webkit-scrollbar-thumb {
            background: #007cba;
            border-radius: 3px;
        }
        
        .debug-scrollable::-webkit-scrollbar-thumb:hover {
            background: #0056b3;
        }
        
        .debug-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .debug-item:last-child {
            border-bottom: none;
        }
        
        .debug-label {
            color: #aaa;
            font-size: 12px;
        }
        
        .debug-value {
            color: #fff;
            font-weight: 600;
            font-size: 12px;
        }
        
        .debug-empty {
            color: #888;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        
        .debug-query {
            background: #3d3d3d;
            border-radius: 6px;
            margin-bottom: 8px;
            border-left: 3px solid #007cba;
            overflow: hidden;
        }
        
        .debug-query-header {
            background: #4d4d4d;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
        }
        
        .debug-query-number {
            color: #007cba;
            font-weight: bold;
        }
        
        .debug-query-time {
            font-weight: bold;
        }
        
        .debug-query-connection {
            color: #aaa;
        }
        
        .debug-query-sql {
            padding: 12px;
            font-family: \'Courier New\', monospace;
            font-size: 11px;
            color: #fff;
            word-break: break-all;
            line-height: 1.4;
        }
        
        .debug-header-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .debug-header-item:last-child {
            border-bottom: none;
        }
        
        .debug-header-name {
            color: #007cba;
            font-weight: 600;
            font-size: 11px;
        }
        
        .debug-header-value {
            color: #fff;
            font-size: 11px;
        }
        
        .debug-log-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 11px;
        }
        
        .debug-log-item:last-child {
            border-bottom: none;
        }
        
        .debug-log-icon {
            font-size: 12px;
        }
        
        .debug-log-level {
            font-weight: bold;
            min-width: 60px;
        }
        
        .debug-log-message {
            flex: 1;
        }
        
        /* Производительность */
        .debug-performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .debug-performance-section {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .debug-performance-header {
            background: linear-gradient(135deg, #007cba, #0056b3);
            padding: 12px 16px;
            font-weight: 600;
            color: #fff;
            font-size: 13px;
        }
        
        .debug-performance-content {
            padding: 16px;
        }
        
        .debug-performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .debug-performance-item:last-child {
            border-bottom: none;
        }
        
        .debug-performance-label {
            color: #007cba;
            font-weight: 600;
            font-size: 11px;
        }
        
        .debug-performance-value {
            color: #fff;
            font-size: 11px;
            word-break: break-all;
            text-align: right;
        }
        
        /* Файлы */
        .debug-files-header {
            background: linear-gradient(135deg, #007cba, #0056b3);
            padding: 12px 16px;
            margin: -20px -20px 20px -20px;
            color: #fff;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
        }
        
        .debug-files-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .debug-file-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-file-name {
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .debug-file-info {
            display: flex;
            gap: 12px;
            margin-bottom: 4px;
        }
        
        .debug-file-size {
            color: #007cba;
            font-size: 11px;
            font-weight: 600;
        }
        
        .debug-file-date {
            color: #aaa;
            font-size: 11px;
        }
        
        .debug-file-path {
            color: #888;
            font-size: 10px;
            word-break: break-all;
        }
        
        /* Ошибки */
        .debug-error-section {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .debug-error-header {
            background: linear-gradient(135deg, #ff6b6b, #e53e3e);
            padding: 12px 16px;
            font-weight: 600;
            color: #fff;
            font-size: 13px;
        }
        
        .debug-error-content {
            padding: 16px;
        }
        
        .debug-error-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .debug-error-type {
            background: #ff6b6b;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .debug-error-message {
            color: #fff;
            font-size: 12px;
            flex: 1;
        }
        
        .debug-error-file {
            color: #aaa;
            font-size: 11px;
            font-family: monospace;
        }
        </style>
        
        <script>
        // Загружаем сохраненное состояние панели
        function loadDebugPanelState(debugId) {
            const savedState = localStorage.getItem(\'imy-debug-panel-state\');
            const savedTab = localStorage.getItem(\'imy-debug-panel-tab\');
            
            if (savedState === \'open\') {
                const panel = document.getElementById(debugId);
                const content = document.getElementById(debugId + "-content");
                const icon = document.getElementById(debugId + "-icon");
                
                panel.style.display = "block";
                content.style.display = "block";
                icon.style.display = "none";
                
                if (savedTab) {
                    switchDebugTab(debugId, savedTab, false);
                }
            }
        }
        
        function toggleDebugPanel(debugId) {
            const panel = document.getElementById(debugId);
            const content = document.getElementById(debugId + "-content");
            const icon = document.getElementById(debugId + "-icon");
            
            if (panel.style.display === "none" || panel.style.display === "") {
                panel.style.display = "block";
                content.style.display = "block";
                icon.style.display = "none";
                localStorage.setItem(\'imy-debug-panel-state\', \'open\');
            } else {
                panel.style.display = "none";
                content.style.display = "none";
                icon.style.display = "flex";
                localStorage.setItem(\'imy-debug-panel-state\', \'closed\');
            }
        }
        
        function switchDebugTab(debugId, tabName, saveState = true) {
            // Скрываем все панели
            const panels = document.querySelectorAll(\'#\' + debugId + \'-tab-overview, #\' + debugId + \'-tab-queries, #\' + debugId + \'-tab-request, #\' + debugId + \'-tab-logs, #\' + debugId + \'-tab-performance, #\' + debugId + \'-tab-files, #\' + debugId + \'-tab-errors\');
            panels.forEach(panel => panel.classList.remove(\'active\'));
            
            // Убираем активный класс со всех табов
            const tabs = document.querySelectorAll(\'#\' + debugId + \' .debug-tab\');
            tabs.forEach(tab => tab.classList.remove(\'active\'));
            
            // Показываем нужную панель
            const targetPanel = document.getElementById(debugId + \'-tab-\' + tabName);
            if (targetPanel) {
                targetPanel.classList.add(\'active\');
            }
            
            // Активируем нужный таб
            const targetTab = event ? event.target.closest(\'.debug-tab\') : document.querySelector(\'#\' + debugId + \' .debug-tab[onclick*="\' + tabName + \'"]\');
            if (targetTab) {
                targetTab.classList.add(\'active\');
            }
            
            // Сохраняем выбранный таб
            if (saveState) {
                localStorage.setItem(\'imy-debug-panel-tab\', tabName);
            }
        }
        
        // Закрытие панели по клику вне её
        document.addEventListener(\'click\', function(event) {
            const debugPanels = document.querySelectorAll(\'[id^="imy-debug-"]\');
            debugPanels.forEach(panel => {
                if (!panel.contains(event.target) && !event.target.id.includes(\'imy-debug-\')) {
                    const panelId = panel.id;
                    if (panelId.includes(\'-\') && !panelId.includes(\'icon\') && !panelId.includes(\'content\')) {
                        const icon = document.getElementById(panelId + \'-icon\');
                        if (icon) {
                            panel.style.display = \'none\';
                            icon.style.display = \'flex\';
                        }
                    }
                }
            });
        });
        
        // Загружаем состояние панели при загрузке страницы
        document.addEventListener(\'DOMContentLoaded\', function() {
            const debugPanels = document.querySelectorAll(\'[id^="imy-debug-"]\');
            debugPanels.forEach(panel => {
                if (!panel.id.includes(\'icon\') && !panel.id.includes(\'content\')) {
                    loadDebugPanelState(panel.id);
                }
            });
        });
        </script>';
        
        return $html;
    }
    
    private static function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

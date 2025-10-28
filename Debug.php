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
    private static $timing_points = [];
    
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
        
        // Добавляем начальную точку профилирования
        self::addTimingPoint('init', 'Инициализация дебаг панели');
        
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
        
        // Анализируем запрос для определения недостающих индексов
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            IndexAnalyzer::analyzeQuery($sql, $connection_name);
            
            // Обновляем данные производительности с реальным временем выполнения
            $performance_data = IndexAnalyzer::analyzeQueryPerformance($sql, $time, $connection_name);
        }
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
    
    
    public static function getErrors()
    {
        return self::$errors;
    }
    
    public static function getIncludes()
    {
        return self::$includes;
    }
    
    public static function addTimingPoint($name, $description = '')
    {
        if (!self::$enabled) return;
        
        $current_time = microtime(true);
        $memory_usage = memory_get_usage();
        
        self::$timing_points[] = [
            'name' => $name,
            'description' => $description,
            'time' => $current_time,
            'time_from_start' => $current_time - self::$start_time,
            'memory' => $memory_usage,
            'memory_from_start' => $memory_usage - self::$memory_start,
            'memory_peak' => memory_get_peak_usage()
        ];
    }
    
    public static function getTimingPoints()
    {
        return self::$timing_points;
    }
    
    public static function getIndexRecommendations()
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getIndexRecommendations();
        }
        return [];
    }
    
    public static function getAllIndexRecommendations()
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getAllIndexRecommendations();
        }
        return [];
    }
    
    public static function getExistingIndexes()
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getExistingIndexes();
        }
        return [];
    }
    
    /**
     * Получает результаты EXPLAIN для запроса
     */
    public static function getExplainResults($sql, $connection_name = 'default')
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getExplainResults($sql, $connection_name);
        }
        return null;
    }
    
    /**
     * Включает или отключает анализ EXPLAIN
     */
    public static function setExplainEnabled($enabled)
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            IndexAnalyzer::setExplainEnabled($enabled);
        }
    }
    
    /**
     * Устанавливает порог для детекции медленных запросов
     */
    public static function setSlowQueryThreshold($threshold_ms)
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            IndexAnalyzer::setSlowQueryThreshold($threshold_ms);
        }
    }
    
    /**
     * Получает данные производительности
     */
    public static function getPerformanceData($grouped = true)
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getPerformanceData($grouped);
        }
        return [];
    }
    
    /**
     * Получает медленные запросы
     */
    public static function getSlowQueries($limit = 10)
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getSlowQueries($limit);
        }
        return [];
    }
    
    /**
     * Получает статистику производительности
     */
    public static function getPerformanceStats()
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getPerformanceStats();
        }
        return [
            'total_queries' => 0,
            'total_time' => 0,
            'avg_time' => 0,
            'min_time' => 0,
            'max_time' => 0,
            'slow_queries' => 0,
            'slow_query_percentage' => 0,
            'unique_queries' => 0,
            'slow_query_threshold' => 1000
        ];
    }
    
    /**
     * Получает тренды производительности
     */
    public static function getPerformanceTrends($time_window = 3600)
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::analyzePerformanceTrends($time_window);
        }
        return [];
    }
    
    /**
     * Получает рекомендации по производительности
     */
    public static function getPerformanceRecommendations()
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::generatePerformanceRecommendations();
        }
        return [];
    }
    
    public static function getIndexStats()
    {
        if (class_exists('Imy\Core\IndexAnalyzer')) {
            return IndexAnalyzer::getStats();
        }
        return [
            'analyzed_queries' => 0,
            'total_recommendations' => 0,
            'missing_recommendations' => 0,
            'existing_recommendations' => 0,
            'high_priority_missing' => 0,
            'medium_priority_missing' => 0,
            'existing_indexes_count' => 0
        ];
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
        $timing_points = self::getTimingPoints();
        $index_recommendations = self::getIndexRecommendations();
        $index_stats = self::getIndexStats();
        $performance_stats = self::getPerformanceStats();
        $slow_queries = self::getSlowQueries();
        $performance_trends = self::getPerformanceTrends();
        $performance_recommendations = self::getPerformanceRecommendations();
        
        $queries_time = array_sum(array_column($queries, 'time'));
        
        echo self::renderDebugHTML($total_time, $total_memory, $peak_memory, $queries, $queries_time, $connections, $headers, $logs, $request_data, $performance_data, $errors, $includes, $timing_points, $index_recommendations, $index_stats, $performance_stats, $slow_queries, $performance_trends, $performance_recommendations);
    }
    
    private static function renderDebugHTML($total_time, $total_memory, $peak_memory, $queries, $queries_time, $connections, $headers, $logs, $request_data, $performance_data, $errors, $includes, $timing_points, $index_recommendations, $index_stats, $performance_stats, $slow_queries, $performance_trends, $performance_recommendations)
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
            border-radius: 8px 8px 0 0;
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
            💻
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
            <!-- Ручка для изменения размера -->
            <div class="debug-resize-handle" style="
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: #007cba;
                cursor: ns-resize;
                z-index: 10001;
            "></div>
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
            
            <div id="' . $debug_id . '-content" style="display: none; padding: 0; height: calc(100% - 60px); overflow: hidden;">
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
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'timing\')">
                        <span class="debug-tab-icon">⏱️</span>
                        Профилирование
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'indexes\')">
                        <span class="debug-tab-icon">📊</span>
                        Индексы (' . $index_stats['missing_recommendations'] . '/' . $index_stats['existing_indexes_count'] . ')
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'performance\')">
                        <span class="debug-tab-icon">⚡</span>
                        Производительность (' . $performance_stats['slow_queries'] . ')
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
                    
                    <!-- Профилирование -->
                    <div id="' . $debug_id . '-tab-timing" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">
                            <div class="debug-timing-header">
                                <span>Точки профилирования: ' . count($timing_points) . '</span>
                                <span>Общее время: ' . number_format($total_time * 1000, 2) . 'ms</span>
                            </div>
                            <div class="debug-timing-list">';
        
        if (empty($timing_points)) {
            $html .= '<div class="debug-empty">Нет точек профилирования</div>';
        } else {
            $previous_time = 0;
            foreach ($timing_points as $index => $point) {
                $time_diff = $index > 0 ? $point['time_from_start'] - $previous_time : $point['time_from_start'];
                $memory_diff = $index > 0 ? $point['memory_from_start'] - $timing_points[$index-1]['memory_from_start'] : $point['memory_from_start'];
                
                $time_color = $time_diff > 0.1 ? '#ff6b6b' : ($time_diff > 0.05 ? '#ffa726' : '#4caf50');
                $memory_color = $memory_diff > 1024*1024 ? '#ff6b6b' : ($memory_diff > 512*1024 ? '#ffa726' : '#4caf50');
                
                $html .= '<div class="debug-timing-item">
                    <div class="debug-timing-header-item">
                        <span class="debug-timing-name">' . htmlspecialchars($point['name']) . '</span>
                        <span class="debug-timing-time" style="color: ' . $time_color . '">+' . number_format($time_diff * 1000, 2) . 'ms</span>
                    </div>
                    <div class="debug-timing-description">' . htmlspecialchars($point['description']) . '</div>
                    <div class="debug-timing-details">
                        <span class="debug-timing-total">Общее время: ' . number_format($point['time_from_start'] * 1000, 2) . 'ms</span>
                        <span class="debug-timing-memory" style="color: ' . $memory_color . '">Память: ' . self::formatBytes($point['memory_from_start']) . '</span>
                        <span class="debug-timing-peak">Пик: ' . self::formatBytes($point['memory_peak']) . '</span>
                    </div>
                </div>';
                
                $previous_time = $point['time_from_start'];
            }
        }
        
        $html .= '</div>
                        </div>
                    </div>
                    
                    <!-- Индексы -->
                    <div id="' . $debug_id . '-tab-indexes" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">
                            <div class="debug-indexes-header">
                                <span>Недостающие индексы: ' . $index_stats['missing_recommendations'] . ' | Существующие: ' . $index_stats['existing_indexes_count'] . '</span>
                                <span>Проанализировано запросов: ' . $index_stats['analyzed_queries'] . '</span>
                            </div>
                            <div class="debug-indexes-stats">
                                <div class="debug-indexes-stat-item">
                                    <span class="debug-indexes-stat-label">Недостающие (высокий):</span>
                                    <span class="debug-indexes-stat-value high-priority">' . $index_stats['high_priority_missing'] . '</span>
                                </div>
                                <div class="debug-indexes-stat-item">
                                    <span class="debug-indexes-stat-label">Недостающие (средний):</span>
                                    <span class="debug-indexes-stat-value medium-priority">' . $index_stats['medium_priority_missing'] . '</span>
                                </div>
                                <div class="debug-indexes-stat-item">
                                    <span class="debug-indexes-stat-label">Существующие:</span>
                                    <span class="debug-indexes-stat-value existing">' . $index_stats['existing_indexes_count'] . '</span>
                                </div>
                            </div>
                            <div class="debug-indexes-tabs">
                                <button class="debug-indexes-tab active" onclick="switchIndexTab(\'' . $debug_id . '\', \'missing\')">
                                    <span class="debug-indexes-tab-icon">❌</span>
                                    Недостающие (' . $index_stats['missing_recommendations'] . ')
                                </button>
                                <button class="debug-indexes-tab" onclick="switchIndexTab(\'' . $debug_id . '\', \'existing\')">
                                    <span class="debug-indexes-tab-icon">✅</span>
                                    Существующие (' . $index_stats['existing_indexes_count'] . ')
                                </button>
                                <button class="debug-indexes-tab" onclick="switchIndexTab(\'' . $debug_id . '\', \'all\')">
                                    <span class="debug-indexes-tab-icon">📊</span>
                                    Все (' . $index_stats['total_recommendations'] . ')
                                </button>
                            </div>
                            <div class="debug-indexes-content">
                                <div id="' . $debug_id . '-indexes-missing" class="debug-indexes-panel active">
                                    <div class="debug-indexes-list">';
        
        if (empty($index_recommendations)) {
            $html .= '<div class="debug-empty">Нет недостающих индексов</div>';
        } else {
            foreach ($index_recommendations as $recommendation) {
                $priority_class = $recommendation['priority'] === 'high' ? 'high-priority' : ($recommendation['priority'] === 'medium' ? 'medium-priority' : 'low-priority');
                $priority_icon = $recommendation['priority'] === 'high' ? '🔴' : ($recommendation['priority'] === 'medium' ? '🟡' : '🟢');
                
                $html .= '<div class="debug-index-item ' . $priority_class . '">
                    <div class="debug-index-header">
                        <span class="debug-index-priority">' . $priority_icon . '</span>
                        <span class="debug-index-table">' . htmlspecialchars($recommendation['table']) . '</span>
                        <span class="debug-index-type">' . ($recommendation['type'] === 'composite' ? 'Составной' : 'Одиночный') . '</span>
                        <span class="debug-index-usage">Использований: ' . $recommendation['usage_count'] . '</span>
                    </div>
                    <div class="debug-index-columns">
                        <strong>Колонки:</strong> ' . htmlspecialchars($recommendation['columns']) . '
                    </div>
                    <div class="debug-index-reason">
                        <strong>Причина:</strong> ' . htmlspecialchars($recommendation['reason']) . '
                    </div>
                    <div class="debug-index-sql">
                        <strong>SQL для создания:</strong><br>
                        <code>CREATE INDEX idx_' . strtolower($recommendation['table']) . '_' . str_replace(', ', '_', strtolower($recommendation['columns'])) . ' ON ' . $recommendation['table'] . ' (' . $recommendation['columns'] . ');</code>
                    </div>
                </div>';
            }
        }
        
        $html .= '</div>
                                </div>
                                
                                <div id="' . $debug_id . '-indexes-existing" class="debug-indexes-panel">
                                    <div class="debug-indexes-list">';
        
        // Получаем существующие индексы
        $existing_indexes = Debug::getExistingIndexes();
        if (empty($existing_indexes)) {
            $html .= '<div class="debug-empty">Нет информации о существующих индексах</div>';
        } else {
            foreach ($existing_indexes as $index) {
                $type_icon = $index['is_primary'] ? '🔑' : ($index['is_unique'] ? '🔒' : '📊');
                $type_text = $index['is_primary'] ? 'Первичный ключ' : ($index['is_unique'] ? 'Уникальный' : 'Обычный');
                
                $html .= '<div class="debug-index-item existing">
                    <div class="debug-index-header">
                        <span class="debug-index-priority">' . $type_icon . '</span>
                        <span class="debug-index-table">' . htmlspecialchars($index['table']) . '</span>
                        <span class="debug-index-type">' . $type_text . '</span>
                        <span class="debug-index-usage">' . $index['type'] . '</span>
                    </div>
                    <div class="debug-index-columns">
                        <strong>Колонки:</strong> ' . htmlspecialchars($index['columns']) . '
                    </div>
                    <div class="debug-index-reason">
                        <strong>Имя индекса:</strong> ' . htmlspecialchars($index['name']) . '
                    </div>
                </div>';
            }
        }
        
        $html .= '</div>
                                </div>
                                
                                <div id="' . $debug_id . '-indexes-all" class="debug-indexes-panel">
                                    <div class="debug-indexes-list">';
        
        // Получаем все рекомендации (включая существующие)
        $all_recommendations = Debug::getAllIndexRecommendations();
        if (empty($all_recommendations)) {
            $html .= '<div class="debug-empty">Нет рекомендаций по индексам</div>';
        } else {
            foreach ($all_recommendations as $recommendation) {
                $priority_class = $recommendation['priority'] === 'high' ? 'high-priority' : ($recommendation['priority'] === 'medium' ? 'medium-priority' : 'low-priority');
                $status_class = isset($recommendation['status']) && $recommendation['status'] === 'exists' ? 'existing' : 'missing';
                $priority_icon = $recommendation['priority'] === 'high' ? '🔴' : ($recommendation['priority'] === 'medium' ? '🟡' : '🟢');
                $status_icon = isset($recommendation['status']) && $recommendation['status'] === 'exists' ? '✅' : '❌';
                
                $html .= '<div class="debug-index-item ' . $priority_class . ' ' . $status_class . '">
                    <div class="debug-index-header">
                        <span class="debug-index-priority">' . $priority_icon . '</span>
                        <span class="debug-index-status">' . $status_icon . '</span>
                        <span class="debug-index-table">' . htmlspecialchars($recommendation['table']) . '</span>
                        <span class="debug-index-type">' . ($recommendation['type'] === 'composite' ? 'Составной' : 'Одиночный') . '</span>
                        <span class="debug-index-usage">Использований: ' . $recommendation['usage_count'] . '</span>
                    </div>
                    <div class="debug-index-columns">
                        <strong>Колонки:</strong> ' . htmlspecialchars($recommendation['columns']) . '
                    </div>
                    <div class="debug-index-reason">
                        <strong>Причина:</strong> ' . htmlspecialchars($recommendation['reason']) . '
                    </div>';
                
                if (isset($recommendation['status']) && $recommendation['status'] === 'exists' && isset($recommendation['existing_index'])) {
                    $html .= '<div class="debug-index-existing">
                        <strong>Существующий индекс:</strong> ' . htmlspecialchars($recommendation['existing_index']['name']) . ' (' . $recommendation['existing_index']['type'] . ')
                    </div>';
                } else {
                    $html .= '<div class="debug-index-sql">
                        <strong>SQL для создания:</strong><br>
                        <code>CREATE INDEX idx_' . strtolower($recommendation['table']) . '_' . str_replace(', ', '_', strtolower($recommendation['columns'])) . ' ON ' . $recommendation['table'] . ' (' . $recommendation['columns'] . ');</code>
                    </div>';
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '</div>
                        </div>
                    </div>
                    
                    <!-- Производительность -->
                    <div id="' . $debug_id . '-tab-performance" class="debug-tab-panel">
                        <div class="debug-tab-content-inner">
                            <div class="debug-performance-header">
                                <span>Медленные запросы: ' . $performance_stats['slow_queries'] . ' | Всего запросов: ' . $performance_stats['total_queries'] . '</span>
                                <span>Среднее время: ' . number_format($performance_stats['avg_time'], 2) . 'ms | Порог: ' . $performance_stats['slow_query_threshold'] . 'ms</span>
                            </div>
                            
                            <!-- Статистика производительности -->
                            <div class="debug-performance-stats">
                                <div class="debug-performance-stat-item">
                                    <span class="debug-performance-stat-label">Всего запросов:</span>
                                    <span class="debug-performance-stat-value">' . $performance_stats['total_queries'] . '</span>
                                </div>
                                <div class="debug-performance-stat-item">
                                    <span class="debug-performance-stat-label">Медленные запросы:</span>
                                    <span class="debug-performance-stat-value slow-queries">' . $performance_stats['slow_queries'] . '</span>
                                </div>
                                <div class="debug-performance-stat-item">
                                    <span class="debug-performance-stat-label">Процент медленных:</span>
                                    <span class="debug-performance-stat-value">' . number_format($performance_stats['slow_query_percentage'], 1) . '%</span>
                                </div>
                                <div class="debug-performance-stat-item">
                                    <span class="debug-performance-stat-label">Среднее время:</span>
                                    <span class="debug-performance-stat-value">' . number_format($performance_stats['avg_time'], 2) . 'ms</span>
                                </div>
                                <div class="debug-performance-stat-item">
                                    <span class="debug-performance-stat-label">Максимальное время:</span>
                                    <span class="debug-performance-stat-value">' . number_format($performance_stats['max_time'], 2) . 'ms</span>
                                </div>
                                <div class="debug-performance-stat-item">
                                    <span class="debug-performance-stat-label">Минимальное время:</span>
                                    <span class="debug-performance-stat-value">' . number_format($performance_stats['min_time'], 2) . 'ms</span>
                                </div>
                            </div>
                            
                            <!-- Медленные запросы -->
                            <div class="debug-slow-queries-section">
                                <h3>🐌 Медленные запросы</h3>';
        
        if (empty($slow_queries)) {
            $html .= '<div class="debug-empty">Нет медленных запросов</div>';
        } else {
            foreach ($slow_queries as $query) {
                $html .= '<div class="debug-slow-query-item">
                    <div class="debug-slow-query-header">
                        <span class="debug-slow-query-time">' . number_format($query['avg_time'], 2) . 'ms</span>
                        <span class="debug-slow-query-count">' . $query['slow_executions'] . '/' . $query['total_executions'] . ' медленных</span>
                    </div>
                    <div class="debug-slow-query-sql">' . htmlspecialchars($query['normalized_sql']) . '</div>
                    <div class="debug-slow-query-details">
                        <span>Макс: ' . number_format($query['max_time'], 2) . 'ms</span>
                        <span>Всего времени: ' . number_format($query['total_time'], 2) . 'ms</span>
                        <span>Соединение: ' . $query['connection'] . '</span>
                    </div>
                </div>';
            }
        }
        
        $html .= '</div>
                            
                            <!-- Рекомендации по производительности -->
                            <div class="debug-performance-recommendations-section">
                                <h3>💡 Рекомендации по производительности</h3>';
        
        if (empty($performance_recommendations)) {
            $html .= '<div class="debug-empty">Нет рекомендаций по производительности</div>';
        } else {
            foreach ($performance_recommendations as $recommendation) {
                $type_class = $recommendation['type'] === 'critical' ? 'critical' : ($recommendation['type'] === 'warning' ? 'warning' : 'info');
                $type_icon = $recommendation['type'] === 'critical' ? '🔴' : ($recommendation['type'] === 'warning' ? '🟡' : 'ℹ️');
                
                $html .= '<div class="debug-performance-recommendation-item ' . $type_class . '">
                    <div class="debug-performance-recommendation-header">
                        <span class="debug-performance-recommendation-icon">' . $type_icon . '</span>
                        <span class="debug-performance-recommendation-title">' . htmlspecialchars($recommendation['title']) . '</span>
                    </div>
                    <div class="debug-performance-recommendation-description">' . htmlspecialchars($recommendation['description']) . '</div>
                    <div class="debug-performance-recommendation-suggestion">' . htmlspecialchars($recommendation['suggestion']) . '</div>';
                
                if (isset($recommendation['sql'])) {
                    $html .= '<div class="debug-performance-recommendation-sql">
                        <strong>SQL:</strong><br>
                        <code>' . htmlspecialchars($recommendation['sql']) . '</code>
                    </div>';
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '</div>
                        </div>
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
            margin: 0;
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
            background: transparent;
            color: #007cba;
            border-bottom-color: #007cba;
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
            overflow: hidden;
            flex: 1;
        }
        
        .debug-tab-panel.active {
            display: block;
        }
        
        .debug-tab-content-inner {
            height: 100%;
            overflow-y: auto;
            padding: 25px;
            padding-right: 35px;
        }
        
        /* Красивый скроллбар */
        .debug-tab-content-inner::-webkit-scrollbar {
            width: 8px;
        }
        
        .debug-tab-content-inner::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        
        .debug-tab-content-inner::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #007cba, #0056b3);
            border-radius: 4px;
        }
        
        .debug-tab-content-inner::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
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
            margin: -25px -25px 20px -25px;
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
        
        /* Профилирование */
        .debug-timing-header {
            background: linear-gradient(135deg, #007cba, #0056b3);
            padding: 12px 16px;
            margin: -25px -25px 20px -25px;
            color: #fff;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
        }
        
        .debug-timing-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .debug-timing-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-timing-header-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .debug-timing-name {
            color: #fff;
            font-weight: 600;
            font-size: 13px;
        }
        
        .debug-timing-time {
            font-weight: 600;
            font-size: 12px;
        }
        
        .debug-timing-description {
            color: #aaa;
            font-size: 11px;
            margin-bottom: 8px;
        }
        
        .debug-timing-details {
            display: flex;
            gap: 12px;
            font-size: 10px;
        }
        
        .debug-timing-total {
            color: #007cba;
        }
        
        .debug-timing-memory {
            font-weight: 600;
        }
        
        .debug-timing-peak {
            color: #888;
        }
        
        /* Индексы */
        .debug-indexes-header {
            background: linear-gradient(135deg, #007cba, #0056b3);
            padding: 12px 16px;
            margin: -25px -25px 20px -25px;
            color: #fff;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
        }
        
        .debug-indexes-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .debug-indexes-stat-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 6px;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .debug-indexes-stat-label {
            color: #aaa;
            font-size: 11px;
        }
        
        .debug-indexes-stat-value {
            font-weight: 600;
            font-size: 14px;
        }
        
        .debug-indexes-stat-value.high-priority {
            color: #ff6b6b;
        }
        
        .debug-indexes-stat-value.medium-priority {
            color: #ffa726;
        }
        
        .debug-indexes-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .debug-index-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid #007cba;
        }
        
        .debug-index-item.high-priority {
            border-left-color: #ff6b6b;
        }
        
        .debug-index-item.medium-priority {
            border-left-color: #ffa726;
        }
        
        .debug-index-item.low-priority {
            border-left-color: #4caf50;
        }
        
        .debug-index-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .debug-index-priority {
            font-size: 16px;
        }
        
        .debug-index-table {
            color: #007cba;
            font-weight: 600;
            font-size: 14px;
        }
        
        .debug-index-type {
            background: rgba(0, 124, 186, 0.2);
            color: #007cba;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .debug-index-usage {
            color: #aaa;
            font-size: 11px;
            margin-left: auto;
        }
        
        .debug-index-columns {
            color: #fff;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .debug-index-reason {
            color: #aaa;
            font-size: 11px;
            margin-bottom: 12px;
        }
        
        .debug-index-sql {
            background: #1e1e1e;
            border-radius: 4px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-index-sql code {
            color: #4caf50;
            font-family: \'Courier New\', monospace;
            font-size: 11px;
            word-break: break-all;
            line-height: 1.4;
        }
        
        /* Табы внутри индексов */
        .debug-indexes-tabs {
            display: flex;
            background: linear-gradient(135deg, #2d2d2d, #3d3d3d);
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-indexes-tab {
            background: transparent;
            border: none;
            color: #aaa;
            padding: 10px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 2px;
        }
        
        .debug-indexes-tab:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        
        .debug-indexes-tab.active {
            background: linear-gradient(135deg, #007cba, #0056b3);
            color: #fff;
        }
        
        .debug-indexes-tab-icon {
            font-size: 12px;
        }
        
        .debug-indexes-content {
            position: relative;
        }
        
        .debug-indexes-panel {
            display: none;
        }
        
        .debug-indexes-panel.active {
            display: block;
        }
        
        /* Статус индексов */
        .debug-indexes-stat-value.existing {
            color: #4caf50;
        }
        
        .debug-index-item.existing {
            border-left-color: #4caf50;
            background: linear-gradient(135deg, #2d4d2d, #3d5d3d);
        }
        
        .debug-index-status {
            font-size: 14px;
        }
        
        .debug-index-existing {
            background: #1e3d1e;
            border-radius: 4px;
            padding: 8px;
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4caf50;
            font-size: 11px;
            margin-top: 8px;
        }
        
        /* Производительность */
        .debug-performance-header {
            background: linear-gradient(135deg, #ff6b6b, #e53e3e);
            padding: 12px 16px;
            margin: -25px -25px 20px -25px;
            color: #fff;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .debug-performance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .debug-performance-stat-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 6px;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .debug-performance-stat-label {
            color: #aaa;
            font-size: 11px;
            font-weight: 600;
        }
        
        .debug-performance-stat-value {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
        }
        
        .debug-performance-stat-value.slow-queries {
            color: #ff6b6b;
        }
        
        .debug-slow-queries-section {
            margin-bottom: 25px;
        }
        
        .debug-slow-queries-section h3 {
            color: #fff;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .debug-slow-query-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid #ff6b6b;
        }
        
        .debug-slow-query-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .debug-slow-query-time {
            color: #ff6b6b;
            font-size: 18px;
            font-weight: 600;
        }
        
        .debug-slow-query-count {
            color: #aaa;
            font-size: 12px;
            background: rgba(255, 107, 107, 0.2);
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .debug-slow-query-sql {
            background: #1e1e1e;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: \'Courier New\', monospace;
            font-size: 11px;
            color: #4caf50;
            word-break: break-all;
            line-height: 1.4;
        }
        
        .debug-slow-query-details {
            display: flex;
            gap: 15px;
            font-size: 11px;
            color: #aaa;
        }
        
        .debug-performance-recommendations-section {
            margin-bottom: 25px;
        }
        
        .debug-performance-recommendations-section h3 {
            color: #fff;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .debug-performance-recommendation-item {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-performance-recommendation-item.critical {
            border-left: 4px solid #ff6b6b;
        }
        
        .debug-performance-recommendation-item.warning {
            border-left: 4px solid #ffa726;
        }
        
        .debug-performance-recommendation-item.info {
            border-left: 4px solid #42a5f5;
        }
        
        .debug-performance-recommendation-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .debug-performance-recommendation-icon {
            font-size: 16px;
        }
        
        .debug-performance-recommendation-title {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
        }
        
        .debug-performance-recommendation-description {
            color: #aaa;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .debug-performance-recommendation-suggestion {
            color: #4caf50;
            font-size: 12px;
            font-weight: 600;
        }
        
        .debug-performance-recommendation-sql {
            background: #1e1e1e;
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-performance-recommendation-sql code {
            color: #4caf50;
            font-family: \'Courier New\', monospace;
            font-size: 10px;
            word-break: break-all;
            line-height: 1.4;
        }
        </style>
        
        <script>
        // Загружаем сохраненное состояние панели
        function loadDebugPanelState(debugId) {
            const savedState = localStorage.getItem(\'imy-debug-panel-state\');
            const savedTab = localStorage.getItem(\'imy-debug-panel-tab\');
            const savedHeight = localStorage.getItem(\'imy-debug-panel-height\');
            
            if (savedState === \'open\') {
                const panel = document.getElementById(debugId);
                const content = document.getElementById(debugId + "-content");
                const icon = document.getElementById(debugId + "-icon");
                
                // Проверяем существование элементов
                if (panel && content && icon) {
                    panel.style.display = "block";
                    content.style.display = "block";
                    icon.style.display = "none";
                    
                    // Восстанавливаем сохраненную высоту
                    if (savedHeight) {
                        panel.style.height = savedHeight + \'px\';
                    }
                    
                    if (savedTab) {
                        switchDebugTab(debugId, savedTab, false);
                    }
                }
            }
        }
        
        function toggleDebugPanel(debugId) {
            const panel = document.getElementById(debugId);
            const content = document.getElementById(debugId + "-content");
            const icon = document.getElementById(debugId + "-icon");
            
            // Проверяем существование элементов
            if (!panel || !content || !icon) return;
            
            if (panel.style.display === "none" || panel.style.display === "") {
                panel.style.display = "block";
                content.style.display = "block";
                icon.style.display = "none";
                localStorage.setItem(\'imy-debug-panel-state\', \'open\');
                
                // Восстанавливаем сохраненную высоту при открытии
                const savedHeight = localStorage.getItem(\'imy-debug-panel-height\');
                if (savedHeight) {
                    panel.style.height = savedHeight + \'px\';
                }
            } else {
                // Сохраняем текущую высоту перед закрытием
                localStorage.setItem(\'imy-debug-panel-height\', panel.offsetHeight.toString());
                
                panel.style.display = "none";
                content.style.display = "none";
                icon.style.display = "flex";
                localStorage.setItem(\'imy-debug-panel-state\', \'closed\');
            }
        }
        
        function switchDebugTab(debugId, tabName, saveState = true) {
            // Скрываем все панели
            const panels = document.querySelectorAll(\'#\' + debugId + \'-tab-overview, #\' + debugId + \'-tab-queries, #\' + debugId + \'-tab-request, #\' + debugId + \'-tab-logs, #\' + debugId + \'-tab-performance, #\' + debugId + \'-tab-files, #\' + debugId + \'-tab-errors, #\' + debugId + \'-tab-timing, #\' + debugId + \'-tab-indexes\');
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
            const targetTab = document.querySelector(\'#\' + debugId + \' .debug-tab[onclick*="\' + tabName + \'"]\');
            if (targetTab) {
                targetTab.classList.add(\'active\');
            }
            
            // Сохраняем выбранный таб
            if (saveState) {
                localStorage.setItem(\'imy-debug-panel-tab\', tabName);
            }
        }
        
        // Функция переключения табов индексов
        function switchIndexTab(debugId, tabName) {
            // Скрываем все панели индексов
            const panels = document.querySelectorAll(\'#\' + debugId + \'-indexes-missing, #\' + debugId + \'-indexes-existing, #\' + debugId + \'-indexes-all\');
            panels.forEach(panel => panel.classList.remove(\'active\'));
            
            // Убираем активный класс со всех табов индексов
            const tabs = document.querySelectorAll(\'#\' + debugId + \' .debug-indexes-tab\');
            tabs.forEach(tab => tab.classList.remove(\'active\'));
            
            // Показываем нужную панель
            const targetPanel = document.getElementById(debugId + \'-indexes-\' + tabName);
            if (targetPanel) {
                targetPanel.classList.add(\'active\');
            }
            
            // Активируем нужный таб
            const targetTab = document.querySelector(\'#\' + debugId + \' .debug-indexes-tab[onclick*="\' + tabName + \'"]\');
            if (targetTab) {
                targetTab.classList.add(\'active\');
            }
        }
        
        // Убрана функциональность закрытия панели по клику вне её
        // Панель теперь закрывается только через иконку или кнопку закрытия
        
        // Загружаем состояние панели при загрузке страницы
        document.addEventListener(\'DOMContentLoaded\', function() {
            const debugPanels = document.querySelectorAll(\'[id^="imy-debug-"]\');
            debugPanels.forEach(panel => {
                if (!panel.id.includes(\'icon\') && !panel.id.includes(\'content\')) {
                    loadDebugPanelState(panel.id);
                    initResizeHandle(panel.id);
                }
            });
        });
        
        // Инициализация ручки изменения размера
        function initResizeHandle(debugId) {
            const panel = document.getElementById(debugId);
            const resizeHandle = panel.querySelector(\'.debug-resize-handle\');
            
            if (!resizeHandle) return;
            
            let isResizing = false;
            let startY = 0;
            let startHeight = 0;
            
            resizeHandle.addEventListener(\'mousedown\', function(e) {
                e.preventDefault();
                e.stopPropagation();
                isResizing = true;
                startY = e.clientY;
                startHeight = panel.offsetHeight;
                document.body.style.cursor = \'ns-resize\';
                document.body.style.userSelect = \'none\';
                resizeHandle.style.background = \'#0056b3\';
            });
            
            document.addEventListener(\'mousemove\', function(e) {
                if (!isResizing) return;
                
                const deltaY = startY - e.clientY;
                const newHeight = startHeight + deltaY;
                const minHeight = 200;
                const maxHeight = window.innerHeight * 0.8;
                
                if (newHeight >= minHeight && newHeight <= maxHeight) {
                    panel.style.height = newHeight + \'px\';
                    // Сохраняем новую высоту в localStorage
                    localStorage.setItem(\'imy-debug-panel-height\', newHeight.toString());
                }
            });
            
            document.addEventListener(\'mouseup\', function() {
                if (isResizing) {
                    isResizing = false;
                    document.body.style.cursor = \'\';
                    document.body.style.userSelect = \'\';
                    resizeHandle.style.background = \'#007cba\';
                }
            });
        }
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

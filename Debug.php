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
    
    public static function init()
    {
        self::$start_time = microtime(true);
        self::$memory_start = memory_get_usage();
        self::$enabled = true;
        
        // Собираем данные о запросе
        self::collectRequestData();
        
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
        
        $queries_time = array_sum(array_column($queries, 'time'));
        
        echo self::renderDebugHTML($total_time, $total_memory, $peak_memory, $queries, $queries_time, $connections, $headers, $logs, $request_data);
    }
    
    private static function renderDebugHTML($total_time, $total_memory, $peak_memory, $queries, $queries_time, $connections, $headers, $logs, $request_data)
    {
        $debug_id = 'imy-debug-' . uniqid();
        
        $html = '
        <!-- Дебаг иконка -->
        <div id="' . $debug_id . '-icon" style="
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #007cba, #0056b3);
            border-radius: 50%;
            cursor: pointer;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 124, 186, 0.3);
            transition: all 0.3s ease;
            font-size: 18px;
            color: white;
            font-weight: bold;
        " onmouseover="this.style.transform=\'scale(1.1)\'; this.style.boxShadow=\'0 6px 16px rgba(0, 124, 186, 0.4)\'" onmouseout="this.style.transform=\'scale(1)\'; this.style.boxShadow=\'0 4px 12px rgba(0, 124, 186, 0.3)\'" onclick="toggleDebugPanel(\'' . $debug_id . '\')">
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
            max-height: 70vh;
            overflow: hidden;
            border-top: 3px solid #007cba;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            display: none;
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
                        Запрос
                    </button>
                    <button class="debug-tab" onclick="switchDebugTab(\'' . $debug_id . '\', \'logs\')">
                        <span class="debug-tab-icon">📝</span>
                        Логи (' . count($logs) . ')
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
                </div>
            </div>
        </div>
        
        <style>
        /* Табы */
        .debug-tabs {
            display: flex;
            background: linear-gradient(135deg, #1e1e1e, #2d2d2d);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .debug-tab {
            flex: 1;
            background: transparent;
            border: none;
            color: #aaa;
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
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
            min-height: 300px;
            max-height: 50vh;
            overflow: hidden;
        }
        
        .debug-tab-panel {
            display: none;
            height: 100%;
            overflow-y: auto;
        }
        
        .debug-tab-panel.active {
            display: block;
        }
        
        .debug-tab-content-inner {
            padding: 20px;
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
        </style>
        
        <script>
        function toggleDebugPanel(debugId) {
            const panel = document.getElementById(debugId);
            const content = document.getElementById(debugId + "-content");
            const icon = document.getElementById(debugId + "-icon");
            
            if (panel.style.display === "none" || panel.style.display === "") {
                panel.style.display = "block";
                content.style.display = "block";
                icon.style.display = "none";
            } else {
                panel.style.display = "none";
                content.style.display = "none";
                icon.style.display = "flex";
            }
        }
        
        function switchDebugTab(debugId, tabName) {
            // Скрываем все панели
            const panels = document.querySelectorAll(\'#\' + debugId + \'-tab-\' + \'overview, #\' + debugId + \'-tab-queries, #\' + debugId + \'-tab-request, #\' + debugId + \'-tab-logs\');
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
            const targetTab = event.target.closest(\'.debug-tab\');
            if (targetTab) {
                targetTab.classList.add(\'active\');
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

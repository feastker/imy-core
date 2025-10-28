<?php

namespace Imy\Core;

/**
 * Класс для анализа SQL запросов и определения недостающих индексов
 */
class IndexAnalyzer
{
    private static $queries = [];
    private static $index_recommendations = [];
    private static $existing_indexes = [];
    private static $indexes_analyzed = false;
    private static $explain_enabled = true;
    private static $explain_results = [];
    private static $performance_data = [];
    private static $slow_query_threshold = 1000; // 1 секунда в миллисекундах
    private static $max_performance_entries = 1000; // Максимум записей в памяти
    private static $max_executions_per_query = 100; // Максимум выполнений на запрос
    
    /**
     * Анализирует SQL запрос и определяет потенциально недостающие индексы
     */
    public static function analyzeQuery($sql, $connection_name = 'default')
    {
        $query_type = self::getQueryType($sql);
        
        // Анализируем только поддерживаемые типы запросов
        if (!in_array($query_type, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) {
            return;
        }
        
        // Для SELECT запросов проверяем UNION
        if ($query_type === 'SELECT' && self::hasUnion($sql)) {
            $union_queries = self::splitUnionQueries($sql);
            foreach ($union_queries as $union_query) {
                self::analyzeQuery($union_query, $connection_name);
            }
            return;
        }
        
        $query_id = md5($sql . $connection_name);
        
        // Извлекаем информацию о запросе в зависимости от типа
        if ($query_type === 'SELECT') {
            $query_info = self::extractSelectQueryInfo($sql);
        } else {
            $query_info = self::extractDmlQueryInfo($sql, $query_type);
        }
        
        if (empty($query_info)) {
            return;
        }
        
        // Анализируем в зависимости от типа запроса
        if ($query_type === 'SELECT') {
            $where_conditions = self::analyzeWhereConditions($query_info['where']);
            $join_conditions = self::analyzeJoinConditions($query_info['joins']);
            $order_columns = self::analyzeOrderBy($query_info['order_by']);
            $group_columns = self::analyzeGroupBy($query_info['group_by']);
            $having_conditions = self::analyzeHavingConditions($query_info['having']);
            
            // Анализируем подзапросы
            $subquery_conditions = [];
            foreach ($query_info['subqueries'] as $subquery) {
                $subquery_info = self::extractSelectQueryInfo($subquery);
                $subquery_conditions = array_merge($subquery_conditions, self::analyzeWhereConditions($subquery_info['where']));
            }
        } else {
            // Для DML запросов анализируем специфичные условия
            $where_conditions = self::analyzeWhereConditions($query_info['where']);
            $join_conditions = self::analyzeJoinConditions($query_info['joins']);
            $order_columns = self::analyzeOrderBy($query_info['order_by']);
            $group_columns = [];
            $having_conditions = [];
            $subquery_conditions = [];
            
            // Дополнительный анализ для DML
            $dml_conditions = self::analyzeDmlConditions($query_info, $query_type);
        }
        
        // Выполняем EXPLAIN для SELECT запросов
        $explain_data = null;
        $explain_recommendations = [];
        
        if ($query_type === 'SELECT') {
            $explain_data = self::executeExplain($sql, $connection_name);
            if ($explain_data) {
                $explain_recommendations = self::analyzeExecutionPlan($explain_data, $sql, $connection_name);
            }
        }
        
        // Генерируем рекомендации по индексам
        if ($query_type === 'SELECT') {
            $recommendations = self::generateIndexRecommendations(
                $query_info['tables'],
                $where_conditions,
                $join_conditions,
                $order_columns,
                $group_columns,
                $having_conditions,
                $subquery_conditions
            );
            
            // Добавляем рекомендации на основе EXPLAIN
            $recommendations = array_merge($recommendations, $explain_recommendations);
        } else {
            // Для DML запросов используем специальную логику
            $recommendations = self::generateDmlIndexRecommendations(
                $query_info,
                $where_conditions,
                $join_conditions,
                $order_columns,
                $dml_conditions ?? []
            );
        }
        
        // Анализируем производительность запроса
        $performance_data = self::analyzeQueryPerformance($sql, 0, $connection_name); // Время будет установлено в Debug::logQuery
        
        if (!empty($recommendations)) {
            self::$queries[$query_id] = [
                'sql' => $sql,
                'connection' => $connection_name,
                'tables' => $query_info['tables'],
                'recommendations' => $recommendations,
                'performance' => $performance_data,
                'timestamp' => microtime(true)
            ];
            
            // Добавляем рекомендации в общий список
            foreach ($recommendations as $recommendation) {
                $index_key = $recommendation['table'] . '.' . $recommendation['columns'];
                if (!isset(self::$index_recommendations[$index_key])) {
                    self::$index_recommendations[$index_key] = [
                        'table' => $recommendation['table'],
                        'columns' => $recommendation['columns'],
                        'type' => $recommendation['type'],
                        'priority' => $recommendation['priority'],
                        'reason' => $recommendation['reason'],
                        'queries' => [],
                        'usage_count' => 0
                    ];
                }
                
                self::$index_recommendations[$index_key]['queries'][] = $query_id;
                self::$index_recommendations[$index_key]['usage_count']++;
            }
        }
    }
    
    /**
     * Проверяет, является ли запрос SELECT запросом
     */
    private static function isSelectQuery($sql)
    {
        $sql = trim($sql);
        return stripos($sql, 'SELECT') === 0;
    }
    
    /**
     * Проверяет, является ли запрос INSERT запросом
     */
    private static function isInsertQuery($sql)
    {
        $sql = trim($sql);
        return stripos($sql, 'INSERT') === 0;
    }
    
    /**
     * Проверяет, является ли запрос UPDATE запросом
     */
    private static function isUpdateQuery($sql)
    {
        $sql = trim($sql);
        return stripos($sql, 'UPDATE') === 0;
    }
    
    /**
     * Проверяет, является ли запрос DELETE запросом
     */
    private static function isDeleteQuery($sql)
    {
        $sql = trim($sql);
        return stripos($sql, 'DELETE') === 0;
    }
    
    /**
     * Определяет тип SQL запроса
     */
    private static function getQueryType($sql)
    {
        $sql = trim($sql);
        $firstWord = strtoupper(explode(' ', $sql)[0]);
        
        switch ($firstWord) {
            case 'SELECT':
                return 'SELECT';
            case 'INSERT':
                return 'INSERT';
            case 'UPDATE':
                return 'UPDATE';
            case 'DELETE':
                return 'DELETE';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Проверяет, содержит ли запрос UNION
     */
    private static function hasUnion($sql)
    {
        return preg_match('/\bUNION\b/i', $sql);
    }
    
    /**
     * Разбивает UNION запрос на отдельные SELECT'ы
     */
    private static function splitUnionQueries($sql)
    {
        $queries = [];
        
        // Разбиваем по UNION (игнорируя ALL)
        $parts = preg_split('/\s+UNION\s+(?:ALL\s+)?/i', $sql);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (!empty($part) && self::isSelectQuery($part)) {
                $queries[] = $part;
            }
        }
        
        return $queries;
    }
    
    /**
     * Извлекает основную информацию о SELECT запросе
     */
    private static function extractSelectQueryInfo($sql)
    {
        $sql = trim($sql);
        
        $info = [
            'tables' => [],
            'where' => '',
            'joins' => [],
            'order_by' => '',
            'group_by' => '',
            'having' => '',
            'subqueries' => []
        ];
        
        // Нормализуем SQL - убираем лишние пробелы и переносы строк
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        // Извлекаем таблицы из FROM с поддержкой алиасов
        $info['tables'] = self::extractTables($sql);
        
        // Извлекаем JOIN'ы с улучшенной поддержкой
        $info['joins'] = self::extractJoins($sql);
        
        // Извлекаем WHERE условия с поддержкой подзапросов
        $info['where'] = self::extractWhereClause($sql);
        
        // Извлекаем ORDER BY
        $info['order_by'] = self::extractOrderBy($sql);
        
        // Извлекаем GROUP BY
        $info['group_by'] = self::extractGroupBy($sql);
        
        // Извлекаем HAVING
        $info['having'] = self::extractHaving($sql);
        
        // Извлекаем подзапросы
        $info['subqueries'] = self::extractSubqueries($sql);
        
        return $info;
    }
    
    /**
     * Извлекает таблицы из FROM с поддержкой алиасов
     */
    private static function extractTables($sql)
    {
        $tables = [];
        
        // Ищем FROM с поддержкой алиасов и подзапросов
        if (preg_match('/FROM\s+([^WHERE\s]+?)(?:\s+WHERE|\s+GROUP\s+BY|\s+ORDER\s+BY|\s+HAVING|\s+LIMIT|$)/i', $sql, $matches)) {
            $from_clause = trim($matches[1]);
            
            // Разделяем по запятым, учитывая скобки
            $table_parts = self::splitByComma($from_clause);
            
            foreach ($table_parts as $part) {
                $part = trim($part);
                
                // Пропускаем подзапросы в FROM
                if (strpos($part, '(') === 0) {
                    continue;
                }
                
                // Извлекаем имя таблицы и алиас
                if (preg_match('/^(\w+)(?:\s+(?:AS\s+)?(\w+))?/i', $part, $table_matches)) {
                    $tables[] = [
                        'name' => $table_matches[1],
                        'alias' => isset($table_matches[2]) ? $table_matches[2] : null
                    ];
                }
            }
        }
        
        return $tables;
    }
    
    /**
     * Извлекает JOIN'ы с улучшенной поддержкой
     */
    private static function extractJoins($sql)
    {
        $joins = [];
        
        // Ищем различные типы JOIN'ов
        $join_patterns = [
            '/(?:LEFT|RIGHT|INNER|OUTER|CROSS)?\s*JOIN\s+(\w+)(?:\s+(?:AS\s+)?(\w+))?\s+ON\s+([^WHERE\s]+)/i',
            '/(?:LEFT|RIGHT|INNER|OUTER|CROSS)?\s*JOIN\s+(\w+)(?:\s+(?:AS\s+)?(\w+))?\s+USING\s*\(([^)]+)\)/i'
        ];
        
        foreach ($join_patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $joins[] = [
                        'table' => $match[1],
                        'alias' => isset($match[2]) ? $match[2] : null,
                        'condition' => isset($match[3]) ? $match[3] : null,
                        'type' => 'join'
                    ];
                }
            }
        }
        
        return $joins;
    }
    
    /**
     * Извлекает WHERE условия с поддержкой подзапросов
     */
    private static function extractWhereClause($sql)
    {
        if (preg_match('/WHERE\s+(.+?)(?:\s+GROUP\s+BY|\s+ORDER\s+BY|\s+HAVING|\s+LIMIT|$)/i', $sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Извлекает ORDER BY
     */
    private static function extractOrderBy($sql)
    {
        if (preg_match('/ORDER\s+BY\s+([^LIMIT\s]+)/i', $sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Извлекает GROUP BY
     */
    private static function extractGroupBy($sql)
    {
        if (preg_match('/GROUP\s+BY\s+([^ORDER\s]+)/i', $sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Извлекает HAVING
     */
    private static function extractHaving($sql)
    {
        if (preg_match('/HAVING\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/i', $sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Извлекает подзапросы из SQL
     */
    private static function extractSubqueries($sql)
    {
        $subqueries = [];
        
        // Ищем подзапросы в различных местах
        $patterns = [
            '/(?:WHERE|HAVING)\s+EXISTS\s*\(\s*(SELECT[^)]+)\)/i',
            '/(?:WHERE|HAVING)\s+\w+\s+IN\s*\(\s*(SELECT[^)]+)\)/i',
            '/(?:WHERE|HAVING)\s+\w+\s+(?:=|<>|!=|>|<|>=|<=)\s*\(\s*(SELECT[^)]+)\)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $subqueries[] = trim($match[1]);
                }
            }
        }
        
        return $subqueries;
    }
    
    /**
     * Разделяет строку по запятым, учитывая скобки
     */
    private static function splitByComma($str)
    {
        $parts = [];
        $current = '';
        $depth = 0;
        
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty($current)) {
            $parts[] = trim($current);
        }
        
        return $parts;
    }
    
    /**
     * Извлекает информацию о DML запросах (INSERT, UPDATE, DELETE)
     */
    private static function extractDmlQueryInfo($sql, $query_type)
    {
        $sql = trim($sql);
        
        $info = [
            'tables' => [],
            'where' => '',
            'joins' => [],
            'order_by' => '',
            'group_by' => '',
            'having' => '',
            'subqueries' => [],
            'type' => $query_type,
            'columns' => [],
            'values' => []
        ];
        
        // Нормализуем SQL
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        switch ($query_type) {
            case 'INSERT':
                $info = self::extractInsertInfo($sql, $info);
                break;
            case 'UPDATE':
                $info = self::extractUpdateInfo($sql, $info);
                break;
            case 'DELETE':
                $info = self::extractDeleteInfo($sql, $info);
                break;
        }
        
        return $info;
    }
    
    /**
     * Извлекает информацию из INSERT запроса
     */
    private static function extractInsertInfo($sql, $info)
    {
        // Извлекаем таблицу
        if (preg_match('/INSERT\s+(?:INTO\s+)?(\w+)/i', $sql, $matches)) {
            $info['tables'][] = ['name' => $matches[1], 'alias' => null];
        }
        
        // Извлекаем колонки
        if (preg_match('/INSERT\s+(?:INTO\s+)?\w+\s*\(([^)]+)\)/i', $sql, $matches)) {
            $columns = explode(',', $matches[1]);
            foreach ($columns as $column) {
                $info['columns'][] = trim($column);
            }
        }
        
        // Извлекаем VALUES
        if (preg_match('/VALUES\s*\(([^)]+)\)/i', $sql, $matches)) {
            $values = explode(',', $matches[1]);
            foreach ($values as $value) {
                $info['values'][] = trim($value);
            }
        }
        
        // Извлекаем SELECT в INSERT ... SELECT
        if (preg_match('/INSERT\s+(?:INTO\s+)?\w+\s+SELECT\s+(.+)/i', $sql, $matches)) {
            $select_part = $matches[1];
            $select_info = self::extractSelectQueryInfo('SELECT ' . $select_part);
            $info = array_merge($info, $select_info);
        }
        
        return $info;
    }
    
    /**
     * Извлекает информацию из UPDATE запроса
     */
    private static function extractUpdateInfo($sql, $info)
    {
        // Извлекаем таблицу
        if (preg_match('/UPDATE\s+(\w+)/i', $sql, $matches)) {
            $info['tables'][] = ['name' => $matches[1], 'alias' => null];
        }
        
        // Извлекаем SET колонки
        if (preg_match('/SET\s+([^WHERE]+)/i', $sql, $matches)) {
            $set_clause = $matches[1];
            if (preg_match_all('/(\w+)\s*=/i', $set_clause, $set_matches)) {
                foreach ($set_matches[1] as $column) {
                    $info['columns'][] = trim($column);
                }
            }
        }
        
        // Извлекаем WHERE условия
        if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/i', $sql, $matches)) {
            $info['where'] = trim($matches[1]);
        }
        
        // Извлекаем JOIN'ы в UPDATE
        if (preg_match_all('/(?:LEFT|RIGHT|INNER|OUTER|CROSS)?\s*JOIN\s+(\w+)(?:\s+(?:AS\s+)?(\w+))?\s+ON\s+([^WHERE\s]+)/i', $sql, $join_matches, PREG_SET_ORDER)) {
            foreach ($join_matches as $join) {
                $info['joins'][] = [
                    'table' => $join[1],
                    'alias' => isset($join[2]) ? $join[2] : null,
                    'condition' => $join[3],
                    'type' => 'join'
                ];
            }
        }
        
        // Извлекаем ORDER BY
        if (preg_match('/ORDER\s+BY\s+([^LIMIT\s]+)/i', $sql, $matches)) {
            $info['order_by'] = trim($matches[1]);
        }
        
        return $info;
    }
    
    /**
     * Извлекает информацию из DELETE запроса
     */
    private static function extractDeleteInfo($sql, $info)
    {
        // Извлекаем таблицу
        if (preg_match('/DELETE\s+(?:FROM\s+)?(\w+)/i', $sql, $matches)) {
            $info['tables'][] = ['name' => $matches[1], 'alias' => null];
        }
        
        // Извлекаем WHERE условия
        if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/i', $sql, $matches)) {
            $info['where'] = trim($matches[1]);
        }
        
        // Извлекаем JOIN'ы в DELETE
        if (preg_match_all('/(?:LEFT|RIGHT|INNER|OUTER|CROSS)?\s*JOIN\s+(\w+)(?:\s+(?:AS\s+)?(\w+))?\s+ON\s+([^WHERE\s]+)/i', $sql, $join_matches, PREG_SET_ORDER)) {
            foreach ($join_matches as $join) {
                $info['joins'][] = [
                    'table' => $join[1],
                    'alias' => isset($join[2]) ? $join[2] : null,
                    'condition' => $join[3],
                    'type' => 'join'
                ];
            }
        }
        
        // Извлекаем ORDER BY
        if (preg_match('/ORDER\s+BY\s+([^LIMIT\s]+)/i', $sql, $matches)) {
            $info['order_by'] = trim($matches[1]);
        }
        
        return $info;
    }
    
    /**
     * Анализирует специфичные условия для DML запросов
     */
    private static function analyzeDmlConditions($query_info, $query_type)
    {
        $conditions = [];
        
        switch ($query_type) {
            case 'INSERT':
                $conditions = self::analyzeInsertConditions($query_info);
                break;
            case 'UPDATE':
                $conditions = self::analyzeUpdateConditions($query_info);
                break;
            case 'DELETE':
                $conditions = self::analyzeDeleteConditions($query_info);
                break;
        }
        
        return $conditions;
    }
    
    /**
     * Анализирует условия для INSERT запросов
     */
    private static function analyzeInsertConditions($query_info)
    {
        $conditions = [];
        
        // Для INSERT запросов важны:
        // 1. PRIMARY KEY колонки
        // 2. UNIQUE колонки
        // 3. FOREIGN KEY колонки
        
        foreach ($query_info['columns'] as $column) {
            $conditions[] = [
                'column' => $column,
                'type' => 'insert_column',
                'priority' => 'medium',
                'reason' => 'Колонка в INSERT запросе - может потребовать индекс для проверки уникальности или внешних ключей'
            ];
        }
        
        return $conditions;
    }
    
    /**
     * Анализирует условия для UPDATE запросов
     */
    private static function analyzeUpdateConditions($query_info)
    {
        $conditions = [];
        
        // Для UPDATE запросов важны:
        // 1. WHERE условия (для поиска записей)
        // 2. SET колонки (могут потребовать индексы)
        // 3. JOIN условия
        
        foreach ($query_info['columns'] as $column) {
            $conditions[] = [
                'column' => $column,
                'type' => 'update_column',
                'priority' => 'low',
                'reason' => 'Колонка в SET - может потребовать индекс для оптимизации обновления'
            ];
        }
        
        return $conditions;
    }
    
    /**
     * Анализирует условия для DELETE запросов
     */
    private static function analyzeDeleteConditions($query_info)
    {
        $conditions = [];
        
        // Для DELETE запросов важны:
        // 1. WHERE условия (для поиска записей)
        // 2. JOIN условия
        
        // DELETE запросы анализируются аналогично SELECT по WHERE условиям
        return $conditions;
    }
    
    /**
     * Генерирует рекомендации по индексам для DML запросов
     */
    private static function generateDmlIndexRecommendations($query_info, $where_conditions, $join_conditions, $order_columns, $dml_conditions)
    {
        $recommendations = [];
        
        foreach ($query_info['tables'] as $table_info) {
            $table_name = is_array($table_info) ? $table_info['name'] : $table_info;
            $table_conditions = [];
            $table_joins = [];
            $table_orders = [];
            $table_dml = [];
            
            // Собираем условия для текущей таблицы
            foreach ($where_conditions as $condition) {
                $table_conditions[] = $condition;
            }
            
            foreach ($join_conditions as $condition) {
                if ($condition['table'] === $table_name) {
                    $table_joins[] = $condition;
                }
            }
            
            foreach ($order_columns as $column) {
                $table_orders[] = $column;
            }
            
            foreach ($dml_conditions as $condition) {
                $table_dml[] = $condition;
            }
            
            // Генерируем рекомендации для таблицы
            $table_recommendations = self::generateTableDmlIndexRecommendations(
                $table_name,
                $query_info['type'],
                $table_conditions,
                $table_joins,
                $table_orders,
                $table_dml
            );
            
            $recommendations = array_merge($recommendations, $table_recommendations);
        }
        
        return $recommendations;
    }
    
    /**
     * Генерирует рекомендации по индексам для конкретной таблицы в DML запросах
     */
    private static function generateTableDmlIndexRecommendations($table, $query_type, $conditions, $joins, $orders, $dml_conditions)
    {
        $recommendations = [];
        $all_columns = [];
        
        // Собираем все колонки
        foreach ($conditions as $condition) {
            if (!empty($condition['column'])) {
                $all_columns[] = $condition['column'];
            }
        }
        
        foreach ($joins as $join) {
            if (!empty($join['column'])) {
                $all_columns[] = $join['column'];
            }
        }
        
        foreach ($orders as $order) {
            if (!empty($order['column'])) {
                $all_columns[] = $order['column'];
            }
        }
        
        foreach ($dml_conditions as $condition) {
            if (!empty($condition['column'])) {
                $all_columns[] = $condition['column'];
            }
        }
        
        // Убираем дубликаты
        $all_columns = array_unique($all_columns);
        
        if (empty($all_columns)) {
            return $recommendations;
        }
        
        // Анализируем существующие индексы
        self::analyzeExistingIndexes();
        
        // Генерируем рекомендации в зависимости от типа запроса
        switch ($query_type) {
            case 'INSERT':
                $recommendations = self::generateInsertRecommendations($table, $all_columns, $dml_conditions);
                break;
            case 'UPDATE':
                $recommendations = self::generateUpdateRecommendations($table, $all_columns, $conditions, $dml_conditions);
                break;
            case 'DELETE':
                $recommendations = self::generateDeleteRecommendations($table, $all_columns, $conditions);
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Генерирует рекомендации для INSERT запросов
     */
    private static function generateInsertRecommendations($table, $columns, $dml_conditions)
    {
        $recommendations = [];
        
        foreach ($columns as $column) {
            $index_key = $table . '_' . $column;
            
            // Проверяем, существует ли уже такой индекс
            $exists = self::indexExists($table, $column);
            
            if (!$exists) {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $column,
                    'type' => 'single',
                    'priority' => 'medium',
                    'reason' => 'Колонка в INSERT запросе - может потребовать индекс для проверки уникальности или внешних ключей',
                    'usage_count' => 1,
                    'status' => 'missing',
                    'sql' => "CREATE INDEX idx_{$table}_{$column} ON {$table} ({$column});"
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Генерирует рекомендации для UPDATE запросов
     */
    private static function generateUpdateRecommendations($table, $columns, $where_conditions, $dml_conditions)
    {
        $recommendations = [];
        
        // Приоритет для WHERE условий
        foreach ($where_conditions as $condition) {
            if (!empty($condition['column'])) {
                $column = $condition['column'];
                $index_key = $table . '_' . $column;
                
                $exists = self::indexExists($table, $column);
                
                if (!$exists) {
                    $recommendations[] = [
                        'table' => $table,
                        'columns' => $column,
                        'type' => 'single',
                        'priority' => 'high',
                        'reason' => 'WHERE условие в UPDATE запросе - критично для производительности поиска записей',
                        'usage_count' => 1,
                        'status' => 'missing',
                        'sql' => "CREATE INDEX idx_{$table}_{$column} ON {$table} ({$column});"
                    ];
                }
            }
        }
        
        // Меньший приоритет для SET колонок
        foreach ($dml_conditions as $condition) {
            if (!empty($condition['column'])) {
                $column = $condition['column'];
                $index_key = $table . '_' . $column;
                
                $exists = self::indexExists($table, $column);
                
                if (!$exists) {
                    $recommendations[] = [
                        'table' => $table,
                        'columns' => $column,
                        'type' => 'single',
                        'priority' => 'low',
                        'reason' => 'Колонка в SET - может потребовать индекс для оптимизации обновления',
                        'usage_count' => 1,
                        'status' => 'missing',
                        'sql' => "CREATE INDEX idx_{$table}_{$column} ON {$table} ({$column});"
                    ];
                }
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Генерирует рекомендации для DELETE запросов
     */
    private static function generateDeleteRecommendations($table, $columns, $where_conditions)
    {
        $recommendations = [];
        
        // Для DELETE запросов важны только WHERE условия
        foreach ($where_conditions as $condition) {
            if (!empty($condition['column'])) {
                $column = $condition['column'];
                $index_key = $table . '_' . $column;
                
                $exists = self::indexExists($table, $column);
                
                if (!$exists) {
                    $recommendations[] = [
                        'table' => $table,
                        'columns' => $column,
                        'type' => 'single',
                        'priority' => 'high',
                        'reason' => 'WHERE условие в DELETE запросе - критично для производительности поиска записей',
                        'usage_count' => 1,
                        'status' => 'missing',
                        'sql' => "CREATE INDEX idx_{$table}_{$column} ON {$table} ({$column});"
                    ];
                }
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Включает или отключает анализ EXPLAIN
     */
    public static function setExplainEnabled($enabled)
    {
        self::$explain_enabled = $enabled;
    }
    
    /**
     * Получает результаты EXPLAIN для запроса
     */
    public static function getExplainResults($sql, $connection_name = 'default')
    {
        $query_id = md5($sql . $connection_name);
        return isset(self::$explain_results[$query_id]) ? self::$explain_results[$query_id] : null;
    }
    
    /**
     * Выполняет EXPLAIN для SQL запроса
     */
    private static function executeExplain($sql, $connection_name = 'default')
    {
        if (!self::$explain_enabled) {
            return null;
        }
        
        try {
            // Проверяем, есть ли подключение к БД
            if (!class_exists('Imy\Core\DB') || !class_exists('Imy\Core\Config')) {
                return null;
            }
            
            $db = \Imy\Core\DB::getInstance($connection_name);
            if (!$db) {
                return null;
            }
            
            // Определяем тип СУБД
            $db_type = self::detectDatabaseType($db);
            
            // Формируем EXPLAIN запрос в зависимости от типа СУБД
            $explain_sql = self::buildExplainQuery($sql, $db_type);
            
            if (!$explain_sql) {
                return null;
            }
            
            // Выполняем EXPLAIN
            $result = $db->query($explain_sql);
            
            if (!$result) {
                return null;
            }
            
            // Парсим результат в зависимости от типа СУБД
            $explain_data = self::parseExplainResult($result, $db_type);
            
            // Сохраняем результат
            $query_id = md5($sql . $connection_name);
            self::$explain_results[$query_id] = $explain_data;
            
            return $explain_data;
            
        } catch (Exception $e) {
            // В случае ошибки возвращаем null
            return null;
        }
    }
    
    /**
     * Определяет тип СУБД
     */
    private static function detectDatabaseType($db)
    {
        try {
            $version = $db->query("SELECT VERSION()")->fetchColumn();
            
            if (strpos($version, 'MySQL') !== false) {
                return 'mysql';
            } elseif (strpos($version, 'PostgreSQL') !== false) {
                return 'postgresql';
            } elseif (strpos($version, 'SQLite') !== false) {
                return 'sqlite';
            }
            
            return 'unknown';
        } catch (Exception $e) {
            return 'unknown';
        }
    }
    
    /**
     * Строит EXPLAIN запрос в зависимости от типа СУБД
     */
    private static function buildExplainQuery($sql, $db_type)
    {
        switch ($db_type) {
            case 'mysql':
                // Для MySQL используем EXPLAIN FORMAT=JSON для более детальной информации
                return "EXPLAIN FORMAT=JSON " . $sql;
            case 'postgresql':
                // Для PostgreSQL используем EXPLAIN (FORMAT JSON)
                return "EXPLAIN (FORMAT JSON) " . $sql;
            case 'sqlite':
                // Для SQLite используем EXPLAIN QUERY PLAN
                return "EXPLAIN QUERY PLAN " . $sql;
            default:
                // Для неизвестных СУБД используем простой EXPLAIN
                return "EXPLAIN " . $sql;
        }
    }
    
    /**
     * Парсит результат EXPLAIN в зависимости от типа СУБД
     */
    private static function parseExplainResult($result, $db_type)
    {
        switch ($db_type) {
            case 'mysql':
                return self::parseMysqlExplain($result);
            case 'postgresql':
                return self::parsePostgresqlExplain($result);
            case 'sqlite':
                return self::parseSqliteExplain($result);
            default:
                return self::parseGenericExplain($result);
        }
    }
    
    /**
     * Парсит результат EXPLAIN для MySQL
     */
    private static function parseMysqlExplain($result)
    {
        $data = [];
        
        // Для MySQL JSON формата
        if (is_string($result)) {
            $json = json_decode($result, true);
            if ($json && isset($json[0]['query_block'])) {
                $query_block = $json[0]['query_block'];
                $data = self::parseMysqlJsonExplain($query_block);
            }
        } else {
            // Для обычного EXPLAIN
            $data = self::parseGenericExplain($result);
        }
        
        return $data;
    }
    
    /**
     * Парсит JSON результат EXPLAIN для MySQL
     */
    private static function parseMysqlJsonExplain($query_block)
    {
        $data = [
            'tables' => [],
            'cost_info' => [],
            'warnings' => []
        ];
        
        // Анализируем таблицы
        if (isset($query_block['table'])) {
            $tables = is_array($query_block['table']) ? $query_block['table'] : [$query_block['table']];
            
            foreach ($tables as $table) {
                $table_info = [
                    'table_name' => $table['table_name'] ?? 'unknown',
                    'access_type' => $table['access_type'] ?? 'unknown',
                    'key' => $table['key'] ?? null,
                    'key_length' => $table['key_length'] ?? null,
                    'rows_examined' => $table['rows_examined_per_scan'] ?? 0,
                    'rows_produced' => $table['rows_produced_per_join'] ?? 0,
                    'filtered' => $table['filtered'] ?? 0,
                    'cost_info' => $table['cost_info'] ?? [],
                    'used_columns' => $table['used_columns'] ?? [],
                    'attached_condition' => $table['attached_condition'] ?? null
                ];
                
                $data['tables'][] = $table_info;
            }
        }
        
        // Общая информация о стоимости
        if (isset($query_block['cost_info'])) {
            $data['cost_info'] = $query_block['cost_info'];
        }
        
        return $data;
    }
    
    /**
     * Парсит результат EXPLAIN для PostgreSQL
     */
    private static function parsePostgresqlExplain($result)
    {
        $data = [
            'tables' => [],
            'cost_info' => [],
            'warnings' => []
        ];
        
        if (is_string($result)) {
            $json = json_decode($result, true);
            if ($json && isset($json[0]['Plan'])) {
                $plan = $json[0]['Plan'];
                $data = self::parsePostgresqlJsonExplain($plan);
            }
        }
        
        return $data;
    }
    
    /**
     * Парсит JSON результат EXPLAIN для PostgreSQL
     */
    private static function parsePostgresqlJsonExplain($plan)
    {
        $data = [
            'tables' => [],
            'cost_info' => [],
            'warnings' => []
        ];
        
        // Рекурсивно обходим план выполнения
        self::parsePostgresqlPlanNode($plan, $data);
        
        return $data;
    }
    
    /**
     * Рекурсивно парсит узел плана PostgreSQL
     */
    private static function parsePostgresqlPlanNode($node, &$data)
    {
        if (isset($node['Relation Name'])) {
            $table_info = [
                'table_name' => $node['Relation Name'],
                'access_type' => $node['Node Type'],
                'index_name' => $node['Index Name'] ?? null,
                'rows_examined' => $node['Actual Rows'] ?? 0,
                'cost_info' => [
                    'startup_cost' => $node['Startup Cost'] ?? 0,
                    'total_cost' => $node['Total Cost'] ?? 0
                ],
                'filter_condition' => $node['Filter'] ?? null,
                'index_condition' => $node['Index Cond'] ?? null
            ];
            
            $data['tables'][] = $table_info;
        }
        
        // Обрабатываем дочерние узлы
        if (isset($node['Plans']) && is_array($node['Plans'])) {
            foreach ($node['Plans'] as $child) {
                self::parsePostgresqlPlanNode($child, $data);
            }
        }
    }
    
    /**
     * Парсит результат EXPLAIN для SQLite
     */
    private static function parseSqliteExplain($result)
    {
        $data = [
            'tables' => [],
            'cost_info' => [],
            'warnings' => []
        ];
        
        if (is_array($result)) {
            foreach ($result as $row) {
                $table_info = [
                    'table_name' => $row['table'] ?? 'unknown',
                    'access_type' => $row['detail'] ?? 'unknown',
                    'index_name' => null,
                    'rows_examined' => 0,
                    'cost_info' => [],
                    'filter_condition' => null,
                    'index_condition' => null
                ];
                
                // Извлекаем информацию об индексах из detail
                if (isset($row['detail'])) {
                    $detail = $row['detail'];
                    if (strpos($detail, 'USING INDEX') !== false) {
                        preg_match('/USING INDEX (\w+)/', $detail, $matches);
                        if (isset($matches[1])) {
                            $table_info['index_name'] = $matches[1];
                        }
                    }
                }
                
                $data['tables'][] = $table_info;
            }
        }
        
        return $data;
    }
    
    /**
     * Парсит результат EXPLAIN для неизвестных СУБД
     */
    private static function parseGenericExplain($result)
    {
        $data = [
            'tables' => [],
            'cost_info' => [],
            'warnings' => []
        ];
        
        if (is_array($result)) {
            foreach ($result as $row) {
                $table_info = [
                    'table_name' => $row['table'] ?? 'unknown',
                    'access_type' => $row['type'] ?? 'unknown',
                    'index_name' => $row['key'] ?? null,
                    'rows_examined' => $row['rows'] ?? 0,
                    'cost_info' => [],
                    'filter_condition' => null,
                    'index_condition' => null
                ];
                
                $data['tables'][] = $table_info;
            }
        }
        
        return $data;
    }
    
    /**
     * Анализирует план выполнения и генерирует рекомендации
     */
    private static function analyzeExecutionPlan($explain_data, $sql, $connection_name = 'default')
    {
        if (!$explain_data || empty($explain_data['tables'])) {
            return [];
        }
        
        $recommendations = [];
        
        foreach ($explain_data['tables'] as $table_info) {
            $table_name = $table_info['table_name'];
            $access_type = $table_info['access_type'];
            
            // Анализируем тип доступа
            switch (strtoupper($access_type)) {
                case 'ALL':
                case 'FULL TABLE SCAN':
                case 'SEQ SCAN':
                    // Полное сканирование таблицы - нужны индексы
                    $recommendations[] = self::generateFullScanRecommendation($table_name, $table_info, $sql);
                    break;
                    
                case 'INDEX':
                case 'RANGE':
                case 'REF':
                case 'EQ_REF':
                    // Используется индекс - проверяем эффективность
                    $recommendations[] = self::analyzeIndexUsage($table_name, $table_info, $sql);
                    break;
                    
                case 'CONST':
                case 'SYSTEM':
                    // Константный доступ - индекс не нужен
                    break;
                    
                default:
                    // Неизвестный тип доступа
                    $recommendations[] = self::generateUnknownAccessRecommendation($table_name, $table_info, $sql);
            }
        }
        
        return array_filter($recommendations);
    }
    
    /**
     * Генерирует рекомендацию для полного сканирования таблицы
     */
    private static function generateFullScanRecommendation($table_name, $table_info, $sql)
    {
        // Извлекаем колонки из WHERE условий
        $where_columns = self::extractColumnsFromWhere($sql);
        
        if (empty($where_columns)) {
            return null;
        }
        
        return [
            'table' => $table_name,
            'columns' => implode(', ', $where_columns),
            'type' => count($where_columns) > 1 ? 'composite' : 'single',
            'priority' => 'high',
            'reason' => "Полное сканирование таблицы {$table_name} - критично для производительности",
            'usage_count' => 1,
            'status' => 'missing',
            'sql' => "CREATE INDEX idx_{$table_name}_" . implode('_', $where_columns) . " ON {$table_name} (" . implode(', ', $where_columns) . ");",
            'explain_info' => [
                'access_type' => $table_info['access_type'] ?? 'unknown',
                'rows_examined' => $table_info['rows_examined'] ?? 0,
                'cost' => $table_info['cost_info'] ?? []
            ]
        ];
    }
    
    /**
     * Анализирует использование существующего индекса
     */
    private static function analyzeIndexUsage($table_name, $table_info, $sql)
    {
        // Если индекс используется эффективно, рекомендация не нужна
        if ($table_info['rows_examined'] < 100) {
            return null;
        }
        
        // Если индекс используется неэффективно, предлагаем улучшения
        return [
            'table' => $table_name,
            'columns' => $table_info['index_name'] ?? 'unknown',
            'type' => 'single',
            'priority' => 'medium',
            'reason' => "Индекс используется неэффективно - обработано {$table_info['rows_examined']} строк",
            'usage_count' => 1,
            'status' => 'optimize',
            'sql' => "-- Рассмотрите оптимизацию индекса " . ($table_info['index_name'] ?? 'unknown'),
            'explain_info' => [
                'access_type' => $table_info['access_type'] ?? 'unknown',
                'index_name' => $table_info['index_name'] ?? null,
                'rows_examined' => $table_info['rows_examined'] ?? 0,
                'cost' => $table_info['cost_info'] ?? []
            ]
        ];
    }
    
    /**
     * Генерирует рекомендацию для неизвестного типа доступа
     */
    private static function generateUnknownAccessRecommendation($table_name, $table_info, $sql)
    {
        return [
            'table' => $table_name,
            'columns' => 'unknown',
            'type' => 'single',
            'priority' => 'low',
            'reason' => "Неизвестный тип доступа: " . ($table_info['access_type'] ?? 'unknown'),
            'usage_count' => 1,
            'status' => 'unknown',
            'sql' => "-- Требуется дополнительный анализ для таблицы {$table_name}",
            'explain_info' => [
                'access_type' => $table_info['access_type'] ?? 'unknown',
                'rows_examined' => $table_info['rows_examined'] ?? 0,
                'cost' => $table_info['cost_info'] ?? []
            ]
        ];
    }
    
    /**
     * Извлекает колонки из WHERE условий SQL
     */
    private static function extractColumnsFromWhere($sql)
    {
        $columns = [];
        
        if (preg_match('/WHERE\s+(.+?)(?:\s+GROUP\s+BY|\s+ORDER\s+BY|\s+HAVING|\s+LIMIT|$)/i', $sql, $matches)) {
            $where_clause = $matches[1];
            
            // Ищем колонки в условиях
            if (preg_match_all('/(\w+)\s*[=<>!]/i', $where_clause, $column_matches)) {
                $columns = array_merge($columns, $column_matches[1]);
            }
        }
        
        return array_unique($columns);
    }
    
    /**
     * Устанавливает порог для детекции медленных запросов
     */
    public static function setSlowQueryThreshold($threshold_ms)
    {
        self::$slow_query_threshold = $threshold_ms;
    }
    
    /**
     * Устанавливает максимальное количество записей производительности в памяти
     */
    public static function setMaxPerformanceEntries($max_entries)
    {
        self::$max_performance_entries = $max_entries;
    }
    
    /**
     * Устанавливает максимальное количество выполнений на запрос
     */
    public static function setMaxExecutionsPerQuery($max_executions)
    {
        self::$max_executions_per_query = $max_executions;
    }
    
    /**
     * Получает порог для детекции медленных запросов
     */
    public static function getSlowQueryThreshold()
    {
        return self::$slow_query_threshold;
    }
    
    /**
     * Анализирует производительность запроса
     */
    public static function analyzeQueryPerformance($sql, $execution_time, $connection_name = 'default')
    {
        $query_id = md5($sql . $connection_name);
        $is_slow = $execution_time > self::$slow_query_threshold;
        
        // Нормализуем SQL для группировки похожих запросов
        $normalized_sql = self::normalizeSqlForPerformance($sql);
        $normalized_id = md5($normalized_sql . $connection_name);
        
        $performance_data = [
            'query_id' => $query_id,
            'normalized_id' => $normalized_id,
            'sql' => $sql,
            'normalized_sql' => $normalized_sql,
            'execution_time' => $execution_time,
            'connection' => $connection_name,
            'is_slow' => $is_slow,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Проверяем лимиты памяти
        self::checkMemoryLimits();
        
        // Сохраняем данные производительности
        if (!isset(self::$performance_data[$normalized_id])) {
            self::$performance_data[$normalized_id] = [
                'normalized_sql' => $normalized_sql,
                'executions' => [],
                'total_time' => 0,
                'avg_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0,
                'slow_count' => 0,
                'connection' => $connection_name,
                'last_execution' => 0
            ];
        }
        
        $group = &self::$performance_data[$normalized_id];
        
        // Ограничиваем количество выполнений на запрос
        if (count($group['executions']) >= self::$max_executions_per_query) {
            // Удаляем самое старое выполнение
            array_shift($group['executions']);
        }
        
        $group['executions'][] = $performance_data;
        $execution_time_float = is_numeric($execution_time) ? (float)$execution_time : 0;
        $group['total_time'] += $execution_time_float;
        $group['avg_time'] = $group['total_time'] / count($group['executions']);
        $group['min_time'] = min($group['min_time'], $execution_time_float);
        $group['max_time'] = max($group['max_time'], $execution_time_float);
        $group['last_execution'] = $performance_data['timestamp'];
        
        if ($is_slow) {
            $group['slow_count']++;
        }
        
        return $performance_data;
    }
    
    /**
     * Нормализует SQL для группировки похожих запросов
     */
    private static function normalizeSqlForPerformance($sql)
    {
        // Убираем лишние пробелы и переводы строк
        $normalized = preg_replace('/\s+/', ' ', trim($sql));
        
        // Заменяем значения в кавычках на плейсхолдеры
        $normalized = preg_replace('/\'[^\']*\'/', '?', $normalized);
        $normalized = preg_replace('/"[^"]*"/', '?', $normalized);
        
        // Заменяем числовые значения на плейсхолдеры
        $normalized = preg_replace('/\b\d+\b/', '?', $normalized);
        
        // Заменяем IN списки на плейсхолдеры
        $normalized = preg_replace('/IN\s*\([^)]+\)/i', 'IN (?)', $normalized);
        
        // Заменяем LIMIT значения
        $normalized = preg_replace('/LIMIT\s+\d+/i', 'LIMIT ?', $normalized);
        $normalized = preg_replace('/OFFSET\s+\d+/i', 'OFFSET ?', $normalized);
        
        return $normalized;
    }
    
    /**
     * Получает данные производительности
     */
    public static function getPerformanceData($grouped = true)
    {
        if ($grouped) {
            return self::$performance_data;
        }
        
        // Возвращаем все отдельные выполнения
        $all_executions = [];
        foreach (self::$performance_data as $group) {
            $all_executions = array_merge($all_executions, $group['executions']);
        }
        
        // Сортируем по времени выполнения (от большего к меньшему)
        usort($all_executions, function($a, $b) {
            return $b['execution_time'] <=> $a['execution_time'];
        });
        
        return $all_executions;
    }
    
    /**
     * Получает медленные запросы
     */
    public static function getSlowQueries($limit = 10)
    {
        $slow_queries = [];
        
        foreach (self::$performance_data as $group) {
            if ($group['slow_count'] > 0) {
                $slow_queries[] = [
                    'normalized_sql' => $group['normalized_sql'],
                    'total_executions' => count($group['executions']),
                    'slow_executions' => $group['slow_count'],
                    'avg_time' => $group['avg_time'],
                    'max_time' => $group['max_time'],
                    'total_time' => $group['total_time'],
                    'connection' => $group['connection'],
                    'last_execution' => $group['last_execution']
                ];
            }
        }
        
        // Сортируем по количеству медленных выполнений
        usort($slow_queries, function($a, $b) {
            return $b['slow_executions'] <=> $a['slow_executions'];
        });
        
        return array_slice($slow_queries, 0, $limit);
    }
    
    /**
     * Получает статистику производительности
     */
    public static function getPerformanceStats()
    {
        $total_queries = 0;
        $total_time = 0;
        $slow_queries = 0;
        $avg_time = 0;
        $max_time = 0;
        $min_time = PHP_FLOAT_MAX;
        
        foreach (self::$performance_data as $group) {
            $total_queries += count($group['executions']);
            $total_time += $group['total_time'];
            $slow_queries += $group['slow_count'];
            $max_time = max($max_time, $group['max_time']);
            $min_time = min($min_time, $group['min_time']);
        }
        
        if ($total_queries > 0) {
            $avg_time = $total_time / $total_queries;
        }
        
        if ($min_time === PHP_FLOAT_MAX) {
            $min_time = 0;
        }
        
        return [
            'total_queries' => $total_queries,
            'total_time' => $total_time,
            'avg_time' => $avg_time,
            'min_time' => $min_time,
            'max_time' => $max_time,
            'slow_queries' => $slow_queries,
            'slow_query_percentage' => $total_queries > 0 ? ($slow_queries / $total_queries) * 100 : 0,
            'unique_queries' => count(self::$performance_data),
            'slow_query_threshold' => self::$slow_query_threshold
        ];
    }
    
    /**
     * Анализирует тренды производительности
     */
    public static function analyzePerformanceTrends($time_window = 3600) // 1 час
    {
        $current_time = microtime(true);
        $trends = [];
        
        foreach (self::$performance_data as $group) {
            $recent_executions = array_filter($group['executions'], function($execution) use ($current_time, $time_window) {
                return ($current_time - $execution['timestamp']) <= $time_window;
            });
            
            if (count($recent_executions) > 1) {
                $recent_times = array_column($recent_executions, 'execution_time');
                $trend = self::calculateTrend($recent_times);
                
                $trends[] = [
                    'normalized_sql' => $group['normalized_sql'],
                    'trend' => $trend,
                    'recent_avg' => array_sum($recent_times) / count($recent_times),
                    'recent_count' => count($recent_executions),
                    'connection' => $group['connection']
                ];
            }
        }
        
        // Сортируем по ухудшению производительности
        usort($trends, function($a, $b) {
            return $b['trend'] <=> $a['trend'];
        });
        
        return $trends;
    }
    
    /**
     * Вычисляет тренд производительности (положительное значение = ухудшение)
     */
    private static function calculateTrend($times)
    {
        if (count($times) < 2) {
            return 0;
        }
        
        $n = count($times);
        $x = range(1, $n);
        $y = array_map(function($time) {
            return is_numeric($time) ? (float)$time : 0;
        }, $times);
        
        // Простая линейная регрессия
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        
        return $slope;
    }
    
    /**
     * Генерирует рекомендации по производительности
     */
    public static function generatePerformanceRecommendations()
    {
        $recommendations = [];
        $stats = self::getPerformanceStats();
        $slow_queries = self::getSlowQueries(5);
        $trends = self::analyzePerformanceTrends();
        
        // Рекомендации на основе общей статистики
        if ($stats['slow_query_percentage'] > 20) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Высокий процент медленных запросов',
                'description' => sprintf("%.1f%% запросов выполняются медленнее %dms", $stats['slow_query_percentage'], $stats['slow_query_threshold']),
                'suggestion' => 'Проверьте индексы и оптимизируйте запросы'
            ];
        }
        
        if ($stats['avg_time'] > $stats['slow_query_threshold'] / 2) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Среднее время выполнения запросов высокое',
                'description' => sprintf("Среднее время: %.2fms", $stats['avg_time']),
                'suggestion' => 'Рассмотрите оптимизацию наиболее частых запросов'
            ];
        }
        
        // Рекомендации на основе медленных запросов
        foreach ($slow_queries as $query) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Медленный запрос обнаружен',
                'description' => sprintf("Запрос выполняется в среднем %.2fms (%d медленных выполнений)", $query['avg_time'], $query['slow_executions']),
                'suggestion' => "Оптимизируйте запрос: {$query['normalized_sql']}",
                'sql' => $query['normalized_sql']
            ];
        }
        
        // Рекомендации на основе трендов
        foreach (array_slice($trends, 0, 3) as $trend) {
            if ($trend['trend'] > 0.1) { // Ухудшение производительности
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => 'Ухудшение производительности',
                    'description' => sprintf("Запрос замедляется со временем (тренд: +%.3f)", $trend['trend']),
                    'suggestion' => "Мониторьте запрос: {$trend['normalized_sql']}",
                    'sql' => $trend['normalized_sql']
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Проверяет лимиты памяти и очищает старые данные при необходимости
     */
    private static function checkMemoryLimits()
    {
        // Если превышен лимит записей, удаляем самые старые
        if (count(self::$performance_data) > self::$max_performance_entries) {
            // Сортируем по времени последнего выполнения
            uasort(self::$performance_data, function($a, $b) {
                return $a['last_execution'] <=> $b['last_execution'];
            });
            
            // Удаляем 20% самых старых записей
            $to_remove = (int)(self::$max_performance_entries * 0.2);
            $keys = array_keys(self::$performance_data);
            for ($i = 0; $i < $to_remove; $i++) {
                unset(self::$performance_data[$keys[$i]]);
            }
        }
        
        // Проверяем использование памяти
        $memory_usage = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit !== '-1') {
            $memory_limit_bytes = self::convertToBytes($memory_limit);
            $memory_percent = ($memory_usage / $memory_limit_bytes) * 100;
            
            // Если используется больше 80% памяти, очищаем половину данных
            if ($memory_percent > 80) {
                self::cleanupOldData(0.5);
            }
        }
    }
    
    /**
     * Конвертирует строку лимита памяти в байты
     */
    private static function convertToBytes($memory_limit)
    {
        $memory_limit = trim($memory_limit);
        $last = strtolower($memory_limit[strlen($memory_limit) - 1]);
        $memory_limit = (int)$memory_limit;
        
        switch ($last) {
            case 'g':
                $memory_limit *= 1024;
            case 'm':
                $memory_limit *= 1024;
            case 'k':
                $memory_limit *= 1024;
        }
        
        return $memory_limit;
    }
    
    /**
     * Очищает старые данные производительности
     */
    private static function cleanupOldData($ratio = 0.5)
    {
        $current_time = microtime(true);
        $cutoff_time = $current_time - (3600 * 24); // 24 часа назад
        
        $to_remove = [];
        foreach (self::$performance_data as $key => $group) {
            // Удаляем группы, которые не выполнялись более 24 часов
            if ($group['last_execution'] < $cutoff_time) {
                $to_remove[] = $key;
            }
        }
        
        // Если нужно удалить больше данных, удаляем самые старые
        if (count($to_remove) < count(self::$performance_data) * $ratio) {
            uasort(self::$performance_data, function($a, $b) {
                return $a['last_execution'] <=> $b['last_execution'];
            });
            
            $keys = array_keys(self::$performance_data);
            $to_remove_count = (int)(count(self::$performance_data) * $ratio);
            for ($i = 0; $i < $to_remove_count; $i++) {
                $to_remove[] = $keys[$i];
            }
        }
        
        foreach ($to_remove as $key) {
            unset(self::$performance_data[$key]);
        }
    }
    
    /**
     * Очищает данные производительности
     */
    public static function clearPerformanceData()
    {
        self::$performance_data = [];
    }
    
    /**
     * Анализирует WHERE условия с улучшенным парсингом
     */
    private static function analyzeWhereConditions($where_clause)
    {
        if (empty($where_clause)) {
            return [];
        }
        
        $conditions = [];
        
        // Нормализуем WHERE условие
        $where_clause = preg_replace('/\s+/', ' ', trim($where_clause));
        
        // Разбиваем на отдельные условия, учитывая скобки и OR/AND
        $condition_parts = self::splitWhereConditions($where_clause);
        
        foreach ($condition_parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // Анализируем каждое условие
            $condition = self::parseSingleCondition($part);
            if ($condition) {
                $conditions[] = $condition;
            }
        }
        
        return $conditions;
    }
    
    /**
     * Разбивает WHERE условия на части, учитывая скобки и логические операторы
     */
    private static function splitWhereConditions($where_clause)
    {
        $conditions = [];
        $current = '';
        $depth = 0;
        $in_quotes = false;
        $quote_char = '';
        
        for ($i = 0; $i < strlen($where_clause); $i++) {
            $char = $where_clause[$i];
            
            // Обрабатываем кавычки
            if (($char === "'" || $char === '"') && !$in_quotes) {
                $in_quotes = true;
                $quote_char = $char;
            } elseif ($char === $quote_char && $in_quotes) {
                $in_quotes = false;
                $quote_char = '';
            }
            
            // Обрабатываем скобки только если не в кавычках
            if (!$in_quotes) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                } elseif (($char === 'A' || $char === 'O') && $depth === 0) {
                    // Проверяем на AND/OR
                    $remaining = substr($where_clause, $i);
                    if (preg_match('/^(AND|OR)\s+/i', $remaining, $matches)) {
                        if (!empty($current)) {
                            $conditions[] = trim($current);
                            $current = '';
                        }
                        $i += strlen($matches[0]) - 1; // Пропускаем AND/OR
                        continue;
                    }
                }
            }
            
            $current .= $char;
        }
        
        if (!empty($current)) {
            $conditions[] = trim($current);
        }
        
        return $conditions;
    }
    
    /**
     * Парсит отдельное условие WHERE
     */
    private static function parseSingleCondition($condition)
    {
        $condition = trim($condition);
        
        // Убираем внешние скобки
        while (preg_match('/^\((.*)\)$/', $condition, $matches)) {
            $condition = $matches[1];
        }
        
        // Ищем различные типы условий
        
        // 1. Равенство и сравнение (=, !=, <>, >, <, >=, <=)
        if (preg_match('/^(\w+(?:\.\w+)?)\s*([=<>!]+)\s*[^\s]+/i', $condition, $matches)) {
            return [
                'column' => self::extractColumnName($matches[1]),
                'type' => 'equality',
                'operator' => $matches[2]
            ];
        }
        
        // 2. LIKE
        if (preg_match('/^(\w+(?:\.\w+)?)\s+LIKE\s+/i', $condition, $matches)) {
            return [
                'column' => self::extractColumnName($matches[1]),
                'type' => 'like',
                'operator' => 'LIKE'
            ];
        }
        
        // 3. IN
        if (preg_match('/^(\w+(?:\.\w+)?)\s+IN\s*\(/i', $condition, $matches)) {
            return [
                'column' => self::extractColumnName($matches[1]),
                'type' => 'in',
                'operator' => 'IN'
            ];
        }
        
        // 4. BETWEEN
        if (preg_match('/^(\w+(?:\.\w+)?)\s+BETWEEN\s+/i', $condition, $matches)) {
            return [
                'column' => self::extractColumnName($matches[1]),
                'type' => 'range',
                'operator' => 'BETWEEN'
            ];
        }
        
        // 5. IS NULL / IS NOT NULL
        if (preg_match('/^(\w+(?:\.\w+)?)\s+IS\s+(?:NOT\s+)?NULL/i', $condition, $matches)) {
            return [
                'column' => self::extractColumnName($matches[1]),
                'type' => 'null_check',
                'operator' => 'IS NULL'
            ];
        }
        
        // 6. EXISTS
        if (preg_match('/^EXISTS\s*\(/i', $condition)) {
            return [
                'column' => null,
                'type' => 'exists',
                'operator' => 'EXISTS'
            ];
        }
        
        // 7. NOT EXISTS
        if (preg_match('/^NOT\s+EXISTS\s*\(/i', $condition)) {
            return [
                'column' => null,
                'type' => 'not_exists',
                'operator' => 'NOT EXISTS'
            ];
        }
        
        return null;
    }
    
    /**
     * Извлекает имя колонки из выражения (убирает алиас таблицы)
     */
    private static function extractColumnName($expression)
    {
        // Если есть точка, берем часть после точки
        if (strpos($expression, '.') !== false) {
            $parts = explode('.', $expression);
            return end($parts);
        }
        
        return $expression;
    }
    
    /**
     * Анализирует JOIN условия с улучшенным парсингом
     */
    private static function analyzeJoinConditions($joins)
    {
        $conditions = [];
        
        foreach ($joins as $join) {
            $condition = $join['condition'];
            $table = $join['table'];
            
            if (empty($condition)) continue;
            
            // Разбиваем условие на части по AND/OR
            $condition_parts = self::splitWhereConditions($condition);
            
            foreach ($condition_parts as $part) {
                $part = trim($part);
                if (empty($part)) continue;
                
                // Ищем различные типы JOIN условий
                
                // 1. Равенство между таблицами: table1.column1 = table2.column2
                if (preg_match_all('/(\w+)\.(\w+)\s*=\s*(\w+)\.(\w+)/i', $part, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        // Добавляем условие для обеих таблиц
                        $conditions[] = [
                            'table' => $match[1],
                            'column' => $match[2],
                            'type' => 'join',
                            'operator' => '='
                        ];
                        $conditions[] = [
                            'table' => $match[3],
                            'column' => $match[4],
                            'type' => 'join',
                            'operator' => '='
                        ];
                    }
                }
                
                // 2. Сравнение с константой: table.column = value
                elseif (preg_match_all('/(\w+)\.(\w+)\s*([=<>!]+)\s*[^\s]+/i', $part, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $conditions[] = [
                            'table' => $match[1],
                            'column' => $match[2],
                            'type' => 'join_condition',
                            'operator' => $match[3]
                        ];
                    }
                }
                
                // 3. BETWEEN в JOIN
                elseif (preg_match_all('/(\w+)\.(\w+)\s+BETWEEN\s+/i', $part, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $conditions[] = [
                            'table' => $match[1],
                            'column' => $match[2],
                            'type' => 'join_condition',
                            'operator' => 'BETWEEN'
                        ];
                    }
                }
                
                // 4. IN в JOIN
                elseif (preg_match_all('/(\w+)\.(\w+)\s+IN\s*\(/i', $part, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $conditions[] = [
                            'table' => $match[1],
                            'column' => $match[2],
                            'type' => 'join_condition',
                            'operator' => 'IN'
                        ];
                    }
                }
                
                // 5. LIKE в JOIN
                elseif (preg_match_all('/(\w+)\.(\w+)\s+LIKE\s+/i', $part, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $conditions[] = [
                            'table' => $match[1],
                            'column' => $match[2],
                            'type' => 'join_condition',
                            'operator' => 'LIKE'
                        ];
                    }
                }
            }
        }
        
        return $conditions;
    }
    
    /**
     * Анализирует ORDER BY с улучшенным парсингом
     */
    private static function analyzeOrderBy($order_by)
    {
        if (empty($order_by)) {
            return [];
        }
        
        $columns = [];
        $order_parts = self::splitByComma($order_by);
        
        foreach ($order_parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // Убираем ASC/DESC
            $part = preg_replace('/\s+(ASC|DESC)$/i', '', $part);
            
            // Извлекаем колонку
            $column = self::extractColumnFromExpression($part);
            if ($column) {
                $columns[] = [
                    'column' => $column,
                    'type' => 'order'
                ];
            }
        }
        
        return $columns;
    }
    
    /**
     * Анализирует GROUP BY с улучшенным парсингом
     */
    private static function analyzeGroupBy($group_by)
    {
        if (empty($group_by)) {
            return [];
        }
        
        $columns = [];
        $group_parts = self::splitByComma($group_by);
        
        foreach ($group_parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // Извлекаем колонку
            $column = self::extractColumnFromExpression($part);
            if ($column) {
                $columns[] = [
                    'column' => $column,
                    'type' => 'group'
                ];
            }
        }
        
        return $columns;
    }
    
    /**
     * Анализирует HAVING условия
     */
    private static function analyzeHavingConditions($having_clause)
    {
        if (empty($having_clause)) {
            return [];
        }
        
        // HAVING анализируется аналогично WHERE
        return self::analyzeWhereConditions($having_clause);
    }
    
    /**
     * Извлекает колонку из выражения (убирает функции, алиасы таблиц)
     */
    private static function extractColumnFromExpression($expression)
    {
        $expression = trim($expression);
        
        // Убираем алиас таблицы: table.column -> column
        if (preg_match('/^(\w+)\.(\w+)$/', $expression, $matches)) {
            return $matches[2];
        }
        
        // Убираем функции: COUNT(column) -> column, MAX(column) -> column
        if (preg_match('/^\w+\s*\(\s*(\w+(?:\.\w+)?)\s*\)$/', $expression, $matches)) {
            return self::extractColumnName($matches[1]);
        }
        
        // Простое имя колонки
        if (preg_match('/^(\w+)$/', $expression, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Генерирует рекомендации по индексам
     */
    private static function generateIndexRecommendations($tables, $where_conditions, $join_conditions, $order_columns, $group_columns, $having_conditions = [], $subquery_conditions = [])
    {
        $recommendations = [];
        
        foreach ($tables as $table_info) {
            $table_name = is_array($table_info) ? $table_info['name'] : $table_info;
            $table_conditions = [];
            $table_joins = [];
            $table_orders = [];
            $table_groups = [];
            $table_having = [];
            $table_subqueries = [];
            
            // Собираем условия для текущей таблицы
            foreach ($where_conditions as $condition) {
                $table_conditions[] = $condition;
            }
            
            foreach ($join_conditions as $condition) {
                if ($condition['table'] === $table_name) {
                    $table_joins[] = $condition;
                }
            }
            
            foreach ($order_columns as $column) {
                $table_orders[] = $column;
            }
            
            foreach ($group_columns as $column) {
                $table_groups[] = $column;
            }
            
            foreach ($having_conditions as $condition) {
                $table_having[] = $condition;
            }
            
            foreach ($subquery_conditions as $condition) {
                $table_subqueries[] = $condition;
            }
            
            // Генерируем рекомендации для таблицы
            $table_recommendations = self::generateTableIndexRecommendations(
                $table_name,
                $table_conditions,
                $table_joins,
                $table_orders,
                $table_groups,
                $table_having,
                $table_subqueries
            );
            
            $recommendations = array_merge($recommendations, $table_recommendations);
        }
        
        return $recommendations;
    }
    
    /**
     * Генерирует рекомендации по индексам для конкретной таблицы
     */
    private static function generateTableIndexRecommendations($table, $conditions, $joins, $orders, $groups, $having = [], $subqueries = [])
    {
        $recommendations = [];
        $all_columns = [];
        
        // Собираем все колонки
        foreach ($conditions as $condition) {
            if (!empty($condition['column'])) {
                $all_columns[] = $condition['column'];
            }
        }
        
        foreach ($joins as $join) {
            if (!empty($join['column'])) {
                $all_columns[] = $join['column'];
            }
        }
        
        foreach ($orders as $order) {
            if (!empty($order['column'])) {
                $all_columns[] = $order['column'];
            }
        }
        
        foreach ($groups as $group) {
            if (!empty($group['column'])) {
                $all_columns[] = $group['column'];
            }
        }
        
        foreach ($having as $condition) {
            if (!empty($condition['column'])) {
                $all_columns[] = $condition['column'];
            }
        }
        
        foreach ($subqueries as $condition) {
            if (!empty($condition['column'])) {
                $all_columns[] = $condition['column'];
            }
        }
        
        // Убираем дубликаты
        $all_columns = array_unique($all_columns);
        
        if (empty($all_columns)) {
            return $recommendations;
        }
        
        // Анализируем существующие индексы
        self::analyzeExistingIndexes();
        
        // Создаем рекомендации по приоритету
        $priority_columns = [];
        $secondary_columns = [];
        
        // Высокий приоритет для WHERE условий с равенством
        foreach ($conditions as $condition) {
            if ($condition['type'] === 'equality') {
                $priority_columns[] = $condition['column'];
            } else {
                $secondary_columns[] = $condition['column'];
            }
        }
        
        // Высокий приоритет для JOIN условий
        foreach ($joins as $join) {
            if (!in_array($join['column'], $priority_columns)) {
                $priority_columns[] = $join['column'];
            }
        }
        
        // Средний приоритет для ORDER BY
        foreach ($orders as $order) {
            if (!in_array($order['column'], $priority_columns) && !in_array($order['column'], $secondary_columns)) {
                $secondary_columns[] = $order['column'];
            }
        }
        
        // Низкий приоритет для GROUP BY
        foreach ($groups as $group) {
            if (!in_array($group['column'], $priority_columns) && !in_array($group['column'], $secondary_columns)) {
                $secondary_columns[] = $group['column'];
            }
        }
        
        // Создаем составной индекс для высокоприоритетных колонок
        if (count($priority_columns) > 1) {
            $columns_str = implode(', ', $priority_columns);
            $existing_index = self::indexExists($table, $priority_columns);
            
            if (!$existing_index) {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $columns_str,
                    'type' => 'composite',
                    'priority' => 'high',
                    'reason' => 'WHERE условия с равенством и JOIN условия',
                    'status' => 'missing'
                ];
            } else {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $columns_str,
                    'type' => 'composite',
                    'priority' => 'high',
                    'reason' => 'WHERE условия с равенством и JOIN условия',
                    'status' => 'exists',
                    'existing_index' => $existing_index
                ];
            }
        } elseif (count($priority_columns) === 1) {
            $existing_index = self::indexExists($table, $priority_columns[0]);
            
            if (!$existing_index) {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $priority_columns[0],
                    'type' => 'single',
                    'priority' => 'high',
                    'reason' => 'WHERE условие с равенством или JOIN условие',
                    'status' => 'missing'
                ];
            } else {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $priority_columns[0],
                    'type' => 'single',
                    'priority' => 'high',
                    'reason' => 'WHERE условие с равенством или JOIN условие',
                    'status' => 'exists',
                    'existing_index' => $existing_index
                ];
            }
        }
        
        // Создаем отдельные индексы для остальных колонок
        foreach ($secondary_columns as $column) {
            $reason = 'ORDER BY или GROUP BY колонка';
            if (in_array($column, array_column($conditions, 'column'))) {
                $reason = 'WHERE условие';
            }
            
            $existing_index = self::indexExists($table, $column);
            
            if (!$existing_index) {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $column,
                    'type' => 'single',
                    'priority' => 'medium',
                    'reason' => $reason,
                    'status' => 'missing'
                ];
            } else {
                $recommendations[] = [
                    'table' => $table,
                    'columns' => $column,
                    'type' => 'single',
                    'priority' => 'medium',
                    'reason' => $reason,
                    'status' => 'exists',
                    'existing_index' => $existing_index
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Получает все проанализированные запросы
     */
    public static function getAnalyzedQueries()
    {
        return self::$queries;
    }
    
    /**
     * Получает рекомендации по индексам
     */
    public static function getIndexRecommendations($include_existing = false)
    {
        // Сортируем по приоритету и количеству использований
        $recommendations = self::$index_recommendations;
        
        // Фильтруем только недостающие индексы, если не запрошены все
        if (!$include_existing) {
            $recommendations = array_filter($recommendations, function($rec) {
                return !isset($rec['status']) || $rec['status'] === 'missing';
            });
        }
        
        uasort($recommendations, function($a, $b) {
            $priority_order = ['high' => 3, 'medium' => 2, 'low' => 1];
            
            if ($priority_order[$a['priority']] !== $priority_order[$b['priority']]) {
                return $priority_order[$b['priority']] - $priority_order[$a['priority']];
            }
            
            return $b['usage_count'] - $a['usage_count'];
        });
        
        return $recommendations;
    }
    
    /**
     * Получает все рекомендации (включая существующие индексы)
     */
    public static function getAllIndexRecommendations()
    {
        return self::getIndexRecommendations(true);
    }
    
    /**
     * Анализирует существующие индексы в базе данных
     */
    public static function analyzeExistingIndexes($connection_name = 'default')
    {
        if (self::$indexes_analyzed) {
            return;
        }
        
        try {
            // Проверяем, доступен ли класс DB и Config
            if (!class_exists('Imy\Core\DB') || !class_exists('Imy\Core\Config')) {
                self::$indexes_analyzed = true;
                return;
            }
            
            $db = DB::getInstance($connection_name);
            $pdo = $db->getConnection();
            
            // Получаем информацию о существующих индексах
            $indexes_query = "
                SELECT 
                    TABLE_NAME,
                    INDEX_NAME,
                    COLUMN_NAME,
                    SEQ_IN_INDEX,
                    NON_UNIQUE,
                    INDEX_TYPE
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE()
                ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
            ";
            
            $stmt = $pdo->query($indexes_query);
            $raw_indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Группируем индексы по таблицам и именам
            $grouped_indexes = [];
            foreach ($raw_indexes as $index) {
                $table = $index['TABLE_NAME'];
                $name = $index['INDEX_NAME'];
                
                if (!isset($grouped_indexes[$table])) {
                    $grouped_indexes[$table] = [];
                }
                
                if (!isset($grouped_indexes[$table][$name])) {
                    $grouped_indexes[$table][$name] = [
                        'name' => $name,
                        'columns' => [],
                        'is_unique' => $index['NON_UNIQUE'] == 0,
                        'type' => $index['INDEX_TYPE'],
                        'is_primary' => $name === 'PRIMARY'
                    ];
                }
                
                $grouped_indexes[$table][$name]['columns'][] = $index['COLUMN_NAME'];
            }
            
            // Преобразуем в удобный формат
            foreach ($grouped_indexes as $table => $indexes) {
                foreach ($indexes as $index) {
                    $columns_str = implode(', ', $index['columns']);
                    $key = $table . '.' . $columns_str;
                    
                    self::$existing_indexes[$key] = [
                        'table' => $table,
                        'name' => $index['name'],
                        'columns' => $columns_str,
                        'column_array' => $index['columns'],
                        'is_unique' => $index['is_unique'],
                        'type' => $index['type'],
                        'is_primary' => $index['is_primary']
                    ];
                }
            }
            
            self::$indexes_analyzed = true;
            
        } catch (\Exception $e) {
            // Если не удалось получить информацию об индексах, продолжаем без неё
            self::$indexes_analyzed = true;
        }
    }
    
    /**
     * Проверяет, существует ли индекс для указанных колонок
     */
    public static function indexExists($table, $columns)
    {
        self::analyzeExistingIndexes();
        
        $columns_str = is_array($columns) ? implode(', ', $columns) : $columns;
        $key = $table . '.' . $columns_str;
        
        // Прямое совпадение
        if (isset(self::$existing_indexes[$key])) {
            return self::$existing_indexes[$key];
        }
        
        // Проверяем частичные совпадения (например, если есть индекс на (a, b, c), 
        // а мы ищем (a, b))
        foreach (self::$existing_indexes as $index) {
            if ($index['table'] === $table) {
                $existing_columns = $index['column_array'];
                $search_columns = is_array($columns) ? $columns : explode(', ', $columns);
                
                // Проверяем, начинается ли существующий индекс с наших колонок
                if (count($existing_columns) >= count($search_columns)) {
                    $matches = true;
                    for ($i = 0; $i < count($search_columns); $i++) {
                        if (trim($existing_columns[$i]) !== trim($search_columns[$i])) {
                            $matches = false;
                            break;
                        }
                    }
                    if ($matches) {
                        return $index;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Получает все существующие индексы
     */
    public static function getExistingIndexes()
    {
        self::analyzeExistingIndexes();
        return self::$existing_indexes;
    }
    
    /**
     * Получает индексы для конкретной таблицы
     */
    public static function getTableIndexes($table)
    {
        self::analyzeExistingIndexes();
        
        $table_indexes = [];
        foreach (self::$existing_indexes as $index) {
            if ($index['table'] === $table) {
                $table_indexes[] = $index;
            }
        }
        
        return $table_indexes;
    }
    
    /**
     * Очищает собранные данные
     */
    public static function clear()
    {
        self::$queries = [];
        self::$index_recommendations = [];
        self::$existing_indexes = [];
        self::$indexes_analyzed = false;
    }
    
    /**
     * Получает статистику анализа
     */
    public static function getStats()
    {
        $missing_recommendations = array_filter(self::$index_recommendations, function($r) {
            return !isset($r['status']) || $r['status'] === 'missing';
        });
        
        $existing_recommendations = array_filter(self::$index_recommendations, function($r) {
            return isset($r['status']) && $r['status'] === 'exists';
        });
        
        return [
            'analyzed_queries' => count(self::$queries),
            'total_recommendations' => count(self::$index_recommendations),
            'missing_recommendations' => count($missing_recommendations),
            'existing_recommendations' => count($existing_recommendations),
            'high_priority_missing' => count(array_filter($missing_recommendations, function($r) {
                return $r['priority'] === 'high';
            })),
            'medium_priority_missing' => count(array_filter($missing_recommendations, function($r) {
                return $r['priority'] === 'medium';
            })),
            'existing_indexes_count' => count(self::$existing_indexes)
        ];
    }
}

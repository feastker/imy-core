<?php
namespace Imy\Core;

class Request
{
    private $get;
    private $post;
    private $server;
    private $cookies;
    private $files;
    private $headers;
    private $session;
    private $body;

    public function __construct($csrfCheck = false)
    {
        $this->get = $this->sanitizeInput($_GET);
        $this->post = $this->sanitizeInput($_POST);
        $this->server = $_SERVER;
        $this->cookies = $this->sanitizeInput($_COOKIE);
        $this->files = $this->mapFiles($_FILES);
        $this->session = $_SESSION;
        $this->headers = $this->extractHeaders($this->server);
        $this->body = $this->sanitizeInput(file_get_contents('php://input'));

        if($csrfCheck)
            $this->validateCsrfToken();
    }

    private function sanitizeInput($input)
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->sanitizeInput($value);
            }
        } else {
            $input = trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
        }
        return $input;
    }

    private function mapFiles($filesArray)
    {
        $files = [];
        foreach ($filesArray as $input => $fileInfo) {
            if (is_array($fileInfo['name'])) {
                foreach ($fileInfo['name'] as $index => $name) {
                    $files[$input][$index] = [
                        'name'     => $fileInfo['name'][$index],
                        'type'     => $fileInfo['type'][$index],
                        'tmp_name' => $fileInfo['tmp_name'][$index],
                        'error'    => $fileInfo['error'][$index],
                        'size'     => $fileInfo['size'][$index]
                    ];
                }
            } else {
                $files[$input] = $fileInfo;
            }
        }
        return $files;
    }

    private function extractHeaders($server)
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
            }
        }
        return $headers;
    }

    private function validateCsrfToken()
    {
        // Если запрос не предполагает изменения данных, проверка CSRF-токена не требуется
        if ($this->isGet()) {
            return;
        }

        // Извлечение токена из сессии и из отправленной формы
        $sessionToken = $this->session['csrf_token'] ?? null;
        $formToken = $this->post['csrf_token'] ?? $this->get['csrf_token'] ?? null;

        if (!$formToken || !$sessionToken || $formToken !== $sessionToken) {
            // Если токены не совпадают, то выбрасываем исключение или устанавливаем ошибку
            throw new \Exception('CSRF token mismatch.');
        }

        // После проверки токена можно обновить его в сессии для следующего запроса
        $this->session['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function get($key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function post($key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function server($key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }

    public function cookie($key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file($key)
    {
        return $this->files[$key] ?? null;
    }

    public function header($key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    public function method()
    {
        return $this->server('REQUEST_METHOD');
    }

    public function uri()
    {
        return $this->server('REQUEST_URI');
    }

    public function isPost()
    {
        return $this->method() === 'POST';
    }

    public function isGet()
    {
        return $this->method() === 'GET';
    }

    public function all()
    {
        return array_merge($this->get, $this->post);
    }

    public function body()
    {
        return $this->body;
    }

}
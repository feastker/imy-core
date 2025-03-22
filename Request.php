<?php
namespace Imy\Core;

class Request
{
    private $getArr;
    private $postArr;
    private $serverArr;
    private $cookiesArr;
    private $filesArr;
    private $headersArr;
    private $sessionArr;
    private $bodyArr;

    public function __construct($csrfCheck = false)
    {
        $this->getArr = $this->sanitizeInput($_GET);
        $this->postArr = $this->sanitizeInput($_POST);
        $this->serverArr = $_SERVER;
        $this->cookiesArr = $this->sanitizeInput($_COOKIE);
        $this->filesArr = $this->mapFiles($_FILES);
        $this->sessionArr = $_SESSION ?? [];
        $this->headersArr = $this->extractHeaders($this->serverArr);
        $this->bodyArr = $this->sanitizeInput(file_get_contents('php://input'));

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

    public function generateCsrfToken(): string
    {
        if (empty($this->session('csrf_token'))) {
            $this->sessionArr['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $this->sessionArr['csrf_token'];
    }

    private function validateCsrfToken()
    {
        // Если запрос не предполагает изменения данных, проверка CSRF-токена не требуется
        if ($this->isGet()) {
            return;
        }

        // Извлечение токена из сессии и из отправленной формы
        $sessionToken = $this->session('csrf_token') ?? null;
        $formToken = $this->post('csrf_token') ?? $this->get('csrf_token') ?? null;

        if (!$formToken || !$sessionToken || $formToken !== $sessionToken) {
            // Если токены не совпадают, то выбрасываем исключение или устанавливаем ошибку
            throw new \Exception('CSRF token mismatch.' . $sessionToken);
        }

        // После проверки токена можно обновить его в сессии для следующего запроса
        $this->sessionArr['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function get($key = '', $default = null)
    {
        return $key ? ($this->getArr[$key] ?? $default) : $this->getArr;
    }

    public function post($key = '', $default = null)
    {
        return $key ? ($this->postArr[$key] ?? $default) : $this->postArr;
    }

    public function server($key = '', $default = null)
    {
        return $key ? ($this->serverArr[$key] ?? $default) : $this->serverArr;
    }

    public function cookies($key = '', $default = null)
    {
        return $key ? ($this->cookiesArr[$key] ?? $default) : $this->cookiesArr;
    }

    public function files($key = '')
    {
        return $key ? ($this->filesArr[$key] ?? null) : $this->filesArr;
    }

    public function header($key = '', $default = null)
    {
        return $key ? ($this->headersArr[$key] ?? $default) : $this->headersArr;
    }

    public function session($key = '', $default = null)
    {
        return $key ? ($this->sessionArr[$key] ?? $default) : $this->sessionArr;
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
        return array_merge($this->getArr, $this->postArr);
    }

    public function body()
    {
        return $this->bodyArr;
    }

}
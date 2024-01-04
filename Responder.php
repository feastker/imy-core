<?php
namespace Imy\Core;

class Responder
{
    private $headers = [];
    private $statusCode = 200;
    private $body = null;
    private $version = '1.1';

    public function setStatusCode(int $statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function addHeader(string $name, string $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    public function send()
    {
        if (!headers_sent()) {
            header(sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->getStatusMessage($this->statusCode)), true, $this->statusCode);

            foreach ($this->headers as $name => $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        if ($this->body) {
            echo $this->body;
        }

        return $this;
    }

    private function getStatusMessage($statusCode)
    {
        $statusCodes = [
            200 => 'OK',
            301 => 'Moved Permanently',
            302 => 'Found',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            // ... другие статусы HTTP ...
        ];

        return $statusCodes[$statusCode] ?? 'Unknown Status';
    }

    public function setVersion(string $version)
    {
        $this->version = $version;
        return $this;
    }

    // Вспомогательные методы для удобства
    public function redirect(string $url, int $statusCode = 302)
    {
        $this->setStatusCode($statusCode)->addHeader('Location', $url)->send();
        exit;
    }

    public function json($data, int $statusCode = 200)
    {
        $this->setBody(json_encode($data))
            ->setStatusCode($statusCode)
            ->addHeader('Content-Type', 'application/json')
            ->send();
    }

    public function file($path,$name = '') {

        if(empty($name)) {
            $tmp = explode(DS,$path);
            $name = array_pop($tmp);
        }

        if (ob_get_length()) ob_end_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        if ($fd = fopen($path, 'rb')) {
            while (!feof($fd)) {
                print fread($fd, 1024);
            }
            fclose($fd);
        }
        exit;
    }
}
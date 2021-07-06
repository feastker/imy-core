<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 18.08.2018
 * Time: 12:58
 */

namespace Imy\Core\Exception;


class Redirect extends \Exception
{
    protected $url;

    protected $query_params;

    protected $http_status;

    public function __construct($url, $query_params = [], $http_status = 302)
    {
        $this->url = $url;
        $this->query_params = $query_params;
        $this->http_status = $http_status;
    }

    public function getHTTPStatusCode()
    {
        return $this->http_status;
    }

    public function getRedirectURL()
    {
        return $this->url . ($this->query_params ? '?' . http_build_query($this->query_params) : '');
    }
}

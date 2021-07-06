<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 18.08.2018
 * Time: 12:58
 */

namespace Imy\Core\Exception;


class NotFound extends \Exception
{
    protected $url;

    protected $query_params;

    protected $http_status;

}

<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 25.08.2018
 * Time: 20:58
 */

namespace Imy\Core\Exception;


class Ajax extends \Exception
{

    public $view;

    public function __construct($view = false)
    {
        $this->view = $view;
    }
}

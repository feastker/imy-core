<?php

namespace Imy\Core;
abstract class Validator
{

    protected $data = [];

    protected $request;

    protected $fields = [];

    private $errors = [];

    public function __construct($data = [], $files = [])
    {
        $this->request = new Request();

        $this->data = $data ?: $this->request->post();
        $this->dataFiles = $files ?: $this->request->files();

        $this->fields = $this->init();
    }

    abstract function init();

    public function check()
    {

        foreach ($this->fields as $field => $rules) {
            $value = $this->data[$field] ?? false;
            $fileValue = $this->files[$field] ?? [];

            if(!empty($rules)) {
                foreach ($rules as $rule => $error) {
                    $rule = explode(':', $rule);
                    $message = gettype($error) == 'boolean' ? $rules['message'] : $error;

                    $error = false;

                    switch ($rule[0]) {
                        case 'required':
                            if (empty($value))
                                $error = true;
                            break;

                        case 'type':
                            if (!Data::check($value, $rule[1]) && !empty($value))
                                $error = true;
                            break;

                        case 'length':
                            if (strlen($value) < $rule[1] && !empty($value))
                                $error = true;
                            break;

                        case 'regexp':
                            if (!preg_match($rule[1], $value) && !empty($value))
                                $error = true;
                            break;

                        case 'size':
                            if ($fileValue['size'] > $rule[1])
                                $error = true;
                            break;

                        case 'range':
                            $range = explode('-', $rule[1]);

                            if (!is_numeric($value) || count($range) != 2 || $value < $range[0] || $value > $range[1])
                                $error = true;

                            break;
                    }

                    if ($error) {
                        $this->errors[$field][] = $message;
                        $isCritical = array_pop($rule) == 'critical';

                        if ($isCritical)
                            return false;
                    }

                }
            }

            if (method_exists($this, $field) && !empty($value)) {
                $test = $this->{$field}($value);

                if ($test !== true) {
                    $message = explode('critical:', $test);
                    if (count($message) > 1) {
                        $this->errors[$field][] = $message[1];
                        return false;
                    } else {
                        $this->errors[$field][] = $message[0];
                    }
                }
            }

        }

        return empty($this->errors);
    }

    public function getErrors($array = false)
    {

        if ($array)
            return $this->errors;

        $separator = "\n";

        $result = [];
        foreach ($this->errors as $error) {
            $result[] = implode($separator, $error);
        }

        return implode($separator, $result);
    }
}

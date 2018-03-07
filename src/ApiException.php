<?php

namespace Transmogrify;

class ApiException extends \Exception
{
    /** @var mixed $data */
    protected $data;

    /**
     * Sets extra data to pass within exception.
     *
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Gets extra data from an exception.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
}
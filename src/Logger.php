<?php

namespace Transmogrify;

class Logger
{
    /** @var bool|resource $stream */
    protected $stream;

    /**
     * Logger constructor.
     *
     * @param string $destination Output destination: file path or std stream
     */
    public function __construct($destination = 'php://stdout')
    {
        $this->stream = fopen($destination, 'w');
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    /**
     * Prints progress for the entity transference.
     *
     * @param string $entity  Entity name
     * @param int    $current Current step
     * @param int    $total   Total steps
     */
    public function setProgress($entity, $current, $total)
    {
        $this->add(sprintf(
            "%s: %d/%d", $entity, $current, $total
        ));
    }

    /**
     * Prints single log message.
     *
     * @param string $message      Message
     * @param bool   $include_date Whether to include date into output
     */
    public function add($message, $include_date = true)
    {
        $date = '';
        if ($include_date) {
            $date = sprintf("[%s]\t", date('Y-m-d H:i:s'));
        }

        fwrite($this->stream, sprintf("%s%s\n", $date, $message));
    }

    /**
     * var_dump's passed values and dies.
     */
    public function dump()
    {
        $message = [];
        foreach (func_get_args() as $arg) {
            $message[] = var_export($arg, true);
        }

        $this->add(implode(PHP_EOL, $message));

        exit(-1);
    }
}
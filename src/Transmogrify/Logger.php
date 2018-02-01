<?php

namespace Transmogrify;

class Logger
{
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

    public function add($string, $include_date = true)
    {
        $date = '';
        if ($include_date) {
            $date = sprintf("[%s]\t", date('Y-m-d H:i:s'));
        }
        printf("%s%s\n", $date, $string);
    }

    /**
     * var_dump's passed values and dies.
     */
    function dump()
    {
        call_user_func_array('var_dump', func_get_args());

        exit(-1);
    }
}
<?php

namespace Transmogrify;

use Exception;

class ComponentManager
{
    /** @var string $overriddenPrefix */
    protected $overriddenPrefix = '\\Transmogrify\\Overrides\\';

    /** @var string $originalPrefix */
    protected $originalPrefix = '\\Transmogrify\\';

    /**
     * Provides user-overridden application component or .
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function get()
    {
        $funcArgs = func_get_args();
        $className = array_shift($funcArgs);
        if (!$className) {
            throw new Exception('Component class is not specified');
        }

        $overriddenClassName = $this->overriddenPrefix . $className;
        $originalClassName = $this->originalPrefix . $className;
        $thirdPartyClassName = $className;

        if (class_exists($overriddenClassName)) {
            return new $overriddenClassName(...$funcArgs);
        }

        if (class_exists($originalClassName)) {
            return new $originalClassName(...$funcArgs);
        }

        if (class_exists($thirdPartyClassName)) {
            return new $thirdPartyClassName(...$funcArgs);
        }

        throw new Exception('Neither ' . $className . ' nor its override are found');
    }
}
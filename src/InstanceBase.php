<?php

namespace ZenCodex\ComposerMirror;

class InstanceBase
{
    /**
     * @return mixed
     */
    protected static function getInstance()
    {
        // override, nothing to do here
    }

    public function __call($method, $parameters)
    {
        return $this->$method(...$parameters);
    }

    /**
     * 魔术方法，静态调用
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return static::getInstance()->$method(...$parameters);
    }
}

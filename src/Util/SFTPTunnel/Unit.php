<?php

namespace Util\SFTPTunnel;

abstract class Unit {
    protected const IS_DIRECTORY = false;

    final public function isDirectory() : bool { return static::IS_DIRECTORY; }

    protected string $_path;
    protected string $_name;

    /**
     * SFTPUnit constructor
     *
     * @param string $path
     * @param string $name
     *
     * @throws \Exception empty directory/name or contain leading/trailing space
     */
    public function __construct(string $path, string $name) {
        $this->_path = trim($path);
        if ($this->_path !== $path) {
            throw new \Exception(message: "The file destination can't contain a leading or trailing space", code: 400);
        }
        if ($this->_path === '') {
            throw new \Exception(message: "File destination can not be empty", code: 400);
        }

        $this->_name = trim($name);
        if ($this->_path !== $path) {
            throw new \Exception(message: "The file name can't contain a leading or trailing space", code: 400);
        }
        if ($this->_path === '') {
            throw new \Exception(message: "File name can not be empty", code: 400);
        }
    }

    public function asArray() : array {
        return [
            "path" => static::Path(),
            "name" => static::Name(),
        ];
    }

    /**
     * @return string
     */
    final public function Path() : string { return $this->_path; }

    /**
     * @return string
     */
    final public function Name() : string { return $this->_name; }
}

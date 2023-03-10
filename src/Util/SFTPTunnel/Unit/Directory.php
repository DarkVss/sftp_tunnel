<?php

namespace Util\SFTPTunnel\Unit;

class Directory extends \Util\SFTPTunnel\Unit {
    protected const IS_DIRECTORY = true;

    /**
     * @var \Util\SFTPTunnel\Unit\Directory[] $_containsDirectories
     */
    protected array $_containsDirectories;
    /**
     * @var \Util\SFTPTunnel\Unit\File[] $_containsFiles
     */
    protected array $_containsFiles;

    /**
     * Unit constructor
     *
     * @param string                  $path
     * @param string                  $name
     * @param \Util\SFTPTunnel\Unit[] $containsUnits
     *
     * @throws \Exception empty directory/name or contain leading/trailing space
     */
    public function __construct(string $path, string $name, array $containsUnits = []) {
        parent::__construct($path, $name);

        $contains = [
            "directory" => [],
            "file"      => [],
        ];
        foreach ($containsUnits as $containsUnit) {
            if (($containsUnit instanceof \Util\SFTPTunnel\Unit) === false) {
                throw new \Exception(message: "Directory cannot contain non-Unit's", code: 409);
            }

            $contains[($containsUnit instanceof \Util\SFTPTunnel\Unit\Directory) === true ? "directory" : "file"][$containsUnit->Name()] = $containsUnit;
        }
        ksort($contains["directory"]);
        ksort($contains["file"]);

        $this->_containsDirectories = array_values($contains["directory"]);
        $this->_containsFiles = array_values($contains["file"]);
    }

    public function asArray() : array {
        return array_merge(
            parent::asArray(),
            [
                "directories" => array_map(fn(\Util\SFTPTunnel\Unit\Directory $directory) => $directory->asArray(), static::ContainsDirectories()),
                "files"       => array_map(fn(\Util\SFTPTunnel\Unit\File $file) => $file->asArray(), static::ContainsFiles()),
            ]
        );
    }

    /**
     * Return contains units list by reference
     *
     * @return \Util\SFTPTunnel\Unit\Directory[]
     */
    public function &ContainsDirectories() : array { return $this->_containsDirectories; }

    /**
     * Return contains directories list by reference
     *
     * @return \Util\SFTPTunnel\Unit\Directory[]
     */
    public function &ContainsFiles() : array { return $this->_containsFiles; }
}

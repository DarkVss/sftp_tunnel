<?php

namespace Util;

final class SFTPTunnel {
    protected static ?self $_instance = null;

    /**
     * @var false|resource
     */
    protected $_connection;
    /**
     * @var false|resource
     */
    protected $_sftp;

    /**
     * @param bool    $byPassword authentication method
     * @param string  $host
     * @param int     $port
     *
     * @param string  $login
     * @param ?string $password
     *
     * @param ?string $publicKey
     * @param ?string $privateKey
     * @param ?string $passphrase
     *
     * @throws \Exception
     */
    protected function __construct(
        bool $byPassword,
        string $login, string $host, int $port = 22,

        ?string $password = null,

        ?string $publicKey = null, ?string $privateKey = null, ?string $passphrase = null
    ) {
        if (empty($host) === true) {
            throw new \Exception(message: "Empty host", code: 406);
        }

        if (@fsockopen(hostname: $host, port: $port,error_code: $errorCode,error_message: $errorMessage, timeout: 3) === false) {
            throw new \Exception(message: "Address `{$host}:{$port}` offline", code: 405);
        }

        $this->_connection = @ssh2_connect($host, $port);
        if ($this->_connection === false) {
            throw new \Exception(message: "Could not connect to {$host} on port {$port}", code: 404);
        }

        if ($byPassword === true) {
            if (empty($password) === true) {
                throw new \Exception(message: "Login or password not set", code: 401);
            }

            if (@ssh2_auth_password($this->_connection, $login, $password) === false) {
                throw new \Exception(message: "Could not authenticate with username and password", code: 403);
            }
        } else {
            if (empty($publicKey) === true || empty($privateKey) === true) {
                throw new \Exception(message: "Public or private key not set", code: 401);
            }
            if (
                (file_exists($publicKey) === false || is_dir($publicKey) === true)
                || (file_exists($privateKey) === false || is_dir($privateKey) === true)
            ) {
                throw new \Exception(message: "Public or private key file not exist", code: 401);
            }

            if (@ssh2_auth_pubkey_file($this->_connection, $login, $publicKey, $privateKey, $passphrase) === false) {
                throw new \Exception(message: "Could not authenticate with public and private key", code: 403);
            }
        }


        $this->_sftp = @ssh2_sftp($this->_connection);
        if ($this->_sftp === false) {
            throw new \Exception(message: "Could not initialize SFTP subsystem", code: 422);
        }
    }

    /**
     * Init new tunnel for SFTP. If tunnel already set - disconnect and try to create new tunnel
     *
     * @param string $login
     * @param string $password
     * @param string $host
     * @param int    $port
     *
     * @throws \Exception
     */
    public static function setTunnelByPassword(string $login, string $password, string $host, int $port = 22) : void {
        if (static::$_instance !== null) {
            @ssh2_disconnect(static::$_instance->_connection);
        }

        static::$_instance = new static(
            byPassword: true,
            login: $login, host: $host, port: $port,

            password: $password
        );
    }

    /**
     * Init new tunnel for SFTP. If tunnel already set - disconnect and try to create new tunnel
     *
     * @param string  $login
     * @param string  $publicKey  public key filename
     * @param string  $privateKey private key filename
     * @param ?string $passphrase password from private key
     * @param string  $host
     * @param int     $port
     *
     * @throws \Exception
     */
    public static function setTunnelByKeys(string $login, string $publicKey, string $privateKey, ?string $passphrase, string $host, int $port = 22) : void {
        if (static::$_instance !== null) {
            @ssh2_disconnect(static::$_instance->_connection);
        }

        static::$_instance = new static(
            byPassword: false,
            login: $login, host: $host, port: $port,

            publicKey: $publicKey, privateKey: $privateKey, passphrase: $passphrase
        );
    }

    /**
     * Get active SFTP tunnel
     *
     * @return static
     *
     * @throws \Exception tunnel not init
     */
    public static function getTunnel() : static { return static::$_instance ?? throw new \Exception(message: "Tunnel not set, use `setTunnel`", code: 400); }

    /**
     * @param string $directory
     * @param bool   $recursive
     *
     * @return \Util\SFTPTunnel\Unit[] key - filename, value - unit class object
     *
     * @throws \Exception
     */
    public function scanFilesystem(string $directory, bool $recursive = false) : array {
        $directory = str_replace("//", "/", "/{$directory}");
        $sftpDirectory = "ssh2.sftp://{$this->_sftp}{$directory}";
        $handle = @opendir($sftpDirectory);
        if ($handle === false) {
            throw new \Exception(message: "Could not read directory `{$directory}`", code: 422);
        }

        $listing = [
            "directory" => [],
            "file"      => [],
        ];
        while (($file = @readdir($handle)) !== false) {
            if ($file !== "." && $file !== "..") {
                if (@is_dir("{$sftpDirectory}/{$file}") === true) {
                    $containsUnits = [];
                    if ($recursive === true) {
                        $containsUnits = $this->scanFilesystem("{$directory}/{$file}");
                    }
                    $listing["directory"][$file] = new \Util\SFTPTunnel\Unit\Directory($directory, $file, $containsUnits);
                } else {
                    $listing["file"][$file] = new \Util\SFTPTunnel\Unit\File($directory, $file);
                }
            }
        }
        @closedir($handle);
        ksort($listing["directory"]);
        ksort($listing["file"]);

        return [
            ...array_values($listing["directory"]),
            ...array_values($listing["file"]),
        ];
    }

    /**
     * Create file
     *
     * @param string $localFile
     * @param string $remoteFile
     *
     * @return \Util\SFTPTunnel\Unit\File
     *
     * @throws \Exception
     */
    public function uploadFile(string $localFile, string $remoteFile) : \Util\SFTPTunnel\Unit\File {
        $remoteFile = str_replace("//", "/", "/{$remoteFile}");

        if (file_exists($localFile) === false || is_dir($localFile) === true) {
            throw new \Exception(message: "Could not find file `{$localFile} for sending", code: 404);
        }
        $localFileData = @file_get_contents($localFile);
        if ($localFileData === false) {
            throw new \Exception(message: "Could not open local file `{$localFile} for sending", code: 403);
        }

        return static::uploadFileData($localFileData, $remoteFile);
    }

    /**
     * @param string $fileDate file data like from `file_get_contents` function
     * @param string $remoteFile
     *
     * @return \Util\SFTPTunnel\Unit\File
     *
     * @throws \Exception
     */
    public function uploadFileData(string $fileDate, string $remoteFile) : \Util\SFTPTunnel\Unit\File {
        $stream = @fopen("ssh2.sftp://{$this->_sftp}{$remoteFile}", "w");
        if ($stream === false) {
            throw new \Exception(message: "Could not open file `{$remoteFile} for writing", code: 403);
        }
        if (@fwrite($stream, $fileDate) === false) {
            throw new \Exception(message: "Could not send file data", code: 403);
        }
        @fclose($stream);

        if (@file_exists("ssh2.sftp://{$this->_sftp}{$remoteFile}") === false) {
            throw new \Exception(message: "File was sent but not written", code: 500);
        }

        return new \Util\SFTPTunnel\Unit\File(dirname($remoteFile), basename($remoteFile));
    }

    /**
     * @param string $remoteDirectory
     * @param int    $mode
     * @param bool   $recursive
     *
     * @return \Util\SFTPTunnel\Unit\Directory
     *
     * @throws \Exception
     */
    public function createDirectory(string $remoteDirectory, int $mode = 0750, bool $recursive = false) : \Util\SFTPTunnel\Unit\Directory {
        $remoteDirectory = str_replace("//", "/", "/{$remoteDirectory}");
        $sftpRemoteDirectory = "ssh2.sftp://{$this->_sftp}{$remoteDirectory}";

        if (file_exists($sftpRemoteDirectory) === true) {
            throw new \Exception(message: "Already existed remote file/directory `{$remoteDirectory}`", code: 404);
        }

        if (@ssh2_sftp_mkdir($this->_sftp, $remoteDirectory, $mode, $recursive) === false) {
            throw new \Exception(message: "Could not create remote directory `{$remoteDirectory}`", code: 400);
        }

        if (is_dir($sftpRemoteDirectory) === false) {
            throw new \Exception(message: "Failed to create remote directory to `{$remoteDirectory}`", code: 500);
        }

        return new \Util\SFTPTunnel\Unit\Directory(dirname($remoteDirectory), basename($remoteDirectory));
    }

    /**
     * @param string $remoteFile
     *
     * @return string
     *
     * @throws \Exception file data like from `file_get_contents` function
     */
    protected function _downloadFileContent(string $remoteFile) : string {
        $remoteFile = str_replace("//", "/", "/{$remoteFile}");
        $sftpRemoteFile = "ssh2.sftp://{$this->_sftp}{$remoteFile}";

        if (file_exists($sftpRemoteFile) === false) {
            throw new \Exception(message: "Not exist remote file `{$remoteFile}`", code: 404);
        }
        if (is_dir($sftpRemoteFile) === true) {
            throw new \Exception(message: "Remote file `{$remoteFile}` is directory", code: 409);
        }

        $stream = @fopen($sftpRemoteFile, "r");
        if (!$stream) {
            throw new \Exception("Could not open remote file `{$remoteFile}`");
        }
        $content = fread($stream, filesize($sftpRemoteFile));
        @fclose($stream);
        if ($content === false) {
            throw new \Exception(message: "Failed to read remote file `{$remoteFile}`", code: 500);
        }

        return $content;
    }

    /**
     * @param string $remoteFile
     * @param string $localFile
     *
     * @return \Util\SFTPTunnel\Unit\File
     *
     * @throws \Exception
     */
    public function downloadFile(string $remoteFile, string $localFile) : \Util\SFTPTunnel\Unit\File {
        $result = @file_put_contents($localFile, static::_downloadFileContent($remoteFile));

        if ($result === false) {
            throw new \Exception(message: "Failed to write locale file to `{$localFile}`", code: 500);
        }

        return new \Util\SFTPTunnel\Unit\File(dirname($remoteFile), basename($remoteFile));
    }

    /**
     * @param string $remoteFile
     *
     * @return string
     *
     * @throws \Exception
     */
    public function downloadFileData(string $remoteFile) : string { return static::_downloadFileContent($remoteFile); }

    /**
     * @param string $remoteFile
     *
     * @throws \Exception
     */
    public function deleteFile(string $remoteFile) : void {
        $remoteFile = str_replace("//", "/", "/{$remoteFile}");
        $sftpRemoteFile = "ssh2.sftp://{$this->_sftp}{$remoteFile}";
        if (file_exists($sftpRemoteFile) === false) {
            throw new \Exception(message: "Not exist remote file `{$remoteFile}`", code: 404);
        }
        if (is_dir($sftpRemoteFile) === true) {
            throw new \Exception(message: "Remote file `{$remoteFile}` is directory", code: 409);
        }

        if (@ssh2_sftp_unlink($this->_sftp, $remoteFile) === false) {
            throw new \Exception(message: "Could not delete remote file `{$remoteFile}`", code: 500);
        }
    }

    /**
     * @param string $remoteDirectory
     *
     * @throws \Exception
     */
    public function deleteDirectory(string $remoteDirectory) : void {
        $remoteDirectory = str_replace("//", "/", "/{$remoteDirectory}");
        $sftpRemoteFile = "ssh2.sftp://{$this->_sftp}{$remoteDirectory}";
        if (file_exists($sftpRemoteFile) === false) {
            throw new \Exception(message: "Not exist remote directory `{$remoteDirectory}`", code: 404);
        }
        if (is_dir($sftpRemoteFile) === false) {
            throw new \Exception(message: "Remote directory `{$remoteDirectory}` is not directory", code: 409);
        }

        if (count(static::scanFilesystem($remoteDirectory, false)) !== 0) {
            throw new \Exception(message: "Could not delete remote directory `{$remoteDirectory}` - contains direcory or files", code: 409);
        }

        if (@ssh2_sftp_rmdir($this->_sftp, $remoteDirectory) === false) {
            throw new \Exception(message: "Could not delete remote directory `{$remoteDirectory}`", code: 500);
        }
    }
}

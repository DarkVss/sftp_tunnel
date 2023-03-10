<?php

require_once "./vendor/autoload.php";

use \Util\SFTPTunnel as SFTPTunnel;

const COMMAND_HELP = "help";
const COMMAND_SCAN = "scan";
const COMMAND_UPLOAD_FILE = "uploadFile";
const COMMAND_UPLOAD_FILE_DATA = "uploadFileData";
const COMMAND_DOWNLOAD_FILE = "downloadFile";
const COMMAND_DOWNLOAD_FILE_DATA = "downloadFileData";
const COMMAND_DELETE_FILE = "deleteFile";
const COMMAND_CREATE_DIRECTORY = "createDirectory";
const COMMAND_DELETE_DIRECTORY = "deleteDirectory";
const AVAILABLE_MANIPULATION_COMMANDS = [
    COMMAND_SCAN               => "Scan directory",
    COMMAND_UPLOAD_FILE        => "Upload local file",
    COMMAND_UPLOAD_FILE_DATA   => "Upload file data",
    COMMAND_DOWNLOAD_FILE      => "Download remote file to local file",
    COMMAND_DOWNLOAD_FILE_DATA => "Download remote file as file data",
    COMMAND_DELETE_FILE        => "Delete remote file",
    COMMAND_CREATE_DIRECTORY   => "Create remote directory",
    COMMAND_DELETE_DIRECTORY   => "Delete remote directory",
];

const AUTH_METHOD_PASSWORD = "password";
const AUTH_METHOD_KEY = "key";
const AVAILABLE_AUTH_METHODS = [
    AUTH_METHOD_PASSWORD => "Auth by password",
    AUTH_METHOD_KEY      => "Auth by ssh-keys",
];

/** ENV settings **/
$host = "192.168.0.166";
$port = 22;

$login = "ftp_user";

$password = "password";

$publicKey = "~/.ssh/id_rsa.pub";
$privateKey = "~/.ssh/id_rsa";
$passphrase = null;

$ftpHomeDirectory = "/home/{$login}";
/** ENV settings **/


$authType = $argv[1] ?? "help";
$command = $argv[2] ?? null;

if ($authType === COMMAND_HELP || $authType === null) {
    echo "Available auth methods:\n" . implode(
            "\n",
            array_map(
                fn(string $authType) => "\t`{$authType}` - " . AVAILABLE_AUTH_METHODS[$authType],
                array_keys(AVAILABLE_AUTH_METHODS)
            )
        ) . "\n\n";

    echo "Available commands:\n" . implode(
            "\n",
            array_map(
                fn(string $command) => "\t`{$command}` - " . AVAILABLE_MANIPULATION_COMMANDS[$command],
                array_keys(AVAILABLE_MANIPULATION_COMMANDS)
            )
        );
} else {
    if (isset(AVAILABLE_AUTH_METHODS[$authType]) === false) {
        echo "Unknown auth method. Call `help` for display info\n";
    } else if (isset(AVAILABLE_MANIPULATION_COMMANDS[$command]) === false) {
        echo "Unset on unknown command. Call `help` for display info\n";
    } else {
        echo "Auth type - `{$authType}` , command - `{$command}`;"
            . "\n*\t*\t*\t*\t*\t*\t*\t*\t*\t*\n";

        try {
            if ($authType === AUTH_METHOD_PASSWORD) {
                SFTPTunnel::setTunnelByPassword($login, $password, $host, $port);
            } else {
                SFTPTunnel::setTunnelByKeys($login, $publicKey, $privateKey, $passphrase, $host, $port);
            }

            switch ($command) {
                case COMMAND_SCAN:
                    {
                        $result = SFTPTunnel::getTunnel()->scanFilesystem($ftpHomeDirectory, true);
                        echo "Files:\n";
                        var_export($result);
                    }
                    break;
                case COMMAND_UPLOAD_FILE:
                    {
                        $result = SFTPTunnel::getTunnel()->uploadFile("./test.json", $ftpHomeDirectory . "/test.json");
                        echo "Successful file upload:\n";
                        var_export($result);
                    }
                    break;
                case COMMAND_UPLOAD_FILE_DATA:
                    {
                        $result = SFTPTunnel::getTunnel()->uploadFileData("./test.json", $ftpHomeDirectory . "/testX.json");
                        echo "Successful file data upload:\n";
                        var_export($result);
                    }
                    break;
                case COMMAND_DOWNLOAD_FILE:
                    {
                        $result = SFTPTunnel::getTunnel()->downloadFile($ftpHomeDirectory . "/rootDir/someFile.txt", "./xxx1");
                        echo "Successful file download:\n";
                        var_export($result);
                    }
                    break;
                case COMMAND_DOWNLOAD_FILE_DATA:
                    {
                        $result = SFTPTunnel::getTunnel()->downloadFileData($ftpHomeDirectory . "/rootDir/someFile.txt");
                        echo "Successful file data download:\n";
                        var_export($result);
                    }
                    break;
                case COMMAND_DELETE_FILE:
                    {
                        SFTPTunnel::getTunnel()->deleteDirectory($ftpHomeDirectory . "/rootDir/someDir/123");
                        echo "Successful file delete";
                    }
                    break;
                case COMMAND_CREATE_DIRECTORY:
                    {
                        $result = SFTPTunnel::getTunnel()->createDirectory($ftpHomeDirectory . "/rootDir/someDir/123XXXX/this_is_directory", 0700, true);
                        echo "Successful directory create:\n";
                        var_export($result);
                    }
                    break;
                case COMMAND_DELETE_DIRECTORY:
                    {
                        SFTPTunnel::getTunnel()->deleteDirectory($ftpHomeDirectory . "/rootDir/someDir/123s");
                        echo "Successful directory delete";
                    }
                    break;
            }
            echo "\n";
        } catch (\Exception $e) {
            echo "Fail:\t {$e->getMessage()} [{$e->getFile()}::{$e->getLine()}]";
        }
    }
}

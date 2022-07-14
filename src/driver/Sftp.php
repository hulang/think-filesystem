<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\AdapterInterface;
use think\filesystem\Driver;

class Sftp extends Driver
{
    protected function createAdapter(): AdapterInterface
    {
        return new \League\Flysystem\Sftp\SftpAdapter([
            'host'       => $this->config['host'],
            'port'       => $this->config['port'],
            'username'   => $this->config['username'],
            'password'   => $this->config['password'],
            'privateKey' => $this->config['privateKey'],
            'root'       => $this->config['root'],
            'timeout'    => $this->config['timeout'],
        ]);
    }
    public function url(string $path): string
    {
        if (isset($this->config['url'])) {
            return $this->concatPathToUrl($this->config['url'], $path);
        }
        return parent::url($path);
    }
}

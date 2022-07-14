<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use think\filesystem\Driver;
use League\Flysystem\AdapterInterface;

class Ftp extends Driver
{
    protected function createAdapter(): AdapterInterface
    {
        return new \League\Flysystem\Adapter\Ftp([
            'host'     => $this->config['host'],
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            /** optional config settings */
            'port'                 => $this->config['port'],
            'root'                 => $this->config['root'],
            'passive'              => $this->config['passive'],
            'ssl'                  => $this->config['ssl'],
            'timeout'              => $this->config['timeout'],
            'ignorePassiveAddress' => $this->config['ignorePassiveAddress'],
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

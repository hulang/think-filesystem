<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use hulang\filesystem\Driver;

class Sftp extends Driver
{
    protected function createAdapter()
    {
        $provider = SftpConnectionProvider::fromArray($this->config);

        $root = $this->config['root'] ?? '/';

        $visibility = PortableVisibilityConverter::fromArray(
            $this->config['permissions'] ?? []
        );
        return new SftpAdapter($provider, $root, $visibility);
    }
}

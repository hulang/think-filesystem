<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use Overtrue\Flysystem\Cos\CosAdapter;
use hulang\filesystem\Driver;

class Qcloud extends Driver
{
    protected function createAdapter()
    {
        return new CosAdapter($this->config);
    }
}

<?php

declare(strict_types=1);

namespace hulang\filesystem;

class Service extends \think\Service
{
    /**
     * 注册文件系统服务
     *
     * 该方法通过绑定 'filesystem' 抽象到 Filesystem 类来注册文件系统服务
     * 这使得文件系统服务可以在应用程序中通过依赖注入来使用
     */
    public function register()
    {
        $this->app->bind('filesystem', Filesystem::class);
    }
}

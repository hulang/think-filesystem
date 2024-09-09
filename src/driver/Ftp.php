<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use hulang\filesystem\Driver;

class Ftp extends Driver
{
    /**
     * 创建适配器实例
     *
     * 该方法负责实例化FtpAdapter,用于处理与FTP相关的操作在实例化适配器之前
     * 该方法确保配置数组中存在一个'root'键,如果不存在,则默认赋值为空字符串,以防止在创建适配器时出现配置缺失的错误
     *
     * @return FtpAdapter 返回一个FtpAdapter实例,该实例使用从配置数组转换得到的FtpConnectionOptions进行初始化
     */
    protected function createAdapter()
    {
        // 确保配置数组中存在'root'键,如果不存在,则默认赋值为空字符串
        if (!isset($this->config['root'])) {
            $this->config['root'] = '';
        }

        // 使用配置数组实例化FtpAdapter并返回
        return new FtpAdapter(FtpConnectionOptions::fromArray($this->config));
    }
}

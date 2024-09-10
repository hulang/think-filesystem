<?php

declare(strict_types=1);

namespace hulang\filesystem;

use InvalidArgumentException;
use think\helper\Arr;
use think\helper\Str;
use think\Manager;

class Filesystem extends Manager
{
    /**
     * 已注册的自定义驱动程序创建者
     *
     * @var array
     */
    protected $customCreators = [];

    protected $namespace = '\\hulang\\filesystem\\driver\\';

    /**
     * 获取指定名称的磁盘驱动实例
     * 
     * 本方法主要用于通过磁盘名称获取对应的驱动实例如果未指定名称,则返回默认驱动实例
     * 这对于需要对文件进行操作,而又不关心具体磁盘类型（本地、远程等）的场景非常有用
     * 
     * @param null|string $name 可选参数,指定要获取的磁盘驱动的名称如果未提供,则返回默认驱动
     * @return Driver 返回请求的磁盘驱动实例
     */
    public function disk(string $name = null): Driver
    {
        return $this->driver($name);
    }

    /**
     * 获取名为 $name 的云存储驱动实例
     * 
     * 本方法主要提供了一个便捷方式来获取云存储驱动实例,避免了直接调用底层的 driver 方法
     * 它允许开发者通过可选的 $name 参数指定所需的云存储服务,从而返回对应的驱动实例
     * 
     * @param null|string $name 可选参数,用于指定云存储服务的名称如果未提供,则使用默认云存储服务
     * @return Driver 返回 Driver 接口的实现实例,具体类型取决于所指定或默认的云存储服务
     */
    public function cloud(string $name = null): Driver
    {
        return $this->driver($name);
    }

    /**
     * 调用自定义驱动程序创建者
     *
     * 此方法用于调用自定义的驱动程序创建函数,以根据配置创建相应的实例
     * 它通过配置数组中指定的驱动类型来选择合适的创建函数
     *
     * @param array $config 包含驱动类型和相关配置的数组.配置数组中必须包含'driver'键,用于指定驱动类型
     * @return mixed 返回自定义驱动程序创建函数的返回值,具体类型取决于驱动程序的实现
     */
    protected function callCustomCreator(array $config)
    {
        // 使用配置数组中指定的驱动类型,调用相应的自定义创建函数
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /**
     * 解析存储类型
     *
     * 此方法用于根据提供的存储名称来解析对应的存储类型
     * 默认情况下,如果配置中没有指定类型,则会返回'local',表示使用本地存储
     *
     * @param string $name 存储名称,用于在配置中查找对应的存储类型
     * @return mixed|string 返回解析得到的存储类型,如果配置中没有指定,则为 'local'
     */
    protected function resolveType(string $name)
    {
        return $this->getDiskConfig($name, 'type', 'local');
    }

    /**
     * 解析配置信息
     *
     * @param string $name 配置名称
     * @return mixed 返回解析后的配置信息
     */
    protected function resolveConfig(string $name)
    {
        return $this->getDiskConfig($name);
    }

    /**
     * 创建指定名称的驱动实例
     * 
     * 本方法首先解析给定的驱动名称,确定所需的驱动类型如果存在自定义驱动创建器,则调用自定义创建器
     * 否则,尝试调用内部的创建方法如果内部方法不存在,则解析驱动类并使用应用实例化该类
     * 
     * @param string $name 驱动名称
     * @return mixed 驱动实例
     */
    protected function createDriver(string $name)
    {
        // 根据驱动名称解析驱动类型
        $type = $this->resolveType($name);

        // 如果存在针对此驱动名称的自定义创建器
        if (isset($this->customCreators[$name])) {
            // 调用自定义创建器并返回结果
            return $this->callCustomCreator($type);
        }

        // 构建驱动创建方法的名称
        $method = 'create' . Str::studly($type) . 'Driver';

        // 解析驱动创建所需的参数
        $params = $this->resolveParams($name);

        // 如果存在对应类型的创建方法
        if (method_exists($this, $method)) {
            // 调用对应类型的创建方法并返回结果
            return $this->$method(...$params);
        }

        // 如果没有对应类型的创建方法,解析驱动类名
        $class = $this->resolveClass($type);

        // 使用应用实例化驱动类并返回实例
        return $this->app->invokeClass($class, $params);
    }

    /**
     * 获取缓存配置
     * @param null|string $name 名称
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig(string $name = null, $default = null)
    {
        if (!is_null($name)) {
            return $this->app->config->get('filesystem.' . $name, $default);
        }

        return $this->app->config->get('filesystem');
    }

    /**
     * 获取磁盘配置
     * 
     * 从配置文件中获取指定磁盘的配置信息
     * 如果指定了配置项名称,则返回该配置项的值;否则,返回整个磁盘配置数组
     * 如果未找到磁盘配置,则抛出异常
     * 
     * @param string $disk 磁盘标识符
     * @param string $name 配置项名称,可选
     * @param mixed $default 当配置项不存在时的默认值,可选
     * @return mixed 返回配置项的值或整个磁盘配置数组
     * @throws InvalidArgumentException 当未找到指定磁盘配置时抛出异常
     */
    public function getDiskConfig($disk, $name = null, $default = null)
    {
        // 尝试获取指定磁盘的配置
        if ($config = $this->getConfig("disks.{$disk}")) {
            // 使用Arr::get方法获取配置项的值,如果配置项不存在,则返回默认值
            return Arr::get($config, $name, $default);
        }

        // 如果未找到磁盘配置,抛出异常
        throw new InvalidArgumentException("Disk [$disk] not found.");
    }

    /**
     * 获取默认驱动
     * 
     * 此方法用于获取配置中的默认驱动名称它通过调用内部的getConfig方法
     * 检索配置数据中的'default'键来实现这一功能如果没有设置默认驱动
     * 或者配置中没有定义'default'键,则方法返回null
     * 
     * @return mixed|string|null 返回默认驱动的名称,如果未设置则返回null
     */
    public function getDefaultDriver()
    {
        return $this->getConfig('default');
    }

    /**
     * 扩展服务容器中驱动程序的创建功能
     * 
     * 该方法允许通过回调函数扩展服务容器中特定驱动程序的创建逻辑,为服务容器的驱动程序创建提供额外的自定义选项
     * 
     * @param string $driver 驱动程序的名称
     * @param \Closure $callback 回调函数用于创建驱动程序
     * @return $this 返回服务容器实例,支持链式调用
     */
    public function extend($driver, \Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * 动态调用
     * 当尝试调用不存在的方法时,此魔术方法会被调用
     * 它允许将方法调用委托给内部驱动程序实例
     * 
     * @param string $method 被调用的方法名称
     * @param array $parameters 传递给方法的参数数组
     * @return mixed 返回驱动程序实例对指定方法调用的结果
     */
    public function __call($method, $parameters)
    {
        // 将方法调用委托给内部驱动程序实例
        return $this->driver()->$method(...$parameters);
    }
}

# Config Container

简单的 PHP 配置解析器.

![Building](https://api.travis-ci.org/JanHuang/config.svg?branch=master)
[![Latest Stable Version](https://poser.pugx.org/fastd/config/v/stable)](https://packagist.org/packages/fastd/config) [![Total Downloads](https://poser.pugx.org/fastd/config/downloads)](https://packagist.org/packages/fastd/config) [![Latest Unstable Version](https://poser.pugx.org/fastd/config/v/unstable)](https://packagist.org/packages/fastd/config) [![License](https://poser.pugx.org/fastd/config/license)](https://packagist.org/packages/fastd/config)

## 要求

* PHP 7+

## Composer

```
composer require "fastd/config:2.0-dev"
```

## 使用

```php
$config = new \FastD\Config\Config();

$config->load(__DIR__ . '/../src/FastD/Config/Tests/config/array.yml');

echo $config->get('array2.name');
```

数组的获取方法可以通过 `.` dot 来进行链接获取，例如数组:

```php
[
    'profile' => [
        'name' => 'janhuang'
    ]
]
```

即可通过 `profile.name` 来进行获取该值，如果没有查询到该下标，会抛出 `\InvalidArgumentException` 异常

### 变量

每个配置项均可以配置自己的变量，边界符为 `%`

例如配置文件:

```yaml
name: %name%
```

PHP: 

```php
$config = new \FastD\Config\Config();

$config->load(__DIR__ . '/../src/FastD/Config/Tests/config/variable.yml');

$config->setVariable('name', 'janhuang');

echo $config->get('name'); // janhuang
```

此处配置文件中有一个定义变量: `%name%`，然后变量的值通过 `\FastD\Config\Config` 对象进行设置。

`\FastD\Config\Config::setVariable($name, $value)`, 第一个参数就是配置变量的名字，不需要边界符: `%`，后面是该配置项的值。

### # 新增配置缓存

```php
$config = new \FastD\Config\Config();

$config->set('name', 'janhuang');

$config->saveCache(); // 创建缓存文件 -> .user.php.cache 默认在 Config 当前目录
```

## Testing

```
phpunit
```

## License MIT


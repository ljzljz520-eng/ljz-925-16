<?php

/**
 * PHPUnit 测试引导文件
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 加载Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// 自动加载类
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 设置测试环境变量
putenv('DB_PATH=:memory:');
putenv('APP_ENV=testing');

<?php

/*
|--------------------------------------------------------------------------
| 加载配置，初始化相关系统参数
|--------------------------------------------------------------------------
*/

use ZenCodex\ComposerMirror\Log;

$config = require(__DIR__ . '/config.php');
require_once ROOTDIR . '/vendor/autoload.php';

declare(ticks = 1);
@exec('ulimit -n 10000');
ini_set('memory_limit', '1G');
set_time_limit($config->timeout);
putenv("GUZZLE_CURL_SELECT_TIMEOUT=" . $config->timeout);

date_default_timezone_set('PRC');
Log::debug(sprintf('使用的时区: %s, 当前时间: %s', date_default_timezone_get(), date('Y-m-d H:i:s')));

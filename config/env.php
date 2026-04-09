<?php

defined('YII_DEBUG') or define('YII_DEBUG', getenv('YII_DEBUG') !== false ? (bool)getenv('YII_DEBUG') : true);
defined('YII_ENV')   or define('YII_ENV',   getenv('YII_ENV') ?: 'dev');

// Suppress E_DEPRECATED so abandoned vendor libs (dreamcommerce/shop-appstore-lib)
// don't crash via Yii2's error handler converting notices to exceptions.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

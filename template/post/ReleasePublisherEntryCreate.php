<?php
/**
 * DF_ReleasePublisher managed POST wrapper
 */

namespace {
    $paths = [];
    if (defined('PLUGIN_LIB_DIR')) {
        $paths[] = rtrim(PLUGIN_LIB_DIR, '/\\') . '/DF_ReleasePublisher/Bootstrap.php';
    }
    $paths[] = dirname(__DIR__, 2) . '/Bootstrap.php';
    $paths[] = dirname(__DIR__, 3) . '/extension/plugins/DF_ReleasePublisher/Bootstrap.php';

    foreach (array_unique($paths) as $path) {
        if (is_file($path)) {
            require_once $path;
            break;
        }
    }
    if (class_exists('\Acms\Plugins\DF_ReleasePublisher\Bootstrap')) {
        \Acms\Plugins\DF_ReleasePublisher\Bootstrap::registerAutoloader();
    }
}

namespace Acms\Custom\POST {
    class ReleasePublisherEntryCreate extends \Acms\Plugins\DF_ReleasePublisher\POST\ReleasePublisherEntryCreate
    {
    }
}

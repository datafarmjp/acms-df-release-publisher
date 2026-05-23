<?php

namespace Acms\Plugins\DF_ReleasePublisher;

use ACMS_App;
use Acms\Services\Common\InjectTemplate;

class ServiceProvider extends ACMS_App
{
    public const VERSION = '0.3.1';

    private const GET_WRAPPER_MARKER = 'DF_ReleasePublisher managed GET wrapper';
    private const POST_WRAPPER_MARKER = 'DF_ReleasePublisher managed POST wrapper';

    private static $getWrappers = [
        'DFReleasePublisher.php',
    ];

    private static $postWrappers = [
        'ReleasePublisherSettings.php',
        'ReleasePublisherPreview.php',
        'ReleasePublisherEntryCreate.php',
        'ReleasePublisherWebhook.php',
    ];

    public $version = self::VERSION;
    public $name = 'DFリリース';
    public $author = '株式会社データファーム';
    public $module = true;
    public $menu = 'df-release-publisher';
    public $desc = '複数のDF製拡張アプリのリリースJSONを読み込み、変更履歴として表示します。';

    public function init()
    {
        $this->boot();
    }

    public function checkRequirements()
    {
        return true;
    }

    public function install()
    {
        $this->boot();
    }

    public function uninstall()
    {
    }

    public function update()
    {
        $this->boot();
        return true;
    }

    public function activate()
    {
        $this->boot();
        return true;
    }

    public function deactivate()
    {
        return true;
    }

    public static function registerAutoloader()
    {
        require_once __DIR__ . '/Bootstrap.php';
        Bootstrap::registerAutoloader();
    }

    private function boot()
    {
        self::registerAutoloader();
        $this->injectAdminTemplate();
        $this->syncGetWrappers();
        $this->syncPostWrappers();
    }

    private function injectAdminTemplate()
    {
        InjectTemplate::singleton()->add(
            'admin-main',
            PLUGIN_DIR . 'DF_ReleasePublisher/template/admin/app/df-release-publisher.html'
        );
        InjectTemplate::singleton()->add(
            'admin-topicpath',
            PLUGIN_DIR . 'DF_ReleasePublisher/template/admin/topicpath/df-release-publisher.html'
        );
    }

    private function syncGetWrappers()
    {
        $this->syncWrappers(self::$getWrappers, PLUGIN_LIB_DIR . 'DF_ReleasePublisher/template/get/', SCRIPT_DIR . 'extension/acms/GET/', self::GET_WRAPPER_MARKER);
    }

    private function syncPostWrappers()
    {
        $this->syncWrappers(self::$postWrappers, PLUGIN_LIB_DIR . 'DF_ReleasePublisher/template/post/', SCRIPT_DIR . 'extension/acms/POST/', self::POST_WRAPPER_MARKER);
    }

    private function syncWrappers(array $files, $sourceDir, $destDir, $marker)
    {
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        if (!is_dir($destDir) || !is_writable($destDir)) {
            return;
        }

        foreach ($files as $file) {
            $source = $sourceDir . $file;
            $dest = $destDir . $file;
            if (!is_file($source)) {
                continue;
            }
            $sourceContent = (string)@file_get_contents($source);
            if ($sourceContent === '' || strpos($sourceContent, $marker) === false) {
                continue;
            }
            if (is_file($dest)) {
                if (!is_writable($dest)) {
                    continue;
                }
                $content = (string)@file_get_contents($dest);
                if ($content !== '' && strpos($content, $marker) === false && strpos($content, 'Acms\\Plugins\\DF_ReleasePublisher') === false) {
                    continue;
                }
            }
            @copy($source, $dest);
        }
    }
}

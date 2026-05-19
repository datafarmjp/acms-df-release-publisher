<?php

namespace Acms\Plugins\DF_ReleasePublisher\POST;

use ACMS_POST;
use Acms\Plugins\DF_ReleasePublisher\Services\ReleaseFeed;
use Acms\Plugins\DF_ReleasePublisher\Services\Settings;
use Acms\Services\Facades\Common;

class ReleasePublisherPreview extends ACMS_POST
{
    public $isCacheDelete = false;

    public function post()
    {
        try {
            if (!$this->canUseAdminPost()) {
                Common::responseJson([
                    'status' => 'failure',
                    'message' => 'リリースJSONを確認する権限がありません。',
                ]);
            }

            $result = ReleaseFeed::preview(Settings::products(), Settings::limit());
            Common::responseJson(array_merge(['status' => 'success'], $result));
        } catch (\Throwable $e) {
            Common::responseJson([
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function canUseAdminPost(): bool
    {
        if (function_exists('sessionWithAdministration') && sessionWithAdministration(BID)) {
            return true;
        }
        if (function_exists('roleAvailableUser') && roleAvailableUser()) {
            return function_exists('roleAuthorization') && roleAuthorization('config_edit', BID);
        }
        return false;
    }
}

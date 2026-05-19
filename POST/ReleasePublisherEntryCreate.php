<?php

namespace Acms\Plugins\DF_ReleasePublisher\POST;

use ACMS_POST;
use Acms\Plugins\DF_ReleasePublisher\Services\ReleaseEntryPublisher;
use Acms\Plugins\DF_ReleasePublisher\Services\Settings;
use Acms\Services\Facades\Common;

class ReleasePublisherEntryCreate extends ACMS_POST
{
    public $isCacheDelete = true;

    public function post()
    {
        try {
            if (!$this->canUseAdminPost()) {
                Common::responseJson([
                    'status' => 'failure',
                    'message' => '告知エントリーを作成する権限がありません。',
                ]);
            }

            $result = ReleaseEntryPublisher::publishLatest(
                Settings::products(),
                Settings::limit(),
                Settings::entrySettings()
            );
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
            return function_exists('roleAuthorization') && roleAuthorization('entry_edit', BID);
        }
        return false;
    }
}

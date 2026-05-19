<?php

namespace Acms\Plugins\DF_ReleasePublisher\POST;

use ACMS_POST;
use Acms\Plugins\DF_ReleasePublisher\Services\ReleaseEntryPublisher;
use Acms\Plugins\DF_ReleasePublisher\Services\Settings;
use Acms\Services\Facades\Common;

class ReleasePublisherWebhook extends ACMS_POST
{
    public $isCacheDelete = true;

    public function fire()
    {
        $app = \App::getInstance();
        $this->Q =& $app->getQueryParameter();
        $this->Get =& $app->getGetParameter();
        $this->Post =& $app->getPostParameter();
        $this->systemErrors = new \Field_Validation();
        $this->errors = new \Field_Validation();
        $this->messages = new \Field_Validation();

        return $this->post();
    }

    public function post()
    {
        try {
            $payload = $this->payload();
            if (!$this->authorized((string)($payload['api_token'] ?? $payload['token'] ?? ''))) {
                Common::responseJson([
                    'status' => 'failure',
                    'message' => 'APIトークンが一致しません。',
                ]);
            }

            $product = trim((string)($payload['product'] ?? ''));
            $version = trim((string)($payload['version'] ?? ''));
            if ($product === '' || $version === '') {
                Common::responseJson([
                    'status' => 'failure',
                    'message' => 'product と version が必要です。',
                ]);
            }

            $result = ReleaseEntryPublisher::publishLatest(
                Settings::products(),
                Settings::limit(),
                Settings::entrySettings(),
                [
                    'product' => $product,
                    'version' => $version,
                ]
            );
            $hasResult = !empty($result['created']) || !empty($result['existing']);
            $hasError = !empty($result['errors']);
            Common::responseJson(array_merge([
                'status' => $hasError && !$hasResult ? 'failure' : 'success',
            ], $result));
        } catch (\Throwable $e) {
            Common::responseJson([
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function authorized(string $token): bool
    {
        $configured = Settings::apiToken();
        return $configured !== '' && $token !== '' && hash_equals($configured, $token);
    }

    private function payload(): array
    {
        $payload = $_POST;
        $raw = (string)@file_get_contents('php://input');
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $payload = array_merge($payload, $json);
            }
        }
        return $payload;
    }
}

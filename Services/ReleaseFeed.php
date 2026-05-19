<?php

namespace Acms\Plugins\DF_ReleasePublisher\Services;

class ReleaseFeed
{
    public static function rows(array $products, int $limit): array
    {
        $rows = [];
        foreach ($products as $product) {
            foreach (self::releasesForProduct($product) as $release) {
                $rows[] = $release;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
        });

        return array_slice($rows, 0, max(1, min(50, $limit)));
    }

    public static function preview(array $products, int $limit): array
    {
        if (!$products) {
            return [
                'amount' => 0,
                'releases' => [],
                'errors' => [[
                    'product' => '',
                    'json_url' => '',
                    'message' => '保存済みの表示対象プロダクトがありません。設定JSONの形式と必須項目を確認してください。',
                ]],
            ];
        }

        $rows = [];
        $errors = [];
        foreach ($products as $product) {
            $result = self::releasesForProductWithDiagnostics($product);
            foreach ($result['releases'] as $release) {
                $rows[] = $release;
            }
            foreach ($result['errors'] as $error) {
                $errors[] = $error;
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
        });

        $rows = array_slice($rows, 0, max(1, min(50, $limit)));
        return [
            'amount' => count($rows),
            'releases' => $rows,
            'errors' => $errors,
        ];
    }

    private static function releasesForProduct(array $product): array
    {
        return self::releasesForProductWithDiagnostics($product)['releases'];
    }

    private static function releasesForProductWithDiagnostics(array $product): array
    {
        $jsonUrl = (string)($product['json_url'] ?? '');
        $loaded = self::loadJsonWithDiagnostics($jsonUrl);
        if (!is_array($loaded['payload'])) {
            return [
                'releases' => [],
                'errors' => [[
                    'product' => (string)($product['product'] ?? ''),
                    'json_url' => trim($jsonUrl),
                    'message' => $loaded['message'],
                ]],
            ];
        }

        $payload = $loaded['payload'];
        $items = isset($payload['releases']) && is_array($payload['releases']) ? $payload['releases'] : [$payload];
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = self::normalizeRelease($item, $product);
            if ($row) {
                $rows[] = $row;
            }
        }
        if ($rows) {
            return [
                'releases' => $rows,
                'errors' => [],
            ];
        }

        return [
            'releases' => [],
            'errors' => [[
                'product' => (string)($product['product'] ?? ''),
                'json_url' => trim($jsonUrl),
                'message' => 'JSONは取得できましたが、有効なリリース情報がありませんでした。version などの必須項目を確認してください。',
            ]],
        ];
    }

    private static function loadJsonWithDiagnostics(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [
                'payload' => null,
                'message' => 'JSON URLが空です。',
            ];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => "User-Agent: DF_ReleasePublisher/0.3\r\n",
            ],
            'https' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => "User-Agent: DF_ReleasePublisher/0.3\r\n",
            ],
        ]);

        $body = false;
        $source = $url;
        if (preg_match('#^https?://#i', $url)) {
            $body = @file_get_contents($url, false, $context);
        } else {
            $path = $url;
            if ($path !== '' && $path[0] === '/') {
                $path = defined('SCRIPT_DIR') ? rtrim(SCRIPT_DIR, '/') . $path : $path;
            }
            $source = $path;
            if (is_file($path)) {
                $body = @file_get_contents($path);
            }
        }

        if (!is_string($body) || $body === '') {
            return [
                'payload' => null,
                'message' => sprintf('JSONを取得できませんでした。URLまたは配置先を確認してください。(%s)', $source),
            ];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [
                'payload' => null,
                'message' => 'JSONを読み取れませんでした。JSON形式を確認してください。',
            ];
        }

        return [
            'payload' => $json,
            'message' => '',
        ];
    }

    private static function normalizeRelease(array $item, array $product): ?array
    {
        $version = self::text((string)($item['version'] ?? ''), 40);
        if ($version === '') {
            return null;
        }
        $changes = [];
        if (isset($item['changes']) && is_array($item['changes'])) {
            foreach ($item['changes'] as $change) {
                $text = self::text((string)$change, 240);
                if ($text !== '') {
                    $changes[] = ['text' => $text];
                }
            }
        }

        return [
            'product' => self::text((string)($item['product'] ?? $product['product']), 80),
            'display_name' => self::text((string)($item['display_name'] ?? $product['display_name']), 80),
            'version' => $version,
            'tag' => self::text((string)($item['tag'] ?? ('v' . $version)), 40),
            'date' => self::date((string)($item['date'] ?? '')),
            'title' => self::text((string)($item['title'] ?? ($product['display_name'] . ' v' . $version)), 160),
            'github_release_url' => self::url((string)($item['github_release_url'] ?? $product['github_releases_url'] ?? '')),
            'changelog_url' => self::url((string)($item['changelog_url'] ?? '')),
            'download_url' => self::url((string)($item['download_url'] ?? '')),
            'previous_version' => self::text((string)($item['previous_version'] ?? ''), 40),
            'previous_tag' => self::text((string)($item['previous_tag'] ?? ''), 40),
            'body_markdown' => self::text((string)($item['body_markdown'] ?? ''), 3000),
            'body_markdown_since_previous_release' => self::text((string)($item['body_markdown_since_previous_release'] ?? ''), 5000),
            'changes' => $changes,
        ];
    }

    private static function text(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }

    private static function date(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function url(string $value): string
    {
        $value = trim($value);
        return preg_match('#^https?://#i', $value) ? $value : '';
    }
}

<?php

namespace Acms\Plugins\DF_ReleasePublisher\Services;

use Acms\Services\Facades\Config;

class Settings
{
    /**
     * @var \Field|null
     */
    private static $config = null;

    public static function all(): array
    {
        return [
            'products' => self::products(),
            'limit' => self::limit(),
            'entry' => self::entrySettings(),
            'apiTokenConfigured' => self::apiToken() !== '',
        ];
    }

    public static function products(): array
    {
        $raw = self::value('df_release_publisher_products');
        if ($raw === '') {
            return [];
        }

        $json = json_decode(rawurldecode($raw), true);
        if (!is_array($json)) {
            $json = json_decode($raw, true);
        }
        if (!is_array($json)) {
            return [];
        }

        $items = isset($json['products']) && is_array($json['products']) ? $json['products'] : $json;
        $products = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $product = self::slug((string)($item['product'] ?? ''));
            $jsonUrl = trim((string)($item['json_url'] ?? $item['jsonUrl'] ?? ''));
            if ($product === '' || $jsonUrl === '') {
                continue;
            }
            $products[] = [
                'product' => $product,
                'display_name' => self::text((string)($item['display_name'] ?? $item['displayName'] ?? $product), 80),
                'json_url' => $jsonUrl,
                'github_releases_url' => trim((string)($item['github_releases_url'] ?? $item['githubReleasesUrl'] ?? '')),
                'entry_blog_id' => max(0, (int)($item['entry_blog_id'] ?? $item['entryBlogId'] ?? 0)),
                'entry_category_id' => max(0, (int)($item['entry_category_id'] ?? $item['entryCategoryId'] ?? 0)),
                'entry_status' => self::status((string)($item['entry_status'] ?? $item['entryStatus'] ?? '')),
                'entry_user_id' => max(0, (int)($item['entry_user_id'] ?? $item['entryUserId'] ?? 0)),
            ];
        }

        return array_values($products);
    }

    public static function limit(): int
    {
        $value = (int)self::value('df_release_publisher_limit');
        if ($value < 1 || $value > 50) {
            return 10;
        }
        return $value;
    }

    public static function apiToken(): string
    {
        return self::value('df_release_publisher_api_token');
    }

    public static function entrySettings(): array
    {
        return [
            'blog_id' => self::entryBlogId(),
            'category_id' => self::entryCategoryId(),
            'status' => self::entryStatus(),
            'user_id' => self::entryUserId(),
        ];
    }

    public static function entryBlogId(): int
    {
        return max(0, (int)self::value('df_release_publisher_entry_blog_id'));
    }

    public static function entryCategoryId(): int
    {
        return max(0, (int)self::value('df_release_publisher_entry_category_id'));
    }

    public static function entryStatus(): string
    {
        return self::status(self::value('df_release_publisher_entry_status')) ?: 'draft';
    }

    public static function entryUserId(): int
    {
        return max(0, (int)self::value('df_release_publisher_entry_user_id'));
    }

    private static function value(string $key): string
    {
        $config = self::config();
        if (!$config) {
            return '';
        }
        return trim((string)$config->get($key));
    }

    private static function config(): ?\Field
    {
        if (self::$config) {
            return self::$config;
        }
        if (!defined('BID') || !BID) {
            return null;
        }

        $currentBid = (int)BID;
        $config = Config::loadDefaultField();
        foreach (self::ancestorBlogIds($currentBid) as $bid) {
            $config->overload(Config::loadBlogConfig($bid));
        }
        $config->overload(Config::loadBlogConfigSet($currentBid));
        $config->overload(Config::loadBlogConfig($currentBid));

        self::$config = $config;
        return self::$config;
    }

    private static function ancestorBlogIds(int $bid): array
    {
        $SQL = \SQL::newSelect('blog');
        $SQL->addSelect('blog_id');
        \ACMS_Filter::blogTree($SQL, $bid, 'ancestor-or-self');
        $SQL->setOrder('blog_left', 'ASC');

        $rows = \DB::query($SQL->get(dsn()), 'all');
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)($row['blog_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids ?: [$bid];
    }

    private static function slug(string $value): string
    {
        $value = trim($value);
        return preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $value) ? $value : '';
    }

    private static function text(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }

    private static function status(string $value): string
    {
        return in_array($value, ['draft', 'open', 'close'], true) ? $value : '';
    }
}

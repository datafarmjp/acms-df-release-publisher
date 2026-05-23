<?php

namespace Acms\Plugins\DF_ReleasePublisher\Services;

use Acms\Services\Entry\EntryRepository;
use Acms\Services\Facades\Common;

class ReleaseEntryPublisher
{
    public static function publishLatest(array $products, int $limit, array $settings, array $filter = []): array
    {
        $targetProduct = trim((string)($filter['product'] ?? ''));
        $targetVersion = trim((string)($filter['version'] ?? ''));
        if ($targetProduct !== '') {
            $products = array_values(array_filter($products, function ($product) use ($targetProduct) {
                return (string)($product['product'] ?? '') === $targetProduct;
            }));
            if (!$products) {
                return [
                    'created' => [],
                    'existing' => [],
                    'errors' => [['message' => '指定されたproductは保存済み設定にありません。', 'product' => $targetProduct]],
                ];
            }
        }

        $preview = ReleaseFeed::preview($products, $limit);
        $created = [];
        $existing = [];
        $errors = $preview['errors'];
        foreach ($preview['releases'] as $release) {
            if ($targetVersion !== '' && (string)$release['version'] !== $targetVersion) {
                $errors[] = [
                    'product' => (string)$release['product'],
                    'message' => 'POSTされたversionとlatest.jsonのversionが一致しません。',
                ];
                continue;
            }

            $productConfig = self::productConfig($products, (string)$release['product']);
            $target = self::entryTarget($settings, $productConfig);
            $blogId = (int)$target['blog_id'];
            $categoryId = (int)$target['category_id'];
            $status = (string)$target['status'];
            $userId = self::resolveUserId((int)$target['user_id'], $blogId);
            $targetErrors = self::validateTarget($blogId, $categoryId, $status, $userId);
            if ($targetErrors) {
                foreach ($targetErrors as $error) {
                    $error['product'] = (string)$release['product'];
                    $errors[] = $error;
                }
                continue;
            }

            $found = self::findExisting((string)$release['product'], (string)$release['version'], $blogId);
            if ($found) {
                $existing[] = array_merge($found, [
                    'product' => $release['product'],
                    'version' => $release['version'],
                    'title' => $found['title'],
                ]);
                continue;
            }
            $created[] = self::createEntry($release, $blogId, $categoryId, $status, $userId);
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'errors' => $errors,
        ];
    }

    private static function productConfig(array $products, string $productName): array
    {
        foreach ($products as $product) {
            if ((string)($product['product'] ?? '') === $productName) {
                return $product;
            }
        }
        return [];
    }

    private static function entryTarget(array $settings, array $product): array
    {
        return [
            'blog_id' => (int)($product['entry_blog_id'] ?? 0) ?: (int)($settings['blog_id'] ?? 0),
            'category_id' => (int)($product['entry_category_id'] ?? 0) ?: (int)($settings['category_id'] ?? 0),
            'status' => (string)($product['entry_status'] ?? '') ?: (string)($settings['status'] ?? 'draft'),
            'user_id' => (int)($product['entry_user_id'] ?? 0) ?: (int)($settings['user_id'] ?? 0),
        ];
    }

    private static function validateTarget(int $blogId, int $categoryId, string $status, int $userId): array
    {
        $errors = [];
        if ($blogId < 1) {
            $errors[] = ['message' => '投稿先ブログIDを設定してください。'];
        } elseif (!self::blogExists($blogId)) {
            $errors[] = ['message' => '投稿先ブログIDが見つかりません。'];
        }
        if ($categoryId < 1) {
            $errors[] = ['message' => '投稿先カテゴリーIDを設定してください。'];
        } elseif (!self::categoryExists($categoryId, $blogId)) {
            $errors[] = ['message' => '投稿先カテゴリーIDが投稿先ブログに存在しません。'];
        }
        if (!in_array($status, ['draft', 'open', 'close'], true)) {
            $errors[] = ['message' => '投稿ステータスが不正です。'];
        }
        if ($userId < 1 || !self::userExists($userId, $blogId)) {
            $errors[] = ['message' => '投稿ユーザーIDを確認できません。'];
        }
        return $errors;
    }

    private static function blogExists(int $blogId): bool
    {
        $sql = \SQL::newSelect('blog');
        $sql->setSelect('blog_id');
        $sql->addWhereOpr('blog_id', $blogId);
        return (int)\DB::query($sql->get(dsn()), 'one') === $blogId;
    }

    private static function categoryExists(int $categoryId, int $blogId): bool
    {
        $sql = \SQL::newSelect('category');
        $sql->setSelect('category_id');
        $sql->addWhereOpr('category_id', $categoryId);
        $sql->addWhereOpr('category_blog_id', $blogId);
        return (int)\DB::query($sql->get(dsn()), 'one') === $categoryId;
    }

    private static function userExists(int $userId, int $blogId): bool
    {
        $sql = \SQL::newSelect('user');
        $sql->setSelect('user_id');
        $sql->addWhereOpr('user_id', $userId);
        $blogIds = self::ancestorOrSelfBlogIds($blogId);
        if (!$blogIds) {
            return false;
        }
        $sql->addWhereIn('user_blog_id', $blogIds);
        return (int)\DB::query($sql->get(dsn()), 'one') === $userId;
    }

    private static function resolveUserId(int $configuredUserId, int $blogId): int
    {
        if (defined('SUID') && (int)SUID > 0) {
            return (int)SUID;
        }
        if ($configuredUserId > 0) {
            return $configuredUserId;
        }

        $blogIds = self::ancestorOrSelfBlogIds($blogId);
        if (!$blogIds) {
            return 0;
        }
        foreach ($blogIds as $candidateBlogId) {
            $sql = \SQL::newSelect('user');
            $sql->setSelect('user_id');
            $sql->addWhereOpr('user_blog_id', $candidateBlogId);
            $sql->setOrder('user_id', 'ASC');
            $sql->setLimit(1);
            $userId = (int)\DB::query($sql->get(dsn()), 'one');
            if ($userId > 0) {
                return $userId;
            }
        }
        return 0;
    }

    private static function ancestorOrSelfBlogIds(int $blogId): array
    {
        $sql = \SQL::newSelect('blog');
        $sql->addSelect('blog_id');
        \ACMS_Filter::blogTree($sql, $blogId, 'ancestor-or-self');
        $sql->setOrder('blog_left', 'DESC');

        $rows = \DB::query($sql->get(dsn()), 'all');
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)($row['blog_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private static function findExisting(string $product, string $version, int $blogId): ?array
    {
        $productIds = self::fieldEntryIds('df_release_product', $product);
        if (!$productIds) {
            return null;
        }
        $versionIds = self::fieldEntryIds('df_release_version', $version, $productIds);
        if (!$versionIds) {
            return null;
        }

        $sql = \SQL::newSelect('entry');
        $sql->addSelect('entry_id');
        $sql->addSelect('entry_title');
        $sql->addSelect('entry_status');
        $sql->addWhereIn('entry_id', $versionIds);
        $sql->addWhereOpr('entry_blog_id', $blogId);
        $sql->addWhereOpr('entry_status', 'trash', '<>');
        $sql->setOrder('entry_id', 'DESC');
        $sql->setLimit(1);
        $row = \DB::query($sql->get(dsn()), 'row');
        if (!$row) {
            return null;
        }
        return [
            'eid' => (int)$row['entry_id'],
            'title' => (string)$row['entry_title'],
            'status' => (string)$row['entry_status'],
        ];
    }

    private static function fieldEntryIds(string $key, string $value, array $entryIds = []): array
    {
        $sql = \SQL::newSelect('field');
        $sql->setSelect('field_eid');
        $sql->addWhereOpr('field_key', $key);
        $sql->addWhereOpr('field_value', $value);
        if ($entryIds) {
            $sql->addWhereIn('field_eid', $entryIds);
        }
        $rows = \DB::query($sql->get(dsn()), 'list');
        return array_values(array_filter(array_map('intval', is_array($rows) ? $rows : [])));
    }

    private static function createEntry(array $release, int $blogId, int $categoryId, string $status, int $userId): array
    {
        $eid = (int)\DB::query(\SQL::nextval('entry_id', dsn()), 'seq');
        $date = self::releaseDate((string)($release['date'] ?? ''));
        $datetime = $date . ' 00:00:00';
        $posted = date('Y-m-d H:i:s', defined('REQUEST_TIME') ? REQUEST_TIME : time());
        $title = self::entryTitle($release);
        $repository = new EntryRepository();

        $entry = [
            'entry_id' => $eid,
            'entry_code' => self::entryCode($release, $eid),
            'entry_status' => $status,
            'entry_sort' => $repository->nextSort($blogId),
            'entry_user_sort' => $repository->nextUserSort($userId, $blogId),
            'entry_category_sort' => $repository->nextCategorySort($categoryId, $blogId),
            'entry_title' => $title,
            'entry_link' => '',
            'entry_datetime' => $datetime,
            'entry_start_datetime' => $datetime,
            'entry_end_datetime' => '9999-12-31 23:59:59',
            'entry_posted_datetime' => $posted,
            'entry_updated_datetime' => $posted,
            'entry_hash' => md5((defined('SYSTEM_GENERATED_DATETIME') ? SYSTEM_GENERATED_DATETIME : '') . $posted),
            'entry_summary_range' => 1,
            'entry_indexing' => 'on',
            'entry_members_only' => 'off',
            'entry_primary_image' => null,
            'entry_category_id' => $categoryId,
            'entry_user_id' => $userId,
            'entry_blog_id' => $blogId,
        ];
        if (class_exists('\Acms\Services\Entry\Enums\EntryApprovalStatus')) {
            $entry['entry_approval'] = \Acms\Services\Entry\Enums\EntryApprovalStatus::None->value;
        }

        $sql = \SQL::newInsert('entry');
        foreach ($entry as $key => $value) {
            $sql->addInsert($key, $value);
        }
        \DB::query($sql->get(dsn()), 'exec');
        self::insertBodyUnit($eid, $blogId, self::bodyHtml($release));
        self::saveReleaseFields($eid, $blogId, $release);
        Common::saveFulltext('eid', $eid, self::fulltext($release, $title), $blogId);
        if (class_exists('\ACMS_RAM')) {
            \ACMS_RAM::entry($eid, $entry);
        }

        return [
            'eid' => $eid,
            'product' => $release['product'],
            'version' => $release['version'],
            'title' => $title,
            'status' => $status,
        ];
    }

    private static function insertBodyUnit(int $entryId, int $blogId, string $html): void
    {
        $sql = \SQL::newInsert('column');
        $sql->addInsert('column_id', (int)\DB::query(\SQL::nextval('column_id', dsn()), 'seq'));
        $sql->addInsert('column_sort', 1);
        $sql->addInsert('column_align', 'auto');
        $sql->addInsert('column_type', 'html');
        $sql->addInsert('column_attr', '');
        $sql->addInsert('column_size', '');
        $sql->addInsert('column_field_1', $html);
        $sql->addInsert('column_field_2', '');
        $sql->addInsert('column_field_3', '');
        $sql->addInsert('column_field_4', '');
        $sql->addInsert('column_field_5', '');
        $sql->addInsert('column_entry_id', $entryId);
        $sql->addInsert('column_blog_id', $blogId);
        \DB::query($sql->get(dsn()), 'exec');
    }

    private static function saveReleaseFields(int $entryId, int $blogId, array $release): void
    {
        $field = new \Field();
        $values = [
            'df_release_product' => (string)($release['product'] ?? ''),
            'df_release_version' => (string)($release['version'] ?? ''),
            'df_release_tag' => (string)($release['tag'] ?? ''),
            'df_release_github_release_url' => (string)($release['github_release_url'] ?? ''),
            'df_release_download_url' => (string)($release['download_url'] ?? ''),
        ];
        foreach ($values as $key => $value) {
            $field->setField($key, $value);
            $field->setMeta($key, 'search', true);
        }
        Common::saveField('eid', $entryId, $field, null, null, $blogId);
    }

    private static function entryTitle(array $release): string
    {
        return trim((string)($release['display_name'] ?? $release['product'] ?? 'DF拡張アプリ') . ' ' . (string)($release['tag'] ?? '')) . ' を公開しました';
    }

    private static function entryCode(array $release, int $entryId): string
    {
        $base = strtolower((string)($release['product'] ?? 'df-release') . '-' . (string)($release['tag'] ?? $entryId));
        $base = preg_replace('/[^a-z0-9_-]+/', '-', $base);
        return trim((string)$base, '-') ?: ('df-release-' . $entryId);
    }

    private static function releaseDate(string $date): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    }

    private static function bodyHtml(array $release): string
    {
        $html = '<p>' . self::h((string)($release['display_name'] ?? $release['product'] ?? 'DF拡張アプリ')) . ' ' . self::h((string)($release['tag'] ?? '')) . ' を公開しました。</p>';
        if (!empty($release['changes']) && is_array($release['changes'])) {
            $html .= '<h3>変更内容</h3>';
            $html .= '<ul>';
            foreach ($release['changes'] as $change) {
                $text = is_array($change) ? (string)($change['text'] ?? '') : (string)$change;
                if ($text !== '') {
                    $html .= '<li>' . self::h($text) . '</li>';
                }
            }
            $html .= '</ul>';
        }
        $links = [];
        foreach (['github_release_url' => 'GitHub Release', 'download_url' => 'Download'] as $key => $label) {
            $url = (string)($release[$key] ?? '');
            if ($url !== '') {
                $links[] = '<a href="' . self::h($url) . '">' . self::h($label) . '</a>';
            }
        }
        if ($links) {
            $html .= '<p>' . implode(' / ', $links) . '</p>';
        }
        return $html;
    }

    private static function fulltext(array $release, string $title): string
    {
        $changes = [];
        foreach ((array)($release['changes'] ?? []) as $change) {
            $changes[] = is_array($change) ? (string)($change['text'] ?? '') : (string)$change;
        }
        return trim(implode(' ', array_filter([
            $title,
            (string)($release['product'] ?? ''),
            (string)($release['version'] ?? ''),
            (string)($release['tag'] ?? ''),
            implode(' ', $changes),
            (string)($release['github_release_url'] ?? ''),
        ])));
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

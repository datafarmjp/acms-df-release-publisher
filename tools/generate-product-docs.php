#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = args($argv);

foreach (['config', 'output-dir'] as $key) {
    if (empty($options[$key])) {
        fwrite(STDERR, "Missing --{$key}.\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$pluginsDir = isset($options['plugins-dir'])
    ? (string)$options['plugins-dir']
    : dirname($root);
$outputDir = rtrim((string)$options['output-dir'], '/');
$configPath = (string)$options['config'];

$config = json_decode((string)file_get_contents($configPath), true);
if (!is_array($config)) {
    fwrite(STDERR, "Config JSON is invalid: {$configPath}\n");
    exit(1);
}

$docs = is_array($config['df_release_docs'] ?? null) ? $config['df_release_docs'] : [];
$products = is_array($config['df_release_products'] ?? null) ? $config['df_release_products'] : [];
$topIncludeName = pathPart((string)($docs['top_include_name'] ?? '_top_include.html'), '_top_include.html');
$changelogIncludeName = pathPart((string)($docs['changelog_include_name'] ?? 'changelog_include.html'), 'changelog_include.html');

if (!$products) {
    fwrite(STDERR, "Config does not contain df_release_products.\n");
    exit(1);
}

$generated = [];
$hasErrors = false;
foreach ($products as $product) {
    if (!is_array($product)) {
        continue;
    }
    $productName = pathPart((string)($product['product'] ?? ''), '');
    $categoryCode = pathPart((string)($product['category_code'] ?? ''), '');
    if ($productName === '' || $categoryCode === '') {
        fwrite(STDERR, "Skipped product with missing product/category_code.\n");
        continue;
    }

    $sourcePath = trim((string)($product['source_path'] ?? ''));
    $productDir = $sourcePath !== ''
        ? rtrim($sourcePath, '/')
        : rtrim($pluginsDir, '/') . '/' . $productName;
    $publicPagePath = $productDir . '/docs/public-page.md';
    $changelogPath = $productDir . '/CHANGELOG.md';
    if (!is_file($publicPagePath)) {
        fwrite(STDERR, "docs/public-page.md was not found: {$publicPagePath}\n");
        $hasErrors = true;
        continue;
    }

    $targetDir = $outputDir . '/' . $categoryCode;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        fwrite(STDERR, "Failed to create output directory: {$targetDir}\n");
        exit(1);
    }

    $displayName = (string)($product['display_name'] ?? $productName);
    writeHtml(
        $targetDir . '/' . $topIncludeName,
        mdToHtml(stripFirstH1((string)file_get_contents($publicPagePath))),
        $displayName,
        'docs/public-page.md'
    );
    writeHtml(
        $targetDir . '/' . $changelogIncludeName,
        is_file($changelogPath)
            ? mdToHtml(stripFirstH1((string)file_get_contents($changelogPath)))
            : '<p>変更履歴はまだありません。</p>' . "\n",
        $displayName,
        'CHANGELOG.md'
    );

    $generated[] = $categoryCode . '/' . $topIncludeName;
    $generated[] = $categoryCode . '/' . $changelogIncludeName;
}

if ($hasErrors) {
    fwrite(STDERR, "Product docs generation failed. Add docs/public-page.md to every configured product.\n");
    exit(1);
}

if (!$generated) {
    fwrite(STDERR, "No product docs were generated.\n");
    exit(1);
}

foreach ($generated as $path) {
    echo "generated {$path}\n";
}

function writeHtml(string $path, string $html, string $displayName, string $sourceName): void
{
    $content = "<!-- Generated from {$sourceName} for " . h($displayName) . ". Do not edit directly. -->\n";
    $content .= "<div class=\"df-product-docs df-product-docs--" . h(basename(dirname($path))) . "\">\n";
    $content .= $html;
    $content .= "</div>\n";
    file_put_contents($path, $content);
}

function stripFirstH1(string $markdown): string
{
    return preg_replace('/\A\s*#\s+.+(?:\R|$)/u', '', $markdown, 1) ?? $markdown;
}

function mdToHtml(string $markdown): string
{
    $lines = preg_split('/\R/u', str_replace("\r\n", "\n", $markdown));
    if (!is_array($lines)) {
        return '';
    }

    $html = '';
    $paragraph = [];
    $inList = false;
    $inCode = false;
    $code = [];

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if (!$paragraph) {
            return;
        }
        $html .= '<p>' . inline(implode(' ', $paragraph)) . "</p>\n";
        $paragraph = [];
    };
    $closeList = static function () use (&$html, &$inList): void {
        if ($inList) {
            $html .= "</ul>\n";
            $inList = false;
        }
    };

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            if ($inCode) {
                $html .= '<pre><code>' . h(implode("\n", $code)) . "</code></pre>\n";
                $code = [];
                $inCode = false;
            } else {
                $flushParagraph();
                $closeList();
                $inCode = true;
            }
            continue;
        }

        if ($inCode) {
            $code[] = $line;
            continue;
        }

        if (trim($line) === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^<a\s+id="[-A-Za-z0-9_.:]+"\s*><\/a>$/', trim($line))) {
            $flushParagraph();
            $closeList();
            $html .= trim($line) . "\n";
            continue;
        }

        if (preg_match('/^(#{2,6})\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            $closeList();
            $level = min(6, strlen($matches[1]));
            $html .= '<h' . $level . '>' . inline(trim($matches[2])) . '</h' . $level . ">\n";
            continue;
        }

        if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            $html .= '<li>' . inline(trim($matches[1])) . "</li>\n";
            continue;
        }

        if (preg_match('/^>\s?(.+)$/u', $line, $matches)) {
            $flushParagraph();
            $closeList();
            $html .= '<blockquote><p>' . inline(trim($matches[1])) . "</p></blockquote>\n";
            continue;
        }

        $paragraph[] = trim($line);
    }

    if ($inCode) {
        $html .= '<pre><code>' . h(implode("\n", $code)) . "</code></pre>\n";
    }
    $flushParagraph();
    $closeList();

    return $html;
}

function inline(string $text): string
{
    $escaped = h($text);
    $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', static function (array $matches): string {
        $url = html_entity_decode((string)$matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (preg_match('/^\s*javascript:/i', $url)) {
            return h($matches[1]);
        }
        return '<a href="' . h($matches[2]) . '">' . h($matches[1]) . '</a>';
    }, $escaped) ?? $escaped;
    return $escaped;
}

function pathPart(string $value, string $default): string
{
    $value = trim($value);
    if ($value === '') {
        return $default;
    }
    return preg_match('/^[A-Za-z0-9_.-]+$/', $value) ? $value : $default;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function args(array $argv): array
{
    $options = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = (string)$argv[$i];
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $key = substr($arg, 2);
        $value = '1';
        if (strpos($key, '=') !== false) {
            [$key, $value] = explode('=', $key, 2);
        } elseif (isset($argv[$i + 1]) && strpos((string)$argv[$i + 1], '--') !== 0) {
            $value = (string)$argv[++$i];
        }
        $options[$key] = $value;
    }
    return $options;
}

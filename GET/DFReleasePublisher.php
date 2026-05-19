<?php

namespace Acms\Plugins\DF_ReleasePublisher\GET;

use ACMS_Corrector;
use ACMS_GET;
use Acms\Plugins\DF_ReleasePublisher\Services\ReleaseFeed;
use Acms\Plugins\DF_ReleasePublisher\Services\Settings;
use Template;

class DFReleasePublisher extends ACMS_GET
{
    public function get()
    {
        $limit = $this->limit();
        $products = Settings::products();
        $rows = ReleaseFeed::rows($products, $limit);
        $Tpl = new Template($this->template(), new ACMS_Corrector());

        $vars = [
            'release' => $rows,
            'amount' => count($rows),
        ];
        if (!$rows) {
            $vars['notFound'] = (object)[];
        }

        return $Tpl->render($vars);
    }

    private function limit(): int
    {
        $configured = Settings::limit();
        $arg = $this->arg('limit');
        if ((int)$arg > 0) {
            $configured = (int)$arg;
        }
        return max(1, min(50, $configured));
    }

    private function arg(string $key): string
    {
        if (isset($this->{$key})) {
            $value = trim((string)$this->{$key});
            if ($value !== '') {
                return $value;
            }
        }
        if ($this->Q) {
            $value = trim((string)$this->Q->get($key));
            if ($value !== '') {
                return $value;
            }
        }
        if ($this->Get) {
            $value = trim((string)$this->Get->get($key));
            if ($value !== '') {
                return $value;
            }
        }
        if (isset($_GET[$key])) {
            return trim((string)$_GET[$key]);
        }
        return '';
    }

    private function template(): string
    {
        $tpl = trim((string)$this->tpl);
        if ($tpl !== '') {
            return $tpl;
        }

        return '<div class="df-release-publisher">'
            . '<!-- BEGIN release:loop -->'
            . '<article class="df-release-publisher__item">'
            . '<p class="df-release-publisher__meta">{date} {display_name} {tag}</p>'
            . '<h2 class="df-release-publisher__title">{title}</h2>'
            . '<ul><!-- BEGIN changes:loop --><li>{text}</li><!-- END changes:loop --></ul>'
            . '<p><!-- BEGIN_IF [{github_release_url}/nem/] --><a href="{github_release_url}">GitHub Release</a><!-- END_IF -->'
            . '<!-- BEGIN_IF [{changelog_url}/nem/] --> <a href="{changelog_url}">CHANGELOG</a><!-- END_IF -->'
            . '<!-- BEGIN_IF [{download_url}/nem/] --> <a href="{download_url}">Download</a><!-- END_IF --></p>'
            . '</article>'
            . '<!-- END release:loop -->'
            . '<!-- BEGIN notFound --><p>公開中の変更履歴はまだありません。</p><!-- END notFound -->'
            . '</div>';
    }
}

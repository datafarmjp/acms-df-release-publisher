# DF_ReleasePublisher

DF製 a-blog cms 拡張アプリのリリースJSONを読み込み、変更履歴として表示するための拡張アプリです。

## v0.1の範囲

- 複数プロダクトのリリースJSON URLを管理画面で設定できます。
- `DFReleasePublisher` モジュールで変更履歴を表示できます。
- お知らせエントリーの自動作成はまだ行いません。

## インストール

1. `DF_ReleasePublisher` フォルダを `extension/plugins/` に配置します。
2. a-blog cms の拡張アプリ管理から `DFリリース` をインストール・有効化します。
3. 管理画面の `DFリリース設定` で、表示対象プロダクトのJSON URLを設定します。

管理画面は `themes/system/admin/app/df-release-publisher.html` へコピーせず、a-blog cms の `InjectTemplate` でプラグイン内テンプレートを差し込みます。パンくず名も `admin-topicpath` で差し込みます。

## 設定例

```json
[
  {
    "product": "DF_InputAssist",
    "display_name": "DF入力支援",
    "json_url": "/media/releases/DF_InputAssist/latest.json",
    "github_releases_url": "https://github.com/datafarmjp/acms-df-input-assist/releases"
  }
]
```

まずは `DF_InputAssist` だけを登録して、`/media/releases/DF_InputAssist/latest.json` が読めることを確認します。複数アプリを表示したい場合は、同じ形式で配列要素を追加します。

## 表示例

```html
<!-- BEGIN_MODULE DFReleasePublisher limit="10" -->
<!-- BEGIN release:loop -->
<article>
  <p>{date} {display_name} {tag}</p>
  <h2>{title}</h2>
  <ul><!-- BEGIN changes:loop --><li>{text}</li><!-- END changes:loop --></ul>
  <p><a href="{github_release_url}">GitHub Release</a> <a href="{changelog_url}">CHANGELOG</a></p>
</article>
<!-- END release:loop -->
<!-- BEGIN notFound --><p>公開中の変更履歴はまだありません。</p><!-- END notFound -->
<!-- END_MODULE DFReleasePublisher -->
```

## リリースJSON形式

```json
{
  "product": "DF_InputAssist",
  "display_name": "DF入力支援",
  "version": "0.2.31",
  "tag": "v0.2.31",
  "previous_version": "0.2.30",
  "previous_tag": "v0.2.30",
  "date": "2026-05-06",
  "title": "DF入力支援 v0.2.31",
  "github_release_url": "https://github.com/datafarmjp/acms-df-input-assist/releases/tag/v0.2.31",
  "changelog_url": "https://github.com/datafarmjp/acms-df-input-assist/blob/v0.2.31/CHANGELOG.md#v0-2-31",
  "download_url": "https://github.com/datafarmjp/acms-df-input-assist/releases/download/v0.2.31/DF_InputAssist-v0.2.31.zip",
  "changes": ["READMEから開発者向け詳細を分離しました。"],
  "body_markdown": "- READMEから開発者向け詳細を分離しました。",
  "body_markdown_since_previous_release": "### 0.2.31 - 2026-05-06\n\n- READMEから開発者向け詳細を分離しました。"
}
```

## リリース

データファーム製 a-blog cms 拡張アプリの共通公開ルールは、`../_shared/DF_EXTENSION_APP_GUIDELINES.md` と `../_shared/DF_EXTENSION_APP_ADMIN_TEMPLATE_HOWTO.md` を参照してください。

```bash
tools/release.sh 0.1.0
```

リリーススクリプトは配布ZIP、GitHub Release本文、リリースJSONを生成します。`DF_RELEASE_SYNC_ENABLED=1` の場合だけ、生成JSONをSFTPで指定先へ同期します。

### DF_InputAssistでのJSON同期実証

標準の配置先は次の形です。

```text
media/releases/DF_InputAssist/latest.json
media/releases/DF_InputAssist/vX.Y.Z.json
```

SFTP同期を有効にするときは、接続情報を環境変数で渡します。値はリポジトリに保存しません。

```bash
export DF_RELEASE_SYNC_ENABLED=1
export DF_RELEASE_SYNC_HOST="example.com"
export DF_RELEASE_SYNC_USER="user"
export DF_RELEASE_SYNC_REMOTE_PATH="/path/to/public_html/media/releases"
export DF_RELEASE_SYNC_PORT=22
```

`DF_RELEASE_SYNC_REMOTE_PATH` は `media/releases` を指すディレクトリにします。`release.sh` はこの下に `{PRODUCT}/latest.json` と `{PRODUCT}/vX.Y.Z.json` を配置します。

## ライセンス

MIT License で公開しています。詳しくは `LICENSE` を参照してください。

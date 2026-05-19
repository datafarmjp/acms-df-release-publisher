# DF_ReleasePublisher

DF製 a-blog cms 拡張アプリのリリースJSONを読み込み、変更履歴として表示するための拡張アプリです。

## できること

- 複数プロダクトのリリースJSON URLを管理画面で設定できます。
- `DFReleasePublisher` モジュールで変更履歴を表示できます。
- 管理画面から保存済みリリースJSONを取得し、指定ブログ・カテゴリーへ告知エントリーを作成できます。
- `product + version` のカスタムフィールドで重複作成を防ぎます。
- `release.sh` から外部POSTを受け、告知エントリー作成を自動化できます。

## インストール

1. `DF_ReleasePublisher` フォルダを `extension/plugins/` に配置します。
2. a-blog cms の拡張アプリ管理から `DFリリース` をインストール・有効化します。
3. 管理画面の `DFリリース設定` で、表示対象プロダクトのJSON URLを設定します。
4. 告知エントリーを作成する場合は、投稿先ブログID、投稿先カテゴリーID、作成ステータス、投稿ユーザーID、APIトークンを設定します。

管理画面は `themes/system/admin/app/df-release-publisher.html` へコピーせず、a-blog cms の `InjectTemplate` でプラグイン内テンプレートを差し込みます。パンくず名も `admin-topicpath` で差し込みます。

## 設定例

```json
[
  {
    "product": "DF_InputAssist",
    "display_name": "DF入力支援",
    "json_url": "/media/releases/DF_InputAssist/latest.json",
    "github_releases_url": "https://github.com/datafarmjp/acms-df-input-assist/releases",
    "entry_blog_id": 1,
    "entry_category_id": 1,
    "entry_status": "draft",
    "entry_user_id": 1
  }
]
```

まずは `DF_InputAssist` だけを登録して、`/media/releases/DF_InputAssist/latest.json` が読めることを確認します。複数アプリを表示したい場合は、同じ形式で配列要素を追加します。
プロダクトごとの `entry_blog_id`、`entry_category_id`、`entry_status`、`entry_user_id` は任意です。未指定の場合は、管理画面下部の共通告知投稿設定を使います。投稿ユーザーIDが未指定の場合は、投稿先ブログまたは親ブログ階層のユーザーを使います。

## 告知エントリー作成

管理画面で投稿先ブログID、投稿先カテゴリーID、作成ステータス、投稿ユーザーIDを保存してから、`告知エントリーを作成` を押します。このボタンは外部POST連携後もデバッグ用として利用できます。

作成されるエントリーには、重複防止用に以下のカスタムフィールドを保存します。

- `df_release_product`
- `df_release_version`
- `df_release_tag`
- `df_release_github_release_url`
- `df_release_download_url`

同じブログ内に同じ `df_release_product` と `df_release_version` を持つエントリーがある場合、新規作成せず既存エントリーとして扱います。

## 外部POST連携

リリーススクリプトから告知エントリー作成を自動通知できます。

```bash
export DF_RELEASE_PUBLISH_ENABLED=1
export DF_RELEASE_PUBLISH_ENDPOINT="https://example.com/bid/1/"
export DF_RELEASE_PUBLISH_TOKEN="管理画面で保存したAPIトークン"
```

POST先では `ACMS_POST_ReleasePublisherWebhook`、`api_token`、`product`、`version` を受け取ります。POSTされた本文そのものは正本にせず、保存済み設定のJSON URLから再取得した `latest.json` の `product/version` が一致する場合だけ告知エントリーを作成します。

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
tools/release.sh 0.3.0
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

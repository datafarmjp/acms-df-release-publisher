# NEXT

DFリリース周辺の継続タスクメモです。

作業開始時にこのファイルを読み、作業中に追加・移動しながら使います。完了したタスクはこのファイルから削除し、リリースに含める変更履歴は `CHANGELOG.md` に残します。

## Now

- info.datafarm.jp 側で、同期済みの製品ページHTML断片を各製品トップへ `@include` して表示確認する。
  - 対象: DF入力支援、DFいいね、DFフォームガード、DFVRプレイヤー
  - 確認先: `_df-product-docs/{category_code}/_top_include.html`
  - 見出し、目次、本文量、リンクの違和感を確認する。

## Next

- `_shared` の共通ドキュメントをどうGit管理するか決める。
  - 追加済み: `/Applications/MAMP/htdocs/extension/plugins/_shared/DF_PUBLIC_PAGE_HOWTO.md`
  - 追加済み: `/Applications/MAMP/htdocs/extension/plugins/_shared/README.md`
  - 親 `/Applications/MAMP/htdocs` 側に大量差分があるため、未コミット。

- `DFリリース` 次版のリリース判断をする。
  - `docs/public-page.md` 正本化は実装・push済み。
  - 現状は `v0.3.3` 後の未リリース変更。
  - 必要なら `v0.3.4` としてリリースする。

- `public-page.md` 運用ルールを各製品READMEへ参照追加する。
  - a-blog cms拡張アプリ: `../_shared/DF_PUBLIC_PAGE_HOWTO.md`
  - DFVRプレイヤー: `_shared` が別場所なので、絶対パスまたは運用メモで参照する。

- リリーススクリプト連携を一覧化する。
  - JSON生成
  - SFTP JSON同期
  - Webhook告知エントリー作成
  - 製品ページHTML断片生成・SFTP同期
  - 各アプリごとに、どこまで対応済みかを整理する。

## Later

- 画像運用を決める。
  - 方針: `docs/images/*` を将来のスクリーンショット置き場にする。
  - 未実装: `docs/images` のSFTP同期。
  - 未決定: HTML本文内の画像URLルール。

- DFVRプレイヤーの告知自動化を次回リリースで実地確認する。
  - SFTP JSON同期
  - Webhook POST
  - 告知エントリー作成
  - 重複防止

- 既存の別件未コミット差分を整理する。
  - `DF_InputAssist`: DF Connect系の変更が残っている。
  - `DF_FormGuard`: DF Connect系の変更が残っている。
  - `DF_VRPlayer`: `scripts/sync-release-sftp.sh` の変更が残っている。

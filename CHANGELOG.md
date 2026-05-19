# Changelog

## Unreleased

<a id="v0-1-1"></a>
## 0.1.1 - 2026-05-19

- 管理画面とREADMEの設定例を、最初の実証対象である `DF_InputAssist` 単体に整理しました。
- `/media/releases/DF_InputAssist/latest.json` を読む運用手順と、SFTP同期用の環境変数例をREADMEに追加しました。

<a id="v0-1-0"></a>
## 0.1.0 - 2026-05-19

- 複数のDF製拡張アプリのリリースJSONを読み込み、変更履歴として表示する `DFReleasePublisher` モジュールを追加しました。
- 管理画面 `DFリリース設定` で、表示対象プロダクトのJSON URL、GitHub Releases URL、表示件数、将来用APIトークンを保存できるようにしました。
- 管理画面テンプレートを `themes/system` へコピーせず、`InjectTemplate` で `admin-main` に差し込む方式にしました。
- 管理画面パンくず用テンプレートを `admin-topicpath` に差し込み、パンくず上でも `DFリリース` と表示するようにしました。
- GET/POST互換ラッパーを `extension/acms/GET/` と `extension/acms/POST/` へ同期するようにしました。
- DF製拡張アプリ共通のリリースJSON形式に対応し、`github_release_url`、`changelog_url`、`download_url`、`changes` を表示に利用できるようにしました。
- 初回リリースで前回タグが存在しない場合も `tools/release.sh` が止まらないようにしました。
- MITライセンスを追加しました。

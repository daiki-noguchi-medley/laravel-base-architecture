# プルリクエストの書き方

PR を作成するときは `.github/PULL_REQUEST_TEMPLATE.md` が自動でテンプレートとして
本文に挿入されます。各セクションを以下の指針で埋めてください。

## 目的
**必須**

このコード変更で何を実現したいか (機能追加 / バグ修正 / リファクタなど)。
ビジネス的な背景や、関連する Issue / 仕様への参照があると尚良い。

## 実装の概要
**必須**

コード自体の概要。どのファイル / 層 / 仕組みを触ったか、設計判断のポイントなど。
箇条書きで構わない。

**マイグレーション (`src/database/migrations/`) を追加 / 変更した場合** は、
そのパスと **なぜ必要か** をここに書く。
CREATE TABLE 等の DDL 全文は貼らない (マイグレーションファイルを見れば分かる)。
書くのは新規 index / 制約など、レビュー上重要な変更点の要点だけ。

## チェック状況
**必須**

動作確認や手動テストがどこまで済んでいるか。

例:
- ローカル (`docker compose up`) でユーザー画面ログイン → ダッシュボード遷移まで確認
- `php artisan test` 緑
- API レスポンスを curl で確認 (HTTP 200 / 期待 JSON)

**マイグレーションを追加 / 変更した場合** は、DB の確認もここに含める
(独立した見出しを作らない。詳しくは [CLAUDE.md §9](../CLAUDE.md))。

- `migrate:fresh` 等での作成確認
- 影響する主要 SELECT の `EXPLAIN ANALYZE` 出力を **全文** 貼り、
  Index Scan が効き Seq Scan に落ちていないことを確認。
  対象の実行時クエリ (アプリが実際に発行する SQL) も併記する

## レビューしてほしいところ

実装内容が不安だったり、設計判断で迷ったところ。
特に注視してほしい箇所を `file:line` 等で指摘してくれるとレビューしやすい。

## 保留していること

正しい動作のために不足している部分で、**あえてこの PR には含めていない** 部分。
理由 (スコープが大きすぎる / 別チーム調整中 等) もセットで書く。

## 関連

関連する PR / Issue / 仕様 / 過去議論などへのリンク。

---

## このプロジェクト固有の注意

- コーディング規約は [`../CLAUDE.md`](../CLAUDE.md) を参照
- コミットメッセージは Conventional Commits (`type(scope): 要約`)、本文は日本語
- **PR / コミットに「Generated with ...」等の署名を付けない**、
  `Co-Authored-By:` トレーラーも付けない
- **実装やレビューの補助に使った個別ツールの名前を書かない** (CLAUDE.md §13)。
  レビューの出所に触れるときは「セルフレビュー」等の一般表現にする。
  リポジトリに入っているレビュー用サブエージェント (`.claude/agents/`) は
  共有物なので名前を書いてよい

## レビュー

`.claude/agents/` に確認専用のサブエージェントが 5 体ある。
変更したファイルの種別で担当が決まる (振り分けは `.claude/skills/review-pr/`)。

| 体 | 担当 |
|---|---|
| `laravel-code-reviewer` | `src/app/` / `src/Demo/` の PHP と `src/resources/views/` の Blade。規約 (CLAUDE.md) |
| `laravel-migration-pr-checker` | `src/database/migrations/` の差分がある PR の本文 (CLAUDE.md §9) |
| `frontend-reviewer` | `src/resources/js/` (React SPA / htmx + Alpine) |
| `github-actions-reviewer` | `.github/` と `.claude/` |
| `security-ops-reviewer` | 秘密情報・セッション・外部連携・ジョブの運用 |

手元では `/review-pr <PR URL>` で同じ振り分けを呼べる。
CI では `ANTHROPIC_API_KEY` を登録してあれば
`.github/workflows/claude-code-review.yml` が自動で走る
(未登録なら notice を出して緑で終わる)。

⚠ **`.claude/` を変更する PR では、その変更はまだ効かない。**
CI は実行前に `.claude/` を base (main) の内容へ戻す (PR HEAD が信頼できない入力のため)。
新しく足した体はマージ後から動くので、その PR では人が読む。

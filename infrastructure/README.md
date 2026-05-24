# infrastructure

このプロジェクトのインフラ (AWS / CI/CD) リソースを構築するためのシェルスクリプト集。

「`docs/infra/` で読んで → `infrastructure/` で実行する」分担:

| ディレクトリ | 役割 |
|---|---|
| [`docs/infra/`](../docs/infra/) | 概念 / 設計思想 / コマンドの中身解説 |
| [`infrastructure/`](.) (このフォルダ) | コピペ不要で直接実行できる shell スクリプト |

---

## 一覧

| ディレクトリ | 内容 |
|---|---|
| [`aws-oidc/`](./aws-oidc/) | GitHub Actions → AWS の OIDC 接続 (`AssumeRoleWithWebIdentity`) を構築するスクリプト |

将来追加候補:

- `aws-s3-deploy/` — Vite ビルド成果物の S3 sync 用 (バケット作成 + IAM 権限 + workflow)
- `aws-ecr/` — Docker image push 用 (リポジトリ作成 + IAM 権限)
- `aws-ecs/` — Fargate デプロイ用 (タスク定義 + サービス作成)

---

## 共通の使い方

すべてのスクリプトは以下を前提とする:

```bash
# AWS SSO ログイン済み (SAML 等)
export AWS_PROFILE=AdministratorAccess-<ACCOUNT_ID>

# gh CLI 認証済み
gh auth status
```

各サブディレクトリ配下の `README.md` に Step-by-Step の実行順序を記載。

---

## 設計方針

- **1 スクリプト = 1 ステップ**: 上から順に実行する想定。事故防止のため大きな処理は分割
- **`set -euo pipefail`** で安全に: エラー時は即停止
- **再実行可能 (idempotent)**: 既存リソースがあれば update / skip するように書く
- **環境変数で上書き可能**: ROLE 名 / リポジトリ名 / プロファイルを変えてもそのまま動く
- **後片付けスクリプトを必ず用意**: 検証で作ったリソースを安全に消せるように

---

## 関連

- [`docs/infra/`](../docs/infra/) — インフラ系ドキュメント (概念 / 設計)
- [`CLAUDE.md`](../CLAUDE.md) — Laravel コードの規約 (shell は対象外)

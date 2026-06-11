# infrastructure/aws-oidc

GitHub Actions → AWS の OIDC 接続 (`AssumeRoleWithWebIdentity`) を構築するためのシェルスクリプト集。

各スクリプトは **1 ステップずつ** 順番に実行する設計。
コマンドの中身と思想は [`../../docs/infra/oidc-aws-cli.md`](../../docs/infra/oidc-aws-cli.md) を参照。

---

## 構成

```
infrastructure/aws-oidc/
├── README.md                              ← このファイル
├── 01-create-oidc-provider.sh             OIDC Identity Provider 作成 (既存ならスキップ)
├── 02-create-iam-role.sh                  IAM Role 作成 + Trust policy 適用
├── 03-register-github-variable.sh         gh variable set AWS_IAM_ROLE_ARN
├── 04-run-connection-test.sh              gh workflow run + 結果確認
├── 99-cleanup.sh                          後片付け (Role / variable 削除)
└── policies/
    ├── trust-policy.json.tmpl             本番想定 (main + tags/v* + environment:production のみ assume 可)
    └── trust-policy-loose.json.tmpl       動作確認用 (任意 branch / PR / tag から assume 可)
```

---

## 前提

| ツール | 確認方法 |
|---|---|
| AWS CLI v2 | `aws --version` |
| GitHub CLI | `gh --version` + `gh auth status` |
| AWS SSO ログイン済み | `aws sts get-caller-identity --profile <YOUR_PROFILE>` で identity が返る |

プロジェクトのデフォルトプロファイル例:

```bash
export AWS_PROFILE=AdministratorAccess-<ACCOUNT_ID>
```

---

## 標準フロー (上から順に実行)

```bash
cd infrastructure/aws-oidc

# Step 1: OIDC Identity Provider 作成
./01-create-oidc-provider.sh

# Step 2: IAM Role 作成 (初回は loose 版 trust policy で動作確認)
TEMPLATE=trust-policy-loose.json.tmpl ./02-create-iam-role.sh

# Step 3: GitHub の repository variable に Role ARN を登録
./03-register-github-variable.sh

# Step 4: 接続テスト workflow を起動 → 結果まで自動で待ち受け
./04-run-connection-test.sh
```

接続テストが green になったら、**Step 2 を厳密版で再実行** して Trust policy を絞る:

```bash
# 本番想定の厳密版に切り替え (main + tags/v* + environment:production のみ)
./02-create-iam-role.sh   # TEMPLATE はデフォルトが厳密版

# もう一度 connection test (この PR のブランチで実行できなくなる、main のみで再確認)
./04-run-connection-test.sh
```

---

## 個別の挙動

### 01-create-oidc-provider.sh

- 同 URL の Provider が既にあれば作成スキップ (AWS は 1 アカウントに 1 つ)
- 失敗例: `EntityAlreadyExists` → 既に作られている。スクリプトはこれを事前検知してスキップする

### 02-create-iam-role.sh

- Role が既存なら **Trust policy だけ update** する (再実行可)
- `TEMPLATE` 環境変数でテンプレを切り替え:
  - `trust-policy.json.tmpl` (デフォルト): **本番想定**、sub claim を 3 つに限定
  - `trust-policy-loose.json.tmpl`: **動作確認用**、sub claim を `repo:OWNER/REPO:*` で許可
- ROLE_NAME / GITHUB_OWNER / GITHUB_REPO も環境変数で上書き可

### 03-register-github-variable.sh

- gh CLI で `repository variable` (= secrets ではない) として登録
- 既存値があれば上書きされる

### 04-run-connection-test.sh

- `aws-oidc-connection-test.yml` workflow を起動
- 最新の run ID を取得 → `gh run watch` で完了まで待機
- 成功時は `aws sts get-caller-identity` の出力を抜粋表示

### 99-cleanup.sh

- デフォルトで Role + インライン policy + GitHub variable を削除
- OIDC Identity Provider は **明示的に `PURGE_PROVIDER=1`** を渡したときだけ削除
  (アカウント全体で共有されるため安易に消さない)

```bash
# 検証後の片付け (Provider は残す)
./99-cleanup.sh

# Provider まで消す (このアカウントで他に GitHub OIDC を使う repo が無い場合のみ)
PURGE_PROVIDER=1 ./99-cleanup.sh
```

---

## カスタマイズ

| 変えたいもの | 環境変数 | デフォルト |
|---|---|---|
| Role 名 | `ROLE_NAME` | `GitHubActionsRole` |
| GitHub オーナー | `GITHUB_OWNER` | `daiki-noguchi-medley` |
| GitHub リポジトリ | `GITHUB_REPO` | `laravel-base-architecture` |
| GitHub variable 名 | `VARIABLE_NAME` | `AWS_IAM_ROLE_ARN` |
| Trust policy テンプレ | `TEMPLATE` | `trust-policy.json.tmpl` |
| workflow ファイル | `WORKFLOW_FILE` | `aws-oidc-connection-test.yml` |
| トリガする ref | `REF` | `main` |

例: 別 Role 名で構築する

```bash
ROLE_NAME=GitHubActionsDeployRole ./02-create-iam-role.sh
ROLE_NAME=GitHubActionsDeployRole VARIABLE_NAME=AWS_DEPLOY_ROLE_ARN ./03-register-github-variable.sh
```

---

## 関連

- 概念 / 全体図: [`../../docs/infra/oidc-aws.md`](../../docs/infra/oidc-aws.md)
- CLI チートシート (手動コマンド版): [`../../docs/infra/oidc-aws-cli.md`](../../docs/infra/oidc-aws-cli.md)
- 接続テスト workflow: [`../../.github/workflows/aws-oidc-connection-test.yml`](../../.github/workflows/aws-oidc-connection-test.yml)

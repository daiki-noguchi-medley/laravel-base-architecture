# infrastructure/cdk

AWS CDK (TypeScript) でこのプロジェクトの AWS リソースを管理する。

現状の構成:

| Stack | 内容 |
|---|---|
| `LaravelBaseArchitectureEcrStack` | ECR repository + 既存 `GitHubActionsRole` への push 権限付与 |

将来追加候補:

- `LaravelBaseArchitectureEcsStack` — Fargate サービス / Task Definition
- `LaravelBaseArchitectureVpcStack` — VPC / Subnet / Security Group
- `LaravelBaseArchitectureRdsStack` — RDS (PostgreSQL)
- `LaravelBaseArchitectureS3Stack` — Vite ビルド成果物配信 + CloudFront

---

## 前提

| ツール | インストール |
|---|---|
| Node.js **22 LTS** (推奨) | `nvm use 22` (動作確認済み)。Node 23 では一部互換性問題あり (後述のトラブルシュート参照) |
| AWS CDK CLI | `npm install -g aws-cdk` または `package.json` 経由で `npx cdk` |
| AWS CLI v2 + SSO ログイン | `aws sso login --profile <YOUR_PROFILE>` |
| IAM Role `GitHubActionsRole` | [`../aws-oidc/02-create-iam-role.sh`](../aws-oidc/02-create-iam-role.sh) で先に作っておく |

環境変数:

```bash
export AWS_PROFILE=<YOUR_PROFILE>           # 例: AdministratorAccess-<ACCOUNT_ID>
export CDK_DEFAULT_REGION=ap-northeast-1    # CDK が拾ってくれる
```

---

## セットアップ

```bash
cd infrastructure/cdk

# 1) 依存をインストール (.gitignore で node_modules は除外、自分でインストール)
npm install

# 2) このアカウント + region で CDK を bootstrap (region 単位で初回 1 回だけ)
npx cdk bootstrap

# 3) 差分確認 (deploy 前に毎回実行推奨)
npx cdk diff LaravelBaseArchitectureEcrStack

# 4) deploy
npx cdk deploy LaravelBaseArchitectureEcrStack
```

deploy 完了時、CloudFormation Outputs に以下が出る:

```
LaravelBaseArchitectureEcrStack.RepositoryUri  = <ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com/laravel-base-architecture
LaravelBaseArchitectureEcrStack.RepositoryName = laravel-base-architecture
LaravelBaseArchitectureEcrStack.RepositoryArn  = arn:aws:ecr:ap-northeast-1:<ACCOUNT_ID>:repository/laravel-base-architecture
```

`RepositoryUri` は GitHub Actions の ECR push workflow で使う。

---

## スタック設計 (`lib/ecr-stack.ts`)

### ECR Repository

| 設定 | 値 | 理由 |
|---|---|---|
| `imageScanOnPush` | `true` | push のたびに ECR Scan (CVE 検出) が走る |
| `imageTagMutability` | `IMMUTABLE` | 同じ tag への上書きを禁止。`v0.7.0` を 2 回 push できない = 履歴の信頼性 |
| `removalPolicy` | `RETAIN` | `cdk destroy` してもイメージが消えない (誤削除防止) |
| Lifecycle Rule 1 | untagged image を 7 日で削除 | layer 残骸 / 失敗ビルドの掃除 |
| Lifecycle Rule 2 | tagged image は最新 30 個まで保持 | 古いリリースが無限に溜まらない |

### IAM Permissions

`infrastructure/aws-oidc/` で作った既存 `GitHubActionsRole` に対し、`repository.grantPullPush(role)` でインライン Policy を追加する。

具体的に付与される Action (CDK が自動生成):

- `ecr:GetAuthorizationToken` (Resource: `*` 必須)
- `ecr:BatchCheckLayerAvailability`
- `ecr:GetDownloadUrlForLayer`
- `ecr:BatchGetImage`
- `ecr:InitiateLayerUpload`
- `ecr:UploadLayerPart`
- `ecr:CompleteLayerUpload`
- `ecr:PutImage`

これらは **この ECR repository ARN にスコープされる** (`GetAuthorizationToken` を除く)。

---

## よく使うコマンド

```bash
# 全スタック一覧
npx cdk list

# CloudFormation テンプレートを synth (デプロイせず、生成だけ確認)
npx cdk synth LaravelBaseArchitectureEcrStack

# 差分確認 (現在のクラウド状態とコードの違い)
npx cdk diff LaravelBaseArchitectureEcrStack

# デプロイ (確認プロンプトあり、--require-approval never で省略可能)
npx cdk deploy LaravelBaseArchitectureEcrStack

# デプロイ取り消し (注意: ECR は removalPolicy=RETAIN なので残る)
npx cdk destroy LaravelBaseArchitectureEcrStack
```

---

## トラブルシュート

### `Need to perform AWS calls for account XXX, but no credentials configured`

→ `aws sso login` してから `AWS_PROFILE` を export しているか確認。

### `This stack uses assets, so the toolkit stack must be deployed to the environment` (cdk bootstrap が必要)

→ `npx cdk bootstrap` を 1 回実行。

### `User: ... is not authorized to perform: cloudformation:CreateStack`

→ SSO Role が CDK 操作に必要な権限を持っていない。AdministratorAccess なら問題なし、最小権限化したい場合は別 Role を使う。

### `Repository cannot be deleted because it is not empty` (cdk destroy で)

→ removalPolicy=RETAIN なので CDK は repository を削除しない。完全削除したい場合は AWS Console / CLI で先にイメージを削除してから `aws ecr delete-repository --force`。

### `ERR_MODULE_NOT_FOUND: Cannot find module '.../bin/app.ts'` (cdk synth で)

→ **`package.json` に `"bin": { "cdk": "bin/app.ts" }` を書かない**。これがあると aws-cdk CLI が `cdk.json` の `app` を無視して `bin/app.ts` を直接 node に渡してしまい、Node 22+ の ESM resolver が走って失敗する。本プロジェクトでは bin フィールドを意図的に外している。

### Node 23 で動かない (`internal/modules/esm/loader` 経由のエラー)

→ Node 23 のネイティブ TS ローダーが ts-node より先に動く場合がある。`nvm use 22` で **Node 22 LTS** に切り替え推奨。本プロジェクトの構築・検証もすべて Node 22 で実施。

---

## 関連

- [../aws-oidc/](../aws-oidc/) — IAM Role と OIDC Provider 構築 (CDK の前にこれを実行)
- [../../docs/infra/ecr-deploy.md](../../docs/infra/ecr-deploy.md) — ECR への push 全体フロー
- [../../docs/infra/oidc-aws.md](../../docs/infra/oidc-aws.md) — OIDC 接続の概念
- AWS CDK 公式: <https://docs.aws.amazon.com/cdk/v2/guide/>

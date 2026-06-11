# ECR への Docker image push

GitHub Actions から ECR に **指定したブランチ or tag の Docker image を push** するフロー。

| 役割 | 場所 |
|---|---|
| **インフラ** (ECR repo + IAM 権限) | [`infrastructure/cdk/`](../../infrastructure/cdk/) (AWS CDK / TypeScript) |
| **CI / push 実行** | [`.github/workflows/aws-ecr-push.yml`](../../.github/workflows/aws-ecr-push.yml) |
| **認証** | OIDC で `GitHubActionsRole` を assume ([`oidc-aws.md`](./oidc-aws.md) 参照) |

---

## 全体図

```
[手動] gh workflow run aws-ecr-push.yml --ref v0.9.0
            │
            ▼
   ┌── GitHub Actions runner ───────────────────────┐
   │  1. checkout (Use workflow from で選んだ ref) │
   │  2. configure-aws-credentials@v4 (OIDC)        │
   │       ↓ id-token をGitHub に発行              │
   │       ↓ STS AssumeRoleWithWebIdentity          │
   │  3. amazon-ecr-login@v2 (短期 credentials)     │
   │  4. docker buildx build                        │
   │  5. docker push -> <account>.dkr.ecr.<region>  │
   │                   .amazonaws.com/<repo>:<tag>  │
   └────────────────────────────────────────────────┘
            │
            ▼
   ┌── AWS ECR (CDK 管理) ──────────────────────────┐
   │  imageScanOnPush: true                         │
   │  imageTagMutability: IMMUTABLE                 │
   │  Lifecycle:                                    │
   │   - untagged → 7 日で削除                      │
   │   - tagged   → 最新 30 個のみ保持              │
   └────────────────────────────────────────────────┘
```

---

## 初回セットアップ

### Step 0. OIDC 接続が済んでいることを確認

未済なら [`oidc-aws-cli.md`](./oidc-aws-cli.md) を見て構築。
`GitHubActionsRole` が存在し、`gh workflow run aws-oidc-connection-test.yml` が green になっていれば OK。

### Step 1. CDK で ECR + IAM 権限を deploy

```bash
cd infrastructure/cdk

# 初回のみ npm install
npm install

# このアカウント + region で CDK を bootstrap (region 単位で 1 回だけ)
npx cdk bootstrap

# 差分確認 → deploy
npx cdk diff LaravelBaseArchitectureEcrStack
npx cdk deploy LaravelBaseArchitectureEcrStack
```

deploy 完了時、Outputs に表示される `RepositoryUri` をメモ:

```
LaravelBaseArchitectureEcrStack.RepositoryUri =
  <ACCOUNT_ID>.dkr.ecr.ap-northeast-1.amazonaws.com/laravel-base-architecture
```

CDK Stack の中身は [`infrastructure/cdk/README.md`](../../infrastructure/cdk/README.md) と
[`infrastructure/cdk/lib/ecr-stack.ts`](../../infrastructure/cdk/lib/ecr-stack.ts) を参照。

### Step 2. (推奨) Trust policy の sub claim を絞る

ECR push を **main + v\* tag のみ** に限定したい場合、`GitHubActionsRole` の Trust policy を厳密版に更新:

```bash
cd infrastructure/aws-oidc
./02-create-iam-role.sh   # TEMPLATE デフォルトが trust-policy.json.tmpl (厳密版)
```

ただし任意 ref から ECR push したい場合は **loose 版のまま** でも構わない。
セキュリティと利便性のトレードオフ。

---

## push の実行

### CLI から

```bash
# tag 名を --ref に指定 → ECR の image tag もそのまま (v0.9.0)
gh workflow run aws-ecr-push.yml --ref v0.9.0

# ブランチを --ref に指定 → ECR の image tag は <branch>-<short-sha> で自動生成
gh workflow run aws-ecr-push.yml --ref main
# → ECR には main-a1b2c3d で push される
```

> ℹ️ AWS region は workflow YAML の `env.AWS_REGION` で `ap-northeast-1` に固定。
> 変えたいときは `.github/workflows/aws-ecr-push.yml` を直接編集する (滅多に変えないので input より明示固定が運用上ラク)。
> ECR tag のカスタム命名も同様、必要なら workflow を fork して `inputs.ecr_tag` を足す方針。

### GitHub Web UI から

1. <https://github.com/daiki-noguchi-medley/laravel-base-architecture/actions/workflows/aws-ecr-push.yml> を開く
2. 「Run workflow」を押す
3. **「Use workflow from」**で対象ブランチ or tag を選択 (`Tags` タブで `v*` も選べる)
4. 「Run workflow」を確定 (他の入力欄はない)

### 結果確認

```bash
# 直近の run を watch
RUN_ID=$(gh run list --workflow aws-ecr-push.yml --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$RUN_ID"

# 完了サマリ ($GITHUB_STEP_SUMMARY に出力されているもの)
gh run view "$RUN_ID"

# ECR に push されたか確認
aws ecr describe-images \
  --repository-name laravel-base-architecture \
  --query 'imageDetails[*].[imageTags,imagePushedAt]' \
  --output table
```

---

## ECR tag の命名規則

| ref の種類 | ECR tag | 上書き可? | 用途 |
|---|---|---|---|
| tag (`v0.7.0` 等) | そのまま (`v0.7.0`) | 不可 (IMMUTABLE) | **リリース版**: 1 回だけ push 可、後から差し替え不可 |
| ブランチ (`main` / `feature/xxx`) | `<branch>-<short-sha>` (例: `main-a1b2c3d`) | 不可 (commit が違えば SHA が変わる) | **検証版**: commit ごとにユニーク tag |

> ⚠️ ECR が `IMMUTABLE` 設定なので、**同じ tag に 2 回 push しようとすると失敗する**。
> 例: `main-a1b2c3d` で push 済みの状態で同じ commit から再 push すると `ImageAlreadyExistsException`。

---

## デバッグ / トラブルシュート

### `Could not load credentials from any providers`

→ `permissions: id-token: write` が workflow に書いてあるか確認。`aws-ecr-push.yml` の冒頭にあり。

### `Not authorized to perform sts:AssumeRoleWithWebIdentity`

→ Trust policy の sub claim 設定問題。loose 版なら通る、strict 版なら指定 ref が main / v* / environment のいずれかか確認。

### `AccessDeniedException: User: ... is not authorized to perform: ecr:GetAuthorizationToken`

→ CDK で `EcrStack` がまだ deploy されていない / Role への permission が付与されていない。
   `npx cdk diff` で差分があれば `npx cdk deploy` で再適用。

### `ImageAlreadyExistsException` (同じ tag を再 push)

→ ECR が IMMUTABLE 設定。新しい commit を push してから workflow を起動するか (`<branch>-<short-sha>` は SHA が変わるので別 tag になる)、リリースなら `v0.7.1` のように bump する。
   開発中に頻繁に上書きしたいなら `infrastructure/cdk/lib/ecr-stack.ts` の `immutableTags: false` に変更して `cdk deploy` で MUTABLE に。

### `docker build` がコケる (composer install / npm install で network エラー)

→ GitHub Actions の cache を消してやり直す: workflow を開いて run logs から `cache-from` 関連を見る。
   `cache-from: type=gha` を一時的に外して試す。

### Vite ビルドや Composer install が image に入っていない

→ 今の `docker/php/Dockerfile` は **開発用** で、Laravel のコード (`src/`) や Composer
   インストール結果はホストとの bind mount を前提にしている。本番運用するなら multi-stage の
   `docker/php/Dockerfile.prod` を別途作る必要あり (今回スコープ外)。

---

## お片付け

### ECR repository のイメージだけ消す

```bash
aws ecr list-images --repository-name laravel-base-architecture --query 'imageIds[*]' --output json \
  > /tmp/ecr-images.json
aws ecr batch-delete-image --repository-name laravel-base-architecture --image-ids file:///tmp/ecr-images.json
```

### Stack ごと消す (= ECR repo + IAM Policy)

```bash
# ECR を空にしてから (上のコマンド) destroy
cd infrastructure/cdk
npx cdk destroy LaravelBaseArchitectureEcrStack
```

> ⚠️ Stack の `removalPolicy: RETAIN` なので、`cdk destroy` しても **ECR repository 本体は残る**。
> 完全に削除したい場合は AWS Console / CLI で `aws ecr delete-repository --force --repository-name laravel-base-architecture` を別途実行。

---

## 関連

- [`infrastructure/cdk/`](../../infrastructure/cdk/) — CDK プロジェクト (TypeScript)
- [`infrastructure/aws-oidc/`](../../infrastructure/aws-oidc/) — OIDC Provider + IAM Role shell
- [`oidc-aws.md`](./oidc-aws.md) — OIDC の概念 / 設計
- [`oidc-aws-cli.md`](./oidc-aws-cli.md) — OIDC 構築の CLI 手順
- [`.github/workflows/aws-ecr-push.yml`](../../.github/workflows/aws-ecr-push.yml) — push 用 workflow
- AWS ECR 公式: <https://docs.aws.amazon.com/AmazonECR/latest/userguide/>
- aws-actions/amazon-ecr-login: <https://github.com/aws-actions/amazon-ecr-login>

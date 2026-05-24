# GitHub Actions → AWS の OIDC 接続セットアップ

GitHub Actions から AWS にアクセスするのに、**長期の `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` を Secrets に置かない方式**。
OIDC で短期トークンを発行し、AWS STS で `AssumeRoleWithWebIdentity` して一時 credentials を取得する。

参考: <https://zenn.dev/kou_pg_0131/articles/gh-actions-oidc-aws>

---

## なぜ OIDC を使うのか

長期 access key を GitHub Secrets に置く運用 (= 旧来) のリスク:

- key が漏れたら **取り消しまで使われ放題** (Mini Shai-Hulud 攻撃 / Laravel-Lang 攻撃などで頻発)
- 定期的な rotation が必要
- repo を fork されると CI の挙動を通じて漏れる可能性

OIDC の利点:

- **AWS に長期 credentials を置かない** (= 漏れる「もの」がない)
- AssumeRoleWithWebIdentity で取得する credentials は **デフォルト 1 時間で失効**
- Trust policy の `sub` claim で **特定の repo / branch からの assume だけを許可** できる
- secrets rotation 不要

---

## 全体図

```
┌─ GitHub Actions runner ────────────────────────┐
│  workflow に permissions: id-token: write      │
│       ↓                                        │
│  aws-actions/configure-aws-credentials@v4      │
│       ↓ OIDC token (JWT) を発行                │
│       ↓ iss = token.actions.githubusercontent.com
│       ↓ sub = repo:NOGUD626/...:ref:refs/heads/main
│       ↓ aud = sts.amazonaws.com                │
└────────────────┬───────────────────────────────┘
                 ↓ HTTPS POST
┌─ AWS STS ─────────────────────────────────────┐
│  AssumeRoleWithWebIdentity                    │
│   ├─ Identity Provider (本ドキュメントで作る)  │
│   │   token.actions.githubusercontent.com     │
│   └─ IAM Role (本ドキュメントで作る)            │
│       Trust policy の Condition.sub にマッチ?  │
│   ↓ 一時 credentials 発行 (1h)                 │
└────────────────┬───────────────────────────────┘
                 ↓
┌─ Actions runner ───────────────────────────────┐
│  AWS_ACCESS_KEY_ID / SECRET / SESSION_TOKEN   │
│  が env に展開され、aws CLI などから利用可能      │
└────────────────────────────────────────────────┘
```

---

## セットアップ手順

AWS Console での手作業 4 ステップ + GitHub 側 1 ステップ。
AWS は SAML ログイン経由でも、IAM 操作権限があれば実行可能。

### Step 1. IAM Identity Provider を作成

AWS Console → **IAM** → **Identity providers** → **Add provider**

| 項目 | 値 |
|---|---|
| Provider type | `OpenID Connect` |
| Provider URL | `https://token.actions.githubusercontent.com` |
| Audience | `sts.amazonaws.com` |

「Add provider」をクリック。

> ⚠️ AWS アカウント内に 1 つ既に存在すると重複追加できない (= 既に他 repo で OIDC を使っていれば共用)。
> 既存があれば Step 2 へスキップ。

### Step 2. IAM Role を作成

AWS Console → **IAM** → **Roles** → **Create role**

1. **Trusted entity type**: `Web identity`
2. **Identity provider**: `token.actions.githubusercontent.com` (Step 1 で作ったもの)
3. **Audience**: `sts.amazonaws.com`
4. **GitHub organization**: `NOGUD626`
5. **GitHub repository**: `laravel-base-architecture`
6. **GitHub branch** (任意): 入力すると `:ref:refs/heads/<branch>` で絞られる。初回は空欄で OK

「Next」→ 権限ポリシーは **何も付けない** (疎通確認だけならポリシー不要、`sts:GetCallerIdentity` は role assume 自体に含まれる) → 「Next」

**Role name**: `GitHubActionsRole` (好きな名前で可)

「Create role」をクリック。

作成後、Role の **ARN** をコピー (`arn:aws:iam::123456789012:role/GitHubActionsRole` の形式)。

### Step 3. Trust policy を必要なら絞る

デフォルトの Trust policy は `repo:NOGUD626/laravel-base-architecture:*` (リポジトリ全体から assume 可) になっている。
ブランチや tag を絞りたい場合は IAM Role > **Trust relationships** > **Edit trust policy** で次のように編集:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": [
            "repo:NOGUD626/laravel-base-architecture:ref:refs/heads/main",
            "repo:NOGUD626/laravel-base-architecture:ref:refs/tags/v*",
            "repo:NOGUD626/laravel-base-architecture:environment:production"
          ]
        }
      }
    }
  ]
}
```

`sub` の書き方:

| 何から assume したいか | sub の値 |
|---|---|
| 任意のブランチ / PR / tag (緩い) | `repo:NOGUD626/laravel-base-architecture:*` |
| main ブランチのみ | `repo:NOGUD626/laravel-base-architecture:ref:refs/heads/main` |
| v* タグのみ | `repo:NOGUD626/laravel-base-architecture:ref:refs/tags/v*` |
| Environments (production など) | `repo:NOGUD626/laravel-base-architecture:environment:production` |
| Pull Request (危険、避ける) | `repo:NOGUD626/laravel-base-architecture:pull_request` |

> ⚠️ **`:pull_request` を sub に含めない**。外部 fork PR からも assume できてしまう。
> 本番運用では `main` か `tags/v*` か `environment:production` に絞ること。

### Step 4. GitHub に Repository variable を登録

GitHub の Web UI で:

1. <https://github.com/NOGUD626/laravel-base-architecture/settings/variables/actions> を開く
2. **「New repository variable」** をクリック
3. **Name**: `AWS_IAM_ROLE_ARN`
4. **Value**: Step 2 でコピーした role の ARN (例: `arn:aws:iam::123456789012:role/GitHubActionsRole`)
5. 「Add variable」

> Role ARN は **機密情報ではない** (sub claim マッチがないと assume できないため)。
> ただし secrets と違って log に出るので、AWS アカウント ID を見せたくない場合だけ secrets に置く運用も可。

### Step 5. 疎通確認 workflow を実行

GitHub Actions の **「AWS OIDC 接続テスト」** workflow を手動起動:

1. <https://github.com/NOGUD626/laravel-base-architecture/actions/workflows/aws-oidc-connection-test.yml> を開く
2. 右上の **「Run workflow」** → ブランチ `main` を選択 → 「Run workflow」
3. 数十秒で完了する。最後のログに以下のような出力が出れば成功:

   ```
   AWS への OIDC 接続が成功しました。Assume した identity:
   {
       "UserId": "AROAEXAMPLE123:GitHubActions",
       "Account": "123456789012",
       "Arn": "arn:aws:sts::123456789012:assumed-role/GitHubActionsRole/GitHubActions"
   }
   ```

---

## トラブルシュート

### `Could not load credentials from any providers`

→ `permissions: id-token: write` を workflow に書いていない。workflow YAML 先頭の `permissions:` ブロックを確認。

### `Not authorized to perform sts:AssumeRoleWithWebIdentity`

→ Trust policy の `sub` claim が一致していない。AWS Console で IAM Role > Trust relationships を開き、
   `Condition.StringLike.token.actions.githubusercontent.com:sub` の値と、実際の Actions が送ってくる
   sub (`repo:NOGUD626/laravel-base-architecture:ref:refs/heads/<branch>`) が一致するか確認。

実行中の sub を見るには、workflow に以下を追加してログを取る:

```yaml
- name: 現在の OIDC token の sub を確認
  run: |
    IDTOKEN=$(curl -sLS -H "Authorization: bearer $ACTIONS_ID_TOKEN_REQUEST_TOKEN" \
      "$ACTIONS_ID_TOKEN_REQUEST_URL&audience=sts.amazonaws.com" | jq -r '.value')
    echo "$IDTOKEN" | cut -d. -f2 | base64 -d 2>/dev/null | jq .
```

### `Region is missing`

→ workflow の `aws-region` 指定が無効。`ap-northeast-1` 等の region を明示する。

---

## 権限を必要に応じて追加する

疎通確認だけなら IAM Role に追加の Permissions policy は不要だが、実際に AWS リソースを操作したくなったら
**最小権限** を inline policy or 管理ポリシーで追加する。

例: S3 バケットへの read-write のみ:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::my-app-assets",
        "arn:aws:s3:::my-app-assets/*"
      ]
    }
  ]
}
```

例: ECR への push:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "ecr:BatchCheckLayerAvailability",
        "ecr:PutImage",
        "ecr:InitiateLayerUpload",
        "ecr:UploadLayerPart",
        "ecr:CompleteLayerUpload"
      ],
      "Resource": "arn:aws:ecr:ap-northeast-1:<ACCOUNT_ID>:repository/my-app"
    }
  ]
}
```

`Resource` は `*` を避けて **具体的なリソース ARN に絞る** こと。
`Action` も `s3:*` のようなワイルドカードを避けて、必要なものだけ。

---

## 関連ドキュメント

- [docs/infra/github-actions.md](github-actions.md) — workflow 全般の解説
- [.github/workflows/aws-oidc-connection-test.yml](../../.github/workflows/aws-oidc-connection-test.yml) — 疎通確認 workflow 実体
- AWS 公式: <https://docs.aws.amazon.com/IAM/latest/UserGuide/id_roles_create_for-idp_oidc.html>
- GitHub 公式: <https://docs.github.com/en/actions/deployment/security-hardening-your-deployments/about-security-hardening-with-openid-connect>

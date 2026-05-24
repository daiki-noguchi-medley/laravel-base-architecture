# GitHub Actions → AWS の OIDC 接続セットアップ (AWS CLI 版)

[oidc-aws.md](./oidc-aws.md) は **AWS Console (Web UI)** での手順を中心に書いている。
本ドキュメントは **AWS CLI で実行できる手順** だけを並べた **コピペで動く版**。
概念 / 全体図 / Trust policy の sub claim 設計の意図は [oidc-aws.md](./oidc-aws.md) を参照。

---

## 前提

- AWS CLI v2 がローカルにインストール済み (`aws --version`)
- IAM 操作権限を持つ profile で認証済み (本プロジェクトでは SAML SSO 経由 `aws sso login` / `saml2aws login` 等)
- `gh` CLI も用意してあれば、Step 4 / Step 5 もコマンドで完結

事前確認:

```bash
# 認証状態
aws sts get-caller-identity

# 出力例:
# {
#   "UserId": "AROAEXAMPLE123:noguchi@example.com",
#   "Account": "123456789012",
#   "Arn": "arn:aws:sts::123456789012:assumed-role/AdminRole/noguchi@example.com"
# }
```

`UserId` / `Account` / `Arn` が返ってくれば OK。返ってこなければ SAML ログインからやり直す。

---

## Step 1. OIDC Identity Provider 作成

AWS アカウント単位で **1 つだけ** 存在する必要がある。既に他用途で作っていれば共用するので Step 2 へ。

```bash
# 既存チェック (1 アカウントに 1 つだけ)
aws iam list-open-id-connect-providers

# 何も無ければ作成 (近年は thumbprint 省略可、AWS 側で自動取得)
aws iam create-open-id-connect-provider \
  --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com
```

出力例:

```json
{
    "OpenIDConnectProviderArn": "arn:aws:iam::123456789012:oidc-provider/token.actions.githubusercontent.com"
}
```

ARN は Step 2 で使うので環境変数に入れておく:

```bash
OIDC_PROVIDER_ARN=$(aws iam list-open-id-connect-providers \
  --query "OpenIDConnectProviderList[?ends_with(Arn,'token.actions.githubusercontent.com')].Arn" \
  --output text)
echo "$OIDC_PROVIDER_ARN"
```

---

## Step 2. IAM Role 作成

### 2-1. Trust policy を一時ファイルに書き出す

```bash
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

cat > /tmp/gha-trust-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": "repo:NOGUD626/laravel-base-architecture:*"
        }
      }
    }
  ]
}
EOF
```

> ⚠️ `sub` の制限がゆるい (`:*`) と、リポジトリの **任意のブランチ / PR / tag** から assume できる。
> 本番運用に近づけるなら次の例 (main / tag / environment 限定) で書き直す:
>
> ```bash
> # main + v* tag + environment:production だけ許可
> cat > /tmp/gha-trust-policy.json <<EOF
> {
>   "Version": "2012-10-17",
>   "Statement": [
>     {
>       "Effect": "Allow",
>       "Principal": {
>         "Federated": "arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"
>       },
>       "Action": "sts:AssumeRoleWithWebIdentity",
>       "Condition": {
>         "StringEquals": {
>           "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
>         },
>         "StringLike": {
>           "token.actions.githubusercontent.com:sub": [
>             "repo:NOGUD626/laravel-base-architecture:ref:refs/heads/main",
>             "repo:NOGUD626/laravel-base-architecture:ref:refs/tags/v*",
>             "repo:NOGUD626/laravel-base-architecture:environment:production"
>           ]
>         }
>       }
>     }
>   ]
> }
> EOF
> ```
>
> ※ `:pull_request` を含めない (外部 fork PR からも assume できてしまう)

### 2-2. Role を作成

```bash
aws iam create-role \
  --role-name GitHubActionsRole \
  --assume-role-policy-document file:///tmp/gha-trust-policy.json \
  --description "OIDC role assumed by GitHub Actions in NOGUD626/laravel-base-architecture"
```

出力例:

```json
{
    "Role": {
        "Path": "/",
        "RoleName": "GitHubActionsRole",
        "RoleId": "AROAEXAMPLE123",
        "Arn": "arn:aws:iam::123456789012:role/GitHubActionsRole",
        ...
    }
}
```

Role ARN を環境変数に拾っておく:

```bash
ROLE_ARN=$(aws iam get-role --role-name GitHubActionsRole --query Role.Arn --output text)
echo "$ROLE_ARN"
# → arn:aws:iam::123456789012:role/GitHubActionsRole
```

### 2-3. Trust policy を後から変更したい場合

```bash
aws iam update-assume-role-policy \
  --role-name GitHubActionsRole \
  --policy-document file:///tmp/gha-trust-policy.json
```

---

## Step 3. (オプション) Permissions policy のアタッチ

疎通確認だけ (`sts:GetCallerIdentity`) なら追加の Permissions policy は不要。
何か AWS リソースを操作するなら、**最小権限の inline policy** を付ける。

### 例: S3 への read-write

```bash
BUCKET_NAME=my-app-assets

cat > /tmp/gha-s3-policy.json <<EOF
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
        "arn:aws:s3:::${BUCKET_NAME}",
        "arn:aws:s3:::${BUCKET_NAME}/*"
      ]
    }
  ]
}
EOF

aws iam put-role-policy \
  --role-name GitHubActionsRole \
  --policy-name S3AssetsReadWrite \
  --policy-document file:///tmp/gha-s3-policy.json
```

### 例: ECR への push

```bash
REPO_NAME=my-app
REGION=ap-northeast-1
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

cat > /tmp/gha-ecr-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "ecr:GetAuthorizationToken",
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
      "Resource": "arn:aws:ecr:${REGION}:${ACCOUNT_ID}:repository/${REPO_NAME}"
    }
  ]
}
EOF

aws iam put-role-policy \
  --role-name GitHubActionsRole \
  --policy-name ECRPush \
  --policy-document file:///tmp/gha-ecr-policy.json
```

> 鉄則: `Resource: "*"` と `s3:*` 等のワイルドカードを避け、具体的な ARN と Action だけに絞る
> (`ecr:GetAuthorizationToken` だけは仕様で `*` 必須)。

---

## Step 4. GitHub の repository variable に Role ARN を登録

GitHub Web UI でも可能だが、`gh` CLI で 1 行:

```bash
gh variable set AWS_IAM_ROLE_ARN \
  --body "$ROLE_ARN" \
  --repo NOGUD626/laravel-base-architecture
```

確認:

```bash
gh variable list --repo NOGUD626/laravel-base-architecture
# NAME                VALUE                                                       UPDATED
# AWS_IAM_ROLE_ARN    arn:aws:iam::123456789012:role/GitHubActionsRole            less than a minute ago
```

---

## Step 5. 動作確認 (CLI で workflow を起動)

`gh` CLI で workflow を手動起動 + ログ確認:

```bash
# workflow を main ブランチで実行
gh workflow run aws-oidc-connection-test.yml \
  --repo NOGUD626/laravel-base-architecture \
  --ref main

# 直後の run を watch (完了まで張り付く)
sleep 3
RUN_ID=$(gh run list --workflow aws-oidc-connection-test.yml --limit 1 --json databaseId --jq '.[0].databaseId' \
  --repo NOGUD626/laravel-base-architecture)
gh run watch "$RUN_ID" --repo NOGUD626/laravel-base-architecture

# ログを引っ張る
gh run view "$RUN_ID" --log --repo NOGUD626/laravel-base-architecture \
  | grep -A3 "get-caller-identity"
```

期待される出力:

```
AWS への OIDC 接続が成功しました。Assume した identity:
{
    "UserId": "AROAEXAMPLE123:GitHubActions",
    "Account": "123456789012",
    "Arn": "arn:aws:sts::123456789012:assumed-role/GitHubActionsRole/GitHubActions"
}
```

`Arn` の末尾 (`GitHubActionsRole/GitHubActions`) が Step 2 で作った role 名と一致していれば成功。

---

## お片付け (CLI でリソース削除)

検証用に作ったものを消したい場合:

```bash
# 1) Role からインライン policy を全部外す
for P in $(aws iam list-role-policies --role-name GitHubActionsRole --query 'PolicyNames[]' --output text); do
  aws iam delete-role-policy --role-name GitHubActionsRole --policy-name "$P"
done

# 2) Role を削除
aws iam delete-role --role-name GitHubActionsRole

# 3) (このアカウントで他に GitHub OIDC を使っていないなら) Identity Provider も削除
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
aws iam delete-open-id-connect-provider \
  --open-id-connect-provider-arn "arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"

# 4) GitHub の variable も削除
gh variable delete AWS_IAM_ROLE_ARN --repo NOGUD626/laravel-base-architecture
```

> ⚠️ Identity Provider は **アカウント全体で共用される** ので、他の repo / 用途で使っているなら削除しない。

---

## トラブルシュート (CLI 特有のもの)

詳しいエラーごとの対処は [oidc-aws.md](./oidc-aws.md) のトラブルシュート章参照。CLI 特有のものだけ:

### `An error occurred (EntityAlreadyExists) when calling the CreateOpenIDConnectProvider operation`

→ Step 1 の identity provider は既に存在する。`aws iam list-open-id-connect-providers` で確認して既存を使う。

### `An error occurred (EntityAlreadyExists) when calling the CreateRole operation`

→ 同名の role が既存。`aws iam get-role --role-name GitHubActionsRole` で中身を確認、必要なら update か別名で再作成。

### `An error occurred (MalformedPolicyDocument) when calling the CreateRole operation`

→ trust policy JSON の構文 (`,` 抜け / `${ACCOUNT_ID}` 展開漏れ等)。`cat /tmp/gha-trust-policy.json | jq .` で構文チェック。

### `gh variable set` で `HTTP 403: Resource not accessible by integration`

→ `gh auth status` で repo scope があるか確認。`gh auth refresh -s repo,workflow` で取り直す。

---

## 関連ドキュメント

- [oidc-aws.md](./oidc-aws.md) — Web UI 版 + 概念図 + sub claim 設計の意図
- [.github/workflows/aws-oidc-connection-test.yml](../../.github/workflows/aws-oidc-connection-test.yml) — 疎通確認 workflow 実体
- [github-actions.md](./github-actions.md) — workflow 全般
- AWS 公式: <https://docs.aws.amazon.com/IAM/latest/UserGuide/id_roles_create_for-idp_oidc.html>
- GitHub 公式: <https://docs.github.com/en/actions/deployment/security-hardening-your-deployments/about-security-hardening-with-openid-connect>

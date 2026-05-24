# GitHub Actions → AWS の OIDC 接続セットアップ (CLI 手順書)

[oidc-aws.md](./oidc-aws.md) は **AWS Console (Web UI)** 中心の手順 + 設計の解説。
本ドキュメントは **CLI を 1 コマンドずつ実行する** ステップ・バイ・ステップ手順書。
実際に AWS account `<ACCOUNT_ID>` に対して構築した時の **実出力** を載せている。

> 💡 ここに書いてあるコマンドは [`infrastructure/aws-oidc/*.sh`](../../infrastructure/aws-oidc/)
> に **シェルスクリプトとしても同梱** されている。手動で打ち直したくないときはそちらを使う。

---

## 前提の確認

実行前に次を済ませておくこと:

```bash
# 1) AWS CLI v2
aws --version
# 出力例: aws-cli/2.x.x ...

# 2) gh CLI
gh --version
gh auth status

# 3) AWS SSO ログイン (本プロジェクトでは SAML)
aws sso login   # or saml2aws login
export AWS_PROFILE=AdministratorAccess-<ACCOUNT_ID>  # 自分のプロファイル名に置き換え
```

認証確認:

```bash
aws sts get-caller-identity
```

実出力 (この repo で実際に得たもの):

```json
{
    "UserId": "AROAEXAMPLEUSERIDXX:<your-email@example.com>",
    "Account": "<ACCOUNT_ID>",
    "Arn": "arn:aws:sts::<ACCOUNT_ID>:assumed-role/AWSReservedSSO_AdministratorAccess_xxxxxxxx/<your-email@example.com>"
}
```

`Account` / `Arn` が返ってくれば OK。出てこなければ SAML ログインからやり直す。

---

## Step 1. OIDC Identity Provider を作成

### 1-1. 既存チェック

AWS アカウントに 1 つだけ存在できるので、まず確認。

```bash
aws iam list-open-id-connect-providers
```

実出力 (この AWS account ではすでに存在していたので **作成スキップ**):

```json
{
    "OpenIDConnectProviderList": [
        {
            "Arn": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com"
        }
    ]
}
```

### 1-2. (存在しないときだけ) 作成

```bash
aws iam create-open-id-connect-provider \
  --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com
```

期待出力:

```json
{
    "OpenIDConnectProviderArn": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com"
}
```

### ✅ 次の Step に進む前の確認

`list-open-id-connect-providers` に `token.actions.githubusercontent.com` で終わる ARN が 1 つあること。

> 💡 これに対応するシェルスクリプトは [`infrastructure/aws-oidc/01-create-oidc-provider.sh`](../../infrastructure/aws-oidc/01-create-oidc-provider.sh)。

---

## Step 2. IAM Role を作成

### 2-1. アカウント ID を取得

```bash
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
echo "$ACCOUNT_ID"
```

実出力:

```
<ACCOUNT_ID>
```

### 2-2. Trust policy JSON を一時ファイルに書き出す (動作確認用: 緩い版)

最初は **動作確認に必要最小限の緩い設定** (`sub` を `repo:OWNER/REPO:*` で許可) で行う。
動作確認が終わったら 2-5 で厳密版に差し替える。

```bash
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

JSON が壊れていないか確認:

```bash
cat /tmp/gha-trust-policy.json | python3 -m json.tool > /dev/null && echo "JSON OK"
```

### 2-3. Role を作成

```bash
aws iam create-role \
  --role-name GitHubActionsRole \
  --assume-role-policy-document file:///tmp/gha-trust-policy.json \
  --description "OIDC role assumed by GitHub Actions in NOGUD626/laravel-base-architecture"
```

実出力 (抜粋):

```json
{
    "Role": {
        "Path": "/",
        "RoleName": "GitHubActionsRole",
        "RoleId": "AROAEXAMPLEROLEIDXX",
        "Arn": "arn:aws:iam::<ACCOUNT_ID>:role/GitHubActionsRole",
        "CreateDate": "2026-05-24T14:35:07+00:00",
        ...
    }
}
```

### 2-4. Role ARN を環境変数に保存 (後続で使う)

```bash
ROLE_ARN=$(aws iam get-role --role-name GitHubActionsRole --query Role.Arn --output text)
echo "$ROLE_ARN"
```

実出力:

```
arn:aws:iam::<ACCOUNT_ID>:role/GitHubActionsRole
```

### 2-5. (動作確認後) Trust policy を厳密版に差し替え

接続テスト (Step 4) が green になったら、`sub` を **main + tags/v* + environment:production の 3 つだけに絞る**:

```bash
cat > /tmp/gha-trust-policy-strict.json <<EOF
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
EOF

aws iam update-assume-role-policy \
  --role-name GitHubActionsRole \
  --policy-document file:///tmp/gha-trust-policy-strict.json
```

### ✅ 次の Step に進む前の確認

`aws iam get-role --role-name GitHubActionsRole --query Role.Arn --output text` で
`arn:aws:iam::<ACCOUNT_ID>:role/GitHubActionsRole` が返る。

> 💡 これに対応するシェルスクリプトは [`infrastructure/aws-oidc/02-create-iam-role.sh`](../../infrastructure/aws-oidc/02-create-iam-role.sh)。
> `TEMPLATE=trust-policy-loose.json.tmpl` で緩い版、デフォルト (`trust-policy.json.tmpl`) で厳密版。

---

## Step 3. GitHub の repository variable に Role ARN を登録

```bash
gh variable set AWS_IAM_ROLE_ARN \
  --body "$ROLE_ARN" \
  --repo NOGUD626/laravel-base-architecture
```

登録確認:

```bash
gh variable list --repo NOGUD626/laravel-base-architecture
```

実出力:

```
NAME              VALUE                                              UPDATED
AWS_IAM_ROLE_ARN  arn:aws:iam::<ACCOUNT_ID>:role/GitHubActionsRole   less than a minute ago
```

### ✅ 次の Step に進む前の確認

- `AWS_IAM_ROLE_ARN` が一覧に出ている
- 値が Step 2-4 で取得した `ROLE_ARN` と一致している

> 💡 これに対応するシェルスクリプトは [`infrastructure/aws-oidc/03-register-github-variable.sh`](../../infrastructure/aws-oidc/03-register-github-variable.sh)。

---

## Step 4. 接続テスト workflow を実行

### 4-1. workflow を起動

```bash
gh workflow run aws-oidc-connection-test.yml \
  --repo NOGUD626/laravel-base-architecture \
  --ref main
```

### 4-2. 直近の Run ID を取得

```bash
sleep 5
RUN_ID=$(gh run list --workflow aws-oidc-connection-test.yml --limit 1 \
  --json databaseId --jq '.[0].databaseId' \
  --repo NOGUD626/laravel-base-architecture)
echo "$RUN_ID"
```

実出力:

```
26364063761
```

### 4-3. 完了まで watch

```bash
gh run watch "$RUN_ID" --repo NOGUD626/laravel-base-architecture --exit-status
```

完了するとプロンプトに戻る。失敗の場合は `gh run view "$RUN_ID" --log ...` でログ確認。

### 4-4. 接続結果のログを確認

```bash
gh run view "$RUN_ID" --log --repo NOGUD626/laravel-base-architecture \
  | grep -A6 "get-caller-identity で接続確認" \
  | head -15
```

実出力 (この repo で実際に得たもの):

```
AWS への OIDC 接続が成功しました。Assume した identity:
{
    "UserId": "AROAEXAMPLEROLEIDXX:GitHubActions",
    "Account": "<ACCOUNT_ID>",
    "Arn": "arn:aws:sts::<ACCOUNT_ID>:assumed-role/GitHubActionsRole/GitHubActions"
}
```

### ✅ 完了の判定

- `Account` が自分の AWS アカウント ID と一致
- `Arn` の末尾が `assumed-role/GitHubActionsRole/GitHubActions` になっている
- `UserId` のプレフィックス (`AROAxxxxx`) が Step 2-3 で作った Role の RoleId と一致 (例: `AROAEXAMPLEROLEIDXX`)

> 💡 これに対応するシェルスクリプトは [`infrastructure/aws-oidc/04-run-connection-test.sh`](../../infrastructure/aws-oidc/04-run-connection-test.sh)。

---

## Step 5. (オプション) Permissions policy を付与

疎通確認だけなら不要。実際に AWS リソース操作 (S3 / ECR 等) をしたくなったら追加する。

### 例: S3 read-write を付与

```bash
BUCKET_NAME=my-app-assets   # 自分のバケット名

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

確認:

```bash
aws iam list-role-policies --role-name GitHubActionsRole
aws iam get-role-policy --role-name GitHubActionsRole --policy-name S3AssetsReadWrite
```

### 鉄則

- `Resource: "*"` を避けて **具体的なリソース ARN** に絞る
- `Action: "s3:*"` のようなワイルドカードを避けて **必要な Action だけ** に絞る
- `ecr:GetAuthorizationToken` だけは仕様上 `Resource: "*"` 必須 (例外)

ECR / Secrets Manager 等の例は [oidc-aws.md](./oidc-aws.md) の末尾参照。

---

## お片付け (検証後にリソースを消す)

### 普通の片付け (Provider は残す)

```bash
# 1) Role からインライン policy を全削除
for P in $(aws iam list-role-policies --role-name GitHubActionsRole --query 'PolicyNames[]' --output text); do
  aws iam delete-role-policy --role-name GitHubActionsRole --policy-name "$P"
done

# 2) Role を削除
aws iam delete-role --role-name GitHubActionsRole

# 3) GitHub variable を削除
gh variable delete AWS_IAM_ROLE_ARN --repo NOGUD626/laravel-base-architecture
```

### Provider まで消す (このアカウントで他に GitHub OIDC を使わない場合)

```bash
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
aws iam delete-open-id-connect-provider \
  --open-id-connect-provider-arn "arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"
```

> ⚠️ OIDC Identity Provider は **アカウント全体で共用される**。
> 他の repo / 用途で使っている場合は削除しない。

> 💡 まとめて実行するシェルスクリプトは [`infrastructure/aws-oidc/99-cleanup.sh`](../../infrastructure/aws-oidc/99-cleanup.sh) (`PURGE_PROVIDER=1` で Provider も削除)。

---

## トラブルシュート

### `Could not load credentials from any providers` (workflow ログ)

→ workflow YAML に `permissions: id-token: write` が無い。`.github/workflows/aws-oidc-connection-test.yml` 参照。

### `Not authorized to perform sts:AssumeRoleWithWebIdentity` (workflow ログ)

→ Trust policy の `sub` 制限が、実際に Actions から送られてくる sub と一致していない。
   現在の sub を見るには workflow を一時的に以下に書き換えて確認:

```yaml
- name: 現在の OIDC token の sub を確認
  run: |
    IDTOKEN=$(curl -sLS -H "Authorization: bearer $ACTIONS_ID_TOKEN_REQUEST_TOKEN" \
      "$ACTIONS_ID_TOKEN_REQUEST_URL&audience=sts.amazonaws.com" | jq -r '.value')
    echo "$IDTOKEN" | cut -d. -f2 | base64 -d 2>/dev/null | jq .
```

### `An error occurred (EntityAlreadyExists) when calling the CreateOpenIDConnectProvider operation`

→ Step 1 の Provider が既に存在する。`list-open-id-connect-providers` で確認して既存を使う (本ドキュメントの 1-1 の確認をしていなかった想定)。

### `An error occurred (EntityAlreadyExists) when calling the CreateRole operation`

→ 同名 Role が既存。中身を確認して必要なら `update-assume-role-policy` で trust policy だけ書き換える。

### `gh variable set` で `HTTP 403: Resource not accessible by integration`

→ `gh auth status` で repo scope が無い。`gh auth refresh -s repo,workflow` で取り直す。

### `MalformedPolicyDocument`

→ Trust policy JSON が壊れている (カンマ抜け、変数展開漏れ等)。
   `cat /tmp/gha-trust-policy.json | python3 -m json.tool` で構文チェック。

---

## 関連

- [oidc-aws.md](./oidc-aws.md) — 概念 / 全体図 / sub claim 設計
- [`infrastructure/aws-oidc/`](../../infrastructure/aws-oidc/) — 上記コマンドをシェルスクリプト化したもの
- [`.github/workflows/aws-oidc-connection-test.yml`](../../.github/workflows/aws-oidc-connection-test.yml) — 接続テスト workflow
- AWS 公式: <https://docs.aws.amazon.com/IAM/latest/UserGuide/id_roles_create_for-idp_oidc.html>
- GitHub 公式: <https://docs.github.com/en/actions/deployment/security-hardening-your-deployments/about-security-hardening-with-openid-connect>

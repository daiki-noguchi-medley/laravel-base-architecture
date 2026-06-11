#!/usr/bin/env bash
# 99-cleanup.sh
#
# OIDC 関連リソースの後片付け。
# デフォルトでは Role + インライン policy + GitHub variable のみ削除。
# OIDC Identity Provider はアカウント全体で共有されるので、明示的に
# PURGE_PROVIDER=1 を渡したときだけ削除する。
#
# 環境変数:
#   AWS_PROFILE       必須
#   ROLE_NAME         デフォルト: GitHubActionsRole
#   GITHUB_OWNER      デフォルト: daiki-noguchi-medley
#   GITHUB_REPO      デフォルト: laravel-base-architecture
#   VARIABLE_NAME     デフォルト: AWS_IAM_ROLE_ARN
#   PURGE_PROVIDER    デフォルト: 0 (1 で OIDC Identity Provider も削除)
#
# 使い方:
#   ./99-cleanup.sh                  # Role + variable のみ削除
#   PURGE_PROVIDER=1 ./99-cleanup.sh  # Provider まで削除

set -euo pipefail

ROLE_NAME="${ROLE_NAME:-GitHubActionsRole}"
GITHUB_OWNER="${GITHUB_OWNER:-daiki-noguchi-medley}"
GITHUB_REPO="${GITHUB_REPO:-laravel-base-architecture}"
VARIABLE_NAME="${VARIABLE_NAME:-AWS_IAM_ROLE_ARN}"
PURGE_PROVIDER="${PURGE_PROVIDER:-0}"
REPO="$GITHUB_OWNER/$GITHUB_REPO"

echo "ROLE_NAME      = $ROLE_NAME"
echo "REPO           = $REPO"
echo "VARIABLE_NAME  = $VARIABLE_NAME"
echo "PURGE_PROVIDER = $PURGE_PROVIDER"

echo ""
echo "==> Role $ROLE_NAME のインライン policy を全削除..."
if aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
  for POLICY in $(aws iam list-role-policies --role-name "$ROLE_NAME" --query 'PolicyNames[]' --output text); do
    echo "    delete-role-policy: $POLICY"
    aws iam delete-role-policy --role-name "$ROLE_NAME" --policy-name "$POLICY"
  done

  echo ""
  echo "==> Role $ROLE_NAME を削除..."
  aws iam delete-role --role-name "$ROLE_NAME"
else
  echo "    Role は既に存在しません。スキップ。"
fi

echo ""
echo "==> GitHub variable $VARIABLE_NAME を削除..."
gh variable delete "$VARIABLE_NAME" --repo "$REPO" 2>/dev/null && echo "    OK" \
  || echo "    (variable は既に存在しないかも。スキップ)"

if [[ "$PURGE_PROVIDER" == "1" ]]; then
  ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
  PROVIDER_ARN="arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"

  echo ""
  echo "==> OIDC Identity Provider $PROVIDER_ARN を削除..."
  echo "    ⚠️  このアカウントで他に GitHub OIDC を使う repo が無いことを確認してください。"
  aws iam delete-open-id-connect-provider --open-id-connect-provider-arn "$PROVIDER_ARN" \
    && echo "    削除完了" \
    || echo "    (Provider は既に存在しないかも。スキップ)"
fi

echo ""
echo "==> 後片付け完了。"

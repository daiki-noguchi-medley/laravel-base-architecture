#!/usr/bin/env bash
# 02-create-iam-role.sh
#
# GitHub Actions が AssumeRoleWithWebIdentity で引き受ける IAM Role を作成する。
# Trust policy は policies/trust-policy.json.tmpl (厳密版、本番想定) を使用する。
# 動作確認だけ済ませたい段階では TEMPLATE=trust-policy-loose.json.tmpl を指定。
#
# 既存の Role があれば Trust policy だけ update する (再実行可)。
#
# 環境変数:
#   AWS_PROFILE      必須
#   ROLE_NAME        デフォルト: GitHubActionsRole
#   GITHUB_OWNER     デフォルト: daiki-noguchi-medley
#   GITHUB_REPO     デフォルト: laravel-base-architecture
#   TEMPLATE         デフォルト: trust-policy.json.tmpl (厳密版)
#                    → 初回動作確認なら TEMPLATE=trust-policy-loose.json.tmpl

set -euo pipefail

ROLE_NAME="${ROLE_NAME:-GitHubActionsRole}"
GITHUB_OWNER="${GITHUB_OWNER:-daiki-noguchi-medley}"
GITHUB_REPO="${GITHUB_REPO:-laravel-base-architecture}"
TEMPLATE="${TEMPLATE:-trust-policy.json.tmpl}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
POLICY_TEMPLATE="${SCRIPT_DIR}/policies/${TEMPLATE}"
POLICY_FILE="/tmp/gha-trust-policy.json"

if [[ ! -f "$POLICY_TEMPLATE" ]]; then
  echo "ERROR: policy template not found: $POLICY_TEMPLATE" >&2
  exit 1
fi

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
echo "ACCOUNT_ID = $ACCOUNT_ID"
echo "ROLE_NAME  = $ROLE_NAME"
echo "GITHUB     = $GITHUB_OWNER/$GITHUB_REPO"
echo "TEMPLATE   = $TEMPLATE"

echo ""
echo "==> Trust policy を生成: $POLICY_FILE"
sed \
  -e "s|<ACCOUNT_ID>|${ACCOUNT_ID}|g" \
  -e "s|<GITHUB_OWNER>|${GITHUB_OWNER}|g" \
  -e "s|<GITHUB_REPO>|${GITHUB_REPO}|g" \
  "$POLICY_TEMPLATE" > "$POLICY_FILE"

cat "$POLICY_FILE"

echo ""
if aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
  echo "==> Role $ROLE_NAME は既存。Trust policy を更新します..."
  aws iam update-assume-role-policy \
    --role-name "$ROLE_NAME" \
    --policy-document "file://$POLICY_FILE"
else
  echo "==> Role $ROLE_NAME を新規作成中..."
  aws iam create-role \
    --role-name "$ROLE_NAME" \
    --assume-role-policy-document "file://$POLICY_FILE" \
    --description "OIDC role assumed by GitHub Actions in $GITHUB_OWNER/$GITHUB_REPO"
fi

echo ""
echo "==> 完了。Role ARN:"
aws iam get-role --role-name "$ROLE_NAME" --query Role.Arn --output text

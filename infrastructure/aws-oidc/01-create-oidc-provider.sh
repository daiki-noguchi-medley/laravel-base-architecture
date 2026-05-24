#!/usr/bin/env bash
# 01-create-oidc-provider.sh
#
# GitHub Actions 用の OIDC Identity Provider を AWS IAM に作成する。
# AWS アカウント単位で 1 つだけ存在できるので、既存があればスキップ。
#
# 前提:
#   - aws CLI v2 がインストール済み
#   - 環境変数 AWS_PROFILE が設定済み (本プロジェクトでは SAML SSO 経由のプロファイル名)
#     例: export AWS_PROFILE=AdministratorAccess-<ACCOUNT_ID>
#
# 使い方:
#   ./01-create-oidc-provider.sh

set -euo pipefail

OIDC_URL="https://token.actions.githubusercontent.com"
CLIENT_ID="sts.amazonaws.com"

echo "==> AWS 認証状態を確認..."
aws sts get-caller-identity

echo ""
echo "==> 既存の OIDC Identity Provider をチェック..."
EXISTING=$(aws iam list-open-id-connect-providers \
  --query "OpenIDConnectProviderList[?ends_with(Arn, 'token.actions.githubusercontent.com')].Arn" \
  --output text)

if [[ -n "$EXISTING" ]]; then
  echo "    既に存在: $EXISTING"
  echo "    → 新規作成をスキップします (このアカウント内で共用)。"
  exit 0
fi

echo ""
echo "==> OIDC Identity Provider を作成中..."
aws iam create-open-id-connect-provider \
  --url "$OIDC_URL" \
  --client-id-list "$CLIENT_ID"

echo ""
echo "==> 作成完了。"

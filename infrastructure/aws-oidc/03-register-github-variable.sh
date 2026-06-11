#!/usr/bin/env bash
# 03-register-github-variable.sh
#
# AWS で作成した IAM Role の ARN を、GitHub repository variable (AWS_IAM_ROLE_ARN) に登録する。
# secrets ではなく variables に置く (Role ARN は機密ではないため。log にも出るが問題なし)。
#
# 前提:
#   - 02-create-iam-role.sh が完了していること
#   - gh CLI で認証済み (`gh auth status`)
#
# 環境変数:
#   AWS_PROFILE      必須
#   ROLE_NAME        デフォルト: GitHubActionsRole
#   GITHUB_OWNER     デフォルト: daiki-noguchi-medley
#   GITHUB_REPO      デフォルト: laravel-base-architecture
#   VARIABLE_NAME    デフォルト: AWS_IAM_ROLE_ARN

set -euo pipefail

ROLE_NAME="${ROLE_NAME:-GitHubActionsRole}"
GITHUB_OWNER="${GITHUB_OWNER:-daiki-noguchi-medley}"
GITHUB_REPO="${GITHUB_REPO:-laravel-base-architecture}"
VARIABLE_NAME="${VARIABLE_NAME:-AWS_IAM_ROLE_ARN}"
REPO="$GITHUB_OWNER/$GITHUB_REPO"

ROLE_ARN=$(aws iam get-role --role-name "$ROLE_NAME" --query Role.Arn --output text)
echo "ROLE_ARN      = $ROLE_ARN"
echo "TARGET REPO   = $REPO"
echo "VARIABLE NAME = $VARIABLE_NAME"

echo ""
echo "==> gh variable set..."
gh variable set "$VARIABLE_NAME" --body "$ROLE_ARN" --repo "$REPO"

echo ""
echo "==> 登録確認:"
gh variable list --repo "$REPO"

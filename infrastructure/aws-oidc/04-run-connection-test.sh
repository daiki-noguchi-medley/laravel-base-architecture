#!/usr/bin/env bash
# 04-run-connection-test.sh
#
# GitHub Actions の AWS OIDC 接続テスト workflow を起動し、結果を確認する。
# `gh workflow run` → `gh run watch` → `gh run view --log` を一通り実行。
#
# 環境変数:
#   GITHUB_OWNER     デフォルト: NOGUD626
#   GITHUB_REPO     デフォルト: laravel-base-architecture
#   WORKFLOW_FILE    デフォルト: aws-oidc-connection-test.yml
#   REF              デフォルト: main

set -euo pipefail

GITHUB_OWNER="${GITHUB_OWNER:-NOGUD626}"
GITHUB_REPO="${GITHUB_REPO:-laravel-base-architecture}"
WORKFLOW_FILE="${WORKFLOW_FILE:-aws-oidc-connection-test.yml}"
REF="${REF:-main}"
REPO="$GITHUB_OWNER/$GITHUB_REPO"

echo "REPO          = $REPO"
echo "WORKFLOW_FILE = $WORKFLOW_FILE"
echo "REF           = $REF"

echo ""
echo "==> Workflow を起動..."
gh workflow run "$WORKFLOW_FILE" --repo "$REPO" --ref "$REF"

echo ""
echo "==> Run ID を取得 (workflow が見える状態になるまで数秒待機)..."
sleep 5
RUN_ID=$(gh run list --workflow "$WORKFLOW_FILE" --limit 1 \
  --json databaseId --jq '.[0].databaseId' --repo "$REPO")
echo "RUN_ID = $RUN_ID"

echo ""
echo "==> 完了まで watch..."
gh run watch "$RUN_ID" --repo "$REPO" --exit-status

echo ""
echo "==> get-caller-identity の出力:"
gh run view "$RUN_ID" --log --repo "$REPO" \
  | grep -A6 "get-caller-identity で接続確認" \
  | head -15

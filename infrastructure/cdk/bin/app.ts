#!/usr/bin/env node
import * as cdk from 'aws-cdk-lib';
import { EcrStack } from '../lib/ecr-stack';

const app = new cdk.App();

const env = {
    // CDK_DEFAULT_ACCOUNT / CDK_DEFAULT_REGION は `aws sso login` 済みの
    // 環境変数 / ~/.aws/config から自動取得される
    account: process.env.CDK_DEFAULT_ACCOUNT,
    region: process.env.CDK_DEFAULT_REGION ?? 'ap-northeast-1',
};

// ECR repository + IAM Permissions stack
new EcrStack(app, 'LaravelBaseArchitectureEcrStack', {
    env,
    description: 'ECR repository + IAM permissions for GitHub Actions push',
    repositoryName: 'laravel-base-architecture',
    // infrastructure/aws-oidc/02-create-iam-role.sh で先に作っておいた Role の名前
    githubActionsRoleName: 'GitHubActionsRole',
});

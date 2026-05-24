import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import * as iam from 'aws-cdk-lib/aws-iam';

export interface EcrStackProps extends cdk.StackProps {
    /** 作成する ECR repository 名。例: 'laravel-base-architecture' */
    readonly repositoryName: string;

    /**
     * Push 権限を付与する既存 IAM Role の名前。
     * infrastructure/aws-oidc/02-create-iam-role.sh で先に作っておく前提。
     */
    readonly githubActionsRoleName: string;

    /**
     * tag を上書き不可 (IMMUTABLE) にするか。
     * - true (デフォルト): 同じ tag に対する 2 回目の push は失敗 → 履歴が確実に残る
     * - false: 同じ tag を上書き可能 (本番運用では避ける)
     */
    readonly immutableTags?: boolean;

    /**
     * 保持する tagged image の最大数。これを超えると古いものから自動削除。
     * デフォルト: 30
     */
    readonly maxTaggedImages?: number;

    /**
     * untagged image を削除するまでの日数。デフォルト: 7 日。
     */
    readonly untaggedImageRetentionDays?: number;
}

/**
 * ECR repository と、GitHub Actions Role への push 権限を 1 つのスタックでまとめて管理する。
 *
 * 含むリソース:
 *   - AWS::ECR::Repository (LifecycleRule 付き、scan-on-push 有効)
 *   - 既存 IAM Role へのインライン Policy (grantPullPush によって追加される)
 *
 * 含まないもの:
 *   - IAM Role 本体 (= GitHubActionsRole): OIDC Trust policy 関連は
 *     infrastructure/aws-oidc/ の shell が管理する責務。
 *   - VPC / ECS Service: 別 stack で追加していく想定。
 */
export class EcrStack extends cdk.Stack {
    public readonly repository: ecr.Repository;

    constructor(scope: Construct, id: string, props: EcrStackProps) {
        super(scope, id, props);

        this.repository = new ecr.Repository(this, 'AppRepository', {
            repositoryName: props.repositoryName,
            imageScanOnPush: true,
            imageTagMutability: (props.immutableTags ?? true)
                ? ecr.TagMutability.IMMUTABLE
                : ecr.TagMutability.MUTABLE,
            // 暗号化は AWS マネージド (AES-256) で十分なので明示しない (= default)
            lifecycleRules: [
                {
                    rulePriority: 1,
                    description: '古い untagged image を一定期間で削除',
                    tagStatus: ecr.TagStatus.UNTAGGED,
                    maxImageAge: cdk.Duration.days(props.untaggedImageRetentionDays ?? 7),
                },
                {
                    rulePriority: 2,
                    description: 'tagged image は直近 N 個のみ保持',
                    tagStatus: ecr.TagStatus.TAGGED,
                    tagPatternList: ['*'],
                    maxImageCount: props.maxTaggedImages ?? 30,
                },
            ],
            // ECR repository を CDK destroy しても誤って消さない (image が入ってるため)
            removalPolicy: cdk.RemovalPolicy.RETAIN,
        });

        // 既存の GitHub Actions Role に push 権限を付与する。
        // grantPullPush は ecr:BatchCheckLayerAvailability / PutImage / InitiateLayerUpload /
        // UploadLayerPart / CompleteLayerUpload / GetDownloadUrlForLayer / BatchGetImage と、
        // ecr:GetAuthorizationToken (Resource: "*") を含む inline policy を Role に追加する。
        const githubActionsRole = iam.Role.fromRoleName(
            this,
            'GitHubActionsRole',
            props.githubActionsRoleName,
        );
        this.repository.grantPullPush(githubActionsRole);

        // CloudFormation Outputs (cdk deploy 後のターミナル出力 + Stack の Outputs タブで参照)
        new cdk.CfnOutput(this, 'RepositoryUri', {
            value: this.repository.repositoryUri,
            description: 'ECR repository URI (docker push 先)',
            exportName: `${id}-RepositoryUri`,
        });
        new cdk.CfnOutput(this, 'RepositoryName', {
            value: this.repository.repositoryName,
            description: 'ECR repository name',
            exportName: `${id}-RepositoryName`,
        });
        new cdk.CfnOutput(this, 'RepositoryArn', {
            value: this.repository.repositoryArn,
            description: 'ECR repository ARN',
            exportName: `${id}-RepositoryArn`,
        });
    }
}

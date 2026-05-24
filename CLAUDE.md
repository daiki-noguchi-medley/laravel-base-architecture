# Laravel プロジェクト コーディング規約

このプロジェクト (Laravel 13 / PHP 8.4) に固有のルール。
グローバル規約 (`~/.claude/CLAUDE.md`) を Laravel / PHP のコンテキストで具体化する。

---

## 1. 定数 / Enum

ドメインに意味のある値は **必ず** 型付きの `enum` か `const` で宣言する。
コード中に数値・文字列リテラルを直接書かない (マジックナンバー禁止)。

### 命名

- case 名 / 定数名は **大文字スネークケース** (`SAMPLE_VALUE`)
- backed enum の値はドメインで意味のある小文字スネークケース

### 例

```php
// app/Enums/UserStatus.php
enum UserStatus: string
{
    case ACTIVE  = 'active';
    case PENDING = 'pending';
    case DELETED = 'deleted';
}

// app/Constants/Limit.php
final class Limit
{
    public const MAX_RETRY_COUNT   = 5;
    public const DEFAULT_PAGE_SIZE = 20;
    public const DIAL_TIMEOUT_SEC  = 3;
}
```

### 悪い例

```php
// NG: マジックナンバー / 文字列リテラル直書き
if ($user->status === 'active') { ... }
sleep(3);
$retries = 5;
```

### 良い例

```php
if ($user->status === UserStatus::ACTIVE) { ... }
sleep(Limit::DIAL_TIMEOUT_SEC);
$retries = Limit::MAX_RETRY_COUNT;
```

---

## 2. DB テーブル名 (単数形)

- テーブル名は **単数形** (`user`, `tag`, `post`, `log_entry`)
- 複数形 (`users`, `tags`) は使わない
- 理由: 1 行 = 1 エンティティ。`user.id = tag.user_id` の方が読みやすい

### Laravel Eloquent の対応

Eloquent は標準でテーブル名を複数形に自動変換するので、各モデルで明示的に上書きする。

```php
class User extends Model
{
    protected $table = 'user';   // 単数形を明示
}
```

マイグレーションも単数形で書く:

```php
Schema::create('user', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

> Laravel デフォルト生成の `users` / `cache` / `jobs` テーブル等は標準ひな型なので
> そのままでよい。新規に作るテーブルは単数形で統一する。

---

## 3. 変数 / 関数の命名

### 件数を明示 (`~List` サフィックス)

- 単数件: `$user`, `$tag`
- 複数件: `~List` サフィックス → `$userList`, `$tagList`

複数形 (`$users`) は「ユーザーの何か」「複数のユーザー」のどちらか紛らわしいため避ける。

```php
// NG
public function getUsers(): Collection { ... }
$users = $service->getUsers();

// OK
public function getUserList(): Collection { ... }
$userList = $service->getUserList();
```

スコープが狭いループ変数等は単数形を許容:

```php
foreach ($userList as $user) {
    echo $user->name;
}
```

### 略語・短縮形は使わない

タイプ数は IDE が補完するので問題にならない。**意味の伝達速度が重要**。

| NG | OK |
|---|---|
| `$cnt` | `$count` / `$userCount` |
| `$lst` | `$userList` |
| `$usr` | `$user` |
| `$res` | `$payment` / `$savedOrder` など意味のある名前 |
| `$tmp` / `$temp` | 一時変数でも何の値かを表す名前 (`$normalizedName` 等) |
| `$data` | `$userRow` / `$requestPayload` 等 |
| `calc()` | `calculateTotalAmount()` |
| `proc()` | `processRefund()` |
| `getUsrCnt()` | `getUserCount()` |
| `chk()` | `validate...()` / `is...()` / `has...()` |
| `$userRepo` / `$adminRepo` | `$userRepository` / `$adminRepository` (Repository は省略しない) |
| `$paymentApi` | `$paymentApiRepository` (型名と一致させる) |

慣用的に許容される略語 (略すのが業界標準のもの):

- `$id` (identifier)
- `$url` / `$uri` / `$http`
- `$ms` / `$sec` (時間単位を明示するとき)
- ループ変数の `$i`, `$k`, `$v` (極小スコープのみ)

### 命名から処理が予測できること (ドキュメント参照を前提にしない)

メソッド名・変数名 **だけ** 見て、何をするかが伝わるようにする。
**「ドメイン知識やドキュメントを参照すれば分かる」は却下**。
読み手がドキュメントを開かなくても命名だけで意図が読めるべき。

NG (何をするかが不明):

```php
interface UserService
{
    public function process(int $id): mixed;    // 何を処理する?
    public function handle(array $data): void;  // 何を扱う?
    public function update(User $user): User;   // 何を更新?
    public function execute(): void;            // ???
    public function doSomething(): void;        // ???
}
```

OK (動詞 + 名詞で意図が伝わる):

```php
interface UserService
{
    public function activateUser(int $userId): User;
    public function importUserListFromCsv(string $csvPath): int;
    public function updateUserEmail(User $user, string $newEmail): User;
    public function notifyUserByEmail(int $userId, string $subject, string $body): void;
}
```

bool を返す関数は `is~` / `has~` / `can~` で始める:

```php
public function isActive(): bool;
public function hasUnpaidInvoice(): bool;
public function canCancelOrder(): bool;
```

変数も同じ。スコープが長くなるほど命名は厳しく:

```php
// NG
$x = $this->userRepository->findById($id);
foreach ($list as $item) { ... }

// OK
$activeUser = $this->userRepository->findById($userId);
foreach ($pendingOrderList as $pendingOrder) { ... }
```

### アクセサ命名のスタイル (Row / Auth / Entity)

クラスの種別ごとに使い分ける。**強制統一はしない**、用途で選ぶ。

#### Row (DTO) — `public readonly` プロパティ基本

データバッグ的な単純表現。シンプルさ優先。

```php
final readonly class UserRow
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

// 使用
$row->id;
$row->name;
```

#### Auth / Entity / ドメインオブジェクト — Laravel スタイル (動詞なし) 基本

`get` プレフィックスは付けない。

```php
$auth->id();
$auth->name();
$auth->email();
```

bool は `is~ / has~ / can~`:

```php
$user->isActive();
$user->hasUnpaidInvoice();
$order->canBeCancelled();
```

#### `getXxx()` を使ってよいケース (例外的)

メソッド名だけだと **「何を返すか」が曖昧** / **加工 / 派生 / 計算** が入る場合は `getXxx()` で明示してよい:

```php
$user->getMaskedEmail();       // ***@example.com に整形
$user->getDefaultProfile();    // 未設定なら default を返すという意図を明示
$order->getTotalIncludeTax();  // 計算結果である旨を明示
```

判断基準:
- メソッド名 (動詞なし) で **意味が一意に伝わる** → Laravel スタイル
- メソッド名だけだと **「取得 / 設定 / 計算」のどれか分かりにくい** → `getXxx()` で明示

統一目的のためだけに全部 `getXxx()` にしない (冗長)。逆に、無理して `id()` 形式に詰めない (曖昧)。

#### Laravel / interface 規定はそのまま従う

例: `Authenticatable::getAuthIdentifier()` / `getAuthPassword()` は Laravel 規定なので、
`get` を付けたまま実装する (ここは自由度なし)。

#### 同一クラス内での混在は OK

`id()` (Laravel スタイル) と `getMaskedEmail()` (getter) が同居して問題ない。
**用途で使い分ける** のが規約。

---

## 4. レイヤー構造とドメインパッケージ

事業ドメイン単位でパッケージを切り、その中を **ビジネスロジック単位** でフォルダ分割する。
Service / Repository はそのビジネスロジックフォルダの中に置く。

### データクラスの種別 — 総称は「Model」

このプロジェクトでは以下を **総称して「Model」** と呼ぶ:

| 種別 | 役割 | 配置 | 例 |
|---|---|---|---|
| **Row** (DTO) | DB の 1 行を表す。Repository ↔ Service の境界で使う | `Demo/Repository/<Logic>/` | `UserRow` / `AdminRow` |
| **ViewModel** (DTO) | Blade に渡す | `app/Http/ViewModel/<Domain>/<SubDomain>/` | `DashboardViewModel` |
| **Resource** (DTO) | HTTP レスポンス整形 (JSON / TSV) | `app/Http/Resource/<Role>/<Entity>/` | `~Resource` (`Arrayable` 直 implements) |
| **Value Object** | ドメインの値そのもの (不変・等価性・バリデーション含む) | `Demo/<Logic>/ValueObject/` | `Email` / `UserId` / `Money` |
| **Entity** | ID で識別されるドメインオブジェクト (振る舞いを持つ) | `Demo/<Logic>/Entity/` | `User` / `Order` |

> ⚠️ **Laravel の Eloquent Model (`App\Models\`) とは別物**。
> このプロジェクトでは Eloquent モデルは使わず (認証は `App\Auth\User\UserAuth` 等の自前 Authenticatable を使う)、
> `App\Models\User` は Laravel ひな型として残置しているだけ。
> 「Model = このプロジェクトのデータクラス群の総称」というプロジェクト内用語。
> 文書や口頭で「Model」と言ったときに Eloquent と紛らわしくないよう注意。

用途で書き分け:

- **Service / Controller で扱う** のは Value Object / Entity / Row
- **Blade に渡す** のは ViewModel (Row を直渡ししない)
- **HTTP レスポンスに整形する** のは Resource

### ディレクトリ構成 (例: `Demo` パッケージ)

```
src/                                       (Laravel プロジェクトルート)
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── UserController.php
│   │   ├── Requests/
│   │   │   └── CreateUserRequest.php
│   │   └── Resources/
│   │       └── UserResource.php
│   ├── Enums/
│   │   └── UserStatus.php
│   └── Constants/
│       └── Limit.php
└── Demo/                                  ← 事業ドメイン (app/ と並列に配置)
    ├── Service/                           ← レイヤー
    │   ├── User/                          ← ビジネスロジック単位
    │   │   ├── UserService.php            (interface)
    │   │   └── UserServiceImpl.php
    │   ├── Order/
    │   │   ├── OrderService.php
    │   │   └── OrderServiceImpl.php
    │   └── Payment/
    │       ├── PaymentService.php
    │       └── PaymentServiceImpl.php
    └── Repository/                        ← レイヤー (DB / API 通信)
        ├── User/
        │   ├── UserRepository.php         (interface)
        │   └── UserRepositoryImpl.php     (DB クエリビルダー)
        ├── Order/
        │   ├── OrderRepository.php
        │   └── OrderRepositoryImpl.php
        └── Payment/                       ← 外部 API も Repository
            ├── PaymentApiRepository.php   (interface)
            └── StripePaymentApiRepository.php  (HTTP 実装)
```

namespace は PSR-4 に従い `Demo\Service\User\UserService` のようになる
(`App\` プレフィックスなし)。`src/composer.json` の `autoload.psr-4` に追記が必要:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/",
        "Demo\\": "Demo/"
    }
}
```

追加後は `docker compose exec app composer dump-autoload` を実行する。

### 依存方向

```
[Controller (app/Http/Controllers/)]
       ↓ 必ず Service 経由
[Service (Demo/Service/<Logic>/)]
       ↓ 必ず Repository 経由
[Repository interface (Demo/Repository/<Logic>/)]
       ↓ 実装
   QueryBuilder (DB) / HttpClient (外部 API)
```

### 鉄則

- **Controller は Service を呼ぶ**、Repository を直接呼ばない
- **Service は必ず Repository 経由で外部 (DB / API / ファイル) と通信**。
  Service 内で `DB::`, `Http::`, `Storage::` を直接呼ばない
- **Repository / Service は interface 必須**、実装は別ファイル
- Service / Repository は **Request / Response / Resource クラスを知らない**
  (HTTP 層の型は Controller で DTO / プリミティブに変換してから渡す)
- **「処理が短いから」「1 行だけだから」は例外にしない**。
  `DB::table('user')->count()` のような 1 行でも必ず Service → Repository を通す。
  ここで一つ例外を許すと「これも短いし」「これも 2 行だし」と崩れていく (broken windows)

**NG: 短いからと Controller に直接書く**

```php
use Illuminate\Support\Facades\DB;

final class DashboardController
{
    public function index(): View
    {
        // NG: Controller から DB を直接叩く / Service / Repository を通さない
        $userCount = DB::table('user')->count();
        $orderTotal = DB::table('order')->sum('amount');

        return view('dashboard', compact('userCount', 'orderTotal'));
    }
}
```

**OK: 1 行でも必ず Service 経由**

```php
final class DashboardController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly OrderService $orderService,
    ) {}

    public function index(): View
    {
        $userCount  = $this->userService->getUserCount();
        $orderTotal = $this->orderService->getTotalAmount();

        return view('dashboard', compact('userCount', 'orderTotal'));
    }
}
```

Service / Repository 側はそれぞれ 1 メソッド追加するだけ:

```php
// Demo/Service/User/UserServiceImpl.php
public function getUserCount(): int
{
    return $this->userRepository->count();
}

// Demo/Repository/User/UserRepositoryImpl.php
public function count(): int
{
    return DB::table('user')->count();
}
```

同じく **`Http::`, `Storage::`, `Cache::` も Controller / Service から直接呼ばない**。
すべて Repository に閉じる。

### Repository に置くもの (must)

DB 通信と外部 API 通信は **必ず Repository を経由する**。

| 種類 | Repository 内で使うもの |
|---|---|
| DB | `DB::table('user')->...` (**クエリビルダー基本**) |
| 外部 API | `Http::post(...)` (Laravel HTTP Client) |
| ファイル / S3 | `Storage::disk(...)->...` |
| キャッシュ | `Cache::store(...)->...` |

Service からはこれらの Facade を直接呼ばない。**すべて Repository に閉じる**。

### DB Repository はクエリビルダー基本

Eloquent ORM ではなく **クエリビルダー** (`DB::table(...)`) を使う。
理由:
- SQL に近く、N+1 等の隠れた挙動が出にくい
- Eloquent モデルが Service 層に漏れない (層の独立性)
- テスト時に挙動が予測しやすい

```php
// Demo/Repository/User/UserRepository.php
namespace Demo\Repository\User;

interface UserRepository
{
    public function findById(int $id): ?UserRow;
    public function getActiveUserList(): array;
    public function insert(UserRow $row): int;
}

// Demo/Repository/User/UserRepositoryImpl.php
namespace Demo\Repository\User;

use App\Enums\UserStatus;
use Illuminate\Support\Facades\DB;

final class UserRepositoryImpl implements UserRepository
{
    public function findById(int $id): ?UserRow
    {
        $row = DB::table('user')->where('id', $id)->first();
        return $row ? UserRow::fromStdClass($row) : null;
    }

    public function getActiveUserList(): array
    {
        return DB::table('user')
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => UserRow::fromStdClass($r))
            ->all();
    }

    public function insert(UserRow $row): int
    {
        return DB::table('user')->insertGetId($row->toArray());
    }
}
```

`UserRow` は readonly DTO (Eloquent モデルではない PHP オブジェクト)。
これにより Service 層に ORM の知識が漏れない。

### API Repository の例

```php
// Demo/Repository/Payment/PaymentApiRepository.php
namespace Demo\Repository\Payment;

interface PaymentApiRepository
{
    public function charge(int $userId, int $amount): PaymentResult;
}

// Demo/Repository/Payment/StripePaymentApiRepository.php
namespace Demo\Repository\Payment;

use Illuminate\Support\Facades\Http;

final class StripePaymentApiRepository implements PaymentApiRepository
{
    public function charge(int $userId, int $amount): PaymentResult
    {
        $response = Http::withToken(config('services.stripe.key'))
            ->post('https://api.stripe.com/v1/charges', [
                'amount'   => $amount,
                'currency' => 'jpy',
                'metadata' => ['user_id' => $userId],
            ])
            ->throw();

        return PaymentResult::fromArray($response->json());
    }
}
```

### Service の例 (Repository を組み合わせる)

```php
// Demo/Service/Order/OrderServiceImpl.php
namespace Demo\Service\Order;

use Demo\Repository\Order\OrderRepository;
use Demo\Repository\Payment\PaymentApiRepository;
use Demo\Repository\User\UserRepository;
use Illuminate\Support\Facades\DB;

final class OrderServiceImpl implements OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly PaymentApiRepository $paymentApiRepository,
    ) {}

    public function placeOrder(int $userId, int $amount): Order
    {
        // Repository 経由で取得 (DB::table を直接触らない)
        $user = $this->userRepository->findById($userId)
            ?? throw new \InvalidArgumentException("user not found: {$userId}");

        // 外部 API も Repository 経由 (Http::post を直接触らない)
        $payment = $this->paymentApiRepository->charge($user->id, $amount);

        // トランザクション境界は Service が握る
        return DB::transaction(function () use ($user, $payment, $amount) {
            $orderId = $this->orderRepository->insert(/* ... */);
            return $this->orderRepository->findById($orderId);
        });
    }
}
```

### DI バインド (`ServiceServiceProvider` / `RepositoryServiceProvider` に分割)

interface → 実装の binding は **役割別に Provider を分けて集約** する。
変更が単一ファイルで完結し、レビューしやすい。

```
app/Providers/
├── AppServiceProvider.php          boot 処理 (Auth::provider 等の Laravel 統合)
├── ServiceServiceProvider.php       Service interface → Impl の binding
└── RepositoryServiceProvider.php    Repository interface → Impl の binding
```

#### `RepositoryServiceProvider`

```php
namespace App\Providers;

use Demo\Repository\User\UserRepository;
use Demo\Repository\User\UserRepositoryImpl;
use Demo\Repository\Payment\PaymentApiRepository;
use Demo\Repository\Payment\StripePaymentApiRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- User ---
        $this->app->bind(UserRepository::class, UserRepositoryImpl::class);

        // --- Payment (本番は Stripe、テストでは fake に差し替え可能) ---
        $this->app->bind(PaymentApiRepository::class, StripePaymentApiRepository::class);
    }
}
```

#### `ServiceServiceProvider`

```php
namespace App\Providers;

use Demo\Service\User\UserService;
use Demo\Service\User\UserServiceImpl;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // --- User ---
        $this->app->bind(UserService::class, UserServiceImpl::class);
    }
}
```

#### `AppServiceProvider` (boot 専用)

```php
namespace App\Providers;

use App\Auth\User\UserAuthProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 空 (interface→Impl binding は ServiceServiceProvider / RepositoryServiceProvider へ)
    }

    public function boot(): void
    {
        Auth::provider('userauth', fn ($app) => $app->make(UserAuthProvider::class));
    }
}
```

#### Provider 登録 (`bootstrap/providers.php`)

```php
return [
    // 依存順: Repository → Service → App (Service は Repository に、Auth は両方に依存)
    RepositoryServiceProvider::class,
    ServiceServiceProvider::class,
    AppServiceProvider::class,
];
```

#### 鉄則

- 新しい **Repository を追加したら `RepositoryServiceProvider`** に bind 追記
- 新しい **Service を追加したら `ServiceServiceProvider`** に bind 追記
- `AppServiceProvider` には interface→Impl の bind を書かない (boot 専用)

### Controller の役割 (薄く保つ)

```php
final class UserController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function store(CreateUserRequest $request): UserResource
    {
        $user = $this->userService->register(
            name:  $request->validated('name'),
            email: $request->validated('email'),
        );
        return new UserResource($user);
    }
}
```

Controller の責務:
1. FormRequest で入力検証
2. Service にプリミティブ値 / DTO を渡す
3. Service の戻り値を Resource で整形して返す

### interface には必ず PHPDoc を付ける

Service / Repository の interface は **「外部から見たこのクラスの API 仕様書」**。
実装を見なくても interface だけ読めば呼び出し方が分かるようにする。

最低限書くこと:

- **概要** (1〜2 行): 何をするか、副作用、トランザクション境界の有無、冪等性
- `@param`: 型は型ヒントから自明な場合でも、**意味** を 1 行説明する
- `@return`: 返り値が何を表すか / 空配列 / null になるケースの意味
- `@throws`: 投げる例外クラスと発生条件

実装側 (`~Impl`) では interface に書いた public メソッドの PHPDoc は **書き直さない**。
PHPDoc は interface だけで一元管理する。
IDE (PhpStorm / VSCode + Intelephense) は interface の PHPDoc を実装にも継承表示するので、
二重に書くとメンテが二重になる。

ただし、**実装側の `private` メソッドには PHPDoc を付ける**。
private は interface に出てこない「実装の内部分割」なので継承元がない。
本体メソッドの guard clause を切り出したり、複雑な計算を分けたりした場合は、
その意図 / 引数の意味 / 例外条件をきちんと書く。

#### Repository interface の例

```php
// src/Demo/Repository/User/UserRepository.php
namespace Demo\Repository\User;

interface UserRepository
{
    /**
     * ID でユーザーを 1 件取得する。
     *
     * @param int $id ユーザー ID
     * @return UserRow|null 該当ユーザー (存在しない場合は null)
     */
    public function findById(int $id): ?UserRow;

    /**
     * status = ACTIVE のユーザーを id 昇順で全件取得する。
     *
     * @return list<UserRow> アクティブユーザー一覧 (該当なしの場合は空配列)
     */
    public function getActiveUserList(): array;

    /**
     * 新規ユーザーを INSERT し、採番された ID を返す。
     *
     * @param UserRow $row INSERT 対象 (id は無視され DB 側で採番される)
     * @return int 採番された ID
     * @throws \Illuminate\Database\QueryException UNIQUE 制約違反など DB エラー時
     */
    public function insert(UserRow $row): int;
}
```

#### Service interface の例

```php
// src/Demo/Service/Order/OrderService.php
namespace Demo\Service\Order;

interface OrderService
{
    /**
     * ユーザーの注文を確定する。
     *
     * 内部で外部決済 API への課金リクエストを行い、成功時のみ order テーブルへ
     * INSERT する。本メソッドがトランザクション境界となる。
     *
     * @param int $userId 対象ユーザー ID
     * @param int $amount 課金額 (円、税込)
     * @return Order 確定した注文
     * @throws \InvalidArgumentException ユーザーが存在しない場合
     * @throws \Demo\Service\Order\PaymentFailedException 決済 API がエラーを返した場合
     */
    public function placeOrder(int $userId, int $amount): Order;
}
```

#### 実装側 (Impl) の例 — public には書かない、private にだけ書く

```php
// src/Demo/Service/Order/OrderServiceImpl.php
namespace Demo\Service\Order;

use Demo\Repository\Order\OrderRepository;
use Demo\Repository\User\UserRepository;
use Demo\Repository\User\UserRow;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\DB;

final class OrderServiceImpl implements OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
    ) {}

    // ↓ interface に PHPDoc を書いているのでここには書かない (IDE が継承表示する)
    public function placeOrder(int $userId, int $amount): Order
    {
        $user = $this->mustGetActiveUser($userId);

        return DB::transaction(function () use ($user, $amount) {
            $orderId = $this->orderRepository->insert(/* ... */);
            return $this->orderRepository->findById($orderId);
        });
    }

    /**
     * ユーザーを取得しつつ「未登録 / 非アクティブ」の場合は例外で弾く。
     * placeOrder の guard clause を切り出したもので、status の判定はこのクラス内に閉じる。
     *
     * @param int $userId 対象ユーザー ID
     * @return UserRow status = ACTIVE のユーザー
     * @throws \InvalidArgumentException 未登録 または 非アクティブ
     */
    private function mustGetActiveUser(int $userId): UserRow
    {
        $user = $this->userRepository->findById($userId)
            ?? throw new \InvalidArgumentException("user not found: {$userId}");

        if ($user->status !== UserStatus::ACTIVE) {
            throw new \InvalidArgumentException("user not active: {$userId}");
        }
        return $user;
    }
}
```

#### 補助ルール

- 配列の中身まで書く: `array` ではなく `list<UserRow>` / `array<string, int>` を使う (PHPStan / Psalm が読める)
- nullable は型シグネチャ (`?Foo`) と `@return Foo|null` の両方で明示
- 例外は `@throws \Fully\Qualified\Name` でフル修飾名 (use しなくても IDE が辿れる)
- 「TODO」「FIXME」を PHPDoc に書く場合は **必ず期限か Issue 番号** を付ける
  (例: `@todo 2026-Q3: バッチサイズの動的調整`)
- **実装側の `private` メソッドは Impl ファイル内に PHPDoc** を書く。
  interface に書けない (継承元がない) ので、ここだけは Impl 側が一次情報になる

---

## 5. 制御フロー (早期 return / match)

### if ネストを深くしない

```php
// NG
public function process(?User $user): Result
{
    if ($user !== null) {
        if ($user->age >= 18) {
            if ($user->email !== '') {
                // 本体
                return ...;
            } else {
                throw new InvalidArgumentException('email required');
            }
        } else {
            throw new InvalidArgumentException('under 18');
        }
    } else {
        throw new InvalidArgumentException('user is null');
    }
}

// OK (guard clause)
public function process(?User $user): Result
{
    if ($user === null) {
        throw new InvalidArgumentException('user is null');
    }
    if ($user->age < 18) {
        throw new InvalidArgumentException('under 18');
    }
    if ($user->email === '') {
        throw new InvalidArgumentException('email required');
    }
    // 本体 (ネスト 0)
    return ...;
}
```

### 分岐が多いときは `match` (PHP 8+)

```php
return match ($user->status) {
    UserStatus::ACTIVE  => $this->sendNotification($user),
    UserStatus::PENDING => $this->remind($user),
    UserStatus::DELETED => null,
};
```

`if-elseif` の長いチェーンは使わない。

---

## 6. その他

- **`declare(strict_types=1);`** を全 PHP ファイルの先頭に置く
- **クラスは `final`** がデフォルト (継承を意図する場合のみ外す)
- **コメントは日本語**、なぜ (Why) を書く。何 (What) は型と命名で表現
- **タイムゾーン依存の処理** は `Carbon::now()` (Laravel が `APP_TIMEZONE=Asia/Tokyo` を見る)。`date()` / `time()` を直接使わない
  - ※ Laravel 13 のひな型は `src/config/app.php` の `'timezone'` が `'UTC'` ハードコード。
    `'timezone' => env('APP_TIMEZONE', 'UTC')` に修正済み。
    新規 `composer create-project` をやり直したときは再度修正が必要

---

## 7. Job / Batch / Schedule (非同期処理・定期実行)

非同期処理 (キュー / バッチ) と定期実行は **薄い殻** に保ち、
ビジネスロジック本体は必ず Service に置く。Job / Command 自体には
条件分岐や DB / API 通信を書かない (Controller と同じ薄さ)。

### ディレクトリと命名

| 種類 | 配置 | 命名 |
|---|---|---|
| Job | `src/app/Jobs/` (Laravel 標準) | `~Job` サフィックス (`SendWelcomeMailJob`) |
| Console Command | `src/app/Console/Commands/` | `~Command` サフィックス (`ImportUserCsvCommand`) |
| Schedule 定義 | `src/routes/console.php` (Laravel 11+) | — |

> Job 自体は薄い殻なので、Service / Repository ほどドメイン分割を厳密にする必要はない。
> Laravel 標準の `src/app/Jobs/` (namespace `App\Jobs\<Class>`) でよい。
> Service / Repository だけ `Demo/` 配下のドメイン構造に置く。

### Job クラスは薄く (Service に委譲)

```php
// src/app/Jobs/SendWelcomeMailJob.php
namespace App\Jobs;

use Demo\Service\User\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendWelcomeMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $userId,           // プリミティブか readonly DTO のみ
    ) {}

    public function handle(UserService $userService): void
    {
        // Service に委譲するだけ (DB / API / 分岐を書かない)
        $userService->sendWelcomeMail($this->userId);
    }
}
```

dispatch は Service / Controller から:

```php
SendWelcomeMailJob::dispatch($userId);
```

### Console Command も薄く

```php
// src/app/Console/Commands/ImportUserCsvCommand.php
namespace App\Console\Commands;

use Demo\Service\User\UserService;
use Illuminate\Console\Command;

final class ImportUserCsvCommand extends Command
{
    protected $signature = 'user:import-csv {path}';
    protected $description = 'CSV からユーザーをインポートする';

    public function handle(UserService $userService): int
    {
        $count = $userService->importUserListFromCsv($this->argument('path'));
        $this->info("{$count} 件インポートしました");
        return self::SUCCESS;
    }
}
```

### Batch (複数 Job をまとめる)

複数 Job を 1 単位として進捗管理 / 一括キャンセルしたいときに `Bus::batch()` を使う。
このプロジェクトでは **Bus::batch の Job も通常 dispatch の Job も同じ `default` queue**
に流して **`job` コンテナで処理** する (queue 分離はしていない)。

> **「`batch` コンテナ」と「`Bus::batch` 機能」は別物。** ⚠️
> 当プロジェクトの `batch` コンテナは **cron daemon (時刻トリガ)** であり Job 本体は実行しない。
> Bus::batch の Job は普通の queue:work (`job` コンテナ) が処理する。

```php
use App\Jobs\ImportUserCsvJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ImportUserCsvJob('chunk-1.csv'),
    new ImportUserCsvJob('chunk-2.csv'),
    new ImportUserCsvJob('chunk-3.csv'),
])
->then(fn (Batch $b) => /* 全成功時 */)
->catch(fn (Batch $b, \Throwable $e) => /* 1 つでも失敗 */)
->finally(fn (Batch $b) => /* 完了時 (成功/失敗どちらも) */)
->name('user import')
->dispatch();
```

Job 側で `use Batchable;` を入れておくと、`$this->batch()` で batch 経由か通常 dispatch かが
分かる (通常 dispatch では null になるだけで両対応可能)。

事前準備 (初回のみ、`job_batches` テーブルを作成):

```bash
docker compose exec app php artisan make:queue-batches-table
docker compose exec app php artisan migrate
```

### Schedule (定期実行)

Laravel 11+ では `src/routes/console.php` で定義する。
時刻トリガで起動するのは **batch コンテナの cron daemon** (`schedule:run` を毎分実行)。

```php
// src/routes/console.php
use App\Jobs\CleanupInactiveUserJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('user:import-csv /var/data/users.csv')->dailyAt('03:00');
Schedule::job(new CleanupInactiveUserJob())->hourly();
Schedule::call(fn () => /* 何かする */)->everyFiveMinutes();
```

- `Schedule::job(...)` → job コンテナの queue に dispatch される
- `Schedule::command(...)` / `Schedule::call(...)` → batch コンテナ内で **直接実行**
  (`schedule:run` の中で fork & run、Job ではない)

### 環境構成 (Docker / Supervisor)

`docker compose up -d` で 5 コンテナが起動する。**役割は 3 種類**:

| 役割 | コンテナ | 中身 |
|---|---|---|
| **app 系** (HTTP) | `web` / `app` / `db` | nginx + PHP-FPM (Vite) + PostgreSQL。`web` (nginx) と `db` (postgres) は app の **サイドカー** |
| **job 系** (非同期 Job 実行) | `job` | supervisord → `queue:work --queue=default` 1 プロセス常駐。通常 Job も Bus::batch の Job もここで処理 |
| **batch 系** (時刻トリガ) | `batch` | supervisord → `cron -f` + `tail -F scheduler-cron.log`。Job 本体は実行せず、cron が schedule:run を毎分発火するだけ |

worker 系コンテナ (`job` / `batch`) は同じ `laravelarche-app:latest` イメージを使い回し、
bind mount する `supervisord-*.conf` だけが違う。

各 program の中身:

- `job` コンテナ: `[program:job-worker]`
  `php artisan queue:work --queue=default --tries=3 --backoff=10 --max-time=3600 --memory=256 --sleep=3`
- `batch` コンテナ: `[program:cron]` + `[program:cron-tail]`
  - `cron`      : `/usr/sbin/cron -f -L 4` (Debian cron daemon をフォアグラウンドで起動)
  - `cron-tail` : `tail -F /var/www/html/storage/logs/scheduler-cron.log`
    (cron job の出力ファイルを stdout に流して `docker compose logs batch` から見えるようにする)
  - crontab 本体: `docker/cron/laravel-scheduler` を `/etc/cron.d/laravel-scheduler` に bind mount
    `* * * * * www-data php artisan schedule:run --verbose --no-interaction >> storage/logs/scheduler-cron.log 2>&1`

scheduler 役を **Laravel 公式 Production 推奨の cron daemon パターン** で実装。
`schedule:work` (Laravel 11+ 限定) も `while true` シェルループも採らない。
理由:
- 運用ツール / モニタリングが cron 前提で揃っている (Linux 標準と一致)
- Laravel 10 / 11 / 12 / 13 すべてで同じ書き方
- `MAILTO=""` / `PATH` 等の cron 標準セマンティクスをそのまま使える

cron job が出力を `/proc/1/fd/1` に直接書こうとしても **supervisord (root) 所有で www-data から書けない** ため、
storage/logs/scheduler-cron.log にいったん書いて tail -F で stdout に流す構成にしている。

なぜ job と batch を分けるか:

- **役割の責務分離**: job (Job 実行) と batch (時刻トリガ) は性質が違う
- **障害隔離**: cron が死んでも job worker は動き続ける (逆も同じ)
- 重い処理を別 worker に分離したくなったら、必要になった時点で queue 名を
  切り出して (`->onQueue('heavy')` 等) `job` の supervisord に program を追加するか、
  別コンテナを足せばよい。**最初は分けない (YAGNI)**

運用コマンド:

```bash
# 各コンテナの supervisor 配下プロセス状況
docker compose exec job   supervisorctl status
docker compose exec batch supervisorctl status

# 失敗 Job 確認 / 再実行 / 全クリア
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:flush
```

- **Queue ドライバーは `database`** (`src/.env` の `QUEUE_CONNECTION=database`、`jobs` テーブル使用)
- 失敗 Job は `failed_jobs` テーブル、Batch 進捗は `job_batches` テーブルに格納される

### 鉄則

- **`handle()` の中身は Service 呼び出し 1 行が基本**。条件分岐 / DB / API 通信を書かない
- **Job コンストラクタ引数はプリミティブ / readonly DTO のみ**。
  Eloquent モデルを渡すと `SerializesModels` が再 fetch して状態がずれる
- **Job は冪等に設計**。リトライで二重実行されてもデータが壊れないようにする
  (例: 処理開始前に「処理済みフラグ」を確認)
- **長時間ジョブはバッチ化** して進捗を可視化する
- **Job 名 / Command シグネチャはドメイン語彙で識別可能に** (`user:import-csv`, `SendWelcomeMailJob`)
- **Service から dispatch** する場合も、テストでは `Bus::fake()` / `Queue::fake()` で差し替えられる前提で書く
- **Worker は Supervisor 経由で運用** (Laravel 公式推奨)。素の `queue:work` を
  Docker の `restart: unless-stopped` だけで運用しない
- **batch コンテナ = cron daemon** + `* * * * * php artisan schedule:run` (Laravel 公式 Production パターン)。
  `schedule:work` (11+ 限定) もシェルループも採らない
- **コンテナ役割は app / job / batch の 3 種類**:
  - `job` = queue:work (Bus::batch を含む全 Job を default queue で処理)
  - `batch` = cron daemon (時刻トリガ。Job は実行しない)
  - `app` = HTTP (php-fpm)。`web` (nginx) と `db` (postgres) はその「サイドカー」
- **「`batch` コンテナ」と「`Bus::batch` 機能」を混同しない**。
  Bus::batch の Job は `job` コンテナで処理される (`->onQueue('batch')` 不要)

---

## 8. HTTP 層 (Request / Resource)

Controller の入力検証と出力整形は **Request** と **Resource** クラスに切り出す。
Controller 本体は **FormRequest 検証 + Service 呼び出し + Resource 整形** の 3 ステップだけ。

### Request (FormRequest)

#### 配置と命名

| 項目 | 規約 |
|---|---|
| 配置 | `src/app/Http/Requests/<Domain>/<SubDomain>/<Name>Request.php` |
| namespace | `App\Http\Requests\<Domain>\<SubDomain>` |
| クラス名 | `~Request` サフィックス (`RegisterRequest`, `UpdateProfileRequest`) |

#### フィールド名は `public const string` で定数化

マジック文字列禁止 (§1)。PHP 8.3+ の型付き定数で宣言する。
rules() / Controller / Service すべて `SomeRequest::FIELD_NAME` で参照する。

```php
<?php

namespace App\Http\Requests\User\Examination;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public const string CLINIC_ID            = 'clinic_id';
    public const string EXAMINATION_STOCK_ID = 'examination_stock_id';
    public const string RESERVATION_REMARKS  = 'reservation_remarks';
    public const string NAME                 = 'name';
    public const string NAME_KANA            = 'name_kana';
    public const string EMAIL                = 'email';
    public const string TEL                  = 'tel';
    public const string IS_MINOR             = 'is_minor';
    public const string IS_AGREE_TERM        = 'is_agree_term';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            self::CLINIC_ID => [
                'required',
                Rule::exists('clinic', 'clinic_id'),
            ],
            self::EXAMINATION_STOCK_ID => [
                'required',
                'array',
                Rule::exists('examination_stock', 'examination_stock_id'),
            ],
            self::RESERVATION_REMARKS => ['nullable', 'string'],
            self::NAME                => ['required', 'string'],
            self::NAME_KANA           => ['required', 'string'],
            self::EMAIL               => ['required', 'email:strict,dns'],
            self::TEL                 => ['required', 'string', 'regex:/^\d{10,11}$/'],
            self::IS_MINOR            => ['nullable', 'boolean'],
            self::IS_AGREE_TERM       => ['required', 'accepted'],
        ];
    }
}
```

#### 鉄則

- フィールド名は **`public const string`** で定数化 (型付き定数、大文字スネークケース)
- `rules()` で **`self::FIELD_NAME`** を使う (キーに文字列直書き禁止)
- Controller でも **`$request->validated(SomeRequest::FIELD_NAME)`** で参照
- `authorize()` は **必ず明示** (Laravel デフォルトに任せない)
- `rules()` に PHPDoc で戻り値型を書く (`array<string, ValidationRule|array<mixed>|string>`)

### Resource

#### 配置と命名

| 項目 | 規約 |
|---|---|
| 配置 | `src/app/Http/Resource/<Role>/<Entity>/<Name>Resource.php` |
| namespace | `App\Http\Resource\<Role>\<Entity>` |
| クラス名 | `~Resource` サフィックス |

> ⚠️ ディレクトリ / namespace は **単数形 `Resource`** (Laravel 標準の `Resources` 複数形は採らない)。
> §2 「単数形優先」と一致させる。

#### `Illuminate\Contracts\Support\Arrayable` を直 implements

Laravel の `JsonResource` は使わない。理由:

- `JsonResource` は `$this->resource->foo` のマジックアクセスで型が追えない
- `Arrayable<TKey, TValue>` を直接 implements して **generics で型を明示**する方が型安全
- JSON 以外 (TSV / CSV / Excel 等) の出力先にも流用しやすい

```php
<?php

declare(strict_types=1);

namespace App\Http\Resource\Admin\ExaminationReservation;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Package\Model\Examination\ExaminationReservationProfileModel;
use RuntimeException;

/**
 * @implements Arrayable<int, string>
 */
class ReservationListResource implements Arrayable
{
    /**
     * @param ExaminationReservationProfileModel[] $examinationReservationProfileModelList
     */
    public function __construct(
        private array $examinationReservationProfileModelList,
    ) {}

    /**
     * @return array<int, string>
     */
    public function toArray(): array
    {
        $outputData = array_map(function (ExaminationReservationProfileModel $model) {
            $dateTimeList = $model->getExaminationDateTime();
            $dateTime = implode("\n", array_map(
                fn (CarbonImmutable $dt) => $dt->format('Y-m-d H:i:s'),
                $dateTimeList,
            ));
            return [
                $model->getUserName(),
                $model->getUserEmail(),
                $model->getUserTel(),
                $dateTime,
                // ...
            ];
        }, $this->examinationReservationProfileModelList);

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            throw new RuntimeException('TSV 書き出しのファイルポインタ生成に失敗しました');
        }
        foreach ($outputData as $line) {
            fputcsv($fp, $line, "\t");
        }
        rewind($fp);
        $tsv = stream_get_contents($fp);
        fclose($fp);

        return [rtrim($tsv, "\n")];
    }
}
```

#### 鉄則

- `declare(strict_types=1);` 必須 (§6)
- **`Illuminate\Contracts\Support\Arrayable` を implements** (`JsonResource` は使わない)
- PHPDoc に **generics 表記**: `@implements Arrayable<int, string>` / `@return array<int, string>`
- コンストラクタで Model (or リスト) を **private プロパティ昇格** で受け取る
- 配列引数は PHPDoc で要素型を明示 (`@param Foo[] $list`)
- 日付は **`CarbonImmutable`** を使う (`Carbon` (可変版) は副作用が出やすい)
- `RuntimeException` 等で fail-fast (`if ($fp === false)` のような defensive check も guard clause で短く)

### ViewModel (Blade に渡す DTO)

#### 配置と命名

| 項目 | 規約 |
|---|---|
| 配置 | `src/app/Http/ViewModel/<Domain>/<SubDomain>/<Name>ViewModel.php` |
| namespace | `App\Http\ViewModel\<Domain>\<SubDomain>` |
| クラス名 | `~ViewModel` サフィックス |

#### Blade に渡すデータは ViewModel (object) で渡す

`Auth` オブジェクト / Eloquent モデル / Service の結果型を **Blade に直接渡さない**。
ViewModel に **詰め替えてから** 渡す。

理由:
- View は **必要なフィールドだけ** 受け取り、不必要な依存型を持たない (Blade 側のロジック軽減)
- `$user->name()` (メソッド呼び出し) より `$vm->userName` (プロパティ) のほうが型と意味が読みやすい
- View 用整形ロジック (日付フォーマット、表示用文字列など) を後から追加しやすい

```php
// app/Http/ViewModel/User/DashboardViewModel.php
namespace App\Http\ViewModel\User;

use App\Auth\User\UserAuth;

final readonly class DashboardViewModel
{
    public function __construct(
        public int $userId,
        public string $userName,
        public string $userEmail,
    ) {}

    /**
     * 認証中の UserAuth から ViewModel を組み立てる。
     */
    public static function fromAuth(UserAuth $auth): self
    {
        return new self(
            userId: $auth->id(),
            userName: $auth->name(),
            userEmail: $auth->email(),
        );
    }
}
```

Controller:

```php
return view('user.dashboard', [
    'vm' => DashboardViewModel::fromAuth($auth),
]);
```

Blade:

```blade
<h1>ようこそ、{{ $vm->userName }}さん</h1>
<p>Email: {{ $vm->userEmail }}</p>
```

#### Resource と ViewModel の使い分け

| 用途 | クラス | 配置 | 役割 |
|---|---|---|---|
| HTTP レスポンス (JSON / TSV / 外部 API 応答) | `~Resource` | `App\Http\Resource\` | API 応答の整形 (Arrayable) |
| Blade テンプレートに渡す | `~ViewModel` | `App\Http\ViewModel\` | View に渡す DTO (object) |

#### 鉄則

- ViewModel は **`final readonly class`**
- Blade に渡すのは **必ず ViewModel 経由**。Auth / Eloquent モデル / Service の戻り値を Blade に直渡ししない
- `fromXxx(...)` 等の **static factory** で組み立てる (コンストラクタは値の代入のみ)
- 値プロパティ中心、メソッドは表示整形が必要なときだけ最小限に
- React 等の SPA に渡す場合は ViewModel ではなく **Resource (Arrayable)** を使う

### Controller (Request × Resource × Service)

Controller の責務は **3 ステップだけ**:

```php
use App\Http\Requests\User\Examination\RegisterRequest;
use App\Http\Resource\User\Examination\RegisterResultResource;
use Demo\Service\Examination\ExaminationReservationService;

final class ExaminationReservationController
{
    public function __construct(
        private readonly ExaminationReservationService $service,
    ) {}

    public function register(RegisterRequest $request): RegisterResultResource
    {
        // 1. FormRequest が自動検証 (rules() の通り)
        // 2. Service にプリミティブ / DTO で渡す (フィールド参照は定数経由)
        $result = $this->service->register(
            clinicId:           $request->validated(RegisterRequest::CLINIC_ID),
            examinationStockId: $request->validated(RegisterRequest::EXAMINATION_STOCK_ID),
            name:               $request->validated(RegisterRequest::NAME),
            nameKana:           $request->validated(RegisterRequest::NAME_KANA),
            email:              $request->validated(RegisterRequest::EMAIL),
            tel:                $request->validated(RegisterRequest::TEL),
            isMinor:            (bool) $request->validated(RegisterRequest::IS_MINOR),
            reservationRemarks: $request->validated(RegisterRequest::RESERVATION_REMARKS),
        );

        // 3. Resource で整形して返す
        return new RegisterResultResource($result);
    }
}
```

Controller には **ビジネスロジックを 1 行も書かない**。Service への引数を組み立てて、結果を Resource に流すだけ。

---

## 9. PR 規約 (マイグレーション編)

`src/database/migrations/` 以下にファイルを **追加 / 変更した PR** は、
本文に以下の 5 項目を必ず書く。レビューエージェント
[`.claude/agents/laravel-migration-pr-checker.md`](.claude/agents/laravel-migration-pr-checker.md)
がこの規約に基づいて自動チェックする。

### 9-1. 追加マイグレーションファイル

差分に入っているマイグレーションファイルのパスを列挙する。

```markdown
- `src/database/migrations/2026_05_24_220001_create_kanban_card_table.php`
```

### 9-2. 実行される SQL

`Schema::create(...)` / `Schema::table(...)` から実際に発行される SQL を `sql` コードブロックで貼る。
PostgreSQL を前提に、`BIGSERIAL` / `TIMESTAMP(0) WITHOUT TIME ZONE` 等の本物の型名で書く。

入手方法:
- migration ファイルから直接読み解いて手書き (シンプル)
- migrate 済みのテーブルなら `docker compose exec db psql -U laravel -d laravel -c '\d <table>'` の
  出力を整形
- 未 migrate のときは `php artisan migrate --pretend` で発行 SQL を確認

```markdown
\`\`\`sql
CREATE TABLE "kanban_card" (
    "id"       BIGSERIAL    PRIMARY KEY,
    "user_id"  BIGINT       NOT NULL,
    ...
);
CREATE INDEX "kanban_card_user_id_lane_position_index"
    ON "kanban_card" ("user_id", "lane", "position");
\`\`\`
```

### 9-3. EXPLAIN (実行計画)

新規追加カラム / インデックスが効くべき **主要 SELECT クエリ** について `EXPLAIN ANALYZE` を実行し、
出力を貼る。確認したいのは:

- `Index Scan` を使えているか (`Seq Scan` に落ちていないか)
- 想定したインデックスが選ばれているか
- 実行時間が妥当な範囲か (典型的にはサンプルデータで sub-ms)

```bash
docker compose exec -T db psql -U laravel -d laravel \
  -c "EXPLAIN ANALYZE SELECT * FROM kanban_card WHERE user_id = 1 AND deleted_at IS NULL ORDER BY lane, position;"
```

出力を貼ったうえで、1〜2 行のコメント (どのインデックスが効いたか / Seq Scan ではないことを確認した旨)
を添える。

### 9-4. down() の reversibility

`down()` がどう書かれているか、安全に巻き戻せるかを書く。
特に以下のケースは明示すること:

- 外部キー制約を張った / 張らない (張ったなら down() で適切に DROP するか)
- `DROP COLUMN` で消えるデータがある場合の補足
- `dropSoftDeletes()` / `dropIfExists()` を使っているなら問題なし

### 9-5. 既存データへの影響

- 既存行に対する挙動 (NULL 埋め / DEFAULT 値 / バックフィル要否)
- ロック挙動 (`ALTER TABLE ... ADD COLUMN NULL` は PostgreSQL 11+ で
  メタデータのみ、`NOT NULL DEFAULT ...` は全行書き換えで長時間ロック等)
- 本番運用時に検討すべき段階的 migration (例: 大規模テーブルなら NULL カラム追加 →
  バックフィル → NOT NULL 化、を別 PR に分割)

サンプルプロジェクトなので「行数少なく影響なし」と書くだけでも OK だが、
本番投入を意識した PR では具体的に書くこと。

### 不要な PR

`PULL_REQUEST_TEMPLATE.md` の **「DB マイグレーション」セクションは、マイグレーション差分が無い PR では
丸ごと削除して構わない**。テンプレが残ったままだとレビューエージェントが「未記入」と扱う可能性があるので、
不要な PR では「セクションごと削除」 を徹底する。

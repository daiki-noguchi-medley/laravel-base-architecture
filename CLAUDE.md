# Laravel プロジェクト コーディング規約

このプロジェクト (Laravel 13 / PHP 8.4) に固有のルール。
グローバル規約 (`~/.claude/CLAUDE.md`) を Laravel / PHP のコンテキストで具体化する。

---

## 1. 定数 / Enum

ドメインに意味のある値は **必ず** 型付きの `enum` か `const` で宣言する。
コード中に数値・文字列リテラルを直接書かない (マジックナンバー禁止)。

### 命名

- **定数名** は 大文字スネークケース (`MAX_RETRY_COUNT`)
- **enum のケース名** は **パスカルケース** (`UserStatus::Active`)。
  PER Coding Style 2.0 が enum case を PascalCase と定めており、PHP / Laravel の標準系もこれに揃う
- **backed enum の値** はドメインで意味のある小文字スネークケース (`'active'`)

> ⚠️ **ケース名と値は別物として扱う。**
> ケース名 (PHP 識別子) の変更は安全だが、**値は DB の既存行・CHECK 制約・外部との契約
> (ジョブのイベント名、外部サービスのステージ名など) がそのまま参照している**。
> 「表記を揃えたいから」で値を書き換えない。**値の変更はデータ移行と対でしか行わない。**

### 置き場 (Model と同居させない)

| 種類 | 配置 | 例 |
|---|---|---|
| ドメインの区分値 (DB に入る値・状態) | `app/Enum/<ドメイン>/` | `App\Enum\Kanban\KanbanLane` |
| ユースケースの結果 (`~Outcome`) | `Demo/<ドメイン>/Service/` | `Demo\User\Service\LoginOutcome` |
| ドメイン横断の定数 | `app/Constants/` | `App\Constants\Limit` |

- **enum を `app/Model/` に置かない。** Model は「1 件のデータ」を表す入れ物、enum は
  「取り得る値の集合」。同居させると、ドメインのフォルダを開いたときに
  «その業務にどんなデータがあるか» が読み取れなくなる
- **例外は `~Outcome` だけ。** ユースケースの結果を表す enum は結果オブジェクトと対で
  1 つの戻り値を成すので、Service の隣に置く (§4 「データクラスの種別」参照)。
  backed か否かは区別にならない (`SystemSettingOutcome: string` のような backed な結果 enum もある)
- **レイヤーディレクトリ直下に直置きしない** (`app/Enum/UserStatus.php` は違反)。必ず `<ドメイン>` サブフォルダを切る

> ⚠️ **enum を移動した差分では «`use` 無しの同一 namespace 参照» を必ず洗う。**
> 移動前は同じ namespace だったため `use` を書いていないファイルがあると、
> テキスト上はマージが通るのに実行時に `Class "..." not found` で落ちる。
> 移動した enum の短縮名を使っているファイルを全 PHP から抽出し、
> `use` も同一 namespace も無いものが 0 件であることを確認する。

### その値に付随する知識は enum に持たせる

表示名・並び順・対応するファイル名など「その値だけが知っていること」は enum のメソッドにする。
Service / Model / Blade の `match` や配列に散らさない。

### 外部システムと共有する値は先方の表記のまま持つ

値そのものが外部との契約になっているもの (Webhook のイベント種別、外部ワークフローの
ステージ名など、**こちらが表現を決められないもの**) は、自分たちの表記に揃えず
**先方の表記のまま** 持つ。変換を挟むと、先方の資料やログと突き合わせるたびに
「どちらの表記か」を考えることになる。
**この例外に当たる enum は、その理由をクラスの docblock に書く**
(書いていないと、後から «規約違反» として直されてしまう)。ケース名は例外なくパスカルケース。

### 例

```php
// app/Enum/User/UserStatus.php
enum UserStatus: string
{
    case Active  = 'active';
    case Pending = 'pending';
    case Deleted = 'deleted';

    /** 画面表示用のラベル (その値に付随する知識は enum が持つ)。 */
    public function label(): string
    {
        return match ($this) {
            self::Active  => '有効',
            self::Pending => '承認待ち',
            self::Deleted => '削除済み',
        };
    }
}

// app/Constants/Limit.php
final class Limit
{
    public const int MAX_RETRY_COUNT   = 5;
    public const int DEFAULT_PAGE_SIZE = 20;
    public const int DIAL_TIMEOUT_SEC  = 3;
}
```

### 悪い例

```php
// NG: マジックナンバー / 文字列リテラル直書き
if ($user->getStatus() === 'active') { ... }
sleep(3);
$retries = 5;

// NG: ケース名が大文字スネーク (PER Coding Style 2.0 は PascalCase)
case ACTIVE = 'active';

// NG: 表示名を enum の外の match / 配列で持つ
$label = ['active' => '有効', 'pending' => '承認待ち'][$user->getStatus()->value];
```

### 良い例

```php
if ($user->getStatus() === UserStatus::Active) { ... }
sleep(Limit::DIAL_TIMEOUT_SEC);
$retries = Limit::MAX_RETRY_COUNT;
$label   = $user->getStatus()->label();
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

### 列には日本語コメントを付ける

`$table->string('status')->comment('申込の状態。App\\Enum\\... の値が入る');` のように、
**マイグレーションの時点で列の意味を DB に残す**。
`\d+ <table>` や DB クライアントから見たときに意味が読めるのが目的で、
「テーブル定義書」という別の資料を作らなくて済む。

外部システムのキーを自DBに持つ場合 (`custid` のような、こちらで名前を決められない識別子) は、
**「どの外部システムの何か」を必ず列コメントに書く** (§10 参照)。

### 状態を表すテーブルは「行が作られる条件」をクラスコメントに書く

`deleted_at` のようなフラグ列ではなく **「行の有無 = その状態」** を別テーブルで表す設計
(`user_active` / `user_application_active` など) を採る場合、
**形式は共通でも «どういう条件で行が作られるか» はテーブルごとに違う**。
マイグレーションのクラスコメントに **行が作られる条件を 1 行** 書く。

```php
/**
 * user_active: 行の有無 = そのユーザーが有効。
 * 行が作られるのは «初回ログインの本人確認に成功したとき»。
 */
return new class extends Migration { ... };
```

書いていないと、フラグ列と違って「どの操作で立つのか」がコードを全部追わないと分からなくなる。

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
    echo $user->getName();
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

### アクセサ命名のスタイル (Model / Auth)

クラスの種別ごとに使い分ける。**強制統一はしない**、用途で選ぶ。

#### Model — private プロパティ + `get~` アクセサで統一

テーブル 1 行を表すデータクラス。**プロパティは公開せず、取り出しは必ず `get~` メソッド経由**。

```php
// app/Model/User/User.php
final readonly class User
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

// 使用
$user->getId();
$user->getName();
```

理由:
- 生プロパティ公開だと、後から加工・派生 (`getMaskedEmail()` 等) を挟む余地がなく、
  挟んだ瞬間に「素のプロパティ」と「加工メソッド」の二重アクセス面ができる。
  最初から get~ に統一すれば取り出し面が 1 つで済む
- bool を返す場合は `is~` / `has~` / `can~` (get~ にしない)

#### Auth / ドメインオブジェクト — Laravel スタイル (動詞なし) 基本

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

#### Auth / ドメインオブジェクト側で `getXxx()` を使ってよいケース

(Model は上記の通り常に `get~`。) Laravel スタイル側でも、メソッド名だけだと
**「何を返すか」が曖昧** / **加工 / 派生 / 計算** が入る場合は `getXxx()` で明示してよい:

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

事業ドメイン単位でパッケージを切り (`Demo/`)、その直下を **ドメイン単位** でフォルダ分割する
(**ドメイン先頭**)。各ドメインフォルダの中に Service / Repository レイヤーを置く。
テーブルに対応するデータクラス (**Model**) は `app/Model/<ドメイン>/` に置く。

### データクラスの種別

| 種別 | 役割 | 配置 | 例 |
|---|---|---|---|
| **Model** | テーブル 1 枚 = 1 クラス。Repository ↔ Service の境界で使う readonly クラス (private プロパティ + `get~` アクセサ) | `app/Model/<ドメイン>/` | `User` / `Admin` / `KanbanCard` |
| **ViewModel** (DTO) | Blade に渡す | `app/Http/ViewModel/<Domain>/<SubDomain>/` | `DashboardViewModel` |
| **Resource** (DTO) | HTTP レスポンス整形 (JSON / TSV) | `app/Http/Resource/<Role>/<Entity>/` | `~Resource` (`Arrayable` 直 implements) |
| **クエリ結果 DTO** | テーブルに対応しない JOIN / 集計結果 | `Demo/<ドメイン>/Repository/` | `~Result` サフィックス (`UserWithOrderCountResult`) |

Model の規約:

- **Model はテーブルに対応するクラスにだけ使う**。クラス名は suffix なしの **ドメイン名詞**
  (`User`, `KanbanCard`)。namespace (`App\Model\<ドメイン>`) がレイヤーを語るので `~Model` サフィックスは付けない
- **`app/` 配下のデータクラスはレイヤーディレクトリ直下に直置きしない**。必ず `<ドメイン>` サブフォルダを切る
  (`app/Model/Kanban/KanbanCard.php`, `app/Enum/Kanban/KanbanLane.php`)。
  Request / Resource / ViewModel が `<Domain>/<SubDomain>` を切るのと同じルール。
  ドメイン横断の汎用定数 (`app/Constants/Limit.php` 等) だけは直置きを許容する
- Model のプロパティは **private** とし、取り出しは **`get~` アクセサ経由** (§3)。
  生プロパティ (`$user->id`) の直アクセスは書かない
- Model は DB の知識 (カラム名 / stdClass) を持たない。クエリビルダー結果 → Model の変換は
  各 RepositoryImpl の **`private function toModel(stdClass $row)`** が担う (後述の Repository 例を参照)。
  複数の Impl で変換を共有したくなったら、その時点で `~Mapper` クラスに切り出す
- テーブルに対応しないクエリ結果 (JOIN / 集計) は Model にせず、
  該当 Repository の隣に `~Result` サフィックスの DTO として置く
- Value Object / Entity は **現時点では導入しない (YAGNI)**。値そのものにロジック
  (`Money` の加算、`Email` のバリデーション等) が必要になった時点で導入を検討する

> ⚠️ **Laravel の Eloquent Model (`App\Models\`) とは別物**。
> このプロジェクトでは Eloquent モデルは使わず (認証は `App\Auth\User\UserAuth` 等の自前 Authenticatable を使う)、
> ひな型の `App\Models\User` と `database/factories/UserFactory.php` は **削除済み**。
> `App\Models\` namespace は使わない (このプロジェクトの Model は `App\Model\` 単数形)。

用途で書き分け:

- **Service / Controller で扱う** のは Model (とクエリ結果 DTO)
- **Blade に渡す** のは ViewModel (Model を直渡ししない)
- **HTTP レスポンスに整形する** のは Resource

### ディレクトリ構成 (例: `Demo` パッケージ)

```
src/                                       (Laravel プロジェクトルート)
├── app/
│   ├── Http/
│   │   ├── Controller/
│   │   │   └── UserController.php
│   │   ├── Request/
│   │   │   └── CreateUserRequest.php
│   │   ├── Resource/
│   │   │   └── UserResource.php
│   │   └── ViewModel/
│   │       └── DashboardViewModel.php
│   ├── Model/                             ← テーブル対応 Model (suffix なし、ドメインサブフォルダ必須)
│   │   ├── User/
│   │   │   └── User.php
│   │   └── Order/
│   │       └── Order.php
│   ├── Enum/
│   │   └── User/
│   │       └── UserStatus.php
│   └── Constants/
│       └── Limit.php
└── Demo/                                  ← 事業ドメイン (app/ と並列に配置)
    ├── User/                              ← ドメイン単位 (ドメイン先頭)
    │   ├── Service/                       ← レイヤー
    │   │   ├── UserService.php            (interface)
    │   │   └── UserServiceImpl.php
    │   └── Repository/                    ← レイヤー (DB / API 通信)
    │       ├── UserRepository.php         (interface)
    │       └── UserRepositoryImpl.php     (DB クエリビルダー + toModel)
    ├── Order/
    │   ├── Service/
    │   │   ├── OrderService.php
    │   │   └── OrderServiceImpl.php
    │   └── Repository/
    │       ├── OrderRepository.php
    │       └── OrderRepositoryImpl.php
    └── Payment/
        ├── Service/
        │   ├── PaymentService.php
        │   └── PaymentServiceImpl.php
        └── Repository/                    ← 外部 API も Repository
            ├── PaymentApiRepository.php   (interface)
            └── StripePaymentApiRepository.php  (HTTP 実装)
```

ディレクトリ名は **単数形** (`Controller` / `Request` / `Resource` / `ViewModel` / `Model` / `Enum`)。
例外として **Laravel 標準のまま残すもの**: `app/Providers` / `app/Jobs` / `app/Constants` /
`app/Console/Commands` / `database/*` / `tests` (フレームワーク管理領域・既存規約の据え置き)。
`artisan make:controller` 等のジェネレーターは複数形ディレクトリに生成するので、生成後に移動する。

namespace は PSR-4 に従い `Demo\User\Service\UserService` のように **ドメイン → レイヤー** の順になる
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
[Controller (app/Http/Controller/)]
       ↓ 必ず Service 経由
[Service (Demo/<ドメイン>/Service/)]
       ↓ 必ず Repository 経由
[Repository interface (Demo/<ドメイン>/Repository/)]
       ↓ 実装
   QueryBuilder (DB) / HttpClient (外部 API)
       ↕ Repository ↔ Service の受け渡しは Model (app/Model/)
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
// Demo/User/Service/UserServiceImpl.php
public function getUserCount(): int
{
    return $this->userRepository->count();
}

// Demo/User/Repository/UserRepositoryImpl.php
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

### トランザクション境界は `Transaction` 経由で張る

「Service は `DB::` を直接呼ばない」を守ると、**複数 Repository の書き込みを 1 つの
トランザクションで束ねる手段が無くなる**。ここだけ `DB::transaction()` を書いてよい、
とすると鉄則が崩れるので、境界そのものを interface にして注入する。

| 種類 | 配置 | 役割 |
|---|---|---|
| interface | `app/Support/Transaction/Transaction.php` | Service が依存する抽象 |
| 実装 | `app/Support/Transaction/DatabaseTransaction.php` | `DB::transaction()` へ委譲 |

- Repository でも Service でもない **横断インフラ** だが、永続化に近い共通基盤なので
  DI バインドは **`RepositoryServiceProvider`** に書く
- 横断インフラの実装クラスは `~Impl` ではなく **「実装が何であるか」を名前で示す**
  (`DatabaseTransaction`)。Repository / Service と違って
  «同じ interface に別種の実装が並ぶ» ことが前提のため

```php
// Demo/Order/Service/OrderServiceImpl.php
public function __construct(
    private readonly Transaction $transaction,
    private readonly OrderRepository $orderRepository,
    private readonly StockRepository $stockRepository,
) {}

public function placeOrder(int $userId, int $itemId): Order
{
    return $this->transaction->run(function () use ($userId, $itemId): Order {
        $orderId = $this->orderRepository->insert($userId, $itemId);
        $this->stockRepository->decrement($itemId);

        return $this->orderRepository->findById($orderId);
    });
}
```

> ⚠️ **クロージャの中で早期 `return` して «途中まで» で抜けない。**
> 巻き戻すのは例外だけなので、`return` した時点でそこまでの書き込みが確定する。
> 複数件を書く処理は «全部の検証 → 全部の書き込み» の順にする (§6)。

> ⚠️ **中で握り潰した SQL エラーは COMMIT を無言の ROLLBACK に変える (PostgreSQL)。**
> トランザクション中に 1 つでも SQL エラーが起きると以降の文がすべて失敗するため、
> `catch` してログだけ残すと「成功ログが出ているのに 1 行も書かれていない」になる。

### DB Repository はクエリビルダー基本

Eloquent ORM ではなく **クエリビルダー** (`DB::table(...)`) を使う。
理由:
- SQL に近く、N+1 等の隠れた挙動が出にくい
- Eloquent モデルが Service 層に漏れない (層の独立性)
- テスト時に挙動が予測しやすい

```php
// Demo/User/Repository/UserRepository.php
namespace Demo\User\Repository;

use App\Model\User\User;

interface UserRepository
{
    public function findById(int $id): ?User;
    public function getActiveUserList(): array;
    public function insert(string $name, string $email): int;
}

// Demo/User/Repository/UserRepositoryImpl.php
namespace Demo\User\Repository;

use App\Enum\User\UserStatus;
use App\Model\User\User;
use Illuminate\Support\Facades\DB;
use stdClass;

final class UserRepositoryImpl implements UserRepository
{
    public function findById(int $id): ?User
    {
        $row = DB::table('user')->where('id', $id)->first();
        return $row === null ? null : $this->toModel($row);
    }

    public function getActiveUserList(): array
    {
        return DB::table('user')
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->toModel($row))
            ->all();
    }

    public function insert(string $name, string $email): int
    {
        return (int) DB::table('user')->insertGetId([
            'name'  => $name,
            'email' => $email,
        ]);
    }

    /**
     * クエリビルダーが返す stdClass を User Model に変換する。
     * カラム名 (snake_case) と型変換の知識はこのクラスに閉じる。
     *
     * @param stdClass $row user テーブルの 1 行
     * @return User 変換済みの Model
     */
    private function toModel(stdClass $row): User
    {
        return new User(
            id: (int) $row->id,
            name: $row->name,
            email: $row->email,
        );
    }
}
```

`User` は `app/Model/User/` の readonly Model (Eloquent ではない素の PHP オブジェクト)。
変換は Impl の `toModel()` に閉じるので、stdClass / snake_case カラム名 / ORM の知識が
Service 層に漏れない。書き込み方向 (insert / update のカラム名) も同じく Impl 内に閉じる。

### API Repository の例

```php
// Demo/Payment/Repository/PaymentApiRepository.php
namespace Demo\Payment\Repository;

interface PaymentApiRepository
{
    public function charge(int $userId, int $amount): PaymentResult;
}

// Demo/Payment/Repository/StripePaymentApiRepository.php
// (PaymentResult はテーブル非対応の「クエリ結果 DTO」なので ~Result 命名で Repository の隣に置く)
namespace Demo\Payment\Repository;

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
// Demo/Order/Service/OrderServiceImpl.php
namespace Demo\Order\Service;

use Demo\Order\Repository\OrderRepository;
use Demo\Payment\Repository\PaymentApiRepository;
use Demo\User\Repository\UserRepository;
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
        $payment = $this->paymentApiRepository->charge($user->getId(), $amount);

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

use Demo\Payment\Repository\PaymentApiRepository;
use Demo\Payment\Repository\StripePaymentApiRepository;
use Demo\User\Repository\UserRepository;
use Demo\User\Repository\UserRepositoryImpl;
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

use Demo\User\Service\UserService;
use Demo\User\Service\UserServiceImpl;
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

**private PHPDoc にも `@param` / `@return` / `@throws` を漏らさず書く** —
戻り値型が型シグネチャから自明 (`: string` 等) であっても **`@return` は省略しない**。
理由:
- 「型は型、ドキュメントは意味」を分けて書く。`@return string 生成された英数字 16 桁` のように
  **値の意味** を書ければ、読み手はメソッドを開かずに用途を理解できる
- 一部だけ省略するルールにすると、レビューで「これは省略してよい / よくない」の判断が要る。
  全部書くルールなら判断不要 (省力)
- PHPStan / Psalm が array shape (`list<X>` / `array<string, Y>`) を読むのは型シグネチャだけでは
  足りないことが多く、結局 `@return` を書く場面が出る。最初から書いておく方が一貫

#### Repository interface の例

```php
// src/Demo/User/Repository/UserRepository.php
namespace Demo\User\Repository;

use App\Model\User\User;

interface UserRepository
{
    /**
     * ID でユーザーを 1 件取得する。
     *
     * @param int $id ユーザー ID
     * @return User|null 該当ユーザー (存在しない場合は null)
     */
    public function findById(int $id): ?User;

    /**
     * status = ACTIVE のユーザーを id 昇順で全件取得する。
     *
     * @return list<User> アクティブユーザー一覧 (該当なしの場合は空配列)
     */
    public function getActiveUserList(): array;

    /**
     * 新規ユーザーを INSERT し、採番された ID を返す。
     *
     * @param string $name 表示名
     * @param string $email Email
     * @return int 採番された ID
     * @throws \Illuminate\Database\QueryException UNIQUE 制約違反など DB エラー時
     */
    public function insert(string $name, string $email): int;
}
```

#### Service interface の例

```php
// src/Demo/Order/Service/OrderService.php
namespace Demo\Order\Service;

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
     * @throws \Demo\Order\Service\PaymentFailedException 決済 API がエラーを返した場合
     */
    public function placeOrder(int $userId, int $amount): Order;
}
```

#### 実装側 (Impl) の例 — public には書かない、private にだけ書く

```php
// src/Demo/Order/Service/OrderServiceImpl.php
namespace Demo\Order\Service;

use App\Enum\User\UserStatus;
use App\Model\User\User;
use Demo\Order\Repository\OrderRepository;
use Demo\User\Repository\UserRepository;
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
     * @return User status = ACTIVE のユーザー
     * @throws \InvalidArgumentException 未登録 または 非アクティブ
     */
    private function mustGetActiveUser(int $userId): User
    {
        $user = $this->userRepository->findById($userId)
            ?? throw new \InvalidArgumentException("user not found: {$userId}");

        if ($user->getStatus() !== UserStatus::ACTIVE) {
            throw new \InvalidArgumentException("user not active: {$userId}");
        }
        return $user;
    }
}
```

#### 補助ルール

- 配列の中身まで書く: `array` ではなく `list<User>` / `array<string, int>` を使う (PHPStan / Psalm が読める)
- nullable は型シグネチャ (`?Foo`) と `@return Foo|null` の両方で明示
- 例外は `@throws \Fully\Qualified\Name` でフル修飾名 (use しなくても IDE が辿れる)
- 「TODO」「FIXME」を PHPDoc に書く場合は **必ず期限か Issue 番号** を付ける
  (例: `@todo 2026-Q3: バッチサイズの動的調整`)
- **実装側の `private` メソッドは Impl ファイル内に PHPDoc** を書く。
  interface に書けない (継承元がない) ので、ここだけは Impl 側が一次情報になる
- **`@return` は型シグネチャから自明でも省略しない** (上記の通り、private でも必須)

---

## 5. 制御フロー (早期 return / match)

### if ネストを深くしない

```php
// NG
public function process(?User $user): Result
{
    if ($user !== null) {
        if ($user->age >= 18) {
            if ($user->getEmail() !== '') {
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
    if ($user->getEmail() === '') {
        throw new InvalidArgumentException('email required');
    }
    // 本体 (ネスト 0)
    return ...;
}
```

### 分岐が多いときは `match` (PHP 8+)

```php
return match ($user->getStatus()) {
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
- **タイムゾーン依存の処理** は **`Carbon::now()` (もしくは `CarbonImmutable::now()`) を使う**。
  以下はすべて禁止:
  - `date()` / `time()` / `mktime()` 等の PHP 標準関数
  - **`now()` ヘルパー** (Laravel グローバルヘルパー)
    `now()` は内部的に `Carbon::now()` を呼ぶラッパーだが、命名規約 (略語禁止 §3) と
    一貫性 (規約 = `Carbon::now()` の 1 つだけ) のため使わない
  - `Carbon::createFromFormat(...)` 等のタイムゾーン未指定生成
    (必ず `Carbon::now()` か、明示的に `setTimezone(...)` する)
  ```php
  // NG
  'created_at' => now(),
  $ts = date('Y-m-d H:i:s');

  // OK
  use Carbon\Carbon;
  'created_at' => Carbon::now(),
  $ts = Carbon::now()->format('Y-m-d H:i:s');
  ```
  - ※ Laravel 13 のひな型は `src/config/app.php` の `'timezone'` が `'UTC'` ハードコード。
    `'timezone' => env('APP_TIMEZONE', 'UTC')` に修正済み。
    新規 `composer create-project` をやり直したときは再度修正が必要

### 外部の指定 (デザイン / 要件 / 法務) を根拠に書くときは、出所を引用で残す

「デザイン指定」「要件どおり」とだけ書かない。**資料名と、そこから読み取れる該当箇所**
(プロトタイプのコード片・ドキュメントのページ名・issue のコメント) を添える。

```php
// NG
// デザイン指定によりステップ 2 へ戻す
// OK
// 受領プロトタイプ (register/step1.html の <a href="./step1.html">) が step1 を指定しているため
```

⚠ 出所を書かないと、あとから誰も裏を取れず、**自分の判断が「指定」として独り歩きする**。
実際に「行き先はステップ 2 (デザイン指定)」と書かれていたものが、どの資料にも無い
自己判断だった、という事故がある。

### 「後で気づけない失敗」を作らない

このリポジトリで繰り返し踏むのは、**壊れているのに緑のまま通る**形。書くときに次を疑う。

- **握り潰した例外** — `catch` して警告ログだけ残し、呼び出し元には «成功» を返していないか。
  特に **PostgreSQL は、トランザクション中に 1 つでも SQL エラーが起きるとその後の文がすべて
  失敗し、`COMMIT` が黙って `ROLLBACK` になる**。例外を握り潰すと
  「成功ログが残っているのに 1 行も書かれていない」が起きる (SQLite では再現しないので、
  sqlite だけのテストは緑のまま通る)
- **トランザクション内の早期 `return`** — 巻き戻すのは «例外» だけ。途中で `return` すると
  **そこまでの書き込みが確定する**。複数件を書く処理は
  «全部の検証 → 全部の書き込み» の順にして、検証中に `return` しない
- **歯止めが黙って全開する書き方** — `grep -vE ""` は「全部許可」、
  `echo "k=$(cmd)"` は `cmd` が失敗しても `set -e` に捕まらない

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

use Demo\User\Service\UserService;
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

use Demo\User\Service\UserService;
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

### 外部へ出る処理は「到達性がアプリ本体と同じ経路」に置く

`batch` と `job` は **別のコンテナ / 本番では別のタスク** として動く。
本番のネットワーク制御 (セキュリティグループ等) は**コンテナ単位**で付くので、
**「app からは繋がるのに batch からは繋がらない」が普通に起きる**。

外部システム (社内 DB / 取引先 API) へ出る定期処理は **キュージョブへ寄せ**、
Console Command は **「ジョブを投げる」で終わらせる**。到達性を「アプリ本体と同じ経路」に揃えるため。

- ⚠ **判定は発火から辿れる範囲すべてで見る。** Command が直接外部を触っていなくても、
  呼んだ Service の先の Repository が外部へ出ていれば同じ問題になる。
  Service の `__construct` を 1 段辿る
- ⚠ **症状が「無言」になりやすい。** 到達できないだけなので例外ではなく «該当 0 件» として
  流れ、warning しか残らない。**「手動だと動くのに定期実行だとスルーされる」の正体はたいていこれ**
  (手動実行は batch を経由しない)
- ⚠ **例外を作るなら理由を `routes/console.php` に書く。**
  「1 件ずつ繋ぐのでキューの打ち切りに収まらない」など、
  次の人が同じ判断をやり直せる形で残す

**外部へ出ない定期実行は Command に実処理を書いてよい。** 自DB・自分のストレージ・
自分のパイプラインだけを触るもの、外部から**データを回収してくるだけ**のものは、
発火と実行を分けてもログと再試行の経路が増えるだけで得るものが無い。
分岐の基準は「**外部へ出るか**」だけで、「定期実行だから」ではない。

### ジョブ側の作法 (重なり / 打ち切り / 可観測性)

定期的に発火して同じ対象を拾うジョブでは、**重なり・打ち切り・無言の 0 件**が必ず問題になる。
書く前に次を決める。

| 決めること | 既定 | 理由 |
|---|---|---|
| `$tries` | 定期発火のジョブは **1** | キュー側でも再試行すると同じ対象を二重に処理し得る。再試行は次の発火に委ねる |
| `$timeout` | **`retry_after` (`config/queue.php` = 90 秒) と `stopwaitsecs` (`docker/php/supervisord-job.conf` = 60 秒) の両方より短く** | 前者を超えると走っている間に予約が切れて別のワーカーが同じ処理を取り、後者を超えるとコンテナ停止時に強制終了される |
| 重なりの防止 | **`Cache::lock`** (`ShouldBeUnique` は使わない) | 下記 |
| 1 回で処理する件数 | **上限を切る** | 下記 |
| ログ | **0 件でも残す** (`Log::channel('stdout')`) | 下記 |

**`ShouldBeUnique` は使わない。**
ロックが取れないと dispatch を「例外も出さず、何も積まず」に打ち切るため
(`PendingDispatch::shouldDispatch`)、**投げた側から取りこぼしを検知できない**。
打ち切りでワーカーが強制終了してロックだけ残ると、
「発火しているのにジョブが 1 度も走らず、0 件のログすら出ない」周期が生まれる。
`Cache::lock` を自分で取り、**取れなかったことをログに残す**。

**ロックの寿命は「打ち切り (`$timeout`) より少し長く、発火の間隔より短く」。**
強制終了で release が呼ばれなくても次の発火までに自然に切れる必要がある
(切れないと「永久に重なり扱いで何もしない」になる)。

**1 回で通し切れる量に上限を切る。**
Service が渡された件数を全部処理し終えてから返す作りだと、打ち切りに達した回は
後片付け (処理済み行の削除など) に 1 度も到達せず **1 件も進まない**。
次回も同じ先頭を取り直すので、永久に進まなくなる。

**0 件でもログを残す。**
「発火はしているが 1 件も進んでいない」を外から見分けるため。
何も出さないと、正常な «対象なし» と «壊れていて 0 件» が区別できない。

**秘密情報をペイロードに載せるなら `ShouldBeEncrypted`。**
`jobs.payload` にも、失敗後に残る `failed_jobs.payload` にも **平文で書かれる**。
ジョブ内で例外を握り潰しても防げない (積んだ時点で書かれている)。

```php
final class SyncExternalStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 定期発火なので再試行は次の発火に委ねる。 */
    public int $tries = 1;

    /** retry_after(90) と stopwaitsecs(60) の両方より短くする。 */
    public int $timeout = 50;

    public function handle(ExternalStatusService $service): void
    {
        // 打ち切り(50 秒)より少し長く、発火間隔(5 分)より短い寿命にする。
        $lock = Cache::lock('sync-external-status', 90);
        if (! $lock->get()) {
            Log::channel('stdout')->info('前回の実行と重なったため見送りました');
            return;
        }

        try {
            // 1 回で通し切れる量に上限を切る (打ち切りに達すると 1 件も進まなくなるため)。
            $count = $service->syncPendingList(limit: Limit::SYNC_BATCH_SIZE);
            // 0 件でも残す (「発火しているのに進んでいない」を外から見分けるため)。
            Log::channel('stdout')->info("外部状態を同期しました: {$count} 件");
        } finally {
            $lock->release();
        }
    }
}
```

### 発火側 (Schedule) の作法

- **`Schedule::command(...)` の出力を捨てない。**
  `schedule:run` は子プロセスの出力を自分のパイプへ受けるので、
  **コマンドが出した内容も、その中で残した Log も、既定ではどこにも残らない**。
  「手動実行だと見えるのに、定期実行だと何も残らない」の正体はこれ。
  - ⚠ **`appendOutputTo('/dev/stdout')` では直らない。**
    それはスケジューラのパイプを指すだけで、コンテナの標準出力ではない
  - このリポジトリは cron daemon が `schedule:run` の出力を
    `storage/logs/scheduler-cron.log` に受け、`tail -F` で stdout に流す構成にしてある (上記「環境構成」)。
    **この経路を変える (出力先の変更 / cron からの切り離し / `schedule:work` への差し替え) ときは、
    「コマンドの出力が本当にコンテナのログまで届くか」を実機で確認してから入れる**
- **`withoutOverlapping()` はジョブを投げるところにしか効かない。**
  ジョブへ寄せた処理が重ならないことは、ジョブ側のロックが担う (上記)
- **dispatch の失敗を握り潰さない。**
  投げられないのはキューの保管先 (自DB) に触れていないということなので、
  ログを残して `FAILURE` を返す。黙って成功扱いにすると「発火しているのに何も起きない」になる

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
| 配置 | `src/app/Http/Request/<Domain>/<SubDomain>/<Name>Request.php` |
| namespace | `App\Http\Request\<Domain>\<SubDomain>` |
| クラス名 | `~Request` サフィックス (`RegisterRequest`, `UpdateProfileRequest`) |

> ⚠️ ディレクトリ / namespace は **単数形 `Request`** (Laravel 標準の `Requests` 複数形は採らない)。
> §2 「単数形優先」と一致させる (`Resource` / `ViewModel` / `Model` / `Enum` も同様)。

#### フィールド名は `public const string` で定数化

マジック文字列禁止 (§1)。PHP 8.3+ の型付き定数で宣言する。
rules() / Controller / Service すべて `SomeRequest::FIELD_NAME` で参照する。

```php
<?php

namespace App\Http\Request\User\Examination;

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

- **入力検証は必ず FormRequest に置く。**
  Controller で `$request->validate()` / `Validator::make()` を直書きしない。
  直書きすると同じ入力の規則が画面ごとに散り、二重管理になる
- **Service は「検証済みの値を受け取る前提」で書く。**
  入力形式の検証 (必須か・書式が正しいか) は FormRequest、
  **「その操作をしてよいか」という業務ルールの検査は Service** — この線で分ける
- フィールド名は **`public const string`** で定数化 (型付き定数、大文字スネークケース)
- `rules()` で **`self::FIELD_NAME`** を使う (キーに文字列直書き禁止)
- Controller でも **`$request->validated(SomeRequest::FIELD_NAME)`** で参照
- `authorize()` は **必ず明示** (Laravel デフォルトに任せない)
- `rules()` に PHPDoc で戻り値型を書く (`array<string, ValidationRule|array<mixed>|string>`)

#### 検証ルールを足したら `lang/ja/validation.php` も見る

`src/lang/ja/validation.php` が **書き忘れた欄の受け皿**。
ここに無いルールを使うと、Laravel 同梱の英語
(`The email field must be a valid email address.`) がそのまま利用者に出る。

- 欄ごとの言い回しは **FormRequest の `messages()`** に寄せる (画面の文脈が要るため)
- `lang/ja/validation.php` は **「どの画面でも日本語になること」を保証する土台**
- 収録するのは「アプリで実際に使っているルール」＋取りこぼしが出やすい基本ルールまで。
  使っていないルールまで並べても保守対象が増えるだけ

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

`Auth` オブジェクト / Eloquent モデル / Service の結果型 / **enum の配列** / **設定値の配列** など、
View で表示するものは何であれ **Blade に直接渡さない**。すべて ViewModel に **詰め替えてから** 渡す。

理由:
- View は **必要なフィールドだけ** 受け取り、不必要な依存型を持たない (Blade 側のロジック軽減)
- `$user->name()` (メソッド呼び出し) より `$vm->userName` (プロパティ) のほうが型と意味が読みやすい
- View 用整形ロジック (日付フォーマット、表示用文字列など) を後から追加しやすい
- enum や設定値も「View が必要とするデータ」なので、ViewModel に集約することで
  「Blade に渡すデータの単一の入口」が `$vm` だけになり、将来の追加もすべて ViewModel に集約できる

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
- Blade に渡すのは **必ず ViewModel 経由**。Auth / Eloquent モデル / Service の戻り値 / **enum の配列 / 単純なリストや設定値** も含めて Blade に直渡ししない
- `fromXxx(...)` / `build()` 等の **static factory** で組み立てる (コンストラクタは値の代入のみ)
- 値プロパティ中心、メソッドは表示整形が必要なときだけ最小限に
- React 等の SPA に渡す場合は ViewModel ではなく **Resource (Arrayable)** を使う

### Blade

`src/resources/views/`。**利用者から見える面は、1 箇所の漏れがそのまま XSS になる。**

- **`{!! !!}` (エスケープなし出力) を使わない。** どうしても要るなら、
  その変数が通る **すべての** 分岐で `e()` を通していることが読み取れる形にし、
  **値の出所 (利用者の入力が混ざらないか) をコメントに残す**
- **view にロジックを置かない。** `@php` ブロックでの分岐・文章の組み立て・
  クロージャ定義は違反。Controller / Service へ寄せて、view は受け取った値を出すだけにする
  (§7 と同じ考え方。Blade に渡すのは ViewModel だけ)
- **同じ記述を分岐ごとに写経しない。** `@switch` / `@if` の各分岐に同じブロックが
  並んでいたら、分岐の外に出せないかを見る
- **外部サイトへのリンクには `rel="noopener"`。** `target="_blank"` だけだと、
  遷移先から `window.opener` 経由でこのページを操作できる
- **ディレクティブを隙間なく並べない。** `@endif@endforeach` のように続けて書くと
  **2 つ目が認識されずビューが壊れる**。改行を挟むか、三項に寄せる
- `@foreach` の `@empty` 忘れ、`@csrf` の抜け、`@include` の暗黙の変数受け渡しに注意する

### Controller (Request × Resource × Service)

Controller の責務は **3 ステップだけ**:

```php
use App\Http\Request\User\Examination\RegisterRequest;
use App\Http\Resource\User\Examination\RegisterResultResource;
use Demo\Examination\Service\ExaminationReservationService;

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

`src/database/migrations/` 以下にファイルを **追加 / 変更した PR** は、本文の
**「実装の概要」と「チェック状況」の中に** 次を含める。レビューエージェント
[`.claude/agents/laravel-migration-pr-checker.md`](.claude/agents/laravel-migration-pr-checker.md)
がこの規約に基づいてチェックする。

> ⚠️ **DB 用の独立見出しを新設しない。** 「実装の概要」「チェック状況」という通常の枠に収める。
> 専用の見出しを増やすと、マイグレーションの無い PR で空欄の枠が残り、テンプレートが形骸化する
> (レビューエージェントも「未記入」と誤検出しやすくなる)。

### 9-1. 実装の概要に書くこと (必須)

- 追加 / 変更したマイグレーションファイルの**パス**
- **なぜ必要か** を端的に

```markdown
- `src/database/migrations/2026_05_24_220001_create_kanban_card_table.php`
  カンバンのカードを保存する。lane + position で表示順を持つため複合 index を張る。
```

⚠ **CREATE TABLE 等の DDL 全文は転記しない。** マイグレーションファイルを見れば分かる。
書くのは **新規 index / 制約など、レビュー上重要な変更点の要点だけ**。
全文を貼ると、本当に見てほしい 1 行が埋まる。

### 9-2. チェック状況に書くこと (必須)

- `migrate:fresh` 等での作成確認
- **影響する主要 SELECT の `EXPLAIN ANALYZE` 出力 (全文)**。
  新規カラム / 索引を `WHERE` / `ORDER BY` / `JOIN` に使うクエリが対象。
  **対象の実行時クエリ (アプリが実際に発行する SQL) も併記**し、
  **Index Scan が効き Seq Scan に落ちていない**ことを一言添える

```bash
docker compose exec -T db psql -U laravel -d laravel \
  -c "EXPLAIN ANALYZE SELECT * FROM kanban_card WHERE user_id = 1 AND deleted_at IS NULL ORDER BY lane, position;"
```

#### 例外: 行数が設計で頭打ちになるテーブル

設定テーブルのように **行数が enum のケース数などで決まり、運用で増えない** テーブルは、
プランナが Seq Scan を選ぶのが正しい。この場合は Seq Scan のままでよいが、
**実測の行数と `Buffers`** を添えて「なぜ増えないのか」を書く。

⚠ 例外はこの条件に限る。**「今はデータが少ないから」は理由にならない**
(件数が伸びる前提のテーブルで Seq Scan を見逃さないため)。

### 9-3. 特記事項があるときだけ書くこと

`down()` の可逆性と既存データへの影響は **必須記載としない**。
毎回書かせると定型文になって読まれなくなるので、**次に当たるときだけ**書く。

- `DROP COLUMN` で消えるデータがある
- 既存行のバックフィルが要る (NULL 埋め / DEFAULT 値)
- ロックが長い (`ALTER TABLE ... ADD COLUMN` は PostgreSQL 11+ ならメタデータのみだが、
  **定数でない DEFAULT** を付けると全行書き換えになる)
- 大規模テーブルで段階的 migration に分けるべき
  (NULL カラム追加 → バックフィル → NOT NULL 化 を別 PR に分割)

### 9-4. enum の値を変える差分

`case ... = '...'` の **値** が変わる差分は、**必ずデータ移行と対で**出す (§1)。
既存行・CHECK 制約・外部との契約がずれる。ケース名 (PHP 識別子) だけの変更は安全。

---

## 10. 外部システム由来の名前 (自分たちで名前を決められないとき)

§3 は「名前だけで意味が通ること」を求める。だが **外部システムのスキーマ**
(社内の別システムの DB、取引先の API) の名前はこちらでは改名できない。
`web` / `req` / `sp1` / `fdel` のように、名前から意味が読めない列が普通にある。

**名前を変えられないぶん、意味をコードに残す。**

| 対象 | 扱い |
|---|---|
| テーブル名・列名 | **生名のまま使う** (別名を作らない)。先方の資料・実データと突き合わせるときに「どちらの表記か」を考えずに済ませるため (§1 の外部契約値と同じ理由)。そのかわり、**出てくる箇所に業務上の意味を 1 行**添える |
| 区分値 (`'0'` / `'1'` 等のフラグ・ステータス値) | **直書き禁止**。1 つの Repository で完結するなら `private const`、複数の Repository で使う / アプリの分岐になるなら backed enum にする |
| 識別子 (`custid` 等) | 生名のまま Model・自DBの列まで持ってよい (外部を引くキーそのもののため)。Model のアクセサと**マイグレーションの列コメント**に「どの外部システムの何か」を書く |
| 外部の値を Repository の外へ渡すとき | **生値を渡さない**。境界 (Repository) で enum / bool / Model に変換し、Service から先は名前で意味が分かる形にする |

定数・enum には「**どの列の何を表す値か**」を docblock で書く。

```php
/** dbab.web が「申込完了」を表す値。 */
private const string WEB_COMPLETED = '2';
```

- **自DBの列名で外部の語彙を真似しない** (`sp1` のような列を新設しない)。例外は上表の識別子のみ
- **外部の語彙を自分たちのコードの名前として広げない。**
  `$web` / `$flg` / `$stat` のような、値の意味を知っている人にしか読めない名前は、
  業務上の意味を表す名前に置き換える (`$isApplicationCompleted` 等)

⚠ §6 の「不要な What コメントは書かない」と矛盾しない。
**名前を自分たちで決められる箇所は改名で直し、決められない外部由来の語彙だけ
コメント・定数・enum で意味を補う**、という切り分け。

⚠ **外部システムの DB への書き込みは既定で禁止**にして、必要になったところだけ
「合意済みの例外」として **理由と合意の出所を添えて** 残す (§6)。
読み取り専用が既定でないと、どこから何が書かれるか分からなくなる。

---

## 11. フロントエンド (`src/resources/js/`)

`admin/` は React + TypeScript の SPA、ルート直下 (`app.js` / `kanban.js` / `user.js`) は
htmx + Alpine のユーザー面。**規約の一次情報はここ**で、
`.claude/agents/frontend-reviewer.md` がこれに照らして違反を検出する。

- **§3 の命名規則はそのまま効く** — 名前だけで意味が通ること、bool は `is~` / `has~` / `can~`、
  略語で意味を潰さない。ハンドラは `handle~` / `on~`。
  複数件は複数形でよい (PHP 側の `~List` サフィックスまでは求めない)
- **API レスポンスのキーをそのまま画面のロジックへ広げない** — サーバー側の名前
  (特に外部システム由来のもの) は表示のために持ち回ってよいが、
  **分岐に使う値は名前付き定数に束ねる** (§10 と同じ考え方)。
  ステータス文字列 (`'in_progress'` 等) を直書きで比較しない
- **ガード節でネストを平坦化する** — 3 段以上のネスト・長い `else` 連鎖は
  早期 return / `switch` / マップに置き換える (§5 と同じ)
- **三項演算子はネストしない** — `a ? b : c ? d : e` は禁止。
  JSX の込み入った条件描画は変数抽出・コンポーネント分割へ。
  単純な二択 (`cond ? A : B`) や `cond && <X/>` は可
- **マジックナンバー / 文字列を使わない** — ページサイズ・上限・ミリ秒・API パス・
  権限キー・ステータス値は名前付き定数へ (§1 と同じ)
- **`.map()` の `key` は安定した id** — `key` 無し / `key={index}` は不可
  (並び替え・追加削除で表示が壊れる)
- **共通部品に倣う** — fetch は `admin/api.ts` を通す。独自の fetch ラッパを作らない
- **副作用は必ず後始末する** — `useEffect` の中で張った `setInterval` /
  `addEventListener` / `AbortController` は cleanup で外す

⚠ **管理画面 SPA は CI で lint されない** (`vite build` は未定義変数を通す)。
宣言漏れ・未使用は実機まで気づけないため、
**レビューで名前・未使用・宣言の整合を見る比重が PHP 側より大きい**。

---

## 12. テストの作法

「テストが緑」を「壊れていない」と読み替えられる状態を保つ。
以下はいずれも **緑のまま壊れた** 形。

### 一部だけ回して判断しない

`--filter` で該当テストだけ回すと、**そのファイルからは見えない壊れ方**を取りこぼす。

- interface にメソッドを 1 つ足すと、**匿名クラスで作ったテストダブル**が実装漏れで落ちる。
  Mockery は素通りするので、周辺のスイートは緑のまま **CI だけ落ちる**
- Service に `Log::` を足すと、コンテナを起こさない Unit テストが
  `Target class [log] does not exist` で落ちる

**最後は必ず全量 (`php artisan test`) を回す。**

### ループの中で立てた Mockery の期待は 1 件目しか使われない

```php
// NG: 3 回とも 1 件目の値が返る
foreach ($caseList as $case) {
    $repository->shouldReceive('findById')->andReturn($case);
}

// OK: 呼ばれた順に返す
$repository->shouldReceive('findById')->andReturnValues($caseList);
```

状態ごとに戻り値を変えたいテストがこの形になっていると、
**1 パターンしか検証していないのに「全状態を見た」ように読める**。

### 環境変数に依存する分岐は、テストの中で明示的に値を入れる

CI は `.env.example` をコピーするだけなので、
**空の値のまま分岐が一度も評価されず緑**になる。`config([...])` で入れてから確かめる。

### SQLite だけで確かめない

- **マイグレーションは本番と同じ RDBMS (PostgreSQL) でも流す。**
  識別子の 63 文字上限による «静かな切り詰め → 索引名の衝突» のような、
  PostgreSQL でしか落ちない書き方が緑のまま入る
- **握り潰した SQL エラーの挙動** (§6) も SQLite では再現しない

### ミューテーションで「テストが本当に見ているか」を確かめる

直したところを **わざと壊して、テストが赤くなること** を確認する。
赤くならなければ、そのテストはその挙動を見ていない。

確認用のスクリプトを書くなら、**スクリプト自身が
「変異が当たったか」「構文が壊れていないか」「未コミットの残骸が無いか」を検査する**
形にする (嘘の「検証できた」を防ぐ)。戻すときは `git checkout HEAD -- <path>` で
確実に戻す (手で戻すと修正前のコードを走らせたまま «確認できた» になる)。

---

## 13. 外部に出す文章での書き方 (PR / コミット / issue)

- **コミット / PR に「Generated with ...」等の署名を付けない。**
  `Co-Authored-By:` トレーラーも付けない
- **実装やレビューの補助に使った個別ツールの名前を書かない。**
  レビューの出所に触れる必要があるときは「**セルフレビュー**」
  「追加のセルフレビューで検出」等の一般表現にする
  - リポジトリに入っているレビュー用サブエージェント (`.claude/agents/` 配下) は
    共有物なので、名前を書いてよい
- **検出した内容・根拠・修正は具体的に書く。**
  読者に必要なのは「何で見つけたか」ではなく「**何が問題でどう直したか**」
- コミットメッセージは **Conventional Commits** (`type(scope): 要約`)、本文は日本語

理由: どのツールで見つけたかは成果物の品質と無関係で、読み手にとってノイズになる。

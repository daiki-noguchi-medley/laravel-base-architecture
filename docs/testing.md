# テスト

このプロジェクトのテスト実装と実行方法。

## 概要

| 種別 | フレームワーク | 場所 | 役割 |
|---|---|---|---|
| **Unit Test** | PHPUnit (Laravel 同梱) | `src/tests/Unit/` | 単一クラス (Service / Repository / ValueObject) のロジックを検証 |
| **Feature Test** | PHPUnit + Laravel `TestCase` | `src/tests/Feature/` | HTTP リクエスト → レスポンスを E2E で検証 (Controller / Route / View) |

Laravel 13 のひな型で `tests/` ディレクトリと `phpunit.xml` は既にセットアップ済み。

---

## 実行方法

```bash
# 全テスト
docker compose exec app php artisan test

# Unit / Feature を絞り込み
docker compose exec app php artisan test --testsuite=Unit
docker compose exec app php artisan test --testsuite=Feature

# 特定テストクラス
docker compose exec app php artisan test --filter=UserAuthServiceTest

# 並列実行 (Laravel 11+)
docker compose exec app php artisan test --parallel
```

実行結果に色がつかない場合は `--colors=always` を追加。

---

## DB を伴うテスト

### 推奨: `RefreshDatabase` トレイト

テスト毎に migrate をリセットして、テスト後 transaction を rollback。

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials(): void
    {
        // テスト用 user を seed
        DB::table('user')->insert([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'alice@example.com',
            'password' => 'secret',
        ])->assertRedirect('/dashboard');
    }
}
```

### テスト用 DB 設定

`phpunit.xml` で `DB_DATABASE=:memory:` (SQLite in-memory) を指定するのが速くて楽。
PostgreSQL を使う場合はテスト用 DB を別途用意する。

```xml
<!-- phpunit.xml -->
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE"   value=":memory:"/>
```

---

## レイヤー別のテスト戦略

### Repository (interface + Impl)

DB に直接触る層なので **実 DB を使った Feature Test** を推奨。
モックすると DB の生挙動 (UNIQUE 制約、cascade 等) を捕まえられない。

```php
class UserRepositoryImplTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $userRepository = app(UserRepository::class);
        $this->assertNull($userRepository->findByEmail('missing@example.com'));
    }

    public function test_insert_returns_generated_id(): void
    {
        $userRepository = app(UserRepository::class);
        $id = $userRepository->insert('Alice', 'alice@example.com', Hash::make('x'));
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }
}
```

#### 高速テストが必要なら fake 実装

`Demo/<ドメイン>/Repository/Memory/<X>RepositoryImpl.php` のようなインメモリ実装を作って、
Service のテストではこれを inject する選択肢もある (本筋ではない、必要になってから)。

### Service (interface + Impl)

**Repository を mock** して、Service のロジックを単体テスト。

```php
use PHPUnit\Framework\TestCase;
use Demo\User\Repository\UserRepository;
use Demo\User\Service\UserAuthServiceImpl;

class UserAuthServiceImplTest extends TestCase
{
    public function test_register_hashes_password_before_insert(): void
    {
        $userRepository = $this->createMock(UserRepository::class);

        // insert が呼ばれるとき、3 つ目の引数 (hashedPassword) は
        // password_verify で元の平文と一致すること
        $userRepository->expects($this->once())
            ->method('insert')
            ->with(
                'Alice',
                'alice@example.com',
                $this->callback(fn (string $hash) => password_verify('secret', $hash)),
            )
            ->willReturn(42);

        $service = new UserAuthServiceImpl($userRepository);

        $this->assertSame(42, $service->register('Alice', 'alice@example.com', 'secret'));
    }
}
```

> **規約**: mock は **interface に対して** 行う (`createMock(UserRepository::class)`)。
> Impl を mock しない (`createMock(UserRepositoryImpl::class)` は NG)。

### Controller (Feature Test 推奨)

HTTP リクエスト → レスポンスを E2E で確認。
Auth ファサードを `actingAs($user, 'guard名')` で setup する。

```php
class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_to_login_when_unauthenticated(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_dashboard_shows_user_name_when_authenticated(): void
    {
        // user テーブルに 1 件挿入してから UserAuth で actingAs
        $id = DB::table('user')->insertGetId([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = app(UserRepository::class)->findById($id);
        $this->assertNotNull($user);

        $this->actingAs(new \App\Auth\User\UserAuth($user), 'user')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Alice');
    }
}
```

### ValueObject

純粋な PHP オブジェクト、依存なし。Unit Test で値の **生成 / 等価性 / バリデーション** を確認。

```php
class EmailTest extends TestCase
{
    public function test_valid_email_is_accepted(): void
    {
        $email = new Email('alice@example.com');
        $this->assertSame('alice@example.com', $email->value);
    }

    public function test_invalid_email_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('not-an-email');
    }

    public function test_equals_by_value(): void
    {
        $a = new Email('alice@example.com');
        $b = new Email('alice@example.com');
        $this->assertTrue($a->equals($b));
    }
}
```

### Job / Console Command (Bus::fake / Queue::fake)

Job が dispatch されることだけ確認したいなら `Bus::fake()` / `Queue::fake()`。

```php
public function test_register_dispatches_welcome_mail_job(): void
{
    Bus::fake();

    $this->post('/register', [/* ... */])->assertRedirect();

    Bus::assertDispatched(SendWelcomeMailJob::class, fn ($job) => $job->userId === 1);
}
```

Job の `handle()` 自体のテストは、Service と同じく Repository を mock して Unit Test。

---

## テストの命名と規約

| 項目 | 規約 |
|---|---|
| テストクラス名 | `<対象クラス>Test` (例: `UserAuthServiceImplTest`) |
| 配置 | `src/tests/{Unit,Feature}/<対象の名前空間に合わせて>/` |
| メソッド名 | `test_<何を検証するか>` (日本語 OK、英語でも可) |
| メソッド内容 | **1 メソッドで 1 つだけ** 検証 (assert 数は少なく) |
| データセットアップ | `setUp()` で共通、テスト固有は各メソッド内 |
| DB | `RefreshDatabase` で毎回クリア (Feature) |
| Mock | **interface に対して** mock (`createMock(UserRepository::class)`) |

### メソッド名の例

```php
// OK (何を確認しているか伝わる)
public function test_register_hashes_password_before_insert(): void
public function test_login_redirects_to_dashboard_on_success(): void
public function test_dashboard_requires_authentication(): void

// NG (何を確認しているか不明)
public function test_register(): void
public function test_dashboard(): void
public function test_1(): void
```

---

## CI で走らせる

`.github/workflows/ci.yml` が PR と `main` への push で
`php artisan test` を走らせる。詳細は [`infra/github-actions.md`](./infra/github-actions.md)。

**CI が見ているのはテストだけではない**:

- **PostgreSQL で `migrate:fresh`** — マイグレーションを SQLite だけで確かめると、
  識別子の 63 文字上限による «静かな切り詰め → 索引名の衝突» のように
  PostgreSQL でしか落ちない書き方が緑のまま入る
- **Vite ビルド** — `@vite()` が manifest を参照するので、テストの前にビルドが要る。
  管理画面 SPA (React) がビルドを通ること自体もここで見ている
  - ⚠ `vite build` は **未定義変数を通す**。ビルドが緑でも実機で壊れることがある (CLAUDE.md §11)

### 緑のまま壊れる形に注意する

CLAUDE.md §12 に一覧がある。特に CI と関係が深いのは次の 2 つ。

- **`.env.example` をコピーしているだけ**なので、env に依存する分岐は
  «空の値のまま一度も評価されず» 緑になる。`config([...])` でテスト内に明示的に入れる
- **`--filter` で一部だけ回して判断しない**。interface にメソッドを足したときの
  «匿名クラスのテストダブルの実装漏れ» は、周辺スイートが緑のまま CI だけ落ちる

---

## 既存テスト

Laravel 13 ひな型の例 (`tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php`) が
そのまま入っているが、内容は空に近い。実装が進んだら本物のテストを追加していく。

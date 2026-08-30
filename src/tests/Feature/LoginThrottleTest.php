<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * ログイン POST の回数制限 (routes/web.php / routes/admin.php の throttle)。
 *
 * 認証そのものが正しくても、回数制限が無いと総当たり・使い回しパスワードの試行を止められない。
 * throttle を外した瞬間にこのテストが落ちることが、制限が「効いている」ことの担保になる。
 */
final class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** ユーザー面のログインは 1 分あたり 10 回まで (routes/web.php)。 */
    private const int USER_LIMIT_PER_MINUTE = 10;

    /** 管理画面のログインは 1 分あたり 5 回まで (routes/admin.php)。 */
    private const int ADMIN_LIMIT_PER_MINUTE = 5;

    public function test_user_login_returns_429_after_limit(): void
    {
        for ($i = 0; $i < self::USER_LIMIT_PER_MINUTE; $i++) {
            $this->postLoginAttempt('/login', 'user@example.com')
                ->assertStatus(Response::HTTP_FOUND);
        }

        $this->postLoginAttempt('/login', 'user@example.com')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function test_admin_login_returns_429_after_limit(): void
    {
        for ($i = 0; $i < self::ADMIN_LIMIT_PER_MINUTE; $i++) {
            $this->postLoginAttempt('/admin/login', 'admin@example.com')
                ->assertStatus(Response::HTTP_FOUND);
        }

        $this->postLoginAttempt('/admin/login', 'admin@example.com')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * 管理画面のほうが厳しいこと自体を固定する。
     * 「取られたときの被害が大きいぶん厳しくする」という意図が、
     * どちらかの値を触ったときに崩れていないかをここで見る。
     */
    public function test_admin_limit_is_stricter_than_user_limit(): void
    {
        $this->assertLessThan(self::USER_LIMIT_PER_MINUTE, self::ADMIN_LIMIT_PER_MINUTE);
    }

    /**
     * 存在しない資格情報でログインを試みる。
     * 成功させると guest ミドルウェアで挙動が変わるので、必ず失敗する組み合わせを送る。
     *
     * @param  string  $path ログイン POST のパス
     * @param  string  $email 送る e-mail (DB には登録しない)
     * @return TestResponse ログイン POST の応答
     */
    private function postLoginAttempt(string $path, string $email): TestResponse
    {
        return $this->post($path, [
            'email'    => $email,
            'password' => 'wrong-password',
        ]);
    }
}

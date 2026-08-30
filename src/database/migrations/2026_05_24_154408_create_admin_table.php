<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者 (管理画面のログイン主体)。user とは別テーブル・別ガード。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin', function (Blueprint $table) {
            $table->id()->comment('管理者 ID');
            $table->string('name')->comment('表示名');
            $table->string('email')->unique()->comment('ログインに使うメールアドレス');
            $table->string('password')->comment('パスワードのハッシュ (bcrypt)');
            $table->rememberToken()->comment('ログイン状態を保持するためのトークン');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一般ユーザー (ユーザー画面のログイン主体)。
 * 認証は自前の UserAuth / UserAuthProvider が引く (Eloquent は使わない)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->id()->comment('ユーザー ID');
            $table->string('name')->comment('表示名');
            $table->string('email')->unique()->comment('ログインに使うメールアドレス');
            $table->string('password')->comment('パスワードのハッシュ (bcrypt)');
            $table->rememberToken()->comment('ログイン状態を保持するためのトークン');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};

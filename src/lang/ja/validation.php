<?php

declare(strict_types=1);

/**
 * 検証エラーの文言 (日本語)。
 *
 * ⚠ これが無いと Laravel 同梱の英語がそのまま利用者に出る
 *   (`The email field must be a valid email address.`)。
 *   Laravel 11+ は lang/ を同梱しないので、置くのは自分たちの責任。
 * ⚠ FormRequest の messages() で個別に書いた文言はこちらより優先される。
 *   ここは「書き忘れた欄」の受け皿で、どの画面でも日本語になることを保証するために置く。
 *   欄ごとの言い回しは FormRequest 側に寄せる (画面の文脈が必要なため)。
 * ⚠ 収録するのは「アプリで実際に使っているルール」＋取りこぼしが出やすい基本ルールまで。
 *   使っていないルールまで並べても保守の対象が増えるだけなので広げない。
 *
 * @see CLAUDE.md §8「検証ルールを足したら lang/ja/validation.php も見る」
 */
return [
    'accepted'             => ':attributeに同意してください。',
    'after'                => ':attributeは:dateより後の日付を入力してください。',
    'after_or_equal'       => ':attributeは:date以降の日付を入力してください。',
    'array'                => ':attributeの形式が正しくありません。',
    'before'               => ':attributeは:dateより前の日付を入力してください。',
    'before_or_equal'      => ':attributeは:date以前の日付を入力してください。',
    'between'              => [
        'array'   => ':attributeは:min個から:max個まで選択してください。',
        'file'    => ':attributeは:min KBから:max KBまでのファイルを選択してください。',
        'numeric' => ':attributeは:minから:maxまでの数値を入力してください。',
        'string'  => ':attributeは:min文字から:max文字までで入力してください。',
    ],
    'boolean'              => ':attributeの形式が正しくありません。',
    'confirmed'            => ':attributeが一致しません。',
    'current_password'     => 'パスワードが正しくありません。',
    'date'                 => ':attributeは日付として正しい値を入力してください。',
    'date_format'          => ':attributeの形式が正しくありません。',
    'different'            => ':attributeと:otherには違う値を入力してください。',
    'digits'               => ':attributeは:digits桁で入力してください。',
    'digits_between'       => ':attributeは:min桁から:max桁で入力してください。',
    'distinct'             => ':attributeに同じ値が重複しています。',
    'email'                => ':attributeはメールアドレスの形式で入力してください。',
    'enum'                 => '選択された:attributeは正しくありません。',
    'exists'               => '選択された:attributeは登録されていません。',
    'file'                 => ':attributeはファイルを選択してください。',
    'filled'               => ':attributeを入力してください。',
    'image'                => ':attributeは画像ファイルを選択してください。',
    'in'                   => '選択された:attributeは正しくありません。',
    'integer'              => ':attributeは整数で入力してください。',
    'max'                  => [
        'array'   => ':attributeは:max個までしか選択できません。',
        'file'    => ':attributeは:max KB以下のファイルを選択してください。',
        'numeric' => ':attributeは:max以下の数値を入力してください。',
        'string'  => ':attributeは:max文字以下で入力してください。',
    ],
    'mimes'                => ':attributeは:valuesのファイルを選択してください。',
    'mimetypes'            => ':attributeは:valuesのファイルを選択してください。',
    'min'                  => [
        'array'   => ':attributeは:min個以上選択してください。',
        'file'    => ':attributeは:min KB以上のファイルを選択してください。',
        'numeric' => ':attributeは:min以上の数値を入力してください。',
        'string'  => ':attributeは:min文字以上で入力してください。',
    ],
    'not_in'               => '選択された:attributeは正しくありません。',
    'not_regex'            => ':attributeの形式が正しくありません。',
    'numeric'              => ':attributeは数値で入力してください。',
    'present'              => ':attributeが指定されていません。',
    'prohibited'           => ':attributeは入力できません。',
    'prohibits'            => ':attributeを指定した場合、:otherは指定できません。',
    'regex'                => ':attributeの形式が正しくありません。',
    'required'             => ':attributeを入力してください。',
    'required_if'          => ':otherが:valueの場合、:attributeを入力してください。',
    'required_if_accepted' => ':otherを有効にする場合、:attributeを入力してください。',
    'required_with'        => ':valuesを入力した場合、:attributeも入力してください。',
    'same'                 => ':attributeと:otherが一致しません。',
    'size'                 => [
        'array'   => ':attributeは:size個選択してください。',
        'file'    => ':attributeは:size KBのファイルを選択してください。',
        'numeric' => ':attributeは:sizeを入力してください。',
        'string'  => ':attributeは:size文字で入力してください。',
    ],
    'string'               => ':attributeは文字で入力してください。',
    'unique'               => 'その:attributeはすでに使われています。',
    'uploaded'             => ':attributeをアップロードできませんでした。ファイルの大きさをご確認ください。',
    'url'                  => ':attributeはURLの形式で入力してください。',
    'uuid'                 => ':attributeの形式が正しくありません。',

    /**
     * 欄ごと・ルールごとの個別文言。
     * ルール共通の文言では意味が通らないものだけ置く (基本は FormRequest の messages() へ)。
     */
    'custom' => [],

    /**
     * 欄の名前。文言に :attribute として差し込む。
     *
     * ⚠ ここに無い欄は**キー名 (英語) がそのまま出る**。
     *   「lastNameは文字で入力してください」のような文言が利用者に出るので、
     *   FormRequest に欄を足したらここにも足す。
     * ⚠ 配列で届く欄は `*` を使ったワイルドカードで書く (`children.*.name` 等)。
     *   素のキー名では当たらない。
     * ⚠ 管理画面の欄も書く。管理者は社内の人でも、内部のキー名が出るのは
     *   「何を直せばよいか」が伝わらない点で利用者向けと同じ。
     */
    'attributes' => [
        // --- 認証 (ユーザー / 管理者 共通) ---
        'email'    => 'メールアドレス',
        'password' => 'パスワード',
        'remember' => 'ログイン状態の保持',

        // --- ユーザー管理 (管理画面) ---
        'name' => '名前',

        // --- カンバン ---
        'title'    => 'タイトル',
        'body'     => '本文',
        'lane'     => 'レーン',
        'position' => '並び順',
    ],
];

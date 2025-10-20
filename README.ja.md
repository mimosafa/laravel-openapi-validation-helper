# Laravel OpenAPI Validation Helper

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mimosafa/laravel-openapi-validation-helper.svg?style=flat-square)](https://packagist.org/packages/mimosafa/laravel-openapi-validation-helper)
[![Total Downloads](https://img.shields.io/packagist/dt/mimosafa/laravel-openapi-validation-helper.svg?style=flat-square)](https://packagist.org/packages/mimosafa/laravel-openapi-validation-helper)

このライブラリは、Laravelの機能テストにおいて、OpenAPI 3.0スキーマに基づいたHTTPリクエストとレスポンスのバリデーションを透過的に行うためのヘルパーを提供します。テスト実行時に自動でバリデーションが走り、APIの仕様と実装の乖離を常にチェックできます。

## 特徴

- **透過的なバリデーション**: `RequestHandled` イベントを利用し、既存のテストコードをほぼ変更することなくバリデーションを導入できます。
- **柔軟な制御**: テストごとにリクエスト・レスポンスのバリデーションを有効/無効にできます。
- **APIプレフィックス対応**: `/api` のようなURLプレフィックスをスキーマ定義と分離して扱えます。
- **簡単なセットアップ**: `TestCase` にトレイトを `use` し、いくつかのメソッドを実装するだけで利用を開始できます。
- **詳細なエラーレポート**: バリデーション失敗時に、どの項目が仕様に違反したか詳細なメッセージを出力します。

## インストール方法

Composer を使ってインストールします。

```bash
composer require mimosafa/laravel-openapi-validation-helper --dev
```

## 使用方法

#### 1. トレイトの利用

ベースとなるテストケース（通常は `tests/TestCase.php`）で `TestCaseHelper` トレイトを `use` します。

```php
// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LaravelOpenAPIValidationHelper\TestCaseHelper;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use TestCaseHelper; // このトレイトを追加
}
```

#### 2. セットアップメソッドの呼び出し

同じく `tests/TestCase.php` の `setUp()` メソッド内で、`setUpTransparentlyTest()` を呼び出します。

```php
// tests/TestCase.php

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransparentlyTest(); // このメソッドを呼び出す
    }
```

#### 3. 抽象メソッドの実装

バリデーションを実行したいテストクラスで、バリデーションに必要な4つの抽象メソッドを実装します。多くの場合、テストクラスにプロパティを定義し、各テストメソッドでその値を上書きすることで、動的に設定するのが便利です。

```php
// tests/Feature/ExampleTest.php

namespace Tests\Feature;

use LaravelOpenAPIValidationHelper\HttpRequestMethod;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // これらのプロパティを各テストメソッドで設定する
    protected string $prefix = '/api';
    protected string $path = '/users/1';
    protected HttpRequestMethod $operation = HttpRequestMethod::GET;

    protected function prefix(): string
    {
        return $this->prefix;
    }

    protected function path(): string
    {
        return $this->path;
    }

    protected function operation(): HttpRequestMethod
    {
        return $this->operation;
    }

    protected function getValidatorBuilder(): ValidatorBuilder
    {
        // openapi.ymlのパスを指定
        return (new ValidatorBuilder)->fromYamlFile(base_path('tests/openapi.yml'));
    }

    /** @test */
    public function a_user_can_be_retrieved(): void
    {
        // プロパティを設定
        $this->prefix = '/api';
        $this->path = '/users/1';
        $this->operation = HttpRequestMethod::GET;

        // テストを実行すると、レスポンスが自動的に検証される
        $this->getJson('/api/users/1')->assertStatus(200);
    }
}
```

## 役割分担 (How it Works)

このライブラリは、バリデーション処理を直接行うのではなく、コアな検証ロジックを `league/openapi-psr7-validator` パッケージに委譲しています。それぞれの役割は以下の通りです。

### `TestCaseHelper` トレイト (このライブラリ)

- **「橋渡し役」** を担当します。
- Laravelのテスト内で実行されたHTTPリクエストとレスポンスを捕まえます。
- それらを `league/openapi-psr7-validator` が理解できるPSR-7標準の形式に変換します。
- 変換の過程で、`/api` のようなプレフィックスを除去し、OpenAPIスキーマのパスと一致するように調整します。
- 準備ができたリクエストとレスポンスを、検証エンジン本体である `league/openapi-psr7-validator` に渡します。

### `league/openapi-psr7-validator` パッケージ

- **「検証エンジン本体」** を担当します。
- `openapi.yml` を読み込み、API仕様を完全に理解します。
- `TestCaseHelper` から渡されたリクエストの具体的なパス（例: `/users/456`）が、スキーマ上のどのテンプレートパス（例: `/users/{id}`）に該当するかを自動で判断します。
- 該当したスキーマ定義に基づき、リクエストやレスポンスの内容が仕様通りか厳密にチェックします。

この連携により、開発者はLaravelのテストを普段通り記述するだけで、透過的にOpenAPI仕様の準拠テストを行うことができます。

## API

### `ignoreRequestCompliance()`

現在のテストにおいて、リクエストのバリデーションを一時的に無効化します。

```php
public function test_with_invalid_request(): void
{
    $this->ignoreRequestCompliance();

    // 不正なリクエストでもリクエストバリデーションは実行されない
    $this->postJson('/api/users', []);
}
```

### `ignoreResponseCompliance()`

現在のテストにおいて、レスポンスのバリデーションを一時的に無効化します。

```php
public function test_with_invalid_response(): void
{
    $this->ignoreResponseCompliance();

    // 不正なレスポンスを返すようなリクエストを投げても
    // レスポンスバリデーションは実行されない
    $this->postJson('/api/users', ['generate_invalid_response' => true]);
}
```

## 謝辞 (Acknowledgements)

このライブラリは、株式会社Nextat（ネクスタット）様の開発ブログ記事「[LaravelアプリケーションのAPIがSwagger/OpenAPIドキュメントに準拠していることを透過的にテストする](https://nextat.co.jp/staff/archives/253)」に多大なインスピレーションを受けて開発されました。

素晴らしいアイデアと実装のヒントを公開してくださった執筆者様に、この場を借りて深く感謝申し上げます。

## ライセンス

このライブラリは [MITライセンス](LICENSE.md) の下で公開されています。

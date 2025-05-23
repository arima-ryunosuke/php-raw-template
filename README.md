Night Dragon (simple php template engine)
====

## Description

php の素材の味を生かしたシンプルなテンプレートエンジンです。
下記の機能・特徴があります。

- （raw な php なので）php で実現できることは何でもできます
- （原則として）raw なので爆速です
- `<?= $string ?>` で自動 html エスケープされます
- `<?= $string ?>` の最後の改行が除去されません
- `<?= $string | strtoupper ?>` で `strtoupper($string)` に変換されます（ネスト可能）
- `<?= $string & strtoupper ?>` で $string が非 null の場合に `strtoupper($string)` に変換されます（ネスト可能）
- `<?= $array->key ?>` で `$array['key']` に変換されます（ネスト可能）
- アサインされた変数の型情報に基づいて自動で `/** @var \Hoge $hoge */` を埋め込みます
- テンプレート継承ができます

逆に巷にあるテンプレートエンジンにある下記のような機能はありません。

- テンプレートディレクトリ指定（常にフルパスで指定）
    - ただし、テンプレート内では相対パスで指定できるので、必要なのは最初の大本テンプレート時のみです
- テンプレートの更新日時チェック機能
    - 今のところ必要になってないので実装していません
- 拡張子指定（常に拡張子も含めて指定）
    - raw であれば拡張子を固定にするメリットは特にありません（phtml, php などで好みが分かれるし）
- ファイル以外のデータソース
    - php には強力なストリームラッパーがあるため個別対応は不要でしょう
- プラグイン機構
    - 大抵はヘルパー関数を設ければそれで十分だし、アサインすれば `<?= $plugin->func() ?>` で呼べるので不要でしょう

## Install

```json
{
    "require": {
        "ryunosuke/night-dragon": "dev-master"
    }
}
```

## Specification

下記は全機能こみこみのテンプレートです。継承機能を使っています。

<details>
<summary>layout.phtml</summary>

```php
<html lang="ja">
<head>
    <title><?= $title ?> - <?= $SiteName ?></title>
</head>
<body>
<?php $this->begin('main') ?>
これは子テンプレートから渡された変数です：<?= $childvar ?>
<?php $this->end() ?>
</body>
</html>
```

</details>

<details>
<summary>action.phtml</summary>

```php
<?php $this->extend(__DIR__ . '/layout.phtml', [
    'title'    => 'PageTitle',
    'childvar' => 'ChildVar',
]) ?>

<?php $this->begin('main') ?>
<section>
    <h2>親・子コンテンツの関係</h2>
    親テンプレートに渡した変数は使えません：<?= $childvar ?? 'not defined' ?>
    parent メソッドを使用して親コンテンツを表示します。
    <?php $this->parent() ?>

    これは子供コンテンツです。
    色々変数を表示しています。
</section>

<section>
    <h2>オートエスケープ</h2>
    これはただの文字列表示です（デフォルトで html エスケープされます）：<?= "this is $string" ?>
    ショート echo タグではなく、php タグはエスケープされません：<?php echo "this is $string" ?>
    ただの php タグは改行もされません（php のデフォルトです。ショート echo タグを使うとその挙動を抑制できます）。
    これは自動エスケープの無効化です（@をつけると生出力になります）：<?= @"this is $string" ?>
</section>

<section>
    <h2>修飾子機能</h2>
    これは修飾子機能です（"|" でパイプ演算子のような挙動になります）：<?= $string | strtoupper ?>
    修飾子は繋げられるし、 $_ という特殊変数を使うと任意引数位置に適用できます：<?= $string | strtoupper ?? 'default' | str_replace('TITLE', 'subject', $_) ?>
    登録しておけば静的メソッドも呼べます：<?= $float | number ?>
    引数付きです：<?= $float | number(3) ?>

    & は基本的に | と同じですが、値が null の場合にスルーされます（ぼっち演算子みたいなものです）：<?= $null & number ?? 'default' ?>
    引数付きです：<?= $float & number(3) ?? 'default' ?>

    | と & は混在可能です：<?= $null & number | is_null | var_export ?>
</section>

<section>
    <h2>配列・オブジェクトアクセス</h2>
    これは配列のアクセスです（"->" で配列アクセスできます）：<?= $array->hoge ?>
    配列アクセスはネストできます：<?= $array->fuga->x ?>
    "?->" で nullsafe アクセスができます：<?= $array?->undefined1?->undefined2 ?? 'default' ?>
    オブジェクトもアクセスできます：<?= $object->hoge ?>
    配列とオブジェクトは混在して OK です：<?= $object->fuga->x ?>
    埋め込み構文も使えます1：<?= "prefix-{$object->fuga->x}-suffix" ?>
    埋め込み構文も使えます2：<?= "prefix-{$closure(strtolower($object->fuga->x))}-suffix" ?>
    このように ?? 演算子とも併用できます：<?= $array->undefined ?? 'default' ?>
    オブジェクトも可能できます。共にネストも出来ます：<?= $object->undefined1->undefined2 ?? 'default' | strtoupper ?>

    上記2つの機能は「配列アクセス -> 修飾子」のときのみ組み合わせ可能です：<?= $array->fuga | implode(',', $_) ?>
    右記のような順番の組み合わせはできません：<?= @"<?=" ?> $string | str_split->z <?= @"?>" ?>
</section>

<section>
    <h2>埋め込み構文</h2>
    `` の中で ${} を使用するとその中で式の展開や定数の埋め込みが可能になります。
    式の展開：<?= `this is $float / 1000 + 5:${$float / 1000 + 5}` ?>
    定数の埋め込み：<?= `ArrayObject::ARRAY_AS_PROPS:${\ArrayObject::ARRAY_AS_PROPS}` ?>
</section>

<section>
    <h2>キャプチャ・サブテンプレート</h2>
    Smarty でいう capture のような機能はありませんが、所詮素の php なので ob_ 系を使用することで簡単に模倣できます。
    <?php ob_start() ?>
    <blockquote>
        これはキャプチャー中の変数表示です：<?= $string | strtoupper ?>
    </blockquote>
    <?php $buffer = ob_get_clean() ?>
    キャプチャ結果を呼び出します。<?= @$buffer ?>

    Smarty でいう function のような機能はありませんが、所詮素の php なのでクロージャを使用することで簡単に模倣できます。
    <?php $template = function ($arg1, $arg2) { ?>
        <blockquote>
            arg1+arg2: <?= $arg1 | strtoupper ?> <?= $arg2 | strtolower ?>
        </blockquote>
    <?php } ?>
    テンプレートを引数付きで呼び出します。<?= @$template("Hello's", 'World') ?>

    上記2つの機能は利便性が高く、使用頻度もそれなりのため、 組み込みの機能として実装する予定はあります。
</section>

<section>
    <h2>カスタムタグ</h2>
    タグのコールバックを登録すると特定タグに対してコールバックが実行されます。
    例えばデフォルトでは strip タグが登録されていて、空白を除去できます（Smarty の {strip} に相当） 。
    <strip>
        このタグ内の空白はすべて除去されます。ただし、変数の中身には関与しません。
        <div
            id="stripping"
            class="hoge fuga piyo"
        >
            <?= $multiline ?>
        </div>
    </strip>
</section>

<section>
    <h2>ショートタグ</h2>
    上記の構文の一部はショートオープンタグ内でも限定的に使えます。
    これらの機能は compatibleShortTag を true にすると php 本体で short_open_tag が無効にされていても使用できます（詳細は README にて）。

    foreach でアクセス子が使える
    <? foreach ($array->fuga as $key => $value): ?>
        <?= $value ?>
    <? endforeach ?>

    if で アクセス子が使える
    <? if (empty($array->undefined)): ?>
        $object.undefined is undefined
    <? endif ?>
</section>

おまけ：所詮素の php なのであらゆる表現が可能です。
<?php foreach ($array as $key => $value): ?>
    <?php if ($key === 'hoge'): ?>
        <?php echo "$value です"; ?>
        <?php echo "ショートタグが使いたいなぁ"; ?>
    <?php endif ?>
<?php endforeach ?>

<?php $this->end() ?>
```

</details>

一見すると意味不明なコードですが、これは完全に valid な php コードであり、IDE の支援をフルに受けることができます。

これをレンダリングするとソースコードが内部的に下記のように書き換えられます。

<details>
<summary>layout.phtml</summary>

```php
<html lang="ja">
<head>
    <title><?=\ryunosuke\NightDragon\Renderer::html($title)?> - <?=\ryunosuke\NightDragon\Renderer::html($SiteName)?></title>
</head>
<body>
<?php $this->begin('main') ?>
これは子テンプレートから渡された変数です：<?=\ryunosuke\NightDragon\Renderer::html($childvar),"\n"?>
<?php $this->end() ?>
</body>
</html>
```

</details>

<details>
<summary>action.phtml</summary>

```php
<?php $this->extend(__DIR__ . '/layout.phtml', [
    'title'    => 'PageTitle',
    'childvar' => 'ChildVar',
]) ?>

<?php $this->begin('main') ?>
<section>
    <h2>親・子コンテンツの関係</h2>
    親テンプレートに渡した変数は使えません：<?=\ryunosuke\NightDragon\Renderer::html($childvar ?? 'not defined'),"\n"?>
    parent メソッドを使用して親コンテンツを表示します。
    <?php $this->parent() ?>

    これは子供コンテンツです。
    色々変数を表示しています。
</section>

<section>
    <h2>オートエスケープ</h2>
    これはただの文字列表示です（デフォルトで html エスケープされます）：<?=\ryunosuke\NightDragon\Renderer::html("this is $string"),"\n"?>
    ショート echo タグではなく、php タグはエスケープされません：<?php echo "this is $string" ?>
    ただの php タグは改行もされません（php のデフォルトです。ショート echo タグを使うとその挙動を抑制できます）。
    これは自動エスケープの無効化です（@をつけると生出力になります）：<?="this is $string","\n"?>
</section>

<section>
    <h2>修飾子機能</h2>
    これは修飾子機能です（"|" でパイプ演算子のような挙動になります）：<?=\ryunosuke\NightDragon\Renderer::html(strtoupper($string)),"\n"?>
    修飾子は繋げられるし、 $_ という特殊変数を使うと任意引数位置に適用できます：<?=\ryunosuke\NightDragon\Renderer::html(str_replace('TITLE','subject',strtoupper($string) ?? 'default')),"\n"?>
    登録しておけば静的メソッドも呼べます：<?=\ryunosuke\NightDragon\Renderer::html(\Modifier::number($float)),"\n"?>
    引数付きです：<?=\ryunosuke\NightDragon\Renderer::html(\Modifier::number($float,3)),"\n"?>

    & は基本的に | と同じですが、値が null の場合にスルーされます（ぼっち演算子みたいなものです）：<?=\ryunosuke\NightDragon\Renderer::html(((${"\0"}=$null) === null ? ${"\0"} : \Modifier::number(${"\0"})) ?? 'default'),"\n"?>
    引数付きです：<?=\ryunosuke\NightDragon\Renderer::html(((${"\0"}=$float) === null ? ${"\0"} : \Modifier::number(${"\0"},3)) ?? 'default'),"\n"?>

    | と & は混在可能です：<?=\ryunosuke\NightDragon\Renderer::html(var_export(is_null(((${"\0"}=$null) === null ? ${"\0"} : \Modifier::number(${"\0"}))))),"\n"?>
</section>

<section>
    <h2>配列・オブジェクトアクセス</h2>
    これは配列のアクセスです（"->" で配列アクセスできます）：<?=\ryunosuke\NightDragon\Renderer::html(\ryunosuke\NightDragon\Renderer::access($array,[false,'hoge'])),"\n"?>
    配列アクセスはネストできます：<?=\ryunosuke\NightDragon\Renderer::html(\ryunosuke\NightDragon\Renderer::access($array,[false,'fuga'],[false,'x'])),"\n"?>
    "?->" で nullsafe アクセスができます：<?=\ryunosuke\NightDragon\Renderer::html(@\ryunosuke\NightDragon\Renderer::access($array,[true,'undefined1'],[true,'undefined2']) ?? 'default'),"\n"?>
    オブジェクトもアクセスできます：<?=\ryunosuke\NightDragon\Renderer::html(\ryunosuke\NightDragon\Renderer::access($object,[false,'hoge'])),"\n"?>
    配列とオブジェクトは混在して OK です：<?=\ryunosuke\NightDragon\Renderer::html(\ryunosuke\NightDragon\Renderer::access($object,[false,'fuga'],[false,'x'])),"\n"?>
    埋め込み構文も使えます1：<?=\ryunosuke\NightDragon\Renderer::html("prefix-".\ryunosuke\NightDragon\Renderer::access($object,[false,'fuga'],[false,'x'])."-suffix"),"\n"?>
    埋め込み構文も使えます2：<?=\ryunosuke\NightDragon\Renderer::html("prefix-{$closure(strtolower(\ryunosuke\NightDragon\Renderer::access($object,[false,'fuga'],[false,'x'])))}-suffix"),"\n"?>
    このように ?? 演算子とも併用できます：<?=\ryunosuke\NightDragon\Renderer::html(@\ryunosuke\NightDragon\Renderer::access($array,[false,'undefined']) ?? 'default'),"\n"?>
    オブジェクトも可能できます。共にネストも出来ます：<?=\ryunosuke\NightDragon\Renderer::html(strtoupper(@\ryunosuke\NightDragon\Renderer::access($object,[false,'undefined1'],[false,'undefined2']) ?? 'default')),"\n"?>

    上記2つの機能は「配列アクセス -> 修飾子」のときのみ組み合わせ可能です：<?=\ryunosuke\NightDragon\Renderer::html(implode(',',\ryunosuke\NightDragon\Renderer::access($array,[false,'fuga']))),"\n"?>
    右記のような順番の組み合わせはできません：<?="<?="?> $string | str_split->z <?="?>","\n"?>
</section>

<section>
    <h2>埋め込み構文</h2>
    `` の中で ${} を使用するとその中で式の展開や定数の埋め込みが可能になります。
    式の展開：<?=\ryunosuke\NightDragon\Renderer::html("this is $float / 1000 + 5:".($float / 1000 + 5).""),"\n"?>
    定数の埋め込み：<?=\ryunosuke\NightDragon\Renderer::html("ArrayObject::ARRAY_AS_PROPS:".(\ArrayObject::ARRAY_AS_PROPS).""),"\n"?>
</section>

<section>
    <h2>キャプチャ・サブテンプレート</h2>
    Smarty でいう capture のような機能はありませんが、所詮素の php なので ob_ 系を使用することで簡単に模倣できます。
    <?php ob_start() ?>
    <blockquote>
        これはキャプチャー中の変数表示です：<?=\ryunosuke\NightDragon\Renderer::html(strtoupper($string)),"\n"?>
    </blockquote>
    <?php $buffer = ob_get_clean() ?>
    キャプチャ結果を呼び出します。<?=$buffer,"\n"?>

    Smarty でいう function のような機能はありませんが、所詮素の php なのでクロージャを使用することで簡単に模倣できます。
    <?php $template = function ($arg1, $arg2) { ?>
        <blockquote>
            arg1+arg2: <?=\ryunosuke\NightDragon\Renderer::html(strtoupper($arg1))?> <?=\ryunosuke\NightDragon\Renderer::html(strtolower($arg2)),"\n"?>
        </blockquote>
    <?php } ?>
    テンプレートを引数付きで呼び出します。<?=$template("Hello's", 'World'),"\n"?>

    上記2つの機能は利便性が高く、使用頻度もそれなりのため、 組み込みの機能として実装する予定はあります。
</section>

<section>
    <h2>カスタムタグ</h2>
    タグのコールバックを登録すると特定タグに対してコールバックが実行されます。
    例えばデフォルトでは strip タグが登録されていて、空白を除去できます（Smarty の {strip} に相当） 。
    このタグ内の空白はすべて除去されます。ただし、変数の中身には関与しません。 <div id="stripping" class="hoge fuga piyo"><?=\ryunosuke\NightDragon\Renderer::html($multiline)?></div>
</section>

<section>
    <h2>ショートタグ</h2>
    上記の構文の一部はショートオープンタグ内でも限定的に使えます。
    これらの機能は compatibleShortTag を true にすると php 本体で short_open_tag が無効にされていても使用できます（詳細は README にて）。

    foreach でアクセス子が使える
    <?php foreach (\ryunosuke\NightDragon\Renderer::access($array,[false,'fuga']) as $key => $value): ?>
        <?=\ryunosuke\NightDragon\Renderer::html($value),"\n"?>
    <?php endforeach ?>

    if で アクセス子が使える
    <?php if (!@boolval(\ryunosuke\NightDragon\Renderer::access($array,[false,'undefined']))): ?>
        $object.undefined is undefined
    <?php endif ?>
</section>

おまけ：所詮素の php なのであらゆる表現が可能です。
<?php foreach ($array as $key => $value): ?>
    <?php if ($key === 'hoge'): ?>
        <?php echo "$value です"; ?>
        <?php echo "ショートタグが使いたいなぁ"; ?>
    <?php endif ?>
<?php endforeach ?>

<?php $this->end() ?>
```

</details>

なお、 `<?php # meta template data ?>` というコメントがあると、テンプレート自体にも手が加わります（後述）。
具体的には「使用している変数情報」「定数情報」などがメタ情報として書き込まれます。
これにより phpstorm のジャンプや補完を最大限に活かすことができます。

さらに上記が include されて最終的に下記のようなレンダリング結果となります。

<details>
<summary>レンダリング結果</summary>

```html
<html lang="ja">
<head>
    <title>PageTitle - サイト名</title>
</head>
<body>
<section>
    <h2>親・子コンテンツの関係</h2>
    親テンプレートに渡した変数は使えません：not defined
    parent メソッドを使用して親コンテンツを表示します。
    これは子テンプレートから渡された変数です：ChildVar

    これは子供コンテンツです。
    色々変数を表示しています。
</section>

<section>
    <h2>オートエスケープ</h2>
    これはただの文字列表示です（デフォルトで html エスケープされます）：this is This&#039;s Title
    ショート echo タグではなく、php タグはエスケープされません：this is This's Title    ただの php タグは改行もされません（php のデフォルトです。ショート echo タグを使うとその挙動を抑制できます）。
    これは自動エスケープの無効化です（@をつけると生出力になります）：this is This's Title
</section>

<section>
    <h2>修飾子機能</h2>
    これは修飾子機能です（"|" でパイプ演算子のような挙動になります）：THIS&#039;S TITLE
    修飾子は繋げられるし、 $_ という特殊変数を使うと任意引数位置に適用できます：THIS&#039;S subject
    登録しておけば静的メソッドも呼べます：12,346
    引数付きです：12,345.679

    & は基本的に | と同じですが、値が null の場合にスルーされます（ぼっち演算子みたいなものです）：default
    引数付きです：12,345.679

    | と & は混在可能です：true
</section>

<section>
    <h2>配列・オブジェクトアクセス</h2>
    これは配列のアクセスです（"->" で配列アクセスできます）：HOGE
    配列アクセスはネストできます：X
    "?->" で nullsafe アクセスができます：default
    オブジェクトもアクセスできます：HOGE
    配列とオブジェクトは混在して OK です：X
    埋め込み構文も使えます1：prefix-X-suffix
    埋め込み構文も使えます2：prefix-closurex-suffix
    このように ?? 演算子とも併用できます：default
    オブジェクトも可能できます。共にネストも出来ます：DEFAULT

    上記2つの機能は「配列アクセス -> 修飾子」のときのみ組み合わせ可能です：X,Y,Z
    右記のような順番の組み合わせはできません：<?= $string | str_split->z ?>
</section>

<section>
    <h2>埋め込み構文</h2>
    `` の中で ${} を使用するとその中で式の展開や定数の埋め込みが可能になります。
    式の展開：this is 12345.6789 / 1000 + 5:17.3456789
    定数の埋め込み：ArrayObject::ARRAY_AS_PROPS:2
</section>

<section>
    <h2>キャプチャ・サブテンプレート</h2>
    Smarty でいう capture のような機能はありませんが、所詮素の php なので ob_ 系を使用することで簡単に模倣できます。
        キャプチャ結果を呼び出します。    <blockquote>
        これはキャプチャー中の変数表示です：THIS&#039;S TITLE
    </blockquote>
    

    Smarty でいう function のような機能はありませんが、所詮素の php なのでクロージャを使用することで簡単に模倣できます。
        テンプレートを引数付きで呼び出します。        <blockquote>
            arg1+arg2: HELLO&#039;S world
        </blockquote>
    

    上記2つの機能は利便性が高く、使用頻度もそれなりのため、 組み込みの機能として実装する予定はあります。
</section>

<section>
    <h2>カスタムタグ</h2>
    タグのコールバックを登録すると特定タグに対してコールバックが実行されます。
    例えばデフォルトでは strip タグが登録されていて、空白を除去できます（Smarty の {strip} に相当） 。
    このタグ内の空白はすべて除去されます。ただし、変数の中身には関与しません。 <div id="stripping" class="hoge fuga piyo">line1
line2
line3</div>
</section>

<section>
    <h2>ショートタグ</h2>
    上記の構文の一部はショートオープンタグ内でも限定的に使えます。
    これらの機能は compatibleShortTag を true にすると php 本体で short_open_tag が無効にされていても使用できます（詳細は README にて）。

    foreach でアクセス子が使える
            X
            Y
            Z
    
    if で アクセス子が使える
            $object.undefined is undefined
    </section>

おまけ：所詮素の php なのであらゆる表現が可能です。
            HOGE です        ショートタグが使いたいなぁ        
</body>
</html>
```

</details>

### Feature

原則として `<?= ?>` を対象に書き換えます。
また、一部の利便性のため `<? ?>` タグも書き換え対象です。

`<?php ?>` を書き換えることはありません。

`<? ?>` は具体的には `<? foreach ($array->member as $key => $value): ?>` や `<? if ($array->flag): ?>` が記述できるようになります。
ただし、実験的な機能であり、今のところこのような配列アクセス機能だけが有効です（修飾子は使えない）。

さらに `<? ?>` は非推奨の機能でもあります。
というのも `<? ?>` 自体が php 本体側で非推奨になるような傾向があり、処理は `token_get_all` に頼っているため、いざ廃止されたときにまともに動かなくなるためです。
また、 `token_get_all` は ini の short_open_tag 設定の影響を受けるため、環境により動作が異なることになります。

後述のオプションで short_open_tag の設定によらずにショートタグを使えるようにすることもできますが、かなりドラスティックな機能です。

#### access key

`<?= $array->key >` という形式で配列やオブジェクトにアクセスできます。
これはネスト可能で、配列・オブジェクトの混在もできます。

`key` 部分に使用できるのはリテラル文字列、リテラル数値、単一変数だけです。式や通常の `[]` によるアクセスは混在できません。

`??` 演算子も混ぜることが出来ます。
ただし後述の `defaultGetter` を指定している場合は関数ベースでのアクセスとなるため、 `@` によるエラー抑制にフォールバックされます（関数ベースで構文レベルの `??` を模倣するのが不可能だからです。動作自体は変わりません）。

- OK
    - `<?= $array->key1->key2 ?>` (単純なネスト)
    - `<?= $array->3 ?>` (リテラル数値)
    - `<?= $array->$key ?>` (単一変数)
    - `<?= "prefix-{$array->key1->key2}-suffix" ?>` (埋め込み構文)
    - `<?= $array?->undefined ?? 'default' ?>` (`?->`, `??` 演算子との併用)
- NG
    - `<?= $array->PATHINFO_EXTENSION ?>` (定数)
    - `<?= $array['key1']->key2 ?>` (`[]` 混在)
    - `<?= $array->strtoupper($key) ?>` (式)

#### modifier

`<?= $value | funcname ?>` という形式でパイプライン風に関数適用できます。
これはネスト可能で、適用する引数位置なども制御できます。

引数位置には `$_` というレシーバ変数を用います。
`$_` は左の値が代入されるような挙動を示します。

`$_` がない場合は第1引数に適用されます。
第1引数への適用だけであれば関数呼び出しの `()` は省略できます。

原則として修飾子に使用できるのは単一の関数だけです。制限付きですが名前空間関数や静的メソッドも一応サポートしています。
文字列以外の callable 形式は使用できません。

`|` の代わりに `&` を使うこともできます。
`&` を使うと値が null だった場合に適用されず、null のまま次のパイプラインへ進みます。
例えば `<?= $null | number_format ?? "null です" ?>` すると出力は「0」となります。これは望まれない場合が多いでしょう。
代わりに `<?= $null & number_format ?? "null です" ?>` とすると出力は「null です」となります。

- OK
    - `<?= $value | funcname($_) ?>` (=`funcname($value)`: 単純な呼び出し)
    - `<?= $value | funcname ?>` (=`funcname($value)`: 第1引数なので `$_` は省略可能)
    - `<?= $value | funcname(3) ?>` (=`funcname($value, 3)`: `$_` は自動で第1引数に適用される)
    - `<?= $value | funcname(3, $_) ?>` (=`funcname(3, $value)`: $value を第2引数に適用)
    - `<?= $value | funcname($_, $_) ?>` (=`funcname($value, $value)`: `$_` は何度でも使える)
    - `<?= $value | funcname(3, "pre-{$_}-fix") ?>` (=`funcname(3, "pre-{$value}-fix")`: `$_` は普通の変数のように使用できる)
    - `<?= $value | funcname1 | funcname2 | funcname3 ?>` (=`funcname3(funcname2(funcname1($value)))`: 修飾子はネスト可能で各段階では上記の記法すべてが使える)
    - `<?= $value | funcname1 & funcname2 & funcname3 ?>` (=`funcname3nully(funcname2nully(funcname1($value)))`: `|` と `&` は混在できる)
    - `<?= $value | \namespace\func ?>` (=`\namespace\func($value)`: 絶対指定の名前空間関数呼び出し)
    - `<?= $value | subspace\func ?>` (=`\filespace\subspace\func($value)`: ファイルの名前空間での相対呼び出し)
    - `<?= $value | func ?>` (=`\defaultNamespace\func($value)`: defaultNamespace で事前登録した名前空間の関数呼び出し)
    - `<?= $value | method ?>` (=`\defaultClass::method($value)`: defaultClass で事前登録したクラスの静的メソッド呼び出し)
- NG
    - `<?= $value | undefined ?>` (未定義関数。構文的にエラーではないが呼び出せない)
    - `<?= $value | class::method ?>` (メソッド形式は呼び出し不可)
    - `<?= $value | $callable ?>` (変数に格納された callable は現状呼び出せるが、正式な仕様ではない)

#### auto filter

`<?= $string ?>` で自動で html エスケープが施されます。
これはショート echo タグだけが対象です。
後述の `nofilter` オプションで「エスケープしない」ショートタグも表現できます。

なお、 `<?= ?>` の直後の改行は維持されます（php の標準の動作だと改行は削除される（正確には終了タグに改行が含まれてしまう））。

- エスケープされる
    - `<?= $string ?>` (通常のタグ)
    - `<?= ucfirst($string) ?>` (式)
    - `<?= $item->children | implode(',', $_) ?>` (キーアクセスや修飾子と併用)
- エスケープされない
    - `<?php echo $string ?>` (`<?php` タグ)
    - `<?= @$string ?>` (`nofilter` による抑止)

#### expand variable

`` ` `` 内では `${}` で埋め込み構文が使えます。

php の変数展開はかなりいろいろなことができるんですが、「式の結果を出力する」ではなく「式の結果の変数名の値を出力する」という謎仕様があります。
多くの場合この仕様は邪魔（というか不便）でしかないためそれを抑制することができます。
さらに `${}` の中は上記の修飾子や配列アクセス記法が全て使用できます。

- 式の埋め込み
    - ``<?= `this is n + 1: ${$n + 1}` ?>`` (計算式)
    - ``<?= `this is class constant: ${Class::constant}` ?>`` (クラス定数)
    - ``<?= `this is json value: ${json_encode([1,2,3])}` ?>`` (関数呼び出し)
    - ``<?= `this is json value: ${[1,2,3] | json_encode}` ?>`` (修飾子)

もっとも、このような表示するだけの用法なら単にリテラル部分を php タグの外に出すだけでもっと気軽に実現できます。
真価を発揮するのは下記のような変数宣言や引数のときでしょう。

- 変数代入や引数での使用
    - ``<? $tmp = `this is n + 1: ${$n + 1}` ?>`` (変数代入)
    - ``<?= ucfirst(`this is n + 1: ${$n + 1}`) ?>`` (関数の引数)

なお、この機能はショートエコータグだけではなく `<? ?>` も書き換え対象になります。
テンプレートファイル内でバッククオート（シェル呼び出し）を使うような状況は限りなくゼロに近く、「バッククオートがあったら式展開構文」という前提がほぼ成り立つためです。

## Usage

### Options

```php
$renderer = new \ryunosuke\NightDragon\Renderer([
    // デバッグ系
    'debug'              => $debug,
    'errorHandling'      => $debug,
    'gatherVariable'     => $debug ? self::DECLARED | self::FIXED | self::GLOBAL | self::ASSIGNED | self::USING : 0,
    'gatherModifier'     => $debug,
    'gatherAccessor'     => $debug,
    'constFilename'      => null,
    'typeMapping'        => [],
    'specialVariable'    => [],
    'ignoreVariable'     => [],
    // インジェクション系
    'templateClass'      => Template::class,
    // ディレクトリ系
    'compileDir'         => null,
    // コンパイルオプション系
    'customTagHandler'   => [
        'strip' => Renderer::class . '::strip',
    ],
    'compatibleShortTag' => false,
    'defaultNamespace'   => '\\',
    'defaultClass'       => '',
    'defaultFilter'      => Renderer::class . '::html',
    'defaultGetter'      => Renderer::class . '::access',
    'defaultCloser'      => "\n",
    'nofilter'           => '@',
    'varModifier'        => ['|', '&'],
    'varReceiver'        => '$_',
    'varAccessor'        => '->',
    'varExpander'        => '`',
]);

$renderer->assign([
    // グローバルにアサインする変数
    'SiteName' => 'サイト名',
]);

echo $renderer->render(__DIR__ . '/action.phtml', [
    // テンプレートにアサインする変数
    'null'      => null,
    'float'     => 12345.6789,
    'string'    => "This's Title",
    'multiline' => "line1\nline2\nline3",
    'array'     => ['hoge' => 'HOGE', 'fuga' => ['x' => 'X', 'y' => 'Y', 'z' => 'Z']],
    'object'    => (object) ['hoge' => 'HOGE', 'fuga' => ['x' => 'X', 'y' => 'Y', 'z' => 'Z']],
    'closure'   => function ($arg) { return 'closure' . $arg; },
]);
```

基本的には上記の使用法しかありません。
ここでは使い方の記述に留めるので、細々とした機能はソースを参照してください。

#### debug

デバッグフラグです。
影響は結構多岐にわたるので、詳細はソースコードを参照してください。
原則として、「開発時は true, 運用時は false」にしてください。

#### errorHandling

テンプレート内でエラーや例外が起こった場合にハンドリングするかを bool で指定します。
ハンドリングを有効にするとテンプレートの前後行が表示されたり見やすくなります。

#### gatherVariable, gatherModifier, gatherAccessor

テンプレートファイルの書き換えオプションです。
テンプレート内に `<?php # meta template data ?>` というコメントを入れるとその位置に下記のようなメタ情報が挿入されます。

```php
<?php
# meta template data
// @formatter:off
// using variables:
/** @var \ryunosuke\NightDragon\Template $this */
/** @var mixed $_ */
/** @var null $null */
/** @var float $float */
/** @var string $string */
/** @var string $multiline */
/** @var array $array */
/** @var \stdClass $object */
/** @var \Closure $closure */
// using modifier functions:
if (false) {function strtoupper(...$args){define('strtoupper', \strtoupper(...[]));return \strtoupper(...$args);}}
if (false) {function str_replace(...$args){define('str_replace', \str_replace(...[]));return \str_replace(...$args);}}
if (false) {function number(...$args){define('number', \Modifier::number(...[]));return \Modifier::number(...$args);}}
if (false) {function is_null(...$args){define('is_null', \is_null(...[]));return \is_null(...$args);}}
if (false) {function var_export(...$args){define('var_export', \var_export(...[]));return \var_export(...$args);}}
if (false) {function implode(...$args){define('implode', \implode(...[]));return \implode(...$args);}}
if (false) {function strtolower(...$args){define('strtolower', \strtolower(...[]));return \strtolower(...$args);}}
// using array keys:
true or define('hoge', 'hoge');
true or define('fuga', 'fuga');
true or define('undefined', 'undefined');
true or define('undefined1', 'undefined1');
true or define('undefined2', 'undefined2');
// @formatter:on
?>
```

用語がバラけていて勘違いしやすいですが、挿入対象は **テンプレートファイル**です。リライト後のファイルや compileDir に吐き出されるファイルではありません。

gatherVariable を true にすると、テンプレート内で使用している変数を「実際にアサインされた型」を見て動的に `/** @var Hoge $hoge */` を挿入します。
型が活きるので IDE の補完やジャンプを活用することができます。
挿入されるのはアサインされていてかつ使用されている変数のみです（勝手に定義したものや、未使用変数は挿入されない）。
true ではなく Renderer に定義されている定数のビット和を渡すとカテゴリを指定して埋め込むことができます（これの詳細は控えます）。

gatherModifier を true にすると `<?= $string | strtoupper ?>` の `strtoupper` が定数・関数宣言されます。
これは phpstorm の警告を抑止と定義ジャンプのためです（実際に呼び出しているシンタックスなのでジャンプできます）。
定義すること自体に具体的な意味はありません。

gatherAccessor を true にすると `<?= $array->key ?>` の `key` が定数宣言されます。
これは phpstorm の警告を抑止するためです。
定義すること自体に具体的な意味はありません。

#### constFilename

ここにファイル名を指定すると gatherVariable, gatherModifier, gatherAccessor で吐き出されるような定数宣言が指定したファイルに書き出されます。
前述の通り、定数宣言は phpstorm の警告を抑止するためのものであり、同一ファイルである必要はありませんし `true or ` といえど活きたコードとして埋め込むのは気持ち悪いです。
このオプションを使用するとすべての定数定義を一つのファイルにまとめることができます。

内容は実行ごとに追記型です。
プロジェクト内のすべてのテンプレートファイルをレンダリングし終わったとき、未定義定数の警告がでるテンプレートファイルは存在しなくなるはずです。
また、埋込み型と違って全く関係のないファイルに出力されるため、実行される恐れがありません。

用途は主にデバッグ用です。
プロジェクトメンバー間で差異が生まれやすいファイルなので、プロジェクト内のどこかに配置して gitignore で無視すると良いかもしれません。

このオプションで別ファイルに逃がすか元テンプレートに埋め込むかは状況によります。
今のところ下記が確認できています（全て phpstorm 前提です）。

- include/extract を使用すると未定義警告を無くせる
- array-shapes の対応が未完全
- $this はどうしようもない

埋め込んでしまった方が全体的な利便性は高いんですが、基本的には余計なことをしない別ファイルに逃がす方を推奨します。

#### typeMapping

ここで `[original => alias]` という配列を指定すると、 gatherVariable で挿入される型情報を上書きすることができます。
「実際は array なんだけど、深遠な理由で ArrayObject として扱いたい」のようなかなり特殊な状況で使用します。

#### specialVariable

ここで `[$varname => typename]` という配列を指定すると、 テンプレートにアサインされた型に関わらず強制的にその変数名は指定した型として出力されます。
共通的なテンプレートでアサインされる型が場合によって異なる、といった状況で型を固定することができます。

#### ignoreVariable

ここで `[$varname => null]` という配列を指定すると、その変数は gatherVariable の対象外になります。
型が複雑で巨大とか array-shapes 記法でエラーになってしまうとか特殊な状況で使います。

値は現在の仕様だとなんでも受け入れます。
これは将来的に bool でフラグ化したり、クロージャで条件判定したりする想定があるためです。
今のところは null を指定しておくのが無難です。

#### templateClass

テンプレートクラスを外部から注入できます。
…が、おまけのようなものであり、実用性は考慮してません。

どうしても拡張したいような抜き差しならない状況で使ってください。

#### compileDir

書き換えられたソースが格納されるコンパイル済みディレクトリです。
別に必須ではありません。未指定の場合は sys_get_temp_dir が設定されます。

存在しない場合は自動で作成されます。

#### customTagHandler

html ソース書き換えのオプションです。

`[タグ名 => callable]` のような配列を指定しておくと、文字列的にそのタグ（配列のキー）に出くわしたときにコールバックが実行されます。
コールバックは `(タグコンテンツ, タグ属性オブジェクト)` が引数として渡ってきます。

デフォルトで `strip` が登録されています。
これは html 中の空白を削除して（基本的に）1行化するコールバックです。

他に例えば haml や markdown のようなタグを登録しておけば html 中で部分的に haml や markdown で記述できるようになります。
FAQ やガイドなど、静的な部分が多くなるページでかなり有用です。

#### compatibleShortTag

ソース書き換えのオプションです。

このキーを true に設定すると ini の short_open_tag の設定に関わらず `<? ?>` タグが有効になります。
ini の変更ができなかったり、 short_open_tag が廃止されたりした状況を想定してますが、気休め程度のオプションなので原則的に false にしてください。

#### defaultNamespace, defaultClass

同じくソース書き換えのオプションです。

defaultNamespace はテンプレート内で名前解決が行われる際に探索・付与する名前空間を指定します。ファイルの名前空間宣言は常に探索されます。
現在のところ `<?= $array | hoge ?>` の `hoge` を探索する際に使用されるのみです。
defaultNamespace が指定されてかつその名前空間に `hoge` 関数が定義されているとこれは `<?= $array | \namespace\hoge ?>` と解釈されます。

defaultClass はテンプレート内で名前解決が行われる際に探索・付与するクラスを指定します。
現在のところ `<?= $array | hoge ?>` の `hoge` を探索する際に使用されるのみです。
defaultClass が指定されてかつそのクラスに `hoge` 静的メソッドが定義されているとこれは `<?= $array | \classname::hoge ?>` と解釈されます。

これらはそれぞれ複数指定できます。その際の探索順は指定順です。
また、defaultNamespace と defaultClass の探索順は defaultClass -> defaultNamespace です。

#### defaultFilter, defaultGetter, defaultCloser

同じくソース書き換えのオプションです。

defaultFilter は `<?= $string ?>` で変換されるデフォルトフィルタを指定します。
可変引数を取る callable でかつ文字列である必要があります（クロージャは不可）。

defaultGetter は `<?= $array->key ?>` されたときにキーを引く変換関数名を指定します。
第1引数に array or object, 第2引数以降に [nullsafe-flag, キー文字列] を受け取る文字列 callable である必要があります。

defaultCloser は `<?= $string ?>` 時に挿入される改行文字を指定します。
あまり指定することはないでしょうが、改行差し込みを無効にしたい場合は空文字を指定するといいでしょう。

#### nofilter, varModifier, varReceiver, varAccessor, varExpander

同じくソース書き換えのオプションです。

nofilter は `<?= $string ?>` で変換されるデフォルトフィルタを無効化する文字を指定します。
例えば `@` を指定すると `<?= @$string ?>` でデフォルトフィルタが無効になります。
空文字にすると無効機能が無効になり、常に変換されます。

varModifier は `<?= $array | implode(',', $_) ?>` における `|` を指定します。
例えば `>>` を指定するとこれを `<?= $array >> implode(',', $_) ?>` と記述できるようになります。
互換性維持のため現在のデフォルトは `|` ですが、本来は配列を指定します。
順番に「単純パイプ修飾子記号」「null ならパイプしない修飾子記号」です。
将来のバージョンでは `['|', '&']` がデフォルトになります。
空文字にすると修飾子機能が無効になり、変換自体が行われなくなります。

varReceiver は `<?= $array | implode(',', $_) ?>` における `$_` を指定します。
例えば `$__var` を指定するとこれを `<?= $array | implode(',', $__var) ?>` と記述できるようになります。
変数名として有効な文字列である必要があります。

varAccessor は `<?= $array->key ?>` における `->` を指定します。
例えば `.` を指定するとこれを `<?= $array.key ?>` と記述できるようになります。
空文字にするとキーアクセス機能が無効になり、変換自体が行われなくなります。

varExpander は ``<?= `${expression}` ?>`` における `` ` `` を指定します。
実装上の都合で指定できるのはバッククオートとダブルクオートのみです。
例えば `"` を指定するとこれを `<?= "${expression}" ?>` と記述できるようになります。
空文字にすると埋め込み機能が無効になり、変換自体が行われなくなります。

### Methods

テンプレート内での `$this` は自身のテンプレートオブジェクトを指します。

テンプレート内で `$this->extend("parent/template/file")` を呼ぶとテンプレート継承を行うことができます。
テンプレート継承では親コンテンツの内容を参照しつつ部分部分の書き換えが可能になります（よくある機能なので概念の説明は省略します）。

子テンプレートにおいてブロック以外のトップレベルの記述はすべて無視されます。

下記の記述はかなり簡易なものであり、具体的には実際に見たほうが分かりやすいと思うので、冒頭の layout.phtml, action.phtml を参照してください。

#### $this->extend(string $filename[, array $vars])

親を指定してテンプレート継承を宣言します。
親は1つだけであり、同じテンプレートで継承を複数行うことはできません。

ただし、「継承しているテンプレートを継承」することはできます。いわゆる「親->子->孫」の多段継承です。

#### $this->begin(string $blockname)

ブロックを定義します。
定義されたブロックは子テンプレート側で参照することができます。

#### $this->end()

ブロックの終了を宣言します。

#### $this->block(string $blockname[, string $contents])

ブロックの定義と終了を同時に行います。
「中身のない begin ～ end」と同義です。

ただし、文字列でコンテンツ内容を与えることはできます。

#### $this->parent()

子テンプレートにおいて親の内容を参照します。

#### $this->import(string $filename[, array $vars])

指定したテンプレートを読み込みます。
このメソッドはテンプレートファイルとしてレンダリングして読み込みます。

つまり「テンプレートファイルの結果」を取り込むメソッドです。

#### $this->include(string $filename[, array $vars])

指定したテンプレートを読み込みます。
このメソッドはテンプレートファイルとして埋め込みます。

つまり「テンプレートファイルをそのまま」取り込むメソッドです。
（極論すると「そこにファイルの中身をコピペ」したと同じ結果になります）。

#### $this->content(string $filename)

指定したファイルを読み込みます。
このメソッドはレンダリングされません（js, css などを埋め込みたいときに使います）。

#### $this->load(string $filename)

拡張子に基づいて指定したファイルを読み込みます。
例えば js ファイルを指定すれば `<script>` タグで囲まれて読み込まれ、 `php` であれば単純に require されます。
自身と同じ拡張子の場合は import と同じ動作になります。

`$filename` は glob 記法で指定でき、マッチしたものが全て読み込まれます。

## Notes

- include, extend などの読み込みは呼び出し元の変数を引き継ぎます
    - 逆は成り立ちません。また、include はアサイン変数を渡せますが、そのアサイン優先です
- テンプレートファイル名に `;` は使用できません
    - 読み替えに使っているからです
- `src/functions.php` の関数群は利用側では使用禁止です
    - バージョンアップで頻繁に変更されます。あくまでこのパッケージ専用の関数群です
- `|` 修飾子の関数名は `use function hoge as fuga` でエイリアスした関数は呼べません
    - パースの都合です
- `|` の本来の用法（OR 演算子）はショートタグ内で使用できません
    - パースの都合です

## Q&A

- Q.「使い方分からん」
    - A. 基本的にはシンプル極まりないはずなので試行錯誤でお願いします
- Q.「バグ多いんだけど」
    - A. 思いつきで突貫で作ったので… issue あげてくれれば対応します
- Q.「名前が厨二臭いんだけど」
    - A. かっこいいでしょ？ :-D

## 独り言

構文の書き換えなどが多少ありますが、思想として「IDE との親和性」があります。

例えば修飾子記号を `|` から `|>` に変えても動きはしますがおそらく phpstorm で警告が出るでしょう。
あるいは `{}` 構文にして開始・終了タグを書き換えるようにしても動きはするでしょうが、定義ジャンプなどの機能が全く活かせませんし、シンタックスエラーも検出できません（そもそもそんなことをするなら Smarty でいい）。

素の php の機能を活かしつつ、あまり独自構文を導入しないのが基本コンセプトです。

あと最近の php は元々テンプレートエンジンだったという過去を忘れつつあるので、アンチテーゼ的にこういうエンジンがあっても良いんじゃないかと思ったのが開発動機です。
具体的には

- ASP タグが消えた（まぁ使ってないけど…）
- ショートオープンタグが消える？（結局却下されたけどたびたび槍玉に挙がる）
- デフォルトエスケープ RFC が却下された（却下されたやつはシンタックスがキモいけど、似たような機能はあってもいいと思ってる）

などです。

「Web 開発に適した言語」を謳っていて、それなりに平易なテンプレート構文があるならば `<?- "html's string" ?>` のような構文を導入してくれても良いと思っています。
逆に php 本体が自動エスケープやパイプライン演算子を導入したら本エンジンは不要になるでしょう（レイアウト機能は惜しいけど）。

また、巷の有名なテンプレートエンジンは非常に高機能ですが、「素の php に毛が生えた程度でいいのでちょろっとレンダリングしたい」という場合には少々オーバースペックです。
さらにそういう状況では往々にして view ファイル内にロジカルな php コードが書かれたりするので、独自構文だとちょっと使いにくいのです。

## License

MIT

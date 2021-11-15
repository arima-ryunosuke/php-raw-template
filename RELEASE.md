# RELEASE

バージョニングはセマンティックバージョニングでは**ありません**。

| バージョン   | 説明
|:--           |:--
| メジャー     | 大規模な仕様変更の際にアップします（クラス構造・メソッド体系などの根本的な変更）。<br>メジャーバージョンアップ対応は多大なコストを伴います。
| マイナー     | 小規模な仕様変更の際にアップします（中機能追加・メソッドの追加など）。<br>マイナーバージョンアップ対応は1日程度の修正で終わるようにします。
| パッチ       | バグフィックス・小機能追加の際にアップします（基本的には互換性を維持するバグフィックス）。<br>パッチバージョンアップは特殊なことをしてない限り何も行う必要はありません。

なお、下記の一覧のプレフィックスは下記のような意味合いです。

- change: 仕様変更
- feature: 新機能
- fixbug: バグ修正
- refactor: 内部動作の変更
- `*` 付きは互換性破壊

## x.y.z

- nikic/php-parser に移行したいけど AST でショートタグが正規化されてしまう？
  - microsoft/tolerant-php-parser を試す
- パス周りがメチャクチャなので正規化して realpath したい

## 1.2.8

- [change][Template] resolvePath はライブラリ外でも有用なので protected にする

## 1.2.7

- [feature][Renderer] 互換性のある型の除去機能
- [feature][Renderer] gatherVariable の収集を種別単位で指定可能にした

## 1.2.6

- bump version
  - php: 7.4
- [change][Renderer] 変数埋め込みの改善
- [change][Renderer] strip の実装を外部化
- [fixbug][RewriteWrapper] カスタムタグの書き換えで html エスケープされたり php タグで即死したりする不具合を修正
- [feature][HtmlString] エスケープされない HtmlString を導入
- [refactor][Source] 行数の取得の厳密化

## 1.2.5

- [feature] package update

## 1.2.4

- [change][Renderer] gatherVariable の仕様変更
- [fixbug][Renderer] constFilename の書き出しのアトミック化

## 1.2.3

- [feature][Renderer] アサイン変数をテンプレートごとに返せるように拡張
- [feature][Template] assign の実装
- [fixbug][RewriteWrapper] isset/empty が誤作動する不具合を修正

## 1.2.2

- [feature][RewriteWrapper] varExpander を実装
- [feature][Renderer] specialVariable オプションを追加
- [feature][RewriteWrapper] & 修飾子の実装
- [fixbug][RewriteWrapper] カスタムタグでトークンが増減するとエラーになる不具合を修正
- [fixbug][RewriteWrapper] 修飾子の後に ?? が来るとパースエラーになる不具合を修正
- [change][Renderer] gather の仕方を変更

## 1.2.1

- [fixbug][Template] ブロックをネストしたとき親で使われていないと内容が消えてしまう不具合を修正

## 1.2.0

- [feature][RewriteWrapper] "{$value.key}" の埋め込み構文に対応
- [fixbug][RewriteWrapper] custom タグで属性に php タグがあると誤作動する不具合を修正
- [*change][RewriteWrapper] access key の多段呼び出しが長すぎるので可変引数で対応
  - defaultGetter を指定している場合は修正が必要

## 1.1.7

- [refactor][Renderer] 互換性破壊を revert
- [fixbug][Renderer] typeMapping でオリジナルの型が出力されてしまう不具合を修正

## 1.1.6

- [feature][Template] ブロックのネストに対応
- [feature][Renderer] typeMapping オプションを追加
- [refactor][Source] メソッドの整理
- [refactor][Renderer] gather と const の処理が煩雑だったのでシンプル化
- [fixbug][RewriteWrapper] アクセスキーを -> にするとメソッド呼び出しも変換される不具合を修正

## 1.1.5

- [all] composer update
- [feature][Template] load メソッドを追加
- [fixbug][Renderer] ファイルが存在しない時に妙なエラーになる不具合を修正

## 1.1.4

- [feature][Renderer] エラーハンドリングがやりやすいように例外を書き換え
- [change][Renderer] compileDir のデフォルトを設定
  - 一時ディレクトリに設定するので互換性は壊れない
- [change][Renderer] phar の特別扱いを除去
  - 直接実行がなくなるだけなので互換性は壊れない

## 1.1.3

- [feature][RewriteWrapper] カスタムタグハンドラを実装
- [feature][Renderer] デフォルトハンドラとして strip を登録

## 1.1.2

- [feature][RewriteWrapper] ?? 演算子の対応

## 1.1.1

- [feature][RewriteWrapper] 自動エスケープ無効化オプションを追加

## 1.1.0

- [fixbug][Renderer] constsFile のマージが動いていなかった不具合を修正
- [feature][RewriteWrapper] 静的メソッドを呼べるように拡張
- [*change][Template] import を新設して include の意味合いを変更

## 1.0.2

- [change][Renderer] エラー時の出力は捨てる
- [feature][RewriteWrapper] defaultNamespace で基底名前空間を指定できるようにした
- [feature][Template] getFilename を実装
- [refactor][Source] ショートタグの読み替えを Source の責務に変更

## 1.0.1

- [feature][Template] content メソッドの実装
- [change][Renderer] エラー抑止時はハンドリングを無効にする
- [fixbug][Renderer] スタックトレースの書き換えで notice が出る不具合を修正
- [fixbug][Template] 親に存在して子に存在しないブロックがあると notice が出る不具合を修正

## 1.0.0

- 公開

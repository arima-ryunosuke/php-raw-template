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
- パス周りがメチャクチャなので正規化して realpath したい

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

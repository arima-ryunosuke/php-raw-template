<?php

namespace ryunosuke\NightDragon;

class Template
{
    private Renderer $renderer;

    private string $filename;

    private array $vars       = [];
    private array $parentVars = [];

    private self $original;

    private self $parent;

    private array $blocks = [];

    private array $currentBlocks = [];

    public function __construct(Renderer $renderer, string $filename)
    {
        $this->renderer = $renderer;
        $this->filename = $filename;
        $this->original = $this;
    }

    public function __get(string $name)
    {
        return $this->vars[$name];
    }

    public function getFilename(): string
    {
        return $this->renderer->resolvePath($this->filename);
    }

    /**
     * テンプレートに変数をアサインする
     *
     * ここでアサインした変数は単純なローカル変数とは違い、エンジンが「アサインした」と認識する。
     * そのため親テンプレート、 include/import などに渡ることになる。
     *
     * ただし注意点として、assign した変数はローカルコンテキストに生えない。
     * アクセスには $this->>varname を使用する必要がある。
     * （ローカルコンテキストに生えている varname は親や子から渡ってきた変数である）。
     */
    public function assign(string|array $name, mixed $value = null): array
    {
        if (!is_array($name)) {
            $name = [$name => $value];
        }
        foreach ($name as $k => $v) {
            $this->vars[$k] = $v;
        }
        $this->renderer->setAssignedVar($this->filename, $name);
        return $name;
    }

    /**
     * 変数を指定してレンダリング
     */
    public function render(array $vars = [], array $parentVars = []): string
    {
        $this->vars = $vars;
        $this->parentVars = $parentVars;

        $contents = $this->fetch($this->renderer->compile($this->filename, $this->vars, $this->parentVars));

        if (isset($this->parent)) {
            assert(strlen(trim($contents)) === 0, "child template can't have content outside blocks [$contents]");
            return $this->parent->render($this->vars, $this->parentVars);
        }

        return $contents;
    }

    /**
     * テンプレートの継承を宣言する
     */
    public function extend(string $filename): static
    {
        assert(!isset($this->parent), "this template is already extended.");

        $this->parent = new static($this->renderer, $this->resolvePath($filename));
        $this->parent->original = $this->original;
        unset($this->original);
        return $this->parent;
    }

    /**
     * ブロックを開始する
     */
    public function begin(string $name): void
    {
        // 継承しないとブロック機能を使えない…ようにしようとしたけど止めた。継承を使わなくてもブロックに名前をつける意義はある
        // assert($this->parent !== null, "this template is not extended.");
        assert(!array_key_exists($name, $this->blocks), "block '$name' is already defined.");
        assert(!in_array($name, $this->currentBlocks, true), "block '$name' is nested.");

        if ($this->currentBlocks) {
            $this->blocks[end($this->currentBlocks)][] = ob_get_clean();
            ob_start();
        }

        $this->currentBlocks[] = $name;
        ob_start();
    }

    /**
     * ブロックを終了する
     */
    public function end(): void
    {
        assert(!empty($this->currentBlocks), "block is not begun.");

        $name = array_pop($this->currentBlocks);
        $this->blocks[$name][] = ob_get_clean();

        if (!isset($this->original) && $this->currentBlocks) {
            $this->blocks[end($this->currentBlocks)][$name] = null;
        }

        if (isset($this->original)) {
            $recho = function ($iterator) use (&$recho) {
                foreach ($iterator as $key => $block) {
                    if ($block === null) {
                        $recho($this->original->closestBlock($key));
                    }
                    elseif ($block instanceof \Closure) {
                        $recho($block());
                    }
                    else {
                        echo $block;
                    }
                }
            };
            $recho($this->original->closestBlock($name));
        }
    }

    /**
     * コンテンツ指定の begin ~ end
     *
     * 下記は同じ意味となる。
     *
     * - `<?php $this->block('block', 'hoge') ?>`
     * - `<?php $this->begin('block') ?>hoge<?php $this->end() ?>`
     *
     * コンテンツは省略すると空文字になるので「親がコンテンツを持たない場合」には単にこのメソッドを呼ぶだけで良い。
     */
    public function block(string $name, string $contents = ''): void
    {
        $this->begin($name);
        echo $contents;
        $this->end();
    }

    /**
     * 親ブロックの内容を出力する
     */
    public function parent(): void
    {
        assert($this->parent !== null, "this template is not extended.");
        assert(!empty($this->currentBlocks), "block is not begun.");

        $name = end($this->currentBlocks);
        $this->blocks[$name][] = ob_get_clean();
        $this->blocks[$name][] = fn() => $this->parent->closestBlock($name);
        ob_start();
    }

    /**
     * 指定ファイルを出力する
     *
     * ファイルはテンプレートファイルとしてレンダリングして取り込まれる。
     * 極論すると別テンプレートの結果を出力する。 $this は $this ではないし、begin ～ end のブロックはそのテンプレートのものとなる。
     * その分 $filename が継承をしていようと何をしていようとその出力が得られる。
     */
    public function import(string $filename, array $vars = []): void
    {
        $template = new static($this->renderer, $this->resolvePath($filename));
        echo $template->render($vars, $this->vars + $this->parentVars);
    }

    /**
     * 指定ファイルを取り込む
     *
     * ファイルはテンプレートファイルとして取り込まれる。
     * 極論するとコピペと同じ。 $this は $this だし、begin ～ end のブロックも自身のブロックとして定義される。
     * $filename が継承をしていたり（意図してない）ブロックを定義していたりするとおそらく意図通りには動かない。
     */
    public function include(string $filename, array $vars = []): void
    {
        echo $this->fetch($this->renderer->compile($this->resolvePath($filename), $vars, $this->vars + $this->parentVars), $vars);
    }

    /**
     * 指定ファイルをただのファイルとして出力する
     *
     * ファイルはただのテキストファイルとして取り込まれる。
     * 極論すると echo file_get_contents と同じ。
     */
    public function content(string $filename): void
    {
        $filename = $this->resolvePath($filename);
        echo file_get_contents($filename);
    }

    /**
     * 拡張子に基づいてよしなに取り込んで出力する
     *
     * - .php: php ファイルとして単純に require する
     * - .css: css ファイルとして style タグで囲んで出力する
     * - .js: js ファイルとして script タグで囲んで出力する
     * - 自身と同じ拡張子: テンプレートファイルとして import する
     * - glob 記法: マッチしたファイルで上記が全て呼ばれる
     * - その他: 現在の実装ではスルーされる
     */
    public function load(string $filename): void
    {
        $curExt = pathinfo($this->getFilename(), PATHINFO_EXTENSION);
        $filename = $this->resolvePath($filename);
        $files = glob($filename, GLOB_BRACE | GLOB_NOSORT);
        foreach ($files as $file) {
            switch (pathinfo($file, PATHINFO_EXTENSION)) {
                case $curExt:
                    $this->import($file);
                    break;
                case 'php':
                    require($file);
                    break;
                case 'js':
                    echo '<script type="text/javascript">' . file_get_contents($file) . '</script>';
                    break;
                case 'css':
                    echo '<style type="text/css">' . file_get_contents($file) . '</style>';
                    break;
            }
        }
    }

    /**
     * 指定されたパスを解決する
     *
     * 絶対パスはそのまま、相対パスは自身のディレクトリが基底となる。
     * compileDir やストリームラッパーなどの状態によらず、 __DIR__ などの定数は使用可能（適宜読み替えられる）。
     */
    protected function resolvePath(string $filename): string
    {
        if (path_is_absolute($filename)) {
            return $this->renderer->resolvePath($filename);
        }
        return dirname($this->getFilename()) . "/$filename";
    }

    /**
     * 指定配列を展開しつつファイルを require するキモメソッド
     */
    private function fetch(string $filename, array $vars = []): string
    {
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        return (function () {
            ob_start();
            extract(func_get_arg(1));
            require func_get_arg(0);
            return ob_get_clean();
        })($filename, $vars + $this->vars + $this->parentVars);
    }

    /**
     * 指定ブロックを持つ直上の親を返す（自身も含む）
     *
     * @return string[] 指定ブロック
     */
    private function closestBlock(string $name): array
    {
        $that = $this;
        while (!isset($that->blocks[$name])) {
            $that = $that->parent;
            assert($that !== null, "undefined block '$name'."); // public メソッドの範疇では基本的にありえない
        }
        return $that->blocks[$name];
    }
}

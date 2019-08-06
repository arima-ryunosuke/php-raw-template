<?php

namespace ryunosuke\NightDragon;

class Renderer
{
    const META_COMMENT              = '# meta template data';
    const VARIABLE_COMMENT          = '// using variables:';
    const MODIFIER_FUNCTION_COMMENT = '// using modifier functions:';
    const ACCESS_KEY_COMMENT        = '// using array keys:';

    const DEFAULT_PROTOCOL = 'RewriteWrapper';

    /** @var bool デバッグモード */
    private $debug;

    /** @var bool エラーハンドリングを内部で行うか */
    private $errorHandling;

    /** @var string ストリームプロトコル名 */
    private $wrapperProtocol;

    /** @var Template テンプレートクラス名 */
    private $templateClass;

    /** @var string コンパイル済ディレクトリ */
    private $compileDir;

    /** @var array テンプレート書き換えオプション */
    private $gatherOptions = [];

    /** @var array レンダリングオプション */
    private $renderOptions = [];

    /** @var array ファイルシステムのキャッシュ */
    private $stats = [];

    /** @var array アサインされたグローバル変数（テンプレート変数とは別） */
    private $globalVars = [];

    /** @var array レンダリングに使用されたすべての変数 */
    private $assignedVars = [];

    /** @var array レンダリング中に出くわした修飾子・配列キーを保持する配列 */
    private $consts = ['modifier' => [], 'accessor' => []];

    /**
     * ENT_QUOTES で htmlspecialchars するだけのラッパー関数
     *
     * デフォルトオプションとして html を指定したいがための実装だけど、ショートタグを書き換える関係上可変引数に対応している。
     *
     * @param string|null ...$strings 文字列
     * @return string html 文字列
     */
    public static function html(?string ...$strings): string
    {
        $result = '';
        foreach ($strings as $string) {
            $result .= htmlspecialchars($string, ENT_QUOTES);
        }
        return $result;
    }

    /**
     * 配列アクセス可能なら `$value[$key]`, そうでないなら `$value->$key` アクセスする
     *
     * ArrayAccess なオブジェクトは [$key] を優先する。
     *
     * @param array|object $value キーアクセス可能ななにか
     * @param string $key キー
     * @return mixed キーの値
     */
    public static function access($value, string $key)
    {
        return is_arrayable($value) ? $value[$key] : $value->$key;
    }

    public function __construct(array $options)
    {
        // オプションを弄ったら README も書き換えること
        $debug = $options['debug'] ?? false;
        $options += [
            // デバッグ系
            'debug'              => $debug,
            'errorHandling'      => $debug,
            'gatherVariable'     => $debug,
            'gatherModifier'     => $debug,
            'gatherAccessor'     => $debug,
            'constFilename'      => null,
            // インジェクション系
            'wrapperProtocol'    => Renderer::DEFAULT_PROTOCOL,
            'templateClass'      => '\\' . Template::class,
            // ディレクトリ系
            'compileDir'         => null,
            // コンパイルオプション系
            'compatibleShortTag' => false,
            'defaultFilter'      => '\\' . Renderer::class . '::html',
            'defaultGetter'      => '\\' . Renderer::class . '::access',
            'defaultCloser'      => "\n",
            'varModifier'        => '|',
            'varReceiver'        => '$_',
            'varAccessor'        => '.',
        ];

        $this->debug = (bool) $options['debug'];
        $this->errorHandling = (bool) $options['errorHandling'];
        $this->wrapperProtocol = (string) $options['wrapperProtocol'];
        $this->templateClass = '\\' . ltrim($options['templateClass'], '\\');
        $this->compileDir = (string) $options['compileDir'];
        $this->gatherOptions = [
            'gatherVariable' => (bool) $options['gatherVariable'],
            'gatherModifier' => (bool) $options['gatherModifier'],
            'gatherAccessor' => (bool) $options['gatherAccessor'],
            'constFilename'  => (string) $options['constFilename'],
        ];
        $this->renderOptions = [
            'compatibleShortTag' => (bool) $options['compatibleShortTag'],
            'defaultFilter'      => (string) $options['defaultFilter'],
            'defaultGetter'      => (string) $options['defaultGetter'],
            'defaultCloser'      => (string) $options['defaultCloser'],
            'varModifier'        => (string) $options['varModifier'],
            'varReceiver'        => (string) $options['varReceiver'],
            'varAccessor'        => (string) $options['varAccessor'],
        ];

        if (strlen($this->compileDir)) {
            mkdir_p($this->compileDir);
            // Windows では {C:\compile}\C;\path\to\file, Linux では {/compile}/path/to/file となり、DS の有無が異なる
            // （Windows だと DS も含めないとフルパスにならない、Linux だと DS を含めるとフルパスにならない）
            $this->compileDir = realpath($this->compileDir) . (DIRECTORY_SEPARATOR === '\\' ? '\\' : '');
        }

        // スキームが phar は特別扱いで登録解除する（phar にすることでダイレクトに opcache を有効化できるメリットがある）
        if ($this->wrapperProtocol === 'phar' && in_array($this->wrapperProtocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($this->wrapperProtocol);
        }
        RewriteWrapper::register($this->wrapperProtocol);
    }

    public function __destruct()
    {
        // @todo `stream_wrapper_restore('phar')` したいけど複数インスタンスで誤作動しそうなので保留中

        if ($this->debug && strlen($this->gatherOptions['constFilename'])) {
            $this->outputConstFile($this->gatherOptions['constFilename'], $this->consts);
        }
    }

    public function resolvePath($filename)
    {
        // compile ディレクトリ内で __DIR__ を使うとその絶対パスになっているので元のパスに読み替える
        $filename = strtr(str_lchop($filename, $this->compileDir), [';' => ':']);

        // 同上。デバッグ中や compile ディレクトリ未設定時は常にストリームラッパー経由になる
        $filename = str_lchop($filename, $this->wrapperProtocol . '://dummy/');

        return $filename;
    }

    /**
     * テンプレートファイルのコンパイル
     *
     * ファイル名を与えるとコンパイルして読み込むべきファイル名を返す。
     *
     * @param string $filename テンプレートファイル名
     * @param array $vars 変数配列
     * @return string 読み込むべきファイル名
     */
    public function compile(string $filename, array $vars): string
    {
        if (isset($this->stats[$filename])) {
            return $this->stats[$filename];
        }

        $filename = $this->resolvePath($filename);

        // デバッグ中はメタ情報埋め込みのためテンプレートファイル自体に手を出す
        if ($this->debug && is_writable($filename)) {
            $content = file_get_contents($filename);
            $source = new Source($content, $this->renderOptions['compatibleShortTag'] ? Source::SHORT_TAG_REPLACE : Source::SHORT_TAG_NOTHING);

            $meta = [];
            if ($this->gatherOptions['gatherVariable']) {
                $variables = $this->gatherVariable($source, $this->renderOptions['varReceiver'], $vars);
                $meta[] = self::VARIABLE_COMMENT;
                $meta[] = array_sprintf($variables, '/** @var %s %s */', "\n");
            }
            if ($this->gatherOptions['gatherModifier'] || $this->gatherOptions['constFilename']) {
                $modifiers = $this->gatherModifier($source, $this->renderOptions['varModifier']);
                if ($this->gatherOptions['gatherModifier']) {
                    $meta[] = self::MODIFIER_FUNCTION_COMMENT;
                    $meta[] = array_sprintf($modifiers, 'true or define(%1$s, %2$s(...[]));', "\n");
                }
                if ($this->gatherOptions['constFilename']) {
                    $this->consts['modifier'] += $modifiers;
                }
            }
            if ($this->gatherOptions['gatherAccessor'] || $this->gatherOptions['constFilename']) {
                $accessors = $this->gatherAccessor($source, $this->renderOptions['varAccessor']);
                if ($this->gatherOptions['gatherAccessor']) {
                    $meta[] = self::ACCESS_KEY_COMMENT;
                    $meta[] = array_sprintf($accessors, 'true or define(%1$s, %1$s);', "\n");
                }
                if ($this->gatherOptions['constFilename']) {
                    $this->consts['accessor'] += $accessors;
                }
            }

            if ($meta) {
                $newcontent = (string) $source->replace([
                    T_OPEN_TAG,
                    function (Token $token) { return $token->id === T_COMMENT && trim($token->token) === self::META_COMMENT; },
                    Source::MATCH_MANY0,
                    T_CLOSE_TAG,
                ], "<?php\n" . self::META_COMMENT . "\n" . implode("\n", $meta) . "\n?>\n");

                // phpstorm が「変更された」と感知して ctrl+z が効かなくなるので書き換えられた場合のみ保存する
                if ($content !== $newcontent) {
                    file_put_contents($filename, $newcontent);
                }
            }
        }

        // キャッシュディレクトリが有効なら保存しておく＋実際の読み込みファイル名はそのキャッシュファイルにする
        if (strlen($this->compileDir)) {
            $fileid = $this->compileDir . strtr($filename, [':' => ';']);
            if ($this->debug || !file_exists($fileid)) {
                file_set_contents($fileid, file_get_contents($this->wrapperPath($filename)));
            }
        }
        else {
            $fileid = $this->wrapperPath($filename);
        }

        // 過程はどうあれデバッグ時はラッパー経由で直接実行する。さらにアサイン変数を溜め込んでおく
        if ($this->debug) {
            $fileid = $this->wrapperPath($filename);
            $this->assignedVars += $vars;
        }

        return $this->stats[$filename] = $fileid;
    }

    private function wrapperPath(string $path): string
    {
        return "$this->wrapperProtocol://dummy/$path?" . http_build_query($this->renderOptions);
    }

    private function detectType($var): string
    {
        // 配列は array じゃなくて Type[] にできる可能性がある
        if (is_array($var) && count($var) > 0) {
            $type = $this->detectType(array_shift($var));
            // 型がバラバラでも抽象型の可能性があるので反変を取る
            foreach ($var as $v) {
                $next = $this->detectType($v);
                if ($next === $type || is_subclass_of($next, $type)) {
                    continue;
                }
                elseif (is_subclass_of($type, $next)) {
                    $type = $next;
                    continue;
                }
                else {
                    $type = null;
                    break;
                }
            }
            if ($type) {
                return $type . '[]';
            }
        }

        // 無名クラスはファイル名なども含まれて一意じゃないので親クラス・実装インターフェースで名前引き
        if (is_object($var)) {
            $ref = new \ReflectionClass($var);
            if ($ref->isAnonymous()) {
                if ($pc = $ref->getParentClass()) {
                    $is = array_diff($ref->getInterfaceNames(), $pc->getInterfaceNames());
                    return array_sprintf(array_merge([$pc->name], $is), '\\%s', '|');
                }
                if ($is = $ref->getInterfaceNames()) {
                    return array_sprintf($is, '\\%s', '|');
                }
                // 本当に匿名ならどうしようもないので object
                return 'object';
            }
            return '\\' . get_class($var);
        }

        return gettype($var);
    }

    private function gatherVariable(Source $source, string $receiver, array $vars): array
    {
        $result = [];
        $result['$this'] = $this->templateClass;
        $result[$receiver] = "mixed";
        foreach ($source->match([T_VARIABLE]) as $tokens) {
            $code = (string) $tokens->shift();
            $vname = substr($code, 1);
            if (array_key_exists($vname, $vars)) {
                $result[$code] = $this->detectType($vars[$vname]);
            }
        }
        return $result;
    }

    private function gatherModifier(Source $source, string $modifier): array
    {
        $namespace = $source->namespace();

        $result = [];
        foreach ($source->match([
            $modifier,
            Source::MATCH_MANY1,
            [T_CLOSE_TAG, $modifier],
        ]) as $tokens) {
            $tokens->shrink();
            $funcname = (string) $tokens;
            if ($funcname[0] !== '\\' && !function_exists($funcname)) {
                $funcname = concat($namespace, '\\') . $funcname;
            }
            if (function_exists($funcname)) {
                $result[$funcname] = var_export($funcname, true);
            }
        }
        return $result;
    }

    private function gatherAccessor(Source $source, string $accessor): array
    {
        $result = [];
        foreach ($source->match([$accessor, T_STRING]) as $tokens) {
            $code = (string) $tokens->pop();
            $result[$code] = var_export($code, true);
        }
        return $result;
    }

    private function outputConstFile(string $filename, array $consts)
    {
        // 既に保存されているならマージする
        if (file_exists($filename)) {
            $current = [];
            $source = new Source(file_get_contents($filename));
            foreach ($source->match(['define', '(', T_CONSTANT_ENCAPSED_STRING, ',', Source::MATCH_ANY]) as $tokens) {
                $code = $tokens[2]->token;
                $name = eval("return $code;");
                if ($tokens[4]->equals(T_CONSTANT_ENCAPSED_STRING)) {
                    $current['accessor'][$name] = $code;
                }
                else {
                    $current['modifier'][$name] = $code;
                }
            }

            $consts['modifier'] += $current['modifier'] ?? [];
            $consts['accessor'] += $current['accessor'] ?? [];
        }

        // 競合したら modifier の方を優先する（callable な分 accessor より情報量が多い）
        $consts['accessor'] = array_diff_key($consts['accessor'], $consts['modifier']);

        ksort($consts['modifier']);
        ksort($consts['accessor']);

        $ms = self::MODIFIER_FUNCTION_COMMENT . "\n" . array_sprintf($consts['modifier'], 'define(%1$s, %2$s(...[]));', "\n");
        $as = self::ACCESS_KEY_COMMENT . "\n" . array_sprintf($consts['accessor'], 'define(%1$s, %1$s);', "\n");
        file_put_contents($filename, "<?php\n$ms\n$as\n");
    }

    /**
     * レンダラーオブジェクト自身に変数をアサインする
     *
     * いわゆる共通・グローバル変数となり、テンプレート変数側と競合した場合はテンプレート側が優先される。
     *
     * - 配列を与えるとすべて追加する
     * - $name, $value を与えるとその名前・値で1つだけアサインする
     *
     * @param string|array $name 変数名 or 変数配列
     * @param mixed $value アサインする値
     * @return $this
     */
    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->assign($k, $v);
            }
        }
        else {
            $this->globalVars[$name] = $value;
        }
        return $this;
    }

    /**
     * アサインされている変数を返す
     *
     * debug 時は一度でもレンダリングが走ったテンプレートの変数もまとめて返す。
     * 基本的に debug 時の使用を想定していて本運用環境での使用は推奨しない。
     *
     * @return array アサインされた全変数
     */
    public function getAssignedVars(): array
    {
        return $this->assignedVars;
    }

    /**
     * ファイル名を指定してレンダリング
     *
     * @param string $filename テンプレートファイル名
     * @param array $vars 変数配列
     * @return string レンダリングされたコンテント文字列
     */
    public function render(string $filename, array $vars = [])
    {
        /** @var Template $template */

        $templateClass = $this->templateClass;
        $template = new $templateClass($this, $filename);

        if ($this->errorHandling) {
            $already = set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$already) {
                if (error_reporting() === 0) {
                    return $already !== null ? $already(...func_get_args()) : false;
                }
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
        }

        $ob_level = ob_get_level();

        try {
            return $template->render($vars + $this->globalVars);
        }
        catch (\Throwable $t) {
            if ($this->errorHandling) {
                $this->rewriteException($t);
            }
            throw $t;
        }
        finally {
            while (ob_get_level() > $ob_level) {
                ob_end_flush();
            }
            if ($this->errorHandling) {
                restore_error_handler();
            }
        }
    }

    private function rewriteException(\Throwable $ex)
    {
        // @memo 例外の属性をリフレクションで書き換えるのはどうかと思うけど、投げ方を工夫するより後から書き換えたほうが楽だと思う

        $internal = function ($file) {
            return (parse_url($file)['scheme'] ?? '') === $this->wrapperProtocol;
        };
        $refile = function ($file) use ($internal) {
            return $internal($file) ? substr(parse_url($file)['path'] ?? '/', 1) : $file;
        };
        $nearest = function ($file, $line) {
            $LENGTH = 3;
            $partial = array_slice(file($file), max(0, $line - $LENGTH - 1), $LENGTH * 2, true);
            foreach ($partial as $n => $row) {
                $partial[$n] = ($n === $line - 1 ? '*' : ' ') . $row;
            }
            return rtrim(implode('', $partial)) . "\n";
        };

        $rewritten = [];

        // 投げ元がテンプレート（パースエラーとか変数の undefined とか）なら自身のファイル名とメッセージを書き換える
        if ($internal($ex->getFile())) {
            $rewritten['file'] = $refile($ex->getFile());
            $rewritten['message'] = $ex->getMessage() . "\nnear:\n" . $nearest($ex->getFile(), $ex->getLine());
        }

        // スタックトレースは共通で書き換えてしまう
        $rewritten['trace'] = array_map(function ($trace) use ($refile) {
            if (isset($trace['file'])) {
                $trace['file'] = $refile($trace['file']);
            }
            return $trace;
        }, $ex->getTrace());

        // リフレクションで書き戻し（コアな private に依存してるので念の為 hasProperty でチェックする）
        // ちなみに、どちらも Throwable だが trace はトップレベルの private なのでリフレクションを分岐しないと取得できない
        $refex = new \ReflectionClass($ex instanceof \Exception ? \Exception::class : \Error::class);
        foreach ($rewritten as $name => $value) {
            if ($refex->hasProperty($name)) {
                $property = $refex->getProperty($name);
                $property->setAccessible(true);
                $property->setValue($ex, $value);
            }
        }

        // と、いうようなことを全例外に対して行う
        if ($previous = $ex->getPrevious()) {
            $this->rewriteException($previous);
        }
    }
}

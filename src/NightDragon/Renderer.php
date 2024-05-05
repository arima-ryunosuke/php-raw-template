<?php

namespace ryunosuke\NightDragon;

class Renderer
{
    const META_COMMENT              = '# meta template data';
    const VARIABLE_COMMENT          = '// using variables:';
    const MODIFIER_FUNCTION_COMMENT = '// using modifier functions:';
    const ACCESS_KEY_COMMENT        = '// using array keys:';

    const FIXED    = 1 << 0; // $this などの固定変数
    const GLOBAL   = 1 << 1; // $this などの固定変数
    const ASSIGNED = 1 << 2; // アサインされている変数
    const USING    = 1 << 3; // 使用されている変数
    const DECLARED = 1 << 4; // 既存の @var 変数

    const DEFAULT_PROTOCOL = 'RewriteWrapper';

    private bool $debug;

    private bool $errorHandling;

    private string $wrapperProtocol;

    private string $templateClass;

    private string $compileDir;

    private array $gatherOptions;

    private array $renderOptions;

    private array $stats = [];

    private array $globalVars = [];

    private array $assignedVars = [];

    private array $consts = ['modifier' => [], 'accessor' => []];

    /**
     * ENT_QUOTES で htmlspecialchars するだけのラッパー関数
     *
     * デフォルトオプションとして html を指定したいがための実装だけど、ショートタグを書き換える関係上可変引数に対応している。
     */
    public static function html(\Stringable|string|null ...$strings): string
    {
        $result = '';
        foreach ($strings as $string) {
            if ($string instanceof HtmlString) {
                $result .= $string;
            }
            else {
                $result .= htmlspecialchars($string ?? '', ENT_QUOTES);
            }
        }
        return $result;
    }

    /**
     * 配列アクセス可能なら `$value[$key]`, そうでないなら `$value->$key` アクセスする
     *
     * ArrayAccess なオブジェクトは [$key] を優先する。
     */
    public static function access(mixed $value, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            $value = is_arrayable($value) ? $value[$key] : $value->$key;
        }
        return $value;
    }

    /**
     * html 的文字列から空白をよしなに除去する
     */
    public static function strip($html, array $attrs = []): string
    {
        return html_strip(trim($html, "\t\n\r "), [
            'error-level' => ($attrs['noerror'] ?? false) ? null : E_USER_NOTICE,
        ]);
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
            'typeMapping'        => [],
            'specialVariable'    => [],
            // インジェクション系
            'wrapperProtocol'    => Renderer::DEFAULT_PROTOCOL,
            'templateClass'      => '\\' . Template::class,
            // ディレクトリ系
            'compileDir'         => null,
            // コンパイルオプション系
            'customTagHandler'   => [
                'strip' => '\\' . Renderer::class . '::strip',
            ],
            'compatibleShortTag' => false,
            'defaultNamespace'   => '\\',
            'defaultClass'       => '',
            'defaultFilter'      => '\\' . Renderer::class . '::html',
            'defaultGetter'      => '\\' . Renderer::class . '::access',
            'defaultCloser'      => "\n",
            'nofilter'           => '', // for compatible. In the future the default will be "@"
            'varModifier'        => '|', // for compatible. In the future the default will be ['|', '&']
            'varReceiver'        => '$_',
            'varAccessor'        => '.',
            'varExpander'        => '', // for compatible. In the future the default will be "`"
        ];

        $explodes = fn($types) => array_map(fn($types) => array_flatten(array_map(fn($v) => explode('|', $v), (array) $types)), $types);

        // for compatible
        if ($options['gatherVariable'] === true) {
            $options['gatherVariable'] = self::DECLARED | self::FIXED | self::GLOBAL | self::ASSIGNED | self::USING;
        }

        $this->debug = (bool) $options['debug'];
        $this->errorHandling = (bool) $options['errorHandling'];
        $this->wrapperProtocol = (string) $options['wrapperProtocol'];
        $this->templateClass = '\\' . ltrim($options['templateClass'], '\\');
        $this->compileDir = (string) $options['compileDir'];
        $this->gatherOptions = [
            'gatherVariable'  => (int) $options['gatherVariable'],
            'gatherModifier'  => (bool) $options['gatherModifier'],
            'gatherAccessor'  => (bool) $options['gatherAccessor'],
            'constFilename'   => (string) $options['constFilename'],
            'typeMapping'     => $explodes((array) $options['typeMapping']),
            'specialVariable' => $explodes((array) $options['specialVariable']),
        ];
        $this->renderOptions = [
            'customTagHandler'   => (array) $options['customTagHandler'],
            'compatibleShortTag' => (bool) $options['compatibleShortTag'],
            'defaultNamespace'   => (array) $options['defaultNamespace'],
            'defaultClass'       => (array) $options['defaultClass'],
            'defaultFilter'      => (string) $options['defaultFilter'],
            'defaultGetter'      => (string) $options['defaultGetter'],
            'defaultCloser'      => (string) $options['defaultCloser'],
            'varModifier'        => ((array) $options['varModifier']) + [1 => ''],
            'varReceiver'        => (string) $options['varReceiver'],
            'varAccessor'        => (string) $options['varAccessor'],
            'varExpander'        => (string) $options['varExpander'],
            'nofilter'           => (string) $options['nofilter'],
        ];

        if (!strlen($this->compileDir)) {
            $this->compileDir = sys_get_temp_dir() . '/' . $this->wrapperProtocol;
        }
        @mkdir_p($this->compileDir);
        if (!(is_dir($this->compileDir) && is_writable($this->compileDir))) {
            throw new \InvalidArgumentException("{$this->compileDir} is not writable directory.");
        }
        // Windows では {C:\compile}\C;\path\to\file, Linux では {/compile}/path/to/file となり、DS の有無が異なる
        // （Windows だと DS も含めないとフルパスにならない、Linux だと DS を含めるとフルパスにならない）
        $this->compileDir = realpath($this->compileDir) . (DIRECTORY_SEPARATOR === '\\' ? '\\' : '');

        RewriteWrapper::register($this->wrapperProtocol);
    }

    public function __destruct()
    {
        if ($this->debug && strlen($this->gatherOptions['constFilename'])) {
            $this->outputConstFile($this->gatherOptions['constFilename'], $this->consts);
        }
    }

    public function resolvePath(string $filename): string
    {
        // compile ディレクトリ内で __DIR__ を使うとその絶対パスになっているので元のパスに読み替える
        $filename = strtr(str_lchop($filename, $this->compileDir), [';' => ':']);

        return $filename;
    }

    public function compile(string $filename, array $vars, array $parentVars = []): string
    {
        $this->setAssignedVar($filename, $vars);

        if (!$this->debug && isset($this->stats[$filename])) {
            return $this->stats[$filename];
        }

        $filename = $this->resolvePath($filename);
        if (!is_readable($filename)) {
            throw new \InvalidArgumentException("$filename is not readable.");
        }

        // デバッグ中はメタ情報埋め込みのためテンプレートファイル自体に手を出す
        if ($this->debug && is_writable($filename)) {
            $ropt = $this->renderOptions;
            $content = file_get_contents($filename);
            $source = new Source($content, $ropt['compatibleShortTag'] ? Source::SHORT_TAG_REPLACE : Source::SHORT_TAG_NOTHING);

            $E = fn($v) => var_export($v, true);
            $meta = [];
            if ($this->gatherOptions['gatherVariable']) {
                $variables = $this->gatherVariable($source, $ropt['varReceiver'], $vars, $parentVars);
                $meta[] = self::VARIABLE_COMMENT;
                $meta[] = array_sprintf($variables, '/** @var %s %s */', "\n");
            }
            if ($this->gatherOptions['gatherModifier']) {
                $modifiers = $this->gatherModifier($source, $ropt['varModifier'], $ropt['defaultNamespace'], $ropt['defaultClass']);
                if ($this->gatherOptions['constFilename']) {
                    $this->consts['modifier'] += $modifiers;
                }
                else {
                    $meta[] = self::MODIFIER_FUNCTION_COMMENT;
                    $meta[] = array_sprintf($modifiers, fn($v, $k) => "if (false) {function $k(...\$args){define({$E($k)}, $v(...[]));return $v(...\$args);}}", "\n");
                }
            }
            if ($this->gatherOptions['gatherAccessor']) {
                $accessors = $this->gatherAccessor($source, $ropt['varAccessor']);
                if ($this->gatherOptions['constFilename']) {
                    $this->consts['accessor'] += $accessors;
                }
                else {
                    $meta[] = self::ACCESS_KEY_COMMENT;
                    $meta[] = array_sprintf($accessors, fn($v, $k) => "true or define({$E($v)}, {$E($v)});", "\n");
                }
            }

            if ($meta) {
                $formatter = "@formatter";
                $newcontent = (string) $source->replace([
                    T_OPEN_TAG,
                    fn(Token $token) => $token->id === T_COMMENT && trim($token->text) === self::META_COMMENT,
                    Source::MATCH_MANY,
                    T_CLOSE_TAG,
                ], /** @lang */ "<?php\n" . self::META_COMMENT . "\n// $formatter:off\n" . implode("\n", $meta) . "\n// $formatter:on\n?>\n");

                // phpstorm が「変更された」と感知して ctrl+z が効かなくなるので書き換えられた場合のみ保存する
                if ($content !== $newcontent) {
                    file_put_contents($filename, $newcontent);
                }
            }
        }

        $fileid = $this->compileDir . strtr($filename, [':' => ';']);
        if ($this->debug || !file_exists($fileid)) {
            $path = "$this->wrapperProtocol://dummy/$filename?" . http_build_query($this->renderOptions);
            file_set_contents($fileid, file_get_contents($path));
        }

        return $this->stats[$filename] = $fileid;
    }

    private function detectType(mixed $var): array
    {
        $map = function ($type) use (&$map) {
            if (is_array($type)) {
                return array_flatten(array_map($map, $type));
            }
            if (isset($this->gatherOptions['typeMapping'][$type])) {
                return $this->gatherOptions['typeMapping'][$type];
            }
            return [$type];
        };

        // 配列は array じゃなくて Type[] にできる可能性がある
        $is_subclass_of = function ($v, $types) {
            foreach ($types as $type) {
                if (is_subclass_of($v, $type)) {
                    return true;
                }
            }
            return false;
        };
        if (is_array($var) && count($var) > 0) {
            $first = array_shift($var);
            $type = $this->detectType($first);
            // 型がバラバラでも抽象型の可能性があるので反変を取る
            foreach ($var as $v) {
                $next = $this->detectType($v);
                if ($next === $type || $is_subclass_of($v, $type)) {
                    continue;
                }
                elseif ($is_subclass_of($first, $next)) {
                    $type = $next;
                    continue;
                }
                else {
                    $type = null;
                    break;
                }
            }
            if ($type) {
                return array_sprintf($type, '%s[]');
            }
        }

        // 無名クラスはファイル名なども含まれて一意じゃないので親クラス・実装インターフェースで名前引き
        if (is_object($var)) {
            $ref = new \ReflectionClass($var);
            if ($ref->isAnonymous()) {
                if ($pc = $ref->getParentClass()) {
                    $is = array_diff($ref->getInterfaceNames(), $pc->getInterfaceNames());
                    return array_sprintf(array_merge([$pc->name], $map($is)), '\\%s');
                }
                if ($is = $ref->getInterfaceNames()) {
                    return array_sprintf($map($is), '\\%s');
                }
                // 本当に匿名ならどうしようもないので object
                return $map('object');
            }
            return $map('\\' . get_class($var));
        }

        return $map(var_type($var));
    }

    private function gatherVariable(Source $source, string $receiver, array $vars, array $parentVars): array
    {
        $results = [];

        // 固定変数
        $results[self::FIXED] = [
            '$this'   => [$this->templateClass],
            $receiver => ["mixed"],
        ];

        // グローバル変数
        foreach ($this->globalVars as $name => $var) {
            $results[self::GLOBAL]['$' . $name] = $this->detectType($var);
        }

        // アサイン変数（親 < 自身の優先度で代入）
        foreach (array_merge($parentVars, $vars) as $name => $var) {
            $results[self::ASSIGNED]['$' . $name] = $this->detectType($var);
        }

        // 使用変数
        foreach ($source->match([T_VARIABLE]) as $var) {
            $varname = (string) $var;
            $results[self::USING][$varname] = $results[self::GLOBAL][$varname] ?? $results[self::ASSIGNED][$varname] ?? [];
        }

        // 既存宣言
        foreach ($source->match([
            T_OPEN_TAG,
            fn(Token $token) => $token->id === T_COMMENT && trim($token->text) === self::META_COMMENT,
            Source::MATCH_MANY,
            T_CLOSE_TAG,
        ]) as $tokens) {
            preg_match_all('#/\*\* @var\s+([^\s]+?)\s+([^\s]+).*?\*/#msu', (string) $tokens, $matches, PREG_SET_ORDER);
            $results[self::DECLARED] = array_map(fn($v) => explode('|', $v), array_column($matches, 1, 2));
        }

        $result = [];
        foreach ($results as $kind => $vars) {
            if ($this->gatherOptions['gatherVariable'] & $kind) {
                foreach ($vars as $name => $types) {
                    $result[$name] = array_merge($result[$name] ?? [], $types, $this->gatherOptions['specialVariable'][$name] ?? []);
                }
            }
        }

        static $orders = null;
        $orders = $orders ?? array_flip(['mixed', 'object', 'callable', 'iterable', 'array', 'string', 'int', 'float', 'bool', 'null']);
        foreach ($result as $varname => $types) {
            $types = array_flip(array_unique(array_filter($types, 'strlen')));
            if (isset($types['mixed'])) {
                $types = ['mixed' => null];
            }
            foreach ($types as $type1 => $dummy1) {
                foreach ($types as $type2 => $dummy2) {
                    // 明示的な配列型が来ている場合 array は不要
                    if (preg_match('#\[]$#', $type1) && $type2 === 'array') {
                        unset($types[$type2]);
                    }
                    if (preg_match('#\[]$#', $type2) && $type1 === 'array') {
                        unset($types[$type1]);
                    }
                    // 継承関係は末端を優先
                    if (is_subclass_of($type1, $type2)) {
                        unset($types[$type2]);
                    }
                    if (is_subclass_of($type2, $type1)) {
                        unset($types[$type1]);
                    }
                }
            }

            uksort($types, fn($a, $b) => ($orders[$a] ?? $a) <=> ($orders[$b] ?? $b));
            $result[$varname] = implode('|', array_keys($types));
        }

        $result = array_filter($result);
        $result = array_shrink_key($results[self::DECLARED] ?? [], $result) + $result;

        return $result;
    }

    private function gatherModifier(Source $source, array $modifiers, array $namespaces, array $classes): array
    {
        $namespaces[] = $source->namespace();

        $result = [];
        foreach ($source->match([
            $modifiers,
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            [T_CLOSE_TAG, $modifiers[0], $modifiers[1], '(', '}'],
        ]) as $tokens) {
            $tokens->shrink();
            $tokens->strip();
            $stmt = (string) $tokens;
            foreach ($classes as $class) {
                if (method_exists($class, $stmt)) {
                    $funcname = trim("$class::$stmt", '\\');
                    $result[trim($stmt, '\\')] = "\\$funcname";
                    continue 2;
                }
            }
            foreach ($namespaces as $namespace) {
                $funcname = trim("$namespace\\$stmt", '\\');
                if (function_exists($funcname)) {
                    $result[trim($stmt, '\\')] = "\\$funcname";
                    continue 2;
                }
            }
        }
        return $result;
    }

    private function gatherAccessor(Source $source, string $accessor): array
    {
        $result = [];
        foreach ($source->match([$accessor, T_STRING]) as $tokens) {
            $code = (string) $tokens->pop();
            $result[$code] = $code;
        }
        return $result;
    }

    private function outputConstFile(string $filename, array $consts): ?int
    {
        if (!$consts['modifier'] && !$consts['accessor']) {
            return null;
        }

        return file_rewrite_contents($filename, function ($contents) use ($consts) {
            try {
                $current = eval('?>' . $contents);
            }
            catch (\Throwable) {
                $current = [];
            }

            $consts['modifier'] += $current['modifier'] ?? [];
            $consts['accessor'] += $current['accessor'] ?? [];

            ksort($consts['modifier']);
            ksort($consts['accessor']);

            $E = fn($v) => var_export($v, true);
            $V = fn($v) => $v;
            $ms = array_sprintf($consts['modifier'], fn($v, $k) => "function $k(...\$args){define({$E($k)}, $v(...[]));return $v(...\$args);}", "\n");
            $as = array_sprintf($consts['accessor'], fn($v, $k) => "define({$E($v)}, {$E($v)});", "\n");

            return <<<PHP
                <?php
                if (null) {
                    {$V(self::MODIFIER_FUNCTION_COMMENT)}
                    {$V(php_indent($ms, 4))}
                    {$V(self::ACCESS_KEY_COMMENT)}
                    {$V(php_indent($as, 4))}
                }
                return {$V(var_export2($consts, 1))};
                
                PHP;
        }, LOCK_EX);
    }

    /**
     * レンダラーオブジェクト自身に変数をアサインする
     *
     * いわゆる共通・グローバル変数となり、テンプレート変数側と競合した場合はテンプレート側が優先される。
     *
     * - 配列を与えるとすべて追加する
     * - $name, $value を与えるとその名前・値で1つだけアサインする
     */
    public function assign(string|array $name, mixed $value = null): static
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
     * デバッグ用のアサイン変数を設定する
     */
    public function setAssignedVar(string $filename, array $vars): static
    {
        if (!$this->debug) {
            return $this;
        }

        $filename = realpath($this->resolvePath($filename));
        if (!isset($this->assignedVars[$filename])) {
            $this->assignedVars[$filename] = [];
        }
        foreach ($vars as $k => $v) {
            $this->assignedVars[$filename][$k] = $v;
        }
        return $this;
    }

    /**
     * アサインされている変数を返す
     *
     * debug 時は一度でもレンダリングが走ったテンプレートの変数もまとめて返す。
     * 基本的に debug 時の使用を想定していて本運用環境での使用は推奨しない。
     */
    public function getAssignedVars(bool $perTemplate = false): array
    {
        if (!$perTemplate) {
            $result = [];
            foreach ($this->assignedVars as $vars) {
                $result += $vars;
            }
            return $result + $this->globalVars;
        }

        return ['(global)' => $this->globalVars] + $this->assignedVars;
    }

    /**
     * ファイル名を指定してレンダリング
     */
    public function render(string $filename, array $vars = []): string
    {
        /** @var Template $template */

        $templateClass = $this->templateClass;
        $template = new $templateClass($this, $filename);

        if ($this->errorHandling) {
            $already = set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$already) {
                if (!(error_reporting() & $errno)) {
                    return $already !== null ? $already(...func_get_args()) : false;
                }
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
        }

        $ob_level = ob_get_level();

        try {
            return $template->render($vars, $this->globalVars);
        }
        catch (\Throwable $t) {
            if ($this->errorHandling) {
                $this->rewriteException($t);
            }
            throw $t;
        }
        finally {
            while (ob_get_level() > $ob_level) {
                ob_end_clean();
            }
            if ($this->errorHandling) {
                restore_error_handler();
            }
        }
    }

    private function rewriteException(\Throwable $ex): void
    {
        // @memo 例外の属性をリフレクションで書き換えるのはどうかと思うけど、投げ方を工夫するより後から書き換えたほうが楽だと思う

        $refile = function ($file) {
            $innerfile = $this->resolvePath($file);
            return $innerfile === $file ? null : $innerfile;
        };

        $remessage = function ($file, $lines) {
            $LENGTH = 3;
            $min = max(0, min($lines) - $LENGTH - 1);
            $max = max($lines) + $LENGTH;
            $partial = array_slice(file($file), $min, $max - $min, true);
            foreach ($partial as $n => $row) {
                $partial[$n] = (in_array($n + 1, $lines, true) ? '*' : ' ') . $row;
            }
            return "\nnear:\n" . rtrim(implode('', $partial)) . "\n";
        };

        $rewritten = [];

        // 投げ元がテンプレート（パースエラーとか変数の undefined とか）なら自身のファイル名とメッセージを書き換える
        if ($innerfile = $refile($ex->getFile())) {
            $innerline = RewriteWrapper::getLineMapping($innerfile, $ex->getLine());
            $rewritten['file'] = $innerfile;
            $rewritten['line'] = first_value($innerline);
            $rewritten['message'] = $ex->getMessage() . $remessage($innerfile, $innerline);
        }

        // スタックトレースは共通で書き換えてしまう
        $rewritten['trace'] = array_map(function ($trace) use ($refile) {
            if (isset($trace['file'], $trace['line']) && $innerfile = $refile($trace['file'])) {
                $trace['file'] = $innerfile ?? $trace['file'];
                $trace['line'] = first_value(RewriteWrapper::getLineMapping($innerfile, $trace['line']));
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

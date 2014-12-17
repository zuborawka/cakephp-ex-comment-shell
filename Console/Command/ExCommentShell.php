<?php
/**
 * Created by PhpStorm.
 * User: Satoshi
 * Date: 2014/12/17
 * Time: 4:51
 */
App::uses('AppShell', 'Console/Command');

/**
 * Class ExCommentShell
 */
class ExCommentShell extends AppShell {

	/**
	 * 出力を抑制する
	 *
	 * @var bool 真の場合、出力は行われません
	 */
	public $silent = false;

	/**
	 * システムが Windows であると同時にコンソールの呼び出しであることを示す。
	 * $autoConvertEncodingWin と共に真の場合、出力時に SJIS-win 変換を行う。
	 *
	 * @var bool
	 */
	public $isWindowsConsole;

	/**
	 * Windows システムのコンソール呼び出し時に自動的に SJIS-win に変換する設定。
	 * コンソールの設定で UTF-8 出力を行う場合は false にして下さい。
	 *
	 * @var bool
	 */
	public $autoConvertEncodingWin = true;

	/**
	 * ExCommentShell::main() における選択候補から除外するメソッド名
	 *
	 * @var array
	 *
	 * @see ExCommentShell::main()
	 */
	protected $_excluded = array();

	public function initialize() {
		parent::initialize();

		$this->isWindowsConsole =
			strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' &&
			php_sapi_name() === 'cli';
	}

	public function main() {
		$this->_command($this->_excluded);
	}

	/**
	 * Shell::out() の拡張・改善
	 *  1.メッセージに配列を渡すことが可能
	 *  2.Windows 環境での文字化けを解消
	 *  3.$this->silent で出力を抑制
	 *
	 * @param null $message
	 * @param int  $newlines
	 * @param int  $level
	 *
	 * @return bool|int
	 */
	public function out($message = null, $newlines = 1, $level = Shell::NORMAL) {
		if ($this->silent) {
			return false;
		}
		if (is_array($message)) {
			ob_start();
			print_r($message);
			$message = ob_get_clean();
		}
		$message = $this->_eliminateWindowsEncoding($message);
		return parent::out($message, $newlines, $level);
	}

	/**
	 * フォーマットを利用した出力を行います
	 * 引数の数は可変長
	 *
	 * @param $format string
	 * @param $arg1 string
	 *
	 * @return bool|int
	 */
	public function outf($format, $arg1) {
		$args = func_get_args();
		array_shift($args);
		$out = vsprintf($format, $args);
		return $this->out($out);
	}

	public function err($message = null, $newlines = 1) {
		$message = $this->_eliminateWindowsEncoding($message);
		parent::err($message, $newlines);
	}

	/**
	 * Shell::in の改善
	 *  Windows 環境での文字化けを解消
	 *
	 * @param string $prompt
	 * @param null   $options
	 * @param null   $default
	 *
	 * @return mixed|string
	 */
	public function in($prompt = '', $options = null, $default = null) {
		$prompt = $this->_eliminateWindowsEncoding($prompt);
		$res = parent::in($prompt, $options, $default);
		return $this->_eliminateWindowsEncoding($res, true);
	}

	/**
	 * Shell::in の拡張
	 * 引数として配列を渡すことで、自動的に選択しを表示し、その選択の結果を返します。
	 * 入力値リストは引数の配列のキーになり、戻り値は引数の配列の値になります。
	 *
	 * 使い方
	 * $options = array(
	 *      'はい',
	 *      'いいえ',
	 * );
	 * $in = $this->inKey('続行しますか？', $options);
	 *
	 * コンソール画面には以下のように表示されます
	 * [0] はい
	 * [1] いいえ
	 *
	 * コンソールは 0 か 1 の入力を受け付けます
	 *
	 * その結果、 $in には "はい" または "いいえ" が入ります。
	 *
	 * @param      $prompt
	 * @param      $options
	 * @param null $default
	 * @param bool $toUpperCase
	 * @param bool $returnKey
	 *
	 * @return mixed
	 */
	public function inKey(
			$prompt,
			$options,
			$default = null,
			$toUpperCase = true,
			$returnKey = false
	) {
		if (is_string($options)) {
			$options = explode('/', $options);
			$options = array_combine($options, $options);
		}
		$keys = array_keys($options);
		foreach ($options as $k => $option) {
			$this->outf('[%s] %s', $k, $option);
		}

		if ($default) {
			if (in_array($default, $options)) {
				$flip = array_flip($options);
				$default = $flip[$default];
			}
			elseif (!isset($options[$default])) {
				$default = null;
			}
		}

		$in = $this->in($prompt, $keys, $default);

		if ($returnKey) {
			return $in;
		}

		if ($toUpperCase) {
			$in = strtoupper($in);
		}

		return $options[$in];
	}

	/**
	 * MyCakeEx::in の戻り値を strtoupper したものを返します。
	 * 入力値の検証が簡易化されます。
	 *
	 * @param      $prompt
	 * @param null $options
	 * @param null $default
	 *
	 * @return string
	 */
	public function inUpper($prompt, $options = null, $default = null) {
		$return = $this->in($prompt, $options, $default);
		return strtoupper($return);
	}

	/**
	 * MyCakeEx::in の戻り値を strtolower したものを返します。
	 * 入力値の検証が簡易化されます。
	 *
	 * @param      $prompt
	 * @param null $options
	 * @param null $default
	 *
	 * @return string
	 */
	public function inLower($prompt, $options = null, $default = null) {
		$return = $this->in($prompt, $options, $default);
		return strtolower($return);
	}

	/**
	 * コメントブロックをパースした配列を返します。
	 *
	 * コメント部分とアノテーション部分にわかれます。
	 * コメントは 'comment' で、アノテーションは '@' でアクセスします。
	 *
	 * コメントは改行がそのまま反映され、先頭のインデント部分は第二パラメータで指定できます。
	 * アノテーションは種類ごとに分類され、それぞれの配列に1つずつ格納されます。
	 *
	 * @param null   $docComment
	 * @param string $commentMarker
	 *
	 * @return array
	 */
	public function _parseCommentBlock($docComment = null, $commentMarker = '   : ') {
		$parsed = array(
			'comment' => '',
			'@' => array(),
		);

		$lines = explode("\n", $docComment);

		foreach ($lines as $line) {
			$line = trim($line);
			if (strpos($line, '*/') !== false || strpos($line, '/**') !== false) {
				continue;
			}
			$line = preg_replace('/^\*+/', '', $line);
			$_line = ltrim($line);
			if (preg_match('/^@([^ \t]+)([ \t]+(.+))?$/', $_line, $m)) {
				$type = $m[1];
				if (isset($m[3])) {
					$content = $m[3];
				}
				else {
					$content = '';
				}
				if (!isset($parsed['@'][$type])) {
					$parsed['@'] = array();
				}
				$parsed['@'][$type][] = $content;
				continue;
			}

			if ($line === '' && empty($parsed['comment'])) {
				continue;
			}

			$parsed['comment'] .= $line . PHP_EOL;
		}

		$parsed['comment'] = join(PHP_EOL . $commentMarker, explode(PHP_EOL, rtrim($parsed['comment'])));

		if ($parsed['comment']) {
			$parsed['comment'] = $commentMarker . $parsed['comment'];
		}

		return $parsed;
	}

	/**
	 * 選択可能なコマンドの配列を返します。
	 * 各配列には 'name' キーでコマンド名、'docComment' キーでパース済みのコメントがセットされています。
	 * 選択可能なコマンドは以下の要件を満たしたものです。
	 *
	 * 1. このメソッド内で定義した $protected に含まれていないもの。
	 * 2. 引数 $exclude に含まれていないもの。
	 * 3. 先頭が '_' で始まっていないもの。
	 *
	 * @param array $exclude 除外するメソッド名
	 *
	 * @return array
	 *
	 * @see ReflectionMethod::getDocComment()
	 * @see ExCommentShell::_parseCommentBlock()
	 */
	public function getCommands(array $exclude = array()) {
		$protected = array('main', 'initialize', 'startup', 'getCommands');
		foreach ($protected as $_protected) {
			if (!in_array($_protected, $exclude)) {
				$exclude[] = $_protected;
			}
		}
		$className = get_class($this);
		$classReflection = new ReflectionClass($className);
		$methods = $classReflection->getMethods(ReflectionMethod::IS_PUBLIC);
		foreach ($methods as $i => $method) {
			$name = $method->name;
			if (in_array($name, $exclude) ||
				$method->class !== $className
			) {
				unset($methods[$i]);
				continue;
			}
			if (substr($name, 0, 1) === '_') {
				unset($methods[$i]);
				continue;
			}

			$methodReflection = new ReflectionMethod($className, $name);
			$docComment = $methodReflection->getDocComment();
			$docComment = $this->_parseCommentBlock($docComment);
			$methods[$i] = compact('name', 'docComment');
		}
		return $methods;
	}

	/**
	 * Windows 環境での文字化けを解消
	 * Windows OS の場合、文字列をSJIS-winに変換します。
	 * Windowsのコンソールの設定を UTF-8 に変換する場合は、これを呼び出さないために
	 * ExCommentShell::$autoConvertEncodingWin を false にセットして下さい。
	 *
	 * @param      $message
	 *
	 * @param bool $reverse 真に設定すると逆変換をおこないます
	 *
	 * @return string
	 */
	protected function _eliminateWindowsEncoding($message, $reverse = false) {
		if (!is_string($message)) {
			ob_start();
			print_r($message);
			$message = ob_get_clean();
		}

		if ($this->isWindowsConsole && $this->autoConvertEncodingWin) {
			$enc_a = 'SJIS-win';
			$enc_b = 'UTF-8';
			if ($reverse) {
				$message = mb_convert_encoding($message, $enc_b, $enc_a);
			}
			else {
				$message = mb_convert_encoding($message, $enc_a, $enc_b);
			}
			return $message;
		}
		return $message;
	}

	/**
	 * クラスで定義されたパブリックメソッドを選択可能な形式で提示し、入力を受け付け、実行します。
	 * 'main', 'initialize', 'startup' は除外されます。
	 * アンダースコアから始まるメソッドは除外されます。
	 * そのほかに除外するメソッドを配列で任意に指定可能。
	 *
	 * 使い方
	 * public function main() {
	 *     $exclude = array(
	 *         'something', // 除外するメソッド
	 *     );
	 *     $this->_command($exclude);
	 * }
	 *
	 * @param array $exclude 除外するメソッド名の配列
	 *
	 * @return bool
	 */
	protected function _command($exclude = array()) {
		$this->out('Choose a command');
		$this->hr();

		$methods = $this->getCommands($exclude);

		// index を 1から始めるため
		array_unshift($methods, '');
		unset($methods[0]);

		$methods['Q'] = array('name' => 'Quit');
		foreach ($methods as $i => $method) {
			$append = '';
			if (! empty($method['docComment']['@']['deprecated'])) {
				$append .= '[非推奨]';
				$deprecatedComment = join("\n   * ", $method['docComment']['@']['deprecated']);
				if ($deprecatedComment !== '') {
					$append .= "\n   **************************************************************************";
					$append .= "\n   * " . $deprecatedComment;
					$append .= "\n   **************************************************************************";
				}
			}
			$this->out(sprintf('[%1$s] %2$s %3$s', $i, $method['name'], $append));
			if (!empty($method['docComment']['comment'])) {
				$this->out($method['docComment']['comment']);
				$this->out();
			}
		}

		$options = array_keys($methods);
		$this->out();
		$in = $this->in('Select ', $options, 'Q');
		if ($in === 'Q') {
			return false;
		}

		if (! empty($methods[$in]['docComment']['@']['deprecated'])) {
			$this->out('このメソッドは「非推奨」です。');
			if ($this->inUpper('本当に実行しますか？', array('Y', 'N'), 'N') === 'N') {
				$this->out('終了します');
				return false;
			}
		}

		$methodName = $methods[$in]['name'];
		$this->$methodName();

		return true;
	}

}

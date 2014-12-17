<?php
/**
 * Created by PhpStorm.
 * User: Satoshi
 * Date: 2014/12/17
 * Time: 2:47
 */
App::uses('ExCommentShell', 'ExCommentShell.Console/Command');

/**
 * Class MySampleShell
 */
class MySampleShell extends ExCommentShell {

	protected $_excluded = array('excludedMethod');

	/**
	 * 選択可能なメソッドの一つ。
	 * "Hello world" と出力します。
	 */
	public function helloWorld() {
		$this->out('Hello world');
	}

	/**
	 * もう一つの選択肢。
	 * "Welcome To Another World" と出力します。
	 */
	public function anotherWorld() {
		$this->out('Welcome To Another World');
	}

	/**
	 * 非推奨ですが実行可能です。
	 *
	 * @deprecated
	 */
	public function deprecatedMethod() {
		$this->out('だめよ～ダメダメ');
	}

	/**
	 * アンダースコアから開始されるメソッド名は無視されます。
	 */
	public function _ignoredMethod() {
	}

	/**
	 * public 以外のメソッドは無視されます。
	 */
	protected function notPublic() {
	}

	/**
	 * $_excluded にセットされたメソッドは無視されます。
	 */
	public function excludedMethod() {
	}
}

<?php
/**
 * Class file
 *
 * @package Admin_Notice
 */

namespace Admin_Notice;

/**
 * Admin notice exception to show an exception as an admin notice.
 */
class Exception extends \Exception {
	/**
	 * The level to show in the UI.
	 *
	 * @var NoticeLevels
	 */
	protected NoticeLevels $level;

	/**
	 * Create an Exception
	 *
	 * @param string          $message The message of the exception.
	 * @param int             $code The code of the exception.
	 * @param NoticeLevels    $level The notice level it should show in the UI.
	 * @param \Throwable|null $previous Optional previous Throwable.
	 */
	public function __construct( string $message, int $code, NoticeLevels $level, \Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->level = $level;
	}

	/**
	 * Create an Exception from a Throwable content.
	 *
	 * @param \Throwable   $t The Throwable from which the code and message will be copied.
	 * @param NoticeLevels $level The notice level to show in the UI.
	 */
	public static function fromThrowable( \Throwable $t, NoticeLevels $level ) {
		return new Exception( $t->getMessage(), $t->getCode(), $level, $t );
	}

	/**
	 * Get the level
	 */
	public function getLevel(): NoticeLevels {
		return $this->level;
	}
}

<?php
/**
 * Minimal WP_Error stub for unit tests that run without WordPress.
 *
 * Kept in its own file so PSR-1 sniffs about "files should contain
 * either declarations or side effects" don't trigger.
 *
 * @package dd32\WordPress\AddFromServer\Tests
 */

declare( strict_types=1 );

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class WP_Error {
	private string $code;
	private string $message;
	private array $data;

	public function __construct( string $code = '', string $message = '', array $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}

	public function get_error_data( $code = '' ) {
		if ( '' === $code || $code === $this->code ) {
			return $this->data;
		}
		return null;
	}
}

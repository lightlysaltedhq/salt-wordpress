<?php
/**
 * WP_REST_Response shim for the contact endpoint's REST callbacks.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * WP_REST_Response shim: data, status and headers.
	 */
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		public $status;

		/**
		 * Headers.
		 *
		 * @var array<string,string>
		 */
		public $headers = array();

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Data.
		 * @param int   $status Status.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Set a header.
		 *
		 * @param string $key   Name.
		 * @param string $value Value.
		 * @return void
		 */
		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		/**
		 * The data.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * The status.
		 *
		 * @return int
		 */
		public function get_status(): int {
			return $this->status;
		}

		/**
		 * The headers.
		 *
		 * @return array<string,string>
		 */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}

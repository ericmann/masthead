<?php
/**
 * Jetpack Social adapter.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Social;

use Masthead\Support\Dates;
use Throwable;
use WP_Error;
use WP_REST_Request;

/**
 * Dispatches internal REST requests to Jetpack's own publicize controllers (spike P3-03). It never
 * contacts a network itself: a share is an outbound call made by Jetpack.
 */
final class JetpackSocial implements ShareSource {

	/**
	 * Connection list route (spike P3-03, fact 2).
	 */
	private const ROUTE_CONNECTIONS = '/wpcom/v2/publicize/connections';

	/**
	 * Share-now route, with the post ID (spike P3-03, fact 3).
	 */
	private const ROUTE_SHARE = '/wpcom/v2/publicize/share-post';

	/**
	 * Scheduled actions route (spike P3-03, fact 4).
	 */
	private const ROUTE_SCHEDULE = '/wpcom/v2/publicize/scheduled-actions';

	/**
	 * Error codes (or fragments) that mean the feature is not there, as opposed to a failed attempt.
	 */
	private const UNAVAILABLE_PATTERN = '/not_available|not_connected|missing_token|no_possible_tokens|invalid_user_permission|plan|upgrade|quota|limit|no_route/i';

	/**
	 * Request dispatcher: ( method, route, params ) => decoded data or WP_Error.
	 *
	 * @var callable
	 */
	private $dispatch;

	/**
	 * Whether the dispatcher was injected (tests).
	 *
	 * @var bool
	 */
	private bool $injected;

	/**
	 * Constructor.
	 *
	 * @param callable|null $dispatch Dispatcher; defaults to rest_do_request().
	 */
	public function __construct( ?callable $dispatch = null ) {
		$this->injected = null !== $dispatch;
		$this->dispatch = $dispatch ?? array( self::class, 'rest_dispatch' );
	}

	/**
	 * Whether Jetpack is connected. Route registration is checked at call time, once REST has initialised.
	 *
	 * @return bool
	 */
	public static function usable(): bool {
		return class_exists( 'Jetpack' ) && \Jetpack::is_connection_ready();
	}

	/**
	 * Available when Jetpack is connected and the connection route is registered.
	 *
	 * @return bool
	 */
	public function available(): bool {
		if ( $this->injected ) {
			return true;
		}
		return self::usable() && $this->route_registered( self::ROUTE_CONNECTIONS );
	}

	/**
	 * Connections mapped to { id, service, display_name }.
	 *
	 * @return list<array{id:int,service:string,display_name:string}>
	 */
	public function connections(): array {
		$items = $this->call( 'GET', self::ROUTE_CONNECTIONS, array() );
		if ( is_wp_error( $items ) || ! is_array( $items ) ) {
			return array();
		}
		$connections = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['connection_id'] ) ) {
				continue;
			}
			$connections[] = array(
				'id'           => (int) $item['connection_id'],
				'service'      => (string) ( $item['service_name'] ?? '' ),
				'display_name' => (string) ( $item['display_name'] ?? '' ),
			);
		}
		return $connections;
	}

	/**
	 * Shares now. Jetpack takes the connections to skip, so every other connection is passed as skipped.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $message        Message.
	 * @param array  $connection_ids Connection IDs to share to.
	 * @return array{status:string,results:array}
	 */
	public function share_now( int $post_id, string $message, array $connection_ids ): array {
		$all = array_column( $this->connections(), 'id' );
		if ( array() === $all ) {
			return $this->result( 'unavailable', array(), __( 'No Jetpack Social connections are available.', 'masthead' ) );
		}
		$chosen  = array_map( 'intval', $connection_ids );
		$skipped = array_values( array_diff( $all, $chosen ) );
		$data    = $this->call(
			'POST',
			sprintf( '%s/%d', self::ROUTE_SHARE, $post_id ),
			array(
				'message'             => $message,
				'skipped_connections' => $skipped,
				'async'               => false,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $this->failure( $data, $chosen );
		}
		return array(
			'status'  => 'shared',
			'results' => $this->results( $chosen, true, '' ),
		);
	}

	/**
	 * Schedules a share, one scheduled action per connection.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $message        Message.
	 * @param array  $connection_ids Connection IDs.
	 * @param string $iso_datetime   ISO 8601 datetime with offset.
	 * @return array{status:string,results:array}
	 */
	public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array {
		$when = Dates::parse_iso( $iso_datetime );
		if ( null === $when ) {
			return $this->result( 'error', array(), __( 'The share time is not a valid date and time.', 'masthead' ) );
		}
		$results = array();
		foreach ( array_map( 'intval', $connection_ids ) as $connection_id ) {
			$data = $this->call(
				'POST',
				self::ROUTE_SCHEDULE,
				array(
					'post_id'       => $post_id,
					'connection_id' => $connection_id,
					'message'       => $message,
					'timestamp'     => $when->getTimestamp(),
				)
			);
			if ( is_wp_error( $data ) ) {
				if ( array() === $results ) {
					return $this->failure( $data, array( $connection_id ) );
				}
				$results[] = array(
					'connection_id' => $connection_id,
					'ok'            => false,
					'message'       => $data->get_error_message(),
				);
				continue;
			}
			$results[] = array(
				'connection_id' => $connection_id,
				'ok'            => true,
				'message'       => '',
			);
		}
		$all_ok = array() !== $results && array() === array_filter( $results, static fn( $r ) => ! $r['ok'] );
		return array(
			'status'  => $all_ok ? 'queued' : 'error',
			'results' => $results,
		);
	}

	/**
	 * Shape-only probe: no names, ids or text.
	 *
	 * @return array{available:bool,connection_count:int,routes:array<string,bool>}
	 */
	public function probe(): array {
		return array(
			'available'        => $this->available(),
			'connection_count' => count( $this->connections() ),
			'routes'           => array(
				'connections' => $this->route_registered( self::ROUTE_CONNECTIONS ),
				'share'       => $this->route_registered( self::ROUTE_SHARE ),
				'schedule'    => $this->route_registered( self::ROUTE_SCHEDULE ),
			),
		);
	}

	/**
	 * Default dispatcher: an internal REST request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $params Parameters.
	 * @return mixed Decoded data, or WP_Error.
	 */
	public static function rest_dispatch( string $method, string $route, array $params ) {
		$request = new WP_REST_Request( $method, $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_body_params( $params );
		}
		$response = rest_do_request( $request );
		return $response->is_error() ? $response->as_error() : $response->get_data();
	}

	/**
	 * Runs the dispatcher, turning a throwing dispatcher into a WP_Error.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $params Parameters.
	 * @return mixed
	 */
	private function call( string $method, string $route, array $params ) {
		try {
			return ( $this->dispatch )( $method, $route, $params );
		} catch ( Throwable $error ) {
			return new WP_Error( 'masthead_dispatch_failed', $error->getMessage() );
		}
	}

	/**
	 * Whether a route (or a route starting with it) is registered.
	 *
	 * @param string $prefix Route prefix.
	 * @return bool
	 */
	private function route_registered( string $prefix ): bool {
		foreach ( array_keys( rest_get_server()->get_routes() ) as $route ) {
			if ( 0 === strpos( (string) $route, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Maps an error to unavailable (the feature is not there) or error (the attempt failed).
	 *
	 * @param WP_Error $error          Error.
	 * @param int[]    $connection_ids Connections involved.
	 * @return array{status:string,results:array}
	 */
	private function failure( WP_Error $error, array $connection_ids ): array {
		$status = 1 === preg_match( self::UNAVAILABLE_PATTERN, (string) $error->get_error_code() ) ? 'unavailable' : 'error';
		return $this->result( $status, $connection_ids, $error->get_error_message() );
	}

	/**
	 * One result per connection, or a single reason result when there is none.
	 *
	 * @param string $status         Status.
	 * @param int[]  $connection_ids Connections.
	 * @param string $message        Reason.
	 * @return array{status:string,results:array}
	 */
	private function result( string $status, array $connection_ids, string $message ): array {
		return array(
			'status'  => $status,
			'results' => array() === $connection_ids
				? array(
					array(
						'connection_id' => 0,
						'ok'            => false,
						'message'       => $message,
					),
				)
				: $this->results( $connection_ids, false, $message ),
		);
	}

	/**
	 * Result entries for connections.
	 *
	 * @param int[]  $connection_ids Connections.
	 * @param bool   $ok             Outcome.
	 * @param string $message        Message.
	 * @return array<int,array{connection_id:int,ok:bool,message:string}>
	 */
	private function results( array $connection_ids, bool $ok, string $message ): array {
		return array_map(
			static fn( int $id ) => array(
				'connection_id' => $id,
				'ok'            => $ok,
				'message'       => $message,
			),
			$connection_ids
		);
	}
}

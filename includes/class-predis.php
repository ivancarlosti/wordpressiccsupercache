<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Predis client class.
 *
 * @package   ICC_GG_WordPress_Super_Cache
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 */

class ICC_GG_WordPress_Super_Cache_Predis
{
	/**
	 * The Redis client.
	 *
	 * @var mixed
	 */
	protected $redis;

	/**
	 * Connect to Redis.
	 *
	 * @param int|null $read_timeout The read timeout in seconds.
	 * @return void
	 */
	public function connect( $read_timeout = null )
	{
		if ( ! function_exists( 'stream_socket_client' ) ) {
			return;
		}

		// Load bundled Predis library.
		if ( ! class_exists( '\Predis\Client' ) ) {
			require_once ICC_GG_WORDPRESS_SUPER_CACHE_PLUGIN_PATH . '/dependencies/predis/predis/autoload.php';
		}

		$servers = false;
		$options = [];

		$parameters = [
			'scheme' => 'tcp',
			'host' => '127.0.0.1',
			'port' => 6379,
			'database' => 0,
			'timeout' => 1,
			'read_timeout' => $read_timeout ?? 1,
		];

		$settings = [
			'scheme',
			'host',
			'port',
			'path',
			'password',
			'database',
			'timeout',
			'read_timeout',
		];

		foreach ( $settings as $setting ) {
			$constant = sprintf( 'WP_REDIS_%s', strtoupper( $setting ) );

			if ( defined( $constant ) ) {
				$parameters[ $setting ] = constant( $constant );
			}
		}

		if ( isset( $parameters['password'] ) && $parameters['password'] === '' ) {
			unset( $parameters['password'] );
		}

		if ( defined( 'WP_REDIS_SHARDS' ) ) {
			$servers = WP_REDIS_SHARDS;
			$parameters['shards'] = $servers;
		} elseif ( defined( 'WP_REDIS_SENTINEL' ) ) {
			$servers = WP_REDIS_SERVERS;
			$parameters['servers'] = $servers;
			$options['replication'] = 'sentinel';
			$options['service'] = WP_REDIS_SENTINEL;
		} elseif ( defined( 'WP_REDIS_SERVERS' ) ) {
			$servers = WP_REDIS_SERVERS;
			$parameters['servers'] = $servers;
			$options['replication'] = 'predis';
		} elseif ( defined( 'WP_REDIS_CLUSTER' ) ) {
			$servers = $this->build_cluster_connection_array();
			$parameters['cluster'] = $servers;
			$options['cluster'] = 'redis';
		}

		if ( strcasecmp( 'unix', $parameters['scheme'] ) === 0 ) {
			unset( $parameters['host'], $parameters['port'] );
		}

		if ( isset( $parameters['read_timeout'] ) && $parameters['read_timeout'] ) {
			$parameters['read_write_timeout'] = $parameters['read_timeout'];
		}

		foreach ( [ 'WP_REDIS_SERVERS', 'WP_REDIS_SHARDS', 'WP_REDIS_CLUSTER' ] as $constant ) {
			if ( defined( $constant ) ) {
				if ( $parameters['database'] ) {
					$options['parameters']['database'] = $parameters['database'];
				}

				if ( isset( $parameters['password'] ) ) {
					if ( is_array( $parameters['password'] ) ) {
						$options['parameters']['username'] = WP_REDIS_PASSWORD[0];
						$options['parameters']['password'] = WP_REDIS_PASSWORD[1];
					} else {
						$options['parameters']['password'] = WP_REDIS_PASSWORD;
					}
				}
			}
		}

		if ( isset( $parameters['password'] ) ) {
			if ( is_array( $parameters['password'] ) ) {
				$parameters['username'] = array_shift( $parameters['password'] );
				$parameters['password'] = implode( '', $parameters['password'] );
			}

			if ( defined( 'WP_REDIS_USERNAME' ) ) {
				$parameters['username'] = WP_REDIS_USERNAME;
			}
		}

		if ( defined( 'WP_REDIS_SSL_CONTEXT' ) && ! empty( WP_REDIS_SSL_CONTEXT ) ) {
			if ( $servers ) {
				$options['parameters']['ssl'] = WP_REDIS_SSL_CONTEXT;
			} else {
				$parameters['ssl'] = WP_REDIS_SSL_CONTEXT;
			}
		}

		$this->redis = new \Predis\Client( $servers ?: $parameters, $options );
		$this->redis->connect();
	}

	/**
	 * Flushes the Redis cache using the `WP_REDIS_FLUSH_TIMEOUT`.
	 *
	 * By default only keys belonging to this site's effective prefix are
	 * deleted. The entire database is flushed only when selective flush is
	 * explicitly disabled in wp-config.php, in the environment or in the Redis
	 * tab.
	 *
	 * @param bool $throw_exception Whether to throw exception on error.
	 * @return bool
	 */
	public function flush( $throw_exception = false )
	{
		if ( is_null( $this->redis ) ) {
			$flush_timeout = defined( 'WP_REDIS_FLUSH_TIMEOUT' )
				? intval( WP_REDIS_FLUSH_TIMEOUT )
				: 5;
			try {
				$this->connect( $flush_timeout );
			} catch ( Exception $exception ) {
				if ( $throw_exception ) {
					throw $exception;
				}

				return false;
			}

			if ( is_null( $this->redis ) ) {
				return false;
			}
		}

		$prefix = ICC_GG_WordPress_Super_Cache_Key_Prefix::resolve();
		$selective = ICC_GG_WordPress_Super_Cache_Key_Prefix::selective_flush_enabled() && '' !== $prefix;
		$script = $selective ? $this->get_selective_flush_script( $prefix ) : null;

		try {
			if ( defined( 'WP_REDIS_CLUSTER' ) ) {
				foreach ( $this->redis->getIterator() as $master ) {
					if ( $selective ) {
						$master->eval( $script, 0 );
					} else {
						$master->flushdb();
					}
				}
			} elseif ( $selective ) {
				$this->redis->eval( $script, 0 );
			} else {
				$this->redis->flushdb();
			}
		} catch ( Exception $exception ) {
			if ( $throw_exception ) {
				throw $exception;
			}

			return false;
		}

		return true;
	}

	/**
	 * Builds the Lua script used to delete only this site's keys.
	 *
	 * The script mirrors the drop-in implementation: `SCAN` avoids blocking
	 * Redis while `DEL` removes the keys in batches. The prefix is sanitized and
	 * quoted so it cannot act as a glob pattern.
	 *
	 * @param string $prefix Effective key prefix.
	 * @return string
	 */
	protected function get_selective_flush_script( $prefix )
	{
		$pattern = ICC_GG_WordPress_Super_Cache_Key_Prefix::escape_lua_string(
			ICC_GG_WordPress_Super_Cache_Key_Prefix::glob_quote( $prefix ) . '*'
		);

		// phpcs:disable Squiz.PHP.Heredoc.NotAllowed
		return <<<LUA
			local cur = 0
			local i = 0
			local tmp
			repeat
				tmp = redis.call('SCAN', cur, 'MATCH', '{$pattern}')
				cur = tonumber(tmp[1])
				if tmp[2] then
					for _, v in pairs(tmp[2]) do
						redis.call('del', v)
						i = i + 1
					end
				end
			until 0 == cur
			return i
LUA;
		// phpcs:enable Squiz.PHP.Heredoc.NotAllowed
	}

	/**
	 * Flushes the entire Redis database using the `WP_REDIS_FLUSH_TIMEOUT`
	 * and will throw an exception if anything goes wrong.
	 *
	 * @return bool
	 */
	public function flushOrFail()
	{
		return $this->flush( true );
	}

	/**
	 * Builds a clean connection array out of redis clusters array.
	 *
	 * @return array
	 */
	protected function build_cluster_connection_array()
	{
		$cluster = array_values( WP_REDIS_CLUSTER );

		foreach ( $cluster as $key => $server ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			$components = parse_url( $server );

			if ( ! empty( $components['scheme'] ) ) {
				$scheme = $components['scheme'];
			} elseif ( defined( 'WP_REDIS_SCHEME' ) ) {
				$scheme = WP_REDIS_SCHEME;
			} else {
				$scheme = null;
			}

			if ( isset( $scheme ) ) {
				$cluster[ $key ] = sprintf(
					'%s://%s:%d',
					$scheme,
					$components['host'],
					$components['port']
				);
			} else {
				$cluster[ $key ] = sprintf(
					'%s:%d',
					$components['host'],
					$components['port']
				);
			}
		}

		return $cluster;
	}
}

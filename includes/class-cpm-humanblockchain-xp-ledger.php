<?php
/**
 * XP ledger — local table + Smallstreet cpm-dongtrader scan API.
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists seller (and future buyer) scan rows and POSTs to Smallstreet.
 */
class Cpm_Humanblockchain_Xp_Ledger {

	const TABLE = 'xp_ledger';

	/** Default POST route on Smallstreet. */
	const DEFAULT_SCAN_URL = 'https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/xp-ledger/scan';

	/** HTTP method for updating a scan row (PUT or PATCH). */
	const DEFAULT_SCAN_UPDATE_METHOD = 'PATCH';

	/**
	 * Seller share: 3% of $10 base → 30¢ → 30 + 21 zeros = 3×10²² XP (stored as string).
	 */
	const XP_SELLER_CENTS = 30;

	/**
	 * Buyer share: 7% of base → 70¢ → 70 + 21 zeros = 7×10²² XP (stored as string).
	 */
	const XP_BUYER_CENTS = 70;

	/**
	 * Trailing zero digits after the cents figure (21) so XP = cents × 10^21.
	 */
	const XP_DECIMAL_ZEROS = 21;

	/**
	 * Table name including prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Scan endpoint (override via filter).
	 *
	 * @return string
	 */
	public static function get_scan_endpoint_url() {
		$default = self::DEFAULT_SCAN_URL;
		$url       = apply_filters( 'cpm_hb_smallstreet_xp_ledger_scan_url', $default );
		$url       = is_string( $url ) ? trim( $url ) : '';
		if ( $url === '' ) {
			$url = $default;
		}
		return esc_url_raw( $url );
	}

	/**
	 * PUT/PATCH …/xp-ledger/scan/{LEDGER_ID}
	 *
	 * @param string|int $ledger_id Remote id (e.g. JSON "id").
	 * @return string
	 */
	public static function get_scan_update_url( $ledger_id ) {
		$ledger_id = trim( (string) $ledger_id );
		$base      = rtrim( self::get_scan_endpoint_url(), '/' );
		$url       = $base . '/' . rawurlencode( $ledger_id );
		return apply_filters( 'cpm_hb_smallstreet_xp_ledger_scan_update_url', $url, $ledger_id );
	}

	/**
	 * Uses the same key as other Dongtrader mobile routes.
	 *
	 * @return string
	 */
	private static function api_key() {
		if ( class_exists( 'Cpm_Humanblockchain_Smallstreet_Backorders' ) ) {
			return Cpm_Humanblockchain_Smallstreet_Backorders::get_api_key();
		}
		return '';
	}

	/**
	 * @param string $key API key.
	 * @return array<string, string>
	 */
	private static function request_headers( $key ) {
		return array(
			'Content-Type'                => 'application/json',
			'Accept'                      => 'application/json',
			'X-Dongtrader-Backorders-Key' => $key,
			'Authorization'               => 'Bearer ' . $key,
		);
	}

	/**
	 * Best-effort parse of remote ledger id from JSON.
	 *
	 * @param array<string, mixed>|null $data Decoded body.
	 * @return string
	 */
	private static function parse_remote_ledger_id( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}
		$candidates = array(
			'ledger_id',
			'id',
			'ID',
		);
		foreach ( $candidates as $k ) {
			if ( isset( $data[ $k ] ) && ( is_string( $data[ $k ] ) || is_numeric( $data[ $k ] ) ) ) {
				$v = trim( (string) $data[ $k ] );
				if ( $v !== '' ) {
					return $v;
				}
			}
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $candidates as $k ) {
				if ( isset( $data['data'][ $k ] ) && ( is_string( $data['data'][ $k ] ) || is_numeric( $data['data'][ $k ] ) ) ) {
					$v = trim( (string) $data['data'][ $k ] );
					if ( $v !== '' ) {
						return $v;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Truncate raw API body for display in browser (avoid huge JSON in AJAX).
	 *
	 * @param string $raw Raw body.
	 * @param int    $max Max bytes.
	 * @return string
	 */
	private static function truncate_api_body_for_display( $raw, $max = 8000 ) {
		$raw = (string) $raw;
		if ( strlen( $raw ) > $max ) {
			return substr( $raw, 0, $max ) . "\n…";
		}
		return $raw;
	}

	/**
	 * Shape returned to verify-OTP JSON for the seller success modal.
	 *
	 * @param string               $remote   synced|skipped|failed|transport_error|db_failed|invalid.
	 * @param string               $summary  Short human-readable line.
	 * @param int|null             $http_code HTTP status if applicable.
	 * @param string               $body     Raw response (truncated in output).
	 * @param array<string,mixed>|null $json Decoded JSON if valid.
	 * @param bool                 $success  True when Smallstreet returned 2xx.
	 * @return array<string,mixed>
	 */
	private static function xp_ledger_api_result( $remote, $summary, $http_code, $body, $json, $success ) {
		return array(
			'remote'    => $remote,
			'summary'   => $summary,
			'http_code' => $http_code,
			'body'      => self::truncate_api_body_for_display( $body ),
			'json'      => is_array( $json ) ? $json : null,
			'success'   => (bool) $success,
		);
	}

	/**
	 * XP string (cents × 10^21): e.g. seller 30 → "30000000000000000000000" (3×10²²).
	 *
	 * @param string $scan_type seller_scan|buyer_scan.
	 * @return string Digits only (no scientific notation).
	 */
	public static function xp_units_string_for_scan_type( $scan_type ) {
		$cents = 0;
		if ( 'seller_scan' === $scan_type ) {
			$cents = self::XP_SELLER_CENTS;
		} elseif ( 'buyer_scan' === $scan_type ) {
			$cents = self::XP_BUYER_CENTS;
		}
		if ( $cents <= 0 ) {
			return '0';
		}
		return (string) $cents . str_repeat( '0', self::XP_DECIMAL_ZEROS );
	}

	/**
	 * Filterable XP string for a ledger row.
	 *
	 * @param string $scan_type seller_scan|buyer_scan.
	 * @param int    $wp_user_id        WordPress user ID.
	 * @param string $transaction_code  Transaction / HB code.
	 * @return string
	 */
	private static function resolve_xp_units_string( $scan_type, $wp_user_id, $transaction_code ) {
		$default = self::xp_units_string_for_scan_type( $scan_type );
		/**
		 * Override XP string (digits). Default: seller 30¢ → 30 + 21 zeros; buyer 70¢ → 70 + 21 zeros.
		 *
		 * @param string $default          Default XP string.
		 * @param string $scan_type        seller_scan|buyer_scan.
		 * @param int    $wp_user_id       WP user ID.
		 * @param string $transaction_code HB transaction code.
		 */
		$out = apply_filters( 'cpm_hb_xp_ledger_xp_units_string', $default, $scan_type, $wp_user_id, $transaction_code );
		if ( ! is_string( $out ) || $out === '' || ! ctype_digit( $out ) ) {
			return $default;
		}
		return $out;
	}

	/**
	 * WP user id + email for Smallstreet XP ledger (both must refer to the same user when both are sent).
	 *
	 * @param int $wp_user_id WordPress user ID.
	 * @return array{ user_id: int, email: string }
	 */
	private static function user_identity_for_xp_api( $wp_user_id ) {
		$wp_user_id = (int) $wp_user_id;
		$email      = '';
		if ( $wp_user_id > 0 ) {
			$user = get_userdata( $wp_user_id );
			if ( $user && ! empty( $user->user_email ) ) {
				$email = sanitize_email( $user->user_email );
			}
		}
		return array(
			'user_id' => $wp_user_id,
			'email'   => is_string( $email ) ? $email : '',
		);
	}

	/**
	 * POST body: scan_type, email (or user_id), optional order_id, entry (cpm-dongtrader xp-ledger/scan).
	 *
	 * By default we send **email only** when the account has an email: Smallstreet validates that
	 * user_id and email refer to the *same user on their site*; our local WP user_id usually does
	 * not match their user table, which triggers rest_invalid_param. Remote lookup by email is stable.
	 *
	 * @param int               $wp_user_id WordPress user ID.
	 * @param string            $scan_type    seller_scan|buyer_scan.
	 * @param array<string,mixed> $entry      entry object.
	 * @param int|null          $order_id     Woo / shop order id (buyer_scan); omit from JSON when empty.
	 * @param string|null       $date_mysql   `date` field `Y-m-d H:i:s`; default site time.
	 * @return array<string,mixed>
	 */
	public static function build_xp_ledger_scan_payload( $wp_user_id, $scan_type, array $entry, $order_id = null, $date_mysql = null ) {
		$identity = self::user_identity_for_xp_api( $wp_user_id );
		$oid        = null !== $order_id ? (int) $order_id : 0;
		$payload    = array(
			'scan_type' => $scan_type,
		);
		/**
		 * Which identity fields to send: email_only (default), user_id_only, or both.
		 * Use "both" only if user_id is Smallstreet’s WP user id (e.g. stored meta), not this site’s id.
		 *
		 * @param string $mode        email_only|user_id_only|both.
		 * @param int    $wp_user_id  Local WordPress user ID.
		 * @param string $scan_type   seller_scan|buyer_scan.
		 * @param array  $entry       Entry object.
		 */
		$mode = apply_filters( 'cpm_hb_xp_ledger_identity_fields', 'email_only', $wp_user_id, $scan_type, $entry );
		if ( ! is_string( $mode ) || ! in_array( $mode, array( 'email_only', 'user_id_only', 'both' ), true ) ) {
			$mode = 'email_only';
		}

		if ( 'both' === $mode ) {
			if ( $identity['user_id'] > 0 ) {
				$payload['user_id'] = $identity['user_id'];
			}
			if ( $identity['email'] !== '' ) {
				$payload['email'] = $identity['email'];
			}
		} elseif ( 'user_id_only' === $mode ) {
			if ( $identity['user_id'] > 0 ) {
				$payload['user_id'] = $identity['user_id'];
			}
		} else {
			if ( $identity['email'] !== '' ) {
				$payload['email'] = $identity['email'];
			} elseif ( $identity['user_id'] > 0 ) {
				$payload['user_id'] = $identity['user_id'];
			}
		}

		if ( $oid > 0 ) {
			$payload['order_id'] = $oid;
		}

		$date_str = is_string( $date_mysql ) && $date_mysql !== ''
			? $date_mysql
			: self::default_ledger_patch_date_mysql( $wp_user_id, $entry, $oid > 0 ? $oid : null );
		$payload['date'] = $date_str;

		$payload['entry'] = $entry;

		/**
		 * Full JSON body for POST /xp-ledger/scan (before wp_json_encode).
		 *
		 * @param array<string,mixed> $payload    Keys: scan_type, email|user_id, optional order_id, date, entry.
		 * @param int                 $wp_user_id WordPress user ID.
		 * @param string              $scan_type  seller_scan|buyer_scan.
		 * @param array<string,mixed> $entry      Entry object.
		 * @param int|null            $order_id   Optional order id sent to API.
		 * @param string              $date_str   Datetime string sent as `date`.
		 */
		return apply_filters( 'cpm_hb_xp_ledger_scan_payload', $payload, $wp_user_id, $scan_type, $entry, $order_id, $date_str );
	}

	/**
	 * Default MySQL datetime for PATCH `date` (site timezone).
	 *
	 * @param int|null $wp_user_id Context user.
	 * @param array<string,mixed> $entry Entry object.
	 * @param int|null $order_id Order id if any.
	 * @return string Y-m-d H:i:s
	 */
	private static function default_ledger_patch_date_mysql( $wp_user_id, array $entry, $order_id ) {
		$d = (string) apply_filters( 'cpm_hb_xp_ledger_update_date', current_time( 'mysql' ), $wp_user_id, $entry, $order_id );
		return $d !== '' ? $d : current_time( 'mysql' );
	}

	/**
	 * PUT/PATCH body: user_id and/or email, optional order_id, date, entry (no scan_type).
	 *
	 * @param int    $wp_user_id WordPress user ID (seller when updating seller row).
	 * @param array<string,mixed> $entry Entry object.
	 * @param int|null $order_id  Woo / shop order id when known (buyer confirm).
	 * @param string|null $date_mysql Event datetime `Y-m-d H:i:s` for `date` field; default site time.
	 * @return array<string,mixed>
	 */
	public static function build_xp_ledger_update_payload( $wp_user_id, array $entry, $order_id = null, $date_mysql = null ) {
		$identity = self::user_identity_for_xp_api( $wp_user_id );
		$oid        = null !== $order_id ? (int) $order_id : 0;
		$payload    = array();
		/**
		 * @param string $mode        email_only|user_id_only|both.
		 * @param int    $wp_user_id  Local WordPress user ID.
		 * @param array  $entry       Entry object.
		 */
		$mode = apply_filters( 'cpm_hb_xp_ledger_update_identity_fields', 'email_only', $wp_user_id, $entry );
		if ( ! is_string( $mode ) || ! in_array( $mode, array( 'email_only', 'user_id_only', 'both' ), true ) ) {
			$mode = 'email_only';
		}

		if ( 'both' === $mode ) {
			if ( $identity['user_id'] > 0 ) {
				$payload['user_id'] = $identity['user_id'];
			}
			if ( $identity['email'] !== '' ) {
				$payload['email'] = $identity['email'];
			}
		} elseif ( 'user_id_only' === $mode ) {
			if ( $identity['user_id'] > 0 ) {
				$payload['user_id'] = $identity['user_id'];
			}
		} else {
			if ( $identity['email'] !== '' ) {
				$payload['email'] = $identity['email'];
			} elseif ( $identity['user_id'] > 0 ) {
				$payload['user_id'] = $identity['user_id'];
			}
		}

		if ( $oid > 0 ) {
			$payload['order_id'] = $oid;
		}

		$date_str = is_string( $date_mysql ) && $date_mysql !== ''
			? $date_mysql
			: self::default_ledger_patch_date_mysql( $wp_user_id, $entry, $order_id );
		$payload['date'] = $date_str;

		$payload['entry'] = $entry;

		return apply_filters( 'cpm_hb_xp_ledger_update_payload', $payload, $wp_user_id, $entry, $order_id, $date_str );
	}

	/**
	 * Latest seller_scan row for this HB transaction code and seller user.
	 *
	 * @param string $transaction_code HB-… code.
	 * @param int    $seller_wp_user_id Seller WP user id.
	 * @return object|null
	 */
	public static function get_seller_ledger_row( $transaction_code, $seller_wp_user_id ) {
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE transaction_id = %s AND scan_type = %s AND wp_user_id = %d ORDER BY id DESC LIMIT 1",
				$transaction_code,
				'seller_scan',
				(int) $seller_wp_user_id
			)
		);
		return ( $row && isset( $row->id ) ) ? $row : null;
	}

	/**
	 * PATCH/PUT remote Smallstreet scan row.
	 *
	 * @param string $remote_ledger_id Remote id.
	 * @param int    $wp_user_id       Identity for API (seller or buyer).
	 * @param array<string,mixed> $entry Entry object.
	 * @param int|null $order_id      Optional order id (seller update after buyer confirm).
	 * @param string|null $date_mysql Optional `date` for payload (defaults inside builder).
	 * @return array{ ok: bool, http_code: int, body: string, raw: string }
	 */
	private static function remote_update_scan( $remote_ledger_id, $wp_user_id, array $entry, $order_id = null, $date_mysql = null ) {
		$key = self::api_key();
		if ( $key === '' ) {
			return array( 'ok' => false, 'http_code' => 0, 'body' => '', 'raw' => '' );
		}
		$url      = self::get_scan_update_url( $remote_ledger_id );
		$payload  = self::build_xp_ledger_update_payload( $wp_user_id, $entry, $order_id, $date_mysql );
		$body     = wp_json_encode( $payload );
		$method   = strtoupper( (string) apply_filters( 'cpm_hb_xp_ledger_scan_update_method', self::DEFAULT_SCAN_UPDATE_METHOD, $remote_ledger_id, $wp_user_id, $entry ) );
		if ( ! in_array( $method, array( 'PUT', 'PATCH' ), true ) ) {
			$method = self::DEFAULT_SCAN_UPDATE_METHOD;
		}

		$args = apply_filters(
			'cpm_hb_smallstreet_xp_ledger_scan_update_request_args',
			array(
				'method'    => $method,
				'timeout'   => 25,
				'headers'   => self::request_headers( $key ),
				'body'      => $body,
				'sslverify' => true,
			),
			$url,
			$payload,
			$remote_ledger_id
		);

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'        => false,
				'http_code' => 0,
				'body'      => $response->get_error_message(),
				'raw'       => '',
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		return array(
			'ok'        => $code >= 200 && $code < 300,
			'http_code' => $code,
			'body'      => $raw,
			'raw'       => $raw,
		);
	}

	/**
	 * PATCH Smallstreet seller row to completed + update local seller_scan row (after buyer_scan POST succeeded).
	 *
	 * @param string   $transaction_code HB-… code.
	 * @param int      $seller_id        Seller WP user id.
	 * @param int|null $order_id         Primary order id from buyer confirmation (stored on seller row + PATCH).
	 */
	private static function complete_seller_ledger_row_after_buyer_remote( $transaction_code, $seller_id, $order_id = null ) {
		global $wpdb;
		$table      = self::table_name();
		$seller_row = self::get_seller_ledger_row( $transaction_code, $seller_id );
		if ( ! $seller_row ) {
			return;
		}
		if ( isset( $seller_row->scan_status ) && 'completed' === $seller_row->scan_status ) {
			return;
		}

		$seller_remote_id = isset( $seller_row->remote_ledger_id ) ? trim( (string) $seller_row->remote_ledger_id ) : '';
		if ( $seller_remote_id === '' ) {
			return;
		}

		$oid       = null !== $order_id ? (int) $order_id : 0;
		$seller_xp = isset( $seller_row->xp_units ) ? (string) $seller_row->xp_units : self::xp_units_string_for_scan_type( 'seller_scan' );
		$seller_entry_update = array(
			'transaction_id' => $transaction_code,
			'xp_units'       => $seller_xp,
			'scan_status'    => 'completed',
		);

		$date_str = self::default_ledger_patch_date_mysql( $seller_id, $seller_entry_update, $order_id );

		$upd = self::remote_update_scan( $seller_remote_id, $seller_id, $seller_entry_update, $oid > 0 ? $oid : null, $date_str );

		if ( $upd['ok'] ) {
			$seller_entry_data = array(
				'transaction_id' => $transaction_code,
				'xp_units'       => $seller_xp,
				'scan_status'    => 'completed',
				'date'           => $date_str,
			);
			if ( $oid > 0 ) {
				$seller_entry_data['order_id'] = $oid;
			}
			$seller_entry_json = wp_json_encode( $seller_entry_data );
			$seller_update     = array(
				'scan_status'        => 'completed',
				'entry_json'         => $seller_entry_json,
				'remote_sync_status' => 'synced',
				'remote_last_error'  => null,
				'ledger_date'        => $date_str,
				'updated_at'         => current_time( 'mysql' ),
			);
			if ( $oid > 0 ) {
				$seller_update['order_id'] = $oid;
			}
			$formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );
			if ( $oid > 0 ) {
				$formats[] = '%s';
			}
			$wpdb->update(
				$table,
				$seller_update,
				array( 'id' => (int) $seller_row->id ),
				$formats,
				array( '%d' )
			);
		} else {
			$wpdb->update(
				$table,
				array(
					'remote_last_error' => substr( $upd['body'] !== '' ? $upd['body'] : 'PATCH failed', 0, 500 ),
					'updated_at'        => current_time( 'mysql' ),
				),
				array( 'id' => (int) $seller_row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * POST local buyer_scan row + Smallstreet POST; PATCH seller row on remote + local to completed.
	 *
	 * @param int    $buyer_id  Buyer WP user id.
	 * @param int    $seller_id Seller WP user id.
	 * @param string $transaction_code HB-… code.
	 * @param int[]  $order_ids Woo / Smallstreet order ids.
	 */
	public static function record_buyer_scan_after_confirm( $buyer_id, $seller_id, $transaction_code, array $order_ids ) {
		$buyer_id = (int) $buyer_id;
		$seller_id = (int) $seller_id;
		$transaction_code = trim( (string) $transaction_code );
		if ( $buyer_id <= 0 || $seller_id <= 0 || $transaction_code === '' ) {
			return;
		}

		$scan_type = 'buyer_scan';
		$xp_units  = self::resolve_xp_units_string( $scan_type, $buyer_id, $transaction_code );

		$entry = array(
			'transaction_id' => $transaction_code,
			'xp_units'       => $xp_units,
			'scan_status'    => 'completed',
		);

		$order_ids_clean = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
		$primary_order_id = 0;
		if ( ! empty( $order_ids_clean ) ) {
			$primary_order_id = (int) apply_filters( 'cpm_hb_xp_ledger_buyer_primary_order_id', (int) $order_ids_clean[0], $order_ids_clean, $buyer_id, $transaction_code );
		}

		$ledger_date = self::default_ledger_patch_date_mysql(
			$buyer_id,
			$entry,
			$primary_order_id > 0 ? $primary_order_id : null
		);

		$entry_local = array_merge(
			$entry,
			array(
				'order_ids' => $order_ids_clean,
				'date'      => $ledger_date,
			)
		);

		global $wpdb;
		$table = self::table_name();

		$existing_buyer = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE wp_user_id = %d AND scan_type = %s AND transaction_id = %s LIMIT 1",
				$buyer_id,
				$scan_type,
				$transaction_code
			)
		);
		if ( $existing_buyer ) {
			$prev = $wpdb->get_row(
				$wpdb->prepare( "SELECT remote_sync_status FROM {$table} WHERE id = %d", (int) $existing_buyer )
			);
			if ( $prev && isset( $prev->remote_sync_status ) && 'synced' === $prev->remote_sync_status ) {
				self::complete_seller_ledger_row_after_buyer_remote(
					$transaction_code,
					$seller_id,
					$primary_order_id > 0 ? $primary_order_id : null
				);
			}
			return;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'wp_user_id'         => $buyer_id,
				'scan_type'          => $scan_type,
				'transaction_id'     => $transaction_code,
				'order_id'           => $primary_order_id > 0 ? $primary_order_id : null,
				'xp_units'           => $xp_units,
				'scan_status'        => 'completed',
				'entry_json'         => wp_json_encode( $entry_local ),
				'remote_sync_status' => 'pending',
				'ledger_date'        => $ledger_date,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return;
		}

		$buyer_row_id = (int) $wpdb->insert_id;
		$key          = self::api_key();

		if ( $key === '' ) {
			$wpdb->update(
				$table,
				array(
					'remote_sync_status' => 'skipped',
					'remote_last_error'  => __( 'Smallstreet API key not configured.', 'cpm-humanblockchain' ),
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $buyer_row_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		$post_payload = self::build_xp_ledger_scan_payload(
			$buyer_id,
			$scan_type,
			$entry,
			$primary_order_id > 0 ? $primary_order_id : null,
			$ledger_date
		);
		$post_url     = self::get_scan_endpoint_url();
		$post_args    = apply_filters(
			'cpm_hb_smallstreet_xp_ledger_scan_request_args',
			array(
				'timeout'   => 25,
				'headers'   => self::request_headers( $key ),
				'body'      => wp_json_encode( $post_payload ),
				'sslverify' => true,
			),
			$post_url,
			$post_payload
		);

		$response = wp_remote_post( $post_url, $post_args );
		if ( is_wp_error( $response ) ) {
			$wpdb->update(
				$table,
				array(
					'remote_sync_status' => 'failed',
					'remote_last_error'  => $response->get_error_message(),
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $buyer_row_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );
		$data      = json_decode( $raw_body, true );

		if ( $http_code >= 200 && $http_code < 300 ) {
			$remote_buyer_id = self::parse_remote_ledger_id( is_array( $data ) ? $data : null );
			$wpdb->update(
				$table,
				array(
					'remote_ledger_id'   => $remote_buyer_id !== '' ? $remote_buyer_id : null,
					'remote_sync_status' => 'synced',
					'remote_last_error'  => null,
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $buyer_row_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->update(
				$table,
				array(
					'remote_sync_status' => 'failed',
					'remote_last_error'  => $raw_body !== '' ? substr( $raw_body, 0, 500 ) : (string) $http_code,
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $buyer_row_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		self::complete_seller_ledger_row_after_buyer_remote(
			$transaction_code,
			$seller_id,
			$primary_order_id > 0 ? $primary_order_id : null
		);
	}

	/**
	 * After seller + ?proof=scan OTP: save row and sync to Smallstreet.
	 *
	 * @param int    $wp_user_id        WordPress user ID.
	 * @param string $transaction_code  HB-… code shown in the modal.
	 * @return array<string,mixed> Outcome (remote, summary, http_code, body, json, success) for logging or hooks; not sent to the browser.
	 */
	public static function record_seller_scan_after_verification( $wp_user_id, $transaction_code ) {
		$wp_user_id       = (int) $wp_user_id;
		$transaction_code = trim( (string) $transaction_code );
		if ( $wp_user_id <= 0 || $transaction_code === '' ) {
			return self::xp_ledger_api_result(
				'invalid',
				__( 'Missing user or transaction code.', 'cpm-humanblockchain' ),
				null,
				'',
				null,
				false
			);
		}

		$scan_type = 'seller_scan';
		$xp_units  = self::resolve_xp_units_string( $scan_type, $wp_user_id, $transaction_code );

		$entry = array(
			'transaction_id' => $transaction_code,
			'xp_units'       => $xp_units,
			'scan_status'    => 'pending',
		);

		$ledger_date = self::default_ledger_patch_date_mysql( $wp_user_id, $entry, null );
		$entry_store = array_merge( $entry, array( 'date' => $ledger_date ) );

		global $wpdb;
		$table = self::table_name();

		$inserted = $wpdb->insert(
			$table,
			array(
				'wp_user_id'         => $wp_user_id,
				'scan_type'          => $scan_type,
				'transaction_id'     => $transaction_code,
				'order_id'           => null,
				'xp_units'           => $xp_units,
				'scan_status'        => 'pending',
				'entry_json'         => wp_json_encode( $entry_store ),
				'remote_sync_status' => 'pending',
				'ledger_date'        => $ledger_date,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return self::xp_ledger_api_result(
				'db_failed',
				__( 'Could not save the XP ledger row locally.', 'cpm-humanblockchain' ),
				null,
				'',
				null,
				false
			);
		}

		$row_id = (int) $wpdb->insert_id;
		if ( $row_id <= 0 ) {
			return self::xp_ledger_api_result(
				'db_failed',
				__( 'Could not save the XP ledger row locally.', 'cpm-humanblockchain' ),
				null,
				'',
				null,
				false
			);
		}

		$key = self::api_key();
		if ( $key === '' ) {
			$wpdb->update(
				$table,
				array(
					'remote_sync_status' => 'skipped',
					'remote_last_error'  => __( 'Smallstreet API key not configured.', 'cpm-humanblockchain' ),
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $row_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return self::xp_ledger_api_result(
				'skipped',
				__( 'Smallstreet API key is not configured. Local ledger row saved; remote sync skipped.', 'cpm-humanblockchain' ),
				null,
				'',
				null,
				false
			);
		}

		$payload = self::build_xp_ledger_scan_payload( $wp_user_id, $scan_type, $entry, null, $ledger_date );

		$url  = self::get_scan_endpoint_url();
		$body = wp_json_encode( $payload );

		$args = apply_filters(
			'cpm_hb_smallstreet_xp_ledger_scan_request_args',
			array(
				'timeout'   => 25,
				'headers'   => self::request_headers( $key ),
				'body'      => $body,
				'sslverify' => true,
			),
			$url,
			$payload
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			$wpdb->update(
				$table,
				array(
					'remote_sync_status' => 'failed',
					'remote_last_error'  => $msg,
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $row_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return self::xp_ledger_api_result(
				'transport_error',
				__( 'Request to Smallstreet failed.', 'cpm-humanblockchain' ),
				null,
				$msg,
				null,
				false
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code >= 200 && $code < 300 ) {
			$remote_id = self::parse_remote_ledger_id( is_array( $data ) ? $data : null );
			$wpdb->update(
				$table,
				array(
					'remote_ledger_id'   => $remote_id !== '' ? $remote_id : null,
					'remote_sync_status' => 'synced',
					'remote_last_error'  => null,
					'updated_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $row_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return self::xp_ledger_api_result(
				'synced',
				__( 'Smallstreet accepted the XP ledger scan.', 'cpm-humanblockchain' ),
				$code,
				$raw,
				is_array( $data ) ? $data : null,
				true
			);
		}

		$err = $raw !== '' ? substr( $raw, 0, 500 ) : (string) $code;
		$wpdb->update(
			$table,
			array(
				'remote_sync_status' => 'failed',
				'remote_last_error'  => $err,
				'updated_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $row_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::xp_ledger_api_result(
			'failed',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Smallstreet returned an error (HTTP %d).', 'cpm-humanblockchain' ),
				$code
			),
			$code,
			$raw,
			is_array( $data ) ? $data : null,
			false
		);
	}

	/**
	 * Shop order IDs already tied to a buyer_scan or seller_scan row (column and/or entry_json).
	 *
	 * Used to hide those rows from the backorders table.
	 *
	 * @return int[] Unique positive order IDs.
	 */
	public static function get_linked_order_ids_for_backorders_display() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefixed constant.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, entry_json FROM {$table} WHERE scan_type IN (%s, %s)",
				'buyer_scan',
				'seller_scan'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$ids = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$oid = isset( $r['order_id'] ) ? (int) $r['order_id'] : 0;
			if ( $oid > 0 ) {
				$ids[ $oid ] = true;
			}
			$json = isset( $r['entry_json'] ) ? $r['entry_json'] : '';
			$entry = is_string( $json ) && $json !== '' ? json_decode( $json, true ) : null;
			if ( ! is_array( $entry ) ) {
				continue;
			}
			if ( ! empty( $entry['order_ids'] ) && is_array( $entry['order_ids'] ) ) {
				foreach ( $entry['order_ids'] as $x ) {
					$xi = (int) $x;
					if ( $xi > 0 ) {
						$ids[ $xi ] = true;
					}
				}
			}
			if ( isset( $entry['order_id'] ) ) {
				$xi = (int) $entry['order_id'];
				if ( $xi > 0 ) {
					$ids[ $xi ] = true;
				}
			}
		}
		$out = array_map( 'intval', array_keys( $ids ) );
		sort( $out );

		/**
		 * Order IDs to omit from the Smallstreet backorders list (already linked in xp_ledger).
		 *
		 * @param int[] $out Linked Woo/Smallstreet order IDs.
		 */
		return apply_filters( 'cpm_hb_backorders_exclude_linked_order_ids', $out );
	}

	/**
	 * Resolve shop order id from a Smallstreet backorders API row (matches JS getOrderIdFromRow).
	 *
	 * @param array<string, mixed> $row Row from backorders-by-mobile.
	 * @return int
	 */
	public static function backorder_row_order_id( array $row ) {
		if ( isset( $row['id'] ) && $row['id'] !== '' && null !== $row['id'] ) {
			$n = (int) $row['id'];
			return $n > 0 ? $n : 0;
		}
		if ( isset( $row['order_number'] ) && $row['order_number'] !== '' && null !== $row['order_number'] ) {
			$n = (int) $row['order_number'];
			return $n > 0 ? $n : 0;
		}
		return 0;
	}

	/**
	 * Drop backorder rows whose order id already appears in xp_ledger (buyer or seller scan).
	 *
	 * @param array<int, array<string, mixed>> $rows Backorder rows.
	 * @param int[]|null                       $linked_ids Optional precomputed list; when null, loads from DB.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_backorder_rows_excluding_linked_orders( array $rows, $linked_ids = null ) {
		if ( null === $linked_ids ) {
			$linked_ids = self::get_linked_order_ids_for_backorders_display();
		}
		if ( ! is_array( $linked_ids ) || empty( $linked_ids ) ) {
			return $rows;
		}
		$exclude = array_flip( array_map( 'intval', $linked_ids ) );
		$out     = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$oid = self::backorder_row_order_id( $row );
			if ( $oid > 0 && isset( $exclude[ $oid ] ) ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}
}

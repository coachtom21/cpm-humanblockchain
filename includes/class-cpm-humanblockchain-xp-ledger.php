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
	 * POST body: scan_type, entry, and user_id and/or email (per cpm-dongtrader xp-ledger/scan).
	 *
	 * By default we send **email only** when the account has an email: Smallstreet validates that
	 * user_id and email refer to the *same user on their site*; our local WP user_id usually does
	 * not match their user table, which triggers rest_invalid_param. Remote lookup by email is stable.
	 *
	 * @param int               $wp_user_id WordPress user ID.
	 * @param string            $scan_type    seller_scan|buyer_scan.
	 * @param array<string,mixed> $entry      entry object.
	 * @return array<string,mixed>
	 */
	public static function build_xp_ledger_scan_payload( $wp_user_id, $scan_type, array $entry ) {
		$identity = self::user_identity_for_xp_api( $wp_user_id );
		$payload    = array(
			'scan_type' => $scan_type,
			'entry'     => $entry,
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

		/**
		 * Full JSON body for POST /xp-ledger/scan (before wp_json_encode).
		 *
		 * @param array<string,mixed> $payload    Keys: scan_type, entry, optional user_id, optional email.
		 * @param int                 $wp_user_id WordPress user ID.
		 * @param string              $scan_type  seller_scan|buyer_scan.
		 * @param array<string,mixed> $entry      Entry object.
		 */
		return apply_filters( 'cpm_hb_xp_ledger_scan_payload', $payload, $wp_user_id, $scan_type, $entry );
	}

	/**
	 * After seller + ?proof=scan OTP: save row and sync to Smallstreet.
	 *
	 * @param int    $wp_user_id        WordPress user ID.
	 * @param string $transaction_code  HB-… code shown in the modal.
	 * @return array<string,mixed> Summary for AJAX modal: remote, summary, http_code, body, json, success.
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

		global $wpdb;
		$table = self::table_name();

		$inserted = $wpdb->insert(
			$table,
			array(
				'wp_user_id'         => $wp_user_id,
				'scan_type'          => $scan_type,
				'transaction_id'     => $transaction_code,
				'xp_units'           => $xp_units,
				'scan_status'        => 'pending',
				'entry_json'         => wp_json_encode( $entry ),
				'remote_sync_status' => 'pending',
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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

		$payload = self::build_xp_ledger_scan_payload( $wp_user_id, $scan_type, $entry );

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
}

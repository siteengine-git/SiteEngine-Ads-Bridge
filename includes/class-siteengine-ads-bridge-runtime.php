<?php
/**
 * Front-end runtime bridge for the SiteEngine Golden Account lead event.
 *
 * Ads Bridge does not call Google Ads directly. For the configured master Fluent
 * Form it adds a consent-gated, SHA-256-only payload to Fluent Forms' successful
 * Ajax confirmation response, then emits a `siteengine_ads_bridge_lead` dataLayer
 * event. GTM / Google tag configuration owns the SE | Lead conversion action.
 *
 * @package SiteEngineAdsBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-siteengine-ads-bridge-consent.php';

final class SiteEngine_Ads_Bridge_Runtime {

	/** @return void */
	public static function init() {
		add_filter( 'fluentform/submission_confirmation', array( __CLASS__, 'inject_confirmation_payload' ), 20, 5 );
		add_action( 'wp_footer', array( __CLASS__, 'render_datalayer_listener' ), 100 );
	}

	/**
	 * Add the canonical transaction id and hashed user identifiers to the successful
	 * Fluent Forms confirmation response. Receipt-backed ad_user_data consent is a
	 * hard gate; denied/unresolved receipts emit no advertising payload.
	 *
	 * @param array  $return_data  Fluent Forms return data.
	 * @param object $form         Fluent Forms form.
	 * @param array  $confirmation Confirmation settings.
	 * @param int    $insert_id    Submission ID.
	 * @param array  $form_data    Submitted form data.
	 * @return array
	 */
	public static function inject_confirmation_payload( $return_data, $form, $confirmation, $insert_id, $form_data ) {
		unset( $confirmation );

		$config  = SiteEngine_Ads_Bridge_Config::instance();
		$form_id = (int) $config->get( 'fluent_forms.form_id', 0 );
		$actual  = is_object( $form ) && isset( $form->id ) ? (int) $form->id : 0;

		if ( $form_id < 1 || $actual !== $form_id || (int) $insert_id < 1 || ! is_array( $return_data ) || ! is_array( $form_data ) ) {
			return $return_data;
		}

		if ( ! self::receipt_allows_ad_user_data( (int) $insert_id, $actual, $config ) ) {
			return $return_data;
		}

		$email_field = (string) $config->get( 'fluent_forms.fields.email', 'email' );
		$phone_field = (string) $config->get( 'fluent_forms.fields.phone', 'phone' );
		$email       = isset( $form_data[ $email_field ] ) ? self::normalize_email( $form_data[ $email_field ] ) : '';
		$phone       = isset( $form_data[ $phone_field ] ) ? self::normalize_phone( $form_data[ $phone_field ] ) : '';

		$payload = array(
			'event'             => 'siteengine_ads_bridge_lead',
			'conversion_action' => 'SE | Lead',
			'transaction_id'    => sprintf( 'ff:%d:%d', $form_id, (int) $insert_id ),
			'hashed_email'      => '' !== $email ? hash( 'sha256', $email ) : '',
			'hashed_phone'      => '' !== $phone ? hash( 'sha256', $phone ) : '',
			'gclid'             => self::form_value( $form_data, $config, 'gclid' ),
			'gbraid'            => self::form_value( $form_data, $config, 'gbraid' ),
			'wbraid'            => self::form_value( $form_data, $config, 'wbraid' ),
		);

		if ( '' !== $payload['gbraid'] && '' !== $payload['wbraid'] ) {
			$payload['wbraid'] = '';
		}

		$return_data['siteengine_ads_bridge'] = $payload;
		return $return_data;
	}

	/** @return void */
	public static function render_datalayer_listener() {
		if ( is_admin() ) {
			return;
		}
		$config = SiteEngine_Ads_Bridge_Config::instance();
		if ( (int) $config->get( 'fluent_forms.form_id', 0 ) < 1 ) {
			return;
		}
		?>
<script id="siteengine-ads-bridge-lead-event">
(function(w,$){
    if (!$ || !$.fn) { return; }
    $(document).on('fluentform_submission_success', function(event, detail) {
        var response = detail && detail.response ? detail.response : {};
        var result = response && response.data && response.data.result ? response.data.result : {};
        var payload = result && result.siteengine_ads_bridge ? result.siteengine_ads_bridge : null;
        if (!payload || !payload.transaction_id) { return; }
        w.dataLayer = w.dataLayer || [];
        w.dataLayer.push({
            event: payload.event || 'siteengine_ads_bridge_lead',
            se_conversion_action: payload.conversion_action || 'SE | Lead',
            se_transaction_id: payload.transaction_id || '',
            se_hashed_email: payload.hashed_email || '',
            se_hashed_phone: payload.hashed_phone || '',
            se_gclid: payload.gclid || '',
            se_gbraid: payload.gbraid || '',
            se_wbraid: payload.wbraid || ''
        });
    });
})(window, window.jQuery);
</script>
		<?php
	}

	/**
	 * Validate the authoritative consent receipt referenced by the form submission.
	 *
	 * @param int    $entry_id  Canonical persisted submission ID.
	 * @param int    $form_id   Canonical form ID.
	 * @param object $config    Config instance.
	 * @return bool
	 */
	private static function receipt_allows_ad_user_data( $entry_id, $form_id, $config ) {
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) { return false; }
		$required = trim( (string) $config->get( 'consent.required_policy_version', '' ) );
		$proof = SiteEngine_Ads_Bridge_Consent::current( $entry_id, $form_id, $wpdb->prefix . 'fluentform_submissions', $wpdb, $required );
		if ( ! $proof['allowed'] ) { return false; }
		$row = $proof['row'];
		if ( '' === $required || $row['policy_version'] !== $required ) { return false; }
		$google = json_decode( $row['google_consent_json'], true );
		return is_array( $google ) && ( $google['ad_user_data'] ?? null ) === 'granted';
	}

	/** @return string */
	private static function form_value( $form_data, $config, $key ) {
		$field = (string) $config->get( 'fluent_forms.fields.' . $key, $key );
		return isset( $form_data[ $field ] ) ? trim( (string) $form_data[ $field ] ) : '';
	}

	/** @return string */
	private static function normalize_email( $email ) {
		$email = strtolower( trim( (string) $email ) );
		$parts = explode( '@', $email );
		if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
			return '';
		}
		if ( 'gmail.com' === $parts[1] || 'googlemail.com' === $parts[1] ) {
			$parts[0] = str_replace( '.', '', $parts[0] );
		}
		return $parts[0] . '@' . $parts[1];
	}

	/** @return string */
	private static function normalize_phone( $phone ) {
		$phone  = trim( (string) $phone );
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( '' === $digits ) {
			return '';
		}
		if ( 10 === strlen( $digits ) ) {
			return '+1' . $digits;
		}
		if ( 11 === strlen( $digits ) && '1' === substr( $digits, 0, 1 ) ) {
			return '+' . $digits;
		}
		if ( '+' === substr( $phone, 0, 1 ) && strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
			return '+' . $digits;
		}
		return '';
	}
}

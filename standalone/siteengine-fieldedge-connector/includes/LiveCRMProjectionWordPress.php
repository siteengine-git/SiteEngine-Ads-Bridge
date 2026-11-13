<?php
/**
 * Same-site authenticated CLI writer for verified FieldEdge reads.
 * Only the three FieldEdge custom fields are mutable.
 *
 * @package SiteEngineFieldEdge
 */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/LiveCRMProjection.php';

final class LiveCRMProjectionWordPress {
	public static function apply( array $input ): array {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ||
			! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ||
			! function_exists( 'FluentCrmApi' ) ) {
			return array( 'status' => 'held', 'reason_code' => 'authenticated_admin_cli_required' );
		}
		global $wpdb;
		$contact_id = $input['contact_id'] ?? null;
		$form_id = $input['form_id'] ?? null;
		$entry_id = $input['entry_id'] ?? null;
		if ( ! is_int( $contact_id ) || $contact_id < 1 || ! is_int( $form_id ) || $form_id < 1 ||
			! is_int( $entry_id ) || $entry_id < 1 ) {
			return array( 'status' => 'held', 'reason_code' => 'explicit_contact_and_form_required' );
		}
		$lock_name = 'se-fe-projection-' . substr( hash( 'sha256', DB_NAME . ':' . $wpdb->prefix ), 0, 32 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock_name ) ) ) {
			return array( 'status' => 'held', 'reason_code' => 'projection_busy' );
		}
		try {
			$api = \FluentCrmApi( 'contacts' );
			$definitions = array_column( $api->getCustomFields(), null, 'slug' );
			foreach ( array( LiveCRMProjection::IDENTITY => 'text', LiveCRMProjection::CUSTOMER => 'textarea', LiveCRMProjection::JOBS => 'textarea' ) as $key => $type ) {
				if ( ( $definitions[ $key ]['type'] ?? null ) !== $type ) {
					return array( 'status' => 'held', 'reason_code' => 'field_schema_unavailable' );
				}
			}
			$contact = $api->getContact( $contact_id );
			if ( ! $contact || ! is_string( $contact->email ?? null ) ||
				! filter_var( $contact->email, FILTER_VALIDATE_EMAIL ) ) {
				return array( 'status' => 'held', 'reason_code' => 'contact_not_found' );
			}
			$table = $wpdb->prefix . 'fluentform_submissions';
			$entry = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, form_id, response FROM `{$table}` WHERE id = %d AND form_id = %d LIMIT 1",
				$entry_id, $form_id
			), ARRAY_A );
			if ( ! is_array( $entry ) ) {
				return array( 'status' => 'held', 'reason_code' => 'original_form_entry_missing' );
			}
			$response = json_decode( (string) $entry['response'], true );
			$email = is_array( $response ) ? ( $response['email'] ?? null ) : null;
			if ( ! is_string( $email ) || ! filter_var( trim( $email ), FILTER_VALIDATE_EMAIL ) ||
				strcasecmp( trim( $email ), trim( $contact->email ) ) !== 0 ||
				! is_string( $input['email_sha256'] ?? null ) ||
				! hash_equals( $input['email_sha256'], hash( 'sha256', strtolower( trim( $email ) ) ) ) ) {
				return array( 'status' => 'held', 'reason_code' => 'form_contact_binding_unverified' );
			}
			$prior = $contact->custom_fields();
			if ( ! is_array( $prior ) ) {
				return array( 'status' => 'held', 'reason_code' => 'existing_custom_fields_unavailable' );
			}
			$plan = LiveCRMProjection::plan( $input, $prior );
			if ( 'held' === $plan['status'] ) {
				return $plan;
			}
			if ( 'replayed' === $plan['status'] ) {
				return array(
					'status' => 'replayed', 'reason_code' => 'projection_unchanged',
					'contact_id' => $contact_id, 'job_count' => $plan['job_count'],
					'projection_sha256' => $plan['projection_sha256'],
				);
			}
			try {
				$contact->syncCustomFieldValues( $plan['fields'], false );
				$readback = $api->getContact( $contact_id )->custom_fields();
				if ( ! is_array( $readback ) ) {
					throw new \RuntimeException();
				}
				foreach ( $plan['fields'] as $key => $value ) {
					if ( ( $readback[ $key ] ?? null ) !== $value ) {
						throw new \RuntimeException();
					}
				}
			} catch ( \Throwable $error ) {
				return array( 'status' => 'held', 'reason_code' => 'write_uncertain_inspect_before_retry' );
			}
			return array(
				'status' => 'updated', 'reason_code' => 'verified_customer_jobs_saved',
				'contact_id' => $contact_id, 'job_count' => $plan['job_count'],
				'projection_sha256' => $plan['projection_sha256'],
			);
		} catch ( \Throwable $error ) {
			return array( 'status' => 'held', 'reason_code' => 'live_projection_unconfirmed' );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}
}

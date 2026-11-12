<?php
/**
 * Read-only FieldEdge connector diagnostics for the Ads Bridge administrator UI.
 *
 * No credentials, customer records, provider network calls, or writes are accessed.
 *
 * @package SiteEngineAdsBridge
 */

namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/CRMProjection.php';

final class ConnectorDiagnostics {

	/**
	 * Summarize the supported CRM field contract without returning registry values.
	 *
	 * @param array|null $registry   FluentCRM global field definitions, or null if unavailable.
	 * @param bool       $crm_api    Whether the installed contact API is callable.
	 * @param bool       $ab_api     Whether all required prospective AB callables exist.
	 * @param string     $read_state Field lookup result: available, unavailable, or unconfirmed.
	 * @return array Safe diagnostic facts, never source records.
	 */
	public static function summarize( ?array $registry, bool $crm_api, bool $ab_api, string $read_state = 'available' ): array {
		if ( ! in_array( $read_state, array( 'available', 'unavailable', 'unconfirmed' ), true ) ) {
			throw new \InvalidArgumentException( 'invalid_registry_read_state' );
		}

		$expected = array();
		foreach ( CRMProjection::definitions() as $definition ) {
			$expected[ $definition['slug'] ] = $definition['type'];
		}

		$missing   = array();
		$conflicts = array();
		$seen      = array();
		$invalid   = false;

		if ( 'available' === $read_state && null !== $registry ) {
			foreach ( $registry as $field ) {
				if ( ! is_array( $field ) || ! is_string( $field['slug'] ?? null ) || '' === $field['slug'] ||
					! is_string( $field['type'] ?? null ) || '' === $field['type'] ) {
					$invalid = true;
					break;
				}
				$slug = $field['slug'];
				if ( isset( $seen[ $slug ] ) ) {
					$invalid = true;
					break;
				}
				$seen[ $slug ] = $field['type'];
			}
			if ( ! $invalid ) {
				foreach ( $expected as $slug => $type ) {
					if ( ! isset( $seen[ $slug ] ) ) {
						$missing[] = $slug;
					} elseif ( $seen[ $slug ] !== $type ) {
						$conflicts[] = $slug;
					}
				}
			}
		} else {
			$read_state = 'unavailable' === $read_state ? 'unavailable' : 'unconfirmed';
		}

		$schema = $invalid ? 'invalid_registry' :
			( 'available' !== $read_state ? 'not_verified' :
				( $conflicts ? 'type_conflict' : ( $missing ? 'missing_fields' : 'ready' ) ) );

		return array(
			'contract'                  => 'siteengine-fieldedge-connector-diagnostics/1',
			'inspection'                => 'read_only_no_provider_access',
			'provider_connection'       => 'not_tested_by_diagnostics',
			'credential_state'          => 'not_read_or_reported',
			'crm_contact_api'           => $crm_api ? 'available' : 'unavailable',
			'crm_field_registry_read'   => $read_state,
			'crm_field_schema'          => $schema,
			'crm_fields_missing'        => $invalid ? array() : $missing,
			'crm_fields_type_conflict'  => $invalid ? array() : $conflicts,
			'crm_projection'            => $crm_api && 'ready' === $schema ? 'synthetic_isolated_cli_only' : 'not_ready',
			'ab_prospective_contract'   => $ab_api ? 'callables_present' : 'callables_missing',
			'lead_creation'             => 'production_post_disabled',
			'provider_lead_job_link'    => 'unverified_provider_contract',
			'advertising_attribution'   => 'not_assessed',
			'consent_and_dnc'           => 'not_changed',
			'subscription_and_sending'  => 'not_changed',
			'automatic_sync'            => 'disabled',
			'provider_http_requests'    => 0,
			'database_writes'           => 0,
			'customer_records_in_output'=> false,
		);
	}

	/**
	 * Admin-only, read-only runtime inspection for WP-CLI or the future AB setup page.
	 *
	 * @return array Diagnostic report safe for an administrator.
	 */
	public static function wordpress(): array {
		if ( ! defined( 'ABSPATH' ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			throw new \RuntimeException( 'administrator_required' );
		}

		$registry   = null;
		$read_state = 'unavailable';
		if ( class_exists( '\\FluentCrm\\App\\Models\\CustomContactField' ) ) {
			$read_state = 'unconfirmed';
			try {
				$manager = new \FluentCrm\App\Models\CustomContactField();
				$global  = $manager->getGlobalFields();
				if ( is_array( $global ) && isset( $global['fields'] ) && is_array( $global['fields'] ) ) {
					$registry   = $global['fields'];
					$read_state = 'available';
				}
			} catch ( \Throwable $error ) {
				// Deliberately suppress exception details and stored field values.
				$read_state = 'unconfirmed';
			}
		}

		$prospective = array(
			'seab_prospective_prepare',
			'seab_prospective_claim',
			'seab_prospective_observe',
			'seab_prospective_read',
		);
		$ab_ready = true;
		foreach ( $prospective as $callable ) {
			if ( ! function_exists( $callable ) ) {
				$ab_ready = false;
				break;
			}
		}

		return self::summarize( $registry, function_exists( 'FluentCrmApi' ), $ab_ready, $read_state );
	}
}

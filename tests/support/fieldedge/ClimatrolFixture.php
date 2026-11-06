<?php
/** Local synthetic fixture factory; never loaded by WordPress/plugin runtime. */
namespace SiteEngine\AdsBridge\Tests\FieldEdge;

use SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation;
use SiteEngine\AdsBridge\Connectors\FieldEdge\ClimatrolWorkflow;

require_once __DIR__ . '/../../../includes/connectors/fieldedge/ClimatrolWorkflow.php';

final class ClimatrolFixture {
	public static function inputs( string $scenario = 'attributed' ): array {
		$root = __DIR__ . '/../../fixtures/fieldedge/';
		$records = json_decode( file_get_contents( $root . 'records.json' ), true, 16, JSON_THROW_ON_ERROR );
		$profile = json_decode( file_get_contents( $root . 'climatrol-synthetic.json' ), true, 16, JSON_THROW_ON_ERROR );
		$lead = Foundation::lead( $records['lead'] );
		$association = array(
			'tenant_id' => $records['tenant'], 'work_order_id' => $records['work']['workOrderId'],
			'invoice_id' => $records['invoice']['invoiceId'], 'customer_id' => $lead['customer_id'],
			'lead_id' => $lead['lead_id'], 'inquiry_id' => $profile['inquiry']['inquiry_id'],
			'basis' => 'reviewed_explicit', 'evidence_id' => $profile['association_evidence_id'],
		);
		$approval = $profile['value_approval'] + array(
			'tenant_id' => $records['tenant'], 'invoice_id' => $records['invoice']['invoiceId'],
			'invoice_sha256' => Foundation::digest( $records['invoice'] ),
		);
		$events = array( 'attributed' => '66666666-6666-4666-8666-666666666666',
			'unattributed' => '77777777-7777-4777-8777-777777777777', 'review' => '88888888-8888-4888-8888-888888888888' );
		if ( ! isset( $events[ $scenario ] ) ) {
			throw new \InvalidArgumentException( 'unknown_synthetic_scenario' );
		}
		$records['notification']['id'] = $events[ $scenario ];
		$body = json_encode( $records['notification'], JSON_THROW_ON_ERROR );
		$secret = 'synthetic-local-demo-only';
		$event = Foundation::notification( $body, 'sha256=' . hash_hmac( 'sha256', $body, $secret ), $secret, $records['tenant'] );
		return array( $records, $profile['inquiry'], array( $association ), $approval, $event );
	}

	public static function packet( string $scenario ): array {
		$inputs = self::inputs( $scenario );
		if ( 'unattributed' === $scenario ) {
			$inputs[2] = array();
		} elseif ( 'review' === $scenario ) {
			// Same customer/phone alone never proves a job-to-inquiry relationship.
			$inputs[2][0]['basis'] = 'phone_match';
		} elseif ( 'attributed' !== $scenario ) {
			throw new \InvalidArgumentException( 'unknown_synthetic_scenario' );
		}
		return ClimatrolWorkflow::normalize( ...$inputs );
	}
}

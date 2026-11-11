<?php
/** Supported FluentCRM 3.3.1 model/custom-field boundary, CLI synthetic use only. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/CRMProjection.php';

final class FluentCRMProjectionContacts implements ProjectionContacts {
	private $api;
	private bool $inside = false;
	private array $allowed = array();
	public function __construct() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'SITEENGINE_FE_PROJECTION_ISOLATED' ) || SITEENGINE_FE_PROJECTION_ISOLATED !== true ||
			! current_user_can( 'manage_options' ) || ! function_exists( 'FluentCrmApi' ) ) { throw new \RuntimeException( 'isolated_admin_cli_required' ); }
		$this->api = \FluentCrmApi( 'contacts' );
		$fields = array_column( $this->api->getCustomFields(), null, 'slug' );
		foreach ( CRMProjection::definitions() as $field ) {
			if ( ( $fields[ $field['slug'] ]['type'] ?? null ) !== $field['type'] ) { throw new \RuntimeException( 'approved_field_definition_required' ); }
		}
	}
	public function locked( callable $run ): array {
		global $wpdb;
		$name = 'se-fe-projection-' . substr( hash( 'sha256', DB_NAME . ':' . $wpdb->prefix ), 0, 32 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ) { return array( 'status' => 'held', 'reason' => 'projection_busy' ); }
		$this->inside = true; $this->allowed = array();
		try { return $run(); } finally {
			$this->inside = false; $this->allowed = array();
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}
	private function requireLock(): void { if ( ! $this->inside ) { throw new \RuntimeException( 'projection_lock_required' ); } }
	private function ids( $query ): array {
		$ids = array(); foreach ( $query->limit( 2 )->get() as $row ) { $ids[] = (int) $row->id; } return $ids;
	}
	public function candidates( string $identity, string $email, string $job_id ): array {
		$this->requireLock(); global $wpdb;
		$model = $this->api->getInstance();
		$by_identity = $this->ids( $model->whereHas( 'custom_field_meta', static fn( $q ) => $q->where( 'key', CRMProjection::IDENTITY )->where( 'value', $identity ) ) );
		$by_email = $this->ids( $model->where( 'email', $email ) );
		$i = json_decode( $identity, true, 4, JSON_THROW_ON_ERROR ); unset( $i['customer_id'] );
		$tenant_prefix = substr( CRMProjection::json( $i ), 0, -1 ) . ',';
		$job_pattern = '%' . $wpdb->esc_like( '"' . $job_id . '":' ) . '%';
		$tenant_pattern = $wpdb->esc_like( $tenant_prefix ) . '%';
		$by_job = $this->ids( $model->whereHas( 'custom_field_meta', static fn( $q ) => $q->where( 'key', CRMProjection::IDENTITY )->where( 'value', 'like', $tenant_pattern ) )
			->whereHas( 'custom_field_meta', static fn( $q ) => $q->where( 'key', CRMProjection::JOBS )->where( 'value', 'like', $job_pattern ) ) );
		$this->allowed = array_unique( array_merge( $by_identity, $by_email, $by_job ) );
		return array( 'identity' => $by_identity, 'email' => $by_email, 'job' => $by_job );
	}
	private function contact( int $id ) {
		$this->requireLock();
		if ( ! in_array( $id, $this->allowed, true ) ) { throw new \RuntimeException( 'foreign_contact_refused' ); }
		$c = $this->api->getContact( $id );
		if ( ! $c || ! preg_match( '/\Afe-projection-[a-z0-9-]+@example\.invalid\z/D', $c->email ) ) { throw new \RuntimeException( 'test_owned_contact_required' ); }
		return $c;
	}
	public function fields( int $id ): array { return $this->contact( $id )->custom_fields(); }
	public function create( string $email, array $fields ): int {
		$this->requireLock();
		if ( $this->allowed || ! preg_match( '/\Afe-projection-[a-z0-9-]+@example\.invalid\z/D', $email ) ) { throw new \RuntimeException( 'new_test_owned_contact_required' ); }
		// Supported ORM insert: unique email races fail, never upsert a foreign winner.
		$c = $this->api->getInstance()->create( array( 'email' => $email, 'status' => 'unsubscribed' ) );
		$id = (int) $c->id; $this->allowed[] = $id;
		$this->updateFields( $id, $fields );
		return $id;
	}
	public function updateFields( int $id, array $fields ): void {
		if ( array_diff( array_keys( $fields ), array( CRMProjection::IDENTITY, CRMProjection::CUSTOMER, CRMProjection::JOBS ) ) ) { throw new \RuntimeException( 'unowned_field_refused' ); }
		$this->contact( $id )->syncCustomFieldValues( $fields, false );
	}
}

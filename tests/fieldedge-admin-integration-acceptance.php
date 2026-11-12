<?php
/**
 * Focused FE optional field provisioning, administrator read-only UI and preservation.
 */
namespace FluentCrm\App\Models {
	final class CustomContactField {
		public function getFieldTypes(): array {
			return array_fill_keys( array( 'text', 'textarea', 'number', 'date', 'date_time' ), array() );
		}
		public function getGlobalFields(): array {
			return array( 'fields' => $GLOBALS['registry'] );
		}
		public function saveGlobalFields( array $fields ): array {
			++$GLOBALS['writes'];
			$GLOBALS['registry'] = $fields;
			return $fields;
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['registry'] = array( array( 'slug' => 'unrelated', 'label' => 'Existing data', 'type' => 'text', 'retained' => array( 'value' => 'private' ) ) );
	$GLOBALS['writes'] = 0;
	$GLOBALS['passed'] = 0;
	function check( bool $condition, string $label ): void {
		if ( ! $condition ) { throw new \RuntimeException( 'FAIL: ' . $label ); }
		++$GLOBALS['passed'];
		echo 'PASS: ' . $label . "\n";
	}
	function current_user_can( string $capability ): bool { return $capability === 'manage_options'; }
	function FluentCrmApi( string $resource ) { return null; }
	function esc_html( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	function esc_url( string $url ): string { return esc_html( $url ); }
	function admin_url( string $path ): string { return '/wp-admin/' . $path; }
	function wp_nonce_field( string $action ): void { echo '<input data-nonce-action="' . esc_html( $action ) . '">'; }
	function submit_button( string $title, string $style = 'primary', string $name = 'submit', bool $wrap = true ): void { echo '<button>' . esc_html( $title ) . '</button>'; }
	require_once __DIR__ . '/../includes/class-siteengine-ads-bridge-fields.php';
	require_once __DIR__ . '/../includes/connectors/fieldedge/ConnectorDiagnostics.php';
	require_once __DIR__ . '/../includes/class-siteengine-ads-bridge-admin.php';

	$definitions = \SiteEngine\AdsBridge\Connectors\FieldEdge\CRMProjection::definitions();
	$original = $GLOBALS['registry'][0];
	$preview = \SiteEngine_Ads_Bridge_Fields::provision( false, $definitions );
	check( count( $preview['missing'] ) === 3 && $GLOBALS['writes'] === 0, 'only three FE fields previewed without mutation' );
	$render = new \ReflectionMethod( \SiteEngine_Ads_Bridge_Admin::class, 'render_fieldedge_status' );
	ob_start();
	$render->invoke( null );
	$html = ob_get_clean();
	check( str_contains( $html, 'Create Missing FieldEdge Fields' ), 'explicit FE provisioning action shown for missing fields' );
	check( str_contains( $html, 'seab_provision_fieldedge' ) && str_contains( $html, 'data-nonce-action' ), 'admin action requires distinct nonce' );
	check( ! str_contains( $html, 'private' ) && ! str_contains( $html, 'Existing data' ), 'stored unrelated field data excluded from UI' );
	check( $GLOBALS['writes'] === 0, 'status render does not mutate CRM' );
	$result = \SiteEngine_Ads_Bridge_Fields::provision( true, $definitions );
	check( $result['created'] === 3 && $GLOBALS['writes'] === 1, 'only three FE definitions created through existing vendor manager' );
	check( $GLOBALS['registry'][0] === $original, 'unrelated definition bytes preserved' );
	check( count( $GLOBALS['registry'] ) === 4, 'three FE fields appended without replacing existing fields' );
	check( \SiteEngine_Ads_Bridge_Fields::provision( true, $definitions )['created'] === 0 && $GLOBALS['writes'] === 1, 'repeat provisioning idempotent' );
	ob_start();
	$render->invoke( null );
	$html = ob_get_clean();
	check( ! str_contains( $html, 'Create Missing FieldEdge Fields' ) && str_contains( $html, 'ready' ), 'ready status hides provisioning action' );
	$GLOBALS['registry'][2]['type'] = 'number';
	$before = $GLOBALS['registry'];
	$plan = \SiteEngine_Ads_Bridge_Fields::provision( false, $definitions );
	check( $plan['conflicts'] === array( $definitions[1]['slug'] ), 'type conflict recognized' );
	try {
		\SiteEngine_Ads_Bridge_Fields::provision( true, $definitions );
		check( false, 'type conflict should fail' );
	} catch ( \RuntimeException $error ) {
		check( $GLOBALS['writes'] === 1 && $GLOBALS['registry'] === $before, 'conflict refused without CRM mutation' );
	}
	echo 'RESULT: ' . $GLOBALS['passed'] . " PASS, 0 FAIL; vendor and WordPress doubles only.\n";
}

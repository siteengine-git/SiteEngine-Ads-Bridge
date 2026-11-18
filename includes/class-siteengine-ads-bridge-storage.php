<?php
/** Shared canonical ledger creation/physical verification, retained from CLI. */
if(!defined('ABSPATH'))exit;
final class SiteEngine_Ads_Bridge_Storage {
    public static function ensure_ledger_exists(){return (new self())->ensure_ledger_table();}
    public static function inspect_ledger(){
        $s=new self();if(!$s->ledger_table_exists())return ['ready'=>false,'reason'=>'Export ledger is absent or unreadable. Normal Setup prepares absent storage; preserve uncertain physical state.'];
        $diff=$s->ledger_schema_differences();return ['ready'=>!$diff,'reason'=>$diff?'Export ledger incompatible;preserved: '.implode('; ',array_slice($diff,0,8)):'Canonical export ledger verified.'];
    }
    public static function prepare_ledger(){
        $s=new self();if(!$s->ensure_ledger_table())throw new RuntimeException('Export ledger creation could not be verified. Preserve physical state.');
        $diff=$s->ledger_schema_differences();if($diff)throw new RuntimeException('Export ledger incompatible; preserved without repair: '.implode('; ',array_slice($diff,0,8)));
    }
        private function ledger_table() {
                global $wpdb;
                return $wpdb->prefix . 'se_ads_export_ledger';
        }
        private function ledger_table_exists() {
                global $wpdb;
                $t     = $this->ledger_table();
                $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
                return ( (string) $found === $t );
        }
        private function ensure_ledger_table() {
                global $wpdb;
                if ( $this->ledger_table_exists() ) {
                        return true;
                }
                $t               = $this->ledger_table();
                $charset_collate = $wpdb->get_charset_collate();
                $sql             = "CREATE TABLE IF NOT EXISTS `{$t}` (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        sink_slug VARCHAR(64) NOT NULL DEFAULT '',
                        contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        transaction_id VARCHAR(191) NOT NULL DEFAULT '',
                        row_hash CHAR(64) NOT NULL DEFAULT '',
                        spreadsheet_id VARCHAR(191) NOT NULL DEFAULT '',
                        tab VARCHAR(191) NOT NULL DEFAULT '',
                        updated_range VARCHAR(191) NOT NULL DEFAULT '',
                        status VARCHAR(16) NOT NULL DEFAULT '',
                        error TEXT NULL,
                        attempts INT UNSIGNED NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        KEY sink_txn (sink_slug, transaction_id),
                        KEY sink_status (sink_slug, status)
                ) {$charset_collate};";
                $wpdb->query( $sql );
                return $this->ledger_table_exists();
        }
        private function ledger_canonical_columns() {
                return array(
                        array( 'field' => 'id',             'type' => 'bigint unsigned',  'null' => 'NO',  'default' => null, 'extra' => 'auto_increment' ),
                        array( 'field' => 'sink_slug',      'type' => 'varchar(64)',      'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'contact_id',     'type' => 'bigint unsigned',  'null' => 'NO',  'default' => '0',  'extra' => '' ),
                        array( 'field' => 'transaction_id', 'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'row_hash',       'type' => 'char(64)',         'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'spreadsheet_id', 'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'tab',            'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'updated_range',  'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'status',         'type' => 'varchar(16)',      'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'error',          'type' => 'text',             'null' => 'YES', 'default' => null, 'extra' => '' ),
                        array( 'field' => 'attempts',       'type' => 'int unsigned',     'null' => 'NO',  'default' => '0',  'extra' => '' ),
                        array( 'field' => 'created_at',     'type' => 'datetime',         'null' => 'NO',  'default' => null, 'extra' => '' ),
                        array( 'field' => 'updated_at',     'type' => 'datetime',         'null' => 'NO',  'default' => null, 'extra' => '' ),
                );
        }
        private function ledger_canonical_indexes() {
                return array(
                        'PRIMARY'     => array( 'unique' => true,  'columns' => array( 'id' ) ),
                        'sink_txn'    => array( 'unique' => false, 'columns' => array( 'sink_slug', 'transaction_id' ) ),
                        'sink_status' => array( 'unique' => false, 'columns' => array( 'sink_slug', 'status' ) ),
                );
        }
        private function normalize_column_type( $type ) {
                $t = strtolower( trim( (string) $type ) );
                $t = preg_replace( '/\s+/', ' ', $t );
                $t = preg_replace( '/^(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $t );
                $t = preg_replace( '/^integer\b/', 'int', $t );
                return (string) $t;
        }
        private function ledger_render_default( $value ) {
                return ( null === $value ) ? '(none)' : sprintf( "'%s'", (string) $value );
        }
        private function ledger_default_matches( $expected, $actual ) {
                if ( null === $expected ) {
                        return ( null === $actual );
                }
                if ( null === $actual ) {
                        return false;
                }
                return ( (string) $expected === (string) $actual );
        }
        private function ledger_show_full_columns() {
                global $wpdb;
                $t = $this->ledger_table();
                if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
                        return array();
                }
                $rows = $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$t}`", ARRAY_A );
                return is_array( $rows ) ? $rows : array();
        }
        private function ledger_show_index() {
                global $wpdb;
                $t = $this->ledger_table();
                if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
                        return array();
                }
                $rows = $wpdb->get_results( "SHOW INDEX FROM `{$t}`", ARRAY_A );
                return is_array( $rows ) ? $rows : array();
        }
        private function ledger_index_map() {
                $map = array();
                $seq = array();

                foreach ( $this->ledger_show_index() as $r ) {
                        $name = isset( $r['Key_name'] ) ? (string) $r['Key_name'] : '';
                        if ( '' === $name ) {
                                continue;
                        }
                        if ( ! isset( $map[ $name ] ) ) {
                                $map[ $name ] = array(
                                        'unique'  => ( isset( $r['Non_unique'] ) && 0 === (int) $r['Non_unique'] ),
                                        'columns' => array(),
                                );
                                $seq[ $name ] = array();
                        }
                        $pos                  = isset( $r['Seq_in_index'] ) ? (int) $r['Seq_in_index'] : ( count( $seq[ $name ] ) + 1 );
                        $seq[ $name ][ $pos ] = isset( $r['Column_name'] ) ? (string) $r['Column_name'] : '';
                }

                foreach ( $seq as $name => $cols ) {
                        ksort( $cols, SORT_NUMERIC );
                        $map[ $name ]['columns'] = array_values( $cols );
                }

                return $map;
        }
        private function ledger_schema_differences() {
                $diffs = array();
                $spec  = $this->ledger_canonical_columns();

                $actual_by_name = array();
                $actual_order   = array();
                foreach ( $this->ledger_show_full_columns() as $row ) {
                        $name                    = isset( $row['Field'] ) ? (string) $row['Field'] : '';
                        $actual_by_name[ $name ] = $row;
                        $actual_order[]          = $name;
                }

                $spec_order = array();
                foreach ( $spec as $col ) {
                        $spec_order[] = $col['field'];
                }

                foreach ( $spec as $col ) {
                        $name = $col['field'];

                        if ( ! isset( $actual_by_name[ $name ] ) ) {
                                $diffs[] = sprintf(
                                        'column MISSING: %s (expected type %s, Null=%s, default %s, extra "%s")',
                                        $name,
                                        $col['type'],
                                        $col['null'],
                                        $this->ledger_render_default( $col['default'] ),
                                        $col['extra']
                                );
                                continue;
                        }

                        $a = $actual_by_name[ $name ];

                        $found_type    = $this->normalize_column_type( isset( $a['Type'] ) ? $a['Type'] : '' );
                        $expected_type = $this->normalize_column_type( $col['type'] );
                        if ( $found_type !== $expected_type ) {
                                $diffs[] = sprintf( 'column %s TYPE: expected "%s", found "%s"', $name, $expected_type, $found_type );
                        }

                        $found_null = strtoupper( trim( (string) ( isset( $a['Null'] ) ? $a['Null'] : '' ) ) );
                        if ( $found_null !== $col['null'] ) {
                                $diffs[] = sprintf( 'column %s NULLABILITY: expected Null=%s, found Null=%s', $name, $col['null'], '' === $found_null ? '(unknown)' : $found_null );
                        }

                        $found_default = array_key_exists( 'Default', $a ) ? $a['Default'] : null;
                        if ( ! $this->ledger_default_matches( $col['default'], $found_default ) ) {
                                $diffs[] = sprintf(
                                        'column %s DEFAULT: expected %s, found %s',
                                        $name,
                                        $this->ledger_render_default( $col['default'] ),
                                        $this->ledger_render_default( $found_default )
                                );
                        }

                        $found_extra = strtolower( trim( (string) ( isset( $a['Extra'] ) ? $a['Extra'] : '' ) ) );
                        if ( $found_extra !== strtolower( $col['extra'] ) ) {
                                $diffs[] = sprintf( 'column %s EXTRA: expected "%s", found "%s"', $name, $col['extra'], $found_extra );
                        }
                }

                foreach ( $actual_order as $name ) {
                        if ( ! in_array( $name, $spec_order, true ) ) {
                                $diffs[] = sprintf( 'column UNEXPECTED: %s (not part of the canonical ledger schema)', $name );
                        }
                }

                // Ordinal position is only meaningful once both sides hold the same set;
                // otherwise the missing/unexpected entries above already explain it.
                $sorted_actual = $actual_order;
                $sorted_spec   = $spec_order;
                sort( $sorted_actual );
                sort( $sorted_spec );
                if ( $sorted_actual === $sorted_spec && $actual_order !== $spec_order ) {
                        $diffs[] = sprintf( 'column ORDER: expected [%s], found [%s]', implode( ', ', $spec_order ), implode( ', ', $actual_order ) );
                }

                $actual_idx = $this->ledger_index_map();
                $spec_idx   = $this->ledger_canonical_indexes();

                foreach ( $spec_idx as $key => $def ) {
                        if ( ! isset( $actual_idx[ $key ] ) ) {
                                $diffs[] = sprintf( 'index MISSING: %s (%s on [%s])', $key, $def['unique'] ? 'unique' : 'non-unique', implode( ', ', $def['columns'] ) );
                                continue;
                        }
                        if ( $actual_idx[ $key ]['columns'] !== $def['columns'] ) {
                                $diffs[] = sprintf(
                                        'index %s COLUMNS: expected [%s], found [%s]',
                                        $key,
                                        implode( ', ', $def['columns'] ),
                                        implode( ', ', $actual_idx[ $key ]['columns'] )
                                );
                        }
                        if ( $actual_idx[ $key ]['unique'] !== $def['unique'] ) {
                                $diffs[] = sprintf(
                                        'index %s UNIQUENESS: expected %s, found %s',
                                        $key,
                                        $def['unique'] ? 'unique' : 'non-unique',
                                        $actual_idx[ $key ]['unique'] ? 'unique' : 'non-unique'
                                );
                        }
                }

                foreach ( $actual_idx as $key => $def ) {
                        if ( ! isset( $spec_idx[ $key ] ) ) {
                                $diffs[] = sprintf( 'index UNEXPECTED: %s on [%s]', $key, implode( ', ', $def['columns'] ) );
                        }
                }

                return $diffs;
        }
}

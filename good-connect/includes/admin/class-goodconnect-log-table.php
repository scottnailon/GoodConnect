<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GoodConnect_Log_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'log_entry',
            'plural'   => 'log_entries',
            'ajax'     => false,
        ] );
    }

    public function get_columns(): array {
        return [
            'created_at'     => __( 'Date / Time', 'good-connect' ),
            'source'         => __( 'Source', 'good-connect' ),
            'form_name'      => __( 'Form', 'good-connect' ),
            'account_id'     => __( 'Account', 'good-connect' ),
            'contact_email'  => __( 'Email', 'good-connect' ),
            'action'         => __( 'Action', 'good-connect' ),
            'success'        => __( 'Status', 'good-connect' ),
            'ghl_contact_id' => __( 'Contact ID', 'good-connect' ),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'created_at' => [ 'created_at', true ],
            'source'     => [ 'source', false ],
            'success'    => [ 'success', false ],
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $page     = $this->get_pagenum();
        $source   = sanitize_key( wp_unslash( $_GET['gc_source'] ?? '' ) );
        $success  = isset( $_GET['gc_success'] ) ? sanitize_key( wp_unslash( $_GET['gc_success'] ) ) : 'all';

        $result = GoodConnect_DB::get_logs( [
            'per_page' => $per_page,
            'page'     => $page,
            'source'   => $source,
            'success'  => $success,
        ] );

        $this->items = $result['rows'];
        $this->set_pagination_args( [
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => ceil( $result['total'] / $per_page ),
        ] );
        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    protected function column_default( $item, $column_name ) {
        return esc_html( $item->$column_name ?? '' );
    }

    protected function column_success( $item ) {
        if ( $item->success ) {
            return '<span class="goodconnect-status goodconnect-status--ok">&#10003; Success</span>';
        }
        $error = esc_html( $item->error_message );
        return '<span class="goodconnect-status goodconnect-status--fail" title="' . $error . '">&#10007; Failed</span>';
    }

    protected function column_source( $item ) {
        $labels = [
            'gravity-forms' => 'Gravity Forms',
            'elementor'     => 'Elementor',
            'woocommerce'   => 'WooCommerce',
        ];
        return esc_html( $labels[ $item->source ] ?? $item->source );
    }

    protected function column_account_id( $item ) {
        $account = GoodConnect_Settings::get_account_by_id( $item->account_id );
        return esc_html( $account ? $account['label'] : $item->account_id );
    }

    protected function column_ghl_contact_id( $item ) {
        if ( ! $item->ghl_contact_id ) return '&mdash;';
        return '<code>' . esc_html( $item->ghl_contact_id ) . '</code>';
    }

    protected function column_created_at( $item ) {
        return esc_html( get_date_from_gmt( $item->created_at, 'd M Y H:i:s' ) );
    }
}

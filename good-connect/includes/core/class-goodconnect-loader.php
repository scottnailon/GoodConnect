<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GoodConnect_Loader {

    public function run() {
        GoodConnect_DB::maybe_upgrade();
        GoodConnect_Settings::init();
        GoodConnect_Admin::init();
        GoodConnect_GF::init();
        GoodConnect_Elementor::init();
        GoodConnect_Woo::init();
        GoodConnect_CF7::init();
        GoodConnect_BulkSync::init();
        GoodConnect_Webhook_Receiver::init();
        GoodConnect_Webhook_Admin::init();
        GoodConnect_Magic_Link::init();
        GoodConnect_Tag_Protection::init();
        GoodConnect_Protection_Meta::init();
    }
}

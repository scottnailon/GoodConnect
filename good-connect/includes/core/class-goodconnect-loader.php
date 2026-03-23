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
    }
}

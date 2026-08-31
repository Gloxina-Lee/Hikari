<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: code_editor
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'CSF_Field_code_editor' ) ) {
  class CSF_Field_code_editor extends CSF_Fields {

    public $version = '5.65.7';
    public $asset_url = '';

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
      $this->asset_url = sakurairo_local_asset_url( 'assets/vendor/codemirror/' );
    }

    public function render() {

      $default_settings = array(
        'tabSize'       => 2,
        'lineNumbers'   => true,
        'theme'         => 'default',
        'mode'          => 'htmlmixed',
        'cdnURL'        => $this->asset_url,
      );

      $settings = ( ! empty( $this->field['settings'] ) ) ? $this->field['settings'] : array();
      $settings = wp_parse_args( $settings, $default_settings );

      echo $this->field_before();
      echo '<textarea name="'. esc_attr( $this->field_name() ) .'"'. $this->field_attributes() .' data-editor="'. esc_attr( json_encode( $settings ) ) .'">'. $this->value .'</textarea>';
      echo $this->field_after();

    }

    public function enqueue() {

      $page = ( ! empty( $_GET[ 'page' ] ) ) ? sanitize_text_field( wp_unslash( $_GET[ 'page' ] ) ) : '';

      // Do not loads CodeMirror in revslider page.
      if ( in_array( $page, array( 'revslider' ) ) ) { return; }

      if ( ! wp_script_is( 'csf-codemirror' ) ) {
        wp_enqueue_script( 'csf-codemirror', esc_url( $this->asset_url . 'lib/codemirror.js' ), array( 'sakurairo_csf' ), $this->version, true );
        wp_enqueue_script( 'csf-codemirror-mode-xml', esc_url( $this->asset_url . 'mode/xml/xml.js' ), array( 'csf-codemirror' ), $this->version, true );
        wp_enqueue_script( 'csf-codemirror-mode-javascript', esc_url( $this->asset_url . 'mode/javascript/javascript.js' ), array( 'csf-codemirror' ), $this->version, true );
        wp_enqueue_script( 'csf-codemirror-mode-css', esc_url( $this->asset_url . 'mode/css/css.js' ), array( 'csf-codemirror' ), $this->version, true );
        wp_enqueue_script( 'csf-codemirror-mode-htmlmixed', esc_url( $this->asset_url . 'mode/htmlmixed/htmlmixed.js' ), array( 'csf-codemirror-mode-xml', 'csf-codemirror-mode-javascript', 'csf-codemirror-mode-css' ), $this->version, true );
        wp_enqueue_script( 'csf-codemirror-loadmode', esc_url( $this->asset_url . 'addon/mode/loadmode.js' ), array( 'csf-codemirror-mode-htmlmixed' ), $this->version, true );
      }

      if ( ! wp_style_is( 'csf-codemirror' ) ) {
        wp_enqueue_style( 'csf-codemirror', esc_url( $this->asset_url . 'lib/codemirror.css' ), array(), $this->version );
      }

    }

  }
}

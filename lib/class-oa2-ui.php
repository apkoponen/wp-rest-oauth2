<?php

/**
 * Authorization page handler
 *
 * Takes care of UI and related elements for the authorization step of OAuth2.
 *
 * Based on WP REST API - OAuth 1.0a Server (https://github.com/WP-API/OAuth2).
 * Used under GPL3 license.
 */
class OA2_UI {

  /**
   * Request token for the current authorization request
   *
   * @var array
   */
  protected $token;

  /**
   * Consumer post object for the current authorization request
   *
   * @var WP_Post
   */
  protected $consumer;

  /**
   * Register required actions and filters
   */
  public function register_hooks() {
	add_action( 'login_form_oauth2_authorize', array( $this, 'handle_request' ) );
	add_action( 'oauth2_authorize_form', array( $this, 'page_fields' ) );
  }

  /**
   * Handle request to authorization page
   *
   * Handles response from {@see render_page}, then exits to avoid output from
   * default wp-login handlers.
   */
  public function handle_request() {
	if ( !is_user_logged_in() ) {
	  wp_safe_redirect( wp_login_url( $_SERVER[ 'REQUEST_URI' ] ) );
	  exit;
	}

	$response = $this->render_page();
	if ( is_wp_error( $response ) ) {
	  $this->display_error( $response );
	}
	exit;
  }

  /**
   * Render authorization page
   *
   * @return null|WP_Error Null on success, error otherwise
   */
  public function render_page() {

	// Check required fields
	if ( !OA2_Authorize_Controller::required_params_exist( $_GET ) ) {
	  $error_code = 'invalid_request';
	  return new WP_Error( $error_code, OA2_Error_Helper::get_error_description( $error_code ), array( 'status' => 400 ) );
	}

	// Set up fields
	$consumer = OA2_Client::get_by_client_id( $_GET[ 'client_id' ] );
	$scope = OA2_Scope_Helper::get_all_caps_scope();
	if ( !empty( $_GET[ 'scope' ] ) ) {
	  if(!OA2_Scope_Helper::validate_scope( $_GET[ 'scope' ] )) {
		$error_code = 'invalid_scope';
		return new WP_Error( $error_code, OA2_Error_Helper::get_error_description( $error_code ), array( 'status' => 400 ) );
	  }
	  $scope = $_GET[ 'scope' ];
	}
	$state = '';
	if ( !empty( $_GET[ 'state' ] ) ) {
	  $state = $_GET[ 'state' ];
	}

	$this->redirect_uri	 = esc_url($_GET[ 'redirect_uri' ]);
	$this->response_type = sanitize_key($_GET[ 'response_type' ]);
	$this->state		 = $state;
	$this->consumer		 = $consumer;
	$this->scope		 = $scope;

	$file = locate_template( 'oauth2-authorize.php' );
	if ( empty( $file ) ) {
	  $file = dirname( dirname( __FILE__ ) ) . '/templates/oauth2-authorize.php';
	}

	$errors = array();
	include $file;
  }

  /**
   * Output required hidden fields
   *
   * Outputs the required hidden fields for the authorization page, including
   * nonce field.
   */
  public function page_fields() {
	echo '<input type="hidden" name="client_id" value="' . esc_attr( $this->consumer->client_id ) . '" />';
	echo '<input type="hidden" name="redirect_uri" value="' . esc_attr( $this->redirect_uri ) . '" />';
	echo '<input type="hidden" name="response_type" value="' . esc_attr( $this->response_type ) . '" />';
	echo '<input type="hidden" name="state" value="' . esc_attr( $this->state ) . '" />';
	echo '<input type="hidden" name="scope" value="' . esc_attr( $this->scope ) . '" />';
	wp_nonce_field( 'oauth2_authorization_' . $this->redirect_uri . '_' . $this->consumer->client_id, '_oauth2_nonce' );
	wp_nonce_field( 'wp_rest' );
  }

  /**
   * Display an error using login page wrapper
   *
   * @param WP_Error $error Error object
   */
  public function display_error( WP_Error $error ) {
	login_header( __( 'Error', 'wp_rest_oauth2' ), '', $error );
	login_footer();
  }

}

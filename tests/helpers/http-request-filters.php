<?php
/**
 * All helper methods for the WP2D tests.
 *
 * @package WP_To_Diaspora\Tests\Helpers
 * @since 1.7.0
 */

/**
 * Custom HTTP request responses for _update_pod_list AJAX call.
 *
 * @since 1.7.0
 */
function wp_to_diaspora_pre_http_request_filter_update_pod_list() {
	static $responses = array(
		array(
			'body'     => '',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		array(
			'body'     => '
				{"podcount":3,"pods":[
					{"id":"1","domain":"pod1","secure":"true","hidden":"no"},
					{"id":"2","domain":"pod2","secure":"false","hidden":"no"},
					{"id":"3","domain":"pod3","secure":"true","hidden":"yes"}
				]}',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
		array(
			'body'     => '
				{"podcount":1,"pods":[
					{"id":"10","domain":"pod10","secure":"true","hidden":"no"}
				]}',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
	);
	return array_shift( $responses );
}

/**
 * Custom HTTP request responses for test_update_aspects_services_list testing both aspects and services.
 *
 * @since 1.7.0
 */
function wp_to_diaspora_pre_http_request_filter_update_aspects() {
	static $i = 0;
	$success_bodies = array(
		// Aspect bodies to return.
		'"aspects":[{"id":1,"name":"Family","selected":true}]',
		'"aspects":[{"id":2,"name":"Friends","selected":true}]',
		'WP_Error',
		'error',
		'"aspects":[]',
		// Service bodies to return.
		'"configured_services":["facebook"]',
		'"configured_services":["twitter"]',
		'WP_Error',
		'error',
		'"configured_services":[]',
	);

	$body = $success_bodies[ $i++ ];
	if ( 'WP_Error' === $body ) {
		return new WP_Error( 'wp_error_code', 'WP_Error message' );
	} elseif ( 'error' === $body ) {
		return array( 'response' => array( 'code' => 999, 'message' => 'Error code message' ) );
	} else {
		return array(
			'body'     => $body,
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		);
	}
}


/**
 * Custom HTTP request responses for test_init_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_init_fail() {
	static $responses = array(
		false, // Will result in "Could not resolve host" error.
		array(
			'body'     => '<meta name="not-a-csrf-token" content="nope" />',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		),
	);
	return array_shift( $responses );
}

/**
 * Custom HTTP request responses for test_init_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_init_success() {
	static $tokens = array( 'token-a', 'token-b', 'token-c' );
	return array(
		'cookies'  => array( 'the_cookie' ),
		'body'     => sprintf( '<meta name="csrf-token" content="%s" />', array_shift( $tokens ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request response for test_fetch_token.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_fetch_token() {
	return array(
		'body'     => '<meta name="csrf-token" content="token-forced" />',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request response for test_login_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_login_fail() {
	return array( 'response' => array( 'code' => 999, 'message' => 'Error code message' ) );
}

/**
 * Custom HTTP request response for test_login_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_login_success() {
	static $i = 0;
	$responses = array(
		array( 'response' => array( 'code' => 302, 'message' => 'Found' ) ),
		array( 'response' => array( 'code' => 200, 'message' => 'OK' ) ),
	);
	// Since the same response pattern is used multiple times, just keep on looping through the responses.
	return $responses[ $i++ % count( $responses ) ];
}

/**
 * Custom HTTP request responses for:
 * test_get_aspects_services_invalid_argument
 * test_get_aspects_fail
 * test_get_services_fail.
 *
 * Return either a WP_Error object or and invalid response code.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_get_aspects_services_fail() {
	// Loop through responses using a static incrementing variable.
	// This is required, because no objects can be added to a static array.
	// see http://stackoverflow.com/a/10771559/3757422 for more info.
	static $i = 0;
	$responses = array(
		new WP_Error( 'wp_error_code', 'WP_Error message' ),
		array( 'response' => array( 'code' => 999, 'message' => 'Error code message' ) ),
	);
	// Since this filter is used by different tests, just keep on looping through the responses.
	return $responses[ $i++ % count( $responses ) ];
}

/**
 * Custom HTTP request responses for test_get_aspects_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_get_aspects_success() {
	static $aspects_bodies = array(
		'[{"id":1,"name":"Family","selected":true}]',
		'[{"id":2,"name":"Friends","selected":true}]',
		'[]',
	);

	return array(
		'body'     => '"aspects":' . array_shift( $aspects_bodies ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request responses for test_get_services_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_get_services_success() {
	static $services_bodies = array( '["facebook"]', '["twitter"]', '[]' );

	return array(
		'body'     => '"configured_services":' . array_shift( $services_bodies ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
	);
}

/**
 * Custom HTTP request responses for test_post_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_post_fail() {
	// Loop through responses using a static incrementing variable.
	// This is required, because no objects can be added to a static array.
	// see http://stackoverflow.com/a/10771559/3757422 for more info.
	static $i = 0;
	$responses = array(
		new WP_Error( 'wp_error_code', 'WP_Error message' ),
		array(
			'body'     => '{"error":"Error code message"}',
			'response' => array( 'code' => 999, 'message' => 'Error code message' ),
		),
	);
	return $responses[ $i++ ];
}

/**
 * Custom HTTP request responses for test_post_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_post_success() {
	static $post_bodies = array(
		'{"id":1,"public":true,"guid":"guid1","text":"text1"}',
		'{"id":2,"public":false,"guid":"guid2","text":"text2"}',
		'{"id":3,"public":false,"guid":"guid3","text":"text3"}',
	);

	return array(
		'body'     => array_shift( $post_bodies ),
		'response' => array( 'code' => 201, 'message' => 'Created' ),
	);
}

/**
 * Custom HTTP request responses for test_delete_fail.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_delete_fail() {
	// Loop through responses using a static incrementing variable.
	// This is required, because no objects can be added to a static array.
	// see http://stackoverflow.com/a/10771559/3757422 for more info.
	static $i = 0;
	$responses = array(
		// WP_Error.
		new WP_Error( 'wp_error_code', 'WP_Error message' ),
		// Posts.
		array( 'response' => array( 'code' => 404, 'message' => 'Not Found' ) ),
		array( 'response' => array( 'code' => 500, 'message' => 'Internal Server Error' ) ),
		// Comments.
		array( 'response' => array( 'code' => 404, 'message' => 'Not Found' ) ),
		array( 'response' => array( 'code' => 403, 'message' => 'Forbidden' ) ),
		// Invalid response code.
		array( 'response' => array( 'code' => 999, 'message' => 'Anything Really' ) ),
	);

	return $responses[ $i++ ];
}

/**
 * Custom HTTP request response for test_delete_success.
 *
 * @since 1.7.0
 */
function wp2d_api_pre_http_request_filter_delete_success() {
	return array( 'response' => array( 'code' => 204, 'message' => 'No Content' ) );
}
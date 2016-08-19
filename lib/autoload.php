<?php
namespace Admin_Page_Locking;

/**
 * Autoload classes.
 *
 * @param  string $cls Class name.
 */
function autoload( $cls ) {
	$cls = ltrim( $cls, '\\' );
	if ( strpos( $cls, 'Admin_Page_Locking\\' ) !== 0 ) {
		return;
	}

	$cls = strtolower( str_replace( [ 'Admin_Page_Locking\\', '_' ], [ '', '-' ], $cls ) );
	$dirs = explode( '\\', $cls );
	$cls = array_pop( $dirs );

	require_once( PATH . rtrim( '/lib/' . implode( '/', $dirs ), '/' ) . '/class-' . $cls . '.php' );
}
spl_autoload_register( '\Admin_Page_Locking\autoload' );

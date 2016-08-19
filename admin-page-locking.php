<?php
/**
 * Plugin Name:     Admin Page Locking
 * Plugin URI:      https://github.com/alleyinteractive/admin-page-locking/
 * Description:     Ensure that only one person is editing a given settings screen in the admin.
 * Author:          Matthew Boynes, Alley Interactive, Penske Media Corporation
 * Author URI:      https://www.alleyinteractive.com
 * Text Domain:     admin-page-locking
 * Domain Path:     /languages
 * Version:         0.1.0
 * License:         GNU Public License, version 2
 *
 * @package         Admin Page Locking
 */
/*
	Copyright 2010-2015 Mohammad Jangda, Automattic
	Copyright 2016 Alley Interactive, Penske Media Corporation

	The following code is a derivative work of code from the Automattic plugin
	Zoninator, which is licensed GPLv2. This code therefore is also licensed
	under the terms of the GNU Public License, version 2.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace Admin_Page_Locking;

define( __NAMESPACE__ . '\PATH', __DIR__ );
define( __NAMESPACE__ . '\URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

// Custom autoloader
require_once( PATH . '/lib/autoload.php' );

// Singleton trait
require_once( PATH . '/lib/trait-singleton.php' );

add_action( 'after_setup_theme', function() {
	/**
	 * Filter the screens that should be setup to be "locked".
	 *
	 * @param array $screens Array of screens (pagenames/hook suffixes) on which
	 *                       to enable locking.
	 */
	$screens = apply_filters( 'admin_page_locking_screens', [] );
	foreach ( $screens as $screen ) {
		new \Admin_Page_Locking\Screen( $screen );
	}
} );

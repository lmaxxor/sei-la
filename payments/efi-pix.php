<?php
/**
 * Plugin Name: EFI Pix Payment
 * Description: Integração com a API Pix da EFI Bank para pagamentos.
 * Version: 1.0
 * Author: Digital Soul | Pedro H. Goffi & Willian Silva
 * License: GPL2 
 */
/** @NOTE: Esse arquivo usa funções do wordpress */

// Main plugin file efi-pix-payment.php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path( __FILE__ ) . 'settings.php';

// Register Settings and Admin Menu
add_action( 'admin_menu', 'efi_pix_payment_menu' );
add_action( 'admin_init', 'efi_pix_register_settings' );

// Define the menu function
function efi_pix_payment_menu(): void {
    add_menu_page(
        'EFI Pix Settings',
        'EFI Pix Payment',
        'manage_options',
        'efi_pix_payment_settings',
        'efi_pix_settings_page',
        'dashicons-admin-generic',
        25
    );
}
<?php
/**
 * Email Actions
 *
 * @package     EDD
 * @subpackage  Emails
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.8.2
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Triggers Purchase Receipt to be sent after the payment status is updated
 *
 * @since 1.0.8.4
 * @since 2.8 - Add parameters for EDD_Payment and EDD_Customer object.
 *
 * @param int          $payment_id Payment ID.
 * @param EDD_Payment  $payment    Payment object for payment ID.
 * @param EDD_Customer $customer   Customer object for associated payment.
 * @return void
 */
function edd_trigger_purchase_receipt( $payment_id = 0, $payment = null, $customer = null ) {
	// Make sure we don't send a purchase receipt while editing a payment
	$action = filter_input( INPUT_POST, 'edd_action', FILTER_SANITIZE_STRING );
	if ( 'update_payment_details' === $action ) {
		return;
	}
	if ( null === $payment ) {
		$payment = new EDD_Payment( $payment_id );
	}
	if ( $payment->order instanceof \EDD\Orders\Order && 'refund' === $payment->order->type ) {
		return;
	}

	// Send email with secure download link
	edd_email_purchase_receipt( $payment_id, true, '', $payment, $customer );
}
add_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999, 3 );

/**
 * Resend the Email Purchase Receipt. (This can be done from the Payment History page)
 *
 * @since 1.0
 * @param array $data Payment Data
 * @return void
 */
function edd_resend_purchase_receipt( $data ) {

	$purchase_id = absint( $data['purchase_id'] );

	if( empty( $purchase_id ) ) {
		return;
	}

	if( ! current_user_can( 'edit_shop_payments' ) ) {
		wp_die( __( 'You do not have permission to edit this payment record', 'easy-digital-downloads' ), __( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	$email = ! empty( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

	if( empty( $email ) ) {
		$customer = new EDD_Customer( edd_get_payment_customer_id( $purchase_id ) );
		$email    = $customer->email;
	}

	$sent = edd_email_purchase_receipt( $purchase_id, false, $email );

	// Grab all downloads of the purchase and update their file download limits, if needed
	// This allows admins to resend purchase receipts to grant additional file downloads
	$downloads = edd_get_payment_meta_cart_details( $purchase_id, true );

	if ( is_array( $downloads ) ) {
		foreach ( $downloads as $download ) {
			$limit = edd_get_file_download_limit( $download['id'] );
			if ( ! empty( $limit ) ) {
				edd_set_file_download_limit_override( $download['id'], $purchase_id );
			}
		}
	}

	edd_redirect(
		add_query_arg(
			array(
				'edd-message' => $sent ? 'email_sent' : 'email_send_failed',
				'edd-action'  => false,
				'purchase_id' => false,
				'email'       => false,
			)
		)
	);
}
add_action( 'edd_email_links', 'edd_resend_purchase_receipt' );

/**
 * Trigger the sending of a Test Email
 *
 * @since 1.5
 * @param array $data Parameters sent from Settings page
 * @return void
 */
function edd_send_test_email( $data ) {
	if ( ! wp_verify_nonce( $data['_wpnonce'], 'edd-test-email' ) ) {
		return;
	}

	// Send a test email
	edd_email_test_purchase_receipt();

	$url = edd_get_admin_url(
		array(
			'page'        => 'edd-settings',
			'tab'         => 'emails',
			'section'     => 'purchase_receipts',
			'edd-message' => 'test-purchase-email-sent',
		)
	);
	edd_redirect( $url );
}
add_action( 'edd_send_test_email', 'edd_send_test_email' );
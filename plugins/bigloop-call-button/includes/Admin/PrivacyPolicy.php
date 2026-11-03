<?php
/**
 * Suggested privacy-policy text (Settings → Privacy → Policy Guide).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Describes exactly what the plugin stores, so site owners can copy it into their policy.
 */
final class PrivacyPolicy {

	/**
	 * Registers the suggested text.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Bigloop Call Button & Contact Bar adds contact buttons (phone, WhatsApp, email and others) to this site. The text below describes what it stores. Adjust it if you turned click statistics, country targeting or Google Analytics events off.', 'bigloop-call-button' ) . '</p>';
		$content .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'bigloop-call-button' ) . ' </strong>';
		$content .= esc_html__( 'When you use one of the contact buttons on this site, we record anonymous usage statistics on our own server: which button was used, the type of device (desktop, tablet or mobile), the page and the time. We do not store your IP address, browser details, cookies or any information that identifies you, and these statistics are never shared with third parties.', 'bigloop-call-button' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If this site uses Google Analytics, a button click may also be reported to Google Analytics as an event, under Google\'s privacy policy.', 'bigloop-call-button' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Some buttons may be shown or hidden depending on your country. The country is provided by our hosting or CDN provider and is not stored on our server; your browser keeps the two-letter country code in session storage until you close the tab, so it is not looked up on every page. Choosing a button opens the corresponding app or service (for example your phone app, WhatsApp or email), and that service\'s own privacy policy applies.', 'bigloop-call-button' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Bigloop Call Button & Contact Bar', 'bigloop-call-button' ), wp_kses_post( $content ) );
	}
}

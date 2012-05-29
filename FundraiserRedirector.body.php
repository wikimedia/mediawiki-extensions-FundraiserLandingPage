<?php
/*
 * Provides redirect service for donors coming from external sites (so that they get
 * directed to the proper form for their country).
 *
 * @author Ryan Kaldari <rkaldari@wikimedia.org>
 * @author Peter Gehres <pgehres@wikimedia.org>
 */
class FundraiserRedirector extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'FundraiserRedirector' );
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgFundraiserLPDefaults, $wgFundraiserLandingPageChapters;
		
		// Set the country parameter
		$country = $wgRequest->getVal( 'country' );
		// If no country was passed do a GeoIP lookup
		if ( !$country ) {
			if ( function_exists( 'geoip_country_code_by_name' ) ) {
				$ip = wfGetIP();
				if ( IP::isValid( $ip ) ) {
					$country = geoip_country_code_by_name( $ip );
				}
			}
		}
		// If country still isn't set, set it to the default
		if ( !$country ) {
			$country = $wgFundraiserLPDefaults[ 'country' ];
		}
		
		$params = array( 'country' => $country );
		
		// Pass any other params that are set
		$excludeKeys = array( 'country', 'title' );
		foreach ( $wgRequest->getValues() as $key => $value ) {
			// Skip the required variables
			if ( !in_array( $key, $excludeKeys ) ) {
				$params[$key] = $value;
			}
		}

		// set the default redirect
		$redirectURL = $this->getTitleFor( 'FundraiserLandingPage' )->getLocalUrl( $params );

		// if the country is covered by a payment-processing chapter, redirect
		// the donor to the chapter's default landing page
		if( array_key_exists( $params['country'], $wgFundraiserLandingPageChapters ) ){
			// Get the message key for the chapter's landing page
			$message_key = $wgFundraiserLandingPageChapters[ $params['country'] ];
			// Get the url for the chapter's landing page
			$message = wfMessage( $message_key )->plain();
			// if the message is not equal to the default message that is returned
			// for a missing message, set the redirect URL to the message
			if( $message != "<$message_key>" ){
				$redirectURL = $message;
			}
		}
		// Redirect
		$wgOut->redirect( $redirectURL );
	}
}

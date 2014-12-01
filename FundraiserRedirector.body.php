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
		global $wgFundraiserLPDefaults, $wgFundraiserLandingPageChapters;

		// Check whether GeoIP cookie is already present.
		$country = false;
		$geoip = $this->getRequest()->getCookie( 'GeoIP', '' );
		if ( $geoip ) {
			$components = explode( ':', $geoip );
			$country = $components[0];
		} else {
			// If no country was passed, do a GeoIP lookup.
			if ( function_exists( 'geoip_country_code_by_name' ) ) {
				$ip = $this->getRequest()->getIP();
				if ( IP::isValid( $ip ) ) {
					$country = geoip_country_code_by_name( $ip );
				}
			}
		}
		// If country still isn't set, set it to the default
		if ( !$country ) {
			$country = $wgFundraiserLPDefaults[ 'country' ];
		}

		// Set the language parameter
		$language = $this->getRequest()->getVal( 'uselang' );
		// If not set, try the browser language
		if( !$language ) {
			$mwLanguages = array_keys( Language::fetchLanguageNames() );
			$languages = array_keys( $this->getRequest()->getAcceptLang() );
			foreach( $languages as $tryLanguage ) {
				if( in_array( $tryLanguage, $mwLanguages ) ) {
					// use the language if it is supported in MediaWiki
					$language = $tryLanguage;
					break; // don't search further
				}
			}
		}

		$params = array(
			'country' => $country,
			'uselang' => $language,
			// set default tracking variables that will be overridden
			// by anything passed in the query string
			'utm_medium' => "spontaneous",
			'utm_source' => "fr-redir",
			'utm_campaign' => "spontaneous",
		);
		
		// Pass any other params that are set
		$excludeKeys = array( 'country', 'title' );
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
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
			$message = $this->msg( $message_key )->plain();
			// if the message is not equal to the default message that is returned
			// for a missing message, set the redirect URL to the message
			if( $message != "<$message_key>" ){
				$redirectURL = $message;

				if( strpos( $redirectURL, "LandingCheck" ) !== false ){
					// the chapter is using LandingCheck, so go ahead and send
					// all of the params as well
					$querystring = http_build_query( $params );

					if( strpos( $redirectURL, "?" ) === false ){
						$redirectURL .= "?" . $querystring;
					}
					else{
						$redirectURL .= "&" . $querystring;
					}
				}
			}
		}
		// Redirect
		$this->getOutput()->redirect( $redirectURL );
	}
}

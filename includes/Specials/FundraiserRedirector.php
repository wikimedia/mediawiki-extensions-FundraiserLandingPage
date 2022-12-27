<?php
/*
 * Provides redirect service for donors coming from external sites (so that they get
 * directed to the proper form for their country).
 *
 * @author Ryan Kaldari <rkaldari@wikimedia.org>
 * @author Peter Gehres <pgehres@wikimedia.org>
 */

namespace MediaWiki\Extension\FundraiserLandingPage\Specials;

use MediaWiki\MediaWikiServices;
use SpecialPage;
use UnlistedSpecialPage;

class FundraiserRedirector extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'FundraiserRedirector' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		global $wgFundraiserLPDefaults, $wgFundraiserLandingPageChapters;

		// Country passed in the URL param gets first precedence.
		$country = $this->getRequest()->getVal( 'country' );
		if ( !self::isValidIsoCountryCode( $country ) ) {
			$country = '';
		}

		// Get country from the GeoIP cookie if present.
		if ( !$country ) {
			$geoip = $this->getRequest()->getCookie( 'GeoIP', '' );
			if ( $geoip ) {
				$components = explode( ':', $geoip );
				$country = $components[0];
			}
		}

		if ( !$country ) {
			// If country isn't set, try realoding the page (redirecting to the same page
			// with a 'reloaded' URL param to prevent a loop). This may be necessary if
			// no GeoIP cookie was previously set for this domain. While our front-end
			// cache showuld always set a GeoIP cookie, it won't be visible server-side
			// until it's reflected back by the browser. For details, see T317427.
			if ( !$this->getRequest()->getBool( 'reloaded' ) ) {
				$this->reload();
				return;
			}

			// Still no country? use the default.
			$country = $wgFundraiserLPDefaults[ 'country' ];
		}

		// Set the language parameter
		$language = $this->getRequest()->getVal( 'uselang' );
		// If not set, try the browser language
		if ( !$language ) {
			$mwLanguages = array_keys( MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageNames() );
			$languages = array_keys( $this->getRequest()->getAcceptLang() );
			foreach ( $languages as $tryLanguage ) {
				if ( in_array( $tryLanguage, $mwLanguages ) ) {
					// use the language if it is supported in MediaWiki
					$language = $tryLanguage;
					// don't search further
					break;
				}
			}
		}

		$params = [
			'country' => $country,
			'uselang' => $language,
			// set default tracking variables that will be overridden
			// by anything passed in the query string
			'utm_medium' => "spontaneous",
			'utm_source' => "fr-redir",
			'utm_campaign' => "spontaneous",
		];

		// Pass any other params that are set
		// If we arrived here with a 'reloaded' param, don't pass that along when
		// redirecting to Special:FundraiserLandingPage.
		$excludeKeys = [ 'country', 'title', 'reloaded' ];
		if ( $this->getRequest()->getQueryValuesOnly() ) {
			foreach ( $this->getRequest()->getQueryValuesOnly() as $key => $value ) {
				// Skip the required variables
				if ( !in_array( $key, $excludeKeys ) ) {
					$params[$key] = $value;
				}
			}
		}

		// set the default redirect
		$redirectURL = SpecialPage::getTitleFor( 'FundraiserLandingPage' )->getLocalUrl( $params );

		// if the country is covered by a payment-processing chapter, redirect
		// the donor to the chapter's default landing page
		if ( array_key_exists( $params['country'], $wgFundraiserLandingPageChapters ) ) {
			// Get the message key for the chapter's landing page
			$message_key = $wgFundraiserLandingPageChapters[ $params['country'] ];
			// Get the url for the chapter's landing page
			$message = $this->msg( $message_key )->plain();
			// if the message is not equal to the default message that is returned
			// for a missing message, set the redirect URL to the message
			if ( $message != "<$message_key>" ) {
				$redirectURL = $message;

				if ( strpos( $redirectURL, "LandingCheck" ) !== false ) {
					// the chapter is using LandingCheck, so go ahead and send
					// all of the params as well
					$querystring = http_build_query( $params );

					if ( strpos( $redirectURL, "?" ) === false ) {
						$redirectURL .= "?" . $querystring;
					} else {
						$redirectURL .= "&" . $querystring;
					}
				}
			}
		}
		// Redirect
		$this->getOutput()->redirect( $redirectURL );
	}

	/**
	 * Reload the page by redirecting to the same URL, adding a 'reloaded' URL param,
	 * and preserving other params from the current request.
	 *
	 * @return void
	 */
	private function reload() {
		$params = $this->getRequest()->getQueryValuesOnly();

		// Title may be re-added below by getTitleFor()
		unset( $params[ 'title' ] );

		// 'reloaded' param used to prevent an infinite redirect loop
		$params[ 'reloaded' ] = 'true';

		$redirectURL = SpecialPage::getTitleFor( 'FundraiserRedirector' )
			->getLocalUrl( $params );

		$this->getOutput()->redirect( $redirectURL );
	}

	/**
	 * Checks to see if $country is a valid iso 3166-1 country code.
	 * DOES NOT VERIFY THAT WE FUNDRAISE THERE. Only that the code makes sense.
	 * @param string $country the code we want to check
	 * @return bool
	 */
	public static function isValidIsoCountryCode( $country ) {
		/**
		 * List of valid iso 3166 country codes, regenerated on 1380836686
		 * Code generated by a happy script at
		 * https://gerrit.wikimedia.org/r/#/admin/projects/wikimedia/fundraising/tools,branches
		 */
		$iso_3166_codes = [
			'AF', 'AX', 'AL', 'DZ', 'AS', 'AD', 'AO', 'AI', 'AQ', 'AG', 'AR', 'AM', 'AW', 'AU',
			'AT', 'AZ', 'BS', 'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ', 'BM', 'BT', 'BO', 'BQ',
			'BA', 'BW', 'BV', 'BR', 'IO', 'BN', 'BG', 'BF', 'BI', 'KH', 'CM', 'CA', 'CV', 'KY',
			'CF', 'TD', 'CL', 'CN', 'CX', 'CC', 'CO', 'KM', 'CG', 'CD', 'CK', 'CR', 'CI', 'HR',
			'CU', 'CW', 'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG', 'SV', 'GQ', 'ER', 'EE',
			'ET', 'FK', 'FO', 'FJ', 'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE', 'DE', 'GH',
			'GI', 'GR', 'GL', 'GD', 'GP', 'GU', 'GT', 'GG', 'GN', 'GW', 'GY', 'HT', 'HM', 'VA',
			'HN', 'HK', 'HU', 'IS', 'IN', 'ID', 'IR', 'IQ', 'IE', 'IM', 'IL', 'IT', 'JM', 'JP',
			'JE', 'JO', 'KZ', 'KE', 'KI', 'KP', 'KR', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR',
			'LY', 'LI', 'LT', 'LU', 'MO', 'MK', 'MG', 'MW', 'MY', 'MV', 'ML', 'MT', 'MH', 'MQ',
			'MR', 'MU', 'YT', 'MX', 'FM', 'MD', 'MC', 'MN', 'ME', 'MS', 'MA', 'MZ', 'MM', 'NA',
			'NR', 'NP', 'NL', 'NC', 'NZ', 'NI', 'NE', 'NG', 'NU', 'NF', 'MP', 'NO', 'OM', 'PK',
			'PW', 'PS', 'PA', 'PG', 'PY', 'PE', 'PH', 'PN', 'PL', 'PT', 'PR', 'QA', 'RE', 'RO',
			'RU', 'RW', 'BL', 'SH', 'KN', 'LC', 'MF', 'PM', 'VC', 'WS', 'SM', 'ST', 'SA', 'SN',
			'RS', 'SC', 'SL', 'SG', 'SX', 'SK', 'SI', 'SB', 'SO', 'ZA', 'GS', 'SS', 'ES', 'LK',
			'SD', 'SR', 'SJ', 'SZ', 'SE', 'CH', 'SY', 'TW', 'TJ', 'TZ', 'TH', 'TL', 'TG', 'TK',
			'TO', 'TT', 'TN', 'TR', 'TM', 'TC', 'TV', 'UG', 'UA', 'AE', 'GB', 'US', 'UM', 'UY',
			'UZ', 'VU', 'VE', 'VN', 'VG', 'VI', 'WF', 'EH', 'YE', 'ZM', 'ZW',
		];

		return in_array( $country, $iso_3166_codes );
	}

}

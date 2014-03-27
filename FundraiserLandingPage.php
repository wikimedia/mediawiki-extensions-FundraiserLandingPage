<?php
/*
 * Extension:FundraiserLandingPage. This extension takes URL parameters in the
 * QueryString and passes them to the specified template as template variables.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install the FundraiserLandingPage extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/FundraiserLandingPage/FundraiserLandingPage.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits[ 'specialpage' ][ ] = array(
	'path'           => __FILE__,
	'name'           => 'FundraiserLandingPage',
	'author'         => array( 'Peter Gehres', 'Ryan Kaldari' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:FundraiserLandingPage',
	'descriptionmsg' => 'fundraiserlandingpage-desc',
	'version'        => '1.1.0',
);

$dir = __DIR__ . '/';

$wgAutoloadClasses[ 'FundraiserLandingPage' ] = $dir . 'FundraiserLandingPage.body.php';
$wgAutoloadClasses[ 'FundraiserRedirector' ] = $dir . 'FundraiserRedirector.body.php';

$wgMessagesDirs['FundraiserLandingPage'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles[ 'FundraiserLandingPage' ] = $dir . 'FundraiserLandingPage.i18n.php';

$wgSpecialPages[ 'FundraiserLandingPage' ] = 'FundraiserLandingPage';
$wgSpecialPages[ 'FundraiserRedirector' ] = 'FundraiserRedirector';

// Specify the function that will initialize the parser function hooks.
$wgHooks[ 'ParserFirstCallInit' ][ ] = 'fundraiserLandingPageSetupParserFunction';
$wgExtensionMessagesFiles[ 'FundraiserLandingPageMagic' ] = $dir . 'FundraiserLandingPage.i18n.magic.php';

/*
 * Defaults for the required fields.  These fields will be included whether
 * or not they are passed through the querystring.
 */
$wgFundraiserLPDefaults = array(
	'template'             => 'Lp-layout-default',
	'appeal'               => 'Appeal-default',
	'appeal-template'      => 'Appeal-template-default',
	'form-template'        => 'Form-template-default',
	'form-countryspecific' => 'Form-countryspecific-control',
	'country'              => 'XX'
);

// Adding configurrable variable for caching time
$wgFundraiserLandingPageMaxAge = 600; // 10 minutes

// Array of chapter countries and the MediaWiki message that contains
// the redirect URL.
$wgFundraiserLandingPageChapters = array(
	'CH' => "fundraiserlandingpage-wmch-landing-page",
	'DE' => "fundraiserlandingpage-wmde-landing-page",
//	'FR' => "fundraiserlandingpage-wmfr-landing-page", // disabled May 2013 per WMFr request
//	'GB' => "fundraiserlandingpage-wmuk-landing-page", // disabled for 2012 per agreement with WMUK

/*
 *	All French Territories disabled May 2013 per WMFr request
	// French Territories per WMFr email 2012-06-13
	'GP' => "fundraiserlandingpage-wmfr-landing-page", // Guadeloupe
	'MQ' => "fundraiserlandingpage-wmfr-landing-page", // Martinique
	'GF' => "fundraiserlandingpage-wmfr-landing-page", // French Guiana
	'RE' => "fundraiserlandingpage-wmfr-landing-page", // Réunion
	'YT' => "fundraiserlandingpage-wmfr-landing-page", // Mayotte
	'PM' => "fundraiserlandingpage-wmfr-landing-page", // Saint Pierre and Miquelon
	'NC' => "fundraiserlandingpage-wmfr-landing-page", // New Caledonia
	'PF' => "fundraiserlandingpage-wmfr-landing-page", // French Polynesia
	'WF' => "fundraiserlandingpage-wmfr-landing-page", // Wallis and Futuna
	'BL' => "fundraiserlandingpage-wmfr-landing-page", // Saint Barthélemy
	'MF' => "fundraiserlandingpage-wmfr-landing-page", // Collectivity of San Martin
	'TF' => "fundraiserlandingpage-wmfr-landing-page", // French Southern and Antarctic Lands
*/

);

/**
 * This function limits the possible characters passed as template keys and
 * values to letters, numbers, hypens, underscores, and the forward slash.
 * The function also performs standard escaping of the passed values.
 *
 * @param $string  string The unsafe string to escape and check for invalid characters
 * @param $default string A default value to return if when making the $string safe no
 *                 results are returned.
 *
 * @return mixed|String A string matching the regex or an empty string
 */
function fundraiserLandingPageMakeSafe( $string, $default = '' ) {
	if ( $default != '' ) {
		$default = fundraiserLandingPageMakeSafe( $default );
	}

	$num = preg_match( '([a-zA-Z0-9_\-/]+)', $string, $matches );

	if ( $num == 1 ) {
		# theoretically this is overkill, but better safe than sorry
		return wfEscapeWikiText( htmlspecialchars( $matches[ 0 ] ) );
	}
	return $default;
}

/**
 * Register the parser function hooks 'switchlanguage' and 'switchcountry'
 * with the MW backend.
 *
 * @see FundraiserLandingPageSwitchLanguage
 * @see FundraiserLandingPageSwitchCountry
 *
 * @param $parser Parser The WM parser object to hook into.
 *
 * @return bool Always true
 */
function fundraiserLandingPageSetupParserFunction( &$parser ) {
	$parser->setFunctionHook( 'switchlanguage', 'fundraiserLandingPageSwitchLanguage' );
	$parser->setFunctionHook( 'switchcountry', 'fundraiserLandingPageSwitchCountry' );

	// Return true so that MediaWiki continues to load extensions.
	return true;
}

/**
 * Attempts to load a language localized template. Precedence is Language,
 * Country, Root. It is assumed that all parts of the title are separated
 * with '/'.
 *
 * @param Parser $parser   Reference to the WM parser object
 * @param string $page     The template page root to load
 * @param string $language The language to attempt to localize onto
 * @param string $country  The country to attempt to localize onto
 *
 * @return string The wikitext template
 */
function fundraiserLandingPageSwitchLanguage( $parser, $page = '', $language = 'en', $country = 'XX' ) {
	$page = fundraiserLandingPageMakeSafe( $page );
	$country = fundraiserLandingPageMakeSafe( $country, 'XX' );
	$language = fundraiserLandingPageMakeSafe( $language, 'en' );

	if ( Title::newFromText( "Template:$page/$language/$country" )->exists() ) {
		$tpltext = "$page/$language/$country";
	} elseif ( Title::newFromText( "Template:$page/$language" )->exists() ) {
		$tpltext = "$page/$language";
	} else {
		// If all the variants don't exist, then merely return the base. If
		// something really screwy happened and the base doesn't exist either
		// we will let the WM error handler sort it out.

		$tpltext = $page;
	}

	return array( "{{Template:$tpltext}}", 'noparse' => false );
}

/**
 * Attempts to load a language localized template. Precedence is Country,
 * Language, Root. It is assumed that all parts of the title are separated
 * with '/'.
 *
 * @param Parser $parser   Reference to the WM parser object
 * @param string $page     The template page root to load
 * @param string $country  The country to attempt to localize onto
 * @param string $language The language to attempt to localize onto
 *
 * @return string The wikitext template
 */
function fundraiserLandingPageSwitchCountry( $parser, $page = '', $country = 'XX', $language = 'en' ) {
	$page = fundraiserLandingPageMakeSafe( $page );
	$country = fundraiserLandingPageMakeSafe( $country, 'XX' );
	$language = fundraiserLandingPageMakeSafe( $language, 'en' );

	if ( Title::newFromText( "Template:$page/$country/$language" )->exists() ) {
		$tpltext = "$page/$country/$language";

	} elseif ( Title::newFromText( "Template:$page/$country" )->exists() ) {
		$tpltext = "$page/$country";

	} else {
		// If all the variants don't exist, then merely return the base. If
		// something really screwy happened and the base doesn't exist either
		// we will let the WM error handler sort it out.

		$tpltext = $page;
	}

	return array( "{{Template:$tpltext}}", 'noparse' => false );
}

// These variables are theoretically in ContributionTracking,
// but setting a default here for safety
$wgContributionTrackingFundraiserMaintenance = false;
$wgContributionTrackingFundraiserMaintenanceUnsched = false;

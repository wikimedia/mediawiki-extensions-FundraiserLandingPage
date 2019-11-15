<?php
class FundraiserLandingPageHooks {
	/**
	 * Register the parser function hooks 'switchlanguage' and 'switchcountry'
	 * with the MW backend.
	 *
	 * @param Parser $parser The MW parser object to hook into.
	 *
	 * @return bool Always true
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'switchlanguage',
			'FundraiserLandingPage::fundraiserLandingPageSwitchLanguage'
		);
		$parser->setFunctionHook(
			'switchcountry',
			'FundraiserLandingPage::fundraiserLandingPageSwitchCountry'
		);

		// Return true so that MediaWiki continues to load extensions.
		return true;
	}

	/**
	 * BeforePageDisplay hook handler
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( $out, $skin ) {
		// TODO Restrict logging to anonymous article viewing once it's demonstrated
		// that data from EventLogging is the same as that received from Kafkatee.
		$out->addModules( 'ext.fundraiserLandingPage.LogPageview' );
		return true;
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler
	 * Send php config vars to js via ResourceLoader
	 *
	 * @param array &$vars variables to be added to the output of the startup module
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgFundraiserLandingPageELSampleRate;

		$vars[ 'wgFundraiserLandingPageELSampleRate' ] =
			$wgFundraiserLandingPageELSampleRate;

		return true;
	}
}

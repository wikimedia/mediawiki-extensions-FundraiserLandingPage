<?php
class FundraiserLandingPageHooks {
	/**
	 * Register the parser function hooks 'switchlanguage' and 'switchcountry'
	 * with the MW backend.
	 *
	 * @param Parser &$parser The MW parser object to hook into.
	 *
	 * @return bool Always true
	 */
	public static function onParserFirstCallInit( &$parser ) {
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
}

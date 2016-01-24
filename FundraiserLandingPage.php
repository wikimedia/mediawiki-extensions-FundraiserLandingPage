<?php
/*
 * Extension:FundraiserLandingPage. This extension takes URL parameters in the
 * QueryString and passes them to the specified template as template variables.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 * To install the FundraiserLandingPage extension, put the following line in LocalSettings.php:
 * require_once( "\$IP/extensions/FundraiserLandingPage/FundraiserLandingPage.php" );
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'FundraiserLandingPage' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['FundraiserLandingPage'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['FundraiserLandingPageAlias'] = __DIR__ . '/FundraiserLandingPage.alias.php';
	$wgExtensionMessagesFiles['FundraiserLandingPageMagic'] = __DIR__ . '/FundraiserLandingPage.i18n.magic.php';
	/*wfWarn(
		'Deprecated PHP entry point used for FundraiserLandingPage extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);*/
	return;
} else {
	die( 'This version of the FundraiserLandingPage extension requires MediaWiki 1.25+' );
}

<?php
/*
 * SpecialPage definition for FundraiserLandingPage.  Extending UnlistedSpecialPage
 * since this page does not need to listed in Special:SpecialPages.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 */
class FundraiserLandingPage extends UnlistedSpecialPage
{
	function __construct() {
		parent::__construct( 'FundraiserLandingPage' );
	}

	function execute( $par ) {
		global $wgFundraiserLPDefaults, $wgRequest, $wgOut, $wgFundraiserLandingPageMaxAge;

		#Set squid age
		$wgOut->setSquidMaxage( $wgFundraiserLandingPageMaxAge );
		$this->setHeaders();

		# set the page title to something useful
		$wgOut->setPagetitle( wfMsg( 'donate_interface-make-your-donation' ) );

		# clear output variable to be safe
		$output = '';
		
		# begin generating the template call
		$template = fundraiserLandingPageMakeSafe( $wgRequest->getText( 'template', $wgFundraiserLPDefaults[ 'template' ] ) );
		$output .= "{{ $template\n";
		
		# get the required variables (except template and country) to use for the landing page
		$requiredParams = array(
			'appeal',
			'appeal-template',
			'form-template',
			'form-countryspecific'
		);
		foreach( $requiredParams as $requiredParam ) {
			$param = fundraiserLandingPageMakeSafe(
				$wgRequest->getText( $requiredParam, $wgFundraiserLPDefaults[$requiredParam] )
			);
			// Add them to the template call
			$output .= "| $requiredParam = $param\n";
		}

		# get the country code
		$country = $wgRequest->getVal( 'country' );
		# If country still isn't set, set it to the default
		if ( !$country ) {
			$country = $wgFundraiserLPDefaults[ 'country' ];
		}
		$country = fundraiserLandingPageMakeSafe( $country );
		$output .= "| country = $country\n";

		$excludeKeys = $requiredParams + array( 'template', 'country', 'title' );
		
		# add any other parameters passed in the querystring
		foreach ( $wgRequest->getValues() as $k_unsafe => $v_unsafe ) {
			# skip the required variables
			if ( in_array( $k_unsafe, $excludeKeys ) ) {
				continue;
			}
			# get the variable's name and value
			$key = fundraiserLandingPageMakeSafe( $k_unsafe );
			$val = fundraiserLandingPageMakeSafe( $v_unsafe );
			# print to the template in wiki-syntax
			$output .= "| $key = $val\n";
		}
		# close the template call
		$output .= "}}";

		# print the output to the page
		$wgOut->addWikiText( $output );
	}
}


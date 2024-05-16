<?php

namespace MediaWiki\Extension\FundraiserLandingPage\Specials;

/*
 * SpecialPage definition for FundraiserLandingPage.  Extending UnlistedSpecialPage
 * since this page does not need to listed in Special:SpecialPages.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 */

use Parser;
use Title;
use UnlistedSpecialPage;

class FundraiserLandingPage extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'FundraiserLandingPage' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		global $wgFundraiserLPDefaults, $wgFundraiserLandingPageMaxAge,
			$wgContributionTrackingFundraiserMaintenance,
			$wgContributionTrackingFundraiserMaintenanceUnsched;

		if ( $wgContributionTrackingFundraiserMaintenance
			|| $wgContributionTrackingFundraiserMaintenanceUnsched
		) {
			$this->getOutput()->redirect(
				Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL(), '302'
			);
		}

		$out = $this->getOutput();
		$request = $this->getRequest();

		// Set squid age
		$out->setCdnMaxage( $wgFundraiserLandingPageMaxAge );

		if ( $this->isFundraiseUp() ) {
			$out->addScript( $this->getFundraiseUpJavascript() );
		}
		$this->setHeaders();

		// set the page title to something useful
		$titleMsg = $this->msg( 'donate_interface-make-your-donation' );
		if ( !is_callable( [ $out, 'setPageTitleMsg' ] ) ) {
			// Backward compatibility with MW < 1.41
			$out->setPageTitle( $titleMsg );
		} else {
			// MW >= 1.41
			$out->setPageTitleMsg( $titleMsg );
		}

		// and add a <meta name="description"> tag to give search engines a useful blurb
		$out->addMeta( 'description', $this->msg( 'fundraiserlandingpage-meta-description' ) );

		// And mark the page as allowed for search engine indexing (default for SpecialPages is noindex)
		$out->setIndexPolicy( 'index' );

		// clear output variable to be safe
		$output = '';

		// begin generating the template call
		$template = self::fundraiserLandingPageMakeSafe(
			$request->getText( 'template', $wgFundraiserLPDefaults[ 'template' ] )
		);
		$output .= "{{ $template\n";

		// get the required variables (except template and country) to use for the landing page
		$requiredParams = [
			'appeal',
			'appeal-template',
			'form-template',
			'form-countryspecific'
		];
		foreach ( $requiredParams as $requiredParam ) {
			$param = self::fundraiserLandingPageMakeSafe(
				$request->getText( $requiredParam, $wgFundraiserLPDefaults[$requiredParam] )
			);
			// Add them to the template call
			$output .= "| $requiredParam = $param\n";
		}

		// get the country code
		$country = $request->getVal( 'country' );
		// If country still isn't set, set it to the default
		if ( !$country ) {
			$country = $wgFundraiserLPDefaults[ 'country' ];
		}
		$country = self::fundraiserLandingPageMakeSafe( $country );
		$output .= "| country = $country\n";

		// @phan-suppress-next-line PhanUselessBinaryAddRight
		$excludeKeys = $requiredParams + [ 'template', 'country', 'title' ];

		// if there are any other parameters passed in the querystring, add them
		if ( $request->getQueryValuesOnly() ) {
			foreach ( $request->getQueryValuesOnly() as $k_unsafe => $v_unsafe ) {
				// skip the required variables
				if ( in_array( $k_unsafe, $excludeKeys ) ) {
					continue;
				}
				// get the variable's name and value
				$key = self::fundraiserLandingPageMakeSafe( $k_unsafe );
				$val = self::fundraiserLandingPageMakeSafe( $v_unsafe );
				// print to the template in wiki-syntax
				$output .= "| $key = $val\n";
			}
		}

		// close the template call
		$output .= "}}";

		// Hijack parser internals to workaround T156184.  This should be safe
		// since we've sanitized all params.
		$parserOptions = $out->parserOptions();
		$parserOptions->setAllowUnsafeRawHtml( true );

		// print the output to the page
		$out->addWikiTextAsInterface( $output );
	}

	/**
	 * This function limits the possible characters passed as template keys and
	 * values to letters, numbers, hyphens, underscores, and the forward slash.
	 * The function also performs standard escaping of the passed values.
	 *
	 * @param mixed $value The unsafe value to escape and check for invalid characters
	 * @param string $default A default value to return if when making the $string safe no
	 *                 results are returned.
	 *
	 * @return string A string matching the regex or an empty string
	 * @suppress SecurityCheck-DoubleEscaped double escaping is on purpose per the inline
	 *                                       comment
	 */
	private static function fundraiserLandingPageMakeSafe( $value, $default = '' ) {
		if ( $default != '' ) {
			$default = self::fundraiserLandingPageMakeSafe( $default );
		}

		if ( !is_string( $value ) ) {
			// In case someone has passed in an array as a request parameter
			return $default;
		}

		$num = preg_match( '/^([-a-zA-Z0-9_\/]+)$/', $value, $matches );

		if ( $num == 1 ) {
			# theoretically this is overkill, but better safe than sorry
			return wfEscapeWikiText( htmlspecialchars( $matches[1] ) );
		}
		return $default;
	}

	/**
	 * Attempts to load a language localized template. Precedence is Language,
	 * Country, Root. It is assumed that all parts of the title are separated
	 * with '/'.
	 *
	 * @param Parser $parser Reference to the WM parser object
	 * @param string $page The template page root to load
	 * @param string $language The language to attempt to localize onto
	 * @param string $country The country to attempt to localize onto
	 *
	 * @return array The wikitext template
	 */
	public static function fundraiserLandingPageSwitchLanguage( $parser, $page = '',
		$language = 'en', $country = 'XX'
	) {
		$page = self::fundraiserLandingPageMakeSafe( $page );
		$country = self::fundraiserLandingPageMakeSafe( $country, 'XX' );
		$language = self::fundraiserLandingPageMakeSafe( $language, 'en' );

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

		return [ "{{Template:$tpltext}}", 'noparse' => false ];
	}

	/**
	 * Attempts to load a language localized template. Precedence is Country,
	 * Language, Root. It is assumed that all parts of the title are separated
	 * with '/'.
	 *
	 * @param Parser $parser Reference to the WM parser object
	 * @param string $page The template page root to load
	 * @param string $country The country to attempt to localize onto
	 * @param string $language The language to attempt to localize onto
	 *
	 * @return array The wikitext template
	 */
	public static function fundraiserLandingPageSwitchCountry( $parser, $page = '', $country = 'XX',
		$language = 'en'
	) {
		$page = self::fundraiserLandingPageMakeSafe( $page );
		$country = self::fundraiserLandingPageMakeSafe( $country, 'XX' );
		$language = self::fundraiserLandingPageMakeSafe( $language, 'en' );

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

		return [ "{{Template:$tpltext}}", 'noparse' => false ];
	}

	/**
	 * Check if template is fundraiseup
	 * @return bool
	 */
	private function isFundraiseUp() {
		return $this->getRequest()->getVal( 'fundraiseupScript' ) === "1";
	}

	/**
	 * Javascript to add fundraiseup to DonateWiki page
	 *
	 * @return string
	 */
	private function getFundraiseUpJavascript() {
		return <<<EOF
			<script>(function(w,d,s,n,a){if(!w[n]){var l='call,catch,on,once,set,then,track'
			.split(','),i,o=function(n){return'function'==typeof n?o.l.push([arguments])&&o
			:function(){return o.l.push([n,arguments])&&o}},t=d.getElementsByTagName(s)[0],
			j=d.createElement(s);j.async=!0;j.src='https://cdn.fundraiseup.com/widget/'+a;
			t.parentNode.insertBefore(j,t);o.s=Date.now();o.v=4;o.h=w.location.href;o.l=[];
			for(i=0;i<7;i++)o[l[i]]=o(l[i]);w[n]=o}
			})(window,document,'script','FundraiseUp','AVLMPSRU');
			</script>
EOF;
	}
}

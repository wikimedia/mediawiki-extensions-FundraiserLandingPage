<?php

namespace MediaWiki\Extension\FundraiserLandingPage\Specials;

/*
 * SpecialPage definition for FundraiserLandingPage.  Extending UnlistedSpecialPage
 * since this page does not need to listed in Special:SpecialPages.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\Title\Title;
use SkinFactory;

class FundraiserLandingPage extends UnlistedSpecialPage {
	public function __construct( private readonly SkinFactory $skinFactory ) {
		parent::__construct( 'FundraiserLandingPage' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$config = $this->getConfig();

		$outputPage = $this->getOutput();
		$request = $this->getRequest();
		$context = $this->getContext();
		if ( $this->getConfig()->get( 'FundraiserUseDonateSkin' ) ) {
			$context->setSkin(
				$this->skinFactory->makeSkin( 'donate' )
			);
		}

		// Set squid age
		$outputPage->setCdnMaxage( $config->get( 'FundraiserLandingPageMaxAge' ) );
		$this->setHeaders();

		// set the page title to something useful
		$titleMsg = $this->msg( 'donate_interface-make-your-donation' );
		if ( !is_callable( [ $outputPage, 'setPageTitleMsg' ] ) ) {
			// Backward compatibility with MW < 1.41
			$outputPage->setPageTitle( $titleMsg );
		} else {
			// MW >= 1.41
			$outputPage->setPageTitleMsg( $titleMsg );
		}

		// and add a <meta name="description"> tag to give search engines a useful blurb
		$outputPage->addMeta( 'description', $this->msg( 'fundraiserlandingpage-meta-description' ) );

		// Instruct browsers to pre-fetch the DNS for payments-wiki to speed up loading the next form
		$outputPage->addHeadItem(
			'payments-dns-prefetch',
			'<link rel="dns-prefetch" href="' . $this->getConfig()->get( 'FundraiserLandingPagePaymentsHost' ) . '" />'
		);

		// clear output variable to be safe
		$outputWikitext = '';

		$fundraiserLPDefaults = $config->get( 'FundraiserLPDefaults' );
		// begin generating the template call
		$template = self::fundraiserLandingPageMakeSafe(
			$request->getText( 'template', $fundraiserLPDefaults[ 'template' ] )
		);
		$outputWikitext .= "{{ $template\n";

		// get the required variables (except template and country) to use for the landing page
		$requiredParams = [
			'appeal',
			'appeal-template',
			'form-template',
			'form-countryspecific'
		];
		foreach ( $requiredParams as $requiredParam ) {
			$param = self::fundraiserLandingPageMakeSafe(
				$request->getText( $requiredParam, $fundraiserLPDefaults[$requiredParam] )
			);
			// Add them to the template call
			$outputWikitext .= "| $requiredParam = $param\n";
		}

		// get the country code
		$country = $request->getVal( 'country' );
		// If country still isn't set, set it to the default
		if ( !$country ) {
			$country = $fundraiserLPDefaults[ 'country' ];
		}
		$country = self::fundraiserLandingPageMakeSafe( $country );
		$outputWikitext .= "| country = $country\n";

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
				$outputWikitext .= "| $key = $val\n";
			}
		}

		// close the template call
		$outputWikitext .= "}}";

		// Hijack parser internals to workaround T156184.  This should be safe
		// since we've sanitized all params.

		$parserOptions = ParserOptions::newFromContext( $outputPage->getContext() );
		$parserOptions->setAllowUnsafeRawHtml( true );

		// FIXME: Generating the parsed output really shouldn't be our responsibility.
		$parsedContent = MediaWikiServices::getInstance()->getParserFactory()->getInstance()
			->parse(
				$outputWikitext,
				$this->getPageTitle(),
				$parserOptions,
				false,
				true,
				$outputPage->getRevisionId()
			);

		// print the output to the page
		$outputPage->addParserOutput( $parsedContent, $parserOptions, [] );
	}

	/**
	 * Mark the page as allowed for search engine indexing
	 * (default for SpecialPages is noindex)
	 *
	 * @return string
	 */
	protected function getRobotPolicy() {
		return 'index,nofollow';
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
}

/**
 * Send an EventLogging event for all pageviews
 */
( function () {
	var urlParams = ( new mw.Uri() ).query,
		sampleRateParamAsFloat = parseFloat( urlParams.fundraiserLandingPageELSampleRate ),
		random = Math.random(),
		sampleRate, eventData, elBaseUrl, elParams, elUrl,

		// Correspondences among possible URL query parameters and EL properties.
		// Object keys are EL properties, and values are arrays with possible URL
		// query parameters that may contain data to send with those EL properties.
		// Query parameters are checked in the order they appear.
		QUERY_EL_MAP = {
			language: [ 'uselang', 'language' ],
			country: [ 'country' ],
			utm_source: [ 'wmf_source', 'utm_source' ],
			utm_campaign: [ 'wmf_campaign', 'utm_campaign' ],
			utm_medium: [ 'wmf_medium', 'utm_medium' ],
			utm_key: [ 'wmf_key', 'utm_key' ],
			contact_id: [ 'contact_id' ],
			link_id: [ 'link_id' ],
			template: [ 'template' ],
			appeal: [ 'appeal' ],
			appeal_template: [ 'appeal_template', 'appeal-template' ],
			form_template: [ 'form_template', 'form-template' ],
			form_countryspecific: [ 'form_countryspecific', 'form-countryspecific' ]
		},

		// EventLogging schema name for logging landing page pageviews
		LANDING_PAGE_EVENT_LOGGING_SCHEMA = 'LandingPageImpression';

	// Allow the configured sample rate to be overridden by a URL parameter
	sampleRate = !isNaN( sampleRateParamAsFloat ) ?
		sampleRateParamAsFloat : mw.config.get( 'wgFundraiserLandingPageELSampleRate' );

	// Randomly select a proportion of pageviews as per sample rate
	// NOTE: Sampling feature here is mainly for testing purposes. In production, 100%
	// sample rate is expected.
	if ( random <= sampleRate ) {
		eventData = {
			landingpage: mw.config.get( 'wgPageName' ),
			sample_rate: sampleRate
		};

		$.each( QUERY_EL_MAP, function ( elPropName, urlParamsToTry ) {
			var i, urlParamToTry;

			for ( i = 0; i < urlParamsToTry.length; i++ ) {
				urlParamToTry = urlParamsToTry[ i ];

				if ( urlParamToTry in urlParams ) {
					eventData[ elPropName ] = urlParams[ urlParamToTry ];
					break;
				}
			}
		} );

		mw.eventLog.logEvent( LANDING_PAGE_EVENT_LOGGING_SCHEMA, eventData );
	}
}() );

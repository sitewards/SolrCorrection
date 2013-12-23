<?php
/**
 *
 * @category    Sitewards
 * @package     Sitewards_SolrCorrection
 * @copyright   Copyright (c) 2013 Sitewards GmbH (http://www.sitewards.com/)
 * @contact     magento@sitewards.com
 * @licence     OSL-3.0
 */
class Sitewards_SolrCorrection_Model_Observer {
	/**
	 * Simple Search interface
	 *
	 * @var string $sQuery The raw query string
	 * @var array $aParams Params can be specified like this:
	 *        'offset'      - The starting offset for result documents
	 *        'limit        - The maximum number of result documents to return
	 *        'sort_by'     - Sort field, can be just sort field name (and ascending order will be used by default),
	 *                        or can be an array of arrays like this: array('sort_field_name' => 'asc|desc')
	 *                        to define sort order and sorting fields.
	 *                        If sort order not asc|desc - ascending will used by default
	 *        'fields'      - Fields names which are need to be retrieved from found documents
	 *        'solr_params' - Key / value pairs for other query parameters (see Solr documentation),
	 *                        use arrays for parameter keys used more than once (e.g. facet.field)
	 *                        automatically sets spellcheck on true to accomplish automatic correction
	 *        'locale_code' - Locale code, it used to define what suffix is needed for text fields,
	 *                        by which will be performed search request and sorting
	 */
	public function autoCorrectSearch(Varien_Event_Observer $oObserver) {
		$oSearchContext = $oObserver->getEvent()->getData('search_context');
		$sQuery = $oObserver->getEvent()->getData('query');
		$aParams = $oObserver->getEvent()->getData('params');
		
		$aParams['solr_params']['spellcheck'] = 'true';
		
		// do not auto-correct if we are searching by sku (only numerical values and spaces)
		if (!is_string($sQuery) OR preg_match('/[0-9 ]+/', $sQuery)){
			$aParams['solr_params']['spellcheck'] = 'false';
		}
		
		$aParams['ignore_handler'] = true;
		$aResults = $oSearchContext->doParentSearch($sQuery, $aParams);
		if (isset($aResults['suggestions_data']) AND count($aResults['suggestions_data'])) {
			$paramsSuggestionData = $aParams;
			$params['solr_params']['spellcheck'] = 'false';
			foreach ($aResults['suggestions_data'] as $aSuggestionData) {
				$aSuggestionResult = $oSearchContext->doParentSearch($aSuggestionData['word'], $paramsSuggestionData);
				foreach ($aSuggestionResult['ids'] as $aId) {
					$aResults['ids'][] = $aId;
				}
				foreach ($aSuggestionResult['faceted_data'] as $sKeyFacet => $aFacets) {
					foreach ($aFacets as $sKeyValue => $sValue) {
						if ($sValue) {
							$aResults['faceted_data'][$sKeyFacet][$sKeyValue]  = $sValue;
						}
					}
				}
			}
		}
		$oSearchContext->setResults($aResults);
	}
}
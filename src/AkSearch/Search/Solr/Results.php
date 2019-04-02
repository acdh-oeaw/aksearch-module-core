<?php

namespace AkSearch\Search\Solr;


class Results extends \VuFind\Search\Solr\Results
{

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        var_dump('getFacetList in Results');
        // Make sure we have processed the search before proceeding:
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $list = [];

        // Loop through every field returned by the result set
        $fieldFacets = $this->responseFacets->getFieldFacets();
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        foreach (array_keys($filter) as $field) {
            $data = $fieldFacets[$field] ?? [];
            // Skip empty arrays:
            if (count($data) < 1) {
                continue;
            }
            // Initialize the settings for the current field
            $list[$field] = [];
            // Add the on-screen label
            $list[$field]['label'] = $filter[$field];
            // Build our array of values for this field
            $list[$field]['list']  = [];
            // Should we translate values for the current facet?
            if ($translate = in_array($field, $translatedFacets)) {
                $translateTextDomain = $this->getOptions()
                    ->getTextDomainForTranslatedFacet($field);
            }
            // Loop through values:
            foreach ($data as $value => $count) {
                // Initialize the array of data about the current facet:
                $currentSettings = [];
                $currentSettings['value'] = $value;

                $displayText = $this->getParams()
                    ->checkForDelimitedFacetDisplayText($field, $value);

                $currentSettings['displayText'] = $translate
                    ? $this->translate("$translateTextDomain::$displayText")
                    : $displayText;
                $currentSettings['count'] = $count;
                $currentSettings['operator']
                    = $this->getParams()->getFacetOperator($field);
                $currentSettings['isApplied']
                    = $this->getParams()->hasFilter("$field:" . $value)
                    || $this->getParams()->hasFilter("~$field:" . $value);

                // Store the collected values:
                $list[$field]['list'][] = $currentSettings;
            }
        }
        return $list;
    }

}

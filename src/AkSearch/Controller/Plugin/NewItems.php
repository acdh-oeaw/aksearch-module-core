<?php
namespace AkSearch\Controller\Plugin;

class NewItems extends \VuFind\Controller\Plugin\NewItems
{

    /**
     * Get a Solr filter to limit to the specified number of days.
     * AK: Added possibility to use a custom Solr date field for the filter.
     *
     * @param int $range Days to search
     *
     * @return string
     */
    public function getSolrFilter($range)
    {
        $solrFieldConf = $this->config['solrfield'];
        $solrField = (isset($solrFieldConf) && !empty($solrFieldConf))
                     ? $solrFieldConf
                     : 'first_indexed';

        return $solrField.':[NOW-' . $range . 'DAY TO NOW]';
    }

}

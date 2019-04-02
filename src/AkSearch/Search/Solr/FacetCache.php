<?php

namespace AkSearch\Search\Solr;

// IMPORTANT:
// The FacetCache plugins were introduced in VuFind 5.0; these provide a plugin
// mechanism for retrieving and caching lists of facet values (**** for use on home
// pages and advanced search forms ****). 
class FacetCache extends \VuFind\Search\Solr\FacetCache
{
    /**
     * Perform the actual facet lookup.
     *
     * @param string $initMethod Name of params method to use to request facets
     *
     * @return array
     */
    protected function getFacetResults($initMethod)
    {
        var_dump('getFacetResults in FacetCache');
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->cacheManager->getCache('object', $this->getCacheNamespace());
        $params = $this->results->getParams();

        // Note that we need to initialize the parameters BEFORE generating the
        // cache key to ensure that the key is based on the proper settings.
        $params->$initMethod();
        $cacheKey = $this->getCacheKey();
        if (!($list = $cache->getItem($cacheKey))) {
            var_dump('1 Get facet Results', $list);
            // Avoid a backend request if there are no facets configured by the given
            // init method.
            if (!empty($params->getFacetConfig())) {
                // We only care about facet lists, so don't get any results (this
                // improves performance):
                $params->setLimit(0);
                $list = $this->results->getFacetList();
                
            } else {
                $list = [];
            }
            $cache->setItem($cacheKey, $list);
        }
        
        return $list;
    }
}

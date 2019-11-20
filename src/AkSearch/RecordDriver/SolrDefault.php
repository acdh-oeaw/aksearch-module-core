<?php

namespace AkSearch\RecordDriver;


class SolrDefault extends \VuFind\RecordDriver\SolrDefault
{

    /**
     * Get a highlighted title string, if available.
     *
     * AK: Also highlight subtitle
     * 
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        var_dump($this->highlightDetails);
        $title = (isset($this->highlightDetails['title'][0]))
            ? $this->highlightDetails['title'][0] : '';
        
        // AK: Highlight subtitle
        $titleSub = (isset($this->highlightDetails['title_sub'][0]))
            ? $this->highlightDetails['title_sub'][0] : '';

        return $title.' : '.$titleSub;
    }

}

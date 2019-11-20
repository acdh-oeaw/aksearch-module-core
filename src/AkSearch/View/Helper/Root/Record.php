<?php

namespace AkSearch\View\Helper\Root;

class Record extends \VuFind\View\Helper\Root\Record
{

    /**
     * Get HTML to render a title.
     * 
     * AK: Added subtitle and title section, separated by colon from each other
     *
     * @param int $maxLength Maximum length of non-highlighted title.
     *
     * @return string
     */
    public function getTitleHtml($maxLength = 180)
    {
        $highlightedTitle = $this->driver->tryMethod('getHighlightedTitle');
        // AK: Add subtitle and title section, separated by colon from each other
        $title = 'TEST '.trim($this->driver->tryMethod('getTitle'))
            .' : '.trim($this->driver->tryMethod('getSubtitle'))
            .' : '.trim($this->driver->getTitleSection());

        if (!empty($highlightedTitle)) {
            $highlight = $this->getView()->plugin('highlight');
            $addEllipsis = $this->getView()->plugin('addEllipsis');
            return $highlight($addEllipsis($highlightedTitle, $title));
        }
        if (!empty($title)) {
            $escapeHtml = $this->getView()->plugin('escapeHtml');
            $truncate = $this->getView()->plugin('truncate');
            return $escapeHtml($truncate($title, $maxLength));
        }
        $transEsc = $this->getView()->plugin('transEsc');
        return $transEsc('Title not available');
    }

}

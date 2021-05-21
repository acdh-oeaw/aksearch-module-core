<?php
/**
 * AK: Extended translate view helper
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2021.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
namespace AkSearch\View\Helper\Root;

/**
 * AK: Extending translate view helper
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
class Translate extends \VuFind\View\Helper\Root\Translate
{

    /**
     * Translate a string.
     * 
     * AK: Pass a locale (e. g. 'en' or 'de') to the translator.
     *
     * @param string|object $str     String to translate
     * @param array         $tokens  Tokens to inject into the translated string
     * @param string        $default Default value to use if no translation is found
     * (null for no default).
     * @param string        $locale  The language to use, e. g. 'en' or 'de'
     *
     * @return string
     */
    public function __invoke($str, $tokens = [], $default = null, $locale = 'en')
    {
        return $this->translate($str, $tokens, $default, $locale);
    }

    /**
     * Translate a string (or string-castable object)
     * 
     * AK: Override "translate" from \VuFind\I18n\Translator\TranslatorAwareTrait.
     * This is for passing a locale (e. g. 'en' or 'de') to the translator.
     *
     * @param string|object|array $target  String to translate or an array of text
     * domain and string to translate
     * @param array               $tokens  Tokens to inject into the translated
     * string
     * @param string              $default Default value to use if no translation is
     * found (null for no default).
     * @param string              $locale  The language to use, e. g. 'en' or 'de'
     * 
     * @return string
     */
    public function translate($target, $tokens = [], $default = null, $locale = 'en')
    {
        // Figure out the text domain for the string:
        list($domain, $str) = $this->extractTextDomain($target);

        // Special case: deal with objects with a designated display value:
        if ($str instanceof \VuFind\I18n\TranslatableStringInterface) {
            if (!$str->isTranslatable()) {
                return $str->getDisplayString();
            }
            // On this pass, don't use the $default, since we want to fail over
            // to getDisplayString before giving up.
            // AK: Pass locale to translator
            $translated = $this
                ->translateString((string)$str, $tokens, null, $domain, $locale);
            if ($translated !== (string)$str) {
                return $translated;
            }
            // Override $domain/$str using getDisplayString() before proceeding:
            $str = $str->getDisplayString();
            // Also the display string can be a TranslatableString. This makes it
            // possible have multiple levels of translatable values while still
            // providing a sane default string if translation is not found. Used at
            // least with hierarchical facets where translation key can be the exact
            // facet value (e.g. "0/Book/") or a displayable value (e.g. "Book").
            if ($str instanceof \VuFind\I18n\TranslatableStringInterface) {
                return $this->translate($str, $tokens, $default);
            } else {
                list($domain, $str) = $this->extractTextDomain($str);
            }
        }

        // Default case: deal with ordinary strings (or string-castable objects).
        // AK: Pass locale to translator
        return $this->translateString((string)$str, $tokens, $default, $domain,
            $locale);
    }

    /**
     * Get translation for a string.
     * 
     * AK: Pass locale (e. g. 'en' or 'de') to translator.
     *
     * @param string $str     String to translate
     * @param array  $tokens  Tokens to inject into the translated string
     * @param string $default Default value to use if no translation is found
     * (null for no default).
     * @param string $domain  Text domain (omit for default)
     * @param string $locale  The language to use, e. g. 'en' or 'de'
     *
     * @return string
     */
    protected function translateString($str, $tokens = [], $default = null,
        $domain = 'default', $locale = 'en'
    ) {
        // AK: Pass locale to translator
        $msg = (null === $this->translator)
            ? $str : $this->translator->translate($str, $domain, $locale);

        // Did the translation fail to change anything?  If so, use default:
        if (null !== $default && $msg == $str) {
            $msg = $default instanceof \VuFind\I18n\TranslatableStringInterface
                ? $default->getDisplayString() : $default;
        }

        // Do we need to perform substitutions?
        if (!empty($tokens)) {
            $in = $out = [];
            foreach ($tokens as $key => $value) {
                $in[] = $key;
                $out[] = $value;
            }
            $msg = str_replace($in, $out, $msg);
        }

        return $msg;
    }
}

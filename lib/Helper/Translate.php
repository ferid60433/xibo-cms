<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (TranslationEngine.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Helper;

use CachedFileReader;
use Gettext\Translations;
use Gettext\Translator;
use gettext_reader;

class Translate
{
    private static $requestedLanguage;
    private static $locale;
    private static $jsLocale;

    /**
     * Gets and Sets the Locale
     * @param $language string[optional] The Language to Load
     */
    public static function InitLocale($language = NULL)
    {
        $localeDir = PROJECT_ROOT . '/locale';
        $default = ($language == NULL) ? Config::GetSetting('DEFAULT_LANGUAGE') : $language;

        // Build an array of supported languages
        $supportedLanguages = array_map('basename', glob($localeDir . '/*.mo'));

        // Try to get the local firstly from _REQUEST (post then get)
        $requestedLanguage = Sanitize::getString('lang', $language);
        $foundLanguage = '';

        if ($requestedLanguage != '') {
            // Serve only the requested language
            // Firstly, Sanitize it
            $requestedLanguage = str_replace('-', '_', $requestedLanguage);

            // Check its valid
            if (in_array($requestedLanguage . '.mo', $supportedLanguages)) {
                $foundLanguage = $requestedLanguage;
            }
        }
        else if (Config::GetSetting('DETECT_LANGUAGE') == 1) {
            // Detect the language, try from HTTP accept
            // Parse the language header and build a preference array
            $languagePreferenceArray = Translate::parseHttpAcceptLanguageHeader();

            if (count($languagePreferenceArray) > 0) {

                // Go through the list until we have a match
                foreach ($languagePreferenceArray as $languagePreference => $preferenceRating) {

                    // We don't ship an en.mo, so fudge in a case where we automatically convert that to en_GB
                    if ($languagePreference == 'en')
                        $languagePreference = 'en_GB';

                    // Sanitize
                    $languagePreference = str_replace('-', '_', $languagePreference);

                    // Check it is valid
                    if (in_array($languagePreference . '.mo', $supportedLanguages)) {
                        $foundLanguage = $languagePreference;
                        break;
                    }
                }
            }
        }

        // Requested language
        self::$requestedLanguage = ($foundLanguage == '') ? $default : $foundLanguage;

        // Are we still empty, then default language from settings
        if ($foundLanguage == '') {
            // Check the default
            if (!in_array($default . '.mo', $supportedLanguages)) {
                Log::Info('Resolved language [%s] not available, checked in %s and found %s.', $default, $localeDir, json_encode($supportedLanguages, JSON_PRETTY_PRINT));

                $default = 'en_GB';
            }

            // The default is valid
            $foundLanguage = $default;
        }

        // Load translations
        $translator = new Translator();
        $translator->loadTranslations(Translations::fromMoFile($localeDir . '/' . $foundLanguage . '.mo'));
        Translator::initGettextFunctions($translator);

        // Store our resolved language locales
        self::$locale = $foundLanguage;
        self::$jsLocale = str_replace('_', '-', $foundLanguage);
    }

    /**
     * Get the Locale
     * @param null $characters The number of characters to take from the beginning of the local string
     * @return mixed
     */
    public static function GetLocale($characters = null)
    {
        return ($characters == null) ? self::$locale : substr(self::$locale, 0, $characters);
    }

    public static function GetJsLocale()
    {
        return self::$jsLocale;
    }

    public static function getRequestedLanguage()
    {
        return self::$requestedLanguage;
    }

    /**
     * Parse the HttpAcceptLanguage Header
     * Inspired by: http://www.thefutureoftheweb.com/blog/use-accept-language-header
     * @param null $header
     * @return array Language array where the key is the language identifier and the value is the preference double.
     */
    public static function parseHttpAcceptLanguageHeader($header = null)
    {
        if ($header == null)
            $header = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';

        $languages = array();

        if ($header != '') {
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $header, $langParse);

            if (count($langParse[1])) {
                // create a list like "en" => 0.8
                $languages = array_combine($langParse[1], $langParse[4]);

                // set default to 1 for any without q factor
                foreach ($languages as $lang => $val) {
                    if ($val === '')
                        $languages[$lang] = 1;
                }

                // sort list based on value
                arsort($languages, SORT_NUMERIC);
            }
        }

        return $languages;
    }
}
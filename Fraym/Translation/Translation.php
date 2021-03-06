<?php
/**
 * @link      http://fraym.org
 * @author    Dominik Weber <info@fraym.org>
 * @copyright Dominik Weber <info@fraym.org>
 * @license   http://www.opensource.org/licenses/gpl-license.php GNU General Public License, version 2 or later (see the LICENSE file)
 */
namespace Fraym\Translation;

/**
 * Class Translation
 * @package Fraym\Translation
 * @Injectable(lazy=true)
 */
class Translation
{
    private $runtimeTranslationKeys = array();
    private $autoTranslationServiceUrl = 'http://translate.google.com/translate_a/t?client=t&hl=%s&tl=%s&sl=%s&multires=1&otf=2&pc=1&ssel=0&tsel=0&sc=1&text=';

    /**
     * @Inject
     * @var \Fraym\Locale\Locale
     */
    protected $locale;

    /**
     * @Inject
     * @var \Fraym\Database\Database
     */
    protected $db;

    /**
     * @Inject
     * @var \Fraym\Registry\Config
     */
    protected $config;

    /**
     * @param $default
     * @param string $defaultLocale
     * @param null $key
     * @param array $placeholder
     * @return null|string
     */
    public function getTranslation($default, $key = null, $defaultLocale = 'en_US', $placeholder = array())
    {
        // convert objects call __toString
        $default = (string)$default;

        // handle NULL and empty values
        $defaultLocale = empty($defaultLocale) ? 'en_US' : $defaultLocale;
        $key = empty($key) ? $default : $key;

        $keyLower = strtolower($key);
        $em = $this->db;
        $translation = null;
        $translationString = '';

        $locale = $this->locale->getLocale() ? : $em->getRepository('\Fraym\Locale\Entity\Locale')->findOneById(
            $this->config->get('ADMIN_LOCALE_ID')->value
        );

        if (isset($this->runtimeTranslationKeys[$keyLower])) {
            $translationString = $this->runtimeTranslationKeys[$keyLower];
        } elseif ($locale) {
            if ($defaultLocale !== $locale->locale) {
                // Get the translation entity
                $translation = $this->getTranslationEntity($key, $locale->locale, $default, $defaultLocale);
                $translationString = $translation->value;
            } else {
                $translationString = (empty($default) ? $key : $default);
            }

            $translationString = $this->runtimeTranslationKeys[$keyLower] = str_replace(
                array_keys($placeholder),
                array_values($placeholder),
                $translationString
            );
        }

        return $translationString;
    }

    /**
     * @param $translation
     * @param $defaultValue
     * @param $locale
     * @param $defaultLocale
     * @return mixed
     */
    private function updateTranslationLocales($translation, $defaultValue, $locale, $defaultLocale)
    {
        $em = $this->db;

        if ($locale != $defaultLocale && $this->config->get('TRANSLATION_AUTO')->value == '1') {
            $translationValue = $this->getTranslatedValue($translation->key, $locale, $defaultLocale);
        }

        $translationValue = empty($translationValue) ? $defaultValue : $translationValue;

        $repository = $em->getRepository('Gedmo\\Translatable\\Entity\\Translation');
        $repository->translate($translation, 'value', $locale, $translationValue);
        $em->persist($translation);
        $em->flush();

        $translation->locale = $locale;
        $em->refresh($translation);
        $em->clear();

        return $translation;
    }

    /**
     * @param $key
     * @param $value
     * @param $locale
     * @param string $defaultLocale
     * @return Entity\Translation
     */
    public function createTranslation($key, $value, $locale, $defaultLocale = 'en_US')
    {
        $em = $this->db;

        $translation = $this->db->getRepository('\Fraym\Translation\Entity\Translation')->findOneByKey($key);

        if ($translation === null) {
            $translation = new \Fraym\Translation\Entity\Translation;
            $translation->key = $key;
            $translation->value = $value;
            $translation->locale = $defaultLocale;
        }

        if ($locale != $defaultLocale && $this->config->get('TRANSLATION_AUTO')->value == '1') {
            $value = $this->autoTranslation($value, $defaultLocale, $locale);
            $repository = $em->getRepository('Gedmo\\Translatable\\Entity\\Translation');
            $repository->translate($translation, 'value', $locale, $value);
        }

        $em->persist($translation);
        $em->flush();

        return $translation;
    }

    /**
     * @param $key
     * @param $locale
     * @param string $default
     * @param string $defaultLocale
     * @return Entity\Translation|mixed|object
     */
    private function getTranslationEntity($key, $locale, $default = '', $defaultLocale = 'en_US')
    {
        $translationString = (empty($default) ? $key : $default);

        $translation = $this->db->getRepository('\Fraym\Translation\Entity\Translation')->findOneByKey($key);

        if (null === $translation) {
            return $this->createTranslation($key, $translationString, $locale, $defaultLocale);
        }

        $repository = $this->db->getRepository('Gedmo\Translatable\Entity\Translation');
        $translations = $repository->findTranslations($translation);

        if (isset($translations[$locale])) {
            return (object)$translations[$locale];
        } elseif ($this->locale->getLocale()->locale == $locale) {

            $translation->locale = $locale;
            $this->db->refresh($translation);
            $this->db->clear();
            return $translation;
        } else {
            return $this->updateTranslationLocales($translation, $translationString, $locale, $defaultLocale);
        }

        return (object)$translations[$locale];
    }


    /**
     * @param $key
     * @param $locale
     * @param $defaultLocale
     * @return string
     */
    private function getTranslatedValue($key, $locale, $defaultLocale)
    {
        $em = $this->db;
        $translation = $em->getRepository('\Fraym\Translation\Entity\Translation')->findOneByKey($key);

        if ($translation) {
            $em->refresh($translation);
            $translated = $this->autoTranslation($translation->value, $defaultLocale, $locale);

            return $translated;
        }
        return '';
    }

    /**
     * @param $str
     * @param string $fromLocale
     * @param string $toLocale
     * @return string
     */
    public function autoTranslation($str, $fromLocale = 'en', $toLocale = 'de')
    {
        if (strpos($fromLocale, '_') !== false) {
            $fromLocale = explode('_', $fromLocale);
            $fromLocale = reset($fromLocale);
        }
        if (strpos($toLocale, '_') !== false) {
            $toLocale = explode('_', $toLocale);
            $toLocale = reset($toLocale);
        }
        $word = urlencode($str);
        $url = sprintf($this->autoTranslationServiceUrl, $toLocale, $toLocale, $fromLocale) . $word;
        $data = @file_get_contents($url);

        $data = explode('"', $data);

        $translatedString = isset($data[1]) ? mb_convert_encoding($data[1], 'utf-8') : $str;
        $translatedString = str_ireplace(array(' .', ' ,', ' !', ' -'), array('.', ',', '!', '-'), $translatedString);
        return $this->unescapeUTF8EscapeSeq($translatedString);
    }

    /**
     * @param $str
     * @return mixed
     */
    function unescapeUTF8EscapeSeq($str) {
        return preg_replace_callback("/\\\u([0-9a-f]{4})/i",
            create_function('$matches',
                'return html_entity_decode(\'&#x\'.$matches[1].\';\', ENT_QUOTES, \'UTF-8\');'
            ), $str);
    }
}

<?php

namespace QF;

class I18n
{

    /**
     * @var \QF\Translation[]
     */
    protected $translations = null;
    protected $languages = array();
    protected $currentLanguage = null;
    protected $defaultLanguage = null;
    protected $translationDirectories = array();
    protected $data = array();
    protected $dataLoaded = false;

    /**
     * initializes the translation class
     *
     * @param string $translationDirectories array of (path => namespace) pairs
     * @param string $moduleDir the path to the modules
     * @param string $languages the available languages
     * @param string $defaultLanguage default languagew
     */
    function __construct($translationDirectories, $languages, $defaultLanguage)
    {
        $this->languages = $languages;
        $this->translationDirectories = $translationDirectories;
        $this->defaultLanguage = $defaultLanguage;
        $this->currentLanguage = $this->defaultLanguage;
    }

    public function getLanguages()
    {
        return $this->languages;
    }

    public function getCurrentLanguage()
    {
        return $this->currentLanguage;
    }

    public function getDefaultLanguage()
    {
        return $this->defaultLanguage;
    }

    public function setLanguages($languages)
    {
        $this->languages = $languages;
    }

    public function setCurrentLanguage($currentLanguage)
    {
        if ($currentLanguage == $this->currentLanguage) {
            return;
        }
        $this->currentLanguage = $currentLanguage;

        $this->dataLoaded = false;
    }

    public function setDefaultLanguage($defaultLanguage)
    {
        $this->defaultLanguage = $defaultLanguage;
    }

    /**
     *
     * @return \QF\Translation
     */
    public function get($ns = 'default')
    {
        if (!$this->dataLoaded) {
            $this->loadTranslationData();
        }
        if (empty($this->translations[$ns])) {
            $this->translations[$ns] = new Translation(!empty($this->data[$ns]) ? $this->data[$ns] : array());
        }
        return $this->translations[$ns];
    }

    /**
     *
     * @param string|null $key if a key is given, return only the specific date instead of an array with all data
     * @return mixed
     */
    public function getL10nData($key = null)
    {
        $t = $this->get('_l10n');
        return $key === null ? $t : (isset($t[$key]) ? $t[$key] : null);
    }

    public function formatNumber($number, $decimals = 0, $decimalSeparator = null, $thousandsSeparator = null)
    {
        return number_format(
                $number, $decimals, $decimalSeparator ? $decimalSeparator : $this->getL10nData('numberDecimalSeparator'), $thousandsSeparator ? $thousandsSeparator : $this->getL10nData('numberThousandsStep')
        );
    }

    public function formatDate($timestamp = null)
    {
        return date($this->getL10nData('formatDate'), $timestamp !== null ? (int) $timestamp : time());
    }

    public function formatTime($timestamp = null, $showSeconds = false)
    {
        return date($this->getL10nData($showSeconds ? 'formatTimeSeconds' : 'formatTime'), $timestamp !== null ? (int) $timestamp : time());
    }

    public function formatDateTime($timestamp = null, $showSeconds = false)
    {
        return date($this->getL10nData($showSeconds ? 'formatDateTimeSeconds' : 'formatDateTime'), $timestamp !== null ? (int) $timestamp : time());
    }

    public function formatMonth($month = null, $full = true)
    {
        $months = $this->getL10nData('months');
        if ($month === null || $month > 12 || $month < 1 || !isset($months[$month])) {
            $month = date('n', $month ? $month : time());
        }
        return $full ? $months[$month] : substr($months[$month], 0, 3);
    }

    public function formatDayOfWeek($day = null, $full = true)
    {
        $weekdays = $this->getL10nData('weekdays');
        if ($day == 7) {
            $day = 0;
        }
        if ($day === null || $day > 6 || $day < 0 || !isset($weekdays[$day])) {
            $day = date('w', $day ? $day : time());
        }
        return $full ? $weekdays[$day] : substr($weekdays[$day], 0, 3);
    }

    public function formatCustom($key = null, $parameter = null)
    {
        $format = $this->getL10nData('format' . ucfirst($key));
        if (is_array($format) && isset($format['callable'])) {
            return call_user_func_array($format['callable'], array_merge(isset($format['parameter']) ? (array) $format['parameter'] : array(), (array) $parameter));
        }
        return date((string) $format, $parameter !== null ? (int) $parameter : time());
    }

    protected function loadTranslationData()
    {
        $this->translations = array();
        $this->data = array();

        foreach ($this->translationDirectories as $dir) {
            $dir = rtrim($dir, '/');
            $i18n = &$this->data;
            if (file_exists($dir . '/' . $this->currentLanguage . '.php')) {
                include($dir . '/' . $this->currentLanguage . '.php');
            }
        }

        $this->dataLoaded = true;
    }

}
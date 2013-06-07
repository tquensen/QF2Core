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
    
    protected function loadTranslationData()
    {
        $this->translations = array();
        $this->data = array();
        
        foreach ($this->translationDirectories as $dir) {
            $dir = rtrim($dir, '/');
            $i18n = &$this->data;
            if (file_exists($dir . '/' .$this->currentLanguage . '.php')) {
                include($dir . '/' .$this->currentLanguage . '.php');
            }
        }
        
        $this->dataLoaded = true;
    }
}
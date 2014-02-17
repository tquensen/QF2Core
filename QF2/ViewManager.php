<?php
namespace QF;

use \QF\Exception\HttpException;

class ViewManager
{

    protected $container = null;
    protected $i18n = null;
    
    protected $parameter = array();
    
    protected $theme = null;
    protected $format = null;
    protected $defaultFormat = null;
    protected $template = null;
    
    protected $baseUrl = null;
    protected $baseUrlI18n = null;
    protected $staticUrl = null;
    
    protected $templatePath = null;
    protected $webPath = null;

    protected $modules = array();

    public function __construct()
    {       
        $this->templatePath = __DIR__.'../../templates';
        $this->webPath = __DIR__.'../../web';
    }
    
    public function __get($key)
    {
        return !empty($this->parameter[$key]) ? $this->parameter[$key] : null;
    }
    
    public function __set($key, $value)
    {
        $this->parameter[$key] = $value;
    }
    
    /**
     * builds an url to a public file (js, css, images, ...)
     *
     * @param string $file path to the file (relative from {templatepath}/{currenttheme or default}/public/ or modulepath/{module}/public/)
     * @param string $module the module containing the file
     * @param bool $cacheBuster add the last modified time as parameter to the url to prevent caching if ressource has changed
     * @return string returns the url to the file including base_url (if available)
     */
    public function getAsset($file, $module = null, $cacheBuster = false)
    {
        $theme = $this->theme;
        $themeString = $theme ? $theme . '/' : '';
        
        $modulepath = $module && isset($this->modules[$module]) ? $this->modules[$module] : false;
        
        if (!$baseurl = $this->staticUrl) {
            $baseurl = $this->baseUrl ?: '/';
        }
        $viewHash = md5($theme.$file.$modulepath.$cacheBuster);
        if (isset($this->viewCache[$viewHash])) {
            $_file = $this->viewCache[$viewHash];
        } else {
            if ($module && $modulepath) {
                if ($theme && file_exists($this->templatePath . '/' . $themeString . 'public/modules/'.$module.'/'.$file)) {
                    $_file =  $baseurl . 'templates/' . $themeString . 'modules/'.$module.'/'.$file . ($cacheBuster ? '?'. filemtime($this->templatePath . '/' . $themeString . 'public/modules/'.$module.'/'.$file) : '');
                } elseif (file_exists($this->templatePath . '/default/public/modules/'.$module.'/'.$file)) {
                    $_file =  $baseurl . 'templates/modules/'.$module.'/'.$file . ($cacheBuster ? '?'. filemtime($this->templatePath . '/default/public/modules/'.$module.'/'.$file) : '');
                } elseif (file_exists($modulepath.'/public/'.$file)) {
                    $_file =  $baseurl . 'modules/'.$module.'/'.$file . ($cacheBuster ? '?'. filemtime($this->modulePath . '/'.$module.'/public/'.$file) : '');
                } else {
                    $_file =  $baseurl . 'modules/'.$module.'/'.$file;
                }
            } else {
                if ($theme && file_exists($this->templatePath . '/' . $themeString . 'public/'.$file)) {
                    $_file =  $baseurl . 'templates/' . $themeString . $file . ($cacheBuster ? '?'. filemtime($this->templatePath . '/' . $themeString . 'public/'.$file) : '');
                } elseif (file_exists($this->templatePath . '/default/public/'.$file)) {
                    $_file =  $baseurl . 'templates/default/'.$file . ($cacheBuster ? '?'. filemtime($this->templatePath . '/default/public/'.$file) : '');
                } else {
                    $_file =  $baseurl . $file;
                }
            }
            $this->viewCache[$viewHash] = $_file;
        }
        return $_file;
    }
    
    /**
     * parses the given page and returns the output
     *
     * inside the page, you have direct access to any given parameter
     *
     * @param string $module the module containing the page
     * @param string $view the name of the view file
     * @param array $parameter parameters for the page
     * @return string the parsed output of the page
     */
    public function parse($module, $view, $parameter = array())
    {
        $_theme = $this->theme;
        $_themeString = $_theme ? $_theme . '/' : '';
        $_format = isset($parameter['_format']) ? $parameter['_format'] : $this->format;
        $_formatString = $_format ? '.' . $_format : '';
        $_lang = !empty($this->i18n) ? $this->getI18n()->getCurrentLanguage() : false;
        
        $_modulepath = $module && isset($this->modules[$module]) ? $this->modules[$module] : false;

        $viewHash = md5($_theme.$_lang.$_format.$_modulepath.$view);
        if (isset($this->viewCache[$viewHash])) {
            $_file = $this->viewCache[$viewHash];
        } else {
            if ($_lang && $_theme && file_exists($this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $_lang . '/' . $view . $_formatString . '.php')) {
                $_file = $this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $_lang . '/' . $view . $_formatString . '.php';
            } elseif ($_lang && $_theme && !$_format && file_exists($this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $_lang . '/' . $view . '.' . $this->defaultFormat . '.php')) {
                $_file = $this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $_lang . '/' . $view . '.' . $this->defaultFormat . '.php';
            } elseif ($_lang && file_exists($this->templatePath . '/default/modules/' . $module . '/' . $_lang . '/' . $view . $_formatString . '.php')) {
                $_file = $this->templatePath . '/default/modules/' . $module . '/' . $_lang . '/' . $view . $_formatString . '.php';
            } elseif ($_lang && !$_format && file_exists($this->templatePath . '/default/modules/' . $module . '/' . $_lang . '/' . $view . '.' . $this->defaultFormat . '.php')) {
                $_file = $this->templatePath . '/default/modules/' . $module . '/' . $_lang . '/' . $view . '.' . $this->defaultFormat . '.php';
            } elseif ($_lang && file_exists($_modulepath . '/views/' . $_lang . '/' . $view . $_formatString . '.php')) {
                $_file = $_modulepath . '/views/' . $_lang . '/' . $view . $_formatString . '.php';
            } elseif ($_lang && !$_format && file_exists($_modulepath . '/views/' . $_lang . '/' . $view . '.' . $this->defaultFormat . '.php')) {
                $_file = $_modulepath . '/views/' . $_lang . '/' . $view . '.' . $this->defaultFormat . '.php';
            } elseif ($_theme && file_exists($this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $view . $_formatString . '.php')) {
                $_file = $this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $view . $_formatString . '.php';
            } elseif ($_theme && !$_format && file_exists($this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $view . '.' . $this->defaultFormat . '.php')) {
                $_file = $this->templatePath . '/' .$_themeString. 'modules/' . $module . '/' . $view . '.' . $this->defaultFormat . '.php';
            } elseif (file_exists($this->templatePath . '/default/modules/' . $module . '/' . $view . $_formatString . '.php')) {
                $_file = $this->templatePath . '/default/modules/' . $module . '/' . $view . $_formatString . '.php';
            } elseif (!$_format && file_exists($this->templatePath . '/default/modules/' . $module . '/' . $view . '.' . $this->defaultFormat . '.php')) {
                $_file = $this->templatePath . '/default/modules/' . $module . '/' . $view . '.' . $this->defaultFormat . '.php';
            } elseif (file_exists($_modulepath . '/views/' . $view . $_formatString . '.php')) {
                $_file = $_modulepath . '/views/' . $view . $_formatString . '.php';
            } elseif (!$_format && file_exists($_modulepath . '/views/' . $view . '.' . $this->defaultFormat . '.php')) {
                $_file = $_modulepath . '/views/' . $view . '.' . $this->defaultFormat . '.php';
            } else {
                throw new HttpException('view not found', 404);
            }
            $this->viewCache[$viewHash] = $_file;
        }

        extract($parameter, \EXTR_OVERWRITE);
        $view = $this;
        $c = $this->getContainer();
        $qf = $c['core'];
        ob_start();
        try {
            require($_file);
        } Catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    /**
     * parses the template with the given content
     *
     * inside the template, you have direct access to the page content $content
     *
     * @param string $content the parsed output of the current page
     * @param array $parameter parameters for the page
     * @return string the output of the template
     */
    public function parseTemplate($content, $parameter = array())
    {
        $_templateName = $this->template ?: 'default';
        $_theme = $this->theme;
        $_themeString = $_theme ? $_theme . '/' : '';
        $_format = $this->format;
        $_defaultFormat = $this->defaultFormat;
        $_lang = !empty($this->i18n) ? $this->getI18n()->getCurrentLanguage() : false;
        
        $_file = false;

        if (is_array($_templateName)) {
            if ($_format) {
                $_templateName = isset($_templateName[$_format]) ? $_templateName[$_format] : (isset($_templateName['all']) ? $_templateName['all'] : null);
            } else {
                if (isset($_templateName['default'])) {
                    $_templateName = $_templateName['default'];
                } else {
                    $_templateName = isset($_templateName[$_defaultFormat]) ? $_templateName[$_defaultFormat] : (isset($_templateName['all']) ? $_templateName['all'] : null);
                }
            }
        }

        if ($this->template === false) {
            return $content;
        }

        $viewHash = md5($_theme.$_lang.$_format.$_templateName);
        if (isset($this->viewCache[$viewHash])) {
            $_file = $this->viewCache[$viewHash];
        } else {
            if ($_format) {
                if ($_lang && $_theme && $_templateName && file_exists($this->templatePath . '/' . $_themeString . $_lang . '/' . $_templateName . '.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/' . $_themeString . $_lang . '/' . $_templateName . '.' . $_format . '.php';
                } elseif ($_lang && $_templateName && file_exists($this->templatePath . '/default/' . $_lang . '/' . $_templateName . '.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_lang . '/' . $_templateName . '.' . $_format . '.php';
                } elseif ($_lang && $_theme && file_exists($this->templatePath . '/' . $_themeString . $_lang . '/' . 'default.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/'. $_themeString . $_lang . '/' . 'default.' . $_format . '.php';
                } elseif ($_lang && file_exists($this->templatePath . '/default/' . $_lang . '/default.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_lang . '/default.' . $_format . '.php';
                } else if ($_theme && $_templateName && file_exists($this->templatePath . '/' . $_themeString . $_templateName . '.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/' . $_themeString . $_templateName . '.' . $_format . '.php';
                } elseif ($_templateName && file_exists($this->templatePath . '/default/' . $_templateName . '.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_templateName . '.' . $_format . '.php';
                } elseif ($_theme && file_exists($this->templatePath . '/' . $_themeString . 'default.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/'. $_themeString . 'default.' . $_format . '.php';
                } elseif (file_exists($this->templatePath . '/default/default.' . $_format . '.php')) {
                    $_file = $this->templatePath . '/default/default.' . $_format . '.php';
                }
            } else { 
                if ($_lang && $_theme && file_exists($this->templatePath . '/' . $_themeString . $_lang . '/' . $_templateName . '.php')) {
                    $_file = $this->templatePath . '/' . $_themeString . $_lang . '/' . $_templateName . '.php';
                } elseif ($_lang && file_exists($this->templatePath . '/default/' . $_lang . '/' . $_templateName . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_lang . '/' . $_templateName . '.php';
                } elseif ($_lang && $_theme && file_exists($this->templatePath . '/' . $_themeString . $_lang . '/' . $_templateName . '.' . $_defaultFormat . '.php')) {
                    $_file = $this->templatePath . '/' . $_themeString . $_lang . '/' . $_templateName . '.' . $_defaultFormat . '.php';
                } elseif ($_lang && file_exists($this->templatePath . '/default/' . $_lang . '/' . $_templateName . '.' . $_defaultFormat . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_lang . '/' . $_templateName . '.' . $_defaultFormat . '.php';
                } elseif ($_theme && file_exists($this->templatePath . '/' . $_themeString . $_templateName . '.php')) {
                    $_file = $this->templatePath . '/' . $_themeString . $_templateName . '.php';
                } elseif (file_exists($this->templatePath . '/default/' . $_templateName . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_templateName . '.php';
                } elseif ($_theme && file_exists($this->templatePath . '/' . $_themeString . $_templateName . '.' . $_defaultFormat . '.php')) {
                    $_file = $this->templatePath . '/' . $_themeString . $_templateName . '.' . $_defaultFormat . '.php';
                } elseif (file_exists($this->templatePath . '/default/' . $_templateName . '.' . $_defaultFormat . '.php')) {
                    $_file = $this->templatePath . '/default/' . $_templateName . '.' . $_defaultFormat . '.php';
                }
            }
            $this->viewCache[$viewHash] = $_file;
        }

        if (!$_file) {
            throw new HttpException('template not found', 404);
        }

        extract($this->parameter, \EXTR_OVERWRITE);
        extract($parameter, \EXTR_OVERWRITE);
        $view = $this;
        $c = $this->getContainer();
        $qf = $c['core'];
        ob_start();
        try {
            require($_file);
        } Catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }
    
    public function getContainer()
    {
        return $this->container;
    }
    
    public function getI18n()
    {
        return $this->i18n;
    }
     
    public function getParameter()
    {
        return $this->parameter;
    }

    public function getTheme()
    {
        return $this->theme;
    }
    
    public function getFormat()
    {
        return $this->format;
    }
    
    public function getDefaultFormat()
    {
        return $this->defaultFormat;
    }
    
    public function getTemplate()
    {
        return $this->template;
    }
    
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }
    
    public function getBaseUrlI18n()
    {
        return $this->baseUrlI18n;
    }
    
    public function getStaticUrl()
    {
        return $this->staticUrl;
    }
    
    public function getTemplatePath()
    {
        return $this->templatePath;
    }

    public function getModules()
    {
        return $this->modules;
    }
    
    public function getWebPath()
    {
        return $this->webPath;
    }
    
    public function setContainer($container)
    {
        $this->container = $container;
    }
    
    public function setI18n($i18n)
    {
        $this->i18n = $i18n;
    }

    public function setParameter($parameter)
    {
        $this->parameter = $parameter;
    }

    public function setTheme($theme)
    {
        $this->theme = $theme;
    }
    
    public function setFormat($format)
    {
        $this->format = $format;
    }
    
    public function setDefaultFormat($defaultFormat)
    {
        $this->defaultFormat = $defaultFormat;
    }
    
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }
    
    public function setBaseUrlI18n($baseUrlI18n)
    {
        $this->baseUrlI18n = $baseUrlI18n;
    }
    
    public function setStaticUrl($staticUrl)
    {
        $this->staticUrl = $staticUrl;
    }
    
    public function setTemplatePath($templatePath)
    {
        $this->templatePath = $templatePath;
    }

    public function setModules($modules)
    {
        $this->modules = $modules;
    }
    
    public function setWebPath($webPath)
    {
        $this->webPath = $webPath;
    }

}
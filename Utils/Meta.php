<?php

namespace QF\Utils;

class Meta
{

    protected $title = array();
    protected $websiteTitle = '';
    protected $metas = array();
    protected $links = array();
    protected $js = array();
    protected $titleAlign = 'ltr';

    public function getTitleAlign()
    {
        return $this->titleAlign;
    }

    public function setTitleAlign($titleAlign)
    {
        $this->titleAlign = ($titleAlign == 'ltr') ? 'ltr' : 'rtl';
    }

    public function getWebsiteTitle()
    {
        return $this->websiteTitle;
    }

    public function setWebsiteTitle($websiteTitle)
    {
        $this->websiteTitle = $websiteTitle;
    }
    
    public function setTitle($title, $append = true)
    {
        if ($append) {
            if ($this->titleAlign == 'ltr') {
                array_push($this->title, $title);
            } else {
                array_unshift($this->title, $title);
            }
        } else {
            $this->title = array($title);
        }
    }

    public function getTitle($glue = false)
    {
        $title = $this->title;
        if ($this->websiteTitle) {
            if ($this->titleAlign == 'ltr') {
                array_unshift($title, $this->websiteTitle);
            } else {
                array_push($title, $this->websiteTitle);
            }
        }
        return ($glue === false) ? $title : implode($glue, $title);
    }

    public function setMeta($name, $content, $isHttpEquiv = false)
    {
        $this->metas[$name] = $isHttpEquiv ? array('http-equiv' => $name, 'content' => $content) : array('name' => $name, 'content' => $content);
    }

    public function getMeta($name = null, $contentOnly = true)
    {
        if ($name === null) {
            return $this->metas;
        }
        return isset($this->metas[$name]) ? ($contentOnly ? $this->metas[$name]['content'] : $this->metas[$name]) : null;
    }

    public function setJS($href, $type = 'text/javascript')
    {
        $this->js[] = array('href' => $href, 'type' => $type);
    }
    
    public function setCSS($href, $media = null)
    {
        $this->setLink('stylesheet', $href, NULL, 'text/css', $media);
    }
    
    public function setLink($rel, $href, $title = null, $type = null, $media = null)
    {
        $this->links[] = array('rel' => $rel, 'href' => $href, 'title' => $title, 'type' => $type, 'media' => $media);
    }

    public function getLinks()
    {
        return $this->links;
    }

    public function setDescription($content)
    {
        $this->setMeta('description', $content);
    }

    public function getDescription()
    {
        return $this->getMeta('description');
    }

    public function setKeywords($content)
    {
        $this->setMeta('keywords', $content);
    }

    public function getKeywords()
    {
        return $this->getMeta('keywords');
    }

    public function get()
    {
        return array('title' => $this->getTitle(), 'metas' => $this->getMeta(), 'links' => $this->getLinks());
    }
    
    public function getTitleOutput($glue = ' ')
    {
        return '<title>'.htmlspecialchars($this->getTitle($glue)).'</title>'."\n";
    }

    public function getMetaOutput()
    {
        $response = '';

        foreach ($this->getMeta() as $current) {
            if (!empty($current['name'])) {
                $response .= '<meta name="' . $current['name'] . '" content="' . htmlspecialchars($current['content']) . '" />' . "\n";
            } elseif (!empty($current['http-equiv'])) {
                $response .= '<meta http-equiv="' . $current['http-equiv'] . '" content="' . htmlspecialchars($current['content']) . '" />' . "\n";
            }
        }
        
        return $response;
    }
    
    public function getLinksOutput()
    {
        $response = '';

        foreach ($this->getLinks() as $current) {
            $response .= '<link';
            if (!empty($current['rel'])) {
                $response .= ' rel="' . htmlspecialchars($current['rel']) . '"';
            }
            if (!empty($current['title'])) {
                $response .= ' title="' . htmlspecialchars($current['title']) . '"';
            }
            if (!empty($current['type'])) {
                $response .= ' type="' . htmlspecialchars($current['type']) . '"';
            }
            if (!empty($current['href'])) {
                $response .= ' href="' . htmlspecialchars($current['href']) . '"';
            }
            if (!empty($current['media'])) {
                $response .= ' media="' . htmlspecialchars($current['media']) . '"';
            }
            $response .= ' />' . "\n";
        }
        
        return $response;
    }
    
    public function getJSOutput()
    {
        $response = '';

        foreach ($this->getJS() as $current) {
            $response .= '<script type="'.htmlspecialchars($current['type']).'" src="';
            $response .= htmlspecialchars($current['file']);
            $response .= '"';
            $response .= ' />' . "\n";
        }
        
        return $response;
    }

}
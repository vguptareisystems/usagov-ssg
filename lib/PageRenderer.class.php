<?php

namespace ctac\ssg;

use \Twig\Loader;
use \ctac\ssg\ProcessorMain;

class PageRenderer
{
    public $ssg;

    public $templates;
    public $templateDir;
    public $templateDirCache;

    public $templateLoader;
    public $templateRenderer;

    public function __construct( &$ssg )
    {
        $this->ssg = &$ssg;

        // $this->templateDir      = realpath($this->ssg->config['baseDir']).'/templates/'.$this->ssg->siteName.'/twig/00-Pages';
        // $this->templateDirCache = realpath($this->ssg->config['baseDir']).'/templates_compiled/'.$this->ssg->siteName.'/twig';
        $this->templateDir      = realpath($this->ssg->config['baseDir']).'/templates/twig/00-Pages';
        $this->templateDirCache = realpath($this->ssg->config['baseDir']).'/templates_compiled/twig';

        $this->templateLoader   = new \Twig_Loader_Fractal($this->templateDir);
        $this->templateRenderer = new \Twig_Environment($this->templateLoader, array(
            'cache' => $this->templateDirCache,
            'auto_reload' => 1
        ));
        $this->addFilters();
    }

    public function addFilters()
    {
        $ssg =& $this->ssg;
        $this->templateRenderer->addFilter(new \Twig_Filter('friendly_url', function ($string) use ($ssg)
        {
            return $ssg->sanitizeForUrl($string);
        }));
        $this->templateRenderer->addFilter(new \Twig_Filter('sanitizeForUrl', function ($string) use ($ssg)
        {
            return $ssg->sanitizeForUrl($string);
        }));
        $this->templateRenderer->addFilter(new \Twig_Filter('onlyFeatures', function ($entities) use ($ssg)
        {
            $features = [];
            foreach ( $entities as $entity ) {
                if ( $ssg->isFeature($entity) ) {
                    $features[$entity['uuid']] = $entity; 
                }
            }
            return $features;
        }));
        $this->templateRenderer->addFilter(new \Twig_Filter('sharesTopicWith', function ($entities,$uuid) use ($ssg)
        {
            return $ssg->sharesTopicWith($entities,$uuid);
        }));
        $this->templateRenderer->addFilter(new \Twig_Filter('sortBy', function ($entities,$key)
        {
            usort($entities,function($a,$b) use ($key) 
            {
                if ( !array_key_exists($key,$a) 
                  || !array_key_exists($key,$b) 
                  || trim($a[$key]) == trim($b[$key]) )
                {
                    return 0;
                }
                return ( trim($a[$key]) < trim($b[$key]) ) ? -1 : 1;
             });
            return $entities;
        }));
    }

  	public function renderPage( &$page )
  	{
          /// PATH
          $url = $this->getPageUrl($page);
          if ( empty($url) )
          {
              /// not renderable
              echo "Render Page: {$page['name']} unrenderable\n";
              return null;
          }
          echo "Render Page: $url ({$page['pageType']}) \"{$page['name']}\"\n";
          $path = ltrim($url,'/');
          if ( !empty($path) && substr($path,-1)!=='/' ) { $path .= '/'; }
          $base = basename($path);
          if ( empty($base) ) { $base = 'index'; }

          $siteDir = './sites/'.trim(strtolower($this->ssg->siteName));
          $fileDir = $siteDir.'/'.$path;
          // $file = $fileDir.'/'.$base.'.html';
          $file = $fileDir.'index.html';

          /// TEMPLATE
          $twig = $this->getTwigPageRenderer($page);
          if ( empty($twig) )
          {
            // echo "  ! no twig renderer {$page['name']} $path\n";
            $html = $path;
            if ( !empty($html) )
            {
                /// directory for path
                if ( !file_exists($fileDir) )
                {
                    mkdir( $fileDir, 0755, true );
                }
                chmod( $fileDir, 0755 );
                file_put_contents( $file, $html );
                echo "No twig template found : Creating empty page for {$page['uuid']} in $file \n";
            }
            return null;
          }

          /// METADATA FOR RENDERING
          $pageData = $this->preProcessPage($page);

          /// HTML
          if ( $page['type_of_page_to_generate'] == 'a-z-page' )
          {
            foreach ( $this->ssg->siteIndexAZ as $letter => $list )
            {
                $pageData['currentAZLetter'] = $letter;
                $html = $twig->render($pageData);
                if ( !empty($html) )
                {
                    /// directory for path
                    $fileDir = $siteDir.'/'.$path.$letter;
                    $file = $fileDir.'/'.'index.html';
                    if ( !file_exists($fileDir) )
                    {
                        mkdir( $fileDir, 0755, true );
                    }
                    chmod( $fileDir, 0755 );
                    file_put_contents( $file, $html );
                    echo "Creating page for {$page['uuid']} in $file \n";
                }
            }
          } else if ( $page['type_of_page_to_generate'] == '50-state-page' ) {
            // render the master page with the dropdown
            // render one page per state in a subdirectory
            /*
            renderPage( 'blah/50-state-friendly-url', template(50-state-page) );
            if ( $page['50-state-category'] == 'autogenerate-business' )
            {
                foreach ( $states as $state_name )
                {
                    // get state details record for state name
                    renderPage( 'blah/state_name', template(state-details-business) );
                }
            } else if ( $page['50-state-category'] == 'autogenerate-government' ) {
                renderPage( 'blah/state_name', template(state-details-governemtn) );
            }
            */
          } else {
            $html = $twig->render($pageData);
            if ( !empty($html) )
            {
                /// directory for path
                if ( !file_exists($fileDir) )
                {
                    mkdir( $fileDir, 0755, true );
                }
                chmod( $fileDir, 0755 );
                file_put_contents( $file, $html );
                echo "Creating page for {$page['uuid']} in $file \n";
            }
          }

          $paths = [ $path ];

          /// SUB-PAGES
          // if ( !empty($pageData['subPages']) )
          // {
          //     foreach ( $pageData['subPages'] as $subPage )
          //     {
          //         $subPaths = $this->renderPage($subPage);
          //         $paths += $subPaths;
          //     }
          // }
          return $paths;
  	}

    public function getPageUrl( $page )
    {
        if ( !empty($page['field_friendly_url'])
          && !empty($page['field_friendly_url']['und'])
          && !empty($page['field_friendly_url']['und'][0])
          && !empty($page['field_friendly_url']['und'][0]['value']) )
        {
            return $page['field_friendly_url']['und'][0]['value'];
        }
        if ( !empty($page['friendly_url']) )
        {
            return $page['friendly_url'];
        }
        return null;
    }

    public function preProcessPage( &$page )
    {
        $processor = null;
        $pageParams = [];

        // $processorClass = "Processor".$this->ssg->getPageType($page);
        // if ( class_exists($processorClass) )
        // {
        //   $processor = new $processorClass($this,$page);
        // } else {
          $processorClass = 'ProcessorMain';
          $processor = new ProcessorMain($this,$page);
        // }
        if ( !empty($processor) )
        {
          // $processor->renderer =& $this;
          // $processor->page =& $page;
          $processor->process( $pageParams );
        }

        return $pageParams;
    }

    public function getTwigPageRenderer( $page )
    {
        $pageType = $this->ssg->getPageType($page);
        // $typeMap = [
        //     'generic-navigation-page' => 'GenericNavigationPage',
        //     'generic-content-page' => 'content-page',
        //     '50-state-page' => '50StatePage',
        //     'a-z-page' => 'atoz',
        //     'more' => 'more-topics',
        //     'home' => 'homepage',
        //     'forms' => 'form',
        //     'site-index' => null,
        //     'directory-record' => 'federal-directory-record',
        //     'government-by-organization' => null
        // ];
        // if ( !empty($typeMap[$pageType]) )
        // {
        //     $pageType = $typeMap[$pageType];
        // }
        // if ( $pageType!=='Home' ){ echo "    Skipping Type:$pageType\n"; return null; }
        if ( $pageType===null )
        {
            return null;
        }

        if ( empty($this->ssg->siteName) ) { echo __LINE__." no renderer:$pageType\n"; return null; }

        if ( empty($this->templates[$this->ssg->siteName]) )
        {
            $this->templates[$this->ssg->siteName] = [];
        }
        if ( empty($this->templates[$this->ssg->siteName]['twig']) )
        {
            $this->templates[$this->ssg->siteName]['twig'] = [];
        }
        ;
        if ( empty($this->templates[$this->ssg->siteName]['twig'][$page['pageType']]) )
        {
            /// if the template file exists
            if ( !file_exists('./templates/twig/00-Pages/'.$page['pageType'].'.twig') )
            {
                $this->templates[$this->ssg->siteName]['twig'][$page['pageType']] = null;
            } else {
                try {
                    // $this->templates[$this->ssg->siteName]['twig'][$page['pageType']] = $this->templateRenderer->load('00-Pages/'.$page['pageType'].'.twig');
                    $this->templates[$this->ssg->siteName]['twig'][$page['pageType']] = $this->templateRenderer->load($page['pageType'].'.twig');
                } catch (Exception $e) { 
                    $this->templates[$this->ssg->siteName]['twig'][$page['pageType']] = null;
                }
            }
        }

        return $this->templates[$this->ssg->siteName]['twig'][$page['pageType']];
    }

    public function initTemplatesDirs()
    {
        if ( !file_exists($this->baseDir.'/templates/') )
        {
            mkdir( $this->baseDir.'/templates/', 0755, true );
        }
        // if ( !file_exists($this->baseDir.'/templates/'.$this->ssg->siteName) )
        // {
        //     mkdir( $this->baseDir.'/templates/'.$this->ssg->siteName, 0755, true );
        // }
        // if ( !file_exists($this->baseDir.'/templates/'.$this->ssg->siteName.'/twig') )
        // {
        //     mkdir( $this->baseDir.'/templates/'.$this->ssg->siteName.'/twig', 0755, true );
        // }
        if ( !file_exists($this->baseDir.'/templates/twig') )
        {
            mkdir( $this->baseDir.'/templates/twig', 0755, true );
        }
        if ( !file_exists($this->baseDir.'/templates/twig/00-Pages') )
        {
            mkdir( $this->baseDir.'/templates/twig/00-Pages', 0755, true );
        }

        if ( !file_exists($this->baseDir.'/templates_compiled/') )
        {
            mkdir( $this->baseDir.'/templates_compiled/', 0755, true );
        }
        // if ( !file_exists($this->baseDir.'/templates_compiled/'.$this->ssg->siteName) )
        // {
        //     mkdir( $this->baseDir.'/templates_compiled/'.$this->ssg->siteName, 0755, true );
        // }
        // if ( !file_exists($this->baseDir.'/templates_compiled/'.$this->ssg->siteName.'/twig') )
        // {
        //     mkdir( $this->baseDir.'/templates_compiled/'.$this->ssg->siteName.'/twig', 0755, true );
        // }
        if ( !file_exists($this->baseDir.'/templates_compiled/twig') )
        {
            mkdir( $this->baseDir.'/templates_compiled/twig', 0755, true );
        }
    }

}

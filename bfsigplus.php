<?php
/**
* @package Customised derivative of sigplus Image Gallery Plus plug-in for Joomla
* @version 0.0.1
* @author http://www.brainforge.co.uk
* @copyright Copyright (C) 2015 Jonathan Brain - brainforge. All rights reserved.
* @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// import library dependencies
jimport('joomla.event.plugin');

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'sigplus'.DIRECTORY_SEPARATOR.'core.php';

/**
* sigplus Image Gallery Plus plug-in.
*/
class plgContentBFSIGPlus extends JPlugin {
	/** Activation tag used to invoke the plug-in. */
	private $activationtag = 'gallery';
	/** sigplus core service object. */
	private $core;
	/** sigplus configuration. */
	private $configuration;
	/** Whether low-level lightbox-only mode should be activated for this article. */
	private $lowlevel;
  /** The original plugin parameters. */
  private $BFparams;

	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);

    $this->BFparams = $this->params;
    $this->params = self::getSigplusParams();

		$activationtag = $this->getParameterValue('activationtag', $this->activationtag);
		if (is_string($activationtag) && ctype_alpha($activationtag)) {
			$this->activationtag = $activationtag;
		}
    $this->activationtag = 'BF' . $this->activationtag;

		// create configuration parameter objects
		$this->configuration = new SIGPlusConfiguration();
		$this->configuration->setParameters($this->params);
	}

	private function getParameterValue($name, $default) {
		if ($this->params instanceof stdClass) {
			if (isset($this->params->$name)) {
				return $this->params->$name;
			}
		} else if ($this->params instanceof JRegistry) {  // Joomla 2.5 and earlier
			$paramvalue = $this->params->get($name);
			if (isset($paramvalue)) {
				return $paramvalue;
			}
		}
		return $default;
	}
	
	/**
	* Joomla 1.5 compatibility method.
	*/
	function onAfterDisplayTitle(&$article, &$params) {
		$this->onContentAfterTitle(null, $article, $params, 0);
	}

	/**
	* Fired before article contents are to be processed by the plug-in.
	* @param $article The article that is being rendered by the view.
	* @param $params An associative array of relevant parameters.
	* @param $limitstart An integer that determines the "page" of the content that is to be generated.
	* @param
	*/
	function onContentAfterTitle($context, &$article, &$params, $limitstart) {

	}

	/**
	* Joomla 1.5 compatibility method.
	*/
	function onPrepareContent(&$row, &$params) {
		$this->onContentPrepare(false, $row, $params, 0);
	}

	/**
	* Fired when contents are to be processed by the plug-in.
	* Recommended usage syntax:
	* a) POSIX fully portable file names
	*    Folder name characters are in [A-Za-z0-9._-])
	*    Regular expression: [/\w.-]+
	*    Example: {gallery rows=1 cols=1}  /sigplus/birds/  {/gallery}
	* b) URL-encoded absolute URLs
	*    Regular expression: (?:[0-9A-Za-z!"$&\'()*+,.:;=@_-]|%[0-9A-Za-z]{2})+
	*    Example: {gallery} http://example.com/image.jpg {/gallery}
	*/
	function onContentPrepare($context, &$article, &$params, $limitstart) {
		// skip plug-in activation when the content is being indexed
		if ($context === 'com_finder.indexer') {
			return;
		}

		if (strpos($article->text, '{'.$this->activationtag) === false) {
			return;  /* short-circuit plugin activation */
		}
		
		// reset low-level lightbox-only mode
		$this->lowlevel = false;

		if (SIGPLUS_LOGGING) {
			$logging = SIGPlusLogging::instance();
			$logging->append('<strong>sigplus is currently running in logging mode</strong>. This should be turned off in a production environment by setting the constant SIGPLUS_LOGGING in <kbd>sigplus.php</kbd> to <kbd>false</kbd>, in which case this message will also disappear.');
		}

		// load language file for internationalized labels and error messages
		$lang = JFactory::getLanguage();
		$lang->load('plg_content_sigplus', JPATH_ADMINISTRATOR);

		try {
			// on-demand instantiation
			if (!isset($this->core)) {
				$this->core = new SIGPlusCore($this->configuration);
			}

			// find gallery tags and emit code
			$activationtag = preg_quote($this->activationtag, '#');
			$article->text = preg_replace_callback('#[{]'.$activationtag.'([^{}]*)(?<!/)[}]\s*((?:[^{]+|[{](?!/'.$activationtag.'))+)\s*[{]/'.$activationtag.'[}]#', array($this, 'getGalleryRegexReplacementExpanded'), $article->text, -1);
			$article->text = preg_replace_callback('#[{]'.$activationtag.'([^{}]*)/[}]#', array($this, 'getGalleryRegexReplacementCollapsed'), $article->text, -1);
			$this->core->addGalleryEngines($this->lowlevel);
		} catch (Exception $e) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( $e->getMessage(), 'error' );
			$article->text = $e->getMessage() . $article->text;
		}

		if (SIGPLUS_LOGGING) {
			$article->text = $logging->fetch().$article->text;
		}
	}

	/**
	 *
	 */
  private static function BFgetEntity($html, &$a, &$e, $type, $single=false) {
    if (empty($e)) {
      $e = 0;
    }
    else if ($e >= strlen($html)) {
      return false;
    }
  
    $a1 = stripos($html, '<' . $type . '>', $e);
    $a2 = stripos($html, '<' . $type . ' ', $e);
    if ($a1 === false) {
      $a = $a2;
    }
    else if ($a2 == false) {
      $a = $a1;
    }
    else if ($a2 < $a1) {
      $a = $a2;
    }
    else {
      $a = $a1;
    }
    if ($a === false) {
      return false;
    }
  
    $e1 = stripos($html, '<' . $type . ' ', $a+1);
    if ($e1 === false) {
      $e1 = stripos($html, '<' . $type . '>', $a+1);
    }
    $e = stripos($html, '</' . $type . '>', $a);
    if ($e === false) {
      if (!$single) {
        return false;
      }
      $e = stripos($html, '/>', $a);
      if ($e === false) {
        $e = stripos($html, '>', $a);
        if ($e === false) {
          return false;
        }
        $e -= 4;
      }
      else {
        $e -= 3;
      }
    }
    if ($e1 !== false && $e1 < $e) {
      $e = $e1;
      return null;
    }
    return trim(substr($html, $a, $e-$a+3+strlen($type)));
  }
  
	/**
	 *
	 */
  private static function BFgetProperty($html, $type) {
    $quote = '"';
    $h1 = stripos($html, $type . '=' . $quote);
    if ($h1 === false) {
      $quote = "'";
      $h1 = stripos($html, $type . '=' . $quote);
      if ($h1 === false) {
        return false;
      }
    }
    $h2 = stripos($html, $quote, $h1+2+strlen($type));
    if ($h2 === false) {
      return false;
    }
    $prop = strip_tags(substr($html, $h1+2+strlen($type), $h2-$h1-2-strlen($type)));
    if (empty($prop)) {
      return false;
    }
    $prop = str_replace('/./', '/', $prop);
    return htmlspecialchars_decode(urldecode($prop));
  }

	/**
	 *
	 */
  public static function BFAddTitles($galleryhtml) {
    $posn = array();
    $titles = array();
    $descriptions= array();
    $a = $e = 0;
    while (true) {
      $li = self::BFgetEntity($galleryhtml, $a, $e, 'li');
      if ($li === false) {
        break;
      }
    
      $a1 = $e1 = 0;
      $link = self::BFgetEntity($li, $a1, $e1, 'a');

      $title = self::BFgetProperty($link, 'alt');
      if (preg_match('@(^.*_summary">)|(</div></div></li>$)@', $li)) {
        $description = preg_replace('@(^.*_summary">)|(</div></div></li>$)@', '', $li);
      }
      else {
        $description = '';
      }
      
      if (!empty($title) || !empty($description)) {
        $posn[] = $e;
        $titles[] = $title;
        $descriptions[] = $description;
      }
    }

    for($i=count($posn)-1; $i>=0; $i--) {
      $galleryhtml = substr($galleryhtml, 0, $posn[$i]) .
                     '<h4 style="text-align:center;margin:0 5px;">' . $titles[$i] . '</h4>' .
                     '<p style="text-align:left;margin:0 5px;">' . $descriptions[$i] . '</p>' .
                     substr($galleryhtml, $posn[$i]);
    }
    
    $backgroundColor = JFactory::getApplication()->getTemplate(true)->params->get('templateBackgroundColor');
    return str_replace('<li>', '<li style="border:solid ' . $backgroundColor . ' 0.2em !important;">', $galleryhtml);
  }

	/**
	* Generates image thumbnails with alternate text, title and lightbox pop-up activation on mouse click.
	* This method is to be called as a regular expression replace callback.
	* Any error messages are printed to screen.
	* @param $match A regular expression match.
	*/
	public function getGalleryRegexReplacementExpanded($match) {
		$imagereference = $match[2];
		if (is_remote_path($imagereference)) {
			$imagereference = safeurlencode($imagereference);
		}
		return $this->getGalleryHtml($imagereference, $match[1]);
	}
	
	/**
	* Generates image thumbnails with alternate text, title and lightbox pop-up activation on mouse click.
	* This method is to be called as a regular expression replace callback.
	* Any error messages are printed to screen.
	* @param $match A regular expression match.
	*/
	public function getGalleryRegexReplacementCollapsed($match) {
		if (strlen(trim($match[1])) === 0) {  // no parameters supplied to {gallery /} activation tag
			$this->lowlevel = true;
			return '';
		} else {  // parameters supplied to {gallery ... /} activation tag, in particular parameter "path"
			return $this->getGalleryHtml(null, $match[1]);
		}
	}

  /**
    *
    */
  private function getGalleryHtml($imagereference, $params) {
    $sigplusParams = self::getSigplusParams();
    parse_str(str_replace(' ', '&', $params), $explodedParams);
    $sigplusParams = json_decode(self::getSigplusParams(true));
    unset($sigplusParams->base_folder);
    
    $params = '';
    foreach($explodedParams as $name=>$value) {
      $params .= $name . '=' . $value . ' ';
    }
    foreach($sigplusParams as $name=>$value) {
      if (!isset($explodedParams[$name])) {
        $params .= $name . '=' . $value . ' ';
      }
    }
    
    return self::BFAddTitles($this->core->getGalleryHtml($imagereference, $params));
  }

  /**
    *
    */
  private static function getSigplusParams($json=false) {
    global $_BF_sigplusparams;
    global $_BF_sigplusparamsJson;

    if (empty($_BF_sigplusparams)) {
      jimport( 'joomla.registry.registry' );
      $plugin = JPluginHelper::getPlugin('content', 'sigplus');
      $_BF_sigplusparamsJson = $plugin->params;
      $_BF_sigplusparams = new JRegistry($_BF_sigplusparamsJson);
    }
    if ($raw) {
      return $_BF_sigplusparamsJson;
    }
    return $_BF_sigplusparams;
  }

  /**
    *
    */
  private static function getSigplusLibGD() {
    global $_BF_sigpluslibGD;

    if (empty($_BFsigpluslibGD)) {
      $_BFsigpluslibGD = new SIGPlusImageLibraryGD();
    }
    return $_BFsigpluslibGD;
  }

  /**
    *
    */
  public static function createThumbnail(&$image, &$thumb_w, &$thumb_h, $crop = true, $quality = 85) {
    if (!class_exists('SIGPlusImageLibraryGD')) {
      return false;
    }

    $sigplusparams = self::getSigplusParams();
    if (empty($thumb_w)) {
      $thumb_w = $sigplusparams->get('thumb_width');
    }
    if (empty($thumb_h)) {
      $thumb_h = $sigplusparams->get('thumb_height');
    }

    $thumb = 'cache/thumbs/bf_' . md5($image . '_' . $thumb_w . '*' . $thumb_h . '*' . $crop . '*' . $quality) . '.jpg';
    $thumbPath = JPATH_SITE . '/' . $thumb;
    $imagePath = JPATH_SITE . '/' . $image;
    $file_exists = false;
    if (file_exists($thumbPath)) {
      if (filemtime($imagePath) < filemtime($thumbPath)) {
        $file_exists = true;
      }
    }
    if ($file_exists ||
        self::getSigplusLibGD()->createThumbnail($imagePath, $thumbPath, $thumb_w, $thumb_h, $crop, $quality)) {
      $image = $thumb;
      return true;
    }
    return false;
  }
}
<?php
/**
 * 이 파일은 iModule 위지윅에디터모듈의 일부입니다. (https://www.imodule.kr)
 *
 * 위지윅에디터와 관련된 모든 기능을 제어한다.
 * 위지윅에디터모듈에 포함된 Froala Wysiwyg Editor (https://froala.com/wysiwyg-editor) 는 iModule 내에서 자유롭게 사용할 수 있도록 라이센싱되어 있습니다.
 * iModule 외부에서 Froala Wysiwyg Editor 사용시 라이센스 위반이므로 주의하시기 바랍니다.
 * 
 * @file /modules/wysiwyg/ModuleWyiswyg.class.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0.161211
 */
class ModuleWysiwyg {
	/**
	 * iModule 및 Module 코어클래스
	 */
	private $IM;
	private $Module;
	
	/**
	 * 언어셋을 정의한다.
	 * 
	 * @private object $lang 현재 사이트주소에서 설정된 언어셋
	 * @private object $oLang package.json 에 의해 정의된 기본 언어셋
	 */
	private $lang = null;
	private $oLang = null;
	
	/**
	 * 위지윅에디터 설정변수
	 */
	private $_attachment = null;
	private $_id = null;
	private $_name = null;
	private $_module = null;
	private $_placeholderText = null;
	private $_content = null;
	private $_required = false;
	private $_theme = 'default';
	private $_height = 300;
	private $_hideButtons = array();
	private $_toolBarFixed = true;
	private $_uploader = true;
	
	/**
	 * HTMLPurifier
	 */
	private $_HTMLPurifier = null;
	
	/**
	 * class 선언
	 *
	 * @param iModule $IM iModule 코어클래스
	 * @param Module $Module Module 코어클래스
	 * @see /classes/iModule.class.php
	 * @see /classes/Module.class.php
	 */
	function __construct($IM,$Module) {
		/**
		 * iModule 및 Module 코어 선언
		 */
		$this->IM = $IM;
		$this->Module = $Module;
		
		/**
		 * 첨부파일모듈 호출
		 */
		$this->_attachment = $this->IM->getModule('attachment');
	}
	
	/**
	 * 모듈 코어 클래스를 반환한다.
	 * 현재 모듈의 각종 설정값이나 모듈의 package.json 설정값을 모듈 코어 클래스를 통해 확인할 수 있다.
	 *
	 * @return Module $Module
	 */
	function getModule() {
		return $this->Module;
	}
	
	/**
	 * [사이트관리자] 모듈 설정패널을 구성한다.
	 *
	 * @return string $panel 설정패널 HTML
	 */
	function getConfigPanel() {
		/**
		 * 설정패널 PHP에서 iModule 코어클래스와 모듈코어클래스에 접근하기 위한 변수 선언
		 */
		$IM = $this->IM;
		$Module = $this->getModule();
		
		ob_start();
		INCLUDE $this->getModule()->getPath().'/admin/configs.php';
		$panel = ob_get_contents();
		ob_end_clean();
		
		return $panel;
	}
	
	/**
	 * 언어셋파일에 정의된 코드를 이용하여 사이트에 설정된 언어별로 텍스트를 반환한다.
	 * 코드에 해당하는 문자열이 없을 경우 1차적으로 package.json 에 정의된 기본언어셋의 텍스트를 반환하고, 기본언어셋 텍스트도 없을 경우에는 코드를 그대로 반환한다.
	 *
	 * @param string $code 언어코드
	 * @param string $replacement 일치하는 언어코드가 없을 경우 반환될 메세지 (기본값 : null, $code 반환)
	 * @return string $language 실제 언어셋 텍스트
	 */
	function getText($code,$replacement=null) {
		if ($this->lang == null) {
			if (is_file($this->getModule()->getPath().'/languages/'.$this->IM->language.'.json') == true) {
				$this->lang = json_decode(file_get_contents($this->getModule()->getPath().'/languages/'.$this->IM->language.'.json'));
				if ($this->IM->language != $this->getModule()->getPackage()->language && is_file($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json') == true) {
					$this->oLang = json_decode(file_get_contents($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json'));
				}
			} elseif (is_file($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json') == true) {
				$this->lang = json_decode(file_get_contents($this->getModule()->getPath().'/languages/'.$this->getModule()->getPackage()->language.'.json'));
				$this->oLang = null;
			}
		}
		
		$returnString = null;
		$temp = explode('/',$code);
		
		$string = $this->lang;
		for ($i=0, $loop=count($temp);$i<$loop;$i++) {
			if (isset($string->{$temp[$i]}) == true) {
				$string = $string->{$temp[$i]};
			} else {
				$string = null;
				break;
			}
		}
		
		if ($string != null) {
			$returnString = $string;
		} elseif ($this->oLang != null) {
			if ($string == null && $this->oLang != null) {
				$string = $this->oLang;
				for ($i=0, $loop=count($temp);$i<$loop;$i++) {
					if (isset($string->{$temp[$i]}) == true) {
						$string = $string->{$temp[$i]};
					} else {
						$string = null;
						break;
					}
				}
			}
			
			if ($string != null) $returnString = $string;
		}
		
		/**
		 * 언어셋 텍스트가 없는경우 iModule 코어에서 불러온다.
		 */
		if ($returnString != null) return $returnString;
		elseif (in_array(reset($temp),array('text','button','action')) == true) return $this->IM->getText($code,$replacement);
		else return $replacement == null ? $code : $replacement;
	}
	
	/**
	 * 상황에 맞게 에러코드를 반환한다.
	 *
	 * @param string $code 에러코드
	 * @param object $value(옵션) 에러와 관련된 데이터
	 * @param boolean $isRawData(옵션) RAW 데이터 반환여부
	 * @return string $message 에러 메세지
	 */
	function getErrorText($code,$value=null,$isRawData=false) {
		$message = $this->getText('error/'.$code,$code);
		if ($message == $code) return $this->IM->getErrorText($code,$value,null,$isRawData);
		
		$description = null;
		switch ($code) {
			case 'NOT_ALLOWED_SIGNUP' :
				if ($value != null && is_object($value) == true) {
					$description = $value->title;
				}
				break;
				
			case 'DISABLED_LOGIN' :
				if ($value != null && is_numeric($value) == true) {
					$description = str_replace('{SECOND}',$value,$this->getText('text/remain_time_second'));
				}
				break;
			
			default :
				if (is_object($value) == false && $value) $description = $value;
		}
		
		$error = new stdClass();
		$error->message = $message;
		$error->description = $description;
		
		if ($isRawData === true) return $error;
		else return $this->IM->getErrorText($error);
	}
	
	/**
	 * 위지윅에디터 설정값을 초기화한다.
	 */
	function reset() {
		$this->_id = null;
		$this->_name = null;
		$this->_placeholderText = null;
		$this->_module = null;
		$this->_content = null;
		$this->_required = false;
		$this->_theme = 'white';
		$this->_hideButtons = array();
		$this->_uploader = true;
	}
	
	/**
	 * 위지윅에디터 아이디를 설정한다.
	 *
	 * @param string $id
	 * @return object $this
	 */
	function setId($id) {
		$this->_id = $id;
		
		return $this;
	}
	
	/**
	 * 위지윅에디터 TEXTAREA 의 NAME을 설정한다.
	 *
	 * @param string $name
	 * @return object $this
	 */
	function setName($name) {
		$this->_name = $name;
		$this->_attachment->setWysiwyg($name);
		
		return $this;
	}
	
	/**
	 * 위지윅에디터를 호출한 모듈명을 설정한다.
	 * 위지윅에디터에서 직접적으로 사용되지 않고, 위지윅에디터와 함께 사용되는 첨부파일모듈에서 사용된다.
	 *
	 * @param string $module
	 * @return object $this
	 */
	function setModule($module) {
		$this->_module = $module;
		$this->_attachment->setModule($module);
		
		return $this;
	}
	
	/**
	 * 위지윅에디터 입력폼의 placeholder 값을 설정한다.
	 *
	 * @param string $placeholder
	 * @return object $this
	 */
	function setPlaceholder($text) {
		$this->_placeholderText = $text;
		
		return $this;
	}
	
	
	function loadFile($files=array()) {
		$this->_attachment->loadFile($files);
		
		return $this;
	}
	
	function setContent($content) {
		$this->_content = $content;
		
		return $this;
	}
	
	function setRequired($required) {
		$this->_required = $required;
		
		return $this;
	}
	
	function setTheme($theme) {
		$this->_theme = $theme;
		
		return $this;
	}
	
	function getAttachment() {
		return $this->_attachment;
	}
	
	function setHeight($height) {
		$this->_height = $height;
		
		return $this;
	}
	
	function setUploader($uploader) {
		$this->_uploader = $uploader;
		
		return $this;
	}
	
	function setHideButtons($hideButtons=array()) {
		$this->_hideButtons = $hideButtons;
		
		return $this;
	}
	
	function setToolBarFixed($toolbarFixed) {
		$this->_toolBarFixed = $toolbarFixed;
		
		return $this;
	}
	
	/**
	 * 위지윅에디터를 사용하기 위한 필수요소를 미리 불러온다.
	 */
	function preload() {
		$this->IM->addHeadResource('script',$this->getModule()->getDir().'/scripts/wysiwyg.js.php');
		$this->IM->addHeadResource('style',$this->getModule()->getDir().'/styles/wysiwyg.css.php?theme='.$this->_theme);
	}
	
	/**
	 * 위지윅 에디터를 가져온다.
	 */
	function get() {
		$this->_id = $this->_id == null ? uniqid('wysiwyg-') : $this->_id;
		$this->_name = $this->_name == null ? 'content' : $this->_name;
		$this->IM->addHeadResource('script',$this->getModule()->getDir().'/scripts/wysiwyg.js.php');
		$this->IM->addHeadResource('style',$this->getModule()->getDir().'/styles/wysiwyg.css.php?theme='.$this->_theme);
		
		$wysiwyg = PHP_EOL.'<div data-role="wysiwyg">'.PHP_EOL;
		$wysiwyg.= '<textarea id="'.$this->_id.'" name="'.$this->_name.'" data-wysiwyg="true" data-module="'.$this->_module.'" data-uploader="'.($this->_uploader == true ? 'true' : 'false').'" data-minHeight="'.$this->_height.'"'.($this->_required == true ? ' data-required="required"' : '').''.($this->_placeholderText != null ? ' placeholder="'.$this->_placeholderText.'"' : '').'>'.($this->_content !== null ? $this->_content : '').'</textarea>'.PHP_EOL;
		$wysiwyg.= '</div>'.PHP_EOL;
		$wysiwyg.= $this->_buildScript();
		
		$this->reset();
		return $wysiwyg;
		
		echo $wysiwyg;
		
		if ($this->_uploader == true) {
			$this->_attachment->setId($this->_id.'-attachment');
			$this->_attachment->doLayout();
		}
		
		$this->reset();
	}
	
	/**
	 * XSS 공격방지 처리 클래스를 가져온다.
	 *
	 * @return HTMLPurifier $HTMLPurifier
	 */
	function getHTMLPurifier() {
		if ($this->_HTMLPurifier != null) return $this->_HTMLPurifier;
		
		REQUIRE_ONCE __IM_PATH__.'/classes/HTMLPurifier/HTMLPurifier.auto.php';

		$config = HTMLPurifier_Config::createDefault();
		$config->set('Cache.SerializerPath',$this->IM->getModule('attachment')->getTempPath(true));
		$config->set('Attr.EnableID',false);
		$config->set('Attr.DefaultImageAlt','');
		$config->set('AutoFormat.Linkify',false);
		$config->set('HTML.MaxImgLength',null);
		$config->set('CSS.MaxImgLength',null);
		$config->set('CSS.AllowTricky',true);
		$config->set('Core.Encoding','UTF-8');
		$config->set('HTML.FlashAllowFullScreen',true);
		$config->set('HTML.SafeEmbed',true);
		$config->set('HTML.SafeIframe',true);
		$config->set('HTML.SafeObject',true);
		$config->set('Output.FlashCompat',true);
	
		$config->set('URI.SafeIframeRegexp', '#^(?:https?:)?//(?:'.implode('|', array(
			'www\\.youtube(?:-nocookie)?\\.com/',
			'maps\\.google\\.com/',
			'player\\.vimeo\\.com/video/',
			'www\\.microsoft\\.com/showcase/video\\.aspx',
			'(?:serviceapi\\.nmv|player\\.music)\\.naver\\.com/',
			'(?:api\\.v|flvs|tvpot|videofarm)\\.daum\\.net/',
			'v\\.nate\\.com/',
			'play\\.mgoon\\.com/',
			'channel\\.pandora\\.tv/',
			'www\\.tagstory\\.com/',
			'play\\.pullbbang\\.com/',
			'tv\\.seoul\\.go\\.kr/',
			'ucc\\.tlatlago\\.com/',
			'vodmall\\.imbc\\.com/',
			'www\\.musicshake\\.com/',
			'www\\.afreeca\\.com/player/Player\\.swf',
			'static\\.plaync\\.co\\.kr/',
			'video\\.interest\\.me/',
			'player\\.mnet\\.com/',
			'sbsplayer\\.sbs\\.co\\.kr/',
			'img\\.lifestyler\\.co\\.kr/',
			'c\\.brightcove\\.com/',
			'www\\.slideshare\\.net/',
		)).')#');
		
		$this->_HTMLPurifier = new HTMLPurifier($config);
		return $this->_HTMLPurifier;
	}
	 
	
	/**
	 * 위지윅에디터의 내용을 정리한다.
	 *
	 * @param string $content 위지윅에디터 내용 HTML
	 * @param object[] $attachments 위지윅에디터에 포함된 첨부파일 배열
	 * @return string $content 정리된 위지윅에디터 내용 HTML
	 */
	function encodeContent($content,$attachments=array()) {
		if (preg_match_all('/<img(.*?)data-idx="([0-9]+)"(.*?)>/',$content,$match) == true) {
			for ($i=0, $loop=count($match[0]);$i<$loop;$i++) {
				if (in_array($match[2][$i],$attachment) == true) {
					$image = preg_replace('/ src="(.*?)"/','',$match[0][$i]);
					$content = str_replace($match[0][$i],$image,$content);
				} else {
					$file = $this->db()->select($this->table->attachment)->where('idx',$match[2][$i])->getOne();
					if ($file == null) {
						$content = str_replace($match[0][$i],'',$content);
					} else {
						$fileIdx = $this->IM->getModule('attachment')->copyFile($match[2][$i]);
						$image = preg_replace('/ src="(.*?)"/','',$match[0][$i]);
						$image = str_replace('data-idx="'.$match[2][$i].'"','data-idx="'.$fileIdx.'"',$image);
						$content = str_replace($match[0][$i],'',$content);
					}
				}
			}
		}
		
		return $content;
	}
	
	/**
	 * 위지윅에디터 내용 출력을 위해 내용을 정리한다.
	 * 공격코드제거(AntiXSS) 및 첨부파일 정리, 스타일시트 적용
	 *
	 * @param string $content 위지윅에디터 원본내용
	 * @return string $content 출력을 위한 위지윅에디터 내용
	 */
	function decodeContent($content) {
		$content = $this->getHTMLPurifier()->purify($content);
		$content = PHP_EOL.'<div data-role="wysiwyg-content">'.$content.'</div>'.PHP_EOL;
		
		return $content;
	}
	
	function _buildScript() {
		$script = '<script>$(document).ready(function() { $("#'.$this->_id.'").wysiwyg(); });</script>'.PHP_EOL;
		
		return $script;
	}
}
?>
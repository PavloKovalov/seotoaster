<?php
/**
 * Controller for all stuff that belongs to theme, template, css.
 */

class Backend_ThemeController extends Zend_Controller_Action {

	const DEFAULT_CSS_NAME = 'style.css';

	private $_protectedTemplates = array('index', 'default', 'category', 'news');

	private $_websiteConfig = null;
	private $_themeConfig = null;

	public function  init() {
		parent::init();
		if(!Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_THEMES)) {
			$this->_redirect($this->_helper->website->getUrl(), array('exit' => true));
		}
		$this->view->websiteUrl = $this->_helper->website->getUrl();
		$this->_websiteConfig	= Zend_Registry::get('website');
		$this->_themeConfig		= Zend_Registry::get('theme');
		$this->_translator      = Zend_Registry::get('Zend_Translate');
		$this->_helper->AjaxContext()->addActionContexts(array(
			'pagesviatemplate' => 'json',
		))->initContext('json');

	}

	/**
	 * Method returns template editing screen
	 * and saves edited template
	 */
	public function templateAction() {
		$templateForm = new Application_Form_Template();
		$templateName = $this->getRequest()->getParam('id');
		$mapper       = Application_Model_Mappers_TemplateMapper::getInstance();
		$currentTheme = $this->_helper->config->getConfig('currentTheme');
		if(!$this->getRequest()->isPost()) {
			$templateForm->getElement('pageId')->setValue($this->getRequest()->getParam('pid'));
			if($templateName) {
				$template = $mapper->find($templateName);
				if($template instanceof Application_Model_Models_Template) {
					$templateForm->getElement('content')->setValue($template->getContent());
					$templateForm->getElement('name')->setValue($template->getName());
					$templateForm->getElement('id')->setValue($template->getName());
					$templateForm->getElement('templateType')->setValue($template->getType());
					$this->view->pagesUsingTemplate = Tools_Page_Tools::getPagesCountByTemplate($templateName);
				}
				//get template preview image
				try {
					$templatePreviewDir = $this->_websiteConfig['path'].$this->_themeConfig['path'].$currentTheme.DIRECTORY_SEPARATOR.$this->_themeConfig['templatePreview'];
					$images = Tools_Filesystem_Tools::findFilesByExtension($templatePreviewDir, '(jpg|gif|png)', false, true, false);
					if (isset($images[$template->getName()])) {
						$this->view->templatePreview = 	$this->_themeConfig['path'].$currentTheme.'/'.$this->_themeConfig['templatePreview'].$images[$template->getName()];
					}
				}
				catch (Exceptions_SeotoasterException $se) {
					$this->view->templatePreview = 	'system/images/no_preview.png';
				}
			}
		} else {
			if($templateForm->isValid($this->getRequest()->getPost())) {
				$templateData = $templateForm->getValues();
				$originalName = $templateData['id'];
				if (!empty($originalName)){
					$status = 'update';
					//find template by original name
					$template = $mapper->find($originalName);
					if (null === $template){
						$this->_helper->response->response($this->_translator->translate('Can\'t create template'), true);
					}
					// avoid renaming of system protected templates
					if (!in_array($template->getName(), $this->_protectedTemplates)) {
						$template->setOldName($originalName);
						$template->setName($templateData['name']);
					}
					$template->setContent($templateData['content']);
				} else {
					$status = 'new';
					//if ID missing and name is not exists and name is not system protected - creating new template
					if ( (null!==$mapper->find($templateData['name'])) || in_array($templateData['name'], $this->_protectedTemplates) ) {
						$this->_helper->response->response($this->_translator->translate('Template exists'), true);
					}
					$template = new Application_Model_Models_Template($templateData);
				}

				// saving/updating template in db
				$template->setType($templateData['templateType']);
				$result = $mapper->save($template);
				if ($result){
					$this->_helper->cache->clean(false, false, array(preg_replace('/[^\w\d_]/', '', $template->getName())));
				}
				// saving to file in theme folder
				$currentThemePath = realpath($this->_websiteConfig['path'] . $this->_themeConfig['path'] . $currentTheme);
				$filepath = $currentThemePath.'/'.$templateData['name'].'.html';
				try {
					if ($filepath) {
						Tools_Filesystem_Tools::saveFile($filepath, $templateData['content']);
					}
				} catch (Exceptions_SeotoasterException $e) {
					error_log($e->getMessage());
				}
                $this->_helper->cache->clean(Helpers_Action_Cache::KEY_PLUGINTABS, Helpers_Action_Cache::PREFIX_PLUGINTABS);

				$this->_helper->response->response($status, false);

			} else {
				$errorMessages = array();
				$validationErrors = $templateForm->getErrors();
				$messages = array(
					'name' => array(
						'isEmpty'	           => 'Template name field can\'t be empty.',
						'notAlnum'             => 'Template name contains characters which are non alphabetic and no digits',
						'stringLengthTooLong'  => 'Template name field is too long.',
						'stringLengthTooShort' => 'Template name field is too short.'),
					'content' => array(
						'isEmpty'	=> 'Content can\'t be empty.'
					)
				);
				foreach ($validationErrors as $element => $errors){
					if (empty ($errors)) {
						continue;
					}
					foreach ($messages[$element] as $n=>$message){
						if (in_array($n, $errors)){
							array_push($errorMessages, $message);
						}
					}
				}
				$this->_helper->response->response($errorMessages, true);
			}
		}
        $this->view->helpSection  = 'addtemplate';
		$this->view->templateForm = $templateForm;
	}

	/**
	 * Method return form for editing css files for current theme
	 * and saves css file content
	 */
	public function editcssAction() {
		$cssFiles =	$this->_buildCssFileList();
		$defaultCss = $this->_websiteConfig['path'] . $this->_themeConfig['path'] . array_search(self::DEFAULT_CSS_NAME, current($cssFiles));

		$editcssForm = new Application_Form_Css();
		$editcssForm->getElement('cssname')->setMultiOptions($cssFiles);
		$editcssForm->getElement('cssname')->setValue(self::DEFAULT_CSS_NAME);

		//checking, if form was submited via POST then
		if ($this->getRequest()->isPost()){
			$postParams = $this->getRequest()->getParams();
			if (isset($postParams['getcss']) && !empty ($postParams['getcss'])) {
				$cssName = $postParams['getcss'];
				try {
					$content = Tools_Filesystem_Tools::getFile($this->_websiteConfig['path'] . $this->_themeConfig['path'] . $cssName);
					$this->_helper->response->response($content, false);
				} catch (Exceptions_SeotoasterException $e){
					$this->_helper->response->response($e->getMessage(), true);
				}
			} else {
				if (is_string($postParams['content']) && empty($postParams['content'])){
					$editcssForm->getElement('content')->setRequired(false);
				}
				if ($editcssForm->isValid($postParams)){
					$cssName = $postParams['cssname'];
					try {
						Tools_Filesystem_Tools::saveFile($this->_websiteConfig['path'] . $this->_themeConfig['path'] . $cssName, $postParams['content']);
						$params = array(
							'websiteUrl' => $this->_helper->website->getUrl(),
							'themePath'	 => $this->_websiteConfig['path'].$this->_themeConfig['path'],
							'currentTheme' =>$this->_helper->config->getConfig('currentTheme')
						);
						$concatCss = Tools_Factory_WidgetFactory::createWidget('Concatcss', array('refresh' => true), $params);
						$concatCss->render();
						$this->_helper->response->response($this->_translator->translate('CSS saved') , false);
					} catch (Exceptions_SeotoasterException $e) {
						$this->_helper->response->response($e->getMessage(), true);
					}
				}
			}
			$this->_helper->response->response($this->_translator->translate('Undefined error'), true);
		} else {
			try {
				$editcssForm->getElement('content')->setValue(Tools_Filesystem_Tools::getFile($defaultCss));
				$editcssForm->getElement('cssname')->setValue(array_search(self::DEFAULT_CSS_NAME, current($cssFiles)));
			} catch (Exceptions_SeotoasterException $e){
				$this->view->errorMessage = $e->getMessage();
			}
		}
        $this->view->helpSection = 'editcss';
		$this->view->editcssForm = $editcssForm;
	}

	/**
	 * Method build a list of css files for current theme
	 * with subdirectories
	 * @return <type>
	 */
	private function _buildCssFileList() {
		$currentThemeName	= $this->_helper->config->getConfig('currentTheme');
		$currentThemePath	= realpath($this->_websiteConfig['path'] . $this->_themeConfig['path'] . $currentThemeName);

		$cssFiles = Tools_Filesystem_Tools::findFilesByExtension($currentThemePath, 'css', true);

		$cssTree = array();
		foreach ($cssFiles as $file){
			// don't show concat.css for editing
			if (strtolower(basename($file)) == Widgets_Concatcss_Concatcss::FILENAME) {
				continue;
			}
			preg_match_all('~^'.$currentThemePath.'/([a-zA-Z0-9-_\s/.]+/)*([a-zA-Z0-9-_\s.]+\.css)$~i', $file, $sequences);
			$subfolders = $currentThemeName.'/'.$sequences[1][0];
			$files = array();
			foreach ($sequences[2] as $key => $value) {
				$files[$subfolders.$value] = $value;
			}

			if (!array_key_exists($subfolders, $cssTree)){
				$cssTree[$subfolders] = array();
			}
			$cssTree[$subfolders] = array_merge($cssTree[$subfolders], $files);

		}

		return $cssTree;
	}


	/**
	 * Method returns list of templates or template content if id given in params (AJAX)
	 * @return html || json
	 */
	public function gettemplateAction(){
		if ($this->getRequest()->isPost()){
			$mapper        = Application_Model_Mappers_TemplateMapper::getInstance();
			$listtemplates = $this->getRequest()->getParam('listtemplates');
			$additional    = $this->getRequest()->getParam('additional');
			$pageId        = $this->getRequest()->getParam('pageId');
			if($pageId) {
				$page = Application_Model_Mappers_PageMapper::getInstance()->find($pageId);
			}
			$currentTheme  = $this->_helper->config->getConfig('currentTheme');
			//get template preview image
			$templatePreviewDir = $this->_websiteConfig['path'].$this->_themeConfig['path'].$currentTheme.DIRECTORY_SEPARATOR.$this->_themeConfig['templatePreview'];
			if ($templatePreviewDir && is_dir($templatePreviewDir)){
				$tmplImages = Tools_Filesystem_Tools::findFilesByExtension($templatePreviewDir, '(jpg|gif|png)', false, true, false);
			} else {
				$tmplImages = array();
			}

            $types = $mapper->fetchAllTypes();
            if(array_key_exists($listtemplates, array_merge($types, array('all' => 'all')))) {
                $template                       = (isset($page) && $page instanceof Application_Model_Models_Page) ? $mapper->find($page->getTemplateId()) : $mapper->find($listtemplates);
                $this->view->templates          = $this->_getTemplateListByType($listtemplates, $tmplImages, $currentTheme, ($template instanceof Application_Model_Models_Template) ? $template->getName() : '');
                if(empty($this->view->templates) || !$this->view->templates) {
                    $this->_helper->response->response($this->_translator->translate('Template not found'), true);
                    return true;
                }
                $this->view->protectedTemplates = $this->_protectedTemplates;
                $this->view->types              = $types;
                echo $this->view->render($this->getViewScript('templateslist'));
            } else {
                $template = $mapper->find($listtemplates);
                if ($template instanceof Application_Model_Models_Template) {
                    $template = array(
                        'id'		=> $template->getId(),
                        'name'		=> $template->getName(),
                        'fullName'  => $template->getName(),
                        'type'      => $template->getType(),
                        'content'	=> $template->getContent(),
                        'preview'	=> isset($tmplImages[$template->getName()]) ?
                            $this->_themeConfig['path'].$currentTheme.'/'.$this->_themeConfig['templatePreview'].$tmplImages[$template->getName()] :
                            'system/images/no_preview.png'
                    );
                    $this->_helper->response->response($template, true);
                } else {
                    //$response = array('done'=> false);
                    $this->_helper->response->response($this->_translator->translate('Template not found'), true);
                }
            }

			exit;
		}
	}

	private function _getTemplateListByType($type, $tmplImages, $currentTheme, $currTemplate = '') {
		$where        = (($type != 'all') ? "type = '" . $type . "'" : null);
		$templates    = Application_Model_Mappers_TemplateMapper::getInstance()->fetchAll($where);
		$templateList = array();
		foreach ($templates as $template) {
			array_push($templateList, array(
				'id'	        => $template->getId(),
				'name'	        => $template->getName(),
				'fullName'      => $template->getName(),
				'isCurrent'     => ($template->getName() == $currTemplate) ? true : false,
				'content'       => $template->getContent(),
				'preview_image' => isset($tmplImages[$template->getName()]) ? $this->_themeConfig['path'].$currentTheme.'/'.$this->_themeConfig['templatePreview'].$tmplImages[$template->getName()] : false, //'system/images/no_preview.png'
                'pagesCount'    => Tools_Page_Tools::getPagesCountByTemplate($template->getName())
			));
		}
		return $templateList;
	}

	/**
	 * Method which delete template (AJAX)
	 */
	public function deletetemplateAction(){
		if ($this->getRequest()->isPost()){
			$mapper = Application_Model_Mappers_TemplateMapper::getInstance();
			$templateId = $this->getRequest()->getPost('id');
			if ($templateId){
				$template = $mapper->find($templateId);
				if ($template instanceof Application_Model_Models_Template && !in_array($template->getName(), $this->_protectedTemplates)){
					$result = $mapper->delete($template);
					if ($result) {
						$currentThemePath = realpath($this->_websiteConfig['path'] . $this->_themeConfig['path'] . $this->_helper->config->getConfig('currentTheme'));
						$filename = $currentThemePath.'/'.$template->getName().'.html';
						Tools_Filesystem_Tools::deleteFile($filename);
						$status = $this->_translator->translate('Template deleted.');
					} else {
						$status = $this->_translator->translate('Can\'t delete template or template doesn\'t exists.');
					}
					$this->_helper->response->response($status, false);
				}
			}
			$this->_helper->response->response($this->_translator->translate('Template doesn\'t exists'), false);
		}
	}

	public function themesAction(){
		$themePath = $this->_websiteConfig['path'] . $this->_themeConfig['path'];
		$themeDirs = Tools_Filesystem_Tools::scanDirectoryForDirs($themePath);
		$themesList = array();
		foreach ($themeDirs as $themeName) {
			$files = Tools_Filesystem_Tools::scanDirectory($themePath.$themeName);
			//check for necessary html files
			$requiredFiles = preg_grep('/^('.implode('|', $this->_protectedTemplates).')\.html$/i', $files);
			if (sizeof($requiredFiles) != 4){
				continue;
			}
			$previews = preg_grep('/^preview\.(png|jpg|gif)$/i', $files);
			array_push($themesList, array(
				'name'      => $themeName,
				'preview'   => !empty ($previews) ? $this->_helper->website->getUrl().$this->_themeConfig['path'].$themeName.'/'.reset($previews) : $this->_helper->website->getUrl().'system/images/noimage.png',
				'isCurrent' => ($this->_helper->config->getConfig('currentTheme') == $themeName)
			));
		}
        $this->view->helpSection = 'themes';
		$this->view->themesList  = $themesList;
	}

	public function applythemeAction(){
		if ($this->getRequest()->isPost()){
			$selectedTheme = trim($this->getRequest()->getParam('themename'));
			if (is_dir($this->_websiteConfig['path'].$this->_themeConfig['path'].$selectedTheme)) {
				$errors = $this->_saveThemeInDatabase($selectedTheme);
				if (empty ($errors)){
					$status = sprintf($this->_translator->translate('The theme "%s" applied!'), $selectedTheme);
					$this->_helper->cache->clean(false, false);
					$this->_helper->response->response($status, false);
				} else {
					$this->_helper->response->response($errors, true);
				}
			}
		}
		$this->_redirect($this->_helper->website->getUrl());
	}

	public function downloadthemeAction() {
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		$themeName    = filter_var($this->getRequest()->getParam('name'), FILTER_SANITIZE_STRING);
		$themeData    = Zend_Registry::get('theme');
		$pathToTheme  = $this->_helper->website->getPath() . $themeData['path'] . $themeName ;
		$themeArchive = Tools_System_Tools::zip($pathToTheme);
		$this->getResponse()->clearAllHeaders()->clearBody();
		$this->getResponse()->setHeader('Content-Disposition', 'attachment; filename=' . Tools_Filesystem_Tools::basename($themeArchive))
            ->setHeader('Content-Type', 'application/force-download', true)
            ->setHeader('Content-Transfer-Encoding','binary', true)
            ->setHeader('Expires', date(DATE_RFC1123), true)
            ->setHeader('Cache-Control','must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Pragma','public', true)
            ->setHeader('Content-Length' , filesize($themeArchive), true)
            ->setBody(file_get_contents($themeArchive))
            ->sendResponse();
        exit;
	}

	public function deletethemeAction(){
		if ($this->getRequest()->isPost()) {

			$themeName = $this->getRequest()->getParam('name');
			if ($this->_helper->config->getConfig('currentTheme') == $themeName) {
//				echo json_encode(array('done'=>false, 'status'=>'trying to remove current theme'));
				$this->_helper->response->response($this->_translator->translate('trying to remove current theme'), true);
			}
			$status = Tools_Filesystem_Tools::deleteDir($this->_websiteConfig['path'].$this->_themeConfig['path'].$themeName);

//			echo json_encode(array('done'=>true,'status'=>$status));
//			exit;
			$this->_helper->response->response($status, false);
		}
		$this->_redirect($this->_helper->website->getUrl());
	}

	/**
	 * Method saves theme in database
	 */
	private function _saveThemeInDatabase($themeName){
		$errors = array();
		$themePath = $this->_websiteConfig['path'].$this->_themeConfig['path'].$themeName;
		$themeFiles = Tools_Filesystem_Tools::findFilesByExtension($themePath, '(html|htm)', true, true);

        foreach($this->_protectedTemplates as $name){
            if (!array_key_exists($name, $themeFiles)){
                array_push($errors, $this->_translator->translate('Theme missing %s\$1 template', $name));
            }
        }

		if (!empty($errors)) return $errors;

		$mapper = Application_Model_Mappers_TemplateMapper::getInstance();
		$removedTemplatesCount = $mapper->clearTemplates(); // this will remove all templates except system required. @see $_protectedTemplates

		$nameValidator = new Zend_Validate();
		$nameValidator->addValidator(new Zend_Validate_Alnum(true))
					  ->addValidator(new Zend_Validate_StringLength(array(3,45)));

		foreach ($themeFiles as $tmplName => $file) {
			if (!$nameValidator->isValid($tmplName)){
				array_push($errors, 'Not valid name for template: '.$tmplName);
				continue;
			}

			$template = $mapper->find($tmplName);
			if (! $template instanceof Application_Model_Models_Template) {
				$template = new Application_Model_Models_Template();
				$template->setName($tmplName);
			}

			// getting template content
			try{
				$content = Tools_Filesystem_Tools::getFile($file);
				$template->setContent($content);
			} catch (Exceptions_SeotoasterException $e){
				array_push($errors, 'Can\'t read template file: '.$tmplName);
			}

			// saving template to db
			$mapper->save($template);
			unset($template);
		}

		//updating config table
		Application_Model_Mappers_ConfigMapper::getInstance()->save(array('currentTheme' => $themeName));

		return $errors;
	}

	/**
	 * Returns amount of pages using specific template
	 *
	 */
	public function pagesviatemplateAction() {
		$templateName = $this->getRequest()->getParam('template', '');
		if($templateName) {
			$this->view->pagesUsingTemplate = Tools_Page_Tools::getPagesCountByTemplate($templateName);
		}
	}
}


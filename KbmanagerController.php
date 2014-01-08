<?php

/**
 * Controller for Dispedia.
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_formgenerator
 * @author     Lars Eidam <larseidam@googlemail.com>
 * @copyright  Copyright (c) 2013
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */ 
class KbmanagerController extends OntoWiki_Controller_Component
{
    
    private $_store;
    private $_translate;
    private $_ontologies;
    
    /**
     * init controller
     */     
    public function init()
    {
        parent::init();
        
        $this->_store = Erfurt_App::getInstance()->getStore();
        
        $this->_translate = $this->_owApp->translate;
        
        $this->_owApp->getNavigation()->disableNavigation();
        
        // init array for output messages
        $this->_messages = array();
        
        // get all models
        $this->_ontologies = $this->_config->ontologies->toArray();
        $this->_ontologies = $this->_ontologies['models'];
        
        // make model instances
        foreach ($this->_ontologies as $modelName => $model) {
            if ($this->_store->isModelAvailable($model['namespace'])) {
                $this->_ontologies[$modelName]['instance'] = $this->_store->getModel($model['namespace']);
            }
            $namespaces[$model['namespace']] = $modelName;
        }
    }
    
    public function indexAction()
    {
        $this->_redirect('kbmanager/store', array());
    }
    
    public function storeAction()
    {
        $this->view->headLink()->appendStylesheet($this->_componentUrlBase .'public/css/store.css');
        $this->view->headScript()->appendFile($this->_componentUrlBase .'public/js/store.js');
        
        if ($this->_erfurt->getAc()->isActionAllowed('PataproStore')) {
            $this->view->url = $this->_config->urlBase;
    
            $this->view->models = $this->_ontologies;
            
            $this->view->storedModels = $this->_store->getAvailableModels(true);
        }
        else {
            $this->_redirect($this->_config->urlBase . 'index', array());
        }
    }
    
    public function changemodelAction()
    {
        $jsonReturnValue = "";

        // disable auto-rendering
        $this->_helper->viewRenderer->setNoRender();

        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        $modelName = urldecode($this->getParam ('modelName'));
        $action = urldecode($this->getParam ('do'));
        $backup = urldecode($this->getParam ('backup', false));
        $hidden = urldecode($this->getParam ('hidden', false));

        if ($this->_erfurt->getAc()->isActionAllowed('KBManagerStore')) {
            if ("" != $modelName)
            {
                $jsonReturnValue['modelName'] = $modelName;
                $jsonReturnValue['action'] = $action;
                $jsonReturnValue['modelUri'] = $this->_ontologies[$modelName]['namespace'];
                $jsonReturnValue['files'] = array();
                $jsonReturnValue['log'] = array();
                
                switch ($action) {
                    case "create":
                        $jsonReturnValue = $this->_createOntology($modelName, $hidden, $jsonReturnValue);
                        break;
                    case "fill":
                        $jsonReturnValue = $this->_addContentToOntology($modelName, $jsonReturnValue);
                        break;
                    case "add":
                        $jsonReturnValue = $this->_createOntology($modelName, $hidden, $jsonReturnValue);
                        $jsonReturnValue = $this->_addContentToOntology($modelName, $jsonReturnValue);
                        break;
                    case "clear":
                        $jsonReturnValue = $this->_removeOntology($modelName, $backup, $jsonReturnValue);
                        $jsonReturnValue = $this->_createOntology($modelName, $hidden, $jsonReturnValue);
                        break;
                    case "delete":
                        $jsonReturnValue = $this->_removeOntology($modelName, $backup, $jsonReturnValue);
                        break;
                    case "renew":
                        $jsonReturnValue = $this->_removeOntology($modelName, $backup, $jsonReturnValue);
                        $jsonReturnValue = $this->_createOntology($modelName, $hidden, $jsonReturnValue);
                        $jsonReturnValue = $this->_addContentToOntology($modelName, $jsonReturnValue);
                        break;
                    case "backup":
                        $jsonReturnValue = $this->_backupOntology($modelName);
                        break;
                    default:
                        $jsonReturnValue['error'] = "wrong action (" . $action . ")";
                }
            } else {
                $jsonReturnValue['error'] = "no model name";
            }
        } else {
            $jsonReturnValue['error'] = "no access rights";
        }

        echo json_encode($jsonReturnValue);
    }
    
    private function _createOntology($modelName, $hidden, $jsonReturnValue)
    {
        $newType = Erfurt_Store::MODEL_TYPE_OWL;
        
        if (false === $this->_store->isModelAvailable($this->_ontologies[$modelName]['namespace'])) {
            // create model
            $model = $this->_store->getNewModel($this->_ontologies[$modelName]['namespace'], $this->_ontologies[$modelName]['namespace'], $newType);
            $jsonReturnValue['log'][] = "model added";
            
            // connect it with system model
            $useSysBaseNew = array();
            $useSysBaseNew[] = array(
                    'type'  => 'uri',
                    'value' => $this->_config->sysbase->model
                    );
    
            $model->setOption($this->_config->sysont->properties->hiddenImports, $useSysBaseNew);
            $jsonReturnValue['log'][] = "model connected to sys model";
            
            // set hidden status
            if ('true' == $hidden) {
                $model->setOption(
                    $this->_config->sysont->properties->hidden,
                    array(
                        array(
                            'value'    => 'true',
                            'type'     => 'literal',
                            'datatype' => EF_XSD_BOOLEAN
                            )
                        )
                    );
                $jsonReturnValue['log'][] = "model set to hidden";
            }
        } else {
            $jsonReturnValue['log'][] = "model skipped, because it's already available";
        }
        
        return $jsonReturnValue;
    }
    
    private function _addContentToOntology($modelName, $jsonReturnValue)
    {
        // get ontologies config object
        $ontologies = $this->_config->ontologies->toArray();
        if (0 === strpos($ontologies['folder'], '/')) {
            $ontologiePath = $ontologies['folder'] . '/';
        } else {
            $ontologiePath = getcwd() . '/' . $ontologies['folder'] . '/';
        }
        
        $filetype = 'auto';
        if ($this->_store->isModelAvailable($this->_ontologies[$modelName]['namespace']))
        {
            // add filecontent
            if (isset($this->_ontologies[$modelName]['files'])) {
                foreach ($this->_ontologies[$modelName]['files'] as $filename)
                {
                    $file = $ontologiePath . $filename;
                    $deleteFile = false;
                    if (
                        isset($this->_ontologies[$modelName]['options'])
                        && false !== array_search('replace', $this->_ontologies[$modelName]['options'])
                    ) {
                        $fileStr = file_get_contents($file);
                        foreach ($this->_ontologies[$modelName]['replace'] as $replaceArray) {
                            $fileStr = str_replace(
                                $replaceArray['search'],
                                $replaceArray['replace'],
                                $fileStr
                            );
                            $jsonReturnValue['log'][] = "replace " . $replaceArray['search'];
                            $jsonReturnValue['log'][] = "with " . $replaceArray['replace'];
                        }
                        $filename = date('Y.m.d-H:i:s') . '_' . $filename;
                        $file = $ontologiePath . 'backup/' . $filename;
                        if (false !== file_put_contents($file, $fileStr)) {
                            $jsonReturnValue['log'][] = "write temp file: " . $filename;
                            $deleteFile = true;
                        } else {
                            $jsonReturnValue['log'][] = "couldn't write temp file: " . $filename;
                        }
                    }
                    // import data to model
                    $this->_store->importRdf(
                        $this->_ontologies[$modelName]['namespace'],
                        $file,
                        $filetype,
                        Erfurt_Syntax_RdfParser::LOCATOR_FILE
                    );
                    $jsonReturnValue['files'][] = $filename;
                    $jsonReturnValue['log'][] = "file " . $filename. " added to model";
                    if (true === $deleteFile) {
                        if (true === unlink($file)) {
                            $jsonReturnValue['log'][] = "deleted temp file: " . $filename;
                        } else {
                            $jsonReturnValue['log'][] = "couldn't delete temp file: " . $filename;
                        }
                    }
                }
            }
            
            // add linkcontent
            if (isset($this->_ontologies[$modelName]['links'])) {
                foreach ($this->_ontologies[$modelName]['links'] as $linkUrl)
                {
                    // import data to model
                    $this->_store->importRdf(
                        $this->_ontologies[$modelName]['namespace'],
                        $linkUrl,
                        $filetype,
                        Erfurt_Syntax_RdfParser::LOCATOR_URL
                    );
                    $jsonReturnValue['links'][] = $linkUrl;
                    $jsonReturnValue['log'][] = "link " . $linkUrl. " added to model";
                }
            }
        } else {
            $jsonReturnValue['error'] = "model not available";
        }
        
        return $jsonReturnValue;
    }
    
    private function _removeOntology($modelName, $backup, $jsonReturnValue)
    {
        if ($this->_store->isModelAvailable($this->_ontologies[$modelName]['namespace']))
        {
            if ('true' == $backup)
            {
                $this->_backupOntology($modelName);
            }
            $this->_store->deleteModel($this->_ontologies[$modelName]['namespace']);
            $jsonReturnValue['log'][] = "model removed";
        } else {
            $jsonReturnValue['log'] = "model skipped, because it's not available";
        }
        
        return $jsonReturnValue;
    }
    
    private function _backupOntology($modelName)
    {
        // get ontologies config object
        $ontologies = $this->_config->ontologies->toArray();
        $ontologiePath = getcwd() . '/' . $ontologies['folder'] . '/';

        $filename = $ontologiePath . 'backup/' . $modelName . '_' . date('Y.m.d-H:i:s') . '.xml';
        $serializationType = 'xml';
        $fileContent = $this->_store->exportRdf(
            $this->_ontologies[$modelName]['namespace'],
            $serializationType,
            null
        );

        $fileHandle = fopen($filename, 'w+');
        $returnValue = fwrite($fileHandle, $fileContent);
        fclose($fileHandle);

        return $returnValue;
    }
    
    /**
     * add status messages to global array
     */
    public function addMessages($messages)
    {
        if (is_array($messages))
            $this->_messages = array_merge($this->_messages, $messages);
        else
            $this->_messages[] = $messages;
    }

    /**
     * show messages after every action
     */
    public function postDispatch()
    {
        foreach ($this->_messages as $message) {
            $this->_owApp->appendMessage($message);
        }
    }
}


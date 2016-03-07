<?php
/**
 * @link      http://fraym.org
 * @author    Dominik Weber <info@fraym.org>
 * @copyright Dominik Weber <info@fraym.org>
 * @license   http://www.opensource.org/licenses/gpl-license.php GNU General Public License, version 2 or later (see the LICENSE file)
 */
namespace Fraym\Template;
use \Fraym\Block\BlockXML as BlockXML;

/**
 * @Injectable(lazy=true)
 */
class DynamicTemplate
{
    /**
     * @Inject
     * @var \Fraym\Template\DynamicTemplateController
     */
    protected $dynamicTemplateController;

    /**
     * @Inject
     * @var \Fraym\Route\Route
     */
    protected $route;

    /**
     * @Inject
     * @var \Fraym\Block\BlockParser
     */
    protected $blockParser;

    /**
     * @Inject
     * @var \Fraym\Template\Template
     */
    protected $template;

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
     * @Inject
     * @var \Fraym\Request\Request
     */
    public $request;

    /**
     * @Inject
     * @var \Fraym\FileManager\FileManager
     */
    public $fileManager;

    /**
     * @param $blockId
     * @param BlockXML $blockXML
     * @return BlockXML
     */
    public function saveBlockConfig($blockId, \Fraym\Block\BlockXML $blockXML)
    {
        $blockConfig = $this->request->getGPAsObject();

        $customProperties = new \Fraym\Block\BlockXMLDom();
        $element = $customProperties->createElement('dynamicTemplateConfig');
        $element->appendChild($customProperties->createCDATASection(serialize($blockConfig->config)));
        $customProperties->appendChild($element);

        $element = $customProperties->createElement('dynamicTemplate');
        $element->nodeValue = $blockConfig->dynamicTemplate;
        $customProperties->appendChild($element);
        $blockXML->setCustomProperty($customProperties);

        return $blockXML;
    }

    /**
     * @param $xml
     * @return mixed
     */
    public function execBlock($xml)
    {
        $variables = unserialize((string)$xml->dynamicTemplateConfig);
        $template = null;
        if(!empty((string)$xml->dynamicTemplate)) {
            $template = $this->getTemplatePath() . DIRECTORY_SEPARATOR . (string)$xml->dynamicTemplate;
        }
        $this->dynamicTemplateController->render($template, $variables);
    }

    /**
     * @param null $blockId
     */
    public function getBlockConfig($blockId = null)
    {
        $configXml = null;
        if ($blockId) {
            $block = $this->db->getRepository('\Fraym\Block\Entity\Block')->findOneById($blockId);
            $configXml = $this->blockParser->getXMLObjectFromString($this->blockParser->wrapBlockConfig($block));
        }

        $files = $this->getTemplateFiles();
        $selectOptions = $this->buildSelectOptions($files);

        $this->dynamicTemplateController->getBlockConfig($selectOptions, $configXml);
    }

    /**
     * @param $files
     * @param array $options
     * @param null $parentKey
     * @return mixed
     */
    private function buildSelectOptions($files, &$options = array(), $parentKey = null) {
        foreach($files as $file) {
            if($file['isDir'] === true) {
                if(count($file['files'])) {
                    $newParentKey = ($parentKey ? $parentKey . '/' : '') . $file['name'];
                    $options[$newParentKey] = array();
                    $subFiles = $file['files'];
                    $this->buildSelectOptions($subFiles, $options, $newParentKey);
                }
            } else {
                if($parentKey) {
                    $options[$parentKey][] = $file['name'];
                } else {
                    $options[] = $file['name'];
                }
            }
        }
        return $options;
    }

    /**
     * @return \Fraym\Registry\Entity\text|string
     */
    private function getTemplatePath() {
        $config = $this->config->get('DYNAMIC_TEMPLATE_PATH');
        if(!empty($config->value)) {
            $path = $config->value;
        } else {
            $path = $this->template->getTemplateDir() . DIRECTORY_SEPARATOR . $this->template->getDefaultDir() .
                DIRECTORY_SEPARATOR . 'Fraym' . DIRECTORY_SEPARATOR . 'Template' . DIRECTORY_SEPARATOR . 'DynamicTemplate';
        }
        return $path;
    }

    /**
     * @return array
     */
    private function getTemplateFiles() {
        $path = $this->getTemplatePath();
        return $this->fileManager->getFiles($path);
    }

    /**
     * @Fraym\Annotation\Route("/load-dynamic-template-config", name="dynamicTemplateConfig", permission={"GROUP:Administrator"})
     */
    public function loadDynamicTemplateConfig()
    {
        $template = $this->request->post('template');
        $blockId = $this->request->post('blockId');
        $variables = array();

        if($blockId) {
            $block = $this->db->getRepository('\Fraym\Block\Entity\Block')->findOneById($blockId);
            $xml = $this->blockParser->getXMLObjectFromString($this->blockParser->wrapBlockConfig($block));
            $variables = unserialize((string)$xml->dynamicTemplateConfig);
        }

        $template = $this->getTemplatePath() . DIRECTORY_SEPARATOR . $template;

        $templateContent = file_get_contents($template);
        $blocks = $this->blockParser->getAllBlocks($templateContent);
        foreach($blocks as $block) {
            $obj = $this->blockParser->getXMLObjectFromString($block);
            if($this->blockParser->getXMLAttr($obj, 'type') === 'config') {
                return $this->dynamicTemplateController->renderConfig((string)$obj->template, $variables);
            }
        }
    }
}
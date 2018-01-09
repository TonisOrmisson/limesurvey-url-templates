<?php

/**
 * Class UrlTemplateChanger - A plugin that enables to load desired templates via
 * URL parameter
 * @author Tõnis Ormisson <tonis@andmemasin.eu>
 * @since 2.63.0.
 */
class UrlTemplateChanger extends PluginBase {

    protected $storage = 'DbStorage';
    static protected $description = 'A plugin to override participant survey template via URL param';
    static protected $name = 'URL Template changer';

    protected $templates;

    /* Register plugin on events*/
    public function init() {
        $this->subscribe('afterFindSurvey');
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
    }

    public function afterFindSurvey(){
        $event = $this->event;
        $templatesEnabled = boolval($this->get("enabled",'Survey',$event->get('surveyid')));
        $paramName = $this->get("paramName",'Survey',$event->get('surveyid'));

        if($templatesEnabled){
            $templateKey = $this->api->getRequest()->getQuery($paramName);
            $possibleTemplates = json_decode($this->get("templates",'Survey',$event->get('surveyid')));
            if(isset($possibleTemplates->{$templateKey})){
                $templateName = $possibleTemplates->{$templateKey}->template;
                $allTemplates = array_keys($this->api->getTemplateList());
                if(in_array($templateName,$allTemplates)){
                    $this->event->set('template',$templateName);
                }
            }
        }
    }



    /**
     * This event is fired by the administration panel to gather extra settings
     * available for a survey.
     * The plugin should return setting meta data.
     */
    public function beforeSurveySettings()
    {
        $event = $this->event;
        $defaultTemplates = (object) array(
            "business"=>array(
                "description" => "My business template",
                "template"=> "vanilla",
            ),
            "fancy"=> array(
                "description" => "My Fancy template",
                "template" => "bootswatch",
            ),
            "funny"=>array(
                "description" => "My funny template",
                "template" => "fruity",
            )
        );

        // set defaults
        $templates = ($this->get('templates', 'Survey', $event->get('survey')) ? $this->get('templates', 'Survey', $event->get('survey')) : json_encode($defaultTemplates));
        $paramName = ($this->get('paramName', 'Survey', $event->get('survey')) ? $this->get('paramName', 'Survey', $event->get('survey')) : 'template');

        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'enabled' => array(
                    'type' => 'boolean',
                    'label' => 'Enable loading templates from URLs',
                    'default'=>true,
                    'current' => $this->get('enabled', 'Survey', $event->get('survey'))
                ),
                'paramName' => array(
                    'type' => 'string',
                    'label' => 'URL parameter name that triggers template change',
                    'current' => $paramName,
                ),
                'info' => array(
                    'type' => 'info',
                    'content'=> 'Set Template names matching key that represents the URL template parameter',
                ),
                'templates' => array(
                    'type' => 'json',
                    'current' => $templates,
                ),
            )
        ));
    }


    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
        $this->set('myTemplates', $event->get('settings')['templates'],'Survey',$event->get('survey'));
    }

}

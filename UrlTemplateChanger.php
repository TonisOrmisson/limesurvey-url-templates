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

    /** @var Survey */
    private $survey;

    const SESSION_KEY = "UrlTemplateChanger";


    /* Register plugin on events*/
    public function init() {
        $this->subscribe('afterFindSurvey');
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');

        $this->subscribe('beforeResponseSave');
    }
    public function beforeResponseSave()
    {
        $oEvent = $this->getEvent();
        $this->log("survey id is : ".$oEvent->get('surveyId'));
        tracevar("survey id is : ".$oEvent->get('surveyId'));
        $response = $oEvent->get('model');
        $this->log("response id is : ".$response->id);
        tracevar("response id is : ".$response->id);
    }

    public function afterResponseSave()
    {
        $oEvent = $this->getEvent();
        $this->log("survey id is : ".$oEvent->get('surveyId'));
        tracevar("survey id is : ".$oEvent->get('surveyId'));
        $response = $oEvent->get('model');
        $this->log("response id is : ".$response->id);
        tracevar("response id is : ".$response->id);
        die("im here");
    }
    public function afterFindSurvey() {
        $this->loadSurvey();
        if (empty($this->survey)) {
            return;
        }
        $surveyId = $this->survey->primaryKey;
        tracevar("testing 123");

        $templatesEnabled = boolval($this->get("enabled", 'Survey', $surveyId));
        if (!$templatesEnabled) {
            return;
        }

        $paramName = $this->get("paramName", 'Survey', $surveyId);
        if (empty($paramName)) {
            return;
        }

        $templateKey = $this->api->getRequest()->getQuery($paramName);
        $possibleTemplates = json_decode($this->get("templates", 'Survey', $surveyId));
        $possibleTemplateKeys = array_keys((array) $possibleTemplates);

        if (!empty($templateKey) and in_array($templateKey, $possibleTemplateKeys)) {
            Yii::app()->session[$this->sessionKey()] = $templateKey;
        }
        if(empty($templateKey)) {
            return;
        }

        $templateKey = Yii::app()->session[$this->sessionKey()];
        $templateName = $possibleTemplates->{$templateKey}->template;
        $allTemplates = array_keys($this->api->getTemplateList());

        if (in_array($templateName, $allTemplates)) {
            $this->event->set('template', $templateName);
        }
    }

    private function sessionKey()
    {
        return self::SESSION_KEY."::".$this->survey->primaryKey;
    }

    private function loadSurvey()
    {
        $event = $this->event;
        $surveyId = $event->get('surveyid');

        /**
         * NB need rto do it without find() since the code at hand is itself run
         * after find() rsulting in infinite loop
         */
        $query = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Survey::model()->tableName())
            ->where('sid=:sid')
            ->bindParam(':sid', $surveyId, PDO::PARAM_STR);
        $surveyArray = $query->queryRow();

        if (empty($surveyArray)) {
            return;
        }
        $this->survey = (new Survey());
        $this->survey->attributes = $surveyArray;

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
        $this->set('myTemplates', $event->get('settings')['templates'], 'Survey', $event->get('survey'));
    }

}

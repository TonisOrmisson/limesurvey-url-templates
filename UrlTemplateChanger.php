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

        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeSurveyPage'); // this does not work due to bug
        $this->subscribe('afterFindSurvey'); // fall back to this due to beforeSurveyPage bug
    }

    public function beforeSurveyPage()
    {
        Yii::log("beforeSurveyPage plugin call", "trace", $this->logCategory());
        $this->beforeGetTemplateInstance();
    }

    public function afterFindSurvey()
    {
        $event = $this->event;
        Yii::log("afterFindSurvey plugin call", "trace", $this->logCategory());
        $this->beforeGetTemplateInstance();
    }


    public function beforeGetTemplateInstance()
    {

        Yii::log("beforeGetTemplateInstance ", "trace", $this->logCategory());
        $this->loadSurvey();
        if (empty($this->survey)) {
            Yii::log("No survey, skipping ", "trace", $this->logCategory());
            return;
        }

        $surveyId = $this->survey->primaryKey;

        $templatesEnabled = boolval($this->get("enabled", 'Survey', $surveyId));
        if (!$templatesEnabled) {
            Yii::log("template switching disabled, skipping ", "trace", $this->logCategory());
            return;
        }

        $paramName = $this->get("paramName", 'Survey', $surveyId);
        if (empty($paramName)) {
            Yii::log("url paramName missing, skipping", "trace", $this->logCategory());
            return;
        }

        Yii::log("Looking for template key", "trace", $this->logCategory());
        $templateKey = $this->api->getRequest()->getQuery($paramName);
        $possibleTemplates = json_decode($this->get("templates", 'Survey', $surveyId));
        $possibleTemplateKeys = array_keys((array) $possibleTemplates);


        if (!empty($templateKey) and in_array($templateKey, $possibleTemplateKeys)) {
            Yii::app()->session[$this->sessionKey()] = $templateKey;
        }

        if(!isset(Yii::app()->session[$this->sessionKey()])) {
            Yii::log("Session template-key missing, skipping", "trace", $this->logCategory());
            return;
        }

        $templateKey = Yii::app()->session[$this->sessionKey()];
        Yii::log("Looking for the template by key $templateKey", "trace", $this->logCategory());
        $templateName = $possibleTemplates->{$templateKey}->template;
        Yii::log("Looking for the template $templateName", "trace", $this->logCategory());
        $allTemplates = array_keys($this->api->getTemplateList());

        if (in_array($templateName, $allTemplates)) {
            $templateKey = array_search($templateName, $allTemplates);
            Yii::log("SETTING template $templateName", "info", $this->logCategory());
            $this->event->set('template', $templateName);
        } else {
            Yii::log("did not find template $templateName", "info", $this->logCategory());
        }

    }

    private function sessionKey()
    {
        return self::SESSION_KEY."::".$this->survey->primaryKey;
    }

    private function loadSurvey()
    {
        Yii::log("Loading survey", "trace",  $this->logCategory());

        $event = $this->event; // beforeSurveyPage;
        $possibleSurveyIdParameterNames = ['surveyid', 'surveyId', 'survey'];

        foreach ($possibleSurveyIdParameterNames as $possibleSurveyIdParameterName) {
            $surveyId = $event->get($possibleSurveyIdParameterName);
            if(!empty($surveyId)) {
                Yii::log("Found surveyId:" . $surveyId, "trace",  $this->logCategory());
                break;
            }
        }
        if(empty($surveyId)) {
            Yii::log("SurveyId not found:" . $surveyId, "trace",  $this->logCategory());
            return;
        }

        /**
         * NB need to do it without find() since the code at hand is itself run
         * after find() resulting in infinite loop
         */
        $query = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Survey::model()->tableName())
            ->where('sid=:sid')
            ->bindParam(':sid', $surveyId, PDO::PARAM_STR);
        $surveyArray = $query->queryRow();

        if (empty($surveyArray)) {
            Yii::log("Got empty survey:$surveyId", "info",  $this->logCategory());
            return;
        }
        Yii::log("Creating a survey from array", "trace",  $this->logCategory());
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
        Yii::log("beforeSurveySettings ", "trace", $this->logCategory());

        $event = $this->event;
        $defaultTemplates = (object) [
            "business"=> [
                "description" => "My business template",
                "template"=> "vanilla",
            ],
            "fancy"=> [
                "description" => "My Fancy template",
                "template" => "bootswatch",
            ],
            "funny"=> [
                "description" => "My funny template",
                "template" => "fruity",
            ],
        ];

        // set defaults
        $surveyTemplates = $this->get('templates', 'Survey', $event->get('survey'));
        $surveyParamName = $this->get('paramName', 'Survey', $event->get('survey'));
        $templates = !empty($surveyTemplates) ? $surveyTemplates : json_encode($defaultTemplates);
        $paramName = !empty($surveyParamName) ? $surveyParamName : 'template';


        $event->set("surveysettings.{$this->id}", [
            'name' => get_class($this),
            'settings' => [
                'enabled' => [
                    'type' => 'boolean',
                    'label' => 'Enable loading templates from URLs',
                    'default'=>true,
                    'current' => $this->get('enabled', 'Survey', $event->get('survey')),
                ],
                'paramName' => [
                    'type' => 'string',
                    'label' => 'URL parameter name that triggers template change',
                    'current' => $paramName,
                ],
                'info' => [
                    'type' => 'info',
                    'content'=> 'Set Template names matching key that represents the URL template parameter',
                ],
                'templates' => [
                    'type' => 'json',
                    'current' => $templates,
                ],
            ],
        ]);
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

    private function logCategory()
    {
        return "andmemasin\\".__CLASS__;
    }


}

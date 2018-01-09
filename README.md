# limesurvey-url-templates

A LimeSurvey plugin to enable overriding survey template for participants via URL paramater.

# Requirements
Requires minimum LimeSurvey version 2.63.0.

# Usage
##1 Install and activate plugin

##2 Set allowed templates & keys in survey settings:
Go to survey plugin settings.

1. Enable loading templates from URLs
2. Set used url parameter values and respective template names as json setting.
3. Set url parameter name that is used to get the template key.
![example settings](images/limesurvey-url-templates.png)

##3 Use URL parameter to fire the template referred in settings

###Url structure:
* <https://example.com/LimeSurvey/survey/index/sid/{SID}/token/{token}/lang/{lang}/newtest/Y/{templateparam}/{template-key}/>

###Example urls
* <https://example.com/LimeSurvey/survey/index/sid/123456/token/123456790abc/lang/en/newtest/Y/template/business/>
* <https://example.com/LimeSurvey/survey/index/sid/123456/token/123456790abc/lang/en/newtest/Y/template/fancy/>
* <https://example.com/LimeSurvey/survey/index/sid/123456/token/123456790abc/lang/en/newtest/Y/template/funny/>

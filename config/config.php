<?php

use Interop\Container\ContainerInterface;
use Piwik\Plugins\AdvancedCampaignReporting\Tracker;

return [
    'advanced_campaign_reporting.campaign_detector' => DI\object(
        '\Piwik\Plugins\AdvancedCampaignReporting\Campaign\CampaignDetector'
    ),
    'advanced_campaign_reporting.uri_parameters.campaign_name' => DI\factory(function (ContainerInterface $c) {
        return [ Tracker::CAMPAIGN_NAME_FIELD => $c->has('ini.AdvancedCampaignReporting.campaign_name') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_name'))) :
            Tracker::$CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_keyword' => DI\factory(function (ContainerInterface $c) {
        return [Tracker::CAMPAIGN_KEYWORD_FIELD => $c->has('ini.AdvancedCampaignReporting.campaign_keyword') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_keyword'))) :
            Tracker::$CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_source' => DI\factory(function (ContainerInterface $c) {
        return [ Tracker::CAMPAIGN_SOURCE_FIELD => $c->has('ini.AdvancedCampaignReporting.campaign_source') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_source'))) :
            Tracker::$CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_medium' => DI\factory(function (ContainerInterface $c) {
        return [ Tracker::CAMPAIGN_MEDIUM_FIELD => $c->has('ini.AdvancedCampaignReporting.campaign_medium') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_medium'))) :
            Tracker::$CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_content' => DI\factory(function (ContainerInterface $c) {
        return [ Tracker::CAMPAIGN_CONTENT_FIELD => $c->has('ini.AdvancedCampaignReporting.campaign_content') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_content'))) :
            Tracker::$CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_id' => DI\factory(function (ContainerInterface $c) {
        return [ Tracker::CAMPAIGN_ID_FIELD => $c->has('ini.AdvancedCampaignReporting.campaign_id') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_id'))) :
            Tracker::$CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
];

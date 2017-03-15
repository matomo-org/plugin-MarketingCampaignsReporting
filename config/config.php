<?php

use Interop\Container\ContainerInterface;
use Piwik\Plugins\AdvancedCampaignReporting\Columns;
use Piwik\Plugins\AdvancedCampaignReporting\AdvancedCampaignReporting;

return [
    'advanced_campaign_reporting.campaign_detector' => DI\object(
        '\Piwik\Plugins\AdvancedCampaignReporting\Campaign\CampaignDetector'
    ),
    'advanced_campaign_reporting.uri_parameters.campaign_name' => DI\factory(function (ContainerInterface $c) {
        return [ (new Columns\CampaignName())->getColumnName() => $c->has('ini.AdvancedCampaignReporting.campaign_name') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_name'))) :
            AdvancedCampaignReporting::$CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_keyword' => DI\factory(function (ContainerInterface $c) {
        return [(new Columns\CampaignKeyword())->getColumnName() => $c->has('ini.AdvancedCampaignReporting.campaign_keyword') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_keyword'))) :
            AdvancedCampaignReporting::$CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_source' => DI\factory(function (ContainerInterface $c) {
        return [ (new Columns\CampaignSource())->getColumnName() => $c->has('ini.AdvancedCampaignReporting.campaign_source') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_source'))) :
            AdvancedCampaignReporting::$CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_medium' => DI\factory(function (ContainerInterface $c) {
        return [ (new Columns\CampaignMedium())->getColumnName() => $c->has('ini.AdvancedCampaignReporting.campaign_medium') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_medium'))) :
            AdvancedCampaignReporting::$CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_content' => DI\factory(function (ContainerInterface $c) {
        return [ (new Columns\CampaignContent())->getColumnName() => $c->has('ini.AdvancedCampaignReporting.campaign_content') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_content'))) :
            AdvancedCampaignReporting::$CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_id' => DI\factory(function (ContainerInterface $c) {
        return [ (new Columns\CampaignId())->getColumnName() => $c->has('ini.AdvancedCampaignReporting.campaign_id') ?
            array_map('trim', explode(',', $c->get('ini.AdvancedCampaignReporting.campaign_id'))) :
            AdvancedCampaignReporting::$CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
];

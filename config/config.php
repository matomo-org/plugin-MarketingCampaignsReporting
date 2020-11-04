<?php

use Psr\Container\ContainerInterface;
use Piwik\Plugins\MarketingCampaignsReporting\Columns;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;

return [
    'advanced_campaign_reporting.campaign_detector'               => DI\autowire(
        '\Piwik\Plugins\MarketingCampaignsReporting\Campaign\CampaignDetector'
    ),
    'advanced_campaign_reporting.uri_parameters.campaign_name'    => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignName())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_name') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_name'))) :
                MarketingCampaignsReporting::$CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_keyword' => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignKeyword())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_keyword') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_keyword'))) :
                MarketingCampaignsReporting::$CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_source'  => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignSource())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_source') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_source'))) :
                MarketingCampaignsReporting::$CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_medium'  => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignMedium())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_medium') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_medium'))) :
                MarketingCampaignsReporting::$CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_content' => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignContent())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_content') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_content'))) :
                MarketingCampaignsReporting::$CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_id'      => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignId())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_id') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_id'))) :
                MarketingCampaignsReporting::$CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_group'      => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignGroup())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_group') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_group'))) :
                MarketingCampaignsReporting::$CAMPAIGN_GROUP_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
    'advanced_campaign_reporting.uri_parameters.campaign_placement'      => DI\factory(function (ContainerInterface $c) {
        return [
            (new Columns\CampaignPlacement())->getColumnName() => $c->has('ini.MarketingCampaignsReporting.campaign_placement') ?
                array_map('trim', explode(',', $c->get('ini.MarketingCampaignsReporting.campaign_placement'))) :
                MarketingCampaignsReporting::$CAMPAIGN_PLACEMENT_FIELD_DEFAULT_URL_PARAMS
        ];
    }),
];

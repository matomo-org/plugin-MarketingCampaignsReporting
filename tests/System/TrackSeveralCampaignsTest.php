<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later:
 * https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\System;

use Piwik\Cache;
use Piwik\Db;
use Piwik\Plugin\Manager;
use Piwik\Plugins\MarketingCampaignsReporting\tests\Fixtures\TrackAdvancedCampaigns;
use Piwik\Tests\Framework\TestCase\SystemTestCase;
use Piwik\Version;

/**
 * @group MarketingCampaignsReporting
 * @group Plugins
 */
class TrackSeveralCampaignsTest extends SystemTestCase
{
    /**
     * @var TrackAdvancedCampaigns
     */
    public static $fixture = null; // initialized below class definition

    public static $isMariaDB = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $version = strtolower(Db::fetchOne("SELECT VERSION()"));
        self::$isMariaDB = strpos($version, "mariadb") !== false;
    }

    public static function getOutputPrefix()
    {
        return '';
    }

    public static function getPathToTestDirectory()
    {
        return dirname(__FILE__);
    }

    /**
     * @dataProvider getApiForTesting
     * @group        TrackSeveralCampaignsTest
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    /**
     * Old API is disabled if plugin is enabled
     * This test aims to check if campaigns are still
     * correctly fetch with the old api if plugin is disabled
     *
     * @dataProvider getReferrerApiForTesting
     * @group        TrackSeveralCampaignsTest
     */
    public function testAnotherApi($api, $params)
    {
        Manager::getInstance()->unloadPlugin('MarketingCampaignsReporting');
        Cache::flushAll();
        $this->runApiTests($api, $params);
        Manager::getInstance()->loadPlugin('MarketingCampaignsReporting');
        Cache::flushAll();
    }


    public function getApiForTesting()
    {
        $dateWithPluginEnabled = self::$fixture->dateTimeWithPluginEnabled;
        $dateTime              = self::$fixture->dateTime;
        $phpVersionPrefix = version_compare(PHP_VERSION, 8.2, '<') && !self::$isMariaDB ? 'min_php_' : '';

        $xmlFieldsToRemove = [];

        if (version_compare(Version::VERSION, '5.5.0-b1', '<')) {
            $xmlFieldsToRemove = ['Referrers_visitorsFromAIAssistants', 'Referrers_distinctAIAssistants', 'Referrers_visitorsFromAIAssistants_percent'];
        }

        $apiToTest[] = [
            'API.get',
            [
                'idSite'  => self::$fixture->idSite,
                'date'    => $dateWithPluginEnabled,
                'periods' => ['day'],
                'testSuffix' => (!empty($phpVersionPrefix) ? $phpVersionPrefix : 'max_php_') . (version_compare(Version::VERSION, '5.2.0-b6', '<') ? 'old' : ''),
                'xmlFieldsToRemove' => $xmlFieldsToRemove,
            ]
        ];

        $columnsToHide = [];

        if (version_compare(Version::VERSION, '5.2.0-alpha', '<')) {
            // In Matomo 5.2 referrer columns had been added to ecommerce actions. For tests with older Matomo releases we therefor ignore those columns
            $columnsToHide = array_merge($columnsToHide, ['referrerType', 'referrerName', 'referrerKeyword']);
        }

        if (version_compare(Version::VERSION, '5.5.0-b1', '<')) {
            // In Matomo 5.5 ai referrer had been added
            $columnsToHide = array_merge($columnsToHide, ['referrerAIAssistantUrl', 'referrerAIAssistantIcon']);
        }

        $apiToTest[] = [
            'Live.getLastVisitsDetails',
            [
                'idSite'            => self::$fixture->idSite,
                'date'              => $dateWithPluginEnabled,
                'periods'           => ['day'],
                'xmlFieldsToRemove' => $columnsToHide,
                'testSuffix'        => $phpVersionPrefix,
            ]
        ];

        $api = [
            'MarketingCampaignsReporting'
        ];

        $apiToTest[] = [
            $api,
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => ['day'],
                'testSuffix'             => 'expanded',
                'otherRequestParameters' => ['expanded' => 1],
            ]
        ];
        $apiToTest[] = [
            $api,
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => ['day'],
                'testSuffix'             => 'flat',
                'otherRequestParameters' => ['flat' => 1, 'expanded' => 0],
            ]
        ];
        $apiToTest[] = [
            $api,
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => ['day'],
                'testSuffix'             => 'segmentedMatchAll',
                'segment'                => 'campaignName!=test;campaignKeyword!=test;campaignSource!=test;campaignMedium!=test;campaignContent!=test;campaignId!=test',
                'otherRequestParameters' => ['flat' => 1, 'expanded' => 0],
            ]
        ];
        $apiToTest[] = [
            $api,
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => ['day'],
                'testSuffix'             => 'segmentedMatchNone',
                'segment'                => 'campaignName==test,campaignKeyword==test,campaignSource==test,campaignMedium==test,campaignContent==test,campaignId==test',
                'otherRequestParameters' => ['flat' => 1, 'expanded' => 0],
            ]
        ];

        $apiToTest[] = [
            'MarketingCampaignsReporting',
            [
                'idSite'            => 'all',
                'date'              => $dateTime,
                'periods'           => 'day',
                'setDateLastN'      => true,
                'testSuffix'        => 'multipleDatesSites_',
            ]
        ];

        // row evolution tests for methods that also use Referrers plugin data
        $apiToTest[] = [
            'API.getRowEvolution',
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateTime,
                'testSuffix'             => 'getName',
                'otherRequestParameters' => [
                    'date'      => '2013-01-20,2013-01-25',
                    'period'    => 'day',
                    'apiModule' => 'MarketingCampaignsReporting',
                    'apiAction' => 'getName',
                    'label'     => 'campaign_hashed',
                    'expanded'  => 0
                ]
            ]
        ];

        $apiToTest[] = [
            'API.getRowEvolution',
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateTime,
                'testSuffix'             => 'getKeyword',
                'otherRequestParameters' => [
                    'date'      => '2013-01-20,2013-01-25',
                    'period'    => 'day',
                    'apiModule' => 'MarketingCampaignsReporting',
                    'apiAction' => 'getKeyword',
                    'label'     => 'mot_clé_pépère',
                    'expanded'  => 0
                ]
            ]
        ];

        // check that API does not return an error if an subtable id is given that does not exist
        $apiToTest[] = [
            'MarketingCampaignsReporting.getKeywordContentFromNameId',
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateTime,
                'period'                 => 'month',
                'otherRequestParameters' => ['idSubtable' => 20],
            ]
        ];

        return $apiToTest;
    }

    public function getReferrerApiForTesting()
    {
        $dateWithPluginEnabled = self::$fixture->dateTimeWithPluginEnabled;
        $apiToTest             = [];

        $api = [
            'Referrers.getCampaigns',
        ];

        $phpVersionPrefix = version_compare(PHP_VERSION, 8.2, '<') && !self::$isMariaDB ? 'min_php_' : '';

        $apiToTest[] = [
            $api,
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => ['day'],
                'testSuffix'             => $phpVersionPrefix . 'expanded',
                'otherRequestParameters' => ['expanded' => 1],
            ]
        ];
        $apiToTest[] = [
            $api,
            [
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => ['day'],
                'testSuffix'             => $phpVersionPrefix . 'flat',
                'otherRequestParameters' => ['flat' => 1, 'expanded' => 0],
            ]
        ];

        return $apiToTest;
    }
}

TrackSeveralCampaignsTest::$fixture = new TrackAdvancedCampaigns();

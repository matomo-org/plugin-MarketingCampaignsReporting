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
use Piwik\Plugin\Manager;
use Piwik\Plugins\MarketingCampaignsReporting\tests\Fixtures\TrackAdvancedCampaigns;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

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

        $apiToTest[] = array(
            'API.get',
            array(
                'idSite'  => self::$fixture->idSite,
                'date'    => $dateWithPluginEnabled,
                'periods' => array('day'),
            )
        );

        $apiToTest[] = array(
            'Live.getLastVisitsDetails',
            array(
                'idSite'  => self::$fixture->idSite,
                'date'    => $dateWithPluginEnabled,
                'periods' => array('day'),
            )
        );

        $api = array(
            'MarketingCampaignsReporting'
        );

        $columnsToHide = '';

        $apiToTest[] = array(
            $api,
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => array('day'),
                'testSuffix'             => 'expanded',
                'otherRequestParameters' => array('expanded' => 1),
                'xmlFieldsToRemove'      => $columnsToHide
            )
        );
        $apiToTest[] = array(
            $api,
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => array('day'),
                'testSuffix'             => 'flat',
                'otherRequestParameters' => array('flat' => 1, 'expanded' => 0),
                'xmlFieldsToRemove'      => $columnsToHide
            )
        );
        $apiToTest[] = array(
            $api,
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => array('day'),
                'testSuffix'             => 'segmentedMatchAll',
                'segment'                => 'campaignName!=test;campaignKeyword!=test;campaignSource!=test;campaignMedium!=test;campaignContent!=test;campaignId!=test',
                'otherRequestParameters' => array('flat' => 1, 'expanded' => 0),
                'xmlFieldsToRemove'      => $columnsToHide
            )
        );
        $apiToTest[] = array(
            $api,
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => array('day'),
                'testSuffix'             => 'segmentedMatchNone',
                'segment'                => 'campaignName==test,campaignKeyword==test,campaignSource==test,campaignMedium==test,campaignContent==test,campaignId==test',
                'otherRequestParameters' => array('flat' => 1, 'expanded' => 0),
                'xmlFieldsToRemove'      => $columnsToHide
            )
        );

        $apiToTest[] = array(
            'MarketingCampaignsReporting',
            array(
                'idSite'            => 'all',
                'date'              => $dateTime,
                'periods'           => 'day',
                'setDateLastN'      => true,
                'testSuffix'        => 'multipleDatesSites_',
                'xmlFieldsToRemove' => $columnsToHide
            )
        );

        // row evolution tests for methods that also use Referrers plugin data
        $apiToTest[] = array(
            'API.getRowEvolution',
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateTime,
                'testSuffix'             => 'getName',
                'otherRequestParameters' => array(
                    'date'      => '2013-01-20,2013-01-25',
                    'period'    => 'day',
                    'apiModule' => 'MarketingCampaignsReporting',
                    'apiAction' => 'getName',
                    'label'     => 'campaign_hashed',
                    'expanded'  => 0
                )
            )
        );

        $apiToTest[] = array(
            'API.getRowEvolution',
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateTime,
                'testSuffix'             => 'getKeyword',
                'otherRequestParameters' => array(
                    'date'      => '2013-01-20,2013-01-25',
                    'period'    => 'day',
                    'apiModule' => 'MarketingCampaignsReporting',
                    'apiAction' => 'getKeyword',
                    'label'     => 'mot_clé_pépère',
                    'expanded'  => 0
                )
            )
        );

        // check that API does not return an error if an subtable id is given that does not exist
        $apiToTest[] = array(
            'MarketingCampaignsReporting.getKeywordContentFromNameId',
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateTime,
                'period'                 => 'month',
                'otherRequestParameters' => ['idSubtable' => 20],
            )
        );

        return $apiToTest;
    }

    public function getReferrerApiForTesting()
    {
        $dateWithPluginEnabled = self::$fixture->dateTimeWithPluginEnabled;
        $apiToTest             = [];

        $api = array(
            'Referrers.getCampaigns',
        );

        $apiToTest[] = array(
            $api,
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => array('day'),
                'testSuffix'             => 'expanded',
                'otherRequestParameters' => array('expanded' => 1),
            )
        );
        $apiToTest[] = array(
            $api,
            array(
                'idSite'                 => self::$fixture->idSite,
                'date'                   => $dateWithPluginEnabled,
                'periods'                => array('day'),
                'testSuffix'             => 'flat',
                'otherRequestParameters' => array('flat' => 1, 'expanded' => 0),
            )
        );

        return $apiToTest;
    }
}

TrackSeveralCampaignsTest::$fixture = new TrackAdvancedCampaigns();

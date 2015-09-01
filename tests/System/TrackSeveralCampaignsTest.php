<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\AdvancedCampaignReporting\tests\System;

use Piwik\Plugins\AdvancedCampaignReporting\tests\Fixtures\TrackAdvancedCampaigns;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

/**
 * @group AdvancedCampaignReporting
 * @group AdvancedCampaignReporting_TrackSeveralCampaignsTest
 * @group AdvancedCampaignReporting_Integration
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
     * @group TrackSeveralCampaignsTest
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        $dateWithPluginEnabled = self::$fixture->dateTimeWithPluginEnabled;

        $apiToTest[] = array('API.get',
                             array('idSite'  => self::$fixture->idSite,
                                   'date'    => $dateWithPluginEnabled,
                                   'periods' => array('day'),
                             ));

        $api = array(
                    'Referrers.getCampaigns',
                    'AdvancedCampaignReporting'
        );
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => $dateWithPluginEnabled,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'expanded',
                                   'otherRequestParameters' => array('expanded' => 1)
                             ));
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => $dateWithPluginEnabled,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'flat',
                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
                             ));
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => $dateWithPluginEnabled,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'segmentedMatchAll',
                                   'segment'                => 'campaignName!=test;campaignKeyword!=test;campaignSource!=test;campaignMedium!=test;campaignContent!=test;campaignId!=test',
                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
                             ));
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => $dateWithPluginEnabled,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'segmentedMatchNone',
                                   'segment'                => 'campaignName==test,campaignKeyword==test,campaignSource==test,campaignMedium==test,campaignContent==test,campaignId==test',
                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
                             ));

        $apiToTest[] = array('AdvancedCampaignReporting', array(
            'idSite' => 'all',
            'date' => self::$fixture->dateTime,
            'periods' => 'day',
            'setDateLastN' => true,
            'testSuffix' => 'multipleDatesSites_',
        ));

        // row evolution tests for methods that also use Referrers plugin data
        $apiToTest[] = array('API.getRowEvolution', array(
            'idSite' => self::$fixture->idSite,
            'date' => self::$fixture->dateTime,
            'testSuffix' => 'getName',
            'otherRequestParameters' => array(
                'date'      => '2013-01-20,2013-01-25',
                'period'    => 'day',
                'apiModule' => 'AdvancedCampaignReporting',
                'apiAction' => 'getName',
                'label'     => 'campaign_hashed',
                'expanded'  => 0
            )
        ));

        $apiToTest[] = array('API.getRowEvolution', array(
            'idSite' => self::$fixture->idSite,
            'date' => self::$fixture->dateTime,
            'testSuffix' => 'getKeyword',
            'otherRequestParameters' => array(
                'date'      => '2013-01-20,2013-01-25',
                'period'    => 'day',
                'apiModule' => 'AdvancedCampaignReporting',
                'apiAction' => 'getKeyword',
                'label'     => 'mot_clé_pépère',
                'expanded'  => 0
            )
        ));

        return $apiToTest;
    }

}

TrackSeveralCampaignsTest::$fixture = new TrackAdvancedCampaigns();

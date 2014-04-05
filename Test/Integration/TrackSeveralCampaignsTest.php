<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AdvancedCampaignReporting\tests;

use Piwik\Plugins\AdvancedCampaignReporting\tests\fixtures\SimpleFixtureTrackFewVisits;
use Piwik\Plugins\AdvancedCampaignReporting\tests\fixtures\TrackAdvancedCampaigns;

/**
 * @group AdvancedCampaignReporting
 * @group TrackSeveralCampaignsTest
 * @group Integration
 */
class TrackSeveralCampaignsTest extends \IntegrationTestCase
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
        $apiToTest[] = array('API.get',
                             array('idSite'  => self::$fixture->idSite,
                                   'date'    => self::$fixture->dateTime,
                                   'periods' => array('day'),
                             ));

        $api = array(
                    'Referrers.getCampaigns',
                    'AdvancedCampaignReporting'
        );
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => self::$fixture->dateTime,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'expanded',
                                   'otherRequestParameters' => array('expanded' => 1)
                             ));
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => self::$fixture->dateTime,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'flat',
                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
                             ));
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => self::$fixture->dateTime,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'segmentedMatchAll',
                                   'segment'                => 'campaignName!=test;campaignKeyword!=test;campaignSource!=test;campaignMedium!=test;campaignContent!=test;campaignId!=test',
                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
                             ));
        $apiToTest[] = array($api,
                             array('idSite'                 => self::$fixture->idSite,
                                   'date'                   => self::$fixture->dateTime,
                                   'periods'                => array('day'),
                                   'testSuffix'             => 'segmentedMatchNone',
                                   'segment'                => 'campaignName==test,campaignKeyword==test,campaignSource==test,campaignMedium==test,campaignContent==test,campaignId==test',
                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
                             ));

        return $apiToTest;
    }

}

TrackSeveralCampaignsTest::$fixture = new TrackAdvancedCampaigns();


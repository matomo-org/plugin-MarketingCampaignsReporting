<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Integration;

use Piwik\Date;
use Piwik\Plugins\Live\API as LiveAPI;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignContent;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignGroup;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignId;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignKeyword;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignMedium;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignPlacement;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSource;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group MarketingCampaignsReporting
 * @group Plugins
 */
class ForceNewVisitTest extends IntegrationTestCase
{
    protected $idSite = null;

    /**
     * @var Date
     */
    protected $testDate = null;

    /**
     * @var \PiwikTracker
     */
    protected $tracker = null;

    public function setUp(): void
    {
        $testVars                                      = new \Piwik\Tests\Framework\TestingEnvironmentVariables();
        $configOverride                                = $testVars->configOverride;
        $configOverride['MarketingCampaignsReporting'] = [
            (new CampaignName())->getColumnName()      => 'pk_campaign,custom_name_parameter',
            (new CampaignKeyword())->getColumnName()   => 'pk_keyword,custom_keyword_parameter',
            (new CampaignSource())->getColumnName()    => 'pk_source,custom_source_parameter',
            (new CampaignMedium())->getColumnName()    => 'pk_medium,custom_medium_parameter',
            (new CampaignContent())->getColumnName()   => 'pk_content,custom_content_parameter',
            (new CampaignId())->getColumnName()        => 'pk_id,custom_id_parameter',
            (new CampaignGroup())->getColumnName()     => 'pk_group,custom_group_parameter',
            (new CampaignPlacement())->getColumnName() => 'pk_placement,custom_placement_parameter',
        ];
        $testVars->configOverride                      = $configOverride;
        $testVars->save();

        parent::setUp();

        $this->idSite = Fixture::createWebsite('2016-01-01 00:00:01', 0, 'TestSite', 'http://example.com');

        $this->testDate = Date::factory('now')->setTime('00:00:01');

        $this->tracker = Fixture::getTracker(
            $this->idSite,
            $this->testDate->toString('Y-m-d H:i:s'),
            $defaultInit = true,
            $useLocal = false
        );
    }

    /**
     * Check visits without parameters are tracked correctly and not forced as new ones
     */
    public function testTrackingNormalPageViews()
    {
        $url = $this->getUrlForTracking([]);

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track visit'));

        $this->assertVisits(1, 1, 1);

        $this->moveTimeForward(0.05);

        $url = $this->getUrlForTracking([], 'anotherpage');

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track another action'));

        $this->assertVisits(1, 1, 2);
    }

    /**
     * Two requests with the same parameters shouldn't be counted as new visit
     */
    public function testTrackingWithSameParameters()
    {
        $url = $this->getUrlForTracking([
            'pk_campaign'  => 'custom name',
            'pk_keyword'   => 'custom keyword',
            'pk_source'    => 'custom source',
            'pk_medium'    => 'custom medium',
            'pk_content'   => 'custom content',
            'pk_id'        => 'custom id',
            'pk_group'     => 'custom group',
            'pk_placement' => 'custom placement',
        ]);

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track visit with custom campaign parameters'));

        $this->assertVisits(1, 1, 1);

        $this->moveTimeForward(0.05);

        $url = $this->getUrlForTracking([
            'pk_campaign'  => 'custom name',
            'pk_keyword'   => 'custom keyword',
            'pk_source'    => 'custom source',
            'pk_medium'    => 'custom medium',
            'pk_content'   => 'custom content',
            'pk_id'        => 'custom id',
            'pk_group'     => 'custom group',
            'pk_placement' => 'custom placement',
        ], 'anotherpage');

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track another time with same campaign parameters'));

        $this->assertVisits(1, 1, 2);
    }

    /**
     * When campaign parameters changes and core should detect a new visit
     * Plugin wouldn't detect a new visit here as `utm_*` parameters are not configured
     */
    public function testTrackingWithCoreParameters()
    {
        $url = $this->getUrlForTracking([
            'pk_campaign'  => 'custom name',
            'pk_keyword'   => 'custom keyword',
            'pk_source'    => 'custom source',
            'pk_medium'    => 'custom medium',
            'pk_content'   => 'custom content',
            'pk_id'        => 'custom id',
            'pk_group'     => 'custom group',
            'pk_placement' => 'custom placement',
        ]);

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track visit with custom campaign parameters'));

        $this->assertVisits(1, 1, 1);

        $this->moveTimeForward(0.05);

        $url = $this->getUrlForTracking([
            'utm_campaign' => 'new name',
            'utm_term'     => 'new keyword',
        ], 'anotherpage');

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track another visit with different campaign parameters'));

        $this->assertVisits(2, 1, 2);
    }

    /**
     * When campaign parameters changes and plugin should detect new visit
     * Core wouldn't detect a new visit here as `custom_*` parameters are not configured
     */
    public function testTrackingWithPluginParameters()
    {
        $url = $this->getUrlForTracking([
            'pk_campaign'  => 'custom name',
            'pk_keyword'   => 'custom keyword',
            'pk_source'    => 'custom source',
            'pk_medium'    => 'custom medium',
            'pk_content'   => 'custom content',
            'pk_id'        => 'custom id',
            'pk_group'     => 'custom group',
            'pk_placement' => 'custom placement',
        ]);

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track visit with custom campaign parameters'));

        $this->assertVisits(1, 1, 1);

        $this->moveTimeForward(0.05);

        $url = $this->getUrlForTracking([
            'custom_name_parameter'    => 'new name',
            'custom_keyword_parameter' => 'new keyword',
        ], 'anotherpage');

        $this->tracker->setUrl($url);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track another visit with different campaign parameters'));

        $this->assertVisits(2, 1, 2);
    }

    private function assertVisits($visitsExpected, $uniqueVisitsExpected, $actionsExpected)
    {
        $counters = LiveAPI::getInstance()->getCounters($this->idSite, 3600, false,
            ['visits', 'visitors', 'actions']);

        $this->assertEquals($visitsExpected, $counters[0]['visits']);
        $this->assertEquals($uniqueVisitsExpected, $counters[0]['visitors']);
        $this->assertEquals($actionsExpected, $counters[0]['actions']);
    }

    private function getUrlForTracking($params, $path = '')
    {
        return 'http://example.com/' . $path . '?' . http_build_query($params);
    }

    protected function moveTimeForward($hourForward)
    {
        $this->tracker->setForceVisitDateTime($this->testDate->addHour($hourForward)->getDatetime());
    }
}
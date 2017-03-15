<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link    http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AdvancedCampaignReporting\tests\Integration;

use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignContent;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignId;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignKeyword;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignMedium;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignName;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignSource;
use Piwik\Plugins\SitesManager\API as SitesManager;
use Piwik\Plugins\AdvancedCampaignReporting\API as AdvancedCampaignReportingAPI;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group AdvancedCampaignReporting
 * @group Plugins
 */
class CustomDimensionConfigTest extends IntegrationTestCase
{

    /** @var string $testUrl */
    private $testUrl;

    /** @var int $idSite */
    private $idSite;

    /** @var \Piwik_LocalTracker $tracker */
    private $tracker;

    /** @var \DateTime $testDate */
    private $testDate;

    public function setUp()
    {
        $testVars                                    = new \Piwik\Tests\Framework\TestingEnvironmentVariables();
        $configOverride                              = $testVars->configOverride;
        $configOverride['AdvancedCampaignReporting'] = [
            (new CampaignName())->getColumnName()    => 'pk_campaign,custom_name_parameter',
            (new CampaignKeyword())->getColumnName() => 'pk_keyword,custom_keyword_parameter',
            (new CampaignSource())->getColumnName()  => 'pk_source,custom_source_parameter',
            (new CampaignMedium())->getColumnName()  => 'pk_medium,custom_medium_parameter',
            (new CampaignContent())->getColumnName() => 'pk_content ,custom_content_parameter',
            (new CampaignId())->getColumnName()      => 'pk_id, custom_id_parameter'
        ];
        $testVars->configOverride                    = $configOverride;
        $testVars->save();

        parent::setUp();
    }

    public function testTrackingWithCustomParameters()
    {
        $this->givenWebsite('TestWebsite');

        $this->givenUrl('http://example.com/?custom_name_parameter=%s&custom_keyword_parameter=%s&custom_source_parameter=%s&custom_content_parameter=%s&custom_medium_parameter=%s&custom_id_parameter=%s');

        $this->givenTrackerConfiguration();

        $this->whenWebsiteTracksUrlWithCustomCampaignParameters();

        $this->thenNameDimensionShouldBeTracked();

        $this->thenKeywordDimensionShouldBeTracked();

        $this->thenSourceDimensionShouldBeTracked();

        $this->thenMediumDimensionShouldBeTracked();

        $this->thenContentDimensionShouldBeTracked();
    }

    private function thenNameDimensionShouldBeTracked()
    {
        $api = AdvancedCampaignReportingAPI::getInstance();

        $nameReport = $api->getName(
            $this->idSite,
            'day',
            $this->testDate->format('Y-m-d')
        );

        $this->assertEquals(
            'custom_name_value',
            $nameReport->getColumn('label')[0]
        );
    }

    private function thenKeywordDimensionShouldBeTracked()
    {
        $api = AdvancedCampaignReportingAPI::getInstance();

        $keywordReport = $api->getKeyword(
            $this->idSite,
            'day',
            $this->testDate->format('Y-m-d')
        );

        $this->assertEquals(
            'custom_keyword_value',
            $keywordReport->getColumn('label')[0]
        );
    }

    private function thenSourceDimensionShouldBeTracked()
    {
        $api = AdvancedCampaignReportingAPI::getInstance();

        $keywordReport = $api->getSource(
            $this->idSite,
            'day',
            $this->testDate->format('Y-m-d')
        );

        $this->assertEquals(
            'custom_source_value',
            $keywordReport->getColumn('label')[0]
        );
    }

    private function thenMediumDimensionShouldBeTracked()
    {
        $api = AdvancedCampaignReportingAPI::getInstance();

        $keywordReport = $api->getMedium(
            $this->idSite,
            'day',
            $this->testDate->format('Y-m-d')
        );

        $this->assertEquals(
            'custom_medium_value',
            $keywordReport->getColumn('label')[0]
        );
    }

    private function thenContentDimensionShouldBeTracked()
    {
        $api = AdvancedCampaignReportingAPI::getInstance();

        $keywordReport = $api->getContent(
            $this->idSite,
            'day',
            $this->testDate->format('Y-m-d')
        );

        $this->assertEquals(
            'custom_content_value',
            $keywordReport->getColumn('label')[0]
        );
    }

    private function whenWebsiteTracksUrlWithCustomCampaignParameters()
    {
        $this->tracker->setUrl($this->testUrl);

        Fixture::checkResponse($this->tracker->doTrackPageView('Track visit with custom campaign parameters'));
    }

    private function givenWebsite($name)
    {
        $sitesManager = SitesManager::getInstance();

        $this->idSite = $sitesManager->addSite(
            $name,
            'http://example.com'
        );
    }

    private function givenTrackerConfiguration()
    {
        $this->testDate = new \DateTime();

        $this->tracker = Fixture::getTracker(
            $this->idSite,
            $this->testDate->format('U'),
            $defaultInit = true,
            $useLocal = false
        );
    }

    private function givenUrl($url)
    {
        $this->testUrl = sprintf(
            $url,
            'custom_name_value',
            'custom_keyword_value',
            'custom_source_value',
            'custom_content_value',
            'custom_medium_value',
            'custom_id_value'
        );
    }
}

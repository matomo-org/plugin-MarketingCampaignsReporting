<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Integration;

use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignContent;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignId;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignKeyword;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignMedium;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSource;
use Piwik\Plugins\SitesManager\API as SitesManager;
use Piwik\Plugins\MarketingCampaignsReporting\API as MarketingCampaignsReportingAPI;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group MarketingCampaignsReporting
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

    public function setUp(): void
    {
        $testVars                                    = new \Piwik\Tests\Framework\TestingEnvironmentVariables();
        $configOverride                              = $testVars->configOverride;
        $configOverride['MarketingCampaignsReporting'] = [
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
        $api = MarketingCampaignsReportingAPI::getInstance();

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
        $api = MarketingCampaignsReportingAPI::getInstance();

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
        $api = MarketingCampaignsReportingAPI::getInstance();

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
        $api = MarketingCampaignsReportingAPI::getInstance();

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
        $api = MarketingCampaignsReportingAPI::getInstance();

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

    protected static function configureFixture($fixture)
    {
        parent::configureFixture($fixture);
        $fixture->createSuperUser = true;
    }
}

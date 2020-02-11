<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Unit\Campaign;

use Piwik\Plugins\MarketingCampaignsReporting\Campaign\CampaignDetector;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignContent;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignId;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignKeyword;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignMedium;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSource;
use Piwik\Tracker\Request;

/**
 * @group MarketingCampaignsReporting
 * @group Plugins
 */
class CampaignDetectorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideRequestData
     * @param Request $request
     * @param array   $campaignParams
     * @param mixed   $expectedOutput
     */
    public function testDetectCampaignFromRequest(Request $request, array $campaignParams, $expectedOutput)
    {
        $detector   = new CampaignDetector();
        $dimensions = $detector->detectCampaignFromRequest($request, $campaignParams);

        $this->assertEquals($expectedOutput, $dimensions);
    }

    /**
     * @dataProvider provideVisitData
     * @param array $visitorInfo
     * @param array $campaignParams
     * @param       $expectedOutput
     */
    public function testDetectCampaignFromVisit(array $visitorInfo, array $campaignParams, $expectedOutput)
    {
        $detector   = new CampaignDetector();
        $dimensions = $detector->detectCampaignFromVisit($visitorInfo, $campaignParams);

        $this->assertEquals($expectedOutput, $dimensions);
    }

    public function provideRequestData()
    {
        return [
            'normal query string'                         => [
                'request'        => $this->createRequestMock(
                    'http://example.com/?pk_campaign=campName&pk_kwd=sdf1'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name'    => 'campname',
                    'campaign_keyword' => 'sdf1'
                ]
            ],
            'query string behind hash'                    => [
                'request'        => $this->createRequestMock(
                    'https://whatever.com/#/category/sub/1?pk_campaign=campName2&pk_kwd=sdf2'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name'    => 'campname2',
                    'campaign_keyword' => 'sdf2'
                ]
            ],
            'normal query string with google parameters'  => [
                'request'        => $this->createRequestMock(
                    'http://example.com/?utm_campaign=campName&utm_term=sdf1'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name'    => 'campname',
                    'campaign_keyword' => 'sdf1'
                ]
            ],
            'query string behind hash with google params' => [
                'request'        => $this->createRequestMock(
                    'https://whatever.com/#/category/sub/1?utm_campaign=campName2&utm_term=sdf2'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name'    => 'campname2',
                    'campaign_keyword' => 'sdf2'
                ]
            ],
            'no query string'                             => [
                'request'        => $this->createRequestMock(
                    'https://whatever.com/cat1/cat2'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => false
            ]
        ];
    }

    public function provideVisitData()
    {
        return [
            [
                'visitorInfo'    => [
                    'campaign_name'    => 'camapaign1',
                    'campaign_keyword' => 'kwd1',
                ],
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name'    => 'camapaign1',
                    'campaign_keyword' => 'kwd1'
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getCampaignParameters()
    {
        return [
            (new CampaignName())->getColumnName()    => ['pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign'],
            (new CampaignKeyword())->getColumnName() => ['pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term'],
            (new CampaignSource())->getColumnName()  => ['pk_source', 'utm_source'],
            (new CampaignMedium())->getColumnName()  => ['pk_medium', 'utm_medium'],
            (new CampaignContent())->getColumnName() => ['pk_content', 'utm_content'],
            (new CampaignId())->getColumnName()      => ['pk_cid', 'utm_id'],
        ];
    }

    private function createRequestMock($returnedUrl)
    {
        $mock = $this->getMockBuilder('\Piwik\Tracker\Request')
                     ->disableOriginalConstructor()
                     ->getMock();

        $mock
            ->expects($this->exactly(1))
            ->method('getParam')
            ->will($this->returnValue($returnedUrl));

        return $mock;
    }

}

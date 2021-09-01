/*!
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later: https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */
describe("MarketingCampaignsReporting_Reports", function () {
    this.timeout(0);

    this.fixture = 'Piwik\\Plugins\\MarketingCampaignsReporting\\tests\\Fixtures\\TrackAdvancedCampaigns';

    var url = "?module=CoreHome&action=index&idSite=1&date=2013-01-23&period=month#?category=Referrers_Referrers&subcategory=Referrers_Campaigns";

    it("should load correctly when user goes to normal campaigns page", async function () {
        await page.goto(url);
        expect(await page.screenshotSelector('.pageWrap,.expandDataTableFooterDrawer')).to.matchImage('loaded');
    });

    it('should show visits log with campaign details', async function() {
        await page.goto("?module=CoreHome&action=index&idSite=1&period=month&date=2013-01-23#?idSite=1&period=month&date=2013-01-23&category=General_Visitors&subcategory=Live_VisitorLog");

        await page.waitForNetworkIdle();
        await page.waitForSelector('.dataTableVizVisitorLog');

        var report = await page.$('.reporting-page');
        expect(await report.screenshot()).to.matchImage('visitor_log');
    });
});
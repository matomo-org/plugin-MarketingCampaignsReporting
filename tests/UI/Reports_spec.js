/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
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
});
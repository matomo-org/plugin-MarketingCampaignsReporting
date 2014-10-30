/*!
 * Copyright (C) Piwik PRO - All rights reserved.
 *
 * AdvancedCampaignReporting report tests.
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
describe("AdvancedCampaignReporting_Reports", function () {
    this.timeout(0);

    this.fixture = 'Piwik\\Plugins\\AdvancedCampaignReporting\\tests\\fixtures\\TrackAdvancedCampaigns';

    var url = "?module=CoreHome&action=index&idSite=1&date=2013-01-23&period=month#/module=Referrers&action=menuGetCampaigns";

    it("should load correctly when user goes to normal campaigns page", function (done) {
        expect.screenshot("loaded").to.be.captureSelector('.pageWrap,.expandDataTableFooterDrawer', function (page) {
            page.load(url);
        }, done);
    });
});
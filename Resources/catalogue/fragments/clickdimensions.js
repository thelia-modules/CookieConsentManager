tarteaucitron.services.clickdimensions = {
    "key": "clickdimensions",
    "type": "ads",
    "name": "Click Dimensions",
    "uri": "https://clickdimensions.com/legal/privacy-policy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.clickdimensionsAccountKey === undefined || tarteaucitron.user.clickdimensionsDomain === undefined) {
            return;
        }

        tarteaucitron.addScript('https://analytics-eu.clickdimensions.com/ts.js', '', function() {
            window.cdAnalytics = new clickdimensions.Analytics('analytics-eu.clickdimensions.com');
            window.cdAnalytics.setAccountKey(tarteaucitron.user.clickdimensionsAccountKey);
            window.cdAnalytics.setDomain(tarteaucitron.user.clickdimensionsDomain);
            window.cdAnalytics.setScore(typeof(cdScore) == "undefined" ? 0 : (cdScore == 0 ? null : cdScore));
            window.cdAnalytics.trackPage();
        });
    }
};

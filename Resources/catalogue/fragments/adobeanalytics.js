tarteaucitron.services.adobeanalytics = {
    "key": "adobeanalytics",
    "type": "analytic",
    "name": "Adobe Analytics",
    "uri": "https://www.adobe.com/privacy/policy.html",
    "needConsent": true,
    "cookies": ['s_ecid', 's_cc', 's_sq', 's_vi', 's_fid'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.adobeanalyticskey === undefined) {
            return;
        }
        tarteaucitron.addScript('//assets.adobedtm.com/launch-' + tarteaucitron.user.adobeanalyticskey + '.min.js');
    }
};

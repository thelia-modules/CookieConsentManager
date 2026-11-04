tarteaucitron.services.microanalytics = {
    "key": "microanalytics",
    "type": "analytic",
    "name": "MicroAnalytic",
    "uri": "https://microanalytics.io/page/privacy",
    "needConsent": false,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.microanalyticsID === undefined) {
            return;
        }

        tarteaucitron.addScript('https://microanalytics.io/js/script.js', tarteaucitron.user.microanalyticsID, undefined, true, "data-host", "https://microanalytics.io");
    }
};

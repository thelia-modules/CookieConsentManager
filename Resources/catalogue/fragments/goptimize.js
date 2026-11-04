tarteaucitron.services.goptimize = {
    "key": "goptimize",
    "type": "other",
    "name": "Google Optimize",
    "needConsent": true,
    "cookies": ["CONSENT", "NID"],
    "uri": "https://policies.google.com/privacy",
    "js": function () {
        "use strict";

        if (tarteaucitron.user.goptimize === undefined) {
            return;
        }

        tarteaucitron.addScript('https://www.googleoptimize.com/optimize.js?id=' + tarteaucitron.user.goptimize);
    }
};

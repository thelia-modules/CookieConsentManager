tarteaucitron.services.weply = {
    "key": "weply",
    "type": "support",
    "name": "Weply",
    "uri": "https://weply.chat/",
    "needConsent": true,
    "cookies": ['weply.analytics', 'logglytrackingsession'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.weplyId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://app.weply.chat/widget/' + tarteaucitron.user.weplyId);
    }
};

tarteaucitron.services.statable = {
    "key": "statable",
    "type": "analytic",
    "name": "Statable",
    "uri": "https://statable.com/privacy",
    "needConsent": false,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.statableSiteId === undefined) {
            return;
        }

        if (tarteaucitron.user.statableHost === undefined) {
            tarteaucitron.user.statableHost = 'statable.com';
        }

        tarteaucitron.addScript('https://' + tarteaucitron.user.statableHost + '/js/' + tarteaucitron.user.statableSiteId + '/s.js');
    }
};

tarteaucitron.services.equativ = {
    "key": "equativ",
    "type": "ads",
    "name": "Equativ",
    "uri": "https://equativ.com/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.equativId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://ced.sascdn.com/tag/' + tarteaucitron.user.equativId + '/smart.js');
    }
};

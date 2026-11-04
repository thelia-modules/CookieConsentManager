tarteaucitron.services.dialoginsight = {
    "key": "dialoginsight",
    "type": "support",
    "name": "Dialog Insight",
    "uri": "https://www.dialoginsight.com/politique-de-confidentialite/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.dialogInsightId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://t.ofsys.com/js/Journey/1/' + tarteaucitron.user.dialogInsightId + '/DI.Journey-min.js');
    }
};

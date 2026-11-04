tarteaucitron.services.smartsupp = {
    "key": "smartsupp",
    "type": "support",
    "name": "Smartsupp",
    "uri": "https://www.smartsupp.com/help/privacy/",
    "needConsent": true,
    "cookies": ['ssupp.vid', 'ssupp.visits', 'AWSALB', 'AWSALBCORS'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.smartsuppKey === undefined) {
            return;
        }

        window._smartsupp = window._smartsupp || {};
        window._smartsupp.key = tarteaucitron.user.smartsuppKey;
        window.smartsupp = function () {
            window.smartsupp._.push(arguments)
        };
        window.smartsupp._ = [];

        tarteaucitron.addScript('https://www.smartsuppchat.com/loader.js');
    }
};

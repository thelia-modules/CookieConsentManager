tarteaucitron.services.collectchat = {
    "key": "collectchat",
    "type": "other",
    "name": "Collect Chat",
    "uri": "https://collect.chat/privacy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.collectchatId === undefined) {
            return;
        }

        window.CollectId = tarteaucitron.user.collectchatId;

        tarteaucitron.addScript('https://collectcdn.com/launcher.js');
    }
};

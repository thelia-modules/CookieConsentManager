tarteaucitron.services.tolkai = {
    "key": "tolkai",
    "type": "other",
    "name": "tolk.ai",
    "uri": "https://www.tolk.ai/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.tolkaiBot === undefined) {
            return;
        }

        window.tcfbot = tarteaucitron.user.tolkaiBot;
        window.TcfWbchtParams = { behaviour: 'default' };
        window.display = 'iframe';
        tarteaucitron.addScript('https://script.tolk.ai/iframe-latest.js');
    }
};

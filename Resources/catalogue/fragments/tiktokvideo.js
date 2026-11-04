tarteaucitron.services.tiktokvideo = {
    "key": "tiktokvideo",
    "type": "video",
    "name": "Tiktok Video",
    "uri": "https://www.tiktok.com/legal/page/eea/privacy-policy/en",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        tarteaucitron.addScript('https://www.tiktok.com/embed.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'tiktokvideo';
        tarteaucitron.fallback(['tiktok-embed'], function (elem) {
            return tarteaucitron.engage(id);
        });
    }
};

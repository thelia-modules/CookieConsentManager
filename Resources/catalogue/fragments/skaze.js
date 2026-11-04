tarteaucitron.services.skaze = {
    "key": "skaze",
    "type": "ads",
    "name": "Skaze",
    "uri": "https://www.skaze.com/fr/politique/politique-de-confidentialite/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.skazeIdentifier === undefined) {
            return;
        }

        window.skaze = window.skaze || {};
        tarteaucitron.addScript('https://events.sk.ht/' + tarteaucitron.user.skazeIdentifier + '/lib.js', '', function() {
            skaze.cmd = skaze.cmd || [];
            skaze.cmd.push(function() {
                skaze.init({ siteIdentifier : tarteaucitron.user.skazeIdentifier });

                if (typeof tarteaucitron.user.skazeMore === 'function') {
                    tarteaucitron.user.skazeMore();
                }
            });
        });
    }
};

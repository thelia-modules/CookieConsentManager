tarteaucitron.services.criteoonetag = {
    "key": "criteoonetag",
    "type": "ads",
    "name": "Criteo OneTag",
    "uri": "https://www.criteo.com/privacy/",
    "needConsent": true,
    "cookies": ['uid', 'tk', 'uid3pd'],
    "js": function() {
        "use strict";
        if (tarteaucitron.user.criteoonetagAccount === undefined) return;

        window.criteo_q = window.criteo_q || [];
        window.criteo_q.push({
            event: "setAccount",
            account: tarteaucitron.user.criteoonetagAccount
        })

        tarteaucitron.addScript('//static.criteo.net/js/ld/ld.js', '', function() {
            if (typeof tarteaucitron.user.criteoonetagMore === 'function') {
                tarteaucitron.user.criteoonetagMore();
            }
        });
    }
};

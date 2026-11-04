tarteaucitron.services.antvoice = {
    "key": "antvoice",
    "type": "ads",
    "name": "antvoice",
    "uri": "https://www.antvoice.com/fr/privacy-policy/",
    "needConsent": true,
    "cookies": ['antvoice'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.antvoiceId === undefined) {
            return;
        }

        window.avDataLayer = window.avDataLayer || [];
        window.avtag = window.avtag || function(_cmd,_p) {
            window.avDataLayer.push({cmd:_cmd,p:_p});
        }
        window.avtag('setConsent', {consent:true});
        window.avtag('init', {id: tarteaucitron.user.antvoiceId});

        tarteaucitron.addScript('https://static.avads.net/avtag.min.js');
    }
};

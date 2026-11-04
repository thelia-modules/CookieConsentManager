tarteaucitron.services.typekit = {
    "key": "typekit",
    "type": "api",
    "name": "Typekit (adobe)",
    "uri": "https://www.adobe.com/privacy.html",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.typekitId === undefined) {
            return;
        }
        tarteaucitron.addScript('//use.typekit.net/' + tarteaucitron.user.typekitId + '.js', '', function () {
            try {
                Typekit.load();
            } catch (e) { }
        });
    }
};

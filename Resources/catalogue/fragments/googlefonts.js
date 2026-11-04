tarteaucitron.services.googlefonts = {
    "key": "googlefonts",
    "type": "api",
    "name": "Google Webfonts",
    "uri": "https://policies.google.com/privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.googleFonts === undefined) {
            return;
        }
        tarteaucitron.addScript('//ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js', '', function () {

            if (tarteaucitron.user.googleFonts instanceof Array) {
                WebFont.load({
                    google: {
                        families: tarteaucitron.user.googleFonts
                    }
                });
            } else {
                WebFont.load({
                    google: {
                        families: [tarteaucitron.user.googleFonts]
                    }
                });
            }
        });
    }
};

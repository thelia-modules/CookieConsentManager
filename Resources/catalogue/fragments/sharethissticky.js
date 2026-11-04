tarteaucitron.services.sharethissticky = {
    "key": "sharethissticky",
    "type": "social",
    "name": "ShareThis Sticky",
    "uri": "https://sharethis.com/fr/privacy/",
    "needConsent": true,
    "cookies": ['_stid','_stidv','pubconsent'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.sharethisStickyProperty === undefined) {
            return;
        }

        tarteaucitron.addScript("https://platform-api.sharethis.com/js/sharethis.js#property=" + tarteaucitron.user.sharethisStickyProperty + "&product=sticky-share-buttons");
    }
};

tarteaucitron.services.faciliti = {
    "key": "faciliti",
    "type": "other",
    "name": "Facil'ITI",
    "uri": "https://www.facil-iti.com/legal-terms/",
    "needConsent": true,
    "cookies": ['FACIL_ITI'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.facilitiID === undefined) {
            return;
        }

        (function () {
            var fs = document.createElement("script");
            fs.setAttribute("src", "https://cdn.facil-iti.app/tags/faciliti-tag.min.js");
            fs.dataset.applicationIdentifier = tarteaucitron.user.facilitiID;
            document.head.appendChild(fs);
        }());
    }
};

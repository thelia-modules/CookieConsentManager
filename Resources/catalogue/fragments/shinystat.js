tarteaucitron.services.shinystat = {
    "key": "shinystat",
    "type": "analytic",
    "name": "Shinystat",
    "uri": "https://www.shinystat.com/en/opt-out.html",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.shinystatUser === undefined) {
            return;
        }

        tarteaucitron.addScript('https://codice.shinystat.com/cgi-bin/getcod.cgi?USER=' + tarteaucitron.user.shinystatUser);
    }
};

tarteaucitron.services.wysistathightrack = {
    "key": "wysistathightrack",
    "type": "analytic",
    "name": "Wysistat (privacy by design)",
    "uri": "https://www.wysistat.net/webanalytics/exemption-cnil/",
    "needConsent": false,
    "cookies": ['wysistat'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.wysistatNom === undefined) {
            return;
        }

        window._wsq = window._wsq || [];
        window._wsq.push(['_setNom', tarteaucitron.user.wysistatNom]);
        window._wsq.push(['_wysistat']);

        tarteaucitron.addScript('https://www.wysistat.com/ws.jsa');
    }
};

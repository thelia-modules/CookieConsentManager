tarteaucitron.services.koban = {
    "key": "koban",
    "type": "analytic",
    "name": "Koban",
    "uri": "https://koban.cloud/tos",
    "needConsent": true,
    "cookies": ['kbntrk'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.kobanurl === undefined) {
            return;
        }
        if (tarteaucitron.user.kobanapi === undefined) {
            return;
        }
        window.KobanObject = 'kb';
        window.kb = window.kb || function () {
            window.kb.q = window.kb.q || [];
            window.kb.q.push(arguments);
        };
        window.kb.l = new Date();
        kb('reg', tarteaucitron.user.kobanapi);
        tarteaucitron.addScript(tarteaucitron.user.kobanurl, '', function () {
        });
    }
};

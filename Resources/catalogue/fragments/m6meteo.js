tarteaucitron.services.m6meteo = {
    "key": "m6meteo",
    "type": "api",
    "name": "M6 Météo",
    "uri": "https://gdpr.m6tech.net/charte-confidentialite-m6-web-meteocity.pdf",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['tac_m6meteo'], function (x) {
            var id = tarteaucitron.getElemAttr(x, "data-id");

            tarteaucitron.addScript('https://www.meteocity.com/widget/js/'+id);

            return '<div id="cont_'+id+'"><div id="spa_'+id+'"><a id="a_'+id+'" href="#"></a> ©<a target="_top" href="https://www.meteocity.com">M6météo</a></div></div>';
        });
    },
    "fallback": function () {
        "use strict";
        var id = 'm6meteo';
        tarteaucitron.fallback(['tac_m6meteo'], function (elem) {

            return tarteaucitron.engage(id);
        });
    }
};

tarteaucitron.services.kwanko = {
    "key": "kwanko",
    "type": "ads",
    "name": "Kwanko",
    "uri": "https://www.kwanko.com/fr/rgpd/politique-gestion-donnees/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['tac_kwanko'], function (x) {
            var mclic = tarteaucitron.getElemAttr(x, "data-mclic");

            return '<img src="https://action.metaffiliation.com/trk.php?mclic=' + mclic + '" width="1" height="1" />';
        });
    },
    "fallback": function () {
        "use strict";
        var id = 'kwanko';
        tarteaucitron.fallback(['tac_kwanko'], function (elem) {
            return tarteaucitron.engage(id);
        });
    }
};

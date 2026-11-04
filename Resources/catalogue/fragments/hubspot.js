tarteaucitron.services.hubspot = {
    "key": "hubspot",
    "type": "analytic",
    "name": "Hubspot",
    "uri": "https://legal.hubspot.com/privacy-policy",
    "needConsent": true,
    "cookies": ['hubspotutk', 'fr', '__hstc', '__hssrc', '__hssc', '__cfduid'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.hubspotId === undefined) {
            return;
        }

        var tac_businessUnitId = "";
        if (tarteaucitron.user.hubspotBusinessUnitId !== undefined && tarteaucitron.user.hubspotBusinessUnitId !== null && tarteaucitron.user.hubspotBusinessUnitId !== "") {
            tac_businessUnitId = "?businessUnitId=" + tarteaucitron.user.hubspotBusinessUnitId;
        }

        tarteaucitron.addScript('//js.hs-scripts.com/' + tarteaucitron.user.hubspotId + '.js' + tac_businessUnitId, 'hs-script-loader');
    }
};

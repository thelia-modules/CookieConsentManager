tarteaucitron.services.abtasty = {
    "key": "abtasty",
    "type": "api",
    "name": "ABTasty",
    "uri": "https://www.abtasty.com/terms-of-use/",
    "needConsent": true,
    "cookies": ['ABTasty', 'ABTastySession'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.abtastyID === undefined) {
            return;
        }
        tarteaucitron.addScript('//try.abtasty.com/' + tarteaucitron.user.abtastyID + '.js');
    }
};

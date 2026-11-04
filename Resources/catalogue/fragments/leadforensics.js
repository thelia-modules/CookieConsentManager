tarteaucitron.services.leadforensics = {
    "key": "leadforensics",
    "type": "ads",
    "name": "Lead Forensics",
    "uri": "https://www.leadforensics.com/cookie-policy/",
    "needConsent": true,
    "cookies": ['ifuuid'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.leadforensicsId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://secure.team8save.com/js/sc/'+ tarteaucitron.user.leadforensicsId +'.js');
    }
};

tarteaucitron.services.adsenseauto = {
    "key": "adsenseauto",
    "type": "ads",
    "name": "Google Adsense Automatic",
    "uri": "https://adssettings.google.com/",
    "needConsent": true,
    "readmoreLink": "https://policies.google.com/technologies/partner-sites",
    "cookies": ['__gads'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.adsensecapub === undefined) {
            return;
        }
        tarteaucitron.addScript('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + tarteaucitron.user.adsensecapub, '', '', '', 'crossorigin', 'anonymous');
    }
};

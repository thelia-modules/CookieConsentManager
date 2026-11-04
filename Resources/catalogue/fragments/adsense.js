tarteaucitron.services.adsense = {
    "key": "adsense",
    "type": "ads",
    "name": "Google Adsense",
    "uri": "https://adssettings.google.com/",
    "needConsent": true,
    "readmoreLink": "https://policies.google.com/technologies/partner-sites",
    "cookies": ['__gads'],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['adsbygoogle'], '');
        tarteaucitron.addScript('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'adsense';
        tarteaucitron.fallback(['adsbygoogle'], tarteaucitron.engage(id));
    }
};

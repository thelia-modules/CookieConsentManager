tarteaucitron.services.adsensesearch = {
    "key": "adsensesearch",
    "type": "ads",
    "name": "Google Adsense Search",
    "uri": "https://adssettings.google.com/",
    "needConsent": true,
    "readmoreLink": "https://policies.google.com/technologies/partner-sites",
    "cookies": ['__gads'],
    "js": function () {
        "use strict";
        tarteaucitron.addScript('https://www.google.com/adsense/search/ads.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'adsensesearch';
        tarteaucitron.fallback(['afscontainer1'], tarteaucitron.engage(id));
    }
};

tarteaucitron.services.twitterwidgetsapi = {
    "key": "twitterwidgetsapi",
    "type": "api",
    "name": "X (formerly Twitter) Widgets API",
    "uri": "https://support.twitter.com/articles/20170514",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['tacTwitterAPI'], '');
        tarteaucitron.addScript('//platform.twitter.com/widgets.js', 'twitter-wjs');
    },
    "fallback": function () {
        "use strict";
        var id = 'twitterwidgetsapi';
        tarteaucitron.fallback(['tacTwitterAPI'], tarteaucitron.engage(id));
    }
};

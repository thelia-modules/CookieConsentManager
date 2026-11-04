tarteaucitron.services.facebooklikebox = {
    "key": "facebooklikebox",
    "type": "social",
    "name": "Facebook (like box)",
    "uri": "https://www.facebook.com/policy.php",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['fb-like-box', 'fb-page'], '');
        tarteaucitron.addScript('//connect.facebook.net/' + tarteaucitron.getLocale() + '/sdk.js#xfbml=1&version=v2.3', 'facebook-jssdk');
        if (tarteaucitron.isAjax === true) {
            if (typeof FB !== "undefined") {
                FB.XFBML.parse();
            }
        }
    },
    "fallback": function () {
        "use strict";
        var id = 'facebooklikebox';
        tarteaucitron.fallback(['fb-like-box', 'fb-page'], tarteaucitron.engage(id));
    }
};

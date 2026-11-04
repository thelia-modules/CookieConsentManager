tarteaucitron.services.facebookcustomerchat = {
    "key": "facebookcustomerchat",
    "type": "social",
    "name": "Facebook (Customer Chat)",
    "uri": "https://www.facebook.com/policies/cookies/",
    "needConsent": true,
    "cookies": ['act','c_user','datr','dpr','presence','sb','wd','xs','/tr'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.facebookChatID === undefined) {
            return;
        }

        tarteaucitron.fallback(['fb-customerchat'], '');
        window.fbAsyncInit=function(){FB.init({appId:tarteaucitron.user.facebookChatID,autoLogAppEvents:!0,xfbml:!0,version:"v3.0"})};
        tarteaucitron.addScript('https://connect.facebook.net/' + tarteaucitron.getLocale() + '/sdk/xfbml.customerchat.js', 'facebook-jssdk');
    },
    "fallback": function () {
        "use strict";
        var id = 'facebookcustomerchat';
        tarteaucitron.fallback(['fb-customerchat'], tarteaucitron.engage(id));
    }
};

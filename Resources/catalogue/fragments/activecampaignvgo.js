tarteaucitron.services.activecampaignvgo = {
    "key": "activecampaignvgo",
    "type": "other",
    "name": "Active Campaign",
    "uri": "https://www.activecampaign.com/legal/privacy-policy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.activecampaignAccount === undefined) {
            return;
        }

        window.visitorGlobalObjectAlias="vgo";
        window[window.visitorGlobalObjectAlias]=window[window.visitorGlobalObjectAlias]||function(){(window[window.visitorGlobalObjectAlias].q=window[window.visitorGlobalObjectAlias].q||[]).push(arguments)};
        window[window.visitorGlobalObjectAlias].l=(new Date).getTime();

        tarteaucitron.addScript('https://diffuser-cdn.app-us1.com/diffuser/diffuser.js', '', function () {
            vgo('setAccount', tarteaucitron.user.activecampaignAccount);
            vgo('setTrackByDefault', true);
            vgo('process');
        });
    }
};

tarteaucitron.services.visiblee = {
    key: "visiblee",
    type: "analytic",
    name: "Visiblee",
    uri: "https://confidentiality.visiblee.io/fr/confidentialite",
    needConsent: true,
    cookies: ["visitor_v2", tarteaucitron.user.visibleedomain, "check", "campaign_ref_" + tarteaucitron.user.visibleedomain, "reload_" + tarteaucitron.user.visibleedomain],
    js: function () {
        "use strict";

        if (tarteaucitron.user.visibleeclientid === undefined) {
            return;
        }
        tarteaucitron.addScript('//www.link-page.info/tracking_' + tarteaucitron.user.visibleeclientid + '.js', 'visiblee');
    }
};

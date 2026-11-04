tarteaucitron.services.zoho = {
    "key": "zoho",
    "type": "support",
    "name": "Zoho SalesIQ",
    "uri": "https://www.zoho.com/gdpr.html",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.zohoWidgetCode === undefined) {
            return;
        }

        var $zoho=$zoho || {};
        $zoho.salesiq = $zoho.salesiq || {widgetcode:tarteaucitron.user.zohoWidgetCode, values:{},ready:function(){}};
        tarteaucitron.addScript('https://salesiq.zoho.eu/widget');
    }
};

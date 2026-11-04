tarteaucitron.services.zohopagesense = {
    "key": "zohopagesense",
    "type": "analytic",
    "name": "Zoho PageSense",
    "uri": "https://www.zoho.com/pagesense/cookie-policy.html",
    "needConsent": true,
    "cookies": ["zab_g_", "zabUserID", "zabVisitID", "zabSplit", "zabBucket", "zabHMBucket", "zpsfa_", "zfa", "zsr", "zabme", "zsd", "ps_payloadSeqId", "zabPZBucket", "zPersonalization", "zia_", "zpc", "zps_permission_status", "zps-tgr-dts", "zpspolls_", "zpsPollsBucket", "zpspb", "zpsPopupBucket", "zpssr", "zab_g_", "zab_", "zPersonalization"],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.zohoPageSenseProjectId === undefined || tarteaucitron.user.zohoPageSenseScriptHash === undefined) {
            return;
        }
        tarteaucitron.addScript('https://cdn-eu.pagesense.io/js/' + tarteaucitron.user.zohoPageSenseProjectId + '/' + tarteaucitron.user.zohoPageSenseScriptHash + '.js');
    }
};

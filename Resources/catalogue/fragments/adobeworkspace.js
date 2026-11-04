tarteaucitron.services.adobeworkspace = {
    "key": "adobeworkspace",
    "type": "analytic",
    "name": "Adobe - Analysis Workspace",
    "uri": "https://www.adobe.com/privacy/policy.html",
    "needConsent": true,
    "cookies": ['s_ecid', 's_cc', 's_sq', 's_vi', 's_fid'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.adobeworkspaceId1 === undefined || tarteaucitron.user.adobeworkspaceId2 === undefined || tarteaucitron.user.adobeworkspaceId3 === undefined) {
            return;
        }

        tarteaucitron.addScript('https://assets.adobedtm.com/'+tarteaucitron.user.adobeworkspaceId1+'/'+tarteaucitron.user.adobeworkspaceId2+'/launch-'+tarteaucitron.user.adobeworkspaceId3+'.min.js');
    }
};

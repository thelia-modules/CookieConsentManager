tarteaucitron.services.tolkaigenii = {
    "key": "tolkaigenii",
    "type": "support",
    "name": "Tolk.ai Genii",
    "uri": "https://www.tolk.ai/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.tolkaiGeniiProject === undefined) {
            return;
        }

        var script;
        script = document.createElement('script');
        script.id = "lightchat-bot";
        script.src = "https://genii-script.tolk.ai/lightchat.js";
        script.async = true;
        script.setAttribute("type", "module");
        script.setAttribute("project-id", tarteaucitron.user.tolkaiGeniiProject);
        document.getElementsByTagName('head')[0].appendChild(script);
    }
};

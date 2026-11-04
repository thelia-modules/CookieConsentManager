tarteaucitron.services.openai = {
    "key": "openai",
    "type": "ads",
    "name": "OpenAI",
    "uri": "https://openai.com/policies/ad-policies/",
    "needConsent": true,
    "cookies": ['__obref'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.openaiId === undefined) {
            return;
        }

        !function(w,d,s,u){if(w.oaiq)return;var q=function(){q.q.push(arguments)};q.q=[];w.oaiq=q;var j=d.createElement(s);j.async=1;j.src=u;var f=d.getElementsByTagName(s)[0];f.parentNode.insertBefore(j,f)}(window,document,"script","https://bzrcdn.openai.com/sdk/oaiq.min.js");
        oaiq("init",{pixelId:tarteaucitron.user.openaiId});
    }
};

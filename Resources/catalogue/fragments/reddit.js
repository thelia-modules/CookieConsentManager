tarteaucitron.services.reddit = {
    "key": "reddit",
    "type": "ads",
    "name": "Reddit",
    "uri": "https://business.reddithelp.com/helpcenter/s/article/Reddit-Advertising-Policy-Overview",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.redditInit === undefined) {
            return;
        }

        !function(w,d){if(!w.rdt){var p=w.rdt=function(){p.sendEvent?p.sendEvent.apply(p,arguments):p.callQueue.push(arguments)};p.callQueue=[];var t=d.createElement("script");t.src="https://www.redditstatic.com/ads/pixel.js",t.async=!0;var s=d.getElementsByTagName("script")[0];s.parentNode.insertBefore(t,s)}}(window,document);rdt('init',tarteaucitron.user.redditInit, {"aaid":tarteaucitron.user.redditAAID,"externalId":tarteaucitron.user.redditExternalId,"idfa":tarteaucitron.user.redditIDFA});rdt('track', 'PageVisit');
    }
};

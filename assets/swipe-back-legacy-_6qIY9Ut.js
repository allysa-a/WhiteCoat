System.register(["./vendor-ionic-legacy-DUdpqiHT.js","./vendor-vue-legacy-ClwUwp9p.js"],function(e,t){"use strict";var n,r,o;return{setters:[e=>{n=e.t,r=e.u,o=e.v},null],execute:function(){
/*!
             * (C) Ionic http://ionicframework.com - MIT License
             */
e("createSwipeBackGesture",(e,t,i,s,c)=>{const a=e.ownerDocument.defaultView;let u=n(e);const l=e=>u?-e.deltaX:e.deltaX;return r({el:e,gestureName:"goback-swipe",gesturePriority:101,threshold:10,canStart:r=>(u=n(e),(e=>{const{startX:t}=e;return u?t>=a.innerWidth-50:t<=50})(r)&&t()),onStart:i,onMove:e=>{const t=l(e)/a.innerWidth;s(t)},onEnd:e=>{const t=l(e),n=a.innerWidth,r=t/n,i=(e=>u?-e.velocityX:e.velocityX)(e),s=i>=0&&(i>.2||t>n/2),d=(s?1-r:r)*n;let v=0;if(d>5){const e=d/Math.abs(i);v=Math.min(e,540)}c(s,r<=0?.01:o(0,r,.9999),v)}})})}}});

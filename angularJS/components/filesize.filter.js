/*
 2015 Jason Mulligan
 @version 3.1.2
 */
"use strict";!function(a){var b=/b$/,c={bits:["B","kb","Mb","Gb","Tb","Pb","Eb","Zb","Yb"],bytes:["B","kB","MB","GB","TB","PB","EB","ZB","YB"]},d=function(a){var d=void 0===arguments[1]?{}:arguments[1],e=[],f=!1,g=0,h=void 0,i=void 0,j=void 0,k=void 0,l=void 0,m=void 0,n=void 0,o=void 0,p=void 0,q=void 0,r=void 0;if(isNaN(a))throw new Error("Invalid arguments");return j=d.bits===!0,p=d.unix===!0,i=void 0!==d.base?d.base:2,o=void 0!==d.round?d.round:p?1:2,q=void 0!==d.spacer?d.spacer:p?"":" ",r=void 0!==d.suffixes?d.suffixes:{},n=void 0!==d.output?d.output:"string",h=void 0!==d.exponent?d.exponent:-1,m=Number(a),l=0>m,k=i>2?1e3:1024,l&&(m=-m),0===m?(e[0]=0,e[1]=p?"":"B"):((-1===h||isNaN(h))&&(h=Math.floor(Math.log(m)/Math.log(k))),h>8&&(g=1e3*g*(h-8),h=8),g=2===i?m/Math.pow(2,10*h):m/Math.pow(1e3,h),j&&(g=8*g,g>k&&(g/=k,h++)),e[0]=Number(g.toFixed(h>0?o:0)),e[1]=c[j?"bits":"bytes"][h],!f&&p&&(j&&b.test(e[1])&&(e[1]=e[1].toLowerCase()),e[1]=e[1].charAt(0),"B"===e[1]?(e[0]=Math.floor(e[0]),e[1]=""):j||"k"!==e[1]||(e[1]="K"))),l&&(e[0]=-e[0]),e[1]=r[e[1]]||e[1],"array"===n?e:"exponent"===n?h:"object"===n?{value:e[0],suffix:e[1]}:e.join(q)};"undefined"!=typeof exports?module.exports=d:"function"==typeof define?define(function(){return d}):a.filesize=d}("undefined"!=typeof global?global:window);

(function(){
  'use strict';

  angular.module('app')
    .filter('filesizeG',function(){
      return function(value,options){
        return filesize(value*1024*1024*1024,options||{});
      };
    });
})();
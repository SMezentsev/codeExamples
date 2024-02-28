/**
 * Created by Sergey on 02.10.19.
 */

(function(){
    'use strict';

    angular.module('app')
        .directive('stringEnd',function(){
            return {
                restrict: 'A',
                link: function(scope,el,attr){

                    var options = scope.$eval(attr.options)||{};
                    var word = '';

                    var n = Math.abs(options.value) % 100;
                    var n1 = n % 10;

                    if (n > 10 && n < 20) {
                        word = options.chars[2];
                    }

                    if (n1 > 1 && n1 < 5) {
                        word = options.chars[1];
                    }

                    if (n1 == 1) {
                        word = options.chars[0];
                    }

                    el.html(word);
                }
            };
        });

})();

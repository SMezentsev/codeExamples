/**
 * Created by chilinduh on 11.10.15.
 */

(function(){
    'use strict';

//
//    angular.module('app')
//        .directive('pattern',function() {
//            return {
//                restrict: 'A',
//                require: 'ngModel',
//                priority: -1,
//                link: function(scope, element, attrs) {
//
//                    console.log('attrs',attrs)
//                    var pattern = scope.$eval(attrs.pattern);
//                    if(pattern.method == 'onlyChars') {
//
//                        element.on('keypress', function(e) {
//
//                            var char = e.char || String.fromCharCode(e.charCode);
//
//                            if (/[A-Za-zА-Яа-я]/i.test(char)) {
//
//                            } else {
//                                e.preventDefault();
//                                return false;
//                            }
//                        });
//                    }
//
//                }
//            };
//
//        });

    angular.module('app')
        .directive('uppercase',function($filter) {
            return {
                restrict: 'A',
                require: 'ngModel',
                priority: -1,
                link: function(scope, element, attrs) {

                    var options = scope.$eval(attrs.uppercase);
                    scope.$watch(function(){
                        return element.val();
                    }, function (val1,val2) {

                        element.val($filter('uppercase')(element.val()));

//                        if(typeof options.transliteration != 'undefined') {
//                            console.log('1')
//                            element.val(transliteration(element.val()));
//                        }

                    });
                }
            };

            function transliteration(value) {
                var space = '';

                var transl = {
                    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e', 'ж': 'zh',
                    'з': 'z', 'и': 'i', 'й': 'j', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
                    'о': 'o', 'п': 'p', 'р': 'r','с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h',
                    'ц': 'c', 'ч': 'ch', 'ш': 'sh', 'щ': 'sh','ъ': space, 'ы': 'y', 'ь': space, 'э': 'e', 'ю': 'yu', 'я': 'ya',
                    ' ': space, '_': space, '`': space, '~': space, '!': space, '@': space,
                    '#': space, '$': space, '%': space, '^': space, '&': space, '*': space,
                    '(': space, ')': space,'-': space, '\=': space, '+': space, '[': space,
                    ']': space, '\\': space, '|': space, '/': space,'.': space, ',': space,
                    '{': space, '}': space, '\'': space, '"': space, ';': space, ':': space,
                    '?': space, '<': space, '>': space, '№':space
                };

                var char = value.toLowerCase();

                for(var i=0;i<char.length;i++) {

                }
//
//                if(transl[value] != undefined) {
//                    return transl[value];
//                } else {
//                    return value;
//                }
            }
        });

})();
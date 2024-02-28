/**
 * Created by chilinduh on 12.10.15.
 */


/**
 * Created by chilinduh on 11.10.15.
 */

(function(){
    'use strict';


    angular.module('app')
        .directive('transliteration',function() {
            return {
                restrict: 'A',
                require: 'ngModel',
                priority: -1,
                link: function(scope, element, attrs) {

                    var pattern = attrs.pattern;
                    element.on('keypress', function(e) {

                        var char = e.char || String.fromCharCode(e.charCode);
                        element.val(transliteration(char));
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

                value = value.toLowerCase();

                if(transl[value] != undefined) {
                    return transl[value];
                } else {
                    return value;
                }
            }


        });

})();


(function(){
    'use strict';

    angular.module('app')
        .directive('ngResetForm',function($timeout,$compile) {
            return {
                restrict: 'A',
                scope: {
                    ngModel: '='
                },
                link: function(scope, el, attrs) {

                    var resetFormButton = $(el).find(".reset");

                    if(resetFormButton) {

                        $(resetFormButton).click(function(){
                            scope.OrdersDataForm.$setPristine();
                        })
                    }
                }
            };
        })
        .directive('ogPlacehold', function(){
            return {
              restrict: 'A',
              link: function(scope, element, attrs) {

                var insert = function() {
                  element.val(attrs.ogPlacehold);
                };

                element.bind('blur', function() {
                  if(element.val() === '')
                    insert();
                });

                element.bind('focus', function() {
                  if(element.val() === attrs.ogPlacehold)
                    element.val('');
                });

                if(element.val() === '')
                  insert();
              }
            }
        });

})();

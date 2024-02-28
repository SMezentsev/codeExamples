(function(){
  'use strict';

  angular.module('app')
    .directive('restrictInput', function() {
      return {
        restrict: 'A',
        require: 'ngModel',
        link: function(scope, element, attr, ctrl) {
          ctrl.$parsers.unshift(function(viewValue) {
            var options = scope.$eval(attr.restrictInput);
            if (!options.regex && options.type) {
              switch (options.type) {
                case 'alphanumeric': options.regex = '^[a-zA-Z0-9]*$'; break;
                default: options.regex = '';
              }
            }
            var reg = new RegExp(options.regex);
            var newValue = reg.test(viewValue) ? viewValue :  (reg.test(ctrl.$modelValue) ? ctrl.$modelValue : '');
            ctrl.$viewValue = newValue;
            ctrl.$commitViewValue();
            ctrl.$render();
            return newValue;
          });
        }
      };
    });

})();


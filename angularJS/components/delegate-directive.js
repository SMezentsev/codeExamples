'use strict';

/**
 * catch events
 * delegate="{}"
 */

angular.module('app')
.directive('delegate',function(){
  return {
    restrict: 'A',
    scope: false,
    link: function(scope,el,attr){
      var targets = scope.$eval(attr.delegate);
      console.log(targets);
    }
  };
});
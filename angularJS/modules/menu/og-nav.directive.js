(function(){
  'use strict';

  angular.module('app')
    .directive(ogNavDirective);


  function ogNavDirective(){
    var n = {};
    return {
      restrict: 'EA',
      terminal: true,
      compile: function(el){
        n.el = el;
        return function($scope){
          d.menu = $scope.$eval(el.attr('menu'));
        };
      }
    };
  }

})();
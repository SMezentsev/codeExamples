(function(){
  'use strict';

  angular.module('app')
    .directive('loader',function(){
      return {
        restrict: 'A',
        template: '<img ng-src="ajax-loader.gif" alt=""/>',
        link: function(scope,el,attr){
          var size = attr.size||30;
          el.find('img').width(size).height(size);
        }
      };
    });

})();
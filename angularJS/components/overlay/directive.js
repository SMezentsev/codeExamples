(function(){
  'use strict';

  angular.module('app')
    .directive('overlay',function(){
      return {
        restrict: 'A',
        link: function(scope,el,attr){
          el.closest('[overlay-container]').hover(function(){
            el.addClass('is-visible');
          },function(){
            el.removeClass('is-visible');
          });
        }
      };
    });

})();
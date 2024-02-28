(function(){
  'use strict';

  angular.module('app')
  .directive('btnLoading',function($timeout){
    return {
      restrict: 'A',
      link: function(scope,el,attr){
        var loader = $('<i class="btn-loader is-hidden icon fa fa-globe fa-spin"></i>');
        el.append(loader);
        var t;
        scope.$watch(attr.btnLoading,function(loading){
          if (loading) {
            el.attr('disabled',true);
            t = $timeout(function(){
              loader.removeClass('is-hidden');
            },200);
          } else {
            $timeout.cancel(t);
            loader.addClass('is-hidden');
            el.attr('disabled',false);
          }
        });
      }
    };
  });

})();
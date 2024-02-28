(function(){
  'use strict';

  angular.module('app')
  .directive('faIcon',function(){
    return {
      restrict: 'E',
      replace: true,
      template: '<i class="fa"></i>',
      link: function(scope,el,attrs){
        var done = false;
        angular.forEach(attrs.$attr,function(attr){
          if (!done && el.attr(attr)=='' && !attr.match(/^ng-/)) {
            el.removeAttr(attr);
            //var icons = attr.split('-and-');
            //el.addClass('mdi-'+icons[0]);
            el.addClass('fa-'+attr);
  //          if (icons[1]) {
  //            el.append('<i class="mdi mdi-"'+icons[1]+'></i>');
  //          }
            done = true;
          }
        });
      }
    };
  });

})();

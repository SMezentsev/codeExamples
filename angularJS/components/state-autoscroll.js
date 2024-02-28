'use strict';

angular.module('app')
.run(function($rootScope,$timeout,$window){
  $rootScope.$on('$stateChangeStart',function(e,data){
    if (data.scrollTop!==undefined) {
      $timeout(function(){
        if(data && data.scrollTop) {
          $window.pageYOffset = data.scrollTop;
        }

        //console.log($window);
      });
    }
  });
});

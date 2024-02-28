angular.module('app')
.run(function($rootScope,$location,$timeout){

    var activeClass = 'active-link';

    $rootScope.$on('$stateChangeSuccess',function(){
      $timeout(function(){
        $('.'+activeClass).removeClass(activeClass);
        $('.link-parent.active').removeClass('active');
        $timeout(function(){
          var active = $('[href="'+$location.path()+'"]:not(base)').addClass(activeClass);
          active.closest('.link-parent').addClass('active');
          active.closest('.link-parent-proxy').find('.link-parent').addClass('active');
        });
      });
    });

});
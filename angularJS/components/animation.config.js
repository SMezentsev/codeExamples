(function(){
  'use strict';

  angular.module('app')
    .config(function($animateProvider){
      $animateProvider.classNameFilter(/^((?!(bg-stripes)).)*$/);
    });
})();
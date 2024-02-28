angular.module('app')
.directive('mdSelect',function(){
  return {
    require: 'ngModel',
    restrict: 'E',
    link: function(scope,el,attr,model){
      var container = el.parent().addClass('md-select-container');
      if (container[0]) {
        scope.$watch(function(){ return model.$viewValue },function(val){
          if (val==undefined || val=='') {
            container.removeClass('md-input-has-value');
          } else {
            container.addClass('md-input-has-value');
          }
        });
      }
    }
  };
});
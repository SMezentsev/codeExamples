angular.module('app')
.directive('clearable',function(){
  return {
    require: 'ngModel',
    restrict: 'A',
    link: function(scope,el,attr,model){
      var clearButton = $('<span class="clearable-button">&times;</span>');
      el.after(clearButton);
      clearButton.click(function(){
        model.$setViewValue(undefined);
        el.val('').blur();
      });
    }
  };
});
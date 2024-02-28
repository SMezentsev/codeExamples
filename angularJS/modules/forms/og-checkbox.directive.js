(function(){
  'use strict';

  angular.module('app')
    .directive('ogCheckbox',function($compile,$timeout){
      return {
        restrict: 'A',
        require: 'ngModel',
        compile: function(el){
          var id,stateOn,stateOff;
          return {
            pre: function(scope,el,attr){
              id = Math.round(Math.random()*1e10);
              el.attr('type','checkbox');
              el.attr('id',id);
              el.removeAttr('og-checkbox');

              if (el.attr('state-on')!=undefined) {
                stateOn = el.attr('state-on');
                el.removeAttr('state-on');
              }

              if (el.attr('state-off')!=undefined) {
                stateOff = el.attr('state-off');
                el.removeAttr('state-off');
              }

              if (stateOn)
                stateOn = $compile('<span translate="'+stateOn+'"></span>')(scope);
              if (stateOff)
                stateOff = $compile('<span translate="'+stateOff+'"></span>')(scope);

              $compile(el[0])(scope);
            },
            post: function(scope,el,attr,model){
              el.hide();
              var display = $(
                '<label for="'+id+'" class="og-checkbox form-control">' +

                  '<span class="icons">' +
                  '<i class="icon icon-unchecked fa fa-square-o"></i>' +
                  '<i class="icon icon-checked fa fa-check"></i>' +
                  '</span>' +

                '</label>');

              var states = $('<span class="states">' +
                '<span class="state state-on"></span>' +
                '<span class="state state-off"></span>' +
                '</span>');

              if (stateOn||stateOff) {
                display.append(states);
                if (stateOn)
                  states.find('.state-on').append(stateOn);
                if (stateOff)
                  states.find('.state-off').append(stateOff);
              }

              el.after(display);
              display.append(el);

              var unwatch = scope.$watch(function(){
                return model.$modelValue;
              },function(val){
                if (val) {
                  display.addClass('checked');
                } else {
                  display.removeClass('checked');
                }
              });

              scope.$on('$destroy',unwatch);
            }
          }
        }
      };
    });

})();
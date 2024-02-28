(function(){
  'use strict';

  angular.module('app')
    .directive('fhLayout',fhLayoutDirective);

  function fhLayoutDirective(){
    return {
      restrict: 'A',
      link: function(scope,wrap,attr){
        var fh = {
          body: $('body')
        };
        fh.content = $('[fh-content]',wrap[0]);
        fh.toolbars = $('[fh-toolbar]',wrap[0]);

        if (fh.content.length) {
          scope.$watch(function(){
            return $('[fh-content]',wrap[0]).height();
          },function(ch){
            fh.contentHeight = ch;
            _update(fh);
          });
        }

        $(window).on('resize',function(){
          scope.$apply();
        });
      }
    };

  }

  function _update(fh) {
    if (fh.contentHeight!==undefined) {
      var toolbarsHeight = _getToolbarsHeight(fh);
      if ((fh.contentHeight+toolbarsHeight)>fh.body.height()) {
        fh.content.height(fh.body.height()-toolbarsHeight);
      } else {
        fh.content.height('auto');
      }
    }
  }

  function _getToolbarsHeight(fh) {
    var toolbarsHeight = 0;
    angular.forEach(fh.toolbars,function(toolbar){
      toolbarsHeight += $(toolbar).height();
    });
    return toolbarsHeight;
  }

})();
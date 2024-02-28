(function(){
  'use strict';

  angular.module('app')
    .directive('ogSidebarLayout',ogSidebarLayoutDirective)
    //.directive('ogSidebarToggle',ogSidebarToggleDirective)
  ;

  function ogSidebarLayoutDirective(){
    return {
      restrict:'A',
      link: function(scope,el){

        var toggle = el.on('click','[og-sidebar-toggle]',function(e) {
          e.preventDefault();
          el.toggleClass("toggled");
        });

      }
    };

//    function _adjustScrolls(scrolls) {
//      scrolls.each(function(){
//        var scroll = $(this);
//        var layout = scroll.closest('[scroll-y-wrap]');
//        var height = layout.height();
//        var fixed = scroll.parent().find('[scroll-y-fixed]');
//        if (fixed.length) {
//          height -= fixed.height();
//        }
//        scroll.height(height);
//      });
//    }
  }

//  function ogSidebarToggleDirective(){
//    return {
//      restrict:'A',
//      link: function(scope,el){
//        el.click(function(e) {
//          e.preventDefault();
//          el.closest('[og-sidebar-layout]').toggleClass("toggled");
//        });
//      }
//    };
//  }

})();
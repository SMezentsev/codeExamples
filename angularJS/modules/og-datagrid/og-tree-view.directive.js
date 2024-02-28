(function(){
  'use strict';

  angular.module('app')
  .directive('ogTreeView',function($compile,$timeout){
    return {
      restrict: 'A',
      terminal: true,
      scope: true,
      compile: function(el,attr){

        el.attr('ng-cloak','');

        var tpl = el.clone();


        el.addClass('og-tree-view');

        var id = Math.round(Math.random()*1e10);

        var collection = el.attr('og-tree-view');
        var item = el.attr('og-tree-view-item');

        //tpl.attr('og-tree-view-item',id+'child as '+item);

        el.removeAttr('og-tree-view');
        el.removeAttr('og-tree-view-item');

        el.attr('ng-repeat',item+' in '+collection+' track by $index');

        var open = el.find('[og-tree-view-open]');

        open.attr('ng-click','open'+id+'('+item+',$event)');



        //var children = el.find('[og-tree-view-children]');

        return {
          pre: function(scope,el,attr){

            $timeout(function(){
              $compile(el[0])(scope);
            });
          },
          post: function(scope,el,attr){
            var openMethod = scope.$eval(open.attr('og-tree-view-open'));


            scope['open'+id] = function(item,e){

              var children= $(e.currentTarget).closest('.og-tree-view').next();
              if (!children.hasClass('og-tree-view-children')) {
                children = $('<div class="og-tree-view-children"></div>')
                  .insertAfter($(e.currentTarget).closest('.og-tree-view'));
              }
              if (!children.hasClass('with-data')) {
                children.addClass('with-data open');

                var sid = Math.round(Math.random()*1e10);
                var stpl = tpl.clone();
                children.append($compile('<div ng-if="!children'+sid+'.length"><span loader size="20"></span></div>')(scope));
                stpl.attr('og-tree-view','children'+sid);

                children.append(
                  $compile(stpl)(scope)
                );

                openMethod('children'+sid,item);

                scope.$on('$destroy',function(){
                  og.del(scope,'open'+id);
                  og.del(scope,'children'+sid);
                });

              } else {
                if (children.hasClass('open')) {
                  children.removeClass('open').hide();
                } else {
                  children.addClass('open').show();
                }
              }

              //children.append(tpl);
//              console.log(children);
//             var tplClone = tpl.clone();
//              children.append(tplClone);
            };

            scope.$on('$destroy',function(){
              og.del(scope,'open'+id);
              og.del(scope,'open'+id);
            });
          }
        };
      }
    };
  });

})();
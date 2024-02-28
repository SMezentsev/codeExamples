(function(){
  'use strict';

  angular.module('app')
  .directive('ogVerticalNav', function (Menu,$timeout) {
    return {
      restrict: 'A',
      scope: true,
      template:
        '<ul id="menu-{{ id }}" role="navigation" class="og-vertical-nav">' +
          '<li class="vertical-menu-item" ng-class="{\'link-parent-proxy\':item.items,\'children-open\':item.open}" ng-repeat="item in items track by $index">' +
            '<a ng-href="{{item.url||\'#\'}}" ng-click="open(item)" ng-class="{\'link-parent\':item.items}" aria-label="{{item.id}}">' +
              '<span ng-if="item.icon"><fa-icon class="fa fa-{{item.icon}}"></fa-icon></span>' +
              '<p><span translate="{{ id }}.{{ item.id }}"></span></p>' +
              '<span><fa-icon angle-down ng-if="item.items" class="toggle-icon"></fa-icon></span>' +
            '</a>' +
            '<div ng-if="item.items" class="children">' +
              '<ul class="sub-menu">' +
                '<li class="vertical-menu-item" ng-repeat="subitem in item.items track by $index">' +
                  '<a ng-href="{{subitem.url}}" aria-label="{{subitem.id}}">' +
                    '<span ng-if="subitem.icon"><fa-icon class="fa fa-{{subitem.icon}}"></fa-icon></span>' +
                    '<p><span translate="{{ id }}.{{ item.id }}-{{ subitem.id }}"></span></p>' +
                  '</a>' +
                '</li>' +
              '</ul>' +
            '</div>' +
          '</li>' +
        '</ul>'
      ,
      link: function (scope, el, attr) {

              scope.id = attr.ogVerticalNav;

//          scope.$watch(function(){
//              return scope.$eval(el.attr('menuLoad'));
//          },function(){
//
//
//
//          });


          $timeout(function(){
              scope.items = Menu(attr.ogVerticalNav).getItems();
              scope.open = function (item) {
                  item.open = !item.open;
                  Menu(attr.ogVerticalNav).remember(scope.items);
              };
          },500)



//        scope.$watch(function(){
//          return attr.ogVerticalNav;
//        },function(id){
//          scope.id = id;
//          scope.items = Menu(id).getItems();
//          scope.open = function (item) {
//            item.open = !item.open;
//            Menu(id).remember(scope.items);
//          };
//        });
      }
    };

  })

;

})();



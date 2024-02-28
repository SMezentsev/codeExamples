angular.module('app')
  .service('Menu', function (AppConfig, webStorage) {

    var m = AppConfig.menu || {};

    return function (id) {
      return {
        getItems: function () {
          var menu = setSavedStates(id);
          return menu || [];
        },
        remember: function (items) {
          var states = [];
          var saveItemsStates = function (items, prefix) {
            prefix = prefix ? prefix + '.' : '';
            angular.forEach(items, function (item, i) {
              if (item.open)
                states.push(prefix + item.id);
              if (item.items) {
                saveItemsStates(item.items, item.id);
              }
            });
          };
          saveItemsStates(items);
          webStorage.add('menu-' + id, states);
        }
      };

      function setSavedStates(id) {
        var states = webStorage.get('menu-' + id);
        if (!states || !states instanceof Array) {
          states = [];
          webStorage.add('menu-' + id, states);
        }
        if (states.length) {
          var setItemsStates = function (items, prefix) {
            prefix = prefix ? prefix + '.' : '';
            angular.forEach(items, function (item) {
              var idx = states.indexOf(prefix + item.id);
              if (idx >= 0) {
                item.open = true;
              }
              if (item.items) {
                setItemsStates(item.items, item.id);
              }
            });
          };
          setItemsStates(m[id]);
        }
        return m[id];
      }
    };
  })

  .directive('verticalMenu', function (Menu,$compile) {
    return {
      restrict: 'A',
      scope: true,
      template:
        '<div id="menu-{{ id }}" role="navigation" class="vertical-menu">' +
          '<div class="vertical-menu-item" ng-class="{\'link-parent-proxy\':item.items,\'children-open\':item.open}" ng-repeat="item in items track by $index">' +
            '<md-button ng-href="{{item.url||\'\'}}" ng-click="open(item)" ng-class="{\'link-parent\':item.items}" aria-label="{{item.id}}">' +
              '<md-icon ng-if="item.icon" class="mdi mdi-{{item.icon}}"></md-icon>' +
              '<b class="vertical-menu-item-content" translate="{{ id }}.{{ item.id }}"></b>' +
              '<md-icon chevron-down ng-if="item.items" class="toggle-icon"></md-icon>' +
            '</md-button>' +
            '<div ng-if="item.items" class="children">' +
              '<div class="sub-menu">' +
                '<div class="vertical-menu-item" ng-repeat="subitem in item.items track by $index">' +
                  '<md-button ng-href="{{subitem.url}}" aria-label="{{subitem.id}}">' +
                    '<span ng-if="subitem.icon"><md-icon class="mdi mdi-{{subitem.icon}}"></md-icon></span>' +
                    '<span class="vertical-menu-item-content" translate="{{ id }}.{{ item.id }}-{{ subitem.id }}"></span>' +
                  '</md-button>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>'
      ,
      link: function (scope, el, attr) {
        scope.$watch(function(){
          return attr.verticalMenu;
        },function(id){
          scope.id = id;
          scope.items = Menu(id).getItems();
          scope.open = function (item) {
            item.open = !item.open;
            Menu(id).remember(scope.items);
          };
        });
      }
    };

  })

//  .directive('activeNav', ['$location', function ($location) {
//    return {
//      restrict: 'A',
//      link: function (scope, element, attrs) {
//        var anchor = element[0];
//        if (element[0].tagName.toUpperCase() != 'A')
//          anchor = element.find('a')[0];
//        var path = anchor.href;
//
//        scope.location = $location;
//        scope.$watch('location.absUrl()', function (newPath) {
//          path = normalizeUrl(path);
//          newPath = normalizeUrl(newPath);
//
//          if (path === newPath ||
//            (attrs.activeNav === 'nestedTop' && newPath.indexOf(path) === 0)) {
//            element.addClass('active');
//          } else {
//            element.removeClass('active');
//          }
//        });
//      }
//
//    };
//
//    function normalizeUrl(url) {
//      if (url[url.length - 1] !== '/')
//        url = url + '/';
//      return url;
//    }
//
//  }]
//  )

;
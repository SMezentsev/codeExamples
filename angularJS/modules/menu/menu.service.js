(function(){
  'use strict';

  angular.module('app')
  .service('Menu', function (AppConfig, webStorage, Auth) {

    var m = AppConfig.menu || {};

    return function (id) {
      return {
        getItems: function () {
          var menu = setSavedStates(id);
          menu = menu||[];

          var filterItems = function(input){
            var output = [];

            angular.forEach(input,function(item){
              var outItem;
              var allow = false;

              if (item.access) {
                if (Auth.check(item.access)) {
                  allow = true;
                }
              } else {
                allow = true;
              }

              if (allow) {
                outItem = angular.copy(item);
                if (item.items) {
                  var subitems = filterItems(item.items);
                  if (subitems.length) {
                    outItem.items = subitems;
                    output.push(outItem);
                  }
                } else {
                  output.push(outItem);
                }

              }
            });

            return output;
          };

          return filterItems(menu);
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
  ;

})();
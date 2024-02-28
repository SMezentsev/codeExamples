(function(){
    'use strict';

    angular.module('app')
        .directive('ogDepend',function(AppConfig, webStorage) {
            return {
                restrict: 'A',
                scope: {
                    ngModel: '='
                },
                link: function(scope, el, attrs) {

                    if(!checkUrlAccess(attrs.ogDepend)) {
                        $(el).hide();
                    }

                    function checkUrlAccess(depend) {

                        var user = webStorage.get(AppConfig.prefix + 'user');

                        console.log('AppConfig.access.urlAccess',user)

                        if(AppConfig && typeof AppConfig.access.urlAccess != 'undefined') {

                            var access = false;

                            var rules;




                            if(AppConfig.access.urlAccess[depend]) {

                                rules = AppConfig.access.urlAccess[depend].access.permissions;

                                angular.forEach(user.permissions, function(perm){

                                    if((rules.indexOf(perm) + 1)) {

                                        access = true;
                                    }

                                });

                            }

                        }

                        return access;
                    }

                }
            };
        });

})();